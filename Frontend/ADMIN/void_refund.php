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
                  <div class="stat-num" style="color:#e74c3c;" data-rt-stat="voidedCount"><?= $voidedCount ?></div>
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
                  <div class="stat-num" style="color:#e74c3c;" data-rt-stat="totalVoided">&#8369;<?= number_format($totalVoided, 2) ?></div>
                  <div class="stat-lbl">Total Amount Voided</div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 col-12 mb-3">
            <div class="card h-100 m-0">
              <div class="card-body vr-stat">
                <div class="icon-wrap" style="background:rgba(59,130,246,.12);color:#3b82f6;">
                  <i class="fas fa-receipt"></i>
                </div>
                <div>
                  <div class="stat-num" style="color:#3b82f6;" data-rt-stat="refundedCount"><?= $refundedCount ?></div>
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
                  <div class="stat-num" style="color:#3b82f6;" data-rt-stat="totalRefunded">&#8369;<?= number_format($totalRefunded, 2) ?></div>
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
                  <i class="fas fa-rotate-left mr-1" style="color:#3b82f6"></i><i class="fas fa-check-circle mr-1" style="color:#3b82f6;font-size:.7rem;vertical-align:middle;"></i> Refunded
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
                              <span class="badge-refunded"><i class="fas fa-check-circle mr-1"></i>Full Refund</span>
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
  var dt     = $('#tbl-active').DataTable();
  var needle = '>#' + orderId + '<';
  dt.rows(function(i, data) {
    return typeof data[0] === 'string' && data[0].indexOf(needle) !== -1;
  }).remove().draw(false);

  // Also update the Active Orders tab badge count
  var badge = document.querySelector('#tab-active .badge');
  if (badge) {
    var cur = parseInt(badge.textContent, 10);
    if (!isNaN(cur) && cur > 0) badge.textContent = cur - 1;
  }
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
      order:        [[0, 'desc']],
      searching:    false,
      lengthChange: false,
      pageLength:   10,
      language:     { emptyTable: 'No active orders.' },
      columnDefs: [
        { targets: 0, type: 'num', render: function(data, type) {
            if (type === 'sort' || type === 'type') {
              var m = String(data).match(/\d+/);
              return m ? parseInt(m[0], 10) : 0;
            }
            return data;
          }
        },
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


<script>
/* ══ empress-realtime: void_refund.php — polling via ?rt=1 ══ */
(function(){
  'use strict';

  var POLL_URL      = window.location.pathname + '?rt=1';
  var POLL_INTERVAL = 10000; // 10 s

  // Seed cursors from PHP so we never show stale flash on first load
  var _sinceActive   = <?= (int)$view->maxActiveOrderId ?>;
  var _sinceVoided   = <?= (int)$view->maxVoidedId ?>;
  var _sinceRefunded = <?= (int)$view->maxRefundedId ?>;

  /* ── helpers ── */
  function esc(s){
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function peso(v){ return '₱'+parseFloat(v||0).toLocaleString('en',{minimumFractionDigits:2,maximumFractionDigits:2}); }
  function fmtDate(ts){
    if(!ts) return '—';
    var d=new Date(ts.replace(' ','T'));
    return isNaN(d)?ts:d.toLocaleDateString('en-PH',{month:'short',day:'2-digit',year:'numeric'})+' '+d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',hour12:true});
  }
  function flashRow(tr, colour){
    tr.style.transition='none';
    tr.style.backgroundColor=colour||'rgba(233,30,140,.18)';
    setTimeout(function(){ tr.style.transition='background-color 1.6s ease'; tr.style.backgroundColor=''; },80);
  }
  function setDot(ok){
    document.querySelectorAll('.rt-live-dot').forEach(function(d){
      d.style.background=ok?'#22c55e':'#ef4444';
      d.title=ok?'Live — connected':'Live — reconnecting…';
    });
  }

  /* ── stat cards ── */
  function updateStats(d){
    var cards={
      voidedCount:   document.querySelector('[data-rt-stat="voidedCount"]'),
      totalVoided:   document.querySelector('[data-rt-stat="totalVoided"]'),
      refundedCount: document.querySelector('[data-rt-stat="refundedCount"]'),
      totalRefunded: document.querySelector('[data-rt-stat="totalRefunded"]')
    };
    if(cards.voidedCount)   cards.voidedCount.textContent   = d.voidedCount;
    if(cards.totalVoided)   cards.totalVoided.textContent   = peso(d.totalVoided);
    if(cards.refundedCount) cards.refundedCount.textContent = d.refundedCount;
    if(cards.totalRefunded) cards.totalRefunded.textContent = peso(d.totalRefunded);

    // Tab badges
    var bVoided   = document.querySelector('#tab-voided .badge');
    var bRefunded = document.querySelector('#tab-refunded .badge');
    if(bVoided)   bVoided.textContent   = d.voidedCount;
    if(bRefunded) bRefunded.textContent = d.refundedCount;
  }

  /* ── prepend new rows to Active DataTable ── */
  function applyNewOrders(orders){
    if(!orders||!orders.length) return;
    var dt=$.fn.DataTable.isDataTable('#tbl-active')?$('#tbl-active').DataTable():null;
    if(!dt) return;
    orders.forEach(function(o){
      var ts=parseInt(new Date(o.created_at.replace(' ','T')).getTime()/1000,10)||0;
      var dateHtml='<span data-order="'+ts+'"><small class="text-muted">'+esc(fmtDate(o.created_at))+'</small></span>';
      var rowNode=dt.row.add([
        '<strong>#'+o.id+'</strong>',
        dateHtml,
        '<span class="badge badge-secondary">#'+esc(o.table_no)+'</span>',
        esc(o.cashier_name||'N/A'),
        esc(o.items||'—'),
        '<span class="badge-done"><i class="fas fa-check mr-1"></i>'+esc(o.status||'active')+'</span>',
        '<strong>'+peso(o.total_amt)+'</strong>',
        '<div class="d-flex" style="gap:6px;">'
          +'<button class="btn-void" onclick="openVoidModal('+o.id+',\''+esc(o.table_no)+'\','+parseFloat(o.total_amt)+',\''+esc(o.items||'')+'\')">'
          +'<i class="fas fa-ban mr-1"></i>Void</button>'
          +'<button class="btn-refund" onclick="openRefundModal('+o.id+',\''+esc(o.table_no)+'\','+parseFloat(o.total_amt)+',\''+esc(o.items||'')+'\')">'
          +'<i class="fas fa-rotate-left mr-1"></i>Refund</button></div>'
      ]).node();
      dt.draw(false);
      flashRow(rowNode,'rgba(40,167,69,.15)');
    });
    // update Active tab badge
    var bActive=document.querySelector('#tab-active .badge');
    if(bActive) bActive.textContent=parseInt(bActive.textContent||'0',10)+orders.length;
  }

  /* ── remove voided/refunded orders from Active DataTable ── */
  function applyRemovedIds(ids){
    if(!ids||!ids.length) return;
    var dt=$.fn.DataTable.isDataTable('#tbl-active')?$('#tbl-active').DataTable():null;
    if(!dt) return;
    var removed=0;
    ids.forEach(function(id){
      var needle='>#'+id+'<';
      var matched=dt.rows(function(i,data){
        return typeof data[0]==='string'&&data[0].indexOf(needle)!==-1;
      });
      if(matched.count()){ matched.remove(); removed++; }
    });
    if(removed){
      dt.draw(false);
      var bActive=document.querySelector('#tab-active .badge');
      if(bActive){
        var cur=parseInt(bActive.textContent||'0',10)-removed;
        bActive.textContent=Math.max(0,cur);
      }
    }
  }

  /* ── prepend new voided rows to Voided DataTable ── */
  function applyNewVoided(rows){
    if(!rows||!rows.length) return;
    var dt=$.fn.DataTable.isDataTable('#tbl-voided')?$('#tbl-voided').DataTable():null;
    if(!dt) return;
    rows.forEach(function(o){
      var ts=parseInt(new Date(o.created_at.replace(' ','T')).getTime()/1000,10)||0;
      var dateHtml='<span data-order="'+ts+'"><small class="text-muted">'+esc(fmtDate(o.created_at))+'</small></span>';
      // Void date — use created_at as proxy if no voided_at column
      var voidTs=o.voided_at||o.updated_at||o.created_at;
      var vts=parseInt(new Date(voidTs.replace(' ','T')).getTime()/1000,10)||0;
      var voidDateHtml='<span data-order="'+vts+'"><small class="text-muted">'+esc(fmtDate(voidTs))+'</small></span>';
      var rowNode=dt.row.add([
        '<strong>#'+o.id+'</strong>',
        dateHtml,
        '<span class="badge badge-secondary">#'+esc(o.table_no)+'</span>',
        esc(o.cashier_name||'N/A'),
        esc(o.items||'—'),
        o.total_qty||0,
        '<span class="text-danger font-weight-bold">'+peso(o.total_amt)+'</span>',
        voidDateHtml
      ]).node();
      dt.draw(false);
      flashRow(rowNode,'rgba(239,68,68,.15)');
    });
  }

  /* ── prepend new refunded rows to Refunded DataTable ── */
  function applyNewRefunded(rows){
    if(!rows||!rows.length) return;
    var dt=$.fn.DataTable.isDataTable('#tbl-refunded')?$('#tbl-refunded').DataTable():null;
    if(!dt) return;
    rows.forEach(function(o){
      var ts=parseInt(new Date(o.created_at.replace(' ','T')).getTime()/1000,10)||0;
      var dateHtml='<span data-order="'+ts+'"><small class="text-muted">'+esc(fmtDate(o.created_at))+'</small></span>';
      var rts=parseInt(new Date((o.refund_at||o.created_at).replace(' ','T')).getTime()/1000,10)||0;
      var refundDateHtml='<span data-order="'+rts+'"><small class="text-muted">'+esc(fmtDate(o.refund_at||o.created_at))+'</small></span>';
      var typeBadge=o.status==='partial_refund'
        ?'<span class="badge-partial"><i class="fas fa-adjust mr-1"></i>Partial</span>'
        :'<span class="badge-refunded"><i class="fas fa-rotate-left mr-1"></i>Full</span>';
      var rowNode=dt.row.add([
        '<strong>#'+o.id+'</strong>',
        dateHtml,
        '<span class="badge badge-secondary">#'+esc(o.table_no)+'</span>',
        typeBadge,
        esc(o.items||'—'),
        '<s class="text-muted">'+peso(o.total_amt)+'</s>',
        '<span class="text-primary font-weight-bold">'+peso(o.refund_total||0)+'</span>',
        esc(o.refund_reason||'—'),
        esc(o.processed_by||'—'),
        refundDateHtml
      ]).node();
      dt.draw(false);
      flashRow(rowNode,'rgba(59,130,246,.15)');
    });
  }

  /* ── main poll ── */
  function poll(){
    var url=POLL_URL
      +'&since='+_sinceActive
      +'&sinceVoided='+_sinceVoided
      +'&sinceRefunded='+_sinceRefunded;
    fetch(url,{cache:'no-store',credentials:'same-origin'})
      .then(function(r){
        if(!r.ok) throw new Error('HTTP '+r.status);
        return r.json();
      })
      .then(function(d){
        setDot(true);

        // Update cursor positions FIRST so we never re-fetch the same rows
        if(d.latestOrderId    > _sinceActive)   _sinceActive   = d.latestOrderId;
        if(d.latestVoidedId   > _sinceVoided)   _sinceVoided   = d.latestVoidedId;
        if(d.latestRefundedId > _sinceRefunded) _sinceRefunded = d.latestRefundedId;

        updateStats(d);
        if(d.newOrders   &&d.newOrders.length)   applyNewOrders(d.newOrders);
        if(d.removedIds  &&d.removedIds.length)   applyRemovedIds(d.removedIds);
        if(d.newVoided   &&d.newVoided.length)    applyNewVoided(d.newVoided);
        if(d.newRefunded &&d.newRefunded.length)  applyNewRefunded(d.newRefunded);
      })
      .catch(function(){ setDot(false); });
  }

  $(function(){
    setInterval(poll, POLL_INTERVAL);
    // Run one poll shortly after page load to catch anything that arrived
    // between PHP render and JS boot
    setTimeout(poll, 2000);
  });
})();
</script>
<!-- ══ END void_refund realtime polling ══════════════════════════════ -->

<!-- (dead report.php SSE block removed) -->

<!-- ══ TOP SELLING ITEMS — DataTable init + Real-Time Polling ════════ -->
<script>
// Initialize DataTable for Top Selling Items
$(function(){
  $('#topItemsTable').DataTable({
    responsive: true, lengthChange: false, autoWidth: false,
    order: [[4, 'desc']],
    buttons: ['copy','csv','excel','pdf','print','colvis']
  }).buttons().container().appendTo('#topItemsTable_wrapper .col-md-6:eq(0)');
});
</script>
<script>
(function(){
  'use strict';

  var POLL_INTERVAL = 30000; // refresh every 30 seconds

  function buildUrl() {
    var params = new URLSearchParams(window.location.search);
    params.set('ajax', 'topitems');
    return window.location.pathname + '?' + params.toString();
  }

  function setDot(ok) {
    var dot = document.querySelector('.top-items-live-dot');
    if (!dot) return;
    dot.style.background = ok ? '#22c55e' : '#ef4444';
    dot.title = ok ? 'Live — connected' : 'Live — reconnecting…';
  }

  function fmtNum(n, decimals) {
    return parseFloat(n || 0).toLocaleString('en', {minimumFractionDigits: decimals, maximumFractionDigits: decimals});
  }

  function rankBadge(rank) {
    if (rank === 1) return '<span class="badge" style="background:#f4c542;color:#333;">🥇 1</span>';
    if (rank === 2) return '<span class="badge" style="background:#b0b8c1;color:#fff;">🥈 2</span>';
    if (rank === 3) return '<span class="badge" style="background:#cd7f32;color:#fff;">🥉 3</span>';
    return '<span class="badge badge-secondary">' + rank + '</span>';
  }

  function flashRow(tr, color) {
    tr.style.transition = 'none';
    tr.style.backgroundColor = color;
    setTimeout(function(){
      tr.style.transition = 'background-color 1.6s ease';
      tr.style.backgroundColor = '';
    }, 80);
  }

  function flashCell(td, color) {
    td.style.transition = 'none';
    td.style.backgroundColor = color;
    setTimeout(function(){
      td.style.transition = 'background-color 1.6s ease';
      td.style.backgroundColor = '';
    }, 80);
  }

  function renderRows(items) {
    var tbody = document.getElementById('topItemsTbody');
    var tsEl  = document.getElementById('topItemsLastUpdated');
    if (!tbody) return;

    if (!items || !items.length) {
      if ($.fn.DataTable.isDataTable('#topItemsTable')) {
        $('#topItemsTable').DataTable().destroy();
      }
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No sales data yet.</td></tr>';
      return;
    }

    // Snapshot prev state from DOM before destroy
    var prevQtyMap  = {};
    var prevRankMap = {};
    tbody.querySelectorAll('tr[data-item-name]').forEach(function(tr, idx){
      var name = tr.getAttribute('data-item-name');
      prevQtyMap[name]  = parseInt(tr.getAttribute('data-item-qty') || '0', 10);
      prevRankMap[name] = idx;
    });

    // Destroy DataTables so we can safely rewrite tbody
    if ($.fn.DataTable.isDataTable('#topItemsTable')) {
      $('#topItemsTable').DataTable().destroy();
    }

    // Build new rows
    var html = items.map(function(it, idx){
      var qty     = parseInt(it.qty_sold, 10);
      var escaped = it.name.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
      return '<tr data-item-name="' + escaped + '" data-item-qty="' + qty + '">'
        + '<td>' + rankBadge(idx + 1) + '</td>'
        + '<td>' + escaped + '</td>'
        + '<td>' + (it.category || '—') + '</td>'
        + '<td>' + fmtNum(it.price, 2) + '</td>'
        + '<td class="qty-cell"><strong>' + qty + '</strong></td>'
        + '<td>₱' + fmtNum(it.revenue, 2) + '</td>'
        + '</tr>';
    }).join('');

    tbody.innerHTML = html;

    // Apply highlights after DOM is updated
    tbody.querySelectorAll('tr[data-item-name]').forEach(function(tr, idx){
      var name   = tr.getAttribute('data-item-name');
      var qty    = parseInt(tr.getAttribute('data-item-qty'), 10);
      var wasNew = !(name in prevQtyMap);
      var qtyUp  = !wasNew && qty > (prevQtyMap[name] || 0);
      var rankChg = !wasNew && prevRankMap[name] !== idx;

      if (wasNew) {
        flashRow(tr, 'rgba(233,30,140,.18)');
      } else if (qtyUp) {
        flashRow(tr, 'rgba(40,167,69,.10)');
        var qtyTd = tr.querySelector('.qty-cell');
        if (qtyTd) flashCell(qtyTd, 'rgba(40,167,69,.45)');
      } else if (rankChg) {
        flashRow(tr, 'rgba(255,193,7,.25)');
      }
    });

    // Reinitialize DataTables
    $('#topItemsTable').DataTable({
      responsive: true, lengthChange: false, autoWidth: false,
      order: [[4, 'desc']],
      buttons: ['copy','csv','excel','pdf','print','colvis']
    }).buttons().container().appendTo('#topItemsTable_wrapper .col-md-6:eq(0)');

    if (tsEl) {
      var now = new Date();
      tsEl.textContent = 'Updated ' + now.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
    }
  }

  function poll() {
    fetch(buildUrl(), {cache: 'no-store'})
      .then(function(res){ return res.json(); })
      .then(function(data){
        setDot(true);
        renderRows(data.items || []);
      })
      .catch(function(){
        setDot(false);
      });
  }

  // Start polling after DOM + DataTables are ready
  $(function(){
    setInterval(poll, POLL_INTERVAL);
  });
})();
</script>
<!-- ══ END top items real-time ════════════════════════════════════════ -->
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

<!-- ── New Order Toast Notification ──────────────────────────── -->
<div id="newOrderToast" style="
  display:none; position:fixed; bottom:28px; right:24px; z-index:99998;
  background: linear-gradient(135deg,#e91e8c,#c2185b);
  border-radius:14px; padding:0; min-width:280px; max-width:340px;
  box-shadow:0 12px 36px rgba(233,30,140,.35), 0 4px 12px rgba(0,0,0,.2);
  overflow:hidden; font-family:inherit;">

  <!-- Accent bar -->
  <div style="height:3px; background:rgba(255,255,255,.35);"></div>

  <div style="padding:14px 16px;">
    <!-- Header row -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
      <div style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:1.1rem;">🔔</span>
        <span style="color:#fff; font-weight:700; font-size:.92rem; letter-spacing:.01em;">New Order Received!</span>
      </div>
      <button onclick="document.getElementById('newOrderToast').style.display='none'"
        style="background:rgba(255,255,255,.2); border:none; border-radius:50%; width:22px; height:22px;
               color:#fff; font-size:.85rem; cursor:pointer; display:flex; align-items:center;
               justify-content:center; line-height:1; padding:0;">&times;</button>
    </div>
    <!-- Message -->
    <div id="noToastMsg" style="color:rgba(255,255,255,.92); font-size:.85rem; margin-bottom:10px;
         padding-left:2px;">Table — — ₱0.00</div>
    <!-- Badges -->
    <div style="display:flex; gap:8px;">
      <span id="noToastOrderBadge" style="
        background:rgba(255,255,255,.22); color:#fff; font-size:.75rem; font-weight:700;
        padding:2px 10px; border-radius:20px;">#—</span>
      <span id="noToastTimeBadge" style="
        background:rgba(255,255,255,.22); color:#fff; font-size:.75rem; font-weight:700;
        padding:2px 10px; border-radius:20px;">—:—</span>
    </div>
  </div>
</div>

<style>
@keyframes toastSlideIn {
  from { opacity:0; transform: translateX(60px) scale(.95); }
  to   { opacity:1; transform: translateX(0)    scale(1);   }
}
</style>



</body>
</html>