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
$VALIDO = "o.status NOT IN ('voided','refunded','partial_refund')";

// ── Date Range Filter ──────────────────────────────────────────
$drpPresets = ['today'=>0,'7days'=>7,'30days'=>30,'3months'=>90,'12months'=>365,'mtd'=>-1,'ytd'=>-1,'alltime'=>-1,'custom'=>-1];
$selectedRange = (isset($_GET['range']) && array_key_exists($_GET['range'], $drpPresets)) ? $_GET['range'] : 'alltime';

$dateFrom = $dateTo = '';
if ($selectedRange === 'today') {
    $df = "DATE(created_at)=CURDATE()";
    $dfO = "DATE(o.created_at)=CURDATE()";
} elseif ($selectedRange === 'custom') {
    $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from']??'') ? $_GET['date_from'] : date('Y-m-d',strtotime('-7 days'));
    $dateTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to']??'')   ? $_GET['date_to']   : date('Y-m-d');
    $df  = "DATE(created_at) BETWEEN '$dateFrom' AND '$dateTo'";
    $dfO = "DATE(o.created_at) BETWEEN '$dateFrom' AND '$dateTo'";
} elseif ($selectedRange === 'mtd') {
    $df  = "YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())";
    $dfO = "YEAR(o.created_at)=YEAR(CURDATE()) AND MONTH(o.created_at)=MONTH(CURDATE())";
} elseif ($selectedRange === 'ytd') {
    $df  = "YEAR(created_at)=YEAR(CURDATE())";
    $dfO = "YEAR(o.created_at)=YEAR(CURDATE())";
} elseif ($selectedRange === 'alltime') {
    $df = $dfO = "1=1";
} else {
    $days = (int)$drpPresets[$selectedRange];
    $df  = "created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
    $dfO = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
}

// ── Summary Stats ──────────────────────────────────────────────
$totalRevenue = 0.0;
$totalOrders  = 0;
$r = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE $VALID AND $df");
if ($r && $row = $r->fetch_assoc()) {
    $totalOrders  = (int)$row['cnt'];
    $totalRevenue = (float)$row['rev'];
}

// Refund deduction
$totalRefundAmt = 0.0;
$rRef = $conn->query("SELECT COALESCE(SUM(refund_amt),0) AS amt FROM order_refunds WHERE $df");
if ($rRef && $rowRef = $rRef->fetch_assoc()) $totalRefundAmt = (float)$rowRef['amt'];

// Today's revenue (always today regardless of filter)
$todayRevenue = 0.0;
$r2 = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");
if ($r2 && $row2 = $r2->fetch_assoc()) $todayRevenue = (float)$row2['rev'];

