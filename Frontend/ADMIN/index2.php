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
$r = $conn->query("SELECT name, price, description FROM menu ORDER BY id DESC LIMIT 4");
if ($r) while ($row = $r->fetch_assoc()) $newMenuItems[] = $row;

// ── Staff list ────────────────────────────────────────────────
$staffList = [];
$r = $conn->query("SELECT firstname, lastname, image FROM user WHERE position = 'staff' ORDER BY id DESC LIMIT 5");
if ($r) while ($row = $r->fetch_assoc()) $staffList[] = $row;

// ── Recent orders (valid only) ────────────────────────────────
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
         WHERE $VALID
         GROUP BY o.id ORDER BY o.created_at DESC LIMIT 5"
    );
    if ($r) while ($row = $r->fetch_assoc()) $recentOrders[] = $row;
} else {
    $r = $conn->query("SELECT o.id, o.table_no, o.total_amt, o.created_at, COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name, '—' AS items FROM orders o LEFT JOIN user u ON u.id = o.user_id WHERE $VALID ORDER BY o.created_at DESC LIMIT 5");
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
              : '../dist/img/avatar.png';
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
          <div class="col-sm-6"><h1 class="m-0">Cafe Shop Analytics</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
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
                        <div class="product-img">
                          <img src="../dist/img/default-150x150.png" alt="<?= htmlspecialchars($item['name']) ?>" class="img-size-50">
                        </div>
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
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header border-transparent">
                <h3 class="card-title"><i class="fas fa-receipt mr-2"></i>Recent Orders</h3>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table m-0 w-100">
                    <thead>
                      <tr>
                        <th>Order ID</th>
                        <th>Cashier</th>
                        <th>Date &amp; Time</th>
                        <th>Number No</th>
                        <th>Items</th>
                        <th>Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($recentOrders)):
                        foreach ($recentOrders as $ro): ?>
                      <tr>
                        <td><strong>#<?= (int)$ro['id'] ?></strong></td>
                        <td><?= htmlspecialchars($ro['cashier_name'] ?? '—') ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars(date('M d, Y g:i A', strtotime($ro['created_at']))) ?></small></td>
                        <td><span class="badge badge-secondary"># <?= htmlspecialchars($ro['table_no']) ?></span></td>
                        <td><?= htmlspecialchars(mb_strimwidth($ro['items'], 0, 50, '…')) ?></td>
                        <td><span class="text-success font-weight-bold">&#8369;<?= number_format((float)$ro['total_amt'], 2) ?></span></td>
                      </tr>
                      <?php endforeach; ?>
                      <?php else: ?>
                      <tr><td colspan="6" class="text-center text-muted p-3">No recent orders.</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="card-footer clearfix">
                <a href="sale_revenue.php" class="btn btn-sm btn-secondary float-right">View All Orders</a>
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


</body>
</html>