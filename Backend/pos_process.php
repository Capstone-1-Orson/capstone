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

$table_no  = $conn->real_escape_string($data['table_no']  ?? '01');
$status    = 'Done';
$total_amt = floatval($data['total_amt'] ?? 0);
$items     = $data['items'];  // [ { menu_id, qty, unit_price }, … ]

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
    $menu_id = intval($item['menu_id']);
    $qty     = intval($item['qty']);

    $stmtCheck->bind_param('i', $menu_id);
    $stmtCheck->execute();
    $res = $stmtCheck->get_result();

    while ($row = $res->fetch_assoc()) {
        $id        = intval($row['id']);
        $required  = floatval($row['qty_needed']) * $qty;

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
        "INSERT INTO orders (table_no, status, total_amt, created_at)
         VALUES (?, ?, ?, NOW())"
    );
    $stmt->bind_param('ssd', $table_no, $status, $total_amt);
    $stmt->execute();
    $order_id = $conn->insert_id;
    $stmt->close();

    $stmtItem = $conn->prepare(
        "INSERT INTO order_items (order_id, menu_id, qty, unit_price)
         VALUES (?, ?, ?, ?)"
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

        $stmtItem->bind_param('iiid', $order_id, $menu_id, $qty, $unit_price);
        $stmtItem->execute();

        $stmtIngReq->bind_param('i', $menu_id);
        $stmtIngReq->execute();
        $ingResult = $stmtIngReq->get_result();

        while ($ing = $ingResult->fetch_assoc()) {
            $ingredient_id = intval($ing['ingredient_id']);
            $total_deduct  = floatval($ing['qty_needed']) * $qty;

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