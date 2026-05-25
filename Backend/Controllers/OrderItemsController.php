<?php
// Backend/Controllers/OrderItemsController.php
// Returns order items for a given order_id (used by Refund modals).
// Replaces: Backend/pos_get_order_items.php

require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Core/Database.php';

Auth::requireStaff();

header('Content-Type: application/json');

$orderId = (int) ($_GET['order_id'] ?? 0);
if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Missing order_id']);
    exit();
}

$db   = Database::getInstance()->getConnection();
$stmt = $db->prepare(
    'SELECT oi.menu_id, oi.qty, oi.unit_price,
            oi.removed_ingredient_ids, oi.removed_ingredient_names,
            oi.addons, oi.notes,
            m.name AS menu_name
     FROM order_items oi
     JOIN menu m ON m.id = oi.menu_id
     WHERE oi.order_id = ?
     ORDER BY m.name'
);
$stmt->bind_param('i', $orderId);
$stmt->execute();
$res   = $stmt->get_result();
$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        'menu_id'                  => (int)   $row['menu_id'],
        'menu_name'                =>          $row['menu_name'],
        'qty'                      => (int)   $row['qty'],
        'unit_price'               => (float) $row['unit_price'],
        'removed_ingredient_ids'   => json_decode($row['removed_ingredient_ids']   ?? '[]', true) ?: [],
        'removed_ingredient_names' => json_decode($row['removed_ingredient_names'] ?? '[]', true) ?: [],
        'addons'                   => $row['addons'] ?? '',
        'notes'                    => $row['notes']  ?? '',
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'items' => $items]);