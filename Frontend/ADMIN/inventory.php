<?php
session_name('ADMIN_SESSION');
session_start();
if (!isset($_SESSION['user']) || $_SESSION['position'] !== 'admin') {
    header("Location:../../lockscreen.html");
    exit();
}


// DB: operlytics | table: ingredients
// columns: id, name, unit, stock_qty, low_stock_threshold, expiry_date, created_at, updated_at
// Migration: ALTER TABLE ingredients ADD COLUMN expiry_date DATE NULL AFTER low_stock_threshold;
require_once '../../Backend/conn.php';

// Format quantity: trim trailing zeros, max 2 decimal places
function fmtQty($val) {
    return rtrim(rtrim(number_format((float)$val, 2, '.', ''), '0'), '.');
}

// ── Stats — single query instead of four separate ones ────────
$stats_row = $conn->query(
    "SELECT
        COUNT(*) AS total,
        SUM(stock_qty > low_stock_threshold) AS in_stock,
        SUM(stock_qty > 0 AND stock_qty <= low_stock_threshold) AS low_stock,
        SUM(stock_qty = 0) AS out_stock,
        SUM(expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE()) AS expiring_soon,
        SUM(expiry_date IS NOT NULL AND expiry_date < CURDATE()) AS expired
     FROM ingredients"
)->fetch_assoc();

$total         = (int)($stats_row['total']         ?? 0);
$in_stock      = (int)($stats_row['in_stock']      ?? 0);
$low_stock     = (int)($stats_row['low_stock']     ?? 0);
$out_stock     = (int)($stats_row['out_stock']     ?? 0);
$expiring_soon = (int)($stats_row['expiring_soon'] ?? 0);
$expired       = (int)($stats_row['expired']       ?? 0);

// ── Fetch all ingredients ─────────────────────────────────────
$items = [];
$res = $conn->query("SELECT * FROM ingredients ORDER BY name ASC");
while ($row = $res->fetch_assoc()) {
    $items[] = $row;
}

// ── Low stock alert list (stock <= threshold, not zero) ───────
$low_alerts = [];
$res2 = $conn->query("SELECT name, stock_qty, unit FROM ingredients WHERE stock_qty <= low_stock_threshold ORDER BY stock_qty ASC LIMIT 8");
while ($row = $res2->fetch_assoc()) {
    $low_alerts[] = $row;
}

// ── Expiring soon list (within 30 days, not yet expired) ──────
$expiry_alerts = [];
$res3 = $conn->query("SELECT name, stock_qty, unit, expiry_date, DATEDIFF(expiry_date, CURDATE()) AS days_left FROM ingredients WHERE expiry_date IS NOT NULL AND expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY expiry_date ASC LIMIT 10");
while ($row = $res3->fetch_assoc()) {
    $expiry_alerts[] = $row;
}

