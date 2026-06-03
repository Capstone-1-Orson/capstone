<?php
// Frontend/ADMIN/void_refund.php  (OOP refactored)
require_once '../../Frontend/Core/VoidRefundView.php';
$view = new VoidRefundView();   // dispatches ?rt JSON endpoint early

// Variable aliases
$voidedCount    = $view->voidedCount;
$refundedCount  = $view->refundedCount;
$totalVoided    = $view->totalVoided;
$totalRefunded  = $view->totalRefunded;
$voidedOrders      = $view->voidedOrders;
$refundedOrders    = $view->refundedOrders;
$pendingOrders     = $view->pendingOrders;
$pendingOrdersJson = $view->pendingOrdersJson ?? json_encode($view->pendingOrders ?? []);
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
          <li class="nav-item"><a href="./sale_revenue.php"    class="nav-link"><i class="nav-icon fas fa-chart-line"></i><p>Sales &amp; Revenue</p></a></li>
          <li class="nav-item"><a href="./report.php"          class="nav-link"><i class="nav-icon fas fa-file-alt"></i><p>Reports</p></a></li>
          <li class="nav-item"><a href="./void_refund.php"     class="nav-link active"><i class="nav-icon fas fa-undo-alt"></i><p>Void &amp; Refund</p></a></li>
          <li class="nav-item"><a href="./settings.php"        class="nav-link"><i class="nav-icon fas fa-cog"></i><p>Settings</p></a></li>
          <li class="nav-item mt-auto"><a href="../../Backend/Controllers/LogoutController.php" class="nav-link"><i class="nav-icon fas fa-sign-out-alt"></i><p>Log Out</p></a></li>
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
                <!-- ── Real-time header bar ── -->
                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 14px;border-bottom:1px solid rgba(233,30,140,.15);background:rgba(233,30,140,.04);">
                  <div id="activeRtNewBadge" style="display:none;">
                    <span style="font-size:.8rem;font-weight:700;color:#e91e8c;">
                      <i class="fas fa-bolt mr-1" style="font-size:.72rem;"></i>New order received — table updated
                    </span>
                  </div>
                  <div style="flex:1;"></div>
                  <span id="activeUpdatedAt" style="font-size:.78rem;color:#aaa;font-weight:600;">
                    Updated <span id="activeUpdatedTime">—</span>
                  </span>
                </div>

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
                          <td><span data-order="<?= strtotime($o['created_at']) ?>"><small class="text-muted"><?= date('M d, Y g:i A', strtotime($o['created_at'])) ?></small></span></td>
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
                        <th>Order Date</th>
                        <th>Bill No.</th>
                        <th>Cashier</th>
                        <th>Items</th>
                        <th>Qty</th>
                        <th>Amount Voided (&#8369;)</th>
                        <th>Void Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($voidedOrders)): ?>
                        <?php foreach ($voidedOrders as $o): ?>
                        <tr>
                          <td><strong>#<?= (int)$o['id'] ?></strong></td>
                          <td><span data-order="<?= strtotime($o['created_at']) ?>"><small class="text-muted"><?= date('M d, Y g:i A', strtotime($o['created_at'])) ?></small></span></td>
                          <td><span class="badge badge-secondary">#<?= htmlspecialchars($o['table_no']) ?></span></td>
                          <td><?= htmlspecialchars($o['cashier_name'] ?? '—') ?></td>
                          <td title="<?= htmlspecialchars($o['items']) ?>"><?= htmlspecialchars(mb_strimwidth($o['items'], 0, 45, '…')) ?></td>
                          <td><?= (int)$o['total_qty'] ?></td>
                          <td><span class="text-danger font-weight-bold">&#8369;<?= number_format((float)$o['total_amt'], 2) ?></span></td>
                          <?php $voidTs = $o['voided_at'] ?? $o['updated_at'] ?? $o['created_at']; ?>
                          <td><span data-order="<?= $voidTs ? strtotime($voidTs) : 0 ?>"><small class="text-muted"><?= $voidTs ? date('M d, Y g:i A', strtotime($voidTs)) : '—' ?></small></span></td>
                        </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr><td colspan="8" class="text-center text-muted p-4">No voided orders found.</td></tr>
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
                          <td><span data-order="<?= strtotime($o['created_at']) ?>"><small class="text-muted"><?= date('M d, Y g:i A', strtotime($o['created_at'])) ?></small></span></td>
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
                          <td><span data-order="<?= $o['refund_at'] ? strtotime($o['refund_at']) : 0 ?>"><small class="text-muted"><?= $o['refund_at'] ? date('M d, Y g:i A', strtotime($o['refund_at'])) : '—' ?></small></span></td>
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
    const res  = await fetch('../../Backend/Controllers/RefundVoidController.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ action: 'void', order_id: voidOrderId, reason })
    });
    const data = await res.json();

    if (data.success) {
      closeVoidModal();
      showToast('Order #' + voidOrderId + ' has been voided.', '#e74c3c');
      // Remove from Active table immediately
      removeActiveRow(voidOrderId);
      // The next poll will inject the row into the Voided tab and update stats
    } else {
      showToast(data.message || 'Failed to void order.', '#e74c3c');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-ban mr-1"></i> Confirm Void';
    }
  } catch (err) {
    showToast('Network error: ' + (err.message || 'Please try again.'), '#e74c3c');
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

  fetch('../../Backend/Controllers/OrderItemsController.php?order_id=' + id)
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
    const res  = await fetch('../../Backend/Controllers/RefundVoidController.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload)
    });
    const data = await res.json();

    if (data.success) {
      closeRefundModal();
      showToast('Refund processed for Order #' + refundOrderId + '.', '#3b82f6');
      // Remove from Active table immediately
      removeActiveRow(refundOrderId);
      // The next poll will inject the row into the Refunded tab and update stats
    } else {
      showToast(data.message || 'Failed to process refund.', '#e74c3c');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-rotate-left mr-1"></i> Confirm Refund';
    }
  } catch (err) {
    showToast('Network error: ' + (err.message || 'Please try again.'), '#e74c3c');
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

// ── Remove a row from the Active Orders DataTable (global scope) ──
function removeActiveRow(orderId) {
  var dt  = $('#tbl-active').DataTable();
  var str = '#' + orderId;
  dt.rows(function(i, data) {
    return typeof data[0] === 'string' && data[0].indexOf('>' + str + '<') !== -1;
  }).remove().draw(false);
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

  // ── Initialise DataTables ──────────────────────────────────────

  /* Shared render function: extracts numeric timestamp from
     data-order="..." for sorting, returns raw HTML for display */
  function dtDateRender(data, type) {
    if (type === 'sort' || type === 'type') {
      var s = (typeof data === 'string') ? data : (data ? String(data) : '');
      var m = s.match(/data-order="(\d+)"/);
      return m ? parseInt(m[1], 10) : 0;
    }
    return data;
  }

  // Active Orders: no search bar, no length-change dropdown
  if (!$.fn.DataTable.isDataTable('#tbl-active')) {
    $('#tbl-active').DataTable({
      order:        [[1, 'desc']],
      searching:    false,
      lengthChange: false,
      pageLength:   10,
      language:     { emptyTable: 'No active orders.' },
      columnDefs: [
        { targets: 1, type: 'num', render: dtDateRender },
        { targets: 7, orderable: false, searchable: false },
        { targets: '_all', defaultContent: '' }
      ]
    });
  }
  // Voided: keep search + length controls
  if (!$.fn.DataTable.isDataTable('#tbl-voided')) {
    $('#tbl-voided').DataTable({
      order:      [[7, 'desc']],
      pageLength: 10,
      language:   { emptyTable: 'No voided orders.' },
      columnDefs: [
        { targets: 1, type: 'num', render: dtDateRender },
        { targets: 7, type: 'num', render: dtDateRender },
        { targets: '_all', defaultContent: '' }
      ]
    });
  }
  // Refunded: two date columns — col 1 (Order Date) and col 9 (Refund Date)
  if (!$.fn.DataTable.isDataTable('#tbl-refunded')) {
    $('#tbl-refunded').DataTable({
      order:      [[1, 'desc']],
      pageLength: 10,
      language:   { emptyTable: 'No refunded orders.' },
      columnDefs: [
        { targets: 1, type: 'num', render: dtDateRender },
        { targets: 9, type: 'num', render: dtDateRender },
        { targets: '_all', defaultContent: '' }
      ]
    });
  }

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

<!-- ── Void real-time notification toast ── -->
<div id="voidRtToast" style="
  display:none;position:fixed;bottom:24px;right:24px;z-index:99998;
  min-width:300px;max-width:360px;
  background:linear-gradient(135deg,#e74c3c 0%,#c0392b 100%);
  color:#fff;border-radius:14px;
  box-shadow:0 8px 32px rgba(231,76,60,.45);
  padding:16px 20px;font-family:'Source Sans Pro',sans-serif;
  animation:toastSlideIn .35s cubic-bezier(.22,1,.36,1);
">
  <div style="display:flex;align-items:flex-start;gap:12px;">
    <div style="font-size:1.6rem;line-height:1;">🚫</div>
    <div style="flex:1;">
      <div style="font-weight:700;font-size:.95rem;margin-bottom:2px;">Order Voided</div>
      <div id="voidRtMsg" style="font-size:.82rem;opacity:.9;">An order has been voided.</div>
    </div>
    <button onclick="document.getElementById('voidRtToast').style.display='none'"
      style="background:none;border:none;color:#fff;font-size:1.1rem;cursor:pointer;padding:0;line-height:1;opacity:.8;">✕</button>
  </div>
  <div style="margin-top:10px;display:flex;gap:8px;">
    <div id="voidRtOrderBadge" style="background:rgba(255,255,255,.2);border-radius:20px;padding:3px 10px;font-size:.78rem;font-weight:600;"></div>
    <div id="voidRtTimeBadge"  style="background:rgba(255,255,255,.15);border-radius:20px;padding:3px 10px;font-size:.78rem;"></div>
  </div>
</div>

<!-- ── Refund real-time notification toast ── -->
<div id="refundRtToast" style="
  display:none;position:fixed;bottom:24px;right:24px;z-index:99997;
  min-width:300px;max-width:360px;
  background:linear-gradient(135deg,#3b82f6 0%,#1d4ed8 100%);
  color:#fff;border-radius:14px;
  box-shadow:0 8px 32px rgba(59,130,246,.45);
  padding:16px 20px;font-family:'Source Sans Pro',sans-serif;
  animation:toastSlideIn .35s cubic-bezier(.22,1,.36,1);
">
  <div style="display:flex;align-items:flex-start;gap:12px;">
    <div style="font-size:1.6rem;line-height:1;">↩️</div>
    <div style="flex:1;">
      <div style="font-weight:700;font-size:.95rem;margin-bottom:2px;">Refund Processed</div>
      <div id="refundRtMsg" style="font-size:.82rem;opacity:.9;">A refund has been processed.</div>
    </div>
    <button onclick="document.getElementById('refundRtToast').style.display='none'"
      style="background:none;border:none;color:#fff;font-size:1.1rem;cursor:pointer;padding:0;line-height:1;opacity:.8;">✕</button>
  </div>
  <div style="margin-top:10px;display:flex;gap:8px;">
    <div id="refundRtOrderBadge" style="background:rgba(255,255,255,.2);border-radius:20px;padding:3px 10px;font-size:.78rem;font-weight:600;"></div>
    <div id="refundRtTimeBadge"  style="background:rgba(255,255,255,.15);border-radius:20px;padding:3px 10px;font-size:.78rem;"></div>
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

  /* ── Show void real-time notification toast ── */
  function showVoidRtToast(orderId, tableNo, amount) {
    var toast = document.getElementById('voidRtToast');
    if (!toast) return;
    var msg   = document.getElementById('voidRtMsg');
    var badge = document.getElementById('voidRtOrderBadge');
    var time  = document.getElementById('voidRtTimeBadge');
    if (msg)   msg.textContent   = 'Table #' + (tableNo || '—') + ' — ₱' + parseFloat(amount || 0).toLocaleString('en',{minimumFractionDigits:2}) + ' reversed';
    if (badge) badge.textContent = 'Order #' + (orderId || '');
    if (time)  time.textContent  = new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',hour12:true});
    toast.style.display   = 'block';
    toast.style.animation = 'none';
    void toast.offsetWidth;
    toast.style.animation = 'toastSlideIn .35s cubic-bezier(.22,1,.36,1)';
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.style.display='none'; }, 7000);
    /* low warning beep */
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      var osc = ctx.createOscillator(); var g = ctx.createGain();
      osc.connect(g); g.connect(ctx.destination);
      osc.type = 'sine'; osc.frequency.value = 440;
      g.gain.setValueAtTime(0.25, ctx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
      osc.start(); osc.stop(ctx.currentTime + 0.5);
    } catch(e){}
  }

  /* ── Show refund real-time notification toast ── */
  function showRefundRtToast(orderId, tableNo, amount) {
    var toast = document.getElementById('refundRtToast');
    if (!toast) return;
    var msg   = document.getElementById('refundRtMsg');
    var badge = document.getElementById('refundRtOrderBadge');
    var time  = document.getElementById('refundRtTimeBadge');
    if (msg)   msg.textContent   = 'Table #' + (tableNo || '—') + ' — ₱' + parseFloat(amount || 0).toLocaleString('en',{minimumFractionDigits:2}) + ' refunded';
    if (badge) badge.textContent = 'Order #' + (orderId || '');
    if (time)  time.textContent  = new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',hour12:true});
    toast.style.display   = 'block';
    toast.style.animation = 'none';
    void toast.offsetWidth;
    toast.style.animation = 'toastSlideIn .35s cubic-bezier(.22,1,.36,1)';
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.style.display='none'; }, 7000);
    /* info chime */
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      var osc = ctx.createOscillator(); var g = ctx.createGain();
      osc.connect(g); g.connect(ctx.destination);
      osc.type = 'sine'; osc.frequency.value = 660;
      g.gain.setValueAtTime(0.25, ctx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.45);
      osc.start(); osc.stop(ctx.currentTime + 0.45);
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
    voidedCount:      parseInt('<?= $voidedCount ?>',  10)  || 0,
    refundedCount:    parseInt('<?= $refundedCount ?>', 10) || 0,
    totalVoided:      parseFloat('<?= $totalVoided ?>') || 0,
    totalRefunded:    parseFloat('<?= $totalRefunded ?>') || 0,
    pendingCount:     parseInt('<?= count($pendingOrders) ?>', 10) || 0,
    latestOrderId:    <?= (int)$view->maxActiveOrderId ?>,
    latestVoidedId:   <?= (int)$view->maxVoidedId ?>,
    latestRefundedId: <?= (int)$view->maxRefundedId ?>
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

    /* Skip duplicates — check if this order ID already exists in the table */
    var alreadyExists = false;
    dt.rows().every(function () {
      var rowData = this.data();
      if (rowData && rowData[0] && String(rowData[0]).indexOf('>#' + o.id + '<') !== -1) {
        alreadyExists = true;
      }
    });
    if (alreadyExists) return;

    /* Build status badge */
    var statusBadge = '<span class="badge-done"><i class="fas fa-check mr-1"></i>' +
      (o.status.charAt(0).toUpperCase() + o.status.slice(1)) + '</span>';

    /* Build action buttons — safely escape item strings */
    var safeItems = (o.items || '—')
      .replace(/\\/g, '\\\\')
      .replace(/'/g, "\\'")
      .replace(/"/g, '&quot;');
    var safeTotal = parseFloat(o.total_amt || 0);
    var actions =
      '<div class="d-flex" style="gap:6px;">' +
        '<button class="btn-void" onclick="openVoidModal(' + o.id + ',\'' +
          o.table_no + '\',' + safeTotal + ',\'' + safeItems + '\')">' +
          '<i class="fas fa-ban mr-1"></i>Void</button>' +
        '<button class="btn-refund" onclick="openRefundModal(' + o.id + ',\'' +
          o.table_no + '\',' + safeTotal + ',\'' + safeItems + '\')">' +
          '<i class="fas fa-rotate-left mr-1"></i>Refund</button>' +
      '</div>';

    var ts = Math.floor(new Date((o.created_at || '').replace(' ', 'T')).getTime() / 1000);

    var rowNode = dt.row.add([
      '<strong>#' + o.id + '</strong>',
      '<span data-order="' + ts + '"><small class="text-muted">' + fmtDate(o.created_at) + '</small></span>',
      '<span class="badge badge-secondary">#' + o.table_no + '</span>',
      o.cashier_name || '—',
      '<span title="' + (o.items || '').replace(/"/g, '&quot;') + '">' + trimItems(o.items, 45) + '</span>',
      statusBadge,
      '<strong>' + fmtPeso(o.total_amt) + '</strong>',
      actions
    ]).draw(false).node();

    /* Style cells */
    $(rowNode).find('td').css({
      'white-space': 'nowrap',
      'overflow': 'hidden',
      'text-overflow': 'ellipsis',
      'max-width': '260px'
    });

    /* Append a pulsing "NEW" badge to the Order # cell */
    $(rowNode).find('td:first').append(
      ' <span class="new-order-badge" style="' +
        'display:inline-block;background:#e91e8c;color:#fff;' +
        'font-size:.62rem;font-weight:700;padding:1px 6px;' +
        'border-radius:20px;vertical-align:middle;margin-left:4px;' +
        'animation:rtPulse 1.4s 3;">' +
      'NEW</span>'
    );
    /* Fade out the NEW badge after 10 s */
    setTimeout(function () {
      $(rowNode).find('.new-order-badge').fadeOut(400, function () { $(this).remove(); });
    }, 10000);

    /* Flash the entire row pink */
    flashRow(rowNode);

    /* Increment Active Orders tab badge */
    var ab = tabBadge('pane-active');
    if (ab) {
      var cur = parseInt(ab.textContent, 10) || 0;
      ab.textContent = cur + 1;
    }

    /* Re-sort by date descending so newest order appears first */
    dt.order([1, 'desc']).draw(false);

    /* Scroll the Active table into view */
    var wrapper = document.querySelector('#tbl-active_wrapper');
    if (wrapper) {
      wrapper.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

  /* ── Helpers ── */
  function fmtDate(str) {
    var d = new Date((str || '').replace(' ', 'T'));
    var mo = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return mo[d.getMonth()] + ' ' + String(d.getDate()).padStart(2,'0') + ', ' +
      d.getFullYear() + ' ' + d.toLocaleTimeString('en-PH',{hour:'numeric',minute:'2-digit',hour12:true});
  }
  function fmtPeso(n) { return '₱' + parseFloat(n||0).toLocaleString('en',{minimumFractionDigits:2}); }
  function trimItems(s,max){ return (s||'—').length > max ? (s||'—').substring(0,max)+'…' : (s||'—'); }

  /* ── Add a new row to the Voided DataTable ── */
  function injectVoidedRow(o) {
    var dt = $('#tbl-voided').DataTable();
    var ts  = Math.floor(new Date((o.created_at || '').replace(' ', 'T')).getTime() / 1000);
    var voidRaw = o.voided_at || o.updated_at || o.created_at || '';
    var vts = voidRaw ? Math.floor(new Date(voidRaw.replace(' ', 'T')).getTime() / 1000) : 0;
    var row = dt.row.add([
      '<strong>#' + o.id + '</strong>',
      '<span data-order="' + ts + '"><small class="text-muted">' + fmtDate(o.created_at) + '</small></span>',
      '<span class="badge badge-secondary">#' + o.table_no + '</span>',
      o.cashier_name || '—',
      '<span title="' + (o.items||'') + '">' + trimItems(o.items,45) + '</span>',
      parseInt(o.total_qty||0,10),
      '<span class="text-danger font-weight-bold">' + fmtPeso(o.total_amt) + '</span>',
      '<span data-order="' + vts + '"><small class="text-muted">' + (voidRaw ? fmtDate(voidRaw) : '—') + '</small></span>'
    ]).draw(false).node();
    $(row).find('td').css({'white-space':'nowrap','overflow':'hidden','text-overflow':'ellipsis','max-width':'260px'});
    flashRow(row);
  }

  /* ── Add a new row to the Refunded DataTable ── */
  function injectRefundedRow(o) {
    var dt = $('#tbl-refunded').DataTable();
    var typeBadge = (o.status === 'partial_refund')
      ? '<span class="badge-partial"><i class="fas fa-adjust mr-1"></i>Partial</span>'
      : '<span class="badge-refunded"><i class="fas fa-rotate-left mr-1"></i>Full</span>';
    var ts  = Math.floor(new Date((o.created_at || '').replace(' ', 'T')).getTime() / 1000);
    var rts = o.refund_at ? Math.floor(new Date((o.refund_at || '').replace(' ', 'T')).getTime() / 1000) : 0;
    var row = dt.row.add([
      '<strong>#' + o.id + '</strong>',
      '<span data-order="' + ts + '"><small class="text-muted">' + fmtDate(o.created_at) + '</small></span>',
      '<span class="badge badge-secondary">#' + o.table_no + '</span>',
      typeBadge,
      '<span title="' + (o.items||'') + '">' + trimItems(o.items,40) + '</span>',
      '<s class="text-muted">' + fmtPeso(o.total_amt) + '</s>',
      '<span class="text-primary font-weight-bold">' + fmtPeso(o.refund_total) + '</span>',
      o.refund_reason || '—',
      o.processed_by  || '—',
      '<span data-order="' + rts + '"><small class="text-muted">' + (o.refund_at ? fmtDate(o.refund_at) : '—') + '</small></span>'
    ]).draw(false).node();
    $(row).find('td').css({'white-space':'nowrap','overflow':'hidden','text-overflow':'ellipsis','max-width':'260px'});
    flashRow(row);
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
    var url = POLL_URL
      + '&since='         + _snap.latestOrderId
      + '&sinceVoided='   + _snap.latestVoidedId
      + '&sinceRefunded=' + _snap.latestRefundedId;

    fetch(url, { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        setDot(true);

        /* ── Stamp "Updated HH:MM:SS" ── */
        var _utEl = document.getElementById('activeUpdatedTime');
        if (_utEl) _utEl.textContent = new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});

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

        /* ── Active Orders count badge — only update if no injections happened
             (injectActiveRow already increments it per new row) ── */
        if (pc !== _snap.pendingCount && injectedCount === 0) {
          var ab2 = tabBadge('pane-active'); if (ab2) ab2.textContent = pc;
        }

        /* ── New active orders injected into table ── */
        var newOrders = d.newOrders || [];
        // Sort ascending so oldest-new goes in first; newest ends up on top after DataTable re-sort
        newOrders.sort(function(a, b) { return a.id - b.id; });
        var injectedCount = 0;
        newOrders.forEach(function (o) {
          if (o.id > _snap.latestOrderId) {
            injectActiveRow(o);
            _snap.latestOrderId = Math.max(_snap.latestOrderId, o.id);
            injectedCount++;
          }
        });
        /* Only advance the cursor from d.latestOrderId if newOrders was empty
           (meaning there were truly no new orders, not that they were skipped) */
        if (newOrders.length === 0 && d.latestOrderId > _snap.latestOrderId) {
          _snap.latestOrderId = d.latestOrderId;
        }
        if (injectedCount > 0) {
          /* Show the inline "New order received" banner above the table */
          var _badge = document.getElementById('activeRtNewBadge');
          if (_badge) {
            var latestNew = newOrders[newOrders.length - 1];
            var badgeInner = _badge.querySelector('span');
            if (badgeInner && latestNew) {
              badgeInner.innerHTML =
                '<i class="fas fa-bolt mr-1" style="font-size:.72rem;"></i>' +
                (injectedCount > 1
                  ? injectedCount + ' new orders received — table updated'
                  : 'New order #' + latestNew.id + ' — Table #' + (latestNew.table_no || '—') +
                    ' · ' + fmtPeso(latestNew.total_amt) + ' — table updated');
            }
            _badge.style.display = 'block';
            clearTimeout(_badge._bT);
            _badge._bT = setTimeout(function () { _badge.style.display = 'none'; }, 7000);
          }
          /* Show the full new-order toast (same as SSE path) */
          var latestO = newOrders[newOrders.length - 1];
          if (latestO) {
            showNewOrderToast({ latestOrder: latestO });
          }
          /* Top banner */
          showBanner('🛎️ ' + injectedCount + ' new order(s) received', '#e91e8c');
        }

        /* ── New voided orders injected into Voided tab ── */
        var newVoided = d.newVoided || [];
        newVoided.forEach(function (o) {
          if (o.id > _snap.latestVoidedId) {
            injectVoidedRow(o);
            _snap.latestVoidedId = o.id;
          }
        });

        /* ── New refunded orders injected into Refunded tab ── */
        var newRefunded = d.newRefunded || [];
        newRefunded.forEach(function (o) {
          if (o.id > _snap.latestRefundedId) {
            injectRefundedRow(o);
            _snap.latestRefundedId = o.id;
          }
        });

        /* ── Orders that became voided/refunded — remove from Active tab ── */
        var removed = d.removedIds || [];
        removed.forEach(function (id) { removeActiveRow(id); });

        /* ── Toasts for changes ── */
        if (vc > _snap.voidedCount) {
          showBanner('⚠️ ' + (vc - _snap.voidedCount) + ' order(s) voided', '#e74c3c');
          if (newVoided.length) {
            var lv = newVoided[newVoided.length - 1];
            showVoidRtToast(lv.id, lv.table_no, lv.total_amt);
          }
        }
        if (rc > _snap.refundedCount) {
          showBanner('↩️ ' + (rc - _snap.refundedCount) + ' order(s) refunded', '#3b82f6');
          if (newRefunded.length) {
            var lr = newRefunded[newRefunded.length - 1];
            showRefundRtToast(lr.id, lr.table_no, lr.refund_total || lr.total_amt);
          }
        }
        /* ── Advance voided/refunded cursors ── */
        if (d.latestVoidedId   > _snap.latestVoidedId)   _snap.latestVoidedId   = d.latestVoidedId;
        if (d.latestRefundedId > _snap.latestRefundedId) _snap.latestRefundedId = d.latestRefundedId;

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
  function startPolling() {
    var _utEl = document.getElementById('activeUpdatedTime');
    if (_utEl) _utEl.textContent = new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
    setInterval(poll, POLL_MS);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startPolling);
  } else {
    startPolling();
  }

})();
</script>
<!-- ══ END real-time table updates ═══════════════════════════════════ -->

</body>
</html>