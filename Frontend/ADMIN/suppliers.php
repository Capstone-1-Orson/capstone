<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['position'] !== 'admin') {
    header("Location: ../../Frontend/lockscreen.html");
    exit();
}

require_once '../../Backend/conn.php';

// ── Stats ──────────────────────────────────────────────────────────────────
$total_suppliers   = $conn->query("SELECT COUNT(*) AS c FROM suppliers")->fetch_assoc()['c'] ?? 0;
$active_suppliers  = $conn->query("SELECT COUNT(*) AS c FROM suppliers WHERE status = 'Active'")->fetch_assoc()['c'] ?? 0;
$inactive_suppliers= $conn->query("SELECT COUNT(*) AS c FROM suppliers WHERE status = 'Inactive'")->fetch_assoc()['c'] ?? 0;
$categories        = $conn->query("SELECT COUNT(DISTINCT category) AS c FROM suppliers")->fetch_assoc()['c'] ?? 0;

// ── Fetch all suppliers ────────────────────────────────────────────────────
$suppliers = [];
$res = $conn->query("SELECT * FROM suppliers ORDER BY name ASC");
if ($res) { while ($row = $res->fetch_assoc()) { $suppliers[] = $row; } }

$conn->close();
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
  <link rel="stylesheet" href="../dist/css/empress-animations.css">
</head>

<style>
  body, .main-header.navbar { transition: background-color 0.5s ease, color 0.5s ease; }
  #darkModeToggle { transition: box-shadow 0.3s ease; }
  #darkModeToggle i { transition: transform 0.3s ease; }
  #darkModeToggle.clicked { box-shadow: 0 0 15px rgba(255,255,255,0.8); }
  #darkModeToggle.clicked i { transform: rotate(180deg) scale(1.2); }

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
        <a class="nav-link" data-widget="fullscreen" href="#" role="button">
          <i class="fas fa-expand-arrows-alt"></i>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" id="darkModeToggle" href="#" role="button">
          <i class="fas fa-moon"></i>
        </a>
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
          <li class="nav-item"><a href="./settings.php" class="nav-link"><i class="nav-icon fas fa-cog"></i><p>Settings</p></a></li>
          <li class="nav-item mt-auto">
            <a href="../../Backend/logout.php" class="nav-link"><i class="nav-icon fas fa-sign-out-alt"></i><p>Log Out</p></a>
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
                    <a href="../../Backend/supplier_process.php?action=delete&id=<?= $s['id'] ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Delete \'<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>\'? This cannot be undone.')">
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
       ADD SUPPLIER MODAL
  ═══════════════════════════════════════════════════════════ -->
  <div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>Add Supplier</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <form action="../../Backend/supplier_process.php" method="POST">
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
        <form action="../../Backend/supplier_process.php" method="POST">
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

</body>
</html>