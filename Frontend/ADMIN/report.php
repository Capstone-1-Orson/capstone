<?php
session_name('ADMIN_SESSION');
session_start();
if (!isset($_SESSION['user']) || $_SESSION['position'] !== 'admin') {
    header("Location:../../lockscreen.html");
    exit();
}


// ── SSE endpoint ─────────────────────────────────────────────────────────────
if (isset($_GET['sse'])) {
    require_once '../../Backend/conn.php';
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @ob_end_clean(); ob_implicit_flush(true);

    $VALID = "status NOT IN ('voided','refunded','partial_refund')";
    $data  = [];

    $r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");
    $data['ordersToday']   = ($r && $row=$r->fetch_assoc()) ? (int)$row['c'] : 0;

    $r = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");
    $data['dailyRevenue']  = ($r && $row=$r->fetch_assoc()) ? (float)$row['rev'] : 0.0;

    $r = $conn->query("SELECT MAX(id) AS mid FROM orders");
    $data['latestOrderId'] = ($r && $row=$r->fetch_assoc()) ? (int)$row['mid'] : 0;

    $r = $conn->query("SELECT id, table_no, total_amt, created_at FROM orders ORDER BY id DESC LIMIT 1");
    if ($r && $row=$r->fetch_assoc()) {
        $data['latestOrder'] = ['id'=>(int)$row['id'],'table_no'=>$row['table_no'],
                                'total_amt'=>(float)$row['total_amt'],'created_at'=>$row['created_at']];
    }

    // Recent orders for table (last 50)
    $recent = [];
    $hasOI  = $conn->query("SHOW TABLES LIKE 'order_items'")->num_rows > 0;
    if ($hasOI) {
        $r = $conn->query(
            "SELECT o.id AS order_id, o.created_at, o.table_no, o.status, o.total_amt,
                    COALESCE(o.discount_amt,0) AS discount_amt,
                    COALESCE(o.discount_type,'') AS discount_type,
                    COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                    GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items,
                    SUM(oi.qty) AS total_qty
             FROM orders o
             LEFT JOIN user u ON u.id = o.user_id
             JOIN order_items oi ON oi.order_id = o.id
             JOIN menu m ON m.id = oi.menu_id
             GROUP BY o.id ORDER BY o.created_at DESC LIMIT 50"
        );
        if ($r) while ($row=$r->fetch_assoc()) $recent[] = $row;
    }
    $data['recentOrders'] = $recent;

    $conn->close();
    echo "retry: 4000\n";
    echo "event: stats\n";
    echo "data: ".json_encode($data)."\n\n";
    if (ob_get_level()) ob_flush();
    flush();
    exit;
}
// ── End SSE endpoint ──────────────────────────────────────────────────────────

require_once '../../Backend/conn.php';

// ── Helper: check if a table exists ───────────────────────────
function tableExists($conn, $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

$hasOrderItems = tableExists($conn, 'order_items');

// ── Date Range Filter ─────────────────────────────────────────
$drpPresets = ['today'=>0,'7days'=>7,'30days'=>30,'3months'=>90,'12months'=>365,'mtd'=>-1,'ytd'=>-1,'alltime'=>-1,'custom'=>-1];
$selectedRange = (isset($_GET['range']) && array_key_exists($_GET['range'], $drpPresets)) ? $_GET['range'] : 'alltime';

$dateFrom = $dateTo = '';
if ($selectedRange === 'today') {
    $df = $dfo = "DATE(created_at)=CURDATE()";
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

// ── Summary Stats ─────────────────────────────────────────────
// NOTE: voided / refunded / partial_refund orders are excluded from all stats
$VALID = "status NOT IN ('voided','refunded','partial_refund')";

$totalOrders  = 0;
$totalRevenue = 0.0;
$res = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE $VALID AND $df");
if ($res && $row = $res->fetch_assoc()) {
    $totalOrders  = (int)$row['cnt'];
    $totalRevenue = (float)$row['rev'];
}

// Refund stats
$totalRefunds   = 0;
$totalRefundAmt = 0.0;
$rRef = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(refund_amt),0) AS amt FROM order_refunds WHERE $df");
if ($rRef && $rowRef = $rRef->fetch_assoc()) {
    $totalRefunds   = (int)$rowRef['cnt'];
    $totalRefundAmt = (float)$rowRef['amt'];
}

$totalTables = 0;
$res2 = $conn->query("SELECT COUNT(DISTINCT table_no) AS cnt FROM orders WHERE $VALID AND $df");
if ($res2 && $row2 = $res2->fetch_assoc()) {
    $totalTables = (int)$row2['cnt'];
}

$topItem = 'No orders yet';
if ($hasOrderItems) {
    $res3 = $conn->query(
        "SELECT m.name, SUM(oi.qty) AS total_qty
         FROM order_items oi
         JOIN menu m ON m.id = oi.menu_id
         JOIN orders o ON o.id = oi.order_id
         WHERE $VALID AND $dfO
         GROUP BY oi.menu_id ORDER BY total_qty DESC LIMIT 1"
    );
    if ($res3 && $row3 = $res3->fetch_assoc()) {
        $topItem = htmlspecialchars($row3['name']) . ' (' . (int)$row3['total_qty'] . ' sold)';
    }
}

// ── Sales Chart (last 7 days) ─────────────────────────────────
$chartLabels = [];
$chartData   = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('M d', strtotime("-$i days"));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at) = ? AND $VALID");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $chartData[] = (float)$stmt->get_result()->fetch_assoc()['rev'];
    $stmt->close();
}

