<?php
session_name('ADMIN_SESSION');
session_start();
if (!isset($_SESSION['user']) || $_SESSION['position'] !== 'admin') {
    if (isset($_GET['rt'])) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    header("Location: ../../lockscreen.html");
    exit();
}

require_once '../../Backend/conn.php';

// ── Helper ─────────────────────────────────────────────────────
function tableExists($conn, $table) {
    $r = $conn->query("SHOW TABLES LIKE '$table'");
    return $r && $r->num_rows > 0;
}
$hasOrderItems   = tableExists($conn, 'order_items');
$hasOrderRefunds = tableExists($conn, 'order_refunds');

// ══ REAL-TIME JSON API (?rt=1) ═════════════════════════════════
if (isset($_GET['rt'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $vc = $rc = $pc = 0; $tv = $tr = 0.0;

    $r = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS amt FROM orders WHERE status='voided'");
    if ($r && $row = $r->fetch_assoc()) { $vc = (int)$row['cnt']; $tv = (float)$row['amt']; }

    $r = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE status IN ('refunded','partial_refund')");
    if ($r && $row = $r->fetch_assoc()) $rc = (int)$row['cnt'];

    if ($hasOrderRefunds) {
        $r = $conn->query("SELECT COALESCE(SUM(refund_amt),0) AS amt FROM order_refunds");
        if ($r && $row = $r->fetch_assoc()) $tr = (float)$row['amt'];
    }

    $r = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE status NOT IN ('voided','refunded','partial_refund')");
    if ($r && $row = $r->fetch_assoc()) $pc = (int)$row['cnt'];

    $latestId = 0;
    $r = $conn->query("SELECT MAX(id) AS mid FROM orders WHERE status NOT IN ('voided','refunded','partial_refund')");
    if ($r && $row = $r->fetch_assoc()) $latestId = (int)($row['mid'] ?? 0);

    // New active orders since client's last known id
    $newOrders = [];
    $since = isset($_GET['since']) ? (int)$_GET['since'] : 0;
    if ($since > 0) {
        if ($hasOrderItems) {
            $stmt = $conn->prepare(
                "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                        COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                        GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items
                 FROM orders o
                 LEFT JOIN user u ON u.id = o.user_id
                 JOIN order_items oi ON oi.order_id = o.id
                 JOIN menu m ON m.id = oi.menu_id
                 WHERE o.status NOT IN ('voided','refunded','partial_refund') AND o.id > ?
                 GROUP BY o.id ORDER BY o.id DESC LIMIT 20"
            );
            if ($stmt) { $stmt->bind_param('i', $since); $stmt->execute(); $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $newOrders[] = $row; $stmt->close(); }
        } else {
            $stmt = $conn->prepare(
                "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                        COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name, '—' AS items
                 FROM orders o LEFT JOIN user u ON u.id = o.user_id
                 WHERE o.status NOT IN ('voided','refunded','partial_refund') AND o.id > ?
                 ORDER BY o.id DESC LIMIT 20"
            );
            if ($stmt) { $stmt->bind_param('i', $since); $stmt->execute(); $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $newOrders[] = $row; $stmt->close(); }
        }
    }

    // Orders recently voided/refunded (to remove from active table)
    $removedIds = [];
    $r = $conn->query("SELECT id FROM orders WHERE status IN ('voided','refunded','partial_refund') AND updated_at >= NOW() - INTERVAL 30 SECOND");
    if ($r) while ($row = $r->fetch_assoc()) $removedIds[] = (int)$row['id'];

    $conn->close();
    echo json_encode(['voidedCount'=>$vc,'refundedCount'=>$rc,'pendingCount'=>$pc,'totalVoided'=>$tv,'totalRefunded'=>$tr,'latestOrderId'=>$latestId,'newOrders'=>$newOrders,'removedIds'=>$removedIds]);
    exit();
}
// ══ END REAL-TIME API ══════════════════════════════════════════

// ── Summary stats ──────────────────────────────────────────────
$totalVoided = 0;
$totalRefunded = 0;
$voidedCount = 0;
$refundedCount = 0;

$r = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS amt FROM orders WHERE status='voided'");
if ($r && $row = $r->fetch_assoc()) { $voidedCount = (int)$row['cnt']; $totalVoided = (float)$row['amt']; }

$r = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE status IN ('refunded','partial_refund')");
if ($r && $row = $r->fetch_assoc()) $refundedCount = (int)$row['cnt'];

if ($hasOrderRefunds) {
    $r = $conn->query("SELECT COALESCE(SUM(refund_amt),0) AS amt FROM order_refunds");
    if ($r && $row = $r->fetch_assoc()) $totalRefunded = (float)$row['amt'];
}

// ── Voided orders ──────────────────────────────────────────────
$voidedOrders = [];
if ($hasOrderItems) {
    $r = $conn->query(
        "SELECT o.id, o.table_no, o.total_amt, o.created_at,
                COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items,
                SUM(oi.qty) AS total_qty
         FROM orders o
         LEFT JOIN user u ON u.id = o.user_id
         JOIN order_items oi ON oi.order_id = o.id
         JOIN menu m ON m.id = oi.menu_id
         WHERE o.status = 'voided'
         GROUP BY o.id ORDER BY o.created_at DESC"
    );
    if ($r) while ($row = $r->fetch_assoc()) $voidedOrders[] = $row;
} else {
    $r = $conn->query(
        "SELECT o.id, o.table_no, o.total_amt, o.created_at,
                COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                '—' AS items, 0 AS total_qty
         FROM orders o LEFT JOIN user u ON u.id = o.user_id
         WHERE o.status = 'voided' ORDER BY o.created_at DESC"
    );
    if ($r) while ($row = $r->fetch_assoc()) $voidedOrders[] = $row;
}

// ── Refunded / partial_refund orders ──────────────────────────
$refundedOrders = [];
if ($hasOrderItems && $hasOrderRefunds) {
    $r = $conn->query(
        "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                GROUP_CONCAT(DISTINCT m.name ORDER BY m.name SEPARATOR ', ') AS items,
                SUM(oi.qty) AS total_qty,
                (SELECT SUM(r2.refund_amt) FROM order_refunds r2 WHERE r2.order_id = o.id) AS refund_total,
                (SELECT r2.reason FROM order_refunds r2 WHERE r2.order_id = o.id ORDER BY r2.id DESC LIMIT 1) AS refund_reason,
                (SELECT r2.created_by FROM order_refunds r2 WHERE r2.order_id = o.id ORDER BY r2.id DESC LIMIT 1) AS processed_by,
                (SELECT r2.created_at FROM order_refunds r2 WHERE r2.order_id = o.id ORDER BY r2.id DESC LIMIT 1) AS refund_at
         FROM orders o
         LEFT JOIN user u ON u.id = o.user_id
         JOIN order_items oi ON oi.order_id = o.id
         JOIN menu m ON m.id = oi.menu_id
         WHERE o.status IN ('refunded','partial_refund')
         GROUP BY o.id ORDER BY o.created_at DESC"
    );
    if ($r) while ($row = $r->fetch_assoc()) $refundedOrders[] = $row;
} else {
    $r = $conn->query(
        "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                '—' AS items, 0 AS total_qty, 0 AS refund_total, '' AS refund_reason, '' AS processed_by, o.created_at AS refund_at
         FROM orders o LEFT JOIN user u ON u.id = o.user_id
         WHERE o.status IN ('refunded','partial_refund') ORDER BY o.created_at DESC"
    );
    if ($r) while ($row = $r->fetch_assoc()) $refundedOrders[] = $row;
}

// ── All orders eligible for void/refund (not yet done) ────────
$pendingOrders = [];
if ($hasOrderItems) {
    $r = $conn->query(
        "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items,
                SUM(oi.qty) AS total_qty,
                GROUP_CONCAT(CONCAT(oi.id,'|',m.id,'|',m.name,'|',oi.qty,'|',oi.unit_price) ORDER BY oi.id SEPARATOR ';;') AS item_details_raw
         FROM orders o
         LEFT JOIN user u ON u.id = o.user_id
         JOIN order_items oi ON oi.order_id = o.id
         JOIN menu m ON m.id = oi.menu_id
         WHERE o.status NOT IN ('voided','refunded','partial_refund')
         GROUP BY o.id ORDER BY o.created_at DESC LIMIT 100"
    );
    if ($r) while ($row = $r->fetch_assoc()) $pendingOrders[] = $row;
} else {
    $r = $conn->query(
        "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                '—' AS items, 0 AS total_qty, '' AS item_details_raw
         FROM orders o LEFT JOIN user u ON u.id = o.user_id
         WHERE o.status NOT IN ('voided','refunded','partial_refund')
         ORDER BY o.created_at DESC LIMIT 100"
    );
    if ($r) while ($row = $r->fetch_assoc()) $pendingOrders[] = $row;
}

$conn->close();

$pendingOrdersJson = json_encode($pendingOrders);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OPERLYTICS | Void &amp; Refund</title>
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
    #darkModeToggle.clicked    { box-shadow: 0 0 15px rgba(255,255,255,.8); }

    /* Kill icon animations */
    i.fas, i.far, i.fab, i.fal, [class*="fa-"] {
      animation: none !important; transform: none !important;
    }

    /* Mobile table scroll */
    .table-responsive { overflow-x: auto !important; -webkit-overflow-scrolling: touch; }
    .dataTables_wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .table td, .table th { white-space: nowrap !important; overflow: hidden; text-overflow: ellipsis; max-width: 260px; }
    ::-webkit-scrollbar { height: 6px; width: 6px; }
    ::-webkit-scrollbar-track  { background: rgba(0,0,0,0.06); border-radius: 3px; }
    ::-webkit-scrollbar-thumb  { background: rgba(233,30,140,0.45); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: rgba(233,30,140,0.75); }
    .content-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }

    /* Light mode table fix */
    body:not(.dark-mode) .table tbody tr:hover { background-color: rgba(233,30,140,0.08) !important; }
    body:not(.dark-mode) .table tbody tr:hover td { color: #212529 !important; }

    /* Stat cards */
    .vr-stat { border-radius: 10px; padding: 18px 20px; display: flex; align-items: center; gap: 16px; }
    .vr-stat .icon-wrap { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
    .vr-stat .stat-num  { font-size: 1.7rem; font-weight: 700; line-height: 1; }
    .vr-stat .stat-lbl  { font-size: .8rem; opacity: .75; margin-top: 3px; }

    /* Modal overlay */
    .vr-modal-overlay {
      display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6);
      z-index: 9999; align-items: center; justify-content: center; padding: 20px;
    }
    .vr-modal-overlay.open { display: flex; }
    .vr-modal {
      background: #fff; border-radius: 14px; width: 100%; max-width: 540px;
      box-shadow: 0 20px 60px rgba(0,0,0,.25); overflow: hidden;
      animation: vrSlideIn .22s ease;
    }
    body.dark-mode .vr-modal { background: #1e2130; color: #e9ecef; }
    @keyframes vrSlideIn { from { opacity:0; transform: translateY(20px); } to { opacity:1; transform: translateY(0); } }
    .vr-modal-header { padding: 20px 24px 16px; border-bottom: 1px solid rgba(0,0,0,.1); display: flex; align-items: center; justify-content: space-between; }
    body.dark-mode .vr-modal-header { border-color: rgba(255,255,255,.1); }
    .vr-modal-header h5 { margin: 0; font-size: 1.1rem; font-weight: 700; }
    .vr-modal-body { padding: 20px 24px; }
    .vr-modal-footer { padding: 14px 24px 20px; display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid rgba(0,0,0,.1); }
    body.dark-mode .vr-modal-footer { border-color: rgba(255,255,255,.1); }

    /* Order meta strip */
    .order-meta-strip { background: rgba(233,30,140,.07); border: 1px solid rgba(233,30,140,.18); border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; display: flex; gap: 20px; flex-wrap: wrap; }
    .order-meta-strip .om-item { font-size: .82rem; }
    .order-meta-strip .om-item strong { display: block; font-size: 1rem; }

    /* Items checklist for partial refund */
    .refund-item-row { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; border: 1px solid rgba(0,0,0,.1); margin-bottom: 6px; cursor: pointer; transition: background .15s; }
    body.dark-mode .refund-item-row { border-color: rgba(255,255,255,.1); }
    .refund-item-row:hover { background: rgba(233,30,140,.06); }
    .refund-item-row.selected { background: rgba(233,30,140,.1); border-color: rgba(233,30,140,.35); }
    .refund-item-row input[type=checkbox] { accent-color: #e91e8c; width: 16px; height: 16px; flex-shrink: 0; cursor: pointer; }
    .refund-item-name  { flex: 1; font-weight: 600; font-size: .9rem; }
    .refund-item-price { font-size: .85rem; color: #e91e8c; font-weight: 700; }
    .refund-qty-ctrl   { display: flex; align-items: center; gap: 6px; }
    .refund-qty-btn    { width: 26px; height: 26px; border-radius: 6px; background: rgba(0,0,0,.08); border: none; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; }
    body.dark-mode .refund-qty-btn { background: rgba(255,255,255,.1); color: #e9ecef; }
    .refund-qty-val    { font-weight: 700; min-width: 20px; text-align: center; }

    /* Reason input */
    .reason-label { font-size: .82rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #888; margin-bottom: 6px; }
    .reason-input { width: 100%; padding: 9px 12px; border: 1px solid #ced4da; border-radius: 8px; font-size: .9rem; }
    body.dark-mode .reason-input { background: #2a2d3e; color: #e9ecef; border-color: rgba(255,255,255,.15); }
    .reason-input:focus { outline: none; border-color: #e91e8c; box-shadow: 0 0 0 3px rgba(233,30,140,.15); }

    /* Refund total display */
    .refund-amt-display { background: rgba(239,68,68,.07); border: 1px solid rgba(239,68,68,.2); border-radius: 10px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; margin-top: 14px; }
    .refund-amt-display.void-mode { background: rgba(239,68,68,.1); border-color: rgba(239,68,68,.3); }
    .refund-amt-display .ramt-lbl { font-size: .82rem; color: #888; }
    .refund-amt-display .ramt-val { font-size: 1.4rem; font-weight: 700; color: #e74c3c; }

    /* Status badges */
    .badge-voided   { background: rgba(239,68,68,.15); color: #e74c3c; padding: 3px 9px; border-radius: 20px; font-size: .75rem; font-weight: 700; }
    .badge-refunded { background: rgba(59,130,246,.15); color: #3b82f6; padding: 3px 9px; border-radius: 20px; font-size: .75rem; font-weight: 700; }
    .badge-partial  { background: rgba(245,158,11,.15); color: #f59e0b; padding: 3px 9px; border-radius: 20px; font-size: .75rem; font-weight: 700; }
    .badge-done     { background: rgba(34,197,94,.15); color: #22c55e; padding: 3px 9px; border-radius: 20px; font-size: .75rem; font-weight: 700; }

    /* Action buttons in table */
    .btn-void   { background: rgba(239,68,68,.12); color: #e74c3c; border: 1px solid rgba(239,68,68,.3); padding: 4px 10px; border-radius: 6px; font-size: .78rem; font-weight: 700; cursor: pointer; transition: background .15s; }
    .btn-void:hover   { background: rgba(239,68,68,.25); }
    .btn-refund { background: rgba(59,130,246,.12); color: #3b82f6; border: 1px solid rgba(59,130,246,.3); padding: 4px 10px; border-radius: 6px; font-size: .78rem; font-weight: 700; cursor: pointer; transition: background .15s; }
    .btn-refund:hover { background: rgba(59,130,246,.25); }

    /* Nav tabs */
    .vr-nav-tabs .nav-link { font-weight: 600; }
    .vr-nav-tabs .nav-link.active { color: #e91e8c; border-bottom-color: #e91e8c; }

    @media (max-width: 576px) {
      .vr-stat .stat-num { font-size: 1.3rem; }
      .vr-modal { max-width: 98vw; }
    }
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
        <div class="image"><img src="../dist/img/Empress' Cafe Boracay.jpg" class="img-circle elevation-2" alt="User Image"></div>
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
          <li class="nav-item"><a href="./sale_revenue.php"    class="nav-link"><i class="nav-icon fas fa-chart-line"></i><p>Sales &amp; Revenue</p></a></li>
          <li class="nav-item"><a href="./report.php"          class="nav-link"><i class="nav-icon fas fa-file-alt"></i><p>Reports</p></a></li>
          <li class="nav-item"><a href="./void_refund.php"     class="nav-link active"><i class="nav-icon fas fa-undo-alt"></i><p>Void &amp; Refund</p></a></li>
          <li class="nav-item"><a href="./settings.php"        class="nav-link"><i class="nav-icon fas fa-cog"></i><p>Settings</p></a></li>
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
          <div class="col-sm-6">
            <h1 class="m-0"><i class="fas fa-undo-alt mr-2" style="color:#e91e8c"></i>Void &amp; Refund</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="index2.php">Home</a></li>
              <li class="breadcrumb-item active">Void &amp; Refund</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">

        <!-- ── Stat Cards ──────────────────────────────────── -->
        <div class="row mb-4">
          <div class="col-md-3 col-sm-6 col-12 mb-3">
            <div class="card h-100 m-0">
              <div class="card-body vr-stat">
                <div class="icon-wrap" style="background:rgba(239,68,68,.12);color:#e74c3c;">
                  <i class="fas fa-ban"></i>
                </div>
                <div>
                  <div class="stat-num" style="color:#e74c3c;"><?= $voidedCount ?></div>
                  <div class="stat-lbl">Total Voided Orders</div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 col-12 mb-3">
            <div class="card h-100 m-0">
              <div class="card-body vr-stat">
                <div class="icon-wrap" style="background:rgba(239,68,68,.12);color:#e74c3c;">
                  <i class="fas fa-money-bill-wave"></i>
                </div>
                <div>
                  <div class="stat-num" style="color:#e74c3c;">&#8369;<?= number_format($totalVoided, 2) ?></div>
                  <div class="stat-lbl">Total Amount Voided</div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 col-12 mb-3">
            <div class="card h-100 m-0">
              <div class="card-body vr-stat">
                <div class="icon-wrap" style="background:rgba(59,130,246,.12);color:#3b82f6;">
                  <i class="fas fa-rotate-left"></i>
                </div>
                <div>
                  <div class="stat-num" style="color:#3b82f6;"><?= $refundedCount ?></div>
                  <div class="stat-lbl">Total Refunded Orders</div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 col-12 mb-3">
            <div class="card h-100 m-0">
              <div class="card-body vr-stat">
                <div class="icon-wrap" style="background:rgba(59,130,246,.12);color:#3b82f6;">
                  <i class="fas fa-coins"></i>
                </div>
                <div>
                  <div class="stat-num" style="color:#3b82f6;">&#8369;<?= number_format($totalRefunded, 2) ?></div>
                  <div class="stat-lbl">Total Refund Amount</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Nav Tabs ────────────────────────────────────── -->
        <div class="card">
          <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs vr-nav-tabs" id="vrTabs" role="tablist" style="padding: 0 16px;">
              <li class="nav-item">
                <a class="nav-link active" id="tab-active" data-toggle="tab" href="#pane-active" role="tab">
                  <i class="fas fa-clock mr-1"></i> Active Orders
                  <span class="badge badge-secondary ml-1"><?= count($pendingOrders) ?></span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="tab-voided" data-toggle="tab" href="#pane-voided" role="tab">
                  <i class="fas fa-ban mr-1" style="color:#e74c3c"></i> Voided
                  <span class="badge badge-danger ml-1"><?= $voidedCount ?></span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="tab-refunded" data-toggle="tab" href="#pane-refunded" role="tab">
                  <i class="fas fa-rotate-left mr-1" style="color:#3b82f6"></i> Refunded
                  <span class="badge badge-primary ml-1"><?= $refundedCount ?></span>
                </a>
              </li>
            </ul>
          </div>
          <div class="card-body p-0">
            <div class="tab-content">

              <!-- ── Active Orders Tab ───────────────────────── -->
              <div class="tab-pane fade show active" id="pane-active" role="tabpanel">
                <div class="table-responsive">
                  <table id="tbl-active" class="table table-bordered table-striped m-0">
                    <thead>
                      <tr>
                        <th>Order #</th>
                        <th>Date &amp; Time</th>
                        <th>Bill No.</th>
                        <th>Cashier</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Total (&#8369;)</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($pendingOrders)): ?>
                        <?php foreach ($pendingOrders as $o): ?>
                        <tr>
                          <td><strong>#<?= (int)$o['id'] ?></strong></td>
                          <td><small class="text-muted"><?= date('M d, Y g:i A', strtotime($o['created_at'])) ?></small></td>
                          <td><span class="badge badge-secondary">#<?= htmlspecialchars($o['table_no']) ?></span></td>
                          <td><?= htmlspecialchars($o['cashier_name'] ?? '—') ?></td>
                          <td title="<?= htmlspecialchars($o['items']) ?>"><?= htmlspecialchars(mb_strimwidth($o['items'], 0, 45, '…')) ?></td>
                          <td>
                            <span class="badge-done"><i class="fas fa-check mr-1"></i><?= ucfirst($o['status']) ?></span>
                          </td>
                          <td><strong>&#8369;<?= number_format((float)$o['total_amt'], 2) ?></strong></td>
                          <td>
                            <div class="d-flex gap-1" style="gap:6px;">
                              <button class="btn-void"
                                onclick="openVoidModal(<?= (int)$o['id'] ?>,'<?= htmlspecialchars($o['table_no']) ?>',<?= (float)$o['total_amt'] ?>,'<?= htmlspecialchars(addslashes($o['items'])) ?>')">
                                <i class="fas fa-ban mr-1"></i>Void
                              </button>
                              <button class="btn-refund"
                                onclick="openRefundModal(<?= (int)$o['id'] ?>,'<?= htmlspecialchars($o['table_no']) ?>',<?= (float)$o['total_amt'] ?>,'<?= htmlspecialchars(addslashes($o['items'])) ?>')">
                                <i class="fas fa-rotate-left mr-1"></i>Refund
                              </button>
                            </div>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr><td colspan="8" class="text-center text-muted p-4">No active orders.</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- ── Voided Tab ──────────────────────────────── -->
              <div class="tab-pane fade" id="pane-voided" role="tabpanel">
                <div class="table-responsive">
                  <table id="tbl-voided" class="table table-bordered table-striped m-0">
                    <thead>
                      <tr>
                        <th>Order #</th>
                        <th>Date &amp; Time</th>
                        <th>Bill No.</th>
                        <th>Cashier</th>
                        <th>Items</th>
                        <th>Qty</th>
                        <th>Amount Voided (&#8369;)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($voidedOrders)): ?>
                        <?php foreach ($voidedOrders as $o): ?>
                        <tr>
                          <td><strong>#<?= (int)$o['id'] ?></strong></td>
                          <td><small class="text-muted"><?= date('M d, Y g:i A', strtotime($o['created_at'])) ?></small></td>
                          <td><span class="badge badge-secondary">#<?= htmlspecialchars($o['table_no']) ?></span></td>
                          <td><?= htmlspecialchars($o['cashier_name'] ?? '—') ?></td>
                          <td title="<?= htmlspecialchars($o['items']) ?>"><?= htmlspecialchars(mb_strimwidth($o['items'], 0, 45, '…')) ?></td>
                          <td><?= (int)$o['total_qty'] ?></td>
                          <td><span class="text-danger font-weight-bold">&#8369;<?= number_format((float)$o['total_amt'], 2) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted p-4">No voided orders found.</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- ── Refunded Tab ────────────────────────────── -->
              <div class="tab-pane fade" id="pane-refunded" role="tabpanel">
                <div class="table-responsive">
                  <table id="tbl-refunded" class="table table-bordered table-striped m-0">
                    <thead>
                      <tr>
                        <th>Order #</th>
                        <th>Order Date</th>
                        <th>Bill No.</th>
                        <th>Type</th>
                        <th>Items</th>
                        <th>Order Total (&#8369;)</th>
                        <th>Refund Amt (&#8369;)</th>
                        <th>Reason</th>
                        <th>Processed By</th>
                        <th>Refund Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($refundedOrders)): ?>
                        <?php foreach ($refundedOrders as $o): ?>
                        <tr>
                          <td><strong>#<?= (int)$o['id'] ?></strong></td>
                          <td><small class="text-muted"><?= date('M d, Y g:i A', strtotime($o['created_at'])) ?></small></td>
                          <td><span class="badge badge-secondary">#<?= htmlspecialchars($o['table_no']) ?></span></td>
                          <td>
                            <?php if ($o['status'] === 'partial_refund'): ?>
                              <span class="badge-partial"><i class="fas fa-adjust mr-1"></i>Partial</span>
                            <?php else: ?>
                              <span class="badge-refunded"><i class="fas fa-rotate-left mr-1"></i>Full</span>
                            <?php endif; ?>
                          </td>
                          <td title="<?= htmlspecialchars($o['items']) ?>"><?= htmlspecialchars(mb_strimwidth($o['items'], 0, 40, '…')) ?></td>
                          <td><s class="text-muted">&#8369;<?= number_format((float)$o['total_amt'], 2) ?></s></td>
                          <td><span class="text-primary font-weight-bold">&#8369;<?= number_format((float)($o['refund_total'] ?? 0), 2) ?></span></td>
                          <td><?= htmlspecialchars($o['refund_reason'] ?? '—') ?></td>
                          <td><?= htmlspecialchars($o['processed_by'] ?? '—') ?></td>
                          <td><small class="text-muted"><?= $o['refund_at'] ? date('M d, Y g:i A', strtotime($o['refund_at'])) : '—' ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr><td colspan="10" class="text-center text-muted p-4">No refunded orders found.</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>

            </div><!-- /.tab-content -->
          </div>
        </div><!-- /.card -->

      </div>
    </section>
  </div><!-- /.content-wrapper -->
</div><!-- /.wrapper -->

<!-- ══════════════════════════════════════════════════════════
     VOID MODAL
═══════════════════════════════════════════════════════════════ -->
<div class="vr-modal-overlay" id="voidOverlay">
  <div class="vr-modal">
    <div class="vr-modal-header">
      <h5><i class="fas fa-ban mr-2" style="color:#e74c3c"></i>Void Order</h5>
      <button onclick="closeVoidModal()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:inherit;">&times;</button>
    </div>
    <div class="vr-modal-body">
      <div class="order-meta-strip" id="voidMeta"></div>
      <div class="reason-label">Reason for Void</div>
      <input type="text" id="voidReason" class="reason-input" placeholder="e.g. Customer cancelled order…">
      <div class="refund-amt-display void-mode mt-3">
        <div>
          <div class="ramt-lbl">Amount to Reverse</div>
        </div>
        <div class="ramt-val" id="voidAmt">&#8369;0.00</div>
      </div>
      <div class="alert alert-warning mt-3 mb-0" style="font-size:.83rem;border-radius:8px;">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        <strong>Warning:</strong> Voiding cannot be undone. The order will be permanently marked as voided and removed from revenue.
      </div>
    </div>
    <div class="vr-modal-footer">
      <button class="btn btn-secondary" onclick="closeVoidModal()">Cancel</button>
      <button class="btn btn-danger font-weight-bold" id="voidConfirmBtn" onclick="submitVoid()">
        <i class="fas fa-ban mr-1"></i> Confirm Void
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     REFUND MODAL
═══════════════════════════════════════════════════════════════ -->
<div class="vr-modal-overlay" id="refundOverlay">
  <div class="vr-modal" style="max-width:580px;">
    <div class="vr-modal-header">
      <h5><i class="fas fa-rotate-left mr-2" style="color:#3b82f6"></i>Process Refund</h5>
      <button onclick="closeRefundModal()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:inherit;">&times;</button>
    </div>
    <div class="vr-modal-body">
      <div class="order-meta-strip" id="refundMeta"></div>

      <!-- Items selection for partial refund -->
      <div id="refundItemsSection" style="display:none;">
        <div class="reason-label mb-2">Select Items to Refund</div>
        <div id="refundItemsList" style="max-height:200px;overflow-y:auto;margin-bottom:14px;padding-right:2px;"></div>
      </div>
      <div id="refundLoadingMsg" style="text-align:center;padding:16px;color:#888;display:none;">
        <i class="fas fa-spinner fa-spin mr-1"></i> Loading order items…
      </div>

      <div class="reason-label">Reason for Refund</div>
      <input type="text" id="refundReason" class="reason-input" placeholder="e.g. Wrong item served…">

      <div class="refund-amt-display mt-3">
        <div>
          <div class="ramt-lbl">Refund Amount</div>
        </div>
        <div class="ramt-val" id="refundAmt" style="color:#3b82f6;">&#8369;0.00</div>
      </div>
    </div>
    <div class="vr-modal-footer">
      <button class="btn btn-secondary" onclick="closeRefundModal()">Cancel</button>
      <button class="btn btn-primary font-weight-bold" id="refundConfirmBtn" onclick="submitRefund()">
        <i class="fas fa-rotate-left mr-1"></i> Confirm Refund
      </button>
    </div>
  </div>
</div>

<!-- ── Toast Notification ─────────────────────────────────────── -->
<div id="vrToast" style="
  position:fixed;bottom:28px;right:24px;z-index:99999;
  padding:12px 20px;border-radius:10px;font-size:.9rem;font-weight:600;
  color:#fff;box-shadow:0 8px 28px rgba(0,0,0,.25);
  opacity:0;pointer-events:none;transition:opacity .25s ease;max-width:340px;
"></div>

<!-- ── Scripts ──────────────────────────────────────────────── -->
<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
<script src="../dist/js/adminlte.js"></script>
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
// ── Pending orders data from PHP ──────────────────────────────
const PENDING_ORDERS = <?= $pendingOrdersJson ?>;

// ── DataTables init ───────────────────────────────────────────
$(function () {
  ['tbl-active','tbl-voided','tbl-refunded'].forEach(function(id) {
    var el = $('#' + id);
    if (el.length && !$.fn.DataTable.isDataTable(el)) {
      el.DataTable({
        responsive: true,
        lengthChange: false,
        autoWidth: false,
        order: [[1, 'desc']],
        buttons: ['copy','csv','excel','pdf','print','colvis']
      }).buttons().container().appendTo('#' + id + '_wrapper .col-md-6:eq(0)');
    }
  });

  // Re-init on tab show so hidden tables render properly
  $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    var pane = $(e.target).attr('href');
    $(pane).find('table').each(function () {
      if ($.fn.DataTable.isDataTable(this)) {
        $(this).DataTable().columns.adjust().responsive.recalc();
      }
    });
  });
});

// ── Void modal state ──────────────────────────────────────────
let voidOrderId    = null;
let voidOrderTotal = 0;

function openVoidModal(id, tableNo, total, items) {
  voidOrderId    = id;
  voidOrderTotal = total;

  document.getElementById('voidMeta').innerHTML = `
    <div class="om-item"><strong>#${id}</strong>Order ID</div>
    <div class="om-item"><strong>#${tableNo}</strong>Bill No.</div>
    <div class="om-item"><strong style="color:#e74c3c">₱${total.toLocaleString('en',{minimumFractionDigits:2})}</strong>Total</div>
    <div class="om-item" style="flex:1;min-width:120px;"><strong style="font-size:.85rem;font-weight:600;">${items || '—'}</strong>Items</div>`;

  document.getElementById('voidAmt').textContent = '₱' + total.toLocaleString('en', {minimumFractionDigits: 2});
  document.getElementById('voidReason').value = '';

  const btn = document.getElementById('voidConfirmBtn');
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-ban mr-1"></i> Confirm Void';

  document.getElementById('voidOverlay').classList.add('open');
}

function closeVoidModal() {
  document.getElementById('voidOverlay').classList.remove('open');
}

async function submitVoid() {
  const reason = document.getElementById('voidReason').value.trim();
  const btn    = document.getElementById('voidConfirmBtn');

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Processing…';

  try {
    const res  = await fetch('../../Backend/pos_void_refund.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ action: 'void', order_id: voidOrderId, reason })
    });
    const data = await res.json();

    if (data.success) {
      closeVoidModal();
      showToast('Order #' + voidOrderId + ' has been voided.', '#e74c3c');
      setTimeout(() => location.reload(), 1600);
    } else {
      showToast(data.message || 'Failed to void order.', '#e74c3c');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-ban mr-1"></i> Confirm Void';
    }
  } catch (err) {
    showToast('Network error. Please try again.', '#e74c3c');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-ban mr-1"></i> Confirm Void';
  }
}

// ── Refund modal state ────────────────────────────────────────
let refundOrderId    = null;
let refundOrderTotal = 0;
let refundItems      = [];  // [{order_item_id, menu_id, name, qty, unit_price, refundQty}]

function openRefundModal(id, tableNo, total, items) {
  refundOrderId    = id;
  refundOrderTotal = total;
  refundItems      = [];

  document.getElementById('refundMeta').innerHTML = `
    <div class="om-item"><strong>#${id}</strong>Order ID</div>
    <div class="om-item"><strong>#${tableNo}</strong>Bill No.</div>
    <div class="om-item"><strong style="color:#3b82f6">₱${total.toLocaleString('en',{minimumFractionDigits:2})}</strong>Total</div>
    <div class="om-item" style="flex:1;min-width:120px;"><strong style="font-size:.85rem;font-weight:600;">${items || '—'}</strong>Items</div>`;

  document.getElementById('refundAmt').textContent = '₱' + total.toLocaleString('en', {minimumFractionDigits: 2});
  document.getElementById('refundReason').value = '';

  const btn = document.getElementById('refundConfirmBtn');
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-rotate-left mr-1"></i> Confirm Refund';

  // Load order items
  const section = document.getElementById('refundItemsSection');
  const loading = document.getElementById('refundLoadingMsg');
  const listEl  = document.getElementById('refundItemsList');

  section.style.display = 'none';
  loading.style.display = 'block';
  listEl.innerHTML = '';

  document.getElementById('refundOverlay').classList.add('open');

  fetch('../../Backend/pos_get_order_items.php?order_id=' + id)
    .then(r => r.json())
    .then(data => {
      loading.style.display = 'none';
      if (data.success && data.items && data.items.length) {
        refundItems = data.items.map(i => ({...i, refundQty: i.qty}));
        renderRefundItems();
        section.style.display = 'block';
      }
      // If items failed, full-refund mode (no item list shown)
      updateRefundAmt();
    })
    .catch(() => {
      loading.style.display = 'none';
      updateRefundAmt();
    });
}

function closeRefundModal() {
  document.getElementById('refundOverlay').classList.remove('open');
}

function renderRefundItems() {
  const listEl = document.getElementById('refundItemsList');
  listEl.innerHTML = refundItems.map((item, idx) => `
    <div class="refund-item-row selected" id="ri-row-${idx}" onclick="toggleRefundItem(${idx})">
      <input type="checkbox" id="ri-chk-${idx}" checked onclick="event.stopPropagation();toggleRefundItem(${idx})">
      <span class="refund-item-name">${item.name}</span>
      <div class="refund-qty-ctrl" onclick="event.stopPropagation()">
        <button class="refund-qty-btn" onclick="changeRefundQty(${idx},-1)"><i class="fas fa-minus" style="font-size:9px"></i></button>
        <span class="refund-qty-val" id="ri-qty-${idx}">${item.refundQty}</span>
        <button class="refund-qty-btn" onclick="changeRefundQty(${idx},1)"><i class="fas fa-plus" style="font-size:9px"></i></button>
      </div>
      <span class="refund-item-price" id="ri-amt-${idx}">₱${(item.unit_price * item.refundQty).toLocaleString('en',{minimumFractionDigits:2})}</span>
    </div>`).join('');
}

function toggleRefundItem(idx) {
  const row = document.getElementById('ri-row-' + idx);
  const chk = document.getElementById('ri-chk-' + idx);
  const isSelected = row.classList.contains('selected');
  if (isSelected) {
    row.classList.remove('selected');
    chk.checked = false;
    refundItems[idx].refundQty = 0;
    document.getElementById('ri-qty-' + idx).textContent = 0;
    document.getElementById('ri-amt-' + idx).textContent = '₱0.00';
  } else {
    row.classList.add('selected');
    chk.checked = true;
    refundItems[idx].refundQty = refundItems[idx].qty;
    document.getElementById('ri-qty-' + idx).textContent = refundItems[idx].qty;
    document.getElementById('ri-amt-' + idx).textContent = '₱' + (refundItems[idx].unit_price * refundItems[idx].qty).toLocaleString('en',{minimumFractionDigits:2});
  }
  updateRefundAmt();
}

function changeRefundQty(idx, delta) {
  const item = refundItems[idx];
  const newQty = Math.max(0, Math.min(item.qty, (item.refundQty || 0) + delta));
  item.refundQty = newQty;
  document.getElementById('ri-qty-' + idx).textContent = newQty;
  document.getElementById('ri-amt-' + idx).textContent = '₱' + (item.unit_price * newQty).toLocaleString('en',{minimumFractionDigits:2});

  // Auto-toggle row selected state
  const row = document.getElementById('ri-row-' + idx);
  const chk = document.getElementById('ri-chk-' + idx);
  if (newQty > 0) { row.classList.add('selected'); chk.checked = true; }
  else            { row.classList.remove('selected'); chk.checked = false; }

  updateRefundAmt();
}

function updateRefundAmt() {
  let amt;
  if (refundItems.length) {
    amt = refundItems.reduce((s, i) => s + (parseFloat(i.unit_price) * (i.refundQty || 0)), 0);
  } else {
    amt = refundOrderTotal;
  }
  document.getElementById('refundAmt').textContent = '₱' + amt.toLocaleString('en', {minimumFractionDigits: 2});
}

async function submitRefund() {
  const reason = document.getElementById('refundReason').value.trim();
  const btn    = document.getElementById('refundConfirmBtn');

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Processing…';

  const payload = { action: 'refund', order_id: refundOrderId, reason };

  if (refundItems.length) {
    const selectedItems = refundItems.filter(i => i.refundQty > 0).map(i => ({ menu_id: i.menu_id, qty: i.refundQty }));
    if (!selectedItems.length) {
      showToast('Please select at least one item to refund.', '#e74c3c');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-rotate-left mr-1"></i> Confirm Refund';
      return;
    }
    // Check if it's a full refund (all items at max qty)
    const isFullRefund = refundItems.every(i => i.refundQty === i.qty);
    if (!isFullRefund) payload.refund_items = selectedItems;
  }

  try {
    const res  = await fetch('../../Backend/pos_void_refund.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload)
    });
    const data = await res.json();

    if (data.success) {
      closeRefundModal();
      showToast('Refund processed for Order #' + refundOrderId + '.', '#3b82f6');
      setTimeout(() => location.reload(), 1600);
    } else {
      showToast(data.message || 'Failed to process refund.', '#e74c3c');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-rotate-left mr-1"></i> Confirm Refund';
    }
  } catch (err) {
    showToast('Network error. Please try again.', '#e74c3c');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-rotate-left mr-1"></i> Confirm Refund';
  }
}

// Close modals on overlay click
document.getElementById('voidOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeVoidModal();
});
document.getElementById('refundOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeRefundModal();
});

// ── Toast ──────────────────────────────────────────────────────
let toastTimer;
function showToast(msg, color) {
  const el = document.getElementById('vrToast');
  el.textContent = msg;
  el.style.background = color || '#333';
  el.style.opacity = '1';
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.style.opacity = '0'; }, 3000);
}

// ── Dark Mode ──────────────────────────────────────────────────
$(function () {
  var isDark = localStorage.getItem('darkMode') === 'true';
  function applyMode(dark) {
    if (dark) {
      $('body').addClass('dark-mode');
      $('.main-header.navbar').addClass('navbar-dark').removeClass('navbar-white navbar-light bg-white');
      $('#darkModeToggle i').removeClass('fa-moon').addClass('fa-sun');
    } else {
      $('body').removeClass('dark-mode');
      $('.main-header.navbar').removeClass('navbar-dark').addClass('navbar-white navbar-light bg-white');
      $('#darkModeToggle i').removeClass('fa-sun').addClass('fa-moon');
    }
  }
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

  function connect() {
    if (!window.EventSource) return;
    var es = new EventSource(SSE_URL);
    es.addEventListener('stats', function(e){
      try {
        var d = JSON.parse(e.data);
        setDot(true);
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

<!-- ══ REAL-TIME TABLE & STAT UPDATES (polling) ══════════════════════ -->
<script>
(function () {
  'use strict';

  /* ── Config ── */
  var POLL_MS   = 8000;   // poll every 8 s
  var POLL_URL  = 'void_refund.php?rt=1'; // self-contained endpoint

  /* ── Snapshot of counts when page loaded ── */
  var _snap = {
    voidedCount:    parseInt('<?= $voidedCount ?>', 10)   || 0,
    refundedCount:  parseInt('<?= $refundedCount ?>', 10) || 0,
    totalVoided:    parseFloat('<?= $totalVoided ?>') || 0,
    totalRefunded:  parseFloat('<?= $totalRefunded ?>') || 0,
    pendingCount:   parseInt('<?= count($pendingOrders) ?>', 10) || 0,
    latestOrderId:  <?= !empty($pendingOrders) ? (int)$pendingOrders[0]['id'] : 0 ?>
  };

  /* ── Live-dot indicator in page title area ── */
  var _dot = (function () {
    var el = document.createElement('span');
    el.id  = 'vrLiveDot';
    el.title = 'Live updates active';
    el.style.cssText = [
      'display:inline-block','width:8px','height:8px',
      'border-radius:50%','background:#22c55e',
      'margin-left:8px','vertical-align:middle',
      'box-shadow:0 0 0 0 rgba(34,197,94,.55)',
      'animation:rtPulse 1.8s infinite'
    ].join(';');
    /* append after the page <h1> */
    var h1 = document.querySelector('.content-header h1');
    if (h1) h1.appendChild(el);
    return el;
  }());

  function setDot(ok) {
    _dot.style.background = ok ? '#22c55e' : '#ef4444';
    _dot.title = ok ? 'Live — connected' : 'Live — reconnecting…';
  }

  /* ── Stat card elements ── */
  function $stat(idx) {
    return document.querySelectorAll('.vr-stat .stat-num')[idx];
  }

  function animateNum(el, newText) {
    if (!el || el.textContent === newText) return;
    el.style.transition = 'opacity .25s';
    el.style.opacity = '0';
    setTimeout(function () {
      el.textContent = newText;
      el.style.opacity = '1';
    }, 250);
  }

  /* ── Tab badge elements ── */
  function tabBadge(tabId) {
    var a = document.querySelector('[href="#' + tabId + '"]');
    return a ? a.querySelector('.badge') : null;
  }

  /* ── Flash a row highlight ── */
  function flashRow(tr) {
    tr.style.transition = 'background .1s';
    tr.style.background = 'rgba(233,30,140,.18)';
    setTimeout(function () { tr.style.background = ''; }, 1400);
  }

  /* ── Add a new row to the Active Orders DataTable ── */
  function injectActiveRow(o) {
    var dt = $('#tbl-active').DataTable();
    /* Build status badge */
    var statusBadge = '<span class="badge-done"><i class="fas fa-check mr-1"></i>' +
      (o.status.charAt(0).toUpperCase() + o.status.slice(1)) + '</span>';
    /* Build action buttons */
    var items  = (o.items || '—').replace(/'/g, "\\'");
    var actions = '<div class="d-flex gap-1" style="gap:6px;">' +
      '<button class="btn-void" onclick="openVoidModal(' + o.id + ',\'' +
        o.table_no + '\',' + o.total_amt + ',\'' + items + '\')">' +
        '<i class="fas fa-ban mr-1"></i>Void</button>' +
      '<button class="btn-refund" onclick="openRefundModal(' + o.id + ',\'' +
        o.table_no + '\',' + o.total_amt + ',\'' + items + '\')">' +
        '<i class="fas fa-rotate-left mr-1"></i>Refund</button></div>';

    /* Date format from PHP: "M d, Y g:i A" */
    var d = new Date(o.created_at.replace(' ', 'T'));
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var dateStr = months[d.getMonth()] + ' ' + String(d.getDate()).padStart(2,'0') + ', ' +
      d.getFullYear() + ' ' + d.toLocaleTimeString('en-PH',{hour:'numeric',minute:'2-digit',hour12:true});

    var totalFmt = '₱' + parseFloat(o.total_amt).toLocaleString('en',{minimumFractionDigits:2});
    var itemsShort = (o.items || '—').length > 45 ? o.items.substring(0,45) + '…' : (o.items || '—');

    var row = dt.row.add([
      '<strong>#' + o.id + '</strong>',
      '<small class="text-muted">' + dateStr + '</small>',
      '<span class="badge badge-secondary">#' + o.table_no + '</span>',
      o.cashier_name || '—',
      '<span title="' + (o.items||'') + '">' + itemsShort + '</span>',
      statusBadge,
      '<strong>' + totalFmt + '</strong>',
      actions
    ]).draw(false).node();

    $(row).find('td').css({'white-space':'nowrap','overflow':'hidden','text-overflow':'ellipsis','max-width':'260px'});
    flashRow(row);
  }

  /* ── Remove a row from Active Orders by order id ── */
  function removeActiveRow(orderId) {
    var dt  = $('#tbl-active').DataTable();
    var str = '#' + orderId;
    dt.rows(function(i, data) {
      return typeof data[0] === 'string' && data[0].indexOf('>' + str + '<') !== -1;
    }).remove().draw(false);
  }

  /* ── Show a small inline alert banner ── */
  var _bannerTimer;
  function showBanner(msg, color) {
    var el = document.getElementById('vrRtBanner');
    if (!el) {
      el = document.createElement('div');
      el.id = 'vrRtBanner';
      el.style.cssText = [
        'position:fixed','top:70px','left:50%','transform:translateX(-50%)',
        'z-index:99998','border-radius:10px','padding:9px 20px',
        'font-size:.85rem','font-weight:700','color:#fff',
        'box-shadow:0 4px 18px rgba(0,0,0,.22)',
        'opacity:0','transition:opacity .3s','pointer-events:none'
      ].join(';');
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.style.background = color || '#333';
    el.style.opacity = '1';
    clearTimeout(_bannerTimer);
    _bannerTimer = setTimeout(function () { el.style.opacity = '0'; }, 4000);
  }

  /* ── Main poll ── */
  function poll() {
    fetch(POLL_URL + '&since=' + _snap.latestOrderId, { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        setDot(true);

        /* ── Stat cards ── */
        var vc = parseInt(d.voidedCount,   10) || 0;
        var rc = parseInt(d.refundedCount, 10) || 0;
        var tv = parseFloat(d.totalVoided)   || 0;
        var tr = parseFloat(d.totalRefunded) || 0;
        var pc = parseInt(d.pendingCount,  10) || 0;

        if (vc !== _snap.voidedCount) {
          animateNum($stat(0), String(vc));
          var vb = tabBadge('pane-voided'); if (vb) vb.textContent = vc;
        }
        if (tv !== _snap.totalVoided) {
          animateNum($stat(1), '₱' + tv.toLocaleString('en',{minimumFractionDigits:2}));
        }
        if (rc !== _snap.refundedCount) {
          animateNum($stat(2), String(rc));
          var rb = tabBadge('pane-refunded'); if (rb) rb.textContent = rc;
        }
        if (tr !== _snap.totalRefunded) {
          animateNum($stat(3), '₱' + tr.toLocaleString('en',{minimumFractionDigits:2}));
        }

        /* ── Active Orders count badge ── */
        if (pc !== _snap.pendingCount) {
          var ab = tabBadge('pane-active'); if (ab) ab.textContent = pc;
        }

        /* ── New active orders injected into table ── */
        var newOrders = d.newOrders || [];
        newOrders.forEach(function (o) {
          if (o.id > _snap.latestOrderId) {
            injectActiveRow(o);
          }
        });

        /* ── Orders that became voided/refunded — remove from Active tab ── */
        var removed = d.removedIds || [];
        removed.forEach(function (id) { removeActiveRow(id); });

        /* ── Toasts for changes ── */
        if (vc > _snap.voidedCount) {
          showBanner('⚠️ ' + (vc - _snap.voidedCount) + ' order(s) voided', '#e74c3c');
        }
        if (rc > _snap.refundedCount) {
          showBanner('↩️ ' + (rc - _snap.refundedCount) + ' order(s) refunded', '#3b82f6');
        }
        if (d.latestOrderId > _snap.latestOrderId) {
          _snap.latestOrderId = d.latestOrderId;
        }

        /* ── Update snapshot ── */
        _snap.voidedCount   = vc;
        _snap.refundedCount = rc;
        _snap.totalVoided   = tv;
        _snap.totalRefunded = tr;
        _snap.pendingCount  = pc;
      })
      .catch(function () { setDot(false); });
  }

  /* ── Start polling after page ready ── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { setInterval(poll, POLL_MS); });
  } else {
    setInterval(poll, POLL_MS);
  }

})();
</script>
<!-- ══ END real-time table updates ═══════════════════════════════════ -->

</body>
</html>