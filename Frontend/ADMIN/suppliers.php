<?php
// Frontend/ADMIN/suppliers.php  (OOP refactored)
require_once '../../Frontend/Core/SupplierView.php';
$view = new SupplierView();

// Variable aliases so the HTML below needs zero changes
$total_suppliers    = $view->totalSuppliers;
$active_suppliers   = $view->activeSuppliers;
$inactive_suppliers = $view->inactiveSuppliers;
$categories         = $view->categories;
$suppliers          = $view->suppliers;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OPERLYTICS | Supplier Information</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
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
  

  .supplier-avatar {
    width: 42px; height: 42px; border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 700; font-size: 1rem; flex-shrink: 0;
  }
  .supplier-avatar.green  { background: linear-gradient(135deg, #11998e, #38ef7d); }
  .supplier-avatar.orange { background: linear-gradient(135deg, #f46b45, #eea849); }
  .supplier-avatar.blue   { background: linear-gradient(135deg, #4facfe, #00f2fe); }
  .supplier-avatar.red    { background: linear-gradient(135deg, #f093fb, #f5576c); }
  .supplier-avatar.teal   { background: linear-gradient(135deg, #43e97b, #38f9d7); }

  #supplierTable td, #supplierTable th { vertical-align: middle; }

    
    /* FIX: Table hover visible in light mode */
    body:not(.dark-mode) .table tbody tr:hover { background-color: rgba(233,30,140,0.08) !important; }
    body:not(.dark-mode) .table tbody tr:hover td { color: #212529 !important; }

    /* FIX: supplier table dark rows invisible in light mode */
    body:not(.dark-mode) #supplierTable,
    body:not(.dark-mode) #supplierTable th,
    body:not(.dark-mode) #supplierTable td {
      color: #212529 !important;
    }
    body:not(.dark-mode) #supplierTable tbody tr:hover td {
      background-color: rgba(233,30,140,0.08) !important;
      color: #212529 !important;
    }
    body:not(.dark-mode) #supplierTable thead th {
      background-color: #f4f6f9;
      color: #212529 !important;
    }

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

<body class="hold-transition dark-mode sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">

  <!-- ── Navbar ─────────────────────────────────────────────────────── -->
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

  <!-- ── Sidebar ────────────────────────────────────────────────────── -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="#" class="brand-link">
      <img src="../dist/img/Empress%27 Cafe Boracay.jpg" alt="Logo" class="brand-image img-circle elevation-3" style="opacity:.8">
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
          <li class="nav-item">
            <a href="./index2.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Overview</p></a>
          </li>
          <li class="nav-item">
            <a href="./menu-management.php" class="nav-link"><i class="nav-icon fas fa-utensils"></i><p>Menu Management</p></a>
          </li>
          <li class="nav-item">
            <a href="./inventory.php" class="nav-link"><i class="nav-icon fas fa-boxes"></i><p>Inventory Tracking</p></a>
          </li>
          <li class="nav-item">
            <a href="./suppliers.php" class="nav-link active"><i class="nav-icon fas fa-truck"></i><p>Supplier Info</p></a>
          </li>
          <li class="nav-item">
            <a href="./staff-list.php" class="nav-link"><i class="far fa-user nav-icon"></i><p>Staff List</p></a>
          </li>
          <li class="nav-item">
            <a href="./sale_revenue.php" class="nav-link"><i class="nav-icon fas fa-chart-line"></i><p>Sales & Revenue</p></a>
          </li>
          <li class="nav-item">
            <a href="./report.php" class="nav-link"><i class="nav-icon fas fa-file-alt"></i><p>Reports</p></a>
          </li>
          <li class="nav-item"><a href="./void_refund.php"     class="nav-link"><i class="nav-icon fas fa-undo-alt"></i><p>Void &amp; Refund</p></a></li>
          <li class="nav-item"><a href="./settings.php" class="nav-link"><i class="nav-icon fas fa-cog"></i><p>Settings</p></a></li>
          <li class="nav-item mt-auto">
            <a href="../../Backend/Controllers/LogoutController.php" class="nav-link"><i class="nav-icon fas fa-sign-out-alt"></i><p>Log Out</p></a>
          </li>
        </ul>
      </nav>
    </div>
  </aside>

  <!-- ── Content Wrapper ───────────────────────────────────────────── -->
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

    <!-- Page Header -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">Supplier Information</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="index2.php">Home</a></li>
              <li class="breadcrumb-item active">Supplier Info</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">

        <!-- ── Stats ─────────────────────────────────────────────── -->
        <div class="row mb-4">
          <div class="col-lg-3 col-6">
            <div class="info-box">
              <span class="info-box-icon bg-info"><i class="fas fa-building"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Suppliers</span>
                <span class="info-box-number"><?= $total_suppliers ?></span>
              </div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="info-box">
              <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Active</span>
                <span class="info-box-number"><?= $active_suppliers ?></span>
              </div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="info-box">
              <span class="info-box-icon bg-warning"><i class="fas fa-pause-circle"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Inactive</span>
                <span class="info-box-number"><?= $inactive_suppliers ?></span>
              </div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="info-box">
              <span class="info-box-icon bg-primary"><i class="fas fa-tags"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Categories</span>
                <span class="info-box-number"><?= $categories ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Supplier Directory Table ──────────────────────────── -->
        <div class="card card-dark">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-address-book mr-2"></i>Supplier Directory</h3>
            <div class="card-tools">
              <button class="btn btn-tool" data-card-widget="maximize">
                <i class="fas fa-expand"></i>
              </button>
              <a href="#" class="btn btn-sm btn-success" data-toggle="modal" data-target="#addSupplierModal">
                <i class="fas fa-plus"></i> Add Supplier
              </a>
            </div>
          </div>
          <div class="card-body">
            <table id="supplierTable" class="table table-dark table-hover">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Supplier</th>
                  <th>Category</th>
                  <th>Contact Person</th>
                  <th>Phone</th>
                  <th>Email</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $avatarColors = ['green','orange','blue','red','teal','',''];
                $i = 0;
                foreach ($suppliers as $s):
                    $color       = $avatarColors[$i % count($avatarColors)];
                    $initials    = strtoupper(substr($s['name'] ?? '?', 0, 1));
                    $statusClass = ($s['status'] ?? '') === 'Active' ? 'badge-success' : 'badge-secondary';
                    $i++;
                ?>
                <tr>
                  <td><?= $s['id'] ?></td>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="supplier-avatar <?= $color ?> mr-2"><?= $initials ?></div>
                      <div>
                        <strong><?= htmlspecialchars($s['name']) ?></strong><br>
                        <small class="text-muted"><?= htmlspecialchars($s['address'] ?? '') ?></small>
                      </div>
                    </div>
                  </td>
                  <td><span class="badge badge-info"><?= htmlspecialchars($s['category'] ?? '—') ?></span></td>
                  <td><?= htmlspecialchars($s['contact_person'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($s['phone'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($s['email'] ?? '—') ?></td>
                  <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($s['status'] ?? 'Unknown') ?></span></td>
                  <td>
                    <button class="btn btn-sm btn-info"
                            data-toggle="modal" data-target="#viewSupplierModal"
                            data-id="<?= $s['id'] ?>"
                            data-name="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>"
                            data-category="<?= htmlspecialchars($s['category'] ?? '', ENT_QUOTES) ?>"
                            data-contact="<?= htmlspecialchars($s['contact_person'] ?? '', ENT_QUOTES) ?>"
                            data-phone="<?= htmlspecialchars($s['phone'] ?? '', ENT_QUOTES) ?>"
                            data-email="<?= htmlspecialchars($s['email'] ?? '', ENT_QUOTES) ?>"
                            data-address="<?= htmlspecialchars($s['address'] ?? '', ENT_QUOTES) ?>"
                            data-notes="<?= htmlspecialchars($s['notes'] ?? '', ENT_QUOTES) ?>"
                            data-status="<?= htmlspecialchars($s['status'] ?? '', ENT_QUOTES) ?>">
                      <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-warning"
                            data-toggle="modal" data-target="#editSupplierModal"
                            data-id="<?= $s['id'] ?>"
                            data-name="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>"
                            data-category="<?= htmlspecialchars($s['category'] ?? '', ENT_QUOTES) ?>"
                            data-contact="<?= htmlspecialchars($s['contact_person'] ?? '', ENT_QUOTES) ?>"
                            data-phone="<?= htmlspecialchars($s['phone'] ?? '', ENT_QUOTES) ?>"
                            data-email="<?= htmlspecialchars($s['email'] ?? '', ENT_QUOTES) ?>"
                            data-address="<?= htmlspecialchars($s['address'] ?? '', ENT_QUOTES) ?>"
                            data-notes="<?= htmlspecialchars($s['notes'] ?? '', ENT_QUOTES) ?>"
                            data-status="<?= htmlspecialchars($s['status'] ?? '', ENT_QUOTES) ?>">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger"
                       onclick="confirmDeleteSupplier(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>')">
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
       ADD SUPPLIER MODAL
  ═══════════════════════════════════════════════════════════ -->
  <div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>Add Supplier</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <form action="../../Backend/Controllers/SupplierController.php" method="POST">
          <input type="hidden" name="action" value="add">
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Supplier Name <span class="text-danger">*</span></label>
                  <input type="text" name="name" class="form-control" placeholder="e.g. ABC Coffee Supply" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Category <span class="text-danger">*</span></label>
                  <select name="category" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <option value="Beverages">Beverages</option>
                    <option value="Dairy">Dairy</option>
                    <option value="Dry Goods">Dry Goods</option>
                    <option value="Fresh Produce">Fresh Produce</option>
                    <option value="Meat &amp; Poultry">Meat &amp; Poultry</option>
                    <option value="Pastry &amp; Bakery">Pastry &amp; Bakery</option>
                    <option value="Packaging">Packaging</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Contact Person</label>
                  <input type="text" name="contact_person" class="form-control" placeholder="e.g. Juan dela Cruz">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Phone Number</label>
                  <input type="text" name="phone" class="form-control" placeholder="e.g. +63 912 345 6789">
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Email Address</label>
                  <input type="email" name="email" class="form-control" placeholder="supplier@email.com">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Status</label>
                  <select name="status" class="form-control">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Address</label>
              <input type="text" name="address" class="form-control" placeholder="e.g. Boracay Island, Aklan">
            </div>
            <div class="form-group">
              <label>Notes</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes about this supplier..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              <i class="fas fa-times mr-1"></i>Cancel
            </button>
            <button type="submit" class="btn btn-success">
              <i class="fas fa-save mr-1"></i>Save Supplier
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>


  <!-- ══════════════════════════════════════════════════════════
       EDIT SUPPLIER MODAL
  ═══════════════════════════════════════════════════════════ -->
  <div class="modal fade" id="editSupplierModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Supplier</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <form action="../../Backend/Controllers/SupplierController.php" method="POST">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" id="editSuppId">
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Supplier Name <span class="text-danger">*</span></label>
                  <input type="text" name="name" id="editSuppName" class="form-control" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Category <span class="text-danger">*</span></label>
                  <select name="category" id="editSuppCategory" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <option value="Beverages">Beverages</option>
                    <option value="Dairy">Dairy</option>
                    <option value="Dry Goods">Dry Goods</option>
                    <option value="Fresh Produce">Fresh Produce</option>
                    <option value="Meat &amp; Poultry">Meat &amp; Poultry</option>
                    <option value="Pastry &amp; Bakery">Pastry &amp; Bakery</option>
                    <option value="Packaging">Packaging</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Contact Person</label>
                  <input type="text" name="contact_person" id="editSuppContact" class="form-control">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Phone Number</label>
                  <input type="text" name="phone" id="editSuppPhone" class="form-control">
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Email Address</label>
                  <input type="email" name="email" id="editSuppEmail" class="form-control">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Status</label>
                  <select name="status" id="editSuppStatus" class="form-control">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Address</label>
              <input type="text" name="address" id="editSuppAddress" class="form-control">
            </div>
            <div class="form-group">
              <label>Notes</label>
              <textarea name="notes" id="editSuppNotes" class="form-control" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              <i class="fas fa-times mr-1"></i>Cancel
            </button>
            <button type="submit" class="btn btn-warning">
              <i class="fas fa-save mr-1"></i>Update Supplier
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>


  <!-- ══════════════════════════════════════════════════════════
       VIEW SUPPLIER MODAL
  ═══════════════════════════════════════════════════════════ -->
  <div class="modal fade" id="viewSupplierModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-info-circle mr-2"></i>Supplier Details</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-3">
            <div class="supplier-avatar mx-auto mb-2" id="viewSuppAvatar"
                 style="width:60px;height:60px;font-size:1.5rem;"></div>
            <h4 id="viewSuppName" class="mb-0"></h4>
            <span id="viewSuppCategory" class="badge badge-info mt-1"></span>
          </div>
          <hr>
          <table class="table table-sm table-borderless">
            <tr>
              <th style="width:40%"><i class="fas fa-user mr-1 text-muted"></i>Contact Person</th>
              <td id="viewSuppContact"></td>
            </tr>
            <tr>
              <th><i class="fas fa-phone mr-1 text-muted"></i>Phone</th>
              <td id="viewSuppPhone"></td>
            </tr>
            <tr>
              <th><i class="fas fa-envelope mr-1 text-muted"></i>Email</th>
              <td id="viewSuppEmail"></td>
            </tr>
            <tr>
              <th><i class="fas fa-map-marker-alt mr-1 text-muted"></i>Address</th>
              <td id="viewSuppAddress"></td>
            </tr>
            <tr>
              <th><i class="fas fa-circle mr-1 text-muted"></i>Status</th>
              <td id="viewSuppStatusCell"></td>
            </tr>
            <tr>
              <th><i class="fas fa-sticky-note mr-1 text-muted"></i>Notes</th>
              <td id="viewSuppNotes"></td>
            </tr>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

</div><!-- /.wrapper -->


<!-- ── Scripts ──────────────────────────────────────────────────────── -->
<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../dist/js/adminlte.js"></script>
<script src="../plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>

<!-- DataTable init -->
<script>
  $(function () {
    $('#supplierTable').DataTable({
      responsive: true, autoWidth: false,
      columnDefs: [{ orderable: false, targets: [7] }],
      order: [[1, 'asc']]
    });
  });
</script>

<!-- Edit modal pre-fill -->
<script>
  $(function () {
    $('#editSupplierModal').on('show.bs.modal', function (e) {
      var b = $(e.relatedTarget);
      $('#editSuppId').val(b.data('id'));
      $('#editSuppName').val(b.data('name'));
      $('#editSuppCategory').val(b.data('category'));
      $('#editSuppContact').val(b.data('contact'));
      $('#editSuppPhone').val(b.data('phone'));
      $('#editSuppEmail').val(b.data('email'));
      $('#editSuppAddress').val(b.data('address'));
      $('#editSuppNotes').val(b.data('notes'));
      $('#editSuppStatus').val(b.data('status'));
    });
  });
</script>

<!-- View modal pre-fill -->
<script>
  $(function () {
    $('#viewSupplierModal').on('show.bs.modal', function (e) {
      var b      = $(e.relatedTarget);
      var name   = b.data('name') || '—';
      var status = b.data('status') || '';
      $('#viewSuppName').text(name);
      $('#viewSuppAvatar').text(name.charAt(0).toUpperCase());
      $('#viewSuppCategory').text(b.data('category') || '—');
      $('#viewSuppContact').text(b.data('contact') || '—');
      $('#viewSuppPhone').text(b.data('phone') || '—');
      $('#viewSuppEmail').text(b.data('email') || '—');
      $('#viewSuppAddress').text(b.data('address') || '—');
      $('#viewSuppNotes').text(b.data('notes') || '—');
      $('#viewSuppStatusCell').html(
        status === 'Active'
          ? '<span class="badge badge-success">Active</span>'
          : '<span class="badge badge-secondary">Inactive</span>'
      );
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


<!-- ══ DELETE SUPPLIER CONFIRM MODAL — custom blur overlay ══════════ -->
<div id="deleteSupplierModal" style="
  display:none;position:fixed;inset:0;z-index:99998;
  background:rgba(0,0,0,.65);backdrop-filter:blur(6px);
  align-items:center;justify-content:center;
">
  <div id="deleteSupplierBox" style="
    background:linear-gradient(145deg,#1a0a1e 0%,#1a1a2e 100%);
    border:1px solid rgba(233,30,140,.3);
    border-radius:18px;
    box-shadow:0 32px 80px rgba(0,0,0,.7),0 0 0 1px rgba(233,30,140,.15),inset 0 1px 0 rgba(255,255,255,.06);
    width:100%;max-width:400px;margin:16px;
    overflow:hidden;
    transform:scale(.88) translateY(20px);opacity:0;
    transition:transform .3s cubic-bezier(.34,1.56,.64,1),opacity .25s ease;
  ">
    <!-- Header -->
    <div style="
      background:linear-gradient(135deg,rgba(233,30,140,.18) 0%,rgba(156,39,176,.1) 100%);
      border-bottom:1px solid rgba(233,30,140,.18);
      padding:20px 22px 16px;display:flex;align-items:center;gap:14px;
    ">
      <div style="
        width:44px;height:44px;border-radius:50%;flex-shrink:0;
        background:linear-gradient(135deg,#f59e0b,#e91e8c);
        display:flex;align-items:center;justify-content:center;
        box-shadow:0 4px 16px rgba(233,30,140,.4);
        font-size:1.15rem;color:#fff;
      "><i class="fas fa-trash"></i></div>
      <div>
        <div style="font-family:'DM Sans',sans-serif;font-weight:700;font-size:1rem;color:#fff;line-height:1.2;">Delete Supplier</div>
        <div style="font-family:'DM Sans',sans-serif;font-size:.75rem;color:rgba(255,255,255,.45);margin-top:2px;">Confirm before deleting</div>
      </div>
      <button onclick="closeDeleteSupplierModal()" style="
        margin-left:auto;background:none;border:none;cursor:pointer;
        color:rgba(255,255,255,.4);font-size:1.2rem;line-height:1;
        transition:color .2s;padding:2px 6px;border-radius:6px;
      " onmouseover="this.style.color='#e91e8c'" onmouseout="this.style.color='rgba(255,255,255,.4)'">&#x2715;</button>
    </div>
    <!-- Body -->
    <div style="padding:22px 22px 8px;">
      <p style="font-family:'DM Sans',sans-serif;color:rgba(255,255,255,.7);font-size:.875rem;margin:0 0 6px;">
        Are you sure you want to delete
      </p>
      <div style="
        background:rgba(233,30,140,.1);border:1px solid rgba(233,30,140,.25);
        border-radius:10px;padding:10px 14px;margin-bottom:4px;
        display:flex;align-items:center;gap:10px;
      ">
        <i class="fas fa-truck" style="color:#e91e8c;font-size:.85rem;"></i>
        <span id="deleteSupplierName" style="font-family:'DM Sans',sans-serif;font-weight:600;color:#fff;font-size:.9rem;word-break:break-all;"></span>
      </div>
      <p style="font-family:'DM Sans',sans-serif;color:rgba(255,255,255,.35);font-size:.75rem;margin:8px 0 0;">
        <i class="fas fa-info-circle mr-1"></i>This cannot be undone.
      </p>
    </div>
    <!-- Footer -->
    <div style="padding:16px 22px 20px;display:flex;gap:10px;justify-content:flex-end;">
      <button onclick="closeDeleteSupplierModal()" style="
        font-family:'DM Sans',sans-serif;font-weight:600;font-size:.82rem;
        background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);
        color:rgba(255,255,255,.65);border-radius:22px;padding:8px 20px;cursor:pointer;
        transition:background .2s,color .2s;
      " onmouseover="this.style.background='rgba(255,255,255,.13)';this.style.color='#fff'"
         onmouseout="this.style.background='rgba(255,255,255,.07)';this.style.color='rgba(255,255,255,.65)'">
        Cancel
      </button>
      <a id="deleteSupplierLink" href="#" style="
        font-family:'DM Sans',sans-serif;font-weight:700;font-size:.82rem;
        background:linear-gradient(135deg,#f59e0b 0%,#e91e8c 100%);
        border:none;color:#fff;border-radius:22px;padding:8px 24px;cursor:pointer;
        box-shadow:0 4px 16px rgba(233,30,140,.4);
        transition:transform .18s,box-shadow .18s;
        display:inline-flex;align-items:center;gap:8px;text-decoration:none;
      " onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 22px rgba(233,30,140,.55)'"
         onmouseout="this.style.transform='';this.style.boxShadow='0 4px 16px rgba(233,30,140,.4)'">
        <i class="fas fa-trash"></i> Delete
      </a>
    </div>
  </div>
</div>
<script>
  function confirmDeleteSupplier(id, name) {
    document.getElementById('deleteSupplierName').textContent = name;
    document.getElementById('deleteSupplierLink').href = '../../Backend/Controllers/SupplierController.php?action=delete&id=' + id;
    var overlay = document.getElementById('deleteSupplierModal');
    var box = document.getElementById('deleteSupplierBox');
    overlay.style.display = 'flex';
    requestAnimationFrame(function(){ box.style.transform='scale(1) translateY(0)'; box.style.opacity='1'; });
  }
  function closeDeleteSupplierModal() {
    var overlay = document.getElementById('deleteSupplierModal');
    var box = document.getElementById('deleteSupplierBox');
    box.style.transform='scale(.88) translateY(20px)'; box.style.opacity='0';
    setTimeout(function(){ overlay.style.display='none'; }, 280);
  }
  document.getElementById('deleteSupplierModal').addEventListener('click', function(e){
    if(e.target === this) closeDeleteSupplierModal();
  });
</script>
<!-- ══ END DELETE SUPPLIER CONFIRM MODAL ══════════════════════════ -->

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
