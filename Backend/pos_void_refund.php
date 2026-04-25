<?php
// Backend/pos_void_refund.php
// Handles VOID (cancel full order) and REFUND (partial or full)
// Both restore ingredient stock back to inventory.

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'conn.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['action']) || empty($data['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit();
}

$action   = $data['action'];   // 'void' or 'refund'
$order_id = intval($data['order_id']);
$reason   = $conn->real_escape_string($data['reason'] ?? '');

// ── Validate action ───────────────────────────────────────────
if (!in_array($action, ['void', 'refund'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// ── Fetch order ───────────────────────────────────────────────
$stmtOrder = $conn->prepare(
    "SELECT id, table_no, status, total_amt FROM orders WHERE id = ?"
);
$stmtOrder->bind_param('i', $order_id);
$stmtOrder->execute();
$orderRes = $stmtOrder->get_result();
$order    = $orderRes->fetch_assoc();
$stmtOrder->close();

if (!$order) {
    echo json_encode(['success' => false, 'message' => "Order #$order_id not found"]);
    $conn->close(); exit();
}

if (in_array($order['status'], ['voided', 'refunded'])) {
    echo json_encode([
        'success' => false,
        'message' => "Order #$order_id is already {$order['status']}"
    ]);
    $conn->close(); exit();
}

// ── Fetch order items ─────────────────────────────────────────
$stmtItems = $conn->prepare(
    "SELECT oi.menu_id, oi.qty, oi.unit_price, oi.removed_ingredient_ids, m.name AS menu_name
     FROM order_items oi
     JOIN menu m ON m.id = oi.menu_id
     WHERE oi.order_id = ?"
);
$stmtItems->bind_param('i', $order_id);
$stmtItems->execute();
$itemsRes   = $stmtItems->get_result();
$orderItems = [];
while ($row = $itemsRes->fetch_assoc()) {
    $orderItems[] = [
        'menu_id'                => intval($row['menu_id']),
        'qty'                    => intval($row['qty']),
        'unit_price'             => floatval($row['unit_price']),
        'menu_name'              => $row['menu_name'],
        // IDs of ingredients that were removed at order time — do NOT restore these
        'removed_ingredient_ids' => json_decode($row['removed_ingredient_ids'] ?? '[]', true) ?: [],
    ];
}
$stmtItems->close();

// ── For REFUND: determine which items to restore ──────────────
// Payload: refund_items = [ { menu_id, qty }, … ]
// If not provided → full refund (all items).
$refundItems = [];
$refundAmt   = 0.0;

if ($action === 'refund') {
    if (!empty($data['refund_items'])) {
        // Partial refund — validate qty doesn't exceed ordered qty
        $orderedMap = [];
        foreach ($orderItems as $oi) {
            $orderedMap[$oi['menu_id']] = [
                'qty'                    => intval($oi['qty']),
                'unit_price'             => floatval($oi['unit_price']),
                'menu_name'              => $oi['menu_name'],
                'removed_ingredient_ids' => $oi['removed_ingredient_ids'],
            ];
        }
        foreach ($data['refund_items'] as $ri) {
            $mid  = intval($ri['menu_id']);
            $rqty = intval($ri['qty']);
            if (!isset($orderedMap[$mid])) {
                echo json_encode(['success' => false, 'message' => "Item menu_id=$mid not in order"]);
                $conn->close(); exit();
            }
            if ($rqty > $orderedMap[$mid]['qty']) {
                echo json_encode([
                    'success' => false,
                    'message' => "Refund qty ($rqty) exceeds ordered qty ({$orderedMap[$mid]['qty']}) for {$orderedMap[$mid]['menu_name']}"
                ]);
                $conn->close(); exit();
            }
            $refundItems[] = [
                'menu_id'                => $mid,
                'qty'                    => $rqty,
                'removed_ingredient_ids' => $orderedMap[$mid]['removed_ingredient_ids'],
            ];
            $refundAmt += $orderedMap[$mid]['unit_price'] * $rqty;
        }
    } else {
        // Full refund
        foreach ($orderItems as $oi) {
            $refundItems[] = [
                'menu_id'                => intval($oi['menu_id']),
                'qty'                    => intval($oi['qty']),
                'removed_ingredient_ids' => $oi['removed_ingredient_ids'],
            ];
        }
        $refundAmt = floatval($order['total_amt']);
    }
} else {
    // Void = return all items
    foreach ($orderItems as $oi) {
        $refundItems[] = [
            'menu_id'                => intval($oi['menu_id']),
            'qty'                    => intval($oi['qty']),
            'removed_ingredient_ids' => $oi['removed_ingredient_ids'],
        ];
    }
    $refundAmt = floatval($order['total_amt']);
}

// ══════════════════════════════════════════════════════════════
//  TRANSACTION: restore stock + record in order_refunds
// ══════════════════════════════════════════════════════════════
$conn->begin_transaction();

try {
    // 1. Restore ingredient stock for each item being voided/refunded
    $stmtIng = $conn->prepare(
        "SELECT ingredient_id, qty_needed FROM menu_ingredients WHERE menu_id = ?"
    );
    $stmtRestore = $conn->prepare(
        "UPDATE ingredients
         SET stock_qty = stock_qty + ?
         WHERE id = ?"
    );

    foreach ($refundItems as $ri) {
        $mid         = $ri['menu_id'];
        $rqty        = $ri['qty'];
        $removed_ids = array_map('intval', $ri['removed_ingredient_ids'] ?? []);

        $stmtIng->bind_param('i', $mid);
        $stmtIng->execute();
        $ingRes = $stmtIng->get_result();

        while ($ing = $ingRes->fetch_assoc()) {
            $ing_id = intval($ing['ingredient_id']);

            // Only restore ingredients that were actually deducted
            if (in_array($ing_id, $removed_ids)) continue;

            $restore = floatval($ing['qty_needed']) * $rqty;
            $stmtRestore->bind_param('di', $restore, $ing_id);
            $stmtRestore->execute();
        }
        $ingRes->free();
    }
    $stmtIng->close();
    $stmtRestore->close();

    // 2. Determine new order status
    $isFullVoid    = ($action === 'void');
    $isFullRefund  = ($action === 'refund' && empty($data['refund_items']));
    $newStatus     = $isFullVoid ? 'voided' : ($isFullRefund ? 'refunded' : 'partial_refund');

    // 3. Update order status
    $stmtStat = $conn->prepare(
        "UPDATE orders SET status = ? WHERE id = ?"
    );
    $stmtStat->bind_param('si', $newStatus, $order_id);
    $stmtStat->execute();
    $stmtStat->close();

    // 4. Log into order_refunds table (create if not exists)
    $conn->query(
        "CREATE TABLE IF NOT EXISTS order_refunds (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            order_id    INT NOT NULL,
            action      ENUM('void','refund') NOT NULL,
            refund_amt  DECIMAL(10,2) NOT NULL DEFAULT 0,
            reason      VARCHAR(255) DEFAULT NULL,
            items_json  TEXT DEFAULT NULL,
            created_by  VARCHAR(100) DEFAULT NULL,
            created_at  DATETIME DEFAULT NOW()
        )"
    );

    $itemsJson = json_encode($refundItems);
    $createdBy = $_SESSION['user']['firstname'] ?? 'staff';
    $stmtLog   = $conn->prepare(
        "INSERT INTO order_refunds (order_id, action, refund_amt, reason, items_json, created_by)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmtLog->bind_param('isdsss', $order_id, $action, $refundAmt, $reason, $itemsJson, $createdBy);
    $stmtLog->execute();
    $refund_log_id = $conn->insert_id;
    $stmtLog->close();

    $conn->commit();

    echo json_encode([
        'success'       => true,
        'action'        => $action,
        'order_id'      => $order_id,
        'new_status'    => $newStatus,
        'refund_amt'    => $refundAmt,
        'refund_log_id' => $refund_log_id,
        'message'       => $action === 'void'
            ? "Order #$order_id has been voided. ₱" . number_format($refundAmt, 2) . " reversed."
            : "Refund of ₱" . number_format($refundAmt, 2) . " processed for Order #$order_id.",
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => "Operation failed: " . $e->getMessage()
    ]);
}

$conn->close();
?>