// ── Already expired list ──────────────────────────────────────
$expired_items = [];
$res4 = $conn->query("SELECT name, stock_qty, unit, expiry_date FROM ingredients WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE() ORDER BY expiry_date ASC");
while ($row = $res4->fetch_assoc()) {
    $expired_items[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OPERLYTICS | Inventory Tracking</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="../plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
    <link rel="stylesheet" href="../plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">
    <link rel="stylesheet" href="../dist/css/empress-cafe-theme.css">
<style>
@keyframes rtPulse{0%{box-shadow:0 0 0 0 rgba(34,197,94,.55)}70%{box-shadow:0 0 0 7px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
</style>
</head>

<style>
    body, .main-header.navbar { transition: background-color 0.5s ease, color 0.5s ease; }
    #darkModeToggle { transition: box-shadow 0.3s ease; }
    #darkModeToggle.clicked { box-shadow: 0 0 15px rgba(255,255,255,0.8); }

    

    /* FIX: Table hover visible in light mode */
    body:not(.dark-mode) .table tbody tr:hover { background-color: rgba(233,30,140,0.08) !important; }
    body:not(.dark-mode) .table tbody tr:hover td { color: #212529 !important; }

    /* FIX: inventory table dark rows invisible in light mode */
    body:not(.dark-mode) #inventoryTable,
    body:not(.dark-mode) #inventoryTable th,
    body:not(.dark-mode) #inventoryTable td {
      color: #212529 !important;
    }
    body:not(.dark-mode) #inventoryTable tbody tr:hover td {
      background-color: rgba(233,30,140,0.08) !important;
      color: #212529 !important;
    }
    body:not(.dark-mode) #inventoryTable thead th {
      background-color: #f4f6f9 !important;
      color: #212529 !important;
    }
    /* Also fix the table-dark class globally in light mode */
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

<body class="hold-transition dark-mode sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">

    <!-- ── Navbar ─────────────────────────────────────────────── -->
    <nav class="main-header navbar navbar-expand navbar-dark">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="index2.php" class="nav-link">Home</a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" data-widget="fullscreen"><i class="fas fa-expand-arrows-alt"></i></a>
            </li>
            <li class="nav-item d-flex align-items-center px-2" title="Real-time: connected" style="font-size:.72rem;font-weight:600;color:#6c757d;">
        <span class="rt-live-dot" style="width:8px;height:8px;background:#22c55e;border-radius:50%;display:inline-block;margin-right:5px;box-shadow:0 0 0 0 rgba(34,197,94,.5);animation:rtPulse 1.8s ease infinite;" title="Live data connected"></span>
        <span class="d-none d-sm-inline rt-live-label">Live</span>
      </li>
      <li class="nav-item">
                <a class="nav-link" id="darkModeToggle" href="#" role="button">
                    <i class="fas fa-moon"></i>
                </a>
            </li>
        </ul>
    </nav>

    <!-- ── Sidebar ────────────────────────────────────────────── -->
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
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview">
                    <li class="nav-item">
                        <a href="index2.php" class="nav-link">
                            <i class="nav-icon fas fa-tachometer-alt"></i><p>Overview</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="./menu-management.php" class="nav-link">
                            <i class="nav-icon fas fa-utensils"></i><p>Menu Management</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="./inventory.php" class="nav-link active">
                            <i class="nav-icon fas fa-boxes"></i><p>Inventory Tracking</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="./suppliers.php" class="nav-link">
                            <i class="nav-icon fas fa-truck"></i><p>Supplier Info</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="./staff-list.php" class="nav-link">
                            <i class="far fa-user nav-icon"></i><p>Staff List</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="sale_revenue.php" class="nav-link">
                            <i class="nav-icon fas fa-chart-line"></i><p>Sales & Revenue</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="report.php" class="nav-link">
                            <i class="nav-icon fas fa-file-alt"></i><p>Reports</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="./void_refund.php" class="nav-link">
                            <i class="nav-icon fas fa-undo-alt"></i><p>Void &amp; Refund</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="./settings.php" class="nav-link">
                            <i class="nav-icon fas fa-cog"></i><p>Settings</p>
                        </a>
                    </li>
                    <li class="nav-item mt-auto">
                        <a href="../../Backend/logout.php" class="nav-link">
                            <i class="nav-icon fas fa-sign-out-alt"></i><p>Log Out</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- ── Content Wrapper ───────────────────────────────────── -->
    <div class="content-wrapper">

        <!-- Flash messages -->
        <?php if (!empty($_SESSION['success'])): ?>
          <div class="alert alert-success alert-dismissible fade show mx-3 mt-3" role="alert">
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
          </div>
          <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
          <div class="alert alert-danger alert-dismissible fade show mx-3 mt-3" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
          </div>
          <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">Inventory Tracking</h1></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index2.php">Home</a></li>
                            <li class="breadcrumb-item active">Inventory</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">

                <!-- ── Info Boxes ─────────────────────────────── -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="fas fa-boxes"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Products</span>
                                <span class="info-box-number"><?= $total ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">In Stock</span>
                                <span class="info-box-number"><?= $in_stock ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Low Stock</span>
                                <span class="info-box-number"><?= $low_stock ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-danger"><i class="fas fa-times"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Out of Stock</span>
                                <span class="info-box-number"><?= $out_stock ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Expiry Info Boxes ──────────────────────── -->
                <div class="row mb-4">
                    <div class="col-lg-6 col-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-orange"><i class="fas fa-hourglass-half"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Expiring Soon <small class="text-muted">(≤ 30 days)</small></span>
                                <span class="info-box-number"><?= $expiring_soon ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-dark"><i class="fas fa-skull-crossbones"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Expired</span>
                                <span class="info-box-number"><?= $expired ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card card-warning">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-bell mr-1"></i> Low Stock Alerts</h3>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php if (empty($low_alerts)): ?>
                                        <li class="list-group-item text-muted text-center py-3">
                                            <i class="fas fa-check-circle text-success mr-1"></i> All ingredients are sufficiently stocked.
                                        </li>
                                    <?php else: ?>
                                        <?php foreach ($low_alerts as $alert):
                                            $badgeClass = $alert['stock_qty'] == 0 ? 'badge-danger' : 'badge-warning';
                                        ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($alert['name']) ?>
                                            <span class="badge <?= $badgeClass ?> badge-pill">
                                                <?= fmtQty($alert['stock_qty']) ?> <?= htmlspecialchars($alert['unit']) ?>
                                            </span>
                                        </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card card-info">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-bolt mr-1"></i> Quick Actions</h3>
                            </div>
                            <div class="card-body">
                                <div class="btn-group-vertical d-block">
                                    <button class="btn btn-secondary mb-2" id="exportCsvBtn">
                                        <i class="fas fa-file-export mr-1"></i> Export Inventory
                                    </button>
                                    <button class="btn btn-success mb-2" data-toggle="modal" data-target="#trendsModal">
                                        <i class="fas fa-chart-line mr-1"></i> View Trends
                                    </button>
                                    <button class="btn btn-warning mb-2" data-toggle="modal" data-target="#setAlertsModal">
                                        <i class="fas fa-bell mr-1"></i> Set Alerts
                                    </button>
                                    <button class="btn btn-orange mb-2" data-toggle="modal" data-target="#expiringModal" style="background:#fd7e14;border-color:#fd7e14;color:#fff;">
                                        <i class="fas fa-hourglass-half mr-1"></i> View Expiring Soon
                                        <?php if ($expiring_soon > 0): ?>
                                            <span class="badge badge-light ml-1"><?= $expiring_soon ?></span>
                                        <?php endif; ?>
                                    </button>
                                    <button class="btn btn-dark" data-toggle="modal" data-target="#expiredModal">
                                        <i class="fas fa-skull-crossbones mr-1"></i> View Expired
                                        <?php if ($expired > 0): ?>
                                            <span class="badge badge-danger ml-1"><?= $expired ?></span>
                                        <?php endif; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Inventory Table ─────────────────────────── -->
                <div class="card card-dark">
                    <div class="card-header">
                        <h3 class="card-title">Inventory Items</h3>
                        <div class="card-tools">
                            <button class="btn btn-tool" data-card-widget="maximize">
                                <i class="fas fa-expand"></i>
                            </button>
                            <a href="#" class="btn btn-sm btn-success" data-toggle="modal" data-target="#addInventoryModal">
                                <i class="fas fa-plus"></i> Add Product
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        <table id="inventoryTable" class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Ingredient Name</th>
                                    <th>Unit</th>
                                    <th>Stock Qty</th>
                                    <th>Low Stock Threshold</th>
                                    <th>Status</th>
                                    <th>Expiry Date</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item):
                                    if ($item['stock_qty'] == 0) {
                                        $statusBadge = 'badge-danger';
                                        $statusLabel = 'Out of Stock';
                                    } elseif ($item['stock_qty'] <= $item['low_stock_threshold']) {
                                        $statusBadge = 'badge-warning';
                                        $statusLabel = 'Low Stock';
                                    } else {
                                        $statusBadge = 'badge-success';
                                        $statusLabel = 'Available';
                                    }
                                    // Expiry logic
                                    $expiryBadge = '';
                                    $expiryLabel = '';
                                    if (!empty($item['expiry_date'])) {
                                        $today     = new DateTime('today');
                                        $expiryDt  = new DateTime($item['expiry_date']);
                                        $daysLeft  = (int)$today->diff($expiryDt)->format('%r%a');
                                        if ($daysLeft < 0) {
                                            $expiryBadge = 'badge-dark';
                                            $expiryLabel = 'Expired';
                                        } elseif ($daysLeft <= 7) {
                                            $expiryBadge = 'badge-danger';
                                            $expiryLabel = $daysLeft === 0 ? 'Expires Today' : "Expires in {$daysLeft}d";
                                        } elseif ($daysLeft <= 30) {
                                            $expiryBadge = 'badge-warning';
                                            $expiryLabel = "Expires in {$daysLeft}d";
                                        } else {
                                            $expiryBadge = 'badge-success';
                                            $expiryLabel = date('M d, Y', strtotime($item['expiry_date']));
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><?= $item['id'] ?></td>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= htmlspecialchars($item['unit']) ?></td>
                                    <td>
                                        <span class="badge <?= $statusBadge ?>">
                                            <?= fmtQty($item['stock_qty']) ?>
                                        </span>
                                    </td>
                                    <td><?= fmtQty($item['low_stock_threshold']) ?></td>
                                    <td><span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span></td>
                                    <td>
                                        <?php if ($expiryLabel): ?>
                                            <span class="badge <?= $expiryBadge ?>"><?= htmlspecialchars($expiryLabel) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= $item['updated_at'] ?? $item['created_at'] ?></small></td>
                                    <td>
                                        <!-- Edit button -->
                                        <button class="btn btn-sm btn-warning"
                                                data-toggle="modal"
                                                data-target="#editInventoryModal"
                                                data-id="<?= $item['id'] ?>"
                                                data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>"
                                                data-unit="<?= htmlspecialchars($item['unit'], ENT_QUOTES) ?>"
                                                data-stock="<?= fmtQty($item['stock_qty']) ?>"
                                                data-threshold="<?= fmtQty($item['low_stock_threshold']) ?>"
                                                data-expiry="<?= htmlspecialchars($item['expiry_date'] ?? '', ENT_QUOTES) ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <!-- Delete — opens confirm modal -->
                                        <button type="button" class="btn btn-sm btn-danger"
                                                onclick="confirmDeleteIngredient(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </section>
    </div><!-- /.content-wrapper -->


    <!-- ══════════════════════════════════════════════════════════
         ADD INGREDIENT MODAL
    ═══════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="addInventoryModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>Add Ingredient</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <form action="../../Backend/inventory_process.php" method="POST">
                    <div class="modal-body">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Ingredient Name</label>
                                    <input type="text" name="name" class="form-control" placeholder="e.g. Chicken Breast" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Unit</label>
                                    <select name="unit" class="form-control" required>
                                        <option value="">-- Select Unit --</option>
                                        <option value="g">g (grams)</option>
                                        <option value="kg">kg (kilograms)</option>
                                        <option value="ml">ml (milliliters)</option>
                                        <option value="L">L (liters)</option>
                                        <option value="pcs">pcs (pieces)</option>
                                        <option value="tbsp">tbsp (tablespoon)</option>
                                        <option value="tsp">tsp (teaspoon)</option>
                                        <option value="cups">cups</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Stock Quantity</label>
                                    <input type="number" name="stock_qty" class="form-control" step="0.01" min="0" placeholder="e.g. 100" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Low Stock Threshold</label>
                                    <input type="number" name="low_stock_threshold" class="form-control" step="0.01" min="0" placeholder="e.g. 20">
                                    <small class="text-muted">Alert will trigger when stock falls at or below this value.</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-calendar-times mr-1 text-warning"></i> Expiry Date <small class="text-muted">(optional)</small></label>
                                    <input type="date" name="expiry_date" class="form-control" min="<?= date('Y-m-d') ?>">
                                    <small class="text-muted">Leave blank if this ingredient has no expiry.</small>
                                </div>
                            </div>
                        </div>

                    </div><!-- /.modal-body -->

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i>Close
                        </button>
                        <button type="submit" name="save_ingredient" class="btn btn-success">
                            <i class="fas fa-save mr-1"></i>Save Ingredient
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════════
         EDIT INGREDIENT MODAL
    ═══════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="editInventoryModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Ingredient</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <form action="../../Backend/inventory_process.php" method="POST">
                    <input type="hidden" name="update_ingredient" value="1">
                    <input type="hidden" name="id" id="editIngId">

                    <div class="modal-body">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Ingredient Name</label>
                                    <input type="text" name="name" id="editIngName" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Unit</label>
                                    <select name="unit" id="editIngUnit" class="form-control" required>
                                        <option value="">-- Select Unit --</option>
                                        <option value="g">g (grams)</option>
                                        <option value="kg">kg (kilograms)</option>
                                        <option value="ml">ml (milliliters)</option>
                                        <option value="L">L (liters)</option>
                                        <option value="pcs">pcs (pieces)</option>
                                        <option value="tbsp">tbsp (tablespoon)</option>
                                        <option value="tsp">tsp (teaspoon)</option>
                                        <option value="cups">cups</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Stock Quantity</label>
                                    <input type="number" name="stock_qty" id="editIngStock" class="form-control" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Low Stock Threshold</label>
                                    <input type="number" name="low_stock_threshold" id="editIngThreshold" class="form-control" step="0.01" min="0">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-calendar-times mr-1 text-warning"></i> Expiry Date <small class="text-muted">(optional)</small></label>
                                    <input type="date" name="expiry_date" id="editIngExpiry" class="form-control">
                                    <small class="text-muted">Leave blank if this ingredient has no expiry.</small>
                                </div>
                            </div>
                        </div>

                    </div><!-- /.modal-body -->

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i>Close
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save mr-1"></i>Update Ingredient
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════════
         BULK RESTOCK MODAL  (live rows from DB)
    ═══════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="bulkRestockModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-truck mr-2"></i>Bulk Restock</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <form action="../../Backend/inventory_process.php" method="POST">
                    <input type="hidden" name="bulk_restock" value="1">

                    <div class="modal-body">
                        <p class="text-muted">Check items to restock and enter the quantity to <strong>add</strong>:</p>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Ingredient</th>
                                    <th>Current Stock</th>
                                    <th>Unit</th>
                                    <th>Add Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $ri): ?>
                                <tr>
                                    <td><input type="checkbox" name="restock_ids[]" value="<?= $ri['id'] ?>" class="restock-check"></td>
                                    <td><?= htmlspecialchars($ri['name']) ?></td>
                                    <td><?= fmtQty($ri['stock_qty']) ?></td>
                                    <td><?= htmlspecialchars($ri['unit']) ?></td>
                                    <td>
                                        <input type="number" name="restock_qty[<?= $ri['id'] ?>]"
                                               class="form-control restock-qty" min="0" step="0.01"
                                               style="width:100px;">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-truck mr-1"></i>Restock Selected
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════════
         EXPIRING SOON MODAL
    ═══════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="expiringModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background:#fd7e14;color:#fff;">
                    <h5 class="modal-title"><i class="fas fa-hourglass-half mr-2"></i>Ingredients Expiring Within 30 Days</h5>
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
                </div>
                <div class="modal-body p-0">
                    <?php if (empty($expiry_alerts)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                            No ingredients are expiring within the next 30 days.
                        </div>
                    <?php else: ?>
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Stock</th>
                                <th>Unit</th>
                                <th>Expiry Date</th>
                                <th>Days Left</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiry_alerts as $ea):
                                $dl = (int)$ea['days_left'];
                                $rowClass = $dl <= 7 ? 'table-danger' : 'table-warning';
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td><?= htmlspecialchars($ea['name']) ?></td>
                                <td><?= fmtQty($ea['stock_qty']) ?></td>
                                <td><?= htmlspecialchars($ea['unit']) ?></td>
                                <td><?= date('M d, Y', strtotime($ea['expiry_date'])) ?></td>
                                <td>
                                    <span class="badge <?= $dl <= 7 ? 'badge-danger' : 'badge-warning' ?>">
                                        <?= $dl === 0 ? 'Today' : "{$dl} day(s)" ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         EXPIRED ITEMS MODAL
    ═══════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="expiredModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-skull-crossbones mr-2"></i>Expired Ingredients</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body p-0">
                    <?php if (empty($expired_items)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                            No expired ingredients on record.
                        </div>
                    <?php else: ?>
                    <div class="alert alert-danger mx-3 mt-3 mb-0">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong><?= count($expired_items) ?> ingredient(s)</strong> have passed their expiry date. Please remove or replace them immediately.
                    </div>
                    <table class="table table-striped mb-0 mt-2">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Stock</th>
                                <th>Unit</th>
                                <th>Expired On</th>
                                <th>Days Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expired_items as $ei):
                                $overdue = (int)(new DateTime('today'))->diff(new DateTime($ei['expiry_date']))->format('%r%a') * -1;
                            ?>
                            <tr class="table-dark">
                                <td><?= htmlspecialchars($ei['name']) ?></td>
                                <td><?= fmtQty($ei['stock_qty']) ?></td>
                                <td><?= htmlspecialchars($ei['unit']) ?></td>
                                <td><?= date('M d, Y', strtotime($ei['expiry_date'])) ?></td>
                                <td><span class="badge badge-danger"><?= $overdue ?> day(s) ago</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
    ═══════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="trendsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-chart-line mr-2"></i>Stock Level Overview</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Current stock quantities vs. low stock thresholds for all ingredients.</p>
                    <canvas id="trendsChart" height="120"></canvas>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         SET ALERTS MODAL
    ═══════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="setAlertsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-bell mr-2"></i>Set Low Stock Alert Thresholds</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form action="../../Backend/inventory_process.php" method="POST">
                    <input type="hidden" name="bulk_update_thresholds" value="1">
                    <div class="modal-body">
                        <p class="text-muted">Update the low stock threshold for each ingredient. An alert triggers when stock falls at or below this value.</p>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Ingredient</th>
                                    <th>Unit</th>
                                    <th>Current Stock</th>
                                    <th>Alert Threshold</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $ai): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ai['name']) ?></td>
                                    <td><?= htmlspecialchars($ai['unit']) ?></td>
                                    <td><?= fmtQty($ai['stock_qty']) ?></td>
                                    <td>
                                        <input type="number"
                                               name="threshold[<?= $ai['id'] ?>]"
                                               value="<?= fmtQty($ai['low_stock_threshold']) ?>"
                                               class="form-control form-control-sm"
                                               min="0" step="0.01"
                                               style="width:100px;">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save mr-1"></i>Save Thresholds
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div><!-- /.wrapper -->


