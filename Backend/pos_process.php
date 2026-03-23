<?php
// Backend/pos_process.php
// Receives JSON POST from POS.php and saves to:
//   orders      (id, table_no, status, total_amt, created_at)
//   order_items (id, order_id, menu_id, qty, unit_price)

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

require_once 'conn.php';

// ── Read JSON body ────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

$table_no  = trim($input['table_no']  ?? '');
$status    = trim($input['status']    ?? 'pending');
$total_amt = floatval($input['total_amt'] ?? 0);
$items     = $input['items'] ?? [];

// ── Validate ──────────────────────────────────────────────────
if (empty($table_no) || $total_amt <= 0 || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Missing required order data.']);
    exit;
}

$created_at = date('Y-m-d H:i:s');

// ── Begin transaction ─────────────────────────────────────────
$conn->begin_transaction();

try {
    // 1. Insert into orders
    // orders columns: id, table_no, status, total_amt, created_at
    $stmt = $conn->prepare(
        "INSERT INTO orders (table_no, status, total_amt, created_at)
         VALUES (?, ?, ?, ?)"
    );
    // s=table_no, s=status, d=total_amt, s=created_at
    $stmt->bind_param('ssds', $table_no, $status, $total_amt, $created_at);
    $stmt->execute();
    $order_id = $conn->insert_id;
    $stmt->close();

    // 2. Insert each item into order_items
    // order_items columns: id, order_id, menu_id, qty, unit_price
    $stmt2 = $conn->prepare(
        "INSERT INTO order_items (order_id, menu_id, qty, unit_price)
         VALUES (?, ?, ?, ?)"
    );

    foreach ($items as $item) {
        $menu_id    = intval($item['menu_id']    ?? 0);
        $qty        = intval($item['qty']        ?? 0);
        $unit_price = floatval($item['unit_price'] ?? 0);

        if ($menu_id <= 0 || $qty <= 0) continue;

        // i=order_id, i=menu_id, i=qty, d=unit_price
        $stmt2->bind_param('iiid', $order_id, $menu_id, $qty, $unit_price);
        $stmt2->execute();
    }
    $stmt2->close();

    // ── Commit ────────────────────────────────────────────────
    $conn->commit();
    $conn->close();

    echo json_encode([
        'success'  => true,
        'order_id' => $order_id,
        'message'  => 'Order placed successfully.'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    echo json_encode([
        'success' => false,
        'message' => 'Transaction failed: ' . $e->getMessage()
    ]);
}
?>
