<?php
session_name('ADMIN_SESSION');
session_start();
if (!isset($_SESSION['user']) || $_SESSION['position'] !== 'admin') {
    header("Location: ../../lockscreen.html");
    exit();
}

require_once '../../Backend/conn.php';

// ── Helper ─────────────────────────────────────────────────────
function tableExists($conn, $table) {
    $r = $conn->query("SHOW TABLES LIKE '$table'");
    return $r && $r->num_rows > 0;
}
$hasOrderItems = tableExists($conn, 'order_items');

// ── Valid order filter (exclude voided / refunded) ─────────────
$VALID = "status NOT IN ('voided','refunded','partial_refund')";

// ── Summary Stats ──────────────────────────────────────────────
$totalRevenue = 0.0;
$totalOrders  = 0;
$r = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE $VALID");
if ($r && $row = $r->fetch_assoc()) {
    $totalOrders  = (int)$row['cnt'];
    $totalRevenue = (float)$row['rev'];
}

// Refund deduction
$totalRefundAmt = 0.0;
$rRef = $conn->query("SELECT COALESCE(SUM(refund_amt),0) AS amt FROM order_refunds");
if ($rRef && $rowRef = $rRef->fetch_assoc()) $totalRefundAmt = (float)$rowRef['amt'];

// Today's revenue
$todayRevenue = 0.0;
$r2 = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at) = CURDATE() AND $VALID");
if ($r2 && $row2 = $r2->fetch_assoc()) $todayRevenue = (float)$row2['rev'];

// This month's revenue
$monthRevenue = 0.0;
$r3 = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) AND $VALID");
if ($r3 && $row3 = $r3->fetch_assoc()) $monthRevenue = (float)$row3['rev'];

// Average order value
$avgOrder = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// ── Monthly Sales Chart (last 6 months) — single query ────────
$monthLabels = [];
$monthData   = [];

// Build the 6 month slots first so we always show all 6 even if revenue is 0
$monthSlots = [];
for ($i = 5; $i >= 0; $i--) {
    $key           = date('Y-m', strtotime("-$i months"));
    $monthSlots[$key] = 0.0;
    $monthLabels[]    = date('M Y', strtotime("-$i months"));
}

$mStmt = $conn->prepare(
    "SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym,
            COALESCE(SUM(total_amt),0) AS rev
     FROM orders
     WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-01')
       AND $VALID
     GROUP BY ym"
);
$mStmt->execute();
$mRes = $mStmt->get_result();
while ($row = $mRes->fetch_assoc()) {
    if (isset($monthSlots[$row['ym']])) {
        $monthSlots[$row['ym']] = (float)$row['rev'];
    }
}
$mStmt->close();
$monthData = array_values($monthSlots);

// ── Revenue Forecasting — Linear Regression on 6-month data ───
// Uses least-squares linear regression to project next 3 months
function linearRegression(array $y): array {
    $n = count($y);
    if ($n < 2) return ['slope' => 0, 'intercept' => 0];
    $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
    for ($i = 0; $i < $n; $i++) {
        $sumX  += $i;
        $sumY  += $y[$i];
        $sumXY += $i * $y[$i];
        $sumX2 += $i * $i;
    }
    $denom = $n * $sumX2 - $sumX * $sumX;
    if ($denom == 0) return ['slope' => 0, 'intercept' => $sumY / $n];
    $slope     = ($n * $sumXY - $sumX * $sumY) / $denom;
    $intercept = ($sumY - $slope * $sumX) / $n;
    return ['slope' => $slope, 'intercept' => $intercept];
}

$reg = linearRegression($monthData);
$forecastData   = [];
$forecastLabels = [];
$n = count($monthData);
for ($f = 1; $f <= 3; $f++) {
    $forecastVal      = max(0, $reg['intercept'] + $reg['slope'] * ($n - 1 + $f));
    $forecastData[]   = round($forecastVal, 2);
    $forecastLabels[] = date('M Y', strtotime("+$f months"));
}

