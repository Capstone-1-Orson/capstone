<?php
session_name('ADMIN_SESSION');
session_start();
if (!isset($_SESSION['user']) || $_SESSION['position'] !== 'admin') {
    if (isset($_GET['sse'])) { http_response_code(403); exit; }
    header("Location: ../../lockscreen.html");
    exit();
}

// ── SSE endpoint (replaces live_data.php) ────────────────────────
if (isset($_GET['sse'])) {
    require_once '../../Backend/conn.php';

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @ob_end_clean();
    ob_implicit_flush(true);

    function tableExists2($conn, $table) {
        $r = $conn->query("SHOW TABLES LIKE '$table'");
        return $r && $r->num_rows > 0;
    }

    $VALID         = "status NOT IN ('voided','refunded','partial_refund')";
    $hasOrderItems = tableExists2($conn, 'order_items');
    $data = [];

    // 1. Info-box top row
    $r = $conn->query("SELECT COUNT(DISTINCT table_no) AS c FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");
    $data['dailyCustomers'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['c'] : 0;

    $r = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE $VALID");
    $data['totalRevenue'] = ($r && $row = $r->fetch_assoc()) ? (float)$row['rev'] : 0.0;

    $r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");
    $data['ordersToday'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['c'] : 0;

    $r = $conn->query("SELECT COUNT(*) AS c FROM user WHERE position='staff'");
    $data['staffCount'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['c'] : 0;

    // 2. Right-panel stats
    if ($hasOrderItems) {
        $r = $conn->query(
            "SELECT COALESCE(SUM(oi.qty),0) AS c FROM order_items oi
             JOIN orders o ON o.id=oi.order_id
             WHERE DATE(o.created_at)=CURDATE() AND $VALID"
        );
        $data['dailyItemsSold'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['c'] : 0;
    } else {
        $data['dailyItemsSold'] = 0;
    }

    $r = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");
    $data['dailyRevenue'] = ($r && $row = $r->fetch_assoc()) ? (float)$row['rev'] : 0.0;

    $r = $conn->query("SELECT COUNT(*) AS c FROM ingredients WHERE stock_qty <= low_stock_threshold");
    $data['lowStockCount'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['c'] : 0;

    $r = $conn->query("SELECT COUNT(*) AS c FROM ingredients WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()");
    $data['expiredCount'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['c'] : 0;

    $r = $conn->query("SELECT COUNT(*) AS c FROM ingredients WHERE expiry_date IS NOT NULL AND expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $data['expiringSoonCount'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['c'] : 0;

    // 3. Monthly summary
    $r = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) AND $VALID");
    $data['thisMonthRev'] = ($r && $row = $r->fetch_assoc()) ? (float)$row['rev'] : 0.0;

    $r = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()-INTERVAL 1 MONTH) AND YEAR(created_at)=YEAR(CURDATE()-INTERVAL 1 MONTH) AND $VALID");
    $data['lastMonthRev'] = ($r && $row = $r->fetch_assoc()) ? (float)$row['rev'] : 0.0;

    $r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE $VALID");
    $data['totalOrders'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['c'] : 0;

    // 4. Latest order ID
    $r = $conn->query("SELECT MAX(id) AS max_id FROM orders");
    $data['latestOrderId'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['max_id'] : 0;

    // 5. Latest order info for toast
    $r = $conn->query("SELECT id, table_no, total_amt, created_at FROM orders ORDER BY id DESC LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) {
        $data['latestOrder'] = [
            'id'         => (int)$row['id'],
            'table_no'   => $row['table_no'],
            'total_amt'  => (float)$row['total_amt'],
            'created_at' => $row['created_at'],
        ];
    }

    // 6. Inventory rows
    $invRows = [];
    $r = $conn->query(
        "SELECT id, name, unit, stock_qty, low_stock_threshold,
                COALESCE(expiry_date,'') AS expiry_date,
                CASE
                  WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE()         THEN 'expired'
                  WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'soon'
                  WHEN stock_qty <= low_stock_threshold THEN 'low'
                  ELSE 'ok'
                END AS health
         FROM ingredients ORDER BY name"
    );
    if ($r) while ($row = $r->fetch_assoc()) $invRows[] = $row;
    $data['inventory'] = $invRows;

    // 7. Recent orders — today only (SSE is used for live "today" view)
    $recentOrders = [];
    if ($hasOrderItems) {
        $r = $conn->query(
            "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                    GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items,
                    COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN menu m ON m.id = oi.menu_id
             LEFT JOIN user u ON u.id = o.user_id
             WHERE $VALID AND DATE(o.created_at) = CURDATE()
             GROUP BY o.id ORDER BY o.created_at DESC LIMIT 50"
        );
        if ($r) while ($row = $r->fetch_assoc()) $recentOrders[] = $row;
    }
    $data['recentOrders'] = $recentOrders;

    // 8. Menu counts
    $r = $conn->query("SELECT COUNT(*) AS c FROM menu WHERE is_available=1");
    $data['menuAvailable'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['c'] : 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM menu");
    $data['menuTotal'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['c'] : 0;

    // 9. Suppliers
    $r = $conn->query("SELECT COUNT(*) AS c FROM suppliers");
    $data['suppliersCount'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['c'] : 0;

    // 10. Void/refund stats
    $r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='voided'");
    $data['voidedCount'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['c'] : 0;

    $r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status IN ('refunded','partial_refund')");
    $data['refundedCount'] = ($r && $row = $r->fetch_assoc()) ? (int)$row['c'] : 0;

    $conn->close();

    echo "retry: 4000\n";
    echo "event: stats\n";
    echo "data: " . json_encode($data) . "\n\n";

    if (ob_get_level()) ob_flush();
    flush();
    exit;
}
// ── End SSE endpoint ─────────────────────────────────────────────

require_once '../../Backend/conn.php';

// ── Helper ─────────────────────────────────────────────────────
function tableExists($conn, $table) {
    $r = $conn->query("SHOW TABLES LIKE '$table'");
    return $r && $r->num_rows > 0;
}
$hasOrderItems = tableExists($conn, 'order_items');

// ── Valid order filter (exclude voided / refunded) ─────────────
$VALID = "status NOT IN ('voided','refunded','partial_refund')";

// ── History date filter for Recent Orders ──────────────────────
$allowedRanges = ['today'=>0,'7days'=>7,'30days'=>30,'custom'=>-1,'3months'=>90,'12months'=>365,'mtd'=>-1,'ytd'=>-1,'alltime'=>-1];
$selectedRange = isset($_GET['range']) && array_key_exists($_GET['range'], $allowedRanges)
    ? $_GET['range'] : '7days';

// Build date condition
$dateFrom = $dateTo = '';
if ($selectedRange === 'today') {
    $dateFilter       = "DATE(o.created_at) = CURDATE()";
    $dateFilterSimple = "DATE(created_at) = CURDATE()";
} elseif ($selectedRange === 'custom') {
    $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from']??'') ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
    $dateTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to']??'')   ? $_GET['date_to']   : date('Y-m-d');
    $dateFilter       = "DATE(o.created_at) BETWEEN '$dateFrom' AND '$dateTo'";
    $dateFilterSimple = "DATE(created_at)   BETWEEN '$dateFrom' AND '$dateTo'";
} elseif ($selectedRange === 'mtd') {
    $dateFilter       = "YEAR(o.created_at)=YEAR(CURDATE()) AND MONTH(o.created_at)=MONTH(CURDATE())";
    $dateFilterSimple = "YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())";
} elseif ($selectedRange === 'ytd') {
    $dateFilter       = "YEAR(o.created_at)=YEAR(CURDATE())";
    $dateFilterSimple = "YEAR(created_at)=YEAR(CURDATE())";
} elseif ($selectedRange === 'alltime') {
    $dateFilter = $dateFilterSimple = "1=1";
} else {
    $days = (int)$allowedRanges[$selectedRange];
    $dateFilter       = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
    $dateFilterSimple = "created_at   >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
}

// ── Top info-box stats ─────────────────────────────────────────
// Daily customers served = distinct table_no ordered today (valid only)
$dailyCustomers = 0;
$r = $conn->query("SELECT COUNT(DISTINCT table_no) AS c FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");
if ($r && $row = $r->fetch_assoc()) $dailyCustomers = (int)$row['c'];

// Total revenue (all time, valid only)
$totalRevenue = 0.0;
$r = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE $VALID");
if ($r && $row = $r->fetch_assoc()) $totalRevenue = (float)$row['rev'];

// Orders today (valid only)
$ordersToday = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");
if ($r && $row = $r->fetch_assoc()) $ordersToday = (int)$row['c'];

// Staff count (from user table)
$staffCount = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM user WHERE position = 'staff'");
if ($r && $row = $r->fetch_assoc()) $staffCount = (int)$row['c'];

// ── Monthly recap chart (last 6 months) ────────────────────────
$chartLabels = [];
$chartData   = [];
for ($i = 5; $i >= 0; $i--) {
    $y = date('Y', strtotime("-$i months"));
    $m = date('m', strtotime("-$i months"));
    $chartLabels[] = date('M Y', strtotime("-$i months"));
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders
         WHERE MONTH(created_at)=? AND YEAR(created_at)=? AND $VALID"
    );
    $stmt->bind_param('ss', $m, $y);
    $stmt->execute();
    $chartData[] = (float)$stmt->get_result()->fetch_assoc()['rev'];
    $stmt->close();
}

// ── Monthly summary footer ─────────────────────────────────────
$thisMonthRev = 0.0;
$lastMonthRev = 0.0;
$r = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) AND $VALID");
if ($r && $row = $r->fetch_assoc()) $thisMonthRev = (float)$row['rev'];
$r = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()-INTERVAL 1 MONTH) AND YEAR(created_at)=YEAR(CURDATE()-INTERVAL 1 MONTH) AND $VALID");
if ($r && $row = $r->fetch_assoc()) $lastMonthRev = (float)$row['rev'];
$revChange = $lastMonthRev > 0 ? round((($thisMonthRev - $lastMonthRev) / $lastMonthRev) * 100, 1) : 0;

$totalOrders = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE $VALID");
if ($r && $row = $r->fetch_assoc()) $totalOrders = (int)$row['c'];

// ── Category revenue progress bars (valid only) ──────────────
$catRevenue = [];
if ($hasOrderItems) {
    $r = $conn->query(
        "SELECT m.category, SUM(oi.qty * oi.unit_price) AS revenue
         FROM order_items oi
         JOIN menu m ON m.id = oi.menu_id
         JOIN orders o ON o.id = oi.order_id
         WHERE $VALID
         GROUP BY m.category ORDER BY revenue DESC LIMIT 4"
    );
    if ($r) while ($row = $r->fetch_assoc()) $catRevenue[] = $row;
}
$maxCatRev = !empty($catRevenue) ? (float)$catRevenue[0]['revenue'] : 1;

// ── Right-side stats ───────────────────────────────────────────
// Daily items sold (valid orders only)
$dailyItemsSold = 0;
if ($hasOrderItems) {
    $r = $conn->query(
        "SELECT COALESCE(SUM(oi.qty),0) AS c FROM order_items oi
         JOIN orders o ON o.id=oi.order_id
         WHERE DATE(o.created_at)=CURDATE() AND $VALID"
    );
    if ($r && $row = $r->fetch_assoc()) $dailyItemsSold = (int)$row['c'];
}

// Daily revenue (valid only)
$dailyRevenue = 0.0;
$r = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");
if ($r && $row = $r->fetch_assoc()) $dailyRevenue = (float)$row['rev'];

// Low stock count
$lowStockCount = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM ingredients WHERE stock_qty <= low_stock_threshold");
if ($r && $row = $r->fetch_assoc()) $lowStockCount = (int)$row['c'];

// Expired ingredients count
$expiredCount = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM ingredients WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()");
if ($r && $row = $r->fetch_assoc()) $expiredCount = (int)$row['c'];

// Expiring soon count (within 30 days)
$expiringSoonCount = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM ingredients WHERE expiry_date IS NOT NULL AND expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
if ($r && $row = $r->fetch_assoc()) $expiringSoonCount = (int)$row['c'];

// Expired ingredient names (for ticker)
$expiredNames = [];
$r = $conn->query("SELECT name, expiry_date FROM ingredients WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE() ORDER BY expiry_date ASC LIMIT 5");
if ($r) while ($row = $r->fetch_assoc()) $expiredNames[] = $row['name'];

// ── New menu items (last 5 added) ──────────────────────────────
$newMenuItems = [];
$r = $conn->query("SELECT name, price, description, image FROM menu ORDER BY id DESC LIMIT 4");
if ($r) while ($row = $r->fetch_assoc()) $newMenuItems[] = $row;

// ── Staff list ────────────────────────────────────────────────
$staffList = [];
$r = $conn->query("SELECT firstname, lastname, image FROM user WHERE position = 'staff' ORDER BY id DESC LIMIT 5");
if ($r) while ($row = $r->fetch_assoc()) $staffList[] = $row;

// ── Recent orders (filtered by date, valid only) ──────────────
$recentOrders = [];
if ($hasOrderItems) {
    $r = $conn->query(
        "SELECT o.id, o.table_no, o.total_amt, o.created_at,
                COALESCE(CONCAT(u.firstname,' ',u.lastname), 'N/A') AS cashier_name,
                GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         JOIN menu m ON m.id = oi.menu_id
         LEFT JOIN user u ON u.id = o.user_id
         WHERE $VALID AND $dateFilter
         GROUP BY o.id ORDER BY o.created_at DESC LIMIT 50"
    );
    if ($r) while ($row = $r->fetch_assoc()) $recentOrders[] = $row;
} else {
    $r = $conn->query("SELECT o.id, o.table_no, o.total_amt, o.created_at, COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name, '—' AS items FROM orders o LEFT JOIN user u ON u.id = o.user_id WHERE $VALID AND $dateFilter ORDER BY o.created_at DESC LIMIT 50");
    if ($r) while ($row = $r->fetch_assoc()) $recentOrders[] = $row;
}

// ── Revenue Forecasting — Linear Regression on 6-month data ───
function linearRegression(array $y): array {
    $n = count($y);
    if ($n < 2) return ['slope' => 0, 'intercept' => 0];
    $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
    for ($i = 0; $i < $n; $i++) {
        $sumX  += $i; $sumY  += $y[$i];
        $sumXY += $i * $y[$i]; $sumX2 += $i * $i;
    }
    $denom = $n * $sumX2 - $sumX * $sumX;
    if ($denom == 0) return ['slope' => 0, 'intercept' => $sumY / $n];
    $slope     = ($n * $sumXY - $sumX * $sumY) / $denom;
    $intercept = ($sumY - $slope * $sumX) / $n;
    return ['slope' => $slope, 'intercept' => $intercept];
}
$reg = linearRegression($chartData);
$n   = count($chartData);
$forecastData   = [];
$forecastLabels = [];
for ($f = 1; $f <= 3; $f++) {
    $forecastData[]   = round(max(0, $reg['intercept'] + $reg['slope'] * ($n - 1 + $f)), 2);
    $forecastLabels[] = date('M Y', strtotime("+$f months"));
}
$projNextMonth   = $forecastData[0];
$lastMonthActual = end($chartData);
$projGrowthPct   = $lastMonthActual > 0
    ? (($projNextMonth - $lastMonthActual) / $lastMonthActual) * 100 : 0;
$avgMonthRev     = array_sum($chartData) / max(1, count(array_filter($chartData, fn($v) => $v > 0)));

$conn->close();

$chartLabelsJson = json_encode($chartLabels);
$chartDataJson   = json_encode($chartData);
$revTrend        = $revChange >= 0 ? '+' . $revChange . '%' : $revChange . '%';
$revTrendClass   = $revChange >= 0 ? 'text-success' : 'text-danger';
$revTrendIcon    = $revChange >= 0 ? 'fa-caret-up' : 'fa-caret-down';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OPERLYTICS | Overview</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../dist/css/empress-cafe-theme.css">
  <style>
    /* Dark-mode toggle */
    body, .main-header.navbar, .main-sidebar, .content-wrapper, .main-footer {
      transition: background-color .4s ease, color .4s ease, border-color .4s ease;
    }
    #darkModeToggle { transition: box-shadow .3s ease; }
    #darkModeToggle.clicked { box-shadow: 0 0 15px rgba(255,255,255,.8); }

    

    /* ── FIX: Table hover visibility in light mode ── */
    /* Keep text color visible on hover in both light and dark mode */
    .table tbody tr:hover td,
    .table-hover tbody tr:hover td {
      color: inherit !important;
    }
    body:not(.dark-mode) .table tbody tr:hover {
      background-color: rgba(233, 30, 140, 0.08) !important;
    }
    body:not(.dark-mode) .table tbody tr:hover td {
      color: #212529 !important;
    }

    /* ── Ticker ribbon at top of page ── */
    @keyframes tickerScroll {
      from { transform: translateX(100%); }
      to   { transform: translateX(-100%); }
    }
    #live-ticker {
      overflow: hidden;
      background: linear-gradient(90deg, #e91e8c 0%, #9c27b0 100%);
      color: #fff;
      height: 28px;
      line-height: 28px;
      font-size: .75rem;
      letter-spacing: .06em;
      font-weight: 600;
      position: relative;
    }
    #live-ticker span {
      display: inline-block;
      white-space: nowrap;
      padding-left: 100%;
      animation: tickerScroll 28s linear infinite;
    }

    /* ── Pulse dot for "live" indicator ── */
    @keyframes pulse-ring {
      0%   { transform: scale(.85); box-shadow: 0 0 0 0 rgba(233,30,140,.6); }
      70%  { transform: scale(1);   box-shadow: 0 0 0 8px rgba(233,30,140,0); }
      100% { transform: scale(.85); box-shadow: 0 0 0 0 rgba(233,30,140,0); }
    }
    .live-dot {
      width: 8px; height: 8px;
      background: #e91e8c;
      border-radius: 50%;
      display: inline-block;
      margin-right: 6px;
      animation: pulse-ring 1.8s ease infinite;
      vertical-align: middle;
    }

    /* ── Revenue chart gradient area ── */
    .chart { position: relative; }

    /* ── Category progress bar label ── */
    .progress-group {
      font-family: 'DM Sans', sans-serif;
      font-size: .82rem;
      font-weight: 500;
      margin-bottom: 12px;
    }

    /* ── Card stat footer numbers ── */
    .description-header {
      font-size: 1.1rem;
    }

    /* ══════════════════════════════════════════════════════
       GLOBAL FIXES — icon animations removed + mobile scrollbar
       ══════════════════════════════════════════════════════ */

    /* 1. Kill ALL icon animations — every selector possible */
    i.fas, i.far, i.fab, i.fal, i.fad,
    .nav-icon,
    .info-box-icon i,
    .btn i,
    .card-title i,
    .sidebar i,
    [class*="fa-"] {
      animation: none !important;
      -webkit-animation: none !important;
      transform: none !important;
      /* allow color/opacity transitions on non-icon elements still */
    }
    .fa-spin, .fa-pulse {
      animation: none !important;
      -webkit-animation: none !important;
    }
    .info-box-icon, .info-box-icon *,
    .small-box .icon i, .small-box .icon [class*="fa-"] {
      animation: none !important;
      -webkit-animation: none !important;
      transform: none !important;
    }

    /* 2. TABLE MOBILE SCROLL FIX
          - Tables MUST NOT wrap text vertically.
          - The wrapper scrolls horizontally; cells stay single-line.   */
    .table-responsive {
      overflow-x: auto !important;
      -webkit-overflow-scrolling: touch;
    }
    /* DataTables also need their own wrapper to scroll */
    .dataTables_wrapper {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    /* CRITICAL: keep all table text on one line — table scrolls, text never wraps */
    .table td,
    .table th {
      white-space: nowrap !important;
      word-break: normal !important;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 220px;        /* prevents insanely wide single cells */
    }
    /* Allow description/notes columns to be slightly wider but still nowrap */
    .table td:nth-child(4),
    .table td.desc-col {
      max-width: 280px;
    }

    /* 3. Pagination scrolls on small screens */
    .dataTables_wrapper .dataTables_paginate {
      overflow-x: auto;
      white-space: nowrap;
      display: block;
      padding-bottom: 6px;
    }

    /* 4. Custom thin scrollbar (WebKit) — pink accent */
    ::-webkit-scrollbar        { height: 6px; width: 6px; }
    ::-webkit-scrollbar-track  { background: rgba(0,0,0,0.06); border-radius: 3px; }
    ::-webkit-scrollbar-thumb  { background: rgba(233,30,140,0.45); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: rgba(233,30,140,0.75); }

    /* 5. Content wrapper horizontal scroll guard */
    .content-wrapper {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    html { overflow-y: scroll; }

    /* 6. Mobile tweaks */
    @media (max-width: 576px) {
      .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 3px 6px !important;
        font-size: 11px !important;
        min-width: 26px;
      }
      .dataTables_wrapper .dataTables_length,
      .dataTables_wrapper .dataTables_filter,
      .dataTables_wrapper .dataTables_info {
        font-size: 11px;
      }
      .content-header h1 { font-size: 1.2rem; }
    }

    /* ══ DATE RANGE PICKER ══════════════════════════════════════ */
    #drpBackdrop{display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.45);backdrop-filter:blur(6px);}
    #drpPopup{display:none;position:fixed;z-index:9001;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:16px;box-shadow:0 24px 64px rgba(0,0,0,.25);width:min(780px,96vw);overflow:hidden;font-family:'Source Sans Pro',sans-serif;}
    body.dark-mode #drpPopup{background:#252535;color:#e0e0e0;}
    #drpPopup .drp-inner{display:flex;}
    #drpPopup .drp-presets{width:160px;flex-shrink:0;border-right:1px solid #f0f0f0;padding:16px 0;}
    body.dark-mode #drpPopup .drp-presets{border-color:#3a3a4a;}
    #drpPopup .drp-preset-item{padding:9px 20px;cursor:pointer;font-size:.86rem;color:#444;transition:background .12s,color .12s;border-left:3px solid transparent;}
    body.dark-mode #drpPopup .drp-preset-item{color:#bbb;}
    #drpPopup .drp-preset-item:hover{background:#fdf0f8;color:#e91e8c;}
    body.dark-mode #drpPopup .drp-preset-item:hover{background:#3a2a3a;color:#e91e8c;}
    #drpPopup .drp-preset-item.active{font-weight:700;color:#e91e8c;border-left-color:#e91e8c;background:#fdf0f8;}
    body.dark-mode #drpPopup .drp-preset-item.active{background:#3a2a3a;}
    #drpPopup .drp-cals{flex:1;padding:18px 22px;display:flex;flex-direction:column;}
    #drpPopup .drp-months{display:flex;gap:24px;flex:1;}
    #drpPopup .drp-month{flex:1;min-width:0;}
    #drpPopup .drp-month-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
    #drpPopup .drp-month-nav span{font-weight:700;font-size:.9rem;color:#222;}
    body.dark-mode #drpPopup .drp-month-nav span{color:#e0e0e0;}
    #drpPopup .drp-nav-btn{background:none;border:1px solid #e0e0e0;border-radius:6px;width:26px;height:26px;cursor:pointer;font-size:.95rem;color:#666;display:flex;align-items:center;justify-content:center;transition:background .12s,border-color .12s;}
    #drpPopup .drp-nav-btn:hover{background:#fdf0f8;border-color:#e91e8c;color:#e91e8c;}
    body.dark-mode #drpPopup .drp-nav-btn{border-color:#555;color:#aaa;}
    #drpPopup .drp-cal{width:100%;border-collapse:collapse;table-layout:fixed;}
    #drpPopup .drp-cal th{text-align:center;font-size:.72rem;font-weight:600;color:#aaa;padding:4px 0;letter-spacing:.04em;}
    #drpPopup .drp-cal td{text-align:center;padding:0;width:14.28%;height:34px;font-size:.83rem;cursor:pointer;position:relative;color:#333;overflow:hidden;}
    body.dark-mode #drpPopup .drp-cal td{color:#ddd;}
    #drpPopup .drp-cal td::before{content:'';position:absolute;top:2px;bottom:2px;left:0;right:0;background:transparent;z-index:0;}
    #drpPopup .drp-cal td.in-range::before{background:rgba(233,30,140,.15);}
    body.dark-mode #drpPopup .drp-cal td.in-range::before{background:rgba(233,30,140,.25);}
    #drpPopup .drp-cal td.sel-start:not(.sel-end)::before{background:rgba(233,30,140,.15);left:50%;right:0;}
    body.dark-mode #drpPopup .drp-cal td.sel-start:not(.sel-end)::before{background:rgba(233,30,140,.25);left:50%;right:0;}
    #drpPopup .drp-cal td.sel-end:not(.sel-start)::before{background:rgba(233,30,140,.15);left:0;right:50%;}
    body.dark-mode #drpPopup .drp-cal td.sel-end:not(.sel-start)::before{background:rgba(233,30,140,.25);left:0;right:50%;}
    #drpPopup .drp-cal td.sel-start.sel-end::before{background:transparent!important;}
    #drpPopup .drp-cal td .drp-day{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;position:relative;z-index:1;transition:background .12s,color .12s;}
    #drpPopup .drp-cal td:not(.other):not(.sel-start):not(.sel-end):hover .drp-day{background:rgba(233,30,140,.12);color:#e91e8c;}
    #drpPopup .drp-cal td.other{opacity:.35;cursor:default;}
    #drpPopup .drp-cal td.other::before{display:none!important;}
    #drpPopup .drp-cal td.sel-start .drp-day,#drpPopup .drp-cal td.sel-end .drp-day{background:#e91e8c!important;color:#fff!important;font-weight:700;}
    #drpPopup .drp-cal td.is-today .drp-day::after{content:'';position:absolute;bottom:2px;left:50%;transform:translateX(-50%);width:4px;height:4px;border-radius:50%;background:#e91e8c;}
    #drpPopup .drp-cal td.sel-start .drp-day::after,#drpPopup .drp-cal td.sel-end .drp-day::after{background:#fff;}
    #drpPopup .drp-footer{display:flex;align-items:center;justify-content:space-between;margin-top:14px;padding-top:12px;border-top:1px solid #f0f0f0;}
    body.dark-mode #drpPopup .drp-footer{border-color:#3a3a4a;}
    #drpPopup .drp-range-label{font-size:.82rem;color:#888;}
    #drpPopup .drp-range-label strong{color:#333;}
    body.dark-mode #drpPopup .drp-range-label strong{color:#e0e0e0;}
    #drpTriggerBtn{display:inline-flex;align-items:center;gap:7px;border:1px solid #d0d0d0;border-radius:8px;padding:5px 13px;background:#fff;cursor:pointer;font-size:.83rem;color:#444;transition:border-color .15s,box-shadow .15s;white-space:nowrap;}
    body.dark-mode #drpTriggerBtn{background:#2a2a3e;border-color:#555;color:#ddd;}
    #drpTriggerBtn:hover{border-color:#e91e8c;box-shadow:0 0 0 3px rgba(233,30,140,.1);}
    #drpTriggerBtn .drp-cal-icon{color:#e91e8c;font-size:.9rem;}
    #drpTriggerBtn .drp-chevron{color:#aaa;font-size:.65rem;transition:transform .2s;}
    #drpTriggerBtn.open .drp-chevron{transform:rotate(180deg);}
    @media(max-width:600px){#drpPopup .drp-months{flex-direction:column;}#drpPopup .drp-presets{width:100%;border-right:none;border-bottom:1px solid #f0f0f0;display:flex;flex-wrap:wrap;padding:8px;}#drpPopup .drp-preset-item{padding:6px 10px;border-left:none;border-bottom:2px solid transparent;}#drpPopup .drp-preset-item.active{border-bottom-color:#e91e8c;border-left-color:transparent;}#drpPopup .drp-inner{flex-direction:column;}}

</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<script>
  /* Apply dark/light mode instantly before first paint — prevents flash */
  if (localStorage.getItem('darkMode') === 'true') {
    document.body.classList.add('dark-mode');
  }
</script>
<div class="wrapper">

  <!-- ── Live Stats Ticker ──────────────────────────────────────── -->
  <div id="live-ticker" class="w-100">
    <span>
      <span class="live-dot"></span>LIVE &nbsp;·&nbsp;
      Tables Served Today: <strong><?= number_format($dailyCustomers) ?></strong> &nbsp;·&nbsp;
      Today's Revenue: <strong>&#8369;<?= number_format($dailyRevenue, 2) ?></strong> &nbsp;·&nbsp;
      Orders Today: <strong><?= number_format($ordersToday) ?></strong> &nbsp;·&nbsp;
      Low Stock Alerts: <strong><?= number_format($lowStockCount) ?></strong> &nbsp;·&nbsp;
      <?php if ($expiredCount > 0): ?>
      ⚠️ Expired Ingredients: <strong style="color:#ffcdd2;"><?= number_format($expiredCount) ?></strong> &nbsp;·&nbsp;
      <?php endif; ?>
      <?php if ($expiringSoonCount > 0): ?>
      🕐 Expiring Soon: <strong style="color:#ffe0b2;"><?= number_format($expiringSoonCount) ?></strong> &nbsp;·&nbsp;
      <?php endif; ?>
      All-Time Revenue: <strong>&#8369;<?= number_format($totalRevenue, 2) ?></strong> &nbsp;·&nbsp;
      Staff Count: <strong><?= number_format($staffCount) ?></strong>
      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
      <span class="live-dot"></span>LIVE &nbsp;·&nbsp;
      Tables Served Today: <strong><?= number_format($dailyCustomers) ?></strong> &nbsp;·&nbsp;
      Today's Revenue: <strong>&#8369;<?= number_format($dailyRevenue, 2) ?></strong> &nbsp;·&nbsp;
      Orders Today: <strong><?= number_format($ordersToday) ?></strong> &nbsp;·&nbsp;
      Low Stock Alerts: <strong><?= number_format($lowStockCount) ?></strong>
    </span>
  </div>

  <!-- ── Navbar ─────────────────────────────────────────────── -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light bg-white">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="index2.php" class="nav-link">Home</a>
      </li>
    </ul>
    <ul class="navbar-nav ml-auto">
<li class="nav-item">
        <a class="nav-link" data-widget="fullscreen" href="#" role="button"><i class="fas fa-expand-arrows-alt"></i></a>
      </li>
      <li class="nav-item">
        <a class="nav-link" id="darkModeToggle" href="#" role="button"><i class="fas fa-moon"></i></a>
      </li>
    </ul>
  </nav>

  <!-- ── Sidebar ───────────────────────────────────────────── -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="#" class="brand-link">
      <img src="../dist/img/Empress' Cafe Boracay.jpg" alt="Logo" class="brand-image img-circle elevation-3" style="opacity:.8">
      <span class="brand-text font-weight-light">Empress' Cafe</span>
    </a>
    <div class="sidebar">
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <?php
          $admin_image = $_SESSION['image'] ?? '';
          $admin_first = $_SESSION['firstname'] ?? '';
          $admin_last  = $_SESSION['lastname']  ?? '';
          if (empty($admin_first)) {
              $admin_first = strpos($_SESSION['user'] ?? '', '@') !== false
                  ? explode('@', $_SESSION['user'])[0]
                  : ($_SESSION['user'] ?? 'Admin');
          }
          $admin_name  = htmlspecialchars(trim($admin_first . ' ' . $admin_last));
          $admin_photo = !empty($admin_image)
              ? '../../' . htmlspecialchars($admin_image)
              : "../dist/img/Empress' Cafe Boracay.jpg";
        ?>
        <div class="image"><img src="<?= $admin_photo ?>" class="img-circle elevation-2" alt="<?= $admin_name ?>"></div>
        <div class="info">
          <a href="#" class="d-block"><?= $admin_name ?></a>
        </div>
      </div>
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item"><a href="./index2.php"          class="nav-link active"><i class="nav-icon fas fa-tachometer-alt"></i><p>Overview</p></a></li>
          <li class="nav-item"><a href="./menu-management.php" class="nav-link"><i class="nav-icon fas fa-utensils"></i><p>Menu Management</p></a></li>
          <li class="nav-item"><a href="./inventory.php"       class="nav-link"><i class="nav-icon fas fa-boxes"></i><p>Inventory Tracking</p></a></li>
          <li class="nav-item"><a href="./suppliers.php"       class="nav-link"><i class="nav-icon fas fa-truck"></i><p>Supplier Info</p></a></li>
          <li class="nav-item"><a href="./staff-list.php"      class="nav-link"><i class="far fa-user nav-icon"></i><p>Staff List</p></a></li>
          <li class="nav-item"><a href="./sale_revenue.php"    class="nav-link"><i class="nav-icon fas fa-chart-line"></i><p>Sales &amp; Revenue</p></a></li>
          <li class="nav-item"><a href="./report.php"          class="nav-link"><i class="nav-icon fas fa-file-alt"></i><p>Reports</p></a></li>
          <li class="nav-item"><a href="./void_refund.php"     class="nav-link"><i class="nav-icon fas fa-undo-alt"></i><p>Void &amp; Refund</p></a></li>
          <li class="nav-item"><a href="./settings.php" class="nav-link"><i class="nav-icon fas fa-cog"></i><p>Settings</p></a></li>
          <li class="nav-item mt-auto"><a href="../../Backend/logout.php" class="nav-link"><i class="nav-icon fas fa-sign-out-alt"></i><p>Log Out</p></a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <!-- ── Content Wrapper ───────────────────────────────────── -->
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2 align-items-center">
          <div class="col-sm-6"><h1 class="m-0">Cafe Shop Analytics</h1></div>
          <div class="col-sm-6 d-flex align-items-center justify-content-sm-end flex-wrap" style="gap:10px;">
            <!-- Date Range Trigger — opens the picker popup -->
            <button type="button" id="drpTriggerBtn" onclick="drpToggle()">
              <i class="fas fa-calendar-alt drp-cal-icon"></i>
              <span id="drpTriggerLabel"><?php
                $lbl=['today'=>'Today','7days'=>'Last 7 Days','30days'=>'Last 30 Days','3months'=>'Last 3 Months',
                      '12months'=>'Last 12 Months','mtd'=>'Month to Date','ytd'=>'Year to Date','alltime'=>'All Time'];
                echo $selectedRange==='custom'
                  ? date('M d, Y',strtotime($dateFrom)).' – '.date('M d, Y',strtotime($dateTo))
                  : ($lbl[$selectedRange]??'All Time');
              ?></span>
              <i class="fas fa-chevron-down drp-chevron"></i>
            </button>
            <ol class="breadcrumb m-0">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Overview</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">

        <!-- ── Top 4 Info Boxes ────────────────────────────── -->
        <div class="row">
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Tables Served Today</span>
                <span class="info-box-number"><?= number_format($dailyCustomers) ?></span>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
              <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-chart-line"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Revenue</span>
                <span class="info-box-number">&#8369;<?= number_format($totalRevenue, 2) ?></span>
              </div>
            </div>
          </div>
          <div class="clearfix hidden-md-up"></div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
              <span class="info-box-icon bg-success elevation-1"><i class="fas fa-shopping-cart"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Orders Today</span>
                <span class="info-box-number"><?= number_format($ordersToday) ?></span>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
              <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-user-tie"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Staff Registered</span>
                <span class="info-box-number"><?= number_format($staffCount) ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Monthly Recap Chart ────────────────────────── -->
        <div class="row">
          <div class="col-md-12">
            <div class="card">
              <div class="card-header">
                <h5 class="card-title">Monthly Revenue — Last 6 Months</h5>
                <div class="card-tools">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                </div>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-8">
                    <p class="text-center">
                      <strong>Revenue: <?= date('M Y', strtotime('-5 months')) ?> – <?= date('M Y') ?></strong>
                    </p>
                    <div class="chart">
                      <canvas id="salesChart" height="180" style="height:180px;"></canvas>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <p class="text-center"><strong>Revenue by Category</strong></p>
                    <?php if (!empty($catRevenue)):
                      $barColors = ['bg-primary','bg-danger','bg-success','bg-warning'];
                      foreach ($catRevenue as $ci => $cat):
                        $pct = $maxCatRev > 0 ? round(((float)$cat['revenue'] / $maxCatRev) * 100) : 0;
                    ?>
                    <div class="progress-group">
                      <?= htmlspecialchars($cat['category']) ?>
                      <span class="float-right"><b>&#8369;<?= number_format((float)$cat['revenue'], 0) ?></b></span>
                      <div class="progress progress-sm">
                        <div class="progress-bar <?= $barColors[$ci % 4] ?>" style="width:<?= $pct ?>%"></div>
                      </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                      <p class="text-muted text-center">No category data yet.</p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="card-footer">
                <div class="row">
                  <div class="col-sm-3 col-6">
                    <div class="description-block border-right">
                      <span class="description-percentage <?= $revTrendClass ?>">
                        <i class="fas <?= $revTrendIcon ?>"></i> <?= $revTrend ?>
                      </span>
                      <h5 class="description-header">&#8369;<?= number_format($thisMonthRev, 2) ?></h5>
                      <span class="description-text">THIS MONTH</span>
                    </div>
                  </div>
                  <div class="col-sm-3 col-6">
                    <div class="description-block border-right">
                      <span class="description-percentage text-muted"><i class="fas fa-minus"></i></span>
                      <h5 class="description-header">&#8369;<?= number_format($lastMonthRev, 2) ?></h5>
                      <span class="description-text">LAST MONTH</span>
                    </div>
                  </div>
                  <div class="col-sm-3 col-6">
                    <div class="description-block border-right">
                      <span class="description-percentage text-info"><i class="fas fa-receipt"></i></span>
                      <h5 class="description-header"><?= number_format($totalOrders) ?></h5>
                      <span class="description-text">TOTAL ORDERS</span>
                    </div>
                  </div>
                  <div class="col-sm-3 col-6">
                    <div class="description-block">
                      <span class="description-percentage text-success"><i class="fas fa-money-bill-wave"></i></span>
                      <h5 class="description-header">&#8369;<?= number_format($totalRevenue, 2) ?></h5>
                      <span class="description-text">ALL-TIME REVENUE</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Main Row ───────────────────────────────────── -->
        <div class="row">
          <!-- Left col -->
          <div class="col-md-8">
            <div class="row">

              <!-- New Menu Items -->
              <div class="col-md-6">
                <div class="card">
                  <div class="card-header">
                    <h3 class="card-title">New Menu Items</h3>
                  </div>
                  <div class="card-body p-0">
                    <ul class="products-list product-list-in-card pl-2 pr-2">
                      <?php if (!empty($newMenuItems)):
                        $badgeColors = ['badge-warning','badge-info','badge-danger','badge-success'];
                        foreach ($newMenuItems as $mi => $item): ?>
                      <li class="item">

                        <div class="product-info">
                          <a href="menu-management.php" class="product-title">
                            <?= htmlspecialchars($item['name']) ?>
                            <span class="badge <?= $badgeColors[$mi % 4] ?> float-right">
                              &#8369;<?= number_format((float)$item['price'], 2) ?>
                            </span>
                          </a>
                          <span class="product-description">
                            <?= htmlspecialchars(mb_strimwidth($item['description'] ?? '', 0, 60, '…')) ?>
                          </span>
                        </div>
                      </li>
                      <?php endforeach; ?>
                      <?php else: ?>
                      <li class="item p-3 text-muted">No menu items yet.</li>
                      <?php endif; ?>
                    </ul>
                  </div>
                  <div class="card-footer text-center">
                    <a href="menu-management.php" class="uppercase">View Full Menu</a>
                  </div>
                </div>
              </div>

              <!-- Staff Members -->
              <div class="col-md-6">
                <div class="card">
                  <div class="card-header">
                    <h3 class="card-title">Staff Members</h3>
                    <span class="badge badge-info"><?= $staffCount ?> Staff</span>
                  </div>
                  <div class="card-body p-0">
                    <ul class="users-list clearfix" style="display:flex;flex-wrap:wrap;justify-content:center;">
                      <?php if (!empty($staffList)):
                        foreach ($staffList as $staff): ?>
                      <li style="text-align:center;margin:8px;">
                        <?php
                          $staffPhoto = (!empty($staff['image']))
                            ? '../../' . htmlspecialchars($staff['image'])
                            : '../dist/img/user2-160x160.jpg';
                        ?>
                        <img src="<?= $staffPhoto ?>" alt="<?= htmlspecialchars($staff['firstname']) ?>">
                        <a class="users-list-name" href="staff-list.php">
                          <?= htmlspecialchars($staff['firstname']) ?> <?= htmlspecialchars($staff['lastname']) ?>
                        </a>
                        <span class="users-list-date">Staff</span>
                      </li>
                      <?php endforeach; ?>
                      <?php else: ?>
                      <li class="p-3 text-muted">No staff records found.</li>
                      <?php endif; ?>
                    </ul>
                  </div>
                  <div class="card-footer text-center">
                    <a href="staff-list.php">View All Staff</a>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- Right col — Today's Snapshot -->
          <div class="col-md-4">
            <!-- Section label so users understand what this panel contains -->
            <div class="d-flex align-items-center mb-2">
              <i class="fas fa-chart-bar mr-2" style="color:#e91e8c;font-size:.95rem;"></i>
              <span style="font-weight:700;font-size:.8rem;text-transform:uppercase;letter-spacing:.07em;color:#888;">Today's Snapshot</span>
            </div>
            <div class="info-box mb-3 bg-warning">
              <span class="info-box-icon"><i class="fas fa-boxes"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Items Sold Today</span>
                <span class="info-box-number"><?= number_format($dailyItemsSold) ?></span>
              </div>
            </div>
            <div class="info-box mb-3 bg-success">
              <span class="info-box-icon"><i class="fas fa-money-bill-wave"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Today's Sales &amp; Revenue</span>
                <span class="info-box-number">&#8369;<?= number_format($dailyRevenue, 2) ?></span>
              </div>
            </div>
            <div class="info-box mb-3 bg-danger">
              <span class="info-box-icon"><i class="fas fa-exclamation-triangle"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Low Stock Ingredients Alerts </span>
                <span class="info-box-number"><?= number_format($lowStockCount) ?></span>
              </div>
            </div>
            <div class="info-box mb-3" style="background:#212121;color:#fff;">
              <span class="info-box-icon" style="background:#111;"><i class="fas fa-skull-crossbones" style="color:#ff5252;"></i></span>
              <div class="info-box-content">
                <span class="info-box-text" style="color:#ccc;">Expired Ingredients</span>
                <span class="info-box-number" style="color:#ff5252;"><?= number_format($expiredCount) ?></span>
                <?php if ($expiredCount > 0): ?>
                <span class="progress-description" style="font-size:10px;color:#aaa;">
                  <a href="inventory.php" style="color:#ff8a80;">View in Inventory →</a>
                </span>
                <?php endif; ?>
              </div>
            </div>
            <div class="info-box mb-3" style="background:#fff3e0;">
              <span class="info-box-icon" style="background:#fd7e14;"><i class="fas fa-hourglass-half" style="color:#fff;"></i></span>
              <div class="info-box-content">
                <span class="info-box-text" style="color:#555;">Expiring Soon <small>(≤30 days)</small></span>
                <span class="info-box-number" style="color:#fd7e14;"><?= number_format($expiringSoonCount) ?></span>
                <?php if ($expiringSoonCount > 0): ?>
                <span class="progress-description" style="font-size:10px;color:#888;">
                  <a href="inventory.php" style="color:#e65100;">Check inventory →</a>
                </span>
                <?php endif; ?>
              </div>
            </div>
            <div class="info-box mb-3 bg-info">
              <span class="info-box-icon"><i class="fas fa-shopping-cart"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Orders Today</span>
                <span class="info-box-number"><?= number_format($ordersToday) ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Revenue Forecast Cards ──────────────────────── -->
        <?php
          $growthColor = $projGrowthPct >= 0 ? '#28a745' : '#e74c3c';
          $growthBg    = $projGrowthPct >= 0 ? '#d4edda'  : '#fde8e8';
          $growthIcon  = $projGrowthPct >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
        ?>
        <div class="row mb-3">
          <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box" style="background:#f0e6ff;border-left:4px solid #8e44ad;">
              <span class="info-box-icon" style="background:#8e44ad;color:#fff;"><i class="fas fa-chart-line"></i></span>
              <div class="info-box-content">
                <span class="info-box-text" style="color:#555;">Next Month Forecast</span>
                <span class="info-box-number" style="color:#8e44ad;">&#8369;<?= number_format($projNextMonth, 2) ?></span>
                <span class="progress-description" style="font-size:11px;color:#888;">Linear regression estimate</span>
              </div>
            </div>
          </div>
          <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box" style="background:<?= $growthBg ?>;border-left:4px solid <?= $growthColor ?>;">
              <span class="info-box-icon" style="background:<?= $growthColor ?>;color:#fff;"><i class="fas <?= $growthIcon ?>"></i></span>
              <div class="info-box-content">
                <span class="info-box-text" style="color:#555;">Projected Growth</span>
                <span class="info-box-number" style="color:<?= $growthColor ?>;"><?= ($projGrowthPct >= 0 ? '+' : '') . number_format($projGrowthPct, 1) ?>%</span>
                <span class="progress-description" style="font-size:11px;color:#888;">vs. current month</span>
              </div>
            </div>
          </div>
          <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box" style="background:#e8f4fd;border-left:4px solid #3c8dbc;">
              <span class="info-box-icon" style="background:#3c8dbc;color:#fff;"><i class="fas fa-calculator"></i></span>
              <div class="info-box-content">
                <span class="info-box-text" style="color:#555;">Avg Monthly Revenue</span>
                <span class="info-box-number" style="color:#3c8dbc;">&#8369;<?= number_format($avgMonthRev, 2) ?></span>
                <span class="progress-description" style="font-size:11px;color:#888;">Based on last 6 months</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Expired Ingredients Alert ──────────────────── -->
        <?php if ($expiredCount > 0): ?>
        <div class="row mb-3">
          <div class="col-12">
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert" style="border-left:5px solid #c0392b;">
              <div class="d-flex align-items-center">
                <i class="fas fa-skull-crossbones fa-2x mr-3"></i>
                <div>
                  <strong><?= $expiredCount ?> ingredient(s) have expired</strong> and should be removed immediately.
                  <?php if (!empty($expiredNames)): ?>
                    &nbsp;<span class="text-muted">—</span>&nbsp;
                    <?= implode(', ', array_map('htmlspecialchars', $expiredNames)) ?>
                    <?php if ($expiredCount > count($expiredNames)): ?>
                      <em>and <?= $expiredCount - count($expiredNames) ?> more</em>
                    <?php endif; ?>
                  <?php endif; ?>
                  &nbsp;<a href="inventory.php" class="btn btn-sm btn-danger ml-2"><i class="fas fa-arrow-right mr-1"></i>Go to Inventory</a>
                </div>
              </div>
              <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- ── Recent Orders ──────────────────────────────── -->

        <!-- ═══ DATE RANGE PICKER BACKDROP + POPUP ════════════════════ -->
        <div id="drpBackdrop" onclick="drpClose()"></div>
        <div id="drpPopup" role="dialog" aria-modal="true" aria-label="Select date range">
          <div class="drp-inner">
            <!-- Presets -->
            <div class="drp-presets" id="drpPresets">
              <?php
                $drpPresets = [
                  'today'    => 'Today',
                  '7days'    => 'Last 7 days',
                  '30days'   => 'Last 30 days',
                  '3months'  => 'Last 3 months',
                  '12months' => 'Last 12 months',
                  'mtd'      => 'Month to date',
                  'ytd'      => 'Year to date',
                  'alltime'  => 'All time',
                ];
                foreach ($drpPresets as $pk => $pv):
              ?>
              <div class="drp-preset-item drp-pi <?= in_array($selectedRange,['today','7days','30days']) && $selectedRange===$pk ? 'active' : '' ?>"
                   data-p="<?= $pk ?>">
                <?= $pv ?>
              </div>
              <?php endforeach; ?>
            </div>
            <!-- Calendars -->
            <div class="drp-cals">
              <div class="drp-months">
                <!-- Left month -->
                <div class="drp-month">
                  <div class="drp-month-nav">
                    <button type="button" class="drp-nav-btn" id="drpPrev">&#8249;</button>
                    <span id="drpTA"></span>
                    <span style="width:26px"></span>
                  </div>
                  <table class="drp-cal" id="drpCA"></table>
                </div>
                <!-- Right month -->
                <div class="drp-month">
                  <div class="drp-month-nav">
                    <span style="width:26px"></span>
                    <span id="drpTB"></span>
                    <button type="button" class="drp-nav-btn" id="drpNext">&#8250;</button>
                  </div>
                  <table class="drp-cal" id="drpCB"></table>
                </div>
              </div>
              <!-- Footer -->
              <div class="drp-footer">
                <div class="drp-range-label">
                  Range:&nbsp;<strong id="drpRL">—</strong>
                </div>
                <div style="display:flex;gap:8px;">
                  <button type="button" class="btn btn-sm btn-light" onclick="drpClose()" style="border:1px solid #ddd;min-width:70px;">Cancel</button>
                  <button type="button" class="btn btn-sm" id="drpApply"
                          style="background:#e91e8c;color:#fff;border:none;min-width:70px;">Apply</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Hidden submit form -->
        <form method="GET" id="drpForm" style="display:none;">
          <input type="hidden" name="range"     id="drpFR" value="<?= htmlspecialchars($selectedRange) ?>">
          <input type="hidden" name="date_from" id="drpFF"  value="<?= htmlspecialchars($dateFrom) ?>">
          <input type="hidden" name="date_to"   id="drpFT"    value="<?= htmlspecialchars($dateTo) ?>">
        </form>

        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header border-transparent d-flex flex-wrap align-items-center" style="gap:8px;">
                <h3 class="card-title mr-auto"><i class="fas fa-receipt mr-2"></i>Recent Orders History&nbsp;<span class="rt-live-dot live-dot" style="width:8px;height:8px;background:#22c55e;border-radius:50%;display:inline-block;vertical-align:middle;margin-left:4px;" title="Live data connected"></span></h3>
                <!-- Summary badge -->
                <span id="recentOrdersBadge" class="badge badge-secondary" style="font-size:.8rem;">
                  <?= count($recentOrders) ?> order<?= count($recentOrders)!==1?'s':'' ?>
                  <?php if ($selectedRange==='today'):       ?>· Today
                  <?php elseif ($selectedRange==='7days'):   ?>· Last 7 Days
                  <?php elseif ($selectedRange==='30days'):  ?>· Last 30 Days
                  <?php elseif ($selectedRange==='custom'):  ?>· <?= date('M d',strtotime($dateFrom??'now')) ?> – <?= date('M d, Y',strtotime($dateTo??'now')) ?>
                  <?php endif; ?>
                </span>
                <!-- Date-range picker trigger (only shown for today view) -->
                <?php if ($selectedRange === 'today'): ?>
                <small class="text-muted ml-1" style="font-size:.75rem;">(live)</small>
                <?php endif; ?>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table m-0 w-100" id="recentOrdersTable">
                    <thead>
                      <tr>
                        <th>Order ID</th>
                        <th>Table No</th>
                        <th>Items</th>
                        <th>Cashier</th>
                        <th>Date &amp; Time</th>
                        <th>Total</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody data-rt-container="recentOrders">
                      <?php if (!empty($recentOrders)):
                        foreach ($recentOrders as $ro):
                          $roStatus = $ro['status'] ?? 'completed';
                          if ($roStatus === 'voided')         $statusBadge = '<span class="badge badge-danger">Voided</span>';
                          elseif ($roStatus === 'refunded')   $statusBadge = '<span class="badge badge-info">Refunded</span>';
                          elseif ($roStatus === 'partial_refund') $statusBadge = '<span class="badge badge-warning">Partial Refund</span>';
                          else                                $statusBadge = '<span class="badge badge-success">Completed</span>';
                      ?>
                      <tr data-order-id="<?= (int)$ro['id'] ?>">
                        <td><strong>#<?= (int)$ro['id'] ?></strong></td>
                        <td><?= htmlspecialchars($ro['table_no'] ?? '—') ?></td>
                        <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($ro['items'] ?? '') ?>"><?= htmlspecialchars(mb_strimwidth($ro['items'] ?? '—', 0, 50, '…')) ?></td>
                        <td><?= htmlspecialchars($ro['cashier_name'] ?? '—') ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars(date('M d, Y g:i A', strtotime($ro['created_at']))) ?></small></td>
                        <td><strong class="text-success">&#8369;<?= number_format((float)$ro['total_amt'], 2) ?></strong></td>
                        <td><?= $statusBadge ?></td>
                      </tr>
                      <?php endforeach; ?>
                      <?php else: ?>
                      <tr><td colspan="7" class="text-center text-muted p-3">No recent orders.</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="card-footer clearfix d-flex align-items-center flex-wrap" style="gap:8px;">
                <?php
                  $periodTotal = array_sum(array_column($recentOrders, 'total_amt'));
                ?>
                <span class="text-muted" style="font-size:.82rem;">
                  Period Total:&nbsp;<strong class="text-success">&#8369;<?= number_format($periodTotal, 2) ?></strong>
                </span>
                <a href="sale_revenue.php" class="btn btn-sm btn-secondary ml-auto">View All Orders</a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>
  </div><!-- /.content-wrapper -->
</div><!-- /.wrapper -->

<!-- ══ NEW ORDER TOAST NOTIFICATION ══════════════════════════════ -->
<div id="newOrderToast" style="
  display:none;
  position:fixed;
  bottom:24px;
  right:24px;
  z-index:99999;
  min-width:300px;
  max-width:360px;
  background:linear-gradient(135deg,#e91e8c 0%,#9c27b0 100%);
  color:#fff;
  border-radius:14px;
  box-shadow:0 8px 32px rgba(233,30,140,.45);
  padding:16px 20px;
  font-family:'Source Sans Pro',sans-serif;
  animation: toastSlideIn .35s cubic-bezier(.22,1,.36,1);
">
  <div style="display:flex;align-items:flex-start;gap:12px;">
    <div style="font-size:1.6rem;line-height:1;">🛎️</div>
    <div style="flex:1;">
      <div style="font-weight:700;font-size:.95rem;margin-bottom:2px;">New Order Received!</div>
      <div id="newOrderToastMsg" style="font-size:.82rem;opacity:.9;">A new order just came in.</div>
    </div>
    <button onclick="document.getElementById('newOrderToast').style.display='none'"
      style="background:none;border:none;color:#fff;font-size:1.1rem;cursor:pointer;padding:0;line-height:1;opacity:.8;">✕</button>
  </div>
  <div style="margin-top:10px;display:flex;gap:8px;">
    <div id="toastCountBadge" style="
      background:rgba(255,255,255,.2);
      border-radius:20px;
      padding:3px 10px;
      font-size:.78rem;
      font-weight:600;
    "></div>
    <div id="toastTimeBadge" style="
      background:rgba(255,255,255,.15);
      border-radius:20px;
      padding:3px 10px;
      font-size:.78rem;
    "></div>
  </div>
</div>
<style>
@keyframes toastSlideIn {
  from { opacity:0; transform:translateY(30px) scale(.96); }
  to   { opacity:1; transform:translateY(0)    scale(1);   }
}

</style>

<!-- ── Scripts ──────────────────────────────────────────────── -->
<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
<script src="../dist/js/adminlte.js"></script>
<script src="../plugins/chart.js/Chart.min.js"></script>

<!-- Monthly Revenue Chart -->
<script>
$(function () {
  var ctx = $('#salesChart').get(0).getContext('2d');

  // Gradient fill for the bar chart
  var grad = ctx.createLinearGradient(0, 0, 0, 260);
  grad.addColorStop(0,   'rgba(233,30,140,0.85)');
  grad.addColorStop(0.5, 'rgba(233,30,140,0.45)');
  grad.addColorStop(1,   'rgba(233,30,140,0.08)');

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= $chartLabelsJson ?>,
      datasets: [{
        label: 'Revenue (₱)',
        data: <?= $chartDataJson ?>,
        backgroundColor: grad,
        borderColor: 'rgba(233,30,140,1)',
        borderWidth: 2,
        borderRadius: 8,
        borderSkipped: false,
        hoverBackgroundColor: 'rgba(233,30,140,0.95)'
      }]
    },
    options: {
      responsive: true,
      animation: {
        duration: 900,
        easing: 'easeOutQuart'
      },
      legend: { display: false },
      tooltips: {
        backgroundColor: 'rgba(22,33,62,0.95)',
        titleFontFamily: "'DM Sans', sans-serif",
        bodyFontFamily:  "'DM Sans', sans-serif",
        borderColor: 'rgba(233,30,140,0.4)',
        borderWidth: 1,
        callbacks: {
          label: i => ' ₱' + parseFloat(i.yLabel).toLocaleString('en', {minimumFractionDigits: 2})
        }
      },
      scales: {
        xAxes: [{
          gridLines: { color: 'rgba(255,255,255,0.04)' },
          ticks: { fontFamily: "'DM Sans', sans-serif", fontSize: 11 }
        }],
        yAxes: [{
          gridLines: { color: 'rgba(255,255,255,0.06)' },
          ticks: {
            beginAtZero: true,
            fontFamily: "'DM Sans', sans-serif",
            fontSize: 11,
            callback: v => '₱' + v.toLocaleString()
          }
        }]
      }
    }
  });
});
</script>

<!-- Dark / Light Mode Toggle -->
<script>
$(function () {
  var isDark = localStorage.getItem('darkMode') === 'true';

  function applyMode(dark) {
    if (dark) {
      $('body').addClass('dark-mode');
      $('.main-header.navbar')
        .addClass('navbar-dark')
        .removeClass('navbar-white navbar-light bg-white');
      $('#darkModeToggle i').removeClass('fa-moon').addClass('fa-sun');
    } else {
      $('body').removeClass('dark-mode');
      $('.main-header.navbar')
        .removeClass('navbar-dark')
        .addClass('navbar-white navbar-light bg-white');
      $('#darkModeToggle i').removeClass('fa-sun').addClass('fa-moon');
    }
  }

  /* Set correct icon on load (body class already applied by inline script) */
  applyMode(isDark);

  $('#darkModeToggle').on('click', function (e) {
    e.preventDefault();
    isDark = !isDark;
    localStorage.setItem('darkMode', isDark);
    applyMode(isDark);
    $(this).addClass('clicked');
    setTimeout(() => $(this).removeClass('clicked'), 300);
  });
});
</script>

<!-- ── Animated Counters ──────────────────────────────────────── -->
<script>
(function () {
  function easeOutQuart(t) { return 1 - Math.pow(1 - t, 4); }

  function animateCounter(el, target, duration, prefix, decimals) {
    var start = null;
    prefix  = prefix  || '';
    decimals = decimals || 0;
    function step(ts) {
      if (!start) start = ts;
      var progress = Math.min((ts - start) / duration, 1);
      var ease = easeOutQuart(progress);
      var value = ease * target;
      el.textContent = prefix + value.toLocaleString('en', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
      });
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  document.addEventListener('DOMContentLoaded', function () {
    // Parse each info-box-number and animate if it looks numeric
    document.querySelectorAll('.info-box-number').forEach(function (el) {
      var raw = el.textContent.trim();
      var prefix = raw.includes('₱') ? '₱' : '';
      var cleaned = raw.replace(/[₱,\s]/g, '');
      var num = parseFloat(cleaned);
      if (isNaN(num)) return;
      var decimals = raw.includes('.') ? 2 : 0;
      el.style.opacity = '1'; // reveal
      animateCounter(el, num, 1200, prefix, decimals);
    });
  });
})();
</script>

<script>
/* empress-realtime.js — inlined (SSE endpoint: index2.php?sse=1) */
(function () {
  'use strict';

  var SSE_URL = 'index2.php?sse=1';
  var SELECTED_RANGE = <?= json_encode($selectedRange) ?>;

  function peso(v, dec) {
    return '₱' + parseFloat(v).toLocaleString('en', {
      minimumFractionDigits: dec !== undefined ? dec : 2,
      maximumFractionDigits: dec !== undefined ? dec : 2
    });
  }

  function num(v) {
    return parseInt(v, 10).toLocaleString('en');
  }

  function morphNumber(el, newVal, prefix, decimals) {
    var oldText = el.textContent.replace(/[₱,\s]/g, '');
    var oldVal  = parseFloat(oldText) || 0;
    if (Math.abs(oldVal - newVal) < 0.001) return;
    var dur = 600, start = null;
    function step(ts) {
      if (!start) start = ts;
      var p    = Math.min((ts - start) / dur, 1);
      var ease = 1 - Math.pow(1 - p, 3);
      var cur  = oldVal + (newVal - oldVal) * ease;
      el.textContent = (prefix || '') + cur.toLocaleString('en', {
        minimumFractionDigits: decimals || 0,
        maximumFractionDigits: decimals || 0
      });
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  function flash(el, colour) {
    el.style.transition = 'none';
    el.style.backgroundColor = colour || 'rgba(233,30,140,0.18)';
    setTimeout(function () {
      el.style.transition = 'background-color 1.2s ease';
      el.style.backgroundColor = '';
    }, 80);
  }

  var PESO_KEYS = {
    totalRevenue: true, dailyRevenue: true, thisMonthRev: true,
    lastMonthRev: true, todayRevenue: true
  };
  var PLAIN_KEYS = {
    dailyCustomers: true, ordersToday: true, staffCount: true,
    dailyItemsSold: true, lowStockCount: true, expiredCount: true,
    expiringSoonCount: true, totalOrders: true, menuAvailable: true,
    menuTotal: true, suppliersCount: true, voidedCount: true,
    refundedCount: true, todayOrders: true
  };

  var _prev = {};

  function applyScalar(key, val) {
    var els = document.querySelectorAll('[data-rt="' + key + '"]');
    if (!els.length) return;
    var isPeso  = !!PESO_KEYS[key];
    var isPlain = !!PLAIN_KEYS[key];
    els.forEach(function (el) {
      var newNum = parseFloat(val);
      var dec    = isPeso ? 2 : 0;
      var prefix = isPeso ? '₱' : '';
      if (_prev[key] !== undefined && _prev[key] !== val) flash(el);
      if (isPeso || isPlain) {
        morphNumber(el, newNum, prefix, dec);
      } else {
        el.textContent = val;
      }
      if (key === 'expiredCount'  && newNum > 0) el.style.color = '#ff5252';
      if (key === 'lowStockCount')               el.style.color = newNum > 0 ? '#fd7e14' : '';
    });
    _prev[key] = val;
  }

  function updateTicker(d) {
    var span = document.querySelector('#live-ticker span');
    if (!span) return;
    span.innerHTML =
      '📊 Tables Served Today: <strong>' + num(d.dailyCustomers) + '</strong> &nbsp;·&nbsp;' +
      "Today's Revenue: <strong>" + peso(d.dailyRevenue) + '</strong> &nbsp;·&nbsp;' +
      'Orders Today: <strong>' + num(d.ordersToday) + '</strong> &nbsp;·&nbsp;' +
      'Low Stock Alerts: <strong>' + num(d.lowStockCount) + '</strong> &nbsp;·&nbsp;' +
      (d.expiredCount > 0
        ? '⚠️ Expired Ingredients: <strong style="color:#ffcdd2;">' + num(d.expiredCount) + '</strong> &nbsp;·&nbsp;'
        : '') +
      'All-Time Revenue: <strong>' + peso(d.totalRevenue) + '</strong> &nbsp;·&nbsp;' +
      'Staff Count: <strong>' + num(d.staffCount) + '</strong>';
  }

  function updateRecentOrders(orders) {
    var tbodies = document.querySelectorAll('[data-rt-container="recentOrders"]');
    if (!tbodies.length || !orders || !orders.length) return;

    // Only overwrite the table rows when the user is viewing "today" (live mode).
    // For any other date-filter range the PHP-rendered rows are authoritative —
    // we must NOT replace them with the SSE payload (which is always last 20 all-time).
    var isLiveMode = (SELECTED_RANGE === 'today');

    tbodies.forEach(function (tbody) {
      var prevIds = Array.from(tbody.querySelectorAll('tr[data-order-id]'))
        .map(function (r) { return r.getAttribute('data-order-id'); });

      if (isLiveMode) {
        var newIds  = orders.map(function (o) { return String(o.id); });
        if (JSON.stringify(prevIds) === JSON.stringify(newIds)) return;
        var html = orders.map(function (o) {
          var statusBadge = '';
          if      (o.status === 'voided')         statusBadge = '<span class="badge badge-danger">Voided</span>';
          else if (o.status === 'refunded')       statusBadge = '<span class="badge badge-info">Refunded</span>';
          else if (o.status === 'partial_refund') statusBadge = '<span class="badge badge-warning">Partial Refund</span>';
          else                                    statusBadge = '<span class="badge badge-success">Completed</span>';
          var date    = new Date(o.created_at);
          var timeStr = date.toLocaleTimeString('en-PH', {hour:'2-digit',minute:'2-digit',hour12:true});
          var dateStr = date.toLocaleDateString('en-PH', {month:'short',day:'numeric'});
          var isNew   = prevIds.indexOf(String(o.id)) === -1 ? ' data-rt-new="1"' : '';
          return '<tr data-order-id="' + o.id + '"' + isNew + '>' +
            '<td><strong>#' + o.id + '</strong></td>' +
            '<td>' + (o.table_no || '—') + '</td>' +
            '<td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + (o.items||'') + '">' + (o.items || '—') + '</td>' +
            '<td>' + (o.cashier_name || 'N/A') + '</td>' +
            '<td><small class="text-muted">' + dateStr + ' ' + timeStr + '</small></td>' +
            '<td><strong class="text-success">₱' + parseFloat(o.total_amt).toLocaleString('en',{minimumFractionDigits:2}) + '</strong></td>' +
            '<td>' + statusBadge + '</td>' +
            '</tr>';
        }).join('');
        tbody.innerHTML = html;
        /* Flash highlight rows that are brand-new */
        tbody.querySelectorAll('tr[data-rt-new="1"]').forEach(function (tr) {
          tr.style.transition = 'none';
          tr.style.backgroundColor = 'rgba(233,30,140,0.18)';
          setTimeout(function () {
            tr.style.transition = 'background-color 1.4s ease';
            tr.style.backgroundColor = '';
          }, 80);
        });
      }
      // In live mode, also update the summary badge count to reflect real-time row count
      if (isLiveMode) {
        var badge = document.getElementById('recentOrdersBadge');
        if (badge) {
          var count = tbody.querySelectorAll('tr[data-order-id]').length;
          badge.textContent = count + ' order' + (count !== 1 ? 's' : '') + ' · Today';
        }
      }
    });
  }

  function updateInventory(rows) {
    var tbody = document.querySelector('[data-rt-container="inventory"]');
    if (!tbody || !rows || !rows.length) return;
    var existingMap = {};
    tbody.querySelectorAll('tr[data-ing-id]').forEach(function (r) {
      existingMap[r.getAttribute('data-ing-id')] = r;
    });
    rows.forEach(function (item) {
      var tr = existingMap[String(item.id)];
      if (!tr) return;
      var stockCell = tr.querySelector('[data-ing-stock]');
      if (stockCell) {
        var oldVal = parseFloat(stockCell.textContent);
        var newVal = parseFloat(item.stock_qty);
        if (Math.abs(oldVal - newVal) > 0.001) {
          flash(stockCell, newVal < oldVal ? 'rgba(239,68,68,.25)' : 'rgba(34,197,94,.2)');
          stockCell.textContent = newVal.toLocaleString('en', {maximumFractionDigits:2});
        }
      }
      var healthCell = tr.querySelector('[data-ing-health]');
      if (healthCell) {
        var badge = '';
        if      (item.health === 'expired') badge = '<span class="badge badge-danger">Expired</span>';
        else if (item.health === 'soon')    badge = '<span class="badge badge-warning">Expiring Soon</span>';
        else if (item.health === 'low')     badge = '<span class="badge badge-warning">Low Stock</span>';
        else                                badge = '<span class="badge badge-success">OK</span>';
        if (healthCell.innerHTML !== badge) healthCell.innerHTML = badge;
      }
    });
  }

  var _lastKnownOrderId = null;

  function checkNewOrder(d) {
    if (!d.latestOrderId) return;
    if (_lastKnownOrderId === null) { _lastKnownOrderId = d.latestOrderId; return; }
    if (d.latestOrderId > _lastKnownOrderId) {
      _lastKnownOrderId = d.latestOrderId;
      var toast   = document.getElementById('newOrderToast');
      var msgEl   = document.getElementById('newOrderToastMsg');
      var countEl = document.getElementById('toastCountBadge');
      var timeEl  = document.getElementById('toastTimeBadge');
      if (!toast) return;
      var lo = d.latestOrder || {};
      if (msgEl)   msgEl.textContent   = 'Table ' + (lo.table_no || '—') + ' — ₱' + parseFloat(lo.total_amt || 0).toLocaleString('en',{minimumFractionDigits:2});
      if (countEl) countEl.textContent = '#' + (lo.id || '');
      if (timeEl)  timeEl.textContent  = new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',hour12:true});
      toast.style.display    = 'block';
      toast.style.animation  = 'none';
      void toast.offsetWidth;
      toast.style.animation  = 'toastSlideIn .35s cubic-bezier(.22,1,.36,1)';
      clearTimeout(toast._hideTimer);
      toast._hideTimer = setTimeout(function () { toast.style.display = 'none'; }, 8000);
      try {
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        var osc = ctx.createOscillator();
        var g   = ctx.createGain();
        osc.connect(g); g.connect(ctx.destination);
        osc.type = 'sine'; osc.frequency.value = 880;
        g.gain.setValueAtTime(0.3, ctx.currentTime);
        g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
        osc.start(); osc.stop(ctx.currentTime + 0.4);
      } catch(e) {}
    }
  }

  function setLiveDot(connected) {
    document.querySelectorAll('.rt-live-dot').forEach(function (d) {
      d.style.background = connected ? '#22c55e' : '#ef4444';
      d.title = connected ? 'Live data connected' : 'Reconnecting…';
    });
  }

  function handleAdminStats(d) {
    var scalars = [
      'dailyCustomers','totalRevenue','ordersToday','staffCount',
      'dailyItemsSold','dailyRevenue','lowStockCount','expiredCount',
      'expiringSoonCount','totalOrders','thisMonthRev','lastMonthRev',
      'menuAvailable','menuTotal','suppliersCount','voidedCount','refundedCount'
    ];
    scalars.forEach(function (k) { if (d[k] !== undefined) applyScalar(k, d[k]); });
    updateTicker(d);
    updateRecentOrders(d.recentOrders);
    updateInventory(d.inventory);
    checkNewOrder(d);
  }

  function connect() {
    if (!window.EventSource) {
      console.warn('[empress-realtime] EventSource not supported.');
      return;
    }
    var es = new EventSource(SSE_URL);
    es.addEventListener('stats', function (e) {
      try { var d = JSON.parse(e.data); setLiveDot(true); handleAdminStats(d); }
      catch (ex) { console.error('[empress-realtime] parse error', ex); }
    });
    es.onerror = function () { setLiveDot(false); };
    es.onopen  = function () { setLiveDot(true);  };
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', connect);
  } else {
    connect();
  }
})();
</script>
<script>
/* ── Date Range Picker ─────────────────────────────────────── */
(function(){
  var MN=['January','February','March','April','May','June','July','August','September','October','November','December'];
  var DN=['M','T','W','T','F','S','S'];
  var today=new Date();today.setHours(0,0,0,0);
  var vY=today.getFullYear(),vM=today.getMonth()>0?today.getMonth()-1:11;
  if(today.getMonth()===0)vY--;
  var sA=null,sB=null,ph=0;
  <?php if($selectedRange==='custom'&&$dateFrom&&$dateTo):?>
  sA=new Date('<?=$dateFrom?>');sA.setHours(0,0,0,0);
  sB=new Date('<?=$dateTo?>');sB.setHours(0,0,0,0);
  vY=sA.getFullYear();vM=sA.getMonth();
  <?php elseif($selectedRange==='today'):?>sA=new Date(today);sB=new Date(today);
  <?php elseif($selectedRange==='7days'):?>sB=new Date(today);sA=new Date(today);sA.setDate(sA.getDate()-6);
  <?php elseif($selectedRange==='30days'):?>sB=new Date(today);sA=new Date(today);sA.setDate(sA.getDate()-29);
  <?php endif;?>
  function iso(d){return d.getFullYear()+'-'+p2(d.getMonth()+1)+'-'+p2(d.getDate());}
  function p2(n){return String(n).padStart(2,'0');}
  function fmt(d){return d?MN[d.getMonth()].slice(0,3)+' '+d.getDate()+', '+d.getFullYear():'—';}
  function sd(a,b){return a&&b&&a.getTime()===b.getTime();}
  function build(tid,yr,mo){
    var t=document.getElementById(tid);t.innerHTML='';
    var hr=t.insertRow();DN.forEach(function(h){var th=document.createElement('th');th.textContent=h;hr.appendChild(th);});
    var fd=(new Date(yr,mo,1).getDay()+6)%7,dm=new Date(yr,mo+1,0).getDate(),dp=new Date(yr,mo,0).getDate();
    for(var i=0;i<42;i++){
      if(i%7===0)t.insertRow();
      var tr=t.rows[t.rows.length-1],td=document.createElement('td');
      var oth=(i<fd)||(i>=fd+dm),dy,cy,cm;
      if(i<fd){dy=dp-fd+i+1;cm=mo-1;cy=yr;if(cm<0){cm=11;cy--;}}
      else if(i>=fd+dm){dy=i-fd-dm+1;cm=mo+1;cy=yr;if(cm>11){cm=0;cy++;}}
      else{dy=i-fd+1;cm=mo;cy=yr;}
      var cd=new Date(cy,cm,dy);cd.setHours(0,0,0,0);
      if(oth)td.classList.add('other');
      if(sd(cd,today))td.classList.add('is-today');
      if(!oth){
        if(sA&&sB){
          if(sd(cd,sA)&&sd(cd,sB))td.classList.add('sel-start','sel-end');
          else if(sd(cd,sA))td.classList.add('sel-start');
          else if(sd(cd,sB))td.classList.add('sel-end');
          else if(cd>sA&&cd<sB)td.classList.add('in-range');
        }else if(sA&&!sB&&sd(cd,sA))td.classList.add('sel-start','sel-end');
        (function(d){td.addEventListener('click',function(){click(d);});})(cd);
      }
      var sp=document.createElement('span');sp.className='drp-day';sp.textContent=dy;td.appendChild(sp);
      tr.appendChild(td);
    }
  }
  function render(){
    var yA=vY,mA=vM,mB=mA+1,yB=yA;if(mB>11){mB=0;yB++;}
    document.getElementById('drpTA').textContent=MN[mA]+', '+yA;
    document.getElementById('drpTB').textContent=MN[mB]+', '+yB;
    build('drpCA',yA,mA);build('drpCB',yB,mB);
    document.getElementById('drpRL').textContent=sA?(sB&&!sd(sA,sB)?fmt(sA)+' – '+fmt(sB):fmt(sA)):'—';
  }
  function click(d){
    if(ph===0||(sA&&sB)){sA=new Date(d);sB=null;ph=1;}
    else{if(d<sA){sB=new Date(sA);sA=new Date(d);}else{sB=new Date(d);}ph=0;}
    render();
  }
  window.drpToggle=function(){
    var p=document.getElementById('drpPopup'),b=document.getElementById('drpBackdrop'),btn=document.getElementById('drpTriggerBtn');
    if(p.style.display==='block'){drpClose();return;}
    p.style.display='block';b.style.display='block';btn.classList.add('open');render();
  };
  window.drpClose=function(){
    document.getElementById('drpPopup').style.display='none';
    document.getElementById('drpBackdrop').style.display='none';
    document.getElementById('drpTriggerBtn').classList.remove('open');
  };
  document.getElementById('drpPrev').addEventListener('click',function(){vM--;if(vM<0){vM=11;vY--;}render();});
  document.getElementById('drpNext').addEventListener('click',function(){vM++;if(vM>11){vM=0;vY++;}render();});
  document.querySelectorAll('.drp-pi').forEach(function(el){
    el.addEventListener('click',function(){
      var pk=this.dataset.p;sB=new Date(today);sA=new Date(today);
      if(pk==='7days')sA.setDate(sA.getDate()-6);
      else if(pk==='30days')sA.setDate(sA.getDate()-29);
      else if(pk==='3months')sA.setMonth(sA.getMonth()-3);
      else if(pk==='12months')sA.setFullYear(sA.getFullYear()-1);
      else if(pk==='mtd')sA=new Date(today.getFullYear(),today.getMonth(),1);
      else if(pk==='ytd')sA=new Date(today.getFullYear(),0,1);
      else if(pk==='alltime')sA=new Date(2000,0,1);
      sA.setHours(0,0,0,0);sB.setHours(0,0,0,0);
      vY=sA.getFullYear();vM=sA.getMonth();ph=0;
      document.querySelectorAll('.drp-pi').forEach(function(e){e.classList.remove('active');});
      this.classList.add('active');
      document.getElementById('drpFR').value=pk;
      document.getElementById('drpFF').value='';
      document.getElementById('drpFT').value='';
      document.getElementById('drpForm').submit();
    });
  });
  document.getElementById('drpApply').addEventListener('click',function(){
    if(!sA)return;if(!sB)sB=new Date(sA);
    document.getElementById('drpFR').value='custom';
    document.getElementById('drpFF').value=iso(sA);
    document.getElementById('drpFT').value=iso(sB);
    document.getElementById('drpForm').submit();
  });
  document.addEventListener('keydown',function(e){if(e.key==='Escape')drpClose();});
})();
</script>
</body>
</html>