// ── Inventory ─────────────────────────────────────────────────
$inventoryRows = [];
$res4 = $conn->query("SELECT name, unit, stock_qty, low_stock_threshold FROM ingredients ORDER BY name");
if ($res4) while ($row = $res4->fetch_assoc()) $inventoryRows[] = $row;

// ── Orders ────────────────────────────────────────────────────
$orderRows = [];
if ($hasOrderItems) {
    $res5 = $conn->query(
        "SELECT o.id AS order_id, o.created_at, o.table_no, o.status, o.total_amt,
                COALESCE(o.discount_amt, 0) AS discount_amt,
                COALESCE(o.discount_type, '') AS discount_type,
                COALESCE(CONCAT(u.firstname,' ',u.lastname), 'N/A') AS cashier_name,
                GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items,
                SUM(oi.qty) AS total_qty,
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
         WHERE $dfO
         GROUP BY o.id ORDER BY o.created_at DESC LIMIT 500"
    );
    if ($res5) while ($row = $res5->fetch_assoc()) $orderRows[] = $row;
} else {
    $res5 = $conn->query(
        "SELECT o.id AS order_id, o.created_at, o.table_no, o.status, o.total_amt,
                COALESCE(o.discount_amt,0) AS discount_amt, COALESCE(o.discount_type,'') AS discount_type,
                COALESCE(CONCAT(u.firstname,' ',u.lastname), 'N/A') AS cashier_name,
                '—' AS items, 0 AS total_qty, '' AS item_details
         FROM orders o LEFT JOIN user u ON u.id = o.user_id
         WHERE $dfO
         ORDER BY o.created_at DESC LIMIT 500"
    );
    if ($res5) while ($row = $res5->fetch_assoc()) $orderRows[] = $row;
}

// ── Category Sales (valid orders only) ───────────────────────
$catSales = [];
if ($hasOrderItems) {
    $res6 = $conn->query(
        "SELECT m.category, SUM(oi.qty * oi.unit_price) AS revenue
         FROM order_items oi
         JOIN menu m ON m.id = oi.menu_id
         JOIN orders o ON o.id = oi.order_id
         WHERE $VALID AND $dfO
         GROUP BY m.category ORDER BY revenue DESC"
    );
    if ($res6) while ($row = $res6->fetch_assoc()) $catSales[] = $row;
}