// Projected next-month revenue & growth rate
$projNextMonth   = $forecastData[0];
$lastMonthActual = end($monthData);
$projGrowthPct   = $lastMonthActual > 0
    ? (($projNextMonth - $lastMonthActual) / $lastMonthActual) * 100
    : 0;

$forecastDataJson   = json_encode($forecastData);
$forecastLabelsJson = json_encode($forecastLabels);

// ── Top Selling Items (for donut chart + sales table) ──────────
$topItems   = [];
$tableItems = [];
if ($hasOrderItems) {
    $r4 = $conn->query(
        "SELECT m.name, m.category, m.price,
                SUM(oi.qty) AS qty_sold,
                SUM(oi.qty * oi.unit_price) AS revenue
         FROM order_items oi
         JOIN menu m ON m.id = oi.menu_id
         JOIN orders o ON o.id = oi.order_id
         WHERE $VALID
         GROUP BY oi.menu_id
         ORDER BY qty_sold DESC
         LIMIT 10"
    );
    if ($r4) while ($row = $r4->fetch_assoc()) {
        $topItems[]   = $row;
        $tableItems[] = $row;
    }
}

// ── Latest Orders ──────────────────────────────────────────────
$latestOrders = [];
if ($hasOrderItems) {
    $r5 = $conn->query(
        "SELECT o.id, o.created_at, o.table_no, o.total_amt,
                COALESCE(o.discount_amt, 0) AS discount_amt,
                COALESCE(o.discount_type, '') AS discount_type,
                COALESCE(CONCAT(u.firstname,' ',u.lastname), 'N/A') AS cashier_name,
                GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items,
                GROUP_CONCAT(
                    CONCAT(
                        m.name,'|',oi.qty,'|',
                        COALESCE(oi.addons,''),'|',
                        CASE
                            WHEN oi.removed_ingredient_names IS NOT NULL
                                 AND oi.removed_ingredient_names != '[]'
                                 AND oi.removed_ingredient_names != ''
                            THEN oi.removed_ingredient_names
                            WHEN oi.removed_ingredient_ids IS NOT NULL
                                 AND oi.removed_ingredient_ids != '[]'
                                 AND oi.removed_ingredient_ids != ''
                            THEN (
                                SELECT CONCAT('[',GROUP_CONCAT(JSON_QUOTE(i2.name) ORDER BY i2.name),']')
                                FROM ingredients i2
                                WHERE JSON_SEARCH(oi.removed_ingredient_ids, 'one', CAST(i2.id AS CHAR)) IS NOT NULL
                            )
                            ELSE ''
                        END
                    )
                    ORDER BY m.name SEPARATOR ';;'
                ) AS item_details
         FROM orders o
         LEFT JOIN user u ON u.id = o.user_id
         JOIN order_items oi ON oi.order_id = o.id
         JOIN menu m ON m.id = oi.menu_id
         WHERE $VALID
         GROUP BY o.id
         ORDER BY o.created_at DESC LIMIT 10"
    );
    if ($r5) while ($row = $r5->fetch_assoc()) $latestOrders[] = $row;
} else {
    $r5 = $conn->query("SELECT o.id, o.created_at, o.table_no, o.total_amt, COALESCE(o.discount_amt,0) AS discount_amt, COALESCE(o.discount_type,'') AS discount_type, COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name, '—' AS items, '' AS item_details FROM orders o LEFT JOIN user u ON u.id = o.user_id WHERE $VALID ORDER BY o.created_at DESC LIMIT 10");
    if ($r5) while ($row = $r5->fetch_assoc()) $latestOrders[] = $row;
}

// ── Daily Revenue – last 7 days — single query ─────────────────
$dayLabels = [];
$dayData   = [];

// Build 7-day slots so missing days show as 0
$daySlots = [];
for ($i = 6; $i >= 0; $i--) {
    $key           = date('Y-m-d', strtotime("-$i days"));
    $daySlots[$key] = 0.0;
    $dayLabels[]    = date('M d', strtotime("-$i days"));
}

