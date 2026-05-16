<?php
/**
 * pos_live_data.php  — Server-Sent Events endpoint (POS / Staff page)
 * Place at:  Frontend/Backend/pos_live_data.php
 *            (alongside pos_process.php, conn.php, etc.)
 *
 * Same one-shot SSE strategy as live_data.php.
 * Called by POS.php's realtime.js as: '../Backend/pos_live_data.php'
 */

session_name('STAFF_SESSION');
session_start();

if (!isset($_SESSION['user']) || $_SESSION['position'] !== 'staff') {
    http_response_code(403);
    exit;
}

require_once 'conn.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
@ob_end_clean();
ob_implicit_flush(true);

function tableExistsPOS($conn, $table) {
    $r = $conn->query("SHOW TABLES LIKE '$table'");
    return $r && $r->num_rows > 0;
}

$VALID         = "status NOT IN ('voided','refunded','partial_refund')";
$hasOrderItems = tableExistsPOS($conn, 'order_items');

$data = [];

// Today stats
$r = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");
if ($r && $row = $r->fetch_assoc()) {
    $data['todayRevenue'] = (float)$row['rev'];
    $data['todayOrders']  = (int)$row['cnt'];
}

// Latest order ID (for new-order detection on POS)
$r = $conn->query("SELECT MAX(id) AS max_id FROM orders");
$data['latestOrderId'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['max_id'] : 0;

// Menu availability (for live menu refresh)
$menuItems = [];
$r = $conn->query("SELECT id, name, is_available FROM menu ORDER BY category, name");
if ($r) while ($row = $r->fetch_assoc()) $menuItems[] = ['id'=>(int)$row['id'],'available'=>(int)$row['is_available']];
$data['menuAvailability'] = $menuItems;

// Today's order history (last 50)
$history = [];
if ($hasOrderItems) {
    $r = $conn->query(
        "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                COALESCE(o.discount_amt,0) AS discount_amt,
                COALESCE(o.discount_type,'') AS discount_type,
                SUM(oi.qty) AS total_qty,
                GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS item_names
         FROM orders o
         JOIN order_items oi ON oi.order_id=o.id
         JOIN menu m ON m.id=oi.menu_id
         WHERE DATE(o.created_at)=CURDATE()
         GROUP BY o.id ORDER BY o.created_at DESC LIMIT 50"
    );
    if ($r) while ($row = $r->fetch_assoc()) $history[] = $row;
}
$data['orderHistory'] = $history;

// Ingredient stock (for displaying low-stock warnings in POS)
$r = $conn->query("SELECT COUNT(*) AS c FROM ingredients WHERE stock_qty <= low_stock_threshold");
$data['lowStockCount'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['c'] : 0;

$conn->close();

echo "retry: 4000\n";
echo "event: posStats\n";
echo "data: " . json_encode($data) . "\n\n";

if (ob_get_level()) ob_flush();
flush();
