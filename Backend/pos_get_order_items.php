<?php
// Backend/pos_get_order_items.php
// Returns order items for a given order_id (used by the Refund modal).

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'conn.php';

$order_id = intval($_GET['order_id'] ?? 0);
if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Missing order_id']);
    exit();
}

$stmt = $conn->prepare(
    "SELECT oi.menu_id, oi.qty, oi.unit_price, m.name AS menu_name
     FROM order_items oi
     JOIN menu m ON m.id = oi.menu_id
     WHERE oi.order_id = ?
     ORDER BY m.name"
);
$stmt->bind_param('i', $order_id);
$stmt->execute();
$res   = $stmt->get_result();
$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        'menu_id'    => (int)$row['menu_id'],
        'menu_name'  => $row['menu_name'],
        'qty'        => (int)$row['qty'],
        'unit_price' => (float)$row['unit_price'],
    ];
}
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'items' => $items]);
?>