$conn->close();
$chartLabelsJson = json_encode($chartLabels);
$chartDataJson   = json_encode($chartData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reports | Empress POS</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../dist/css/empress-cafe-theme.css">
  <style>
    body,.main-header.navbar{transition:background-color .5s ease,color .5s ease;}
    #darkModeToggle{transition:box-shadow .3s ease;}
    
    #darkModeToggle.clicked{box-shadow:0 0 15px rgba(255,255,255,.8);}
    
    .summary-stat h5{font-size:1.6rem;font-weight:700;margin-bottom:2px;}
    .summary-stat span{font-size:.82rem;color:#aaa;}
    .top-item-val{font-size:1rem!important;}
  
    
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
    #drpBackdrop{display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.45);backdrop-filter:blur(6px);}
    #drpPopup{display:none;position:fixed;z-index:9001;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:16px;box-shadow:0 24px 64px rgba(0,0,0,.25);width:min(780px,96vw);overflow:hidden;font-family:'Source Sans Pro',sans-serif;}
    body.dark-mode #drpPopup{background:#252535;color:#e0e0e0;}
    .drp-inner{display:flex;}
    .drp-presets{width:158px;flex-shrink:0;border-right:1px solid #f0f0f0;padding:16px 0;}
    body.dark-mode .drp-presets{border-color:#3a3a4a;background:#252535;}
    .drp-pi{padding:9px 20px;cursor:pointer;font-size:.85rem;color:#444;border-left:3px solid transparent;transition:background .12s,color .12s;}
    body.dark-mode .drp-pi{color:#bbb;}
    .drp-pi:hover{background:#fdf0f8;color:#e91e8c;}
    body.dark-mode .drp-pi:hover{background:rgba(233,30,140,.15);color:#e91e8c;}
    .drp-pi.active{font-weight:700;color:#e91e8c;border-left-color:#e91e8c;background:#fdf0f8;}
    body.dark-mode .drp-pi.active{background:rgba(233,30,140,.18);color:#e91e8c;border-left-color:#e91e8c;}
    .drp-cals{flex:1;padding:18px 22px;display:flex;flex-direction:column;background:#fff;}
    body.dark-mode .drp-cals{background:#2e2e42;}
    .drp-months{display:flex;gap:24px;flex:1;}
    .drp-mon{flex:1;min-width:0;}
    .drp-mnav{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
    .drp-mnav strong{font-size:.9rem;color:#222;}
    body.dark-mode .drp-mnav strong{color:#eee;}
    .drp-nb{background:none;border:1px solid #ddd;border-radius:6px;width:26px;height:26px;cursor:pointer;color:#666;font-size:.95rem;display:flex;align-items:center;justify-content:center;transition:background .12s,border-color .12s,color .12s;}
    body.dark-mode .drp-nb{border-color:#555;color:#aaa;}
    .drp-nb:hover{background:#fdf0f8;border-color:#e91e8c;color:#e91e8c;}
    body.dark-mode .drp-nb:hover{background:rgba(233,30,140,.15);border-color:#e91e8c;color:#e91e8c;}
    .drp-cal{width:100%;border-collapse:collapse;}
    .drp-cal th{text-align:center;font-size:.72rem;font-weight:600;color:#aaa;padding:4px 0;letter-spacing:.03em;}
    body.dark-mode .drp-cal th{color:#666;}
    .drp-cal td{text-align:center;padding:0;width:14.28%;height:34px;font-size:.83rem;cursor:pointer;position:relative;color:#333;}
    body.dark-mode .drp-cal td{color:#ddd;}
    .drp-day{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;position:relative;z-index:1;transition:background .12s,color .12s;}
    .drp-cal td:not(.oth):hover .drp-day{background:#f5e8fb;color:#e91e8c;}
    body.dark-mode .drp-cal td:not(.oth):hover .drp-day{background:rgba(233,30,140,.22);color:#e91e8c;}
    .drp-cal td.oth .drp-day{color:#ccc;cursor:default;}
    body.dark-mode .drp-cal td.oth .drp-day{color:#444;}
    .drp-cal td.ds .drp-day,.drp-cal td.de .drp-day{background:#e91e8c!important;color:#fff!important;font-weight:700;}
    .drp-cal td.ir::before{content:'';position:absolute;inset:2px 0;background:rgba(233,30,140,.12);z-index:0;}
    body.dark-mode .drp-cal td.ir::before{background:rgba(233,30,140,.2);}
    .drp-cal td.ds::before{left:50%;}
    .drp-cal td.de::before{right:50%;}
    .drp-cal td.ds.de::before{display:none;}
    .drp-cal td.tod .drp-day::after{content:'';position:absolute;bottom:3px;left:50%;transform:translateX(-50%);width:4px;height:4px;border-radius:50%;background:#e91e8c;}
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
    @media(max-width:600px){.drp-months{flex-direction:column;}.drp-presets{width:100%;border-right:none;border-bottom:1px solid #3a3a4a;display:flex;flex-wrap:wrap;padding:8px;}.drp-pi{padding:5px 10px;border-left:none;}.drp-inner{flex-direction:column;}}

</style>
<style>
@keyframes rtPulse{0%{box-shadow:0 0 0 0 rgba(34,197,94,.55)}70%{box-shadow:0 0 0 7px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
@keyframes toastSlideIn{from{opacity:0;transform:translateY(30px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
</style>
</head>
<body class="hold-transition dark-mode sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">

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
        <a class="nav-link" data-widget="fullscreen" href="#" role="button"><i class="fas fa-expand-arrows-alt"></i></a>
      </li>
      <li class="nav-item">
        <a class="nav-link" id="darkModeToggle" href="#" role="button"><i class="fas fa-moon"></i></a>
      </li>
    </ul>
  </nav>

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
          <li class="nav-item"><a href="./index2.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Overview</p></a></li>
          <li class="nav-item"><a href="./menu-management.php" class="nav-link"><i class="nav-icon fas fa-utensils"></i><p>Menu Management</p></a></li>
          <li class="nav-item"><a href="./inventory.php" class="nav-link"><i class="nav-icon fas fa-boxes"></i><p>Inventory Tracking</p></a></li>
          <li class="nav-item"><a href="./suppliers.php" class="nav-link"><i class="nav-icon fas fa-truck"></i><p>Supplier Info</p></a></li>
          <li class="nav-item"><a href="./staff-list.php" class="nav-link"><i class="far fa-user nav-icon"></i><p>Staff List</p></a></li>
          <li class="nav-item"><a href="./sale_revenue.php" class="nav-link"><i class="nav-icon fas fa-chart-line"></i><p>Sales &amp; Revenue</p></a></li>
          <li class="nav-item"><a href="./report.php" class="nav-link active"><i class="nav-icon fas fa-file-alt"></i><p>Reports</p></a></li>
          <li class="nav-item"><a href="./void_refund.php"     class="nav-link"><i class="nav-icon fas fa-undo-alt"></i><p>Void &amp; Refund</p></a></li>
          <li class="nav-item"><a href="./settings.php" class="nav-link"><i class="nav-icon fas fa-cog"></i><p>Settings</p></a></li>
          <li class="nav-item mt-auto"><a href="../../Backend/logout.php" class="nav-link"><i class="nav-icon fas fa-sign-out-alt"></i><p>Log Out</p></a></li>
        </ul>
      </nav>
    </div>
  </aside>

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
          <div class="col-sm-6"><h1 class="m-0">Reports</h1></div>
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
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Reports</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">

        <?php if (!$hasOrderItems): ?>
        <!-- ── Setup Notice ──────────────────────────────── -->
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
          <i class="fas fa-exclamation-triangle mr-2"></i>
          <strong>Setup Required:</strong> The <code>order_items</code> table is missing.
          Please run <strong>create_order_items_table.sql</strong> in phpMyAdmin to enable
          full order detail reporting. Orders are still displayed using basic data.
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php endif; ?>

        <!-- ── Summary Stats ──────────────────────────────── -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-chart-pie mr-2"></i>Summary Report
              <small class="text-muted ml-2" style="font-size:12px;">Live from POS database · Voided &amp; refunded orders excluded</small>
            </h3>
          </div>
          <div class="card-body">
            <div class="row text-center">
              <div class="col-md-3 summary-stat">
                <h5 class="text-primary"><?= number_format($totalOrders) ?></h5>
                <span>Valid Orders</span>
              </div>
              <div class="col-md-3 summary-stat">
                <h5 class="text-success">&#8369;<?= number_format($totalRevenue, 2) ?></h5>
                <span>Net Revenue</span>
              </div>
              <div class="col-md-3 summary-stat">
                <h5 class="text-warning"><?= $totalTables ?></h5>
                <span>Tables Served</span>
              </div>
              <div class="col-md-3 summary-stat">
                <h5 class="text-danger top-item-val"><?= $topItem ?></h5>
                <span>Top Selling Item</span>
              </div>
            </div>
            <?php if ($totalRefunds > 0): ?>
            <hr>
            <div class="row text-center">
              <div class="col-md-4 summary-stat">
                <h5 class="text-danger"><?= number_format($totalRefunds) ?></h5>
                <span>Total Voids &amp; Refunds</span>
              </div>
              <div class="col-md-4 summary-stat">
                <h5 class="text-danger">&#8369;<?= number_format($totalRefundAmt, 2) ?></h5>
                <span>Total Refunded Amount</span>
              </div>
              <div class="col-md-4 summary-stat">
                <h5 class="text-info">&#8369;<?= number_format(max(0, $totalRevenue - $totalRefundAmt), 2) ?></h5>
                <span>Net After Refunds</span>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ── Sales Chart ───────────────────────────────── -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>Sales Overview &mdash; Last 7 Days</h3>
          </div>
          <div class="card-body">
            <canvas id="salesChart" height="80"></canvas>
          </div>
        </div>

        <!-- ── Category Revenue ──────────────────────────── -->
        <?php if (!empty($catSales)): ?>
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-tags mr-2"></i>Revenue by Category</h3>
          </div>
          <div class="card-body">
            <div class="row text-center">
              <?php foreach ($catSales as $cs): ?>
              <div class="col-md-3 summary-stat mb-3">
                <h5 class="text-info">&#8369;<?= number_format((float)$cs['revenue'], 2) ?></h5>
                <span><?= htmlspecialchars($cs['category']) ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- ── Inventory Report ──────────────────────────── -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-boxes mr-2"></i>Inventory Report</h3>
          </div>
          <div class="card-body">
            <table id="example1" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Ingredient</th>
                  <th>Unit</th>
                  <th>Stock Qty</th>
                  <th>Low Stock Threshold</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($inventoryRows as $inv):
                  $qty = (float)$inv['stock_qty'];
                  $thr = (float)$inv['low_stock_threshold'];
                  if ($qty <= 0)        $badge = '<span class="badge badge-danger">Out of Stock</span>';
                  elseif ($qty <= $thr) $badge = '<span class="badge badge-warning">Low Stock</span>';
                  else                  $badge = '<span class="badge badge-success">In Stock</span>';
                ?>
                <tr>
                  <td><?= htmlspecialchars($inv['name']) ?></td>
                  <td><?= htmlspecialchars($inv['unit']) ?></td>
                  <td><?= number_format($qty, 0) ?></td>
                  <td><?= number_format($thr, 0) ?></td>
                  <td><?= $badge ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ── Order Report ──────────────────────────────── -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-receipt mr-2"></i>Order Report&nbsp;<span class="rt-live-dot" style="width:8px;height:8px;background:#22c55e;border-radius:50%;display:inline-block;vertical-align:middle;margin-left:4px;" title="Live"></span></h3>
          </div>
          <div class="card-body">
            <table id="example2" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Order ID</th>
                  <th>Date &amp; Time</th>
                  <th>Number</th>
                  <th>Status</th>
                  <th>Cashier</th>
                  <th>Items Ordered</th>
                  <th>Add-ons</th>
                  <th>Removed Ingredients</th>
                  <th>Total Qty</th>
                  <th>Discount</th>
                  <th>Total (&#8369;)</th>
                </tr>
              </thead>
              <tbody id="orderReportTbody" data-rt-container="reportOrders">
                <?php foreach ($orderRows as $ord):
                  $st = $ord['status'] ?? 'Done';
                  if ($st === 'voided')          { $badge = '<span class="badge badge-danger">Voided</span>';          $rowClass = 'table-danger'; }
                  elseif ($st === 'refunded')     { $badge = '<span class="badge badge-info">Refunded</span>';          $rowClass = 'table-info'; }
                  elseif ($st === 'partial_refund'){ $badge = '<span class="badge badge-warning">Partial Refund</span>';$rowClass = 'table-warning'; }
                  else                            { $badge = '<span class="badge badge-success">'.htmlspecialchars($st).'</span>'; $rowClass = ''; }

                  // Parse item_details: "name|qty|addons|removed_json;;..."
                  $allAddons   = [];
                  $allRemoved  = [];
                  if (!empty($ord['item_details'])) {
                      foreach (explode(';;', $ord['item_details']) as $seg) {
                          $parts    = explode('|', $seg, 4);
                          $itemName = $parts[0] ?? '';
                          $addons   = trim($parts[2] ?? '');
                          $rawRem   = trim($parts[3] ?? '');
                          // Decode JSON array of names e.g. ["Cheese Powder","Onion"]
                          $removed  = '';
                          if ($rawRem !== '' && $rawRem !== '[]') {
                              $arr = json_decode($rawRem, true);
                              if (is_array($arr) && count($arr) > 0) {
                                  $removed = implode(', ', array_filter($arr));
                              } elseif (!is_array($arr) && $rawRem !== '') {
                                  $removed = $rawRem; // plain string fallback
                              }
                          }
                          if ($addons)  $allAddons[]  = htmlspecialchars($itemName) . ': ' . htmlspecialchars($addons);
                          if ($removed) $allRemoved[] = htmlspecialchars($itemName) . ': No ' . htmlspecialchars($removed);
                      }
                  }
                  $addonsCell  = !empty($allAddons)  ? implode('<br>', $allAddons)  : '<span class="text-muted">—</span>';
                  $removedCell = !empty($allRemoved) ? implode('<br>', $allRemoved) : '<span class="text-muted">—</span>';
                ?>
                <tr class="<?= $rowClass ?>" data-order-id="<?= (int)$ord['order_id'] ?>">
                  <td><strong>#<?= (int)$ord['order_id'] ?></strong></td>
                  <td><?= htmlspecialchars($ord['created_at']) ?></td>
                  <td><?= htmlspecialchars($ord['table_no']) ?></td>
                  <td><?= $badge ?></td>
                  <td><?= htmlspecialchars($ord['cashier_name']) ?></td>
                  <td><?= htmlspecialchars($ord['items']) ?></td>
                  <td style="font-size:12px;"><?= $addonsCell ?></td>
                  <td style="font-size:12px;"><?= $removedCell ?></td>
                  <td><?= (int)$ord['total_qty'] ?></td>
                  <td>
                    <?php if ((float)$ord['discount_amt'] > 0): ?>
                      <span class="badge badge-success" style="font-size:11px;">
                        <?= $ord['discount_type'] === 'senior' ? 'Senior 20%' : ($ord['discount_type'] === 'pwd' ? 'PWD 20%' : 'Discount') ?>
                      </span><br>
                      <span class="text-danger font-weight-bold">-&#8369;<?= number_format((float)$ord['discount_amt'], 2) ?></span>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td><?php if (in_array($st, ['voided','refunded','partial_refund'])): ?>
                    <s class="text-muted">&#8369;<?= number_format((float)$ord['total_amt'], 2) ?></s>
                  <?php else: ?>
                    &#8369;<?= number_format((float)$ord['total_amt'], 2) ?>
                  <?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </section>
  </div><!-- /.content-wrapper -->
</div><!-- ./wrapper -->

<!-- Scripts -->
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
// ── DataTables ────────────────────────────────────────────────
$(function () {
  $("#example1").DataTable({
    responsive: true, lengthChange: false, autoWidth: false,
    order: [[2, "asc"]],
    buttons: [
      { extend:'copy',  title:'Inventory Report' },
      { extend:'csv',   title:'Inventory_Report' },
      { extend:'excel', title:'Inventory_Report' },
      { extend:'pdf',   title:'Inventory_Report' },
      { extend:'print', title:'Inventory Report' },
      'colvis'
    ]
  }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');

  $("#example2").DataTable({
    responsive: true, lengthChange: false, autoWidth: false,
    order: [[0, "desc"]],
    buttons: [
      { extend:'copy',  title:'Order Report' },
      { extend:'csv',   title:'Order_Report' },
      { extend:'excel', title:'Order_Report' },
      { extend:'pdf',   title:'Order_Report' },
      { extend:'print', title:'Order Report' },
      'colvis'
    ]
  }).buttons().container().appendTo('#example2_wrapper .col-md-6:eq(0)');
});

// ── Sales Chart ───────────────────────────────────────────────
$(function () {
  var ctx = document.getElementById('salesChart').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= $chartLabelsJson ?>,
      datasets: [{
        label: 'Revenue',
        data: <?= $chartDataJson ?>,
        backgroundColor: 'rgba(233,30,140,0.55)',
        borderColor: 'rgba(233,30,140,1)',
        borderWidth: 2,
        hoverBackgroundColor: 'rgba(233,30,140,0.8)'
      }]
    },
    options: {
      responsive: true,
      legend: { display: false },
      tooltips: {
        callbacks: {
          label: function(item) {
            return '₱' + parseFloat(item.yLabel).toLocaleString('en', {minimumFractionDigits:2});
          }
        }
      },
      scales: {
        yAxes: [{
          ticks: {
            beginAtZero: true,
            callback: function(val) { return '₱' + val.toLocaleString(); }
          }
        }]
      }
    }
  });
});

// ── Dark Mode ─────────────────────────────────────────────────
$(function () {
  var darkMode = localStorage.getItem('darkMode');
  if (darkMode === 'true') {
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
    setTimeout(function() { $('#darkModeToggle').removeClass('clicked'); }, 300);
  });
});
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


<!-- ══ NEW ORDER REAL-TIME NOTIFICATION ══════════════════════════════ -->
<div id="newOrderToast" style="
  display:none;position:fixed;bottom:24px;right:24px;z-index:99999;
  min-width:300px;max-width:360px;
  background:linear-gradient(135deg,#e91e8c 0%,#9c27b0 100%);
  color:#fff;border-radius:14px;
  box-shadow:0 8px 32px rgba(233,30,140,.45);
  padding:16px 20px;font-family:'Source Sans Pro',sans-serif;">
  <div style="display:flex;align-items:flex-start;gap:12px;">
    <div style="font-size:1.6rem;line-height:1;">🛎️</div>
    <div style="flex:1;">
      <div style="font-weight:700;font-size:.95rem;margin-bottom:2px;">New Order Received!</div>
      <div id="noToastMsg" style="font-size:.82rem;opacity:.9;">A new order just came in.</div>
    </div>
    <button onclick="document.getElementById('newOrderToast').style.display='none'"
      style="background:none;border:none;color:#fff;font-size:1.1rem;cursor:pointer;padding:0;opacity:.8;">✕</button>
  </div>
  <div style="margin-top:10px;display:flex;gap:8px;">
    <div id="noToastBadge" style="background:rgba(255,255,255,.2);border-radius:20px;padding:3px 10px;font-size:.78rem;font-weight:600;"></div>
    <div id="noToastTime"  style="background:rgba(255,255,255,.15);border-radius:20px;padding:3px 10px;font-size:.78rem;"></div>
  </div>
</div>

<script>
/* ── empress-realtime: report.php ── */
(function(){
  'use strict';
  var SSE_URL = 'report.php?sse=1';
  var _lastId = null;

  function peso(v){ return '₱'+parseFloat(v).toLocaleString('en',{minimumFractionDigits:2,maximumFractionDigits:2}); }

  function flash(el, colour){
    el.style.transition='none';
    el.style.backgroundColor=colour||'rgba(233,30,140,.18)';
    setTimeout(function(){ el.style.transition='background-color 1.4s ease'; el.style.backgroundColor=''; },80);
  }

  function setDot(ok){
    document.querySelectorAll('.rt-live-dot').forEach(function(d){
      d.style.background = ok ? '#22c55e' : '#ef4444';
      d.title = ok ? 'Live — connected' : 'Live — reconnecting…';
    });
  }

  function showToast(d){
    var toast=document.getElementById('newOrderToast');
    var msg=document.getElementById('noToastMsg');
    var badge=document.getElementById('noToastBadge');
    var time=document.getElementById('noToastTime');
    if(!toast) return;
    var lo=d.latestOrder||{};
    if(msg)   msg.textContent  ='Table '+(lo.table_no||'—')+' — '+peso(lo.total_amt||0);
    if(badge) badge.textContent='#'+(lo.id||'');
    if(time)  time.textContent =new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',hour12:true});
    toast.style.display='block';
    toast.style.animation='none';
    void toast.offsetWidth;
    toast.style.animation='toastSlideIn .35s cubic-bezier(.22,1,.36,1)';
    clearTimeout(toast._t);
    toast._t=setTimeout(function(){ toast.style.display='none'; },8000);
    try{
      var ctx=new(window.AudioContext||window.webkitAudioContext)();
      var osc=ctx.createOscillator(); var g=ctx.createGain();
      osc.connect(g); g.connect(ctx.destination);
      osc.type='sine'; osc.frequency.value=880;
      g.gain.setValueAtTime(0.3,ctx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+0.4);
      osc.start(); osc.stop(ctx.currentTime+0.4);
    }catch(e){}
  }

  function updateOrderTable(orders){
    var tbody=document.getElementById('orderReportTbody');
    if(!tbody||!orders||!orders.length) return;
    var prevIds=Array.from(tbody.querySelectorAll('tr[data-order-id]')).map(function(r){ return r.getAttribute('data-order-id'); });
    var newIds =orders.map(function(o){ return String(o.order_id); });
    if(JSON.stringify(prevIds)===JSON.stringify(newIds)) return;

    var html=orders.map(function(o){
      var st=o.status||'done';
      var badge='', rowCls='';
      if(st==='voided')         { badge='<span class="badge badge-danger">Voided</span>';          rowCls='table-danger'; }
      else if(st==='refunded')  { badge='<span class="badge badge-info">Refunded</span>';           rowCls='table-info';   }
      else if(st==='partial_refund'){ badge='<span class="badge badge-warning">Partial Refund</span>'; rowCls='table-warning';}
      else                      { badge='<span class="badge badge-success">Done</span>';            rowCls=''; }
      var isNew=prevIds.indexOf(String(o.order_id))===-1;
      var discount='<span class="text-muted">—</span>';
      if(parseFloat(o.discount_amt)>0){
        var dtype=o.discount_type==='senior'?'Senior 20%':(o.discount_type==='pwd'?'PWD 20%':'Discount');
        discount='<span class="badge badge-success" style="font-size:11px;">'+dtype+'</span><br>'
                +'<span class="text-danger font-weight-bold">-₱'+parseFloat(o.discount_amt).toLocaleString('en',{minimumFractionDigits:2})+'</span>';
      }
      var totalCell=rowCls?('<s class="text-muted">₱'+parseFloat(o.total_amt).toFixed(2)+'</s>'):'₱'+parseFloat(o.total_amt).toLocaleString('en',{minimumFractionDigits:2});
      return '<tr class="'+rowCls+'" data-order-id="'+o.order_id+'"'+(isNew?' data-rt-new="1"':'')+'>'
        +'<td><strong>#'+o.order_id+'</strong></td>'
        +'<td>'+o.created_at+'</td>'
        +'<td>'+o.table_no+'</td>'
        +'<td>'+badge+'</td>'
        +'<td>'+o.cashier_name+'</td>'
        +'<td>'+(o.items||'—')+'</td>'
        +'<td><span class="text-muted">—</span></td>'
        +'<td><span class="text-muted">—</span></td>'
        +'<td>'+(o.total_qty||0)+'</td>'
        +'<td>'+discount+'</td>'
        +'<td>'+totalCell+'</td>'
        +'</tr>';
    }).join('');
    tbody.innerHTML=html;
    tbody.querySelectorAll('tr[data-rt-new="1"]').forEach(function(tr){ flash(tr,'rgba(233,30,140,.18)'); });
  }

  function connect(){
    if(!window.EventSource) return;
    var es=new EventSource(SSE_URL);
    es.addEventListener('stats',function(e){
      try{
        var d=JSON.parse(e.data);
        setDot(true);
        if(_lastId===null){ _lastId=d.latestOrderId; updateOrderTable(d.recentOrders); return; }
        if(d.latestOrderId>_lastId){ _lastId=d.latestOrderId; showToast(d); }
        updateOrderTable(d.recentOrders);
      }catch(ex){}
    });
    es.onerror=function(){ setDot(false); };
    es.onopen =function(){ setDot(true);  };
  }

  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded',connect);
  } else { connect(); }
})();
</script>
<!-- ══ END real-time ═══════════════════════════════════════════════════ -->

</body>
</html>