// This month's revenue (always this month regardless of filter)
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
         WHERE $VALID AND $dfO
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
         WHERE $VALID AND $dfO
         GROUP BY o.id
         ORDER BY o.created_at DESC LIMIT 10"
    );
    if ($r5) while ($row = $r5->fetch_assoc()) $latestOrders[] = $row;
} else {
    $r5 = $conn->query("SELECT o.id, o.created_at, o.table_no, o.total_amt, COALESCE(o.discount_amt,0) AS discount_amt, COALESCE(o.discount_type,'') AS discount_type, COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name, '—' AS items, '' AS item_details FROM orders o LEFT JOIN user u ON u.id = o.user_id WHERE $VALID AND $dfO ORDER BY o.created_at DESC LIMIT 10");
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
         WHERE $VALID AND $dfO
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

    /* ══ DRP: Date Range Picker ════════════════════════════════ */
    #drpBackdrop{display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);}
    #drpPopup{display:none;position:fixed;z-index:9001;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:16px;box-shadow:0 24px 64px rgba(0,0,0,.25);width:min(780px,96vw);overflow:hidden;}
    body.dark-mode #drpPopup{background:#252535;color:#e0e0e0;}
    .drp-inner{display:flex;}
    .drp-presets{width:158px;flex-shrink:0;border-right:1px solid #f0f0f0;padding:16px 0;}
    body.dark-mode .drp-presets{border-color:#3a3a4a;}
    .drp-pi{padding:9px 20px;cursor:pointer;font-size:.85rem;color:#444;border-left:3px solid transparent;transition:background .12s,color .12s;}
    body.dark-mode .drp-pi{color:#bbb;}
    .drp-pi:hover{background:#fdf0f8;color:#e91e8c;}
    body.dark-mode .drp-pi:hover{background:#3a2a3a;color:#e91e8c;}
    .drp-pi.active{font-weight:700;color:#e91e8c;border-left-color:#e91e8c;background:#fdf0f8;}
    body.dark-mode .drp-pi.active{background:#3a2a3a;}
    .drp-cals{flex:1;padding:18px 22px;display:flex;flex-direction:column;}
    .drp-months{display:flex;gap:24px;flex:1;}
    .drp-mon{flex:1;min-width:0;}
    .drp-mnav{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
    .drp-mnav strong{font-size:.9rem;color:#222;}
    body.dark-mode .drp-mnav strong{color:#eee;}
    .drp-nb{background:none;border:1px solid #ddd;border-radius:6px;width:26px;height:26px;cursor:pointer;color:#666;font-size:.95rem;display:flex;align-items:center;justify-content:center;}
    body.dark-mode .drp-nb{border-color:#555;color:#aaa;}
    .drp-nb:hover{background:#fdf0f8;border-color:#e91e8c;color:#e91e8c;}
    .drp-cal{width:100%;border-collapse:collapse;table-layout:fixed;}
    .drp-cal th{text-align:center;font-size:.72rem;font-weight:600;color:#aaa;padding:4px 0;}
    .drp-cal td{text-align:center;padding:0;width:14.28%;height:34px;font-size:.83rem;cursor:pointer;position:relative;color:#333;overflow:hidden;}
    body.dark-mode .drp-cal td{color:#ddd;}
    .drp-cal td::before{content:'';position:absolute;top:2px;bottom:2px;left:0;right:0;background:transparent;z-index:0;}
    .drp-cal td.ir::before{background:rgba(233,30,140,.15);}
    body.dark-mode .drp-cal td.ir::before{background:rgba(233,30,140,.25);}
    .drp-cal td.ds:not(.de)::before{background:rgba(233,30,140,.15);left:50%;right:0;}
    body.dark-mode .drp-cal td.ds:not(.de)::before{background:rgba(233,30,140,.25);left:50%;right:0;}
    .drp-cal td.de:not(.ds)::before{background:rgba(233,30,140,.15);left:0;right:50%;}
    body.dark-mode .drp-cal td.de:not(.ds)::before{background:rgba(233,30,140,.25);left:0;right:50%;}
    .drp-cal td.ds.de::before{background:transparent!important;}
    .drp-day{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;position:relative;z-index:1;transition:background .12s,color .12s;}
    .drp-cal td:not(.oth):not(.ds):not(.de):hover .drp-day{background:rgba(233,30,140,.12);color:#e91e8c;}
    .drp-cal td.oth{opacity:.35;cursor:default;}
    .drp-cal td.oth::before{display:none!important;}
    .drp-cal td.ds .drp-day,.drp-cal td.de .drp-day{background:#e91e8c!important;color:#fff!important;font-weight:700;}
    .drp-cal td.tod .drp-day::after{content:'';position:absolute;bottom:2px;left:50%;transform:translateX(-50%);width:4px;height:4px;border-radius:50%;background:#e91e8c;}
    .drp-cal td.ds .drp-day::after,.drp-cal td.de .drp-day::after{background:#fff;}
    .drp-foot{display:flex;align-items:center;justify-content:space-between;margin-top:14px;padding-top:12px;border-top:1px solid #f0f0f0;}
    body.dark-mode .drp-foot{border-color:#3a3a4a;}
    .drp-rl{font-size:.82rem;color:#888;}
    .drp-rl strong{color:#333;}
    body.dark-mode .drp-rl strong{color:#ddd;}
    #drpTriggerBtn{display:inline-flex;align-items:center;gap:7px;border:1px solid #ccc;border-radius:8px;padding:5px 13px;background:#fff;cursor:pointer;font-size:.83rem;color:#444;transition:border-color .15s,box-shadow .15s;white-space:nowrap;}
    body.dark-mode #drpTriggerBtn{background:#2a2a3e;border-color:#555;color:#ddd;}
    #drpTriggerBtn:hover{border-color:#e91e8c;box-shadow:0 0 0 3px rgba(233,30,140,.1);}
    #drpTriggerBtn .dci{color:#e91e8c;}
    #drpTriggerBtn .dcv{color:#aaa;font-size:.65rem;transition:transform .2s;}
    #drpTriggerBtn.open .dcv{transform:rotate(180deg);}
    @media(max-width:600px){.drp-months{flex-direction:column;}.drp-presets{width:100%;border-right:none;border-bottom:1px solid #eee;display:flex;flex-wrap:wrap;padding:8px;}.drp-pi{padding:5px 10px;border-left:none;}.drp-inner{flex-direction:column;}}

</style>
<style>
@keyframes rtPulse{0%{box-shadow:0 0 0 0 rgba(34,197,94,.55)}70%{box-shadow:0 0 0 7px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
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
      <li class="nav-item d-flex align-items-center px-2" title="Real-time: connected" style="font-size:.72rem;font-weight:600;color:#6c757d;">
        <span class="rt-live-dot" style="width:8px;height:8px;background:#22c55e;border-radius:50%;display:inline-block;margin-right:5px;box-shadow:0 0 0 0 rgba(34,197,94,.5);animation:rtPulse 1.8s ease infinite;" title="Live data connected"></span>
        <span class="d-none d-sm-inline rt-live-label">Live</span>
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

    <!-- DRP Backdrop & Popup -->
    <div id="drpBackdrop" onclick="drpClose()"></div>
    <div id="drpPopup">
      <div class="drp-inner">
        <div class="drp-presets">
          <?php foreach(['today'=>'Today','7days'=>'Last 7 days','30days'=>'Last 30 days','3months'=>'Last 3 months','12months'=>'Last 12 months','mtd'=>'Month to date','ytd'=>'Year to date','alltime'=>'All time'] as $pk=>$pv): ?>
          <div class="drp-pi<?= $selectedRange===$pk?' active':'' ?>" data-p="<?= $pk ?>"><?= $pv ?></div>
          <?php endforeach; ?>
        </div>
        <div class="drp-cals">
          <div class="drp-months">
            <div class="drp-mon">
              <div class="drp-mnav"><button class="drp-nb" id="drpPrev">&#8249;</button><strong id="drpTA"></strong><span></span></div>
              <table class="drp-cal" id="drpCA"></table>
            </div>
            <div class="drp-mon">
              <div class="drp-mnav"><span></span><strong id="drpTB"></strong><button class="drp-nb" id="drpNext">&#8250;</button></div>
              <table class="drp-cal" id="drpCB"></table>
            </div>
          </div>
          <div class="drp-foot">
            <div class="drp-rl">Range:&nbsp;<strong id="drpRL">—</strong></div>
            <div style="display:flex;gap:8px">
              <button type="button" class="btn btn-sm btn-light" onclick="drpClose()" style="border:1px solid #ddd;min-width:68px">Cancel</button>
              <button type="button" class="btn btn-sm" id="drpApply" style="background:#e91e8c;color:#fff;border:none;min-width:68px">Apply</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <form method="GET" id="drpForm" style="display:none">
      <input type="hidden" name="range"     id="drpFR" value="<?= htmlspecialchars($selectedRange) ?>">
      <input type="hidden" name="date_from" id="drpFF" value="<?= htmlspecialchars($dateFrom) ?>">
      <input type="hidden" name="date_to"   id="drpFT" value="<?= htmlspecialchars($dateTo) ?>">
    </form>

    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2 align-items-center">
          <div class="col-sm-6"><h1 class="m-0">Sales &amp; Revenue</h1></div>
          <div class="col-sm-6 d-flex align-items-center justify-content-sm-end flex-wrap" style="gap:10px">
            <button type="button" id="drpTriggerBtn" onclick="drpToggle()">
              <i class="fas fa-calendar-alt dci"></i>
              <span id="drpLabel"><?php
                $lbl=['today'=>'Today','7days'=>'Last 7 Days','30days'=>'Last 30 Days','3months'=>'Last 3 Months',
                      '12months'=>'Last 12 Months','mtd'=>'Month to Date','ytd'=>'Year to Date','alltime'=>'All Time'];
                echo $selectedRange==='custom'
                  ? date('M d, Y',strtotime($dateFrom)).' – '.date('M d, Y',strtotime($dateTo))
                  : ($lbl[$selectedRange]??'All Time');
              ?></span>
              <i class="fas fa-chevron-down dcv"></i>
            </button>
            <ol class="breadcrumb m-0">
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
                <h3 class="card-title"><i class="fas fa-receipt mr-2"></i>Latest Orders&nbsp;<span class="rt-live-dot" style="width:8px;height:8px;background:#22c55e;border-radius:50%;display:inline-block;vertical-align:middle;margin-left:4px;" title="Live"></span></h3>
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
                  <tbody id="latestOrdersTbody" data-rt-container="saleRecentOrders">
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
                      <tr data-order-id="<?= (int)$lo['id'] ?>">
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
</script><script>
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
      if(oth)td.classList.add('oth');
      if(sd(cd,today))td.classList.add('tod');
      if(!oth){
        if(sA&&sB){
          if(sd(cd,sA)&&sd(cd,sB))td.classList.add('ds','de');
          else if(sd(cd,sA))td.classList.add('ds');
          else if(sd(cd,sB))td.classList.add('de');
          else if(cd>sA&&cd<sB)td.classList.add('ir');
        }else if(sA&&!sB&&sd(cd,sA))td.classList.add('ds','de');
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
<script src="../dist/js/empress-realtime.js" data-scope="admin"></script>

<!-- ══ NEW ORDER REAL-TIME NOTIFICATION (SSE) ══════════════════════ -->
<div id="newOrderToast" style="
  display:none;position:fixed;bottom:24px;right:24px;z-index:99999;
  min-width:300px;max-width:360px;
  background:linear-gradient(135deg,#e91e8c 0%,#9c27b0 100%);
  color:#fff;border-radius:14px;
  box-shadow:0 8px 32px rgba(233,30,140,.45);
  padding:16px 20px;font-family:'Source Sans Pro',sans-serif;
  animation:toastSlideIn .35s cubic-bezier(.22,1,.36,1);
">
  <div style="display:flex;align-items:flex-start;gap:12px;">
    <div style="font-size:1.6rem;line-height:1;">🛎️</div>
    <div style="flex:1;">
      <div style="font-weight:700;font-size:.95rem;margin-bottom:2px;">New Order Received!</div>
      <div id="noToastMsg" style="font-size:.82rem;opacity:.9;">A new order just came in.</div>
    </div>
    <button onclick="document.getElementById('newOrderToast').style.display='none'"
      style="background:none;border:none;color:#fff;font-size:1.1rem;cursor:pointer;padding:0;line-height:1;opacity:.8;">✕</button>
  </div>
  <div style="margin-top:10px;display:flex;gap:8px;">
    <div id="noToastOrderBadge" style="background:rgba(255,255,255,.2);border-radius:20px;padding:3px 10px;font-size:.78rem;font-weight:600;"></div>
    <div id="noToastTimeBadge"  style="background:rgba(255,255,255,.15);border-radius:20px;padding:3px 10px;font-size:.78rem;"></div>
  </div>
</div>
<style>
@keyframes toastSlideIn{from{opacity:0;transform:translateY(30px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
</style>
<script>
/* ── empress-realtime-notify: new-order toast on all admin pages ── */
(function(){
  'use strict';
  var SSE_URL = 'index2.php?sse=1';
  var _lastId  = null;

  function showNewOrderToast(d) {
    var toast = document.getElementById('newOrderToast');
    var msg   = document.getElementById('noToastMsg');
    var badge = document.getElementById('noToastOrderBadge');
    var time  = document.getElementById('noToastTimeBadge');
    if (!toast) return;
    var lo = d.latestOrder || {};
    if (msg)   msg.textContent   = 'Table ' + (lo.table_no || '—') + ' — ₱' + parseFloat(lo.total_amt || 0).toLocaleString('en',{minimumFractionDigits:2});
    if (badge) badge.textContent = '#' + (lo.id || '');
    if (time)  time.textContent  = new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',hour12:true});
    toast.style.display   = 'block';
    toast.style.animation = 'none';
    void toast.offsetWidth;
    toast.style.animation = 'toastSlideIn .35s cubic-bezier(.22,1,.36,1)';
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.style.display='none'; }, 8000);
    /* beep */
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      var osc = ctx.createOscillator(); var g = ctx.createGain();
      osc.connect(g); g.connect(ctx.destination);
      osc.type = 'sine'; osc.frequency.value = 880;
      g.gain.setValueAtTime(0.3, ctx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
      osc.start(); osc.stop(ctx.currentTime + 0.4);
    } catch(e){}
  }

  function setDot(ok) {
    document.querySelectorAll('.rt-live-dot').forEach(function(el){
      el.style.background = ok ? '#22c55e' : '#ef4444';
      el.title = ok ? 'Live — connected' : 'Live — reconnecting…';
    });
  }


  function updateLatestOrders(orders){
    var tbody=document.getElementById('latestOrdersTbody');
    if(!tbody||!orders||!orders.length) return;
    var prevIds=Array.from(tbody.querySelectorAll('tr[data-order-id]')).map(function(r){ return r.getAttribute('data-order-id'); });
    var newIds=orders.map(function(o){ return String(o.id); });
    if(JSON.stringify(prevIds)===JSON.stringify(newIds)) return;
    var html=orders.map(function(o){
      var isNew=prevIds.indexOf(String(o.id))===-1;
      var discount='<span class="text-muted">—</span>';
      if(parseFloat(o.discount_amt||0)>0){
        var dt=o.discount_type==='senior'?'Senior 20%':(o.discount_type==='pwd'?'PWD 20%':'Discount');
        discount='<span class="badge badge-success" style="font-size:11px;">'+dt+'</span><br>'
                +'<span class="text-danger font-weight-bold">-₱'+parseFloat(o.discount_amt).toLocaleString('en',{minimumFractionDigits:2})+'</span>';
      }
      return '<tr data-order-id="'+o.id+'"'+(isNew?' data-rt-new="1"':'')+'>'
        +'<td><strong>#'+o.id+'</strong></td>'
        +'<td style="white-space:nowrap;">'+o.created_at+'</td>'
        +'<td>'+o.cashier_name+'</td>'
        +'<td>'+(o.items||'—')+'</td>'
        +'<td style="font-size:12px;"><span class="text-muted">—</span></td>'
        +'<td style="font-size:12px;"><span class="text-muted">—</span></td>'
        +'<td>'+discount+'</td>'
        +'<td><span class="text-success font-weight-bold">₱'+parseFloat(o.total_amt).toLocaleString('en',{minimumFractionDigits:2})+'</span></td>'
        +'</tr>';
    }).join('');
    tbody.innerHTML=html;
    tbody.querySelectorAll('tr[data-rt-new="1"]').forEach(function(tr){
      tr.style.transition='none';
      tr.style.backgroundColor='rgba(233,30,140,.18)';
      setTimeout(function(){ tr.style.transition='background-color 1.4s ease'; tr.style.backgroundColor=''; },80);
    });
  }

  function connect() {
    if (!window.EventSource) return;
    var es = new EventSource(SSE_URL);
    es.addEventListener('stats', function(e){
      try {
        var d = JSON.parse(e.data);
        setDot(true);
        updateLatestOrders(d.recentOrders || []);
        if (_lastId === null) { _lastId = d.latestOrderId; return; }
        if (d.latestOrderId > _lastId) {
          _lastId = d.latestOrderId;
          showNewOrderToast(d);
        }
      } catch(ex){}
    });
    es.onerror = function(){ setDot(false); };
    es.onopen  = function(){ setDot(true);  };
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', connect);
  } else {
    connect();
  }
})();
</script>
<!-- ══ END real-time notification ════════════════════════════════════ -->

</body>