<!-- ══════════════════════════════════════════════════════════
     DELETE CONFIRM MODAL
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title text-white">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Delete Ingredient
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-1">Are you sure you want to delete</p>
                <strong id="deleteIngredientName" class="d-block mb-2" style="color:#e91e8c;"></strong>
                <small class="text-muted">This cannot be undone.</small>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cancel
                </button>
                <form id="deleteIngredientForm" action="../../Backend/inventory_process.php" method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteIngredientId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash mr-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- ── Scripts ──────────────────────────────────────────────── -->
<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../dist/js/adminlte.js"></script>
<script src="../plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>

<!-- DataTable init -->
<script>
    $(function () {
        $("#inventoryTable").DataTable({
            "responsive": true,
            "autoWidth": false,
            "columnDefs": [
                { "orderable": false, "targets": [8] }
            ],
            "order": [[1, "asc"]]
        });
    });
</script>

<!-- Dark mode toggle -->
<script>
    $(function () {
        const darkMode = localStorage.getItem('darkMode');
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
            setTimeout(() => $(this).removeClass('clicked'), 300);
        });
    });
</script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Quick Actions JS -->
<script>
(function ($) {

    // ── Inventory data from PHP ──────────────────────────────
    var inventoryData = <?php
        $chartData = array_map(fn($i) => [
            'name'      => $i['name'],
            'stock'     => (float) $i['stock_qty'],
            'threshold' => (float) $i['low_stock_threshold'],
        ], $items);
        echo json_encode($chartData, JSON_HEX_TAG);
    ?>;

    // ── 1. Export Inventory as CSV ───────────────────────────
    $('#exportCsvBtn').on('click', function () {
        var rows = [['ID','Name','Unit','Stock Qty','Low Stock Threshold','Status','Last Updated']];
        $('#inventoryTable tbody tr').each(function () {
            var cells = $(this).find('td');
            rows.push([
                cells.eq(0).text().trim(),
                cells.eq(1).text().trim(),
                cells.eq(2).text().trim(),
                cells.eq(3).text().trim(),
                cells.eq(4).text().trim(),
                cells.eq(5).text().trim(),
                cells.eq(6).text().trim()
            ]);
        });
        var csv = rows.map(function (r) {
            return r.map(function (v) { return '"' + v.replace(/"/g, '""') + '"'; }).join(',');
        }).join('\r\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href     = url;
        a.download = 'inventory_export_' + new Date().toISOString().slice(0,10) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });

    // ── 2. View Trends — bar chart ───────────────────────────
    var trendsChartInstance = null;

    $('#trendsModal').on('shown.bs.modal', function () {
        if (trendsChartInstance) { trendsChartInstance.destroy(); }

        var labels     = inventoryData.map(function (d) { return d.name; });
        var stocks     = inventoryData.map(function (d) { return d.stock; });
        var thresholds = inventoryData.map(function (d) { return d.threshold; });
        var colors     = inventoryData.map(function (d) {
            if (d.stock === 0)              return 'rgba(220,53,69,0.8)';
            if (d.stock <= d.threshold)     return 'rgba(255,193,7,0.8)';
            return 'rgba(40,167,69,0.8)';
        });

        var ctx = document.getElementById('trendsChart').getContext('2d');
        trendsChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Stock Qty',
                        data: stocks,
                        backgroundColor: colors,
                        borderColor: colors.map(function (c) { return c.replace('0.8', '1'); }),
                        borderWidth: 1
                    },
                    {
                        label: 'Low Stock Threshold',
                        data: thresholds,
                        type: 'line',
                        borderColor: 'rgba(255,193,7,1)',
                        backgroundColor: 'transparent',
                        borderDash: [6,3],
                        borderWidth: 2,
                        pointRadius: 3,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.dataset.label + ': ' + ctx.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    x: { ticks: { maxRotation: 45, minRotation: 30 } },
                    y: { beginAtZero: true }
                }
            }
        });
    });

    $('#trendsModal').on('hidden.bs.modal', function () {
        if (trendsChartInstance) { trendsChartInstance.destroy(); trendsChartInstance = null; }
    });

}(jQuery));
</script>

<!-- Edit modal: pre-fill from data-* on Edit button -->
<script>
    $(function () {
        $('#editInventoryModal').on('show.bs.modal', function (event) {
            var btn = $(event.relatedTarget);
            $('#editIngId').val(btn.data('id'));
            $('#editIngName').val(btn.data('name'));
            $('#editIngUnit').val(btn.data('unit'));
            $('#editIngStock').val(btn.data('stock'));
            $('#editIngThreshold').val(btn.data('threshold'));
            $('#editIngExpiry').val(btn.data('expiry') || '');
        });
    });
</script>

<script>
    function confirmDeleteIngredient(id, name) {
        $('#deleteIngredientName').text(name);
        $('#deleteIngredientId').val(id);
        $('#deleteConfirmModal').modal('show');
    }
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

</body>
</html>