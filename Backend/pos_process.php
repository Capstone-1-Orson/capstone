<?php
// Backend/pos_process.php
// Handles POS order placement AND auto-deducts ingredient stock.

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'conn.php';

// ── Read JSON payload from POS.php ────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit();
}

$table_no      = $conn->real_escape_string($data['table_no']    ?? '01');
$status        = 'Done';
$total_amt     = floatval($data['total_amt']    ?? 0);
$discount_amt  = floatval($data['discount_amt'] ?? 0);
$discount_type = $conn->real_escape_string($data['discount_type'] ?? '');
$items         = $data['items'];

// ══════════════════════════════════════════════════════════════
//  PRE-CHECK: verify all ingredients have enough stock BEFORE
//  touching the database. Collect shortages and reject early.
// ══════════════════════════════════════════════════════════════
$stmtCheck = $conn->prepare(
    "SELECT i.id, i.name, i.stock_qty, i.unit, mi.qty_needed, m.name AS menu_name
     FROM menu_ingredients mi
     JOIN ingredients i ON i.id = mi.ingredient_id
     JOIN menu m        ON m.id = mi.menu_id
     WHERE mi.menu_id = ?"
);

// We accumulate how much of each ingredient the full order needs
// key = ingredient_id, value = [ name, unit, available, needed ]
$needs = [];

foreach ($items as $item) {
    $menu_id     = intval($item['menu_id']);
    $qty         = intval($item['qty']);
    // Support removed as [{id,name}] objects OR plain id array
    $raw_removed = $item['removed_ingredient_ids'] ?? [];
    $removed_ids = array_map('intval', array_map(
        fn($r) => is_array($r) ? ($r['id'] ?? 0) : $r,
        $raw_removed
    ));

    $stmtCheck->bind_param('i', $menu_id);
    $stmtCheck->execute();
    $res = $stmtCheck->get_result();

    while ($row = $res->fetch_assoc()) {
        $id = intval($row['id']);

        // Skip ingredients the customer asked to remove
        if (in_array($id, $removed_ids)) continue;

        $required = floatval($row['qty_needed']) * $qty;

        if (!isset($needs[$id])) {
            $needs[$id] = [
                'name'      => $row['name'],
                'unit'      => $row['unit'],
                'available' => floatval($row['stock_qty']),
                'needed'    => 0,
                'menu_name' => $row['menu_name'],
            ];
        }
        $needs[$id]['needed'] += $required;
    }
    $res->free();
}
$stmtCheck->close();

$shortages = [];
foreach ($needs as $ing) {
    if ($ing['needed'] > $ing['available']) {
        $shortages[] = sprintf(
            '"%s" needs %.2f %s but only %.2f %s left',
            $ing['name'],
            $ing['needed'],
            $ing['unit'],
            $ing['available'],
            $ing['unit']
        );
    }
}

if (!empty($shortages)) {
    echo json_encode([
        'success'   => false,
        'out_of_stock' => true,
        'message'   => '⚠️ Out of stock: ' . implode('; ', $shortages),
    ]);
    $conn->close();
    exit();
}

$conn->begin_transaction();

try {

    $stmt = $conn->prepare(
        "INSERT INTO orders (table_no, status, total_amt, discount_amt, discount_type, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param('ssdds', $table_no, $status, $total_amt, $discount_amt, $discount_type);
    $stmt->execute();
    $order_id = $conn->insert_id;
    $stmt->close();

    $stmtItem = $conn->prepare(
        "INSERT INTO order_items
             (order_id, menu_id, qty, unit_price,
              removed_ingredient_ids, removed_ingredient_names,
              addons, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmtIngReq = $conn->prepare(
        "SELECT ingredient_id, qty_needed
         FROM menu_ingredients
         WHERE menu_id = ?"
    );

    $stmtDeduct = $conn->prepare(
        "UPDATE ingredients
         SET stock_qty = GREATEST(stock_qty - ?, 0),
             updated_at = NOW()
         WHERE id = ?"
    );

    foreach ($items as $item) {
        $menu_id    = intval($item['menu_id']);
        $qty        = intval($item['qty']);
        $unit_price = floatval($item['unit_price']);

        // Support removed as [{id,name}] objects OR plain id array
        $raw_removed   = $item['removed_ingredient_ids'] ?? [];
        $removed_ids   = [];
        $removed_names = [];
        foreach ($raw_removed as $r) {
            if (is_array($r)) {
                $removed_ids[]   = intval($r['id']   ?? 0);
                $removed_names[] = strval($r['name'] ?? '');
            } else {
                $removed_ids[] = intval($r);
            }
        }

        $removed_ids_json   = json_encode($removed_ids);
        $removed_names_json = json_encode($removed_names);
        // addons already a formatted string from POS.php e.g. "2× Extra Shot (+₱30), Whipped Cream (+₱15)"
        $addons_str = strval($item['addons'] ?? '');
        $notes_str  = strval($item['notes']  ?? '');

        $stmtItem->bind_param(
            'iiidssss',
            $order_id, $menu_id, $qty, $unit_price,
            $removed_ids_json, $removed_names_json,
            $addons_str, $notes_str
        );
        $stmtItem->execute();

        $stmtIngReq->bind_param('i', $menu_id);
        $stmtIngReq->execute();
        $ingResult = $stmtIngReq->get_result();

        while ($ing = $ingResult->fetch_assoc()) {
            $ingredient_id = intval($ing['ingredient_id']);

            // Skip ingredients the customer asked to remove
            if (in_array($ingredient_id, $removed_ids)) continue;

            $total_deduct = floatval($ing['qty_needed']) * $qty;
            $stmtDeduct->bind_param('di', $total_deduct, $ingredient_id);
            $stmtDeduct->execute();
        }

        $ingResult->free();
    }

    $stmtItem->close();
    $stmtIngReq->close();
    $stmtDeduct->close();

    $conn->commit();

    echo json_encode([
        'success'  => true,
        'order_id' => $order_id,
        'message'  => 'Order placed and inventory updated.'
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => 'Order failed: ' . $e->getMessage()
    ]);
}

$conn->close();
?>