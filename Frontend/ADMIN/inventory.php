<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../../Frontend/lockscreen.html");
    exit();
}


// DB: operlytics | table: ingredients
// columns: id, name, unit, stock_qty, low_stock_threshold, created_at, updated_at
require_once '../../Backend/conn.php';

// ── Stats ─────────────────────────────────────────────────────
$total     = $conn->query("SELECT COUNT(*) AS c FROM ingredients")->fetch_assoc()['c'] ?? 0;
$in_stock  = $conn->query("SELECT COUNT(*) AS c FROM ingredients WHERE stock_qty > low_stock_threshold")->fetch_assoc()['c'] ?? 0;
$low_stock = $conn->query("SELECT COUNT(*) AS c FROM ingredients WHERE stock_qty > 0 AND stock_qty <= low_stock_threshold")->fetch_assoc()['c'] ?? 0;
$out_stock = $conn->query("SELECT COUNT(*) AS c FROM ingredients WHERE stock_qty = 0")->fetch_assoc()['c'] ?? 0;

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
</head>

<style>
    body, .main-header.navbar { transition: background-color 0.5s ease, color 0.5s ease; }
    #darkModeToggle { transition: box-shadow 0.3s ease; }
    #darkModeToggle i { transition: transform 0.3s ease; }
    #darkModeToggle.clicked { box-shadow: 0 0 15px rgba(255,255,255,0.8); }
    #darkModeToggle.clicked i { transform: rotate(180deg) scale(1.2); }
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
                <div class="image">
                    <img src="../dist/img/user2-160x160.jpg" class="img-circle elevation-2">
                </div>
                <div class="info">
                     <a href="#" class="d-block"><?= htmlspecialchars($_SESSION['user']['firstname'] ?? 'Admin') ?></a>
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

                <!-- ── Alerts + Quick Actions ─────────────────── -->
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
                                                <?= $alert['stock_qty'] ?> <?= htmlspecialchars($alert['unit']) ?>
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
                                    <button class="btn btn-warning" data-toggle="modal" data-target="#setAlertsModal">
                                        <i class="fas fa-bell mr-1"></i> Set Alerts
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
                                ?>
                                <tr>
                                    <td><?= $item['id'] ?></td>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= htmlspecialchars($item['unit']) ?></td>
                                    <td>
                                        <span class="badge <?= $statusBadge ?>">
                                            <?= $item['stock_qty'] ?>
                                        </span>
                                    </td>
                                    <td><?= $item['low_stock_threshold'] ?></td>
                                    <td><span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span></td>
                                    <td><small><?= $item['updated_at'] ?? $item['created_at'] ?></small></td>
                                    <td>
                                        <!-- Edit button -->
                                        <button class="btn btn-sm btn-warning"
                                                data-toggle="modal"
                                                data-target="#editInventoryModal"
                                                data-id="<?= $item['id'] ?>"
                                                data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>"
                                                data-unit="<?= htmlspecialchars($item['unit'], ENT_QUOTES) ?>"
                                                data-stock="<?= $item['stock_qty'] ?>"
                                                data-threshold="<?= $item['low_stock_threshold'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <!-- Delete link -->
                                        <a href="../../Backend/inventory_process.php?action=delete&id=<?= $item['id'] ?>"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Delete \'<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>\'? This cannot be undone.')">
                                            <i class="fas fa-trash"></i>
                                        </a>
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
                                    <td><?= $ri['stock_qty'] ?></td>
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
         VIEW TRENDS MODAL
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
                                    <td><?= $ai['stock_qty'] ?></td>
                                    <td>
                                        <input type="number"
                                               name="threshold[<?= $ai['id'] ?>]"
                                               value="<?= $ai['low_stock_threshold'] ?>"
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
                { "orderable": false, "targets": [7] }
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
        });
    });
</script>

</body>
</html>