$dStmt = $conn->prepare(
    "SELECT DATE(created_at) AS d, COALESCE(SUM(total_amt),0) AS rev
     FROM orders
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
       AND $VALID
     GROUP BY d"
);
$dStmt->execute();
$dRes = $dStmt->get_result();
while ($row = $dRes->fetch_assoc()) {
    if (isset($daySlots[$row['d']])) {
        $daySlots[$row['d']] = (float)$row['rev'];
    }
}
$dStmt->close();
$dayData = array_values($daySlots);

// ── Category Revenue (valid orders only) ──────────────────────
$catRevenue = [];
if ($hasOrderItems) {
    $r6 = $conn->query(
        "SELECT m.category, SUM(oi.qty * oi.unit_price) AS revenue
         FROM order_items oi
         JOIN menu m ON m.id = oi.menu_id
         JOIN orders o ON o.id = oi.order_id
         WHERE $VALID
         GROUP BY m.category ORDER BY revenue DESC LIMIT 5"
    );
    if ($r6) while ($row = $r6->fetch_assoc()) $catRevenue[] = $row;
}
$maxCatRev = !empty($catRevenue) ? (float)$catRevenue[0]['revenue'] : 1;

$conn->close();

// JSON for charts
$monthLabelsJson = json_encode($monthLabels);
$monthDataJson   = json_encode($monthData);
$dayLabelsJson   = json_encode($dayLabels);
$dayDataJson     = json_encode($dayData);
$donutLabels     = json_encode(array_column($topItems, 'name'));
$donutData       = json_encode(array_map(fn($i) => (float)$i['qty_sold'], $topItems));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OPERLYTICS | Sales &amp; Revenue</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" href="../dist/css/empress-cafe-theme.css">
  <style>
    body, .main-header.navbar { transition: background-color .5s ease, color .5s ease; }
    #darkModeToggle            { transition: box-shadow .3s ease; }
    
    #darkModeToggle.clicked    { box-shadow: 0 0 15px rgba(255,255,255,.8); }
    
    .stat-label { font-size: .82rem; color: #aaa; }
    .stat-val   { font-size: 1.5rem; font-weight: 700; margin-bottom: 2px; }
  
    
    /* FIX: Table hover visible in light mode */
    body:not(.dark-mode) .table tbody tr:hover { background-color: rgba(233,30,140,0.08) !important; }
    body:not(.dark-mode) .table tbody tr:hover td { color: #212529 !important; }

    /* FIX: table-dark class invisible in light mode — keep text readable */
    body:not(.dark-mode) .table-dark,
    body:not(.dark-mode) .table-dark th,
    body:not(.dark-mode) .table-dark td,
    body:not(.dark-mode) .table-dark thead th {
      background-color: transparent !important;
      color: #212529 !important;
      border-color: #dee2e6 !important;
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

</style>
</head>
<body class="hold-transition dark-mode sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">

  <!-- ── Navbar ─────────────────────────────────────────────── -->
  <nav class="main-header navbar navbar-expand navbar-dark">
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
        <a class="nav-link" data-widget="navbar-search" href="#" role="button"><i class="fas fa-search"></i></a>
        <div class="navbar-search-block">
          <form class="form-inline">
            <div class="input-group input-group-sm">
              <input class="form-control form-control-navbar" type="search" placeholder="Search">
              <div class="input-group-append">
                <button class="btn btn-navbar" type="submit"><i class="fas fa-search"></i></button>
                <button class="btn btn-navbar" type="button" data-widget="navbar-search"><i class="fas fa-times"></i></button>
              </div>
            </div>
          </form>
        </div>
      </li>
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
      <div class="image"><img src="../dist/img/avatar.png" class="img-circle elevation-2" alt="User Image"></div>
      <div class="info">
          <a href="#" class="d-block"><?= htmlspecialchars($_SESSION['user'] ?? 'Admin') ?></a>
        </div>
      </div>
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item"><a href="./index2.php"          class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Overview</p></a></li>
          <li class="nav-item"><a href="./menu-management.php" class="nav-link"><i class="nav-icon fas fa-utensils"></i><p>Menu Management</p></a></li>
          <li class="nav-item"><a href="./inventory.php"       class="nav-link"><i class="nav-icon fas fa-boxes"></i><p>Inventory Tracking</p></a></li>
          <li class="nav-item"><a href="./suppliers.php"       class="nav-link"><i class="nav-icon fas fa-truck"></i><p>Supplier Info</p></a></li>
          <li class="nav-item"><a href="./staff-list.php"      class="nav-link"><i class="far fa-user nav-icon"></i><p>Staff List</p></a></li>
          <li class="nav-item"><a href="./sale_revenue.php"    class="nav-link active"><i class="nav-icon fas fa-chart-line"></i><p>Sales &amp; Revenue</p></a></li>
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
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">Sales &amp; Revenue</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="index2.php">Home</a></li>
              <li class="breadcrumb-item active">Sales &amp; Revenue</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">

        <?php if (!$hasOrderItems): ?>
        <div class="alert alert-warning alert-dismissible fade show">
          <i class="fas fa-exclamation-triangle mr-2"></i>
          <strong>Note:</strong> The <code>order_items</code> table is missing. Item-level stats are unavailable.
          Run <strong>create_order_items_table.sql</strong> in phpMyAdmin to enable full data.
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php endif; ?>

        <!-- ── Stat Cards ──────────────────────────────────── -->
        <div class="row">
          <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-info">
              <span class="info-box-icon"><i class="fas fa-money-bill-wave"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Net Revenue</span>
                <span class="info-box-number">&#8369;<?= number_format($totalRevenue, 2) ?></span>
                <span class="progress-description" style="font-size:11px;opacity:.85;">Voided &amp; refunded excluded</span>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-success">
              <span class="info-box-icon"><i class="fas fa-calendar-day"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Today's Revenue</span>
                <span class="info-box-number">&#8369;<?= number_format($todayRevenue, 2) ?></span>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-warning">
              <span class="info-box-icon"><i class="fas fa-calendar-alt"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">This Month</span>
                <span class="info-box-number">&#8369;<?= number_format($monthRevenue, 2) ?></span>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-danger">
              <span class="info-box-icon"><i class="fas fa-shopping-cart"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Valid Orders</span>
                <span class="info-box-number"><?= number_format($totalOrders) ?></span>
              </div>
            </div>
          </div>
        </div>
        <?php if ($totalRefundAmt > 0): ?>
        <div class="row">
          <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box" style="background:#fff3cd;border-left:4px solid #e74c3c;">
              <span class="info-box-icon" style="background:#e74c3c;color:#fff;"><i class="fas fa-undo-alt"></i></span>
              <div class="info-box-content">
                <span class="info-box-text" style="color:#555;">Total Refunded</span>
                <span class="info-box-number" style="color:#e74c3c;">&#8369;<?= number_format($totalRefundAmt, 2) ?></span>
              </div>
            </div>
          </div>
          <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box" style="background:#d4edda;border-left:4px solid #28a745;">
              <span class="info-box-icon" style="background:#28a745;color:#fff;"><i class="fas fa-check-circle"></i></span>
              <div class="info-box-content">
                <span class="info-box-text" style="color:#555;">Gross Revenue (before refunds)</span>
                <span class="info-box-number" style="color:#28a745;">&#8369;<?= number_format($totalRevenue + $totalRefundAmt, 2) ?></span>
              </div>
            </div>
          </div>
          <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box" style="background:#cce5ff;border-left:4px solid #3c8dbc;">
              <span class="info-box-icon" style="background:#3c8dbc;color:#fff;"><i class="fas fa-calculator"></i></span>
              <div class="info-box-content">
                <span class="info-box-text" style="color:#555;">Net After Refunds</span>
                <span class="info-box-number" style="color:#3c8dbc;">&#8369;<?= number_format(max(0, $totalRevenue - $totalRefundAmt), 2) ?></span>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- ── Forecast Stat Cards ─────────────────────────── -->
        <div class="row">
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
            <?php $growthColor = $projGrowthPct >= 0 ? '#28a745' : '#e74c3c';
                  $growthBg    = $projGrowthPct >= 0 ? '#d4edda'  : '#fde8e8';
                  $growthIcon  = $projGrowthPct >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>
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
                <span class="info-box-number" style="color:#3c8dbc;">&#8369;<?= number_format(array_sum($monthData) / max(1, count(array_filter($monthData))), 2) ?></span>
                <span class="progress-description" style="font-size:11px;color:#888;">Based on last 6 months</span>
              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-8">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>Monthly Revenue — Last 6 Months + 3-Month Forecast</h3>
              </div>
              <div class="card-body">
                <canvas id="salesChart" height="120"></canvas>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fas fa-tags mr-2"></i>Revenue by Category</h3>
              </div>
              <div class="card-body">
                <?php if (!empty($catRevenue)): ?>
                  <?php foreach ($catRevenue as $cat):
                    $pct = $maxCatRev > 0 ? round(((float)$cat['revenue'] / $maxCatRev) * 100) : 0;
                    $colors = ['bg-primary','bg-success','bg-warning','bg-danger','bg-info'];
                    static $ci = 0;
                    $color = $colors[$ci++ % count($colors)];
                  ?>
                  <div class="progress-group mb-3">
                    <?= htmlspecialchars($cat['category']) ?>
                    <span class="float-right"><b>&#8369;<?= number_format((float)$cat['revenue'], 2) ?></b></span>
                    <div class="progress progress-sm">
                      <div class="progress-bar <?= $color ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="text-muted text-center mt-3">No category data yet.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Daily Revenue (7 days) + Donut Chart ─────────── -->
        <div class="row">
          <div class="col-md-8">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-line mr-2"></i>Daily Revenue — Last 7 Days</h3>
              </div>
              <div class="card-body">
                <canvas id="lineChart" height="120"></canvas>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Top Items by Qty Sold</h3>
              </div>
              <div class="card-body text-center">
                <?php if (!empty($topItems)): ?>
                  <canvas id="donutChart" style="max-height:250px;"></canvas>
                <?php else: ?>
                  <p class="text-muted mt-4">No item data yet.<br><small>Requires <code>order_items</code> table.</small></p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Latest Orders ─────────────────────────────────── -->
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fas fa-receipt mr-2"></i>Latest Orders</h3>
              </div>
              <div class="card-body table-responsive p-0">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>Order ID</th>
                      <th>Date &amp; Time</th>
                      <th>Cashier</th>
                      <th>Items</th>
                      <th>Add-ons</th>
                      <th>Removed</th>
                      <th>Discount</th>
                      <th>Revenue</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($latestOrders)): ?>
                      <?php foreach ($latestOrders as $lo):
                        // Parse item_details: "name|qty|addons|removed_json;;..."
                        $allAddons  = [];
                        $allRemoved = [];
                        if (!empty($lo['item_details'])) {
                            foreach (explode(';;', $lo['item_details']) as $seg) {
                                $parts   = explode('|', $seg, 4);
                                $iName   = $parts[0] ?? '';
                                $addons  = trim($parts[2] ?? '');
                                $rawRem  = trim($parts[3] ?? '');
                                // Decode JSON array of names e.g. ["Cheese Powder","Onion"]
                                $removed = '';
                                if ($rawRem !== '' && $rawRem !== '[]') {
                                    $arr = json_decode($rawRem, true);
                                    if (is_array($arr) && count($arr) > 0) {
                                        $removed = implode(', ', array_filter($arr));
                                    } elseif (!is_array($arr) && $rawRem !== '') {
                                        $removed = $rawRem;
                                    }
                                }
                                if ($addons)  $allAddons[]  = '<strong>' . htmlspecialchars($iName) . ':</strong> ' . htmlspecialchars($addons);
                                if ($removed) $allRemoved[] = '<strong>' . htmlspecialchars($iName) . ':</strong> No ' . htmlspecialchars($removed);
                            }
                        }
                        $addonsCell  = !empty($allAddons)  ? implode('<br>', $allAddons)  : '<span class="text-muted">—</span>';
                        $removedCell = !empty($allRemoved) ? implode('<br>', $allRemoved) : '<span class="text-muted">—</span>';
                      ?>
                      <tr>
                        <td><strong>#<?= (int)$lo['id'] ?></strong></td>
                        <td style="white-space:nowrap;"><?= htmlspecialchars($lo['created_at']) ?></td>
                        <td><?= htmlspecialchars($lo['cashier_name']) ?></td>
                        <td><?= htmlspecialchars($lo['items']) ?></td>
                        <td style="font-size:12px;"><?= $addonsCell ?></td>
                        <td style="font-size:12px;"><?= $removedCell ?></td>
                        <td>
                          <?php if ((float)$lo['discount_amt'] > 0): ?>
                            <span class="badge badge-success" style="font-size:11px;">
                              <?= $lo['discount_type'] === 'senior' ? 'Senior 20%' : ($lo['discount_type'] === 'pwd' ? 'PWD 20%' : 'Discount') ?>
                            </span><br>
                            <span class="text-danger font-weight-bold">-&#8369;<?= number_format((float)$lo['discount_amt'], 2) ?></span>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>
                        <td><span class="text-success font-weight-bold">&#8369;<?= number_format((float)$lo['total_amt'], 2) ?></span></td>
                      </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="8" class="text-center text-muted">No orders found.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Top Selling Items Table ───────────────────────── -->
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fas fa-star mr-2"></i>Top Selling Items</h3>
              </div>
              <div class="card-body">
                <table id="example1" class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Category</th>
                      <th>Unit Price (&#8369;)</th>
                      <th>Qty Sold</th>
                      <th>Revenue (&#8369;)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($tableItems)): ?>
                      <?php foreach ($tableItems as $ti): ?>
                      <tr>
                        <td><?= htmlspecialchars($ti['name']) ?></td>
                        <td><?= htmlspecialchars($ti['category']) ?></td>
                        <td><?= number_format((float)$ti['price'], 2) ?></td>
                        <td><?= (int)$ti['qty_sold'] ?></td>
                        <td><?= number_format((float)$ti['revenue'], 2) ?></td>
                      </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="5" class="text-center text-muted">No sales data yet.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>
  </div><!-- /.content-wrapper -->
</div><!-- /.wrapper -->

<!-- ── Scripts ──────────────────────────────────────────────── -->
<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
<script src="../dist/js/adminlte.js"></script>
<script src="../plugins/chart.js/Chart.min.js"></script>
<script src="../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="../plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="../plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="../plugins/jszip/jszip.min.js"></script>
<script src="../plugins/pdfmake/pdfmake.min.js"></script>
<script src="../plugins/pdfmake/vfs_fonts.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.colVis.min.js"></script>

<script>
// ── Monthly Revenue Bar Chart with Forecast (Chart.js v4) ────
$(function () {
  var actualLabels   = <?= $monthLabelsJson ?>;
  var actualData     = <?= $monthDataJson ?>;
  var forecastLabels = <?= $forecastLabelsJson ?>;
  var forecastData   = <?= $forecastDataJson ?>;

  var allLabels = actualLabels.concat(forecastLabels);

  // Pad actual data with nulls for forecast months
  var paddedActual   = actualData.concat(forecastLabels.map(() => null));
  // Pad forecast data with nulls for actual months
  var paddedForecast = actualLabels.map(() => null).concat(forecastData);

  new Chart($('#salesChart').get(0).getContext('2d'), {
    type: 'bar',
    data: {
      labels: allLabels,
      datasets: [
        {
          label: 'Actual Revenue (₱)',
          data: paddedActual,
          backgroundColor: 'rgba(233,30,140,0.55)',
          borderColor:     'rgba(233,30,140,1)',
          borderWidth: 2,
          hoverBackgroundColor: 'rgba(233,30,140,0.8)'
        },
        {
          label: 'Forecast Revenue (₱)',
          data: paddedForecast,
          backgroundColor: 'rgba(142,68,173,0.35)',
          borderColor:     'rgba(142,68,173,1)',
          borderWidth: 2,
          borderDash: [6, 3],
          hoverBackgroundColor: 'rgba(142,68,173,0.6)'
        }
      ]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: true },
        tooltip: {
          callbacks: {
            label: ctx => ctx.dataset.label + ': ₱' + parseFloat(ctx.parsed.y).toLocaleString('en', { minimumFractionDigits: 2 })
          }
        }
      },
      scales: {
        y: { beginAtZero: true, ticks: { callback: v => '₱' + v.toLocaleString() } }
      }
    }
  });
});

// ── Daily Revenue Line Chart (Chart.js v4) ───────────────────
$(function () {
  new Chart($('#lineChart').get(0).getContext('2d'), {
    type: 'line',
    data: {
      labels: <?= $dayLabelsJson ?>,
      datasets: [{
        label: 'Daily Revenue (₱)',
        data: <?= $dayDataJson ?>,
        fill: true,
        backgroundColor: 'rgba(60,141,188,0.15)',
        borderColor:     'rgba(60,141,188,1)',
        borderWidth: 2,
        pointRadius: 4,
        pointBackgroundColor: 'rgba(60,141,188,1)'
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => '₱' + parseFloat(ctx.parsed.y).toLocaleString('en', { minimumFractionDigits: 2 })
          }
        }
      },
      scales: {
        y: { beginAtZero: true, ticks: { callback: v => '₱' + v.toLocaleString() } }
      }
    }
  });
});

// ── Donut Chart ───────────────────────────────────────────────
<?php if (!empty($topItems)): ?>
$(function () {
  new Chart($('#donutChart').get(0).getContext('2d'), {
    type: 'doughnut',
    data: {
      labels: <?= $donutLabels ?>,
      datasets: [{
        data: <?= $donutData ?>,
        backgroundColor: [
          '#f56954','#00a65a','#f39c12','#00c0ef',
          '#3c8dbc','#d2d6de','#e91e8c','#8e44ad',
          '#27ae60','#e74c3c'
        ]
      }]
    },
    options: { maintainAspectRatio: false, responsive: true }
  });
});
<?php endif; ?>

// ── DataTable ─────────────────────────────────────────────────
$(function () {
  $('#example1').DataTable({
    responsive: true, lengthChange: false, autoWidth: false,
    order: [[3, 'desc']],
    buttons: ['copy','csv','excel','pdf','print','colvis']
  }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
});

// ── Dark Mode ─────────────────────────────────────────────────
$(function () {
  var dm = localStorage.getItem('darkMode');
  if (dm === 'true') {
    $('body').addClass('dark-mode');
    $('.main-header.navbar').addClass('navbar-dark').removeClass('navbar-white navbar-light bg-white');
    $('#darkModeToggle i').removeClass('fa-moon').addClass('fa-sun');
  } else {
    $('body').removeClass('dark-mode');
    $('.main-header.navbar').removeClass('navbar-dark').addClass('navbar-white navbar-light bg-white');
    $('#darkModeToggle i').removeClass('fa-sun').addClass('fa-moon');
  }
  $('#darkModeToggle').on('click', function (e) {
    e.preventDefault();
    $('body').toggleClass('dark-mode');
    $('.main-header.navbar').toggleClass('navbar-dark navbar-white navbar-light bg-white');
    $(this).find('i').toggleClass('fa-moon fa-sun');
    localStorage.setItem('darkMode', $('body').hasClass('dark-mode'));
    $(this).addClass('clicked');
    setTimeout(() => $(this).removeClass('clicked'), 300);
  });
});
</script>
</body>
</html>