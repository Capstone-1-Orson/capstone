<?php
session_name('ADMIN_SESSION');
session_start();
if (!isset($_SESSION['user']) || $_SESSION['position'] !== 'admin') {
    header("Location:../../lockscreen.html");
    exit();
}

// DB: operlytics  |  table: menu  |  PK: id
require_once '../../Backend/conn.php';

// ── Stats for info boxes ──────────────────────────────────────
$total    = $conn->query("SELECT COUNT(*) AS c FROM menu")->fetch_assoc()['c'] ?? 0;
$active   = $conn->query("SELECT COUNT(*) AS c FROM menu WHERE is_available = 1")->fetch_assoc()['c'] ?? 0;
$inactive = $conn->query("SELECT COUNT(*) AS c FROM menu WHERE is_available = 0")->fetch_assoc()['c'] ?? 0;
$cats     = $conn->query("SELECT COUNT(DISTINCT category) AS c FROM menu")->fetch_assoc()['c'] ?? 0;

// ── Fetch all menu items ──────────────────────────────────────
$items = [];
$res = $conn->query("SELECT * FROM menu ORDER BY created_at DESC");
while ($row = $res->fetch_assoc()) {
    $items[] = $row;
}

// ── Fetch all ingredients for the dropdown ────────────────────
$ingredients_list = [];
$res3 = $conn->query("SELECT id, name, unit FROM ingredients ORDER BY name ASC");
if ($res3) {
    while ($row = $res3->fetch_assoc()) {
        $ingredients_list[] = $row;
    }
}

// ── Fetch menu_ingredients for display in table ───────────────
$menu_ingredients_map = [];
$res4 = $conn->query(
    "SELECT mi.menu_id, mi.ingredient_id, mi.qty_needed, i.name, i.unit
     FROM menu_ingredients mi
     JOIN ingredients i ON i.id = mi.ingredient_id
     ORDER BY mi.menu_id, i.name"
);
if ($res4) {
    while ($row = $res4->fetch_assoc()) {
        $menu_ingredients_map[$row['menu_id']][] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OPERLYTICS | Menu Management</title>

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

    

    /* FIX: table-dark rows are invisible on hover in light mode — fix text color */
    body:not(.dark-mode) #menuTable,
    body:not(.dark-mode) #menuTable th,
    body:not(.dark-mode) #menuTable td {
      color: #212529 !important;
      background-color: transparent;
    }
    body:not(.dark-mode) #menuTable tbody tr:hover td {
      background-color: rgba(233,30,140,0.08) !important;
      color: #212529 !important;
    }
    body:not(.dark-mode) #menuTable thead th {
      background-color: #f4f6f9;
      color: #212529 !important;
    }

    /* FIX: Table text wrapping on mobile */
    

    /* FIX: Responsive pagination */
    
    
    
    .table td, .table th { font-size: 12px; }
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
        <a class="nav-link" data-widget="fullscreen" href="#" role="button"><i class="fas fa-expand-arrows-alt"></i></a>
      </li>
      <li class="nav-item">
        <a class="nav-link" id="darkModeToggle" href="#" role="button"><i class="fas fa-moon"></i></a>
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
        <div class="image"><img src="../dist/img/Empress' Cafe Boracay.jpg" class="img-circle elevation-2" alt="User Image"></div>
        <div class="info">
           <a href="#" class="d-block"><?= htmlspecialchars($_SESSION['user']['firstname'] ?? 'admin') ?></a>
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
            <a href="./menu-management.php" class="nav-link active">
              <i class="nav-icon fas fa-utensils"></i><p>Menu Management</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="./inventory.php" class="nav-link">
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
          <div class="col-sm-6">
            <h1 class="m-0">Menu Management</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="index2.php">Home</a></li>
              <li class="breadcrumb-item active">Menu</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">

        <!-- ── Info Boxes ─────────────────────────────────── -->
        <div class="row mb-4">
          <div class="col-lg-3 col-6">
            <div class="info-box">
              <span class="info-box-icon bg-info"><i class="fas fa-utensils"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Items</span>
                <span class="info-box-number"><?= $total ?></span>
              </div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="info-box">
              <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Active Items</span>
                <span class="info-box-number"><?= $active ?></span>
              </div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="info-box">
              <span class="info-box-icon bg-warning"><i class="fas fa-pause"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Inactive Items</span>
                <span class="info-box-number"><?= $inactive ?></span>
              </div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="info-box">
              <span class="info-box-icon bg-danger"><i class="fas fa-tags"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Categories</span>
                <span class="info-box-number"><?= $cats ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Table Card ─────────────────────────────────── -->
        <div class="row">
          <div class="col-12">
            <div class="card card-dark">
              <div class="card-header">
                <h3 class="card-title">Menu Items</h3>
                <div class="card-tools">
                  <button class="btn btn-tool" data-card-widget="maximize">
                    <i class="fas fa-expand"></i>
                  </button>
                  <a href="#" class="btn btn-sm btn-success" data-toggle="modal" data-target="#addMenuModal">
                    <i class="fas fa-plus"></i> Add Item
                  </a>
                </div>
              </div>

              <div class="card-body">
                <table id="menuTable" class="table table-dark table-hover">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Image</th>
                      <th>Item Name</th>
                      <th>Category</th>
                      <th>Price</th>
                      <th>Description</th>
                      <th>Ingredients</th>
                      <th>Status</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                      <td><?= $item['id'] ?></td>
                      <td>
                        <?php if (!empty($item['image'])): ?>
                          <img src="../<?= htmlspecialchars(str_replace('Frontend/', '', $item['image'])) ?>"
                               alt="<?= htmlspecialchars($item['name']) ?>"
                               style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,0.15);cursor:pointer;"
                               onclick="showImagePreview('<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>','../<?= htmlspecialchars(str_replace('Frontend/', '', $item['image'])) ?>')">
                        <?php else: ?>
                          <div style="width:48px;height:48px;border-radius:6px;background:rgba(255,255,255,0.07);border:1px dashed rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-image" style="color:rgba(255,255,255,0.25);font-size:16px;"></i>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td><?= htmlspecialchars($item['name']) ?></td>
                      <td><?= htmlspecialchars($item['category']) ?></td>
                      <td>₱<?= number_format($item['price'], 2) ?></td>
                      <td>
                        <?php if (!empty($item['description'])): ?>
                          <small style="opacity:0.8;"><?= htmlspecialchars($item['description']) ?></small>
                        <?php else: ?>
                          <small class="text-muted">—</small>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if (!empty($menu_ingredients_map[$item['id']])): ?>
                          <div style="display:flex;flex-wrap:wrap;gap:4px;">
                          <?php foreach ($menu_ingredients_map[$item['id']] as $ing): ?>
                            <span class="badge badge-secondary" style="font-size:10px;font-weight:500;">
                              <?= htmlspecialchars($ing['name']) ?> <?= $ing['qty_needed'] ?><?= htmlspecialchars($ing['unit']) ?>
                            </span>
                          <?php endforeach; ?>
                          </div>
                        <?php else: ?>
                          <small class="text-muted">—</small>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($item['is_available'] == 1): ?>
                          <span class="badge badge-success">Active</span>
                        <?php else: ?>
                          <span class="badge badge-danger">Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <button class="btn btn-sm btn-warning"
                                data-toggle="modal"
                                data-target="#editMenuModal"
                                data-id="<?= $item['id'] ?>"
                                data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>"
                                data-category="<?= htmlspecialchars($item['category'], ENT_QUOTES) ?>"
                                data-price="<?= $item['price'] ?>"
                                data-status="<?= $item['is_available'] ?>"
                                data-description="<?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES) ?>"
                                data-image="<?= htmlspecialchars($item['image'] ?? '', ENT_QUOTES) ?>">
                          <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger"
                                onclick="confirmDelete(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')">
                          <i class="fas fa-trash"></i>
                        </button>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div><!-- /.card-body -->
            </div>
          </div>
        </div>

      </div>
    </section>
  </div><!-- /.content-wrapper -->


  <!-- ══════════════════════════════════════════════════════════
       ADD MENU MODAL
  ═══════════════════════════════════════════════════════════ -->
  <div class="modal fade" id="addMenuModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>Add Menu Item</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>

        <form action="../../Backend/menu_process.php" method="POST" enctype="multipart/form-data">
          <div class="modal-body">

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Item Name</label>
                  <input type="text" name="name" class="form-control" placeholder="e.g. Grilled Chicken" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Category</label>
                  <select name="category" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <optgroup label="☕ Drinks">
                      <option value="Coffee">Coffee (Hot/Iced)</option>
                      <option value="Frappe">Frappe</option>
                      <option value="Non-Coffee">Non-Coffee (Hot/Iced)</option>
                      <option value="Soda Mix & Match">Soda Mix &amp; Match</option>
                      <option value="Fresh Juice">Fresh Juices</option>
                      <option value="Shake">Fresh Fruit / Protein Shake</option>
                      <option value="Beer & Wine">Beers / Wine</option>
                      <option value="Others">Others (Drinks)</option>
                    </optgroup>
                    <optgroup label="🍽️ Food">
                      <option value="Rice Meal">Rice Meals</option>
                      <option value="Pasta">Pasta</option>
                      <option value="Bites & Treats">Bites and Treats</option>
                    </optgroup>
                    <optgroup label="🧇 Croffles">
                      <option value="Croffle">Croffles</option>
                      <option value="Croffle Box">Croffle in a Box</option>
                    </optgroup>
                    <optgroup label="➕ Extras">
                      <option value="Add-On">Customize Add-Ons</option>
                    </optgroup>
                  </select>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Price (₱)</label>
                  <input type="number" name="price" class="form-control" step="0.01" min="0.01" placeholder="e.g. 150.00" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Status</label>
                  <select name="is_available" class="form-control" required>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Description -->
            <div class="form-group">
              <label>Description <small class="text-muted">(optional)</small></label>
              <textarea name="description" class="form-control" rows="2"
                        placeholder="e.g. Grilled to perfection with herbs and spices"></textarea>
            </div>

            <!-- Image Upload -->
            <div class="form-group">
              <label>Item Image <small class="text-muted">(optional — JPG, PNG, WEBP · max 2 MB)</small></label>
              <div class="custom-file">
                <input type="file" class="custom-file-input" id="addImageInput" name="image" accept="image/jpeg,image/png,image/webp">
                <label class="custom-file-label" for="addImageInput">Choose image…</label>
              </div>
              <div id="addImagePreview" class="mt-2" style="display:none;">
                <img id="addImageThumb" src="" alt="Preview"
                     style="max-height:120px;max-width:100%;border-radius:8px;border:1px solid rgba(233,30,140,0.3);">
                <button type="button" class="btn btn-sm btn-link text-danger ml-2" id="addImageClear">
                  <i class="fas fa-times"></i> Remove
                </button>
              </div>
            </div>

            <!-- Ingredients linked to inventory -->
            <div class="form-group">
              <label>Ingredients <small class="text-muted">(linked to Inventory)</small></label>
              <?php if (empty($ingredients_list)): ?>
                <div class="alert alert-warning py-2">
                  <i class="fas fa-exclamation-triangle mr-1"></i>
                  No ingredients found. Please <a href="./inventory.php">add ingredients in Inventory</a> first.
                </div>
              <?php endif; ?>
              <div class="input-group mb-2">
                <select class="form-control" id="ingredientSelect" style="flex:2;">
                  <option value="">-- Select Ingredient --</option>
                  <?php foreach ($ingredients_list as $ing): ?>
                  <option value="<?= $ing['id'] ?>"
                          data-unit="<?= htmlspecialchars($ing['unit']) ?>"
                          data-name="<?= htmlspecialchars($ing['name'], ENT_QUOTES) ?>">
                    <?= htmlspecialchars($ing['name']) ?> (<?= htmlspecialchars($ing['unit']) ?>)
                  </option>
                  <?php endforeach; ?>
                </select>
                <input type="number" class="form-control" id="ingredientQty"
                       placeholder="Qty" min="0.01" step="any" style="flex:0 0 90px;max-width:90px;">
                <div class="input-group-append">
                  <button type="button" class="btn btn-success btn-sm px-3" id="addIngredientBtn">
                    <i class="fas fa-plus"></i> Add
                  </button>
                </div>
              </div>
              <div id="ingredientTags" style="display:flex;flex-wrap:wrap;gap:6px;min-height:38px;padding:7px 10px;border:1px solid rgba(233,30,140,0.25);border-radius:8px;background:rgba(233,30,140,0.04);">
                <span style="color:#aaa;font-size:12px;align-self:center;font-style:italic;">No ingredients added yet</span>
              </div>
              <!-- JSON array of {ingredient_id, qty_needed} pairs -->
              <input type="hidden" name="ingredients_json" id="itemIngredients">
              <small class="text-muted mt-1 d-block">
                Select an ingredient from inventory, enter quantity, click <strong>Add</strong>. Click a tag to remove.
              </small>
            </div>

          </div><!-- /.modal-body -->

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              <i class="fas fa-times mr-1"></i>Close
            </button>
            <button type="submit" name="save_menu" class="btn btn-success">
              <i class="fas fa-save mr-1"></i>Save Item
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>


  <!-- ══════════════════════════════════════════════════════════
       EDIT MENU MODAL
  ═══════════════════════════════════════════════════════════ -->
  <div class="modal fade" id="editMenuModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Menu Item</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>

        <form action="../../Backend/menu_process.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="update_menu" value="1">
          <input type="hidden" name="id" id="editId">
          <input type="hidden" name="existing_image" id="editExistingImage">

          <div class="modal-body">

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Item Name</label>
                  <input type="text" name="name" id="editName" class="form-control" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Category</label>
                  <select name="category" id="editCategory" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <optgroup label="☕ Drinks">
                      <option value="Coffee">Coffee (Hot/Iced)</option>
                      <option value="Frappe">Frappe</option>
                      <option value="Non-Coffee">Non-Coffee (Hot/Iced)</option>
                      <option value="Soda Mix & Match">Soda Mix &amp; Match</option>
                      <option value="Fresh Juice">Fresh Juices</option>
                      <option value="Shake">Fresh Fruit / Protein Shake</option>
                      <option value="Beer & Wine">Beers / Wine</option>
                      <option value="Others">Others (Drinks)</option>
                    </optgroup>
                    <optgroup label="🍽️ Food">
                      <option value="Rice Meal">Rice Meals</option>
                      <option value="Pasta">Pasta</option>
                      <option value="Bites & Treats">Bites and Treats</option>
                    </optgroup>
                    <optgroup label="🧇 Croffles">
                      <option value="Croffle">Croffles</option>
                      <option value="Croffle Box">Croffle in a Box</option>
                    </optgroup>
                    <optgroup label="➕ Extras">
                      <option value="Add-On">Customize Add-Ons</option>
                    </optgroup>
                  </select>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Price (₱)</label>
                  <input type="number" name="price" id="editPrice" class="form-control" step="0.01" min="0.01" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Status</label>
                  <select name="is_available" id="editStatus" class="form-control" required>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label>Description <small class="text-muted">(optional)</small></label>
              <textarea name="description" id="editDescription" class="form-control" rows="2"></textarea>
            </div>

            <!-- Image Upload -->
            <div class="form-group">
              <label>Item Image <small class="text-muted">(optional — JPG, PNG, WEBP · max 2 MB)</small></label>
              <div id="editCurrentImageWrap" class="mb-2" style="display:none;">
                <p class="mb-1" style="font-size:12px;color:#aaa;">Current image:</p>
                <img id="editCurrentThumb" src="" alt="Current"
                     style="max-height:100px;max-width:160px;border-radius:8px;border:1px solid rgba(233,30,140,0.3);">
                <button type="button" class="btn btn-sm btn-link text-danger ml-2" id="editRemoveCurrentBtn">
                  <i class="fas fa-times"></i> Remove
                </button>
              </div>
              <div class="custom-file">
                <input type="file" class="custom-file-input" id="editImageInput" name="image" accept="image/jpeg,image/png,image/webp">
                <label class="custom-file-label" for="editImageInput">Choose new image…</label>
              </div>
              <div id="editNewImagePreview" class="mt-2" style="display:none;">
                <img id="editNewImageThumb" src="" alt="New Preview"
                     style="max-height:120px;max-width:100%;border-radius:8px;border:1px solid rgba(233,30,140,0.3);">
                <button type="button" class="btn btn-sm btn-link text-danger ml-2" id="editImageClear">
                  <i class="fas fa-times"></i> Remove
                </button>
              </div>
            </div>

            <!-- Edit Ingredients -->
            <div class="form-group">
              <label>Ingredients <small class="text-muted">(linked to Inventory)</small></label>
              <?php if (empty($ingredients_list)): ?>
                <div class="alert alert-warning py-2">
                  <i class="fas fa-exclamation-triangle mr-1"></i>
                  No ingredients found. Please <a href="./inventory.php">add ingredients in Inventory</a> first.
                </div>
              <?php endif; ?>
              <div class="input-group mb-2">
                <select class="form-control" id="editIngredientSelect" style="flex:2;">
                  <option value="">-- Select Ingredient --</option>
                  <?php foreach ($ingredients_list as $ing): ?>
                  <option value="<?= $ing['id'] ?>"
                          data-unit="<?= htmlspecialchars($ing['unit']) ?>"
                          data-name="<?= htmlspecialchars($ing['name'], ENT_QUOTES) ?>">
                    <?= htmlspecialchars($ing['name']) ?> (<?= htmlspecialchars($ing['unit']) ?>)
                  </option>
                  <?php endforeach; ?>
                </select>
                <input type="number" class="form-control" id="editIngredientQty"
                       placeholder="Qty" min="0.01" step="any" style="flex:0 0 90px;max-width:90px;">
                <div class="input-group-append">
                  <button type="button" class="btn btn-success btn-sm px-3" id="editAddIngredientBtn">
                    <i class="fas fa-plus"></i> Add
                  </button>
                </div>
              </div>
              <div id="editIngredientTags" style="display:flex;flex-wrap:wrap;gap:6px;min-height:38px;padding:7px 10px;border:1px solid rgba(233,30,140,0.25);border-radius:8px;background:rgba(233,30,140,0.04);">
                <span style="color:#aaa;font-size:12px;align-self:center;font-style:italic;">No ingredients added yet</span>
              </div>
              <input type="hidden" name="ingredients_json" id="editItemIngredients">
              <small class="text-muted mt-1 d-block">
                Select an ingredient from inventory, enter quantity, click <strong>Add</strong>. Click a tag to remove.
              </small>
            </div>

          </div><!-- /.modal-body -->

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              <i class="fas fa-times mr-1"></i>Close
            </button>
            <button type="submit" name="update_menu" class="btn btn-warning">
              <i class="fas fa-save mr-1"></i>Update Item
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>

</div><!-- /.wrapper -->

<!-- ── Image Preview Modal ──────────────────────────────────── -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content" style="background:#1a1a2e;">
      <div class="modal-header" style="border-bottom:1px solid rgba(233,30,140,0.3);">
        <h6 class="modal-title" id="imgPreviewLabel" style="color:#e91e8c;"></h6>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body text-center p-2">
        <img id="imgPreviewFull" src="" alt="Menu Item"
             style="max-width:100%;max-height:320px;border-radius:8px;">
      </div>
    </div>
  </div>
</div>
<!-- ══════════════════════════════════════════════════════════
     DELETE CONFIRM MODAL
═══════════════════════════════════════════════════════════ -->
<!-- DELETE CONFIRM MODAL — custom blur overlay matching resend style -->
<div id="deleteConfirmModal" style="
  display:none;position:fixed;inset:0;z-index:99998;
  background:rgba(0,0,0,.65);backdrop-filter:blur(6px);
  align-items:center;justify-content:center;
">
  <div id="deleteConfirmBox" style="
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
        <div style="font-family:'DM Sans',sans-serif;font-weight:700;font-size:1rem;color:#fff;line-height:1.2;">Delete Item</div>
        <div style="font-family:'DM Sans',sans-serif;font-size:.75rem;color:rgba(255,255,255,.45);margin-top:2px;">Confirm before deleting</div>
      </div>
      <button onclick="closeDeleteMenuModal()" style="
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
        <i class="fas fa-utensils" style="color:#e91e8c;font-size:.85rem;"></i>
        <span id="deleteItemName" style="font-family:'DM Sans',sans-serif;font-weight:600;color:#fff;font-size:.9rem;word-break:break-all;"></span>
      </div>
      <p style="font-family:'DM Sans',sans-serif;color:rgba(255,255,255,.35);font-size:.75rem;margin:8px 0 0;">
        <i class="fas fa-info-circle mr-1"></i>If this item has order history, it will be <em>hidden</em> instead of permanently deleted.
      </p>
    </div>
    <!-- Footer -->
    <div style="padding:16px 22px 20px;display:flex;gap:10px;justify-content:flex-end;">
      <button onclick="closeDeleteMenuModal()" style="
        font-family:'DM Sans',sans-serif;font-weight:600;font-size:.82rem;
        background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);
        color:rgba(255,255,255,.65);border-radius:22px;padding:8px 20px;cursor:pointer;
        transition:background .2s,color .2s;
      " onmouseover="this.style.background='rgba(255,255,255,.13)';this.style.color='#fff'"
         onmouseout="this.style.background='rgba(255,255,255,.07)';this.style.color='rgba(255,255,255,.65)'">
        Cancel
      </button>
      <form id="deleteMenuForm" action="../../Backend/menu_process.php" method="POST" style="display:inline;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteMenuId">
        <button type="submit" style="
          font-family:'DM Sans',sans-serif;font-weight:700;font-size:.82rem;
          background:linear-gradient(135deg,#f59e0b 0%,#e91e8c 100%);
          border:none;color:#fff;border-radius:22px;padding:8px 24px;cursor:pointer;
          box-shadow:0 4px 16px rgba(233,30,140,.4);
          transition:transform .18s,box-shadow .18s;
          display:inline-flex;align-items:center;gap:8px;
        " onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 22px rgba(233,30,140,.55)'"
           onmouseout="this.style.transform='';this.style.boxShadow='0 4px 16px rgba(233,30,140,.4)'">
          <i class="fas fa-trash"></i> Delete
        </button>
      </form>
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
    if ($.fn.DataTable.isDataTable('#menuTable')) {
      $('#menuTable').DataTable().destroy();
    }
    $("#menuTable").DataTable({
      "responsive": true,
      "autoWidth": false,
      "columnDefs": [
        { "orderable": false, "targets": [1, 8] },
        { "searchable": false, "targets": [1] }
      ],
      "order": [[0, "desc"]]
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

<!-- ── Ingredient tag builder — shared helper ─────────────────── -->
<script>
  function makeIngredientBuilder(cfg) {
    var selected = [];

    function tagStyle(bg) {
      return {
        display: 'inline-flex', alignItems: 'center', gap: '5px',
        padding: '4px 10px', borderRadius: '20px',
        background: bg || 'rgba(233,30,140,0.12)',
        border: '1px solid rgba(233,30,140,0.35)',
        color: '#e91e8c', fontSize: '12.5px', fontWeight: '500', cursor: 'pointer'
      };
    }

    function renderTags() {
      var box = $('#' + cfg.tagsId);
      box.empty();
      if (!selected.length) {
        box.append('<span style="color:#aaa;font-size:12px;align-self:center;font-style:italic;">No ingredients added yet</span>');
      } else {
        $.each(selected, function (i, ing) {
          var label = ing.name + ' ' + ing.qty_needed + ing.unit;
          var tag = $('<span>').css(tagStyle())
            .attr('title', 'Click to remove')
            .data('idx', i)
            .html('<i class="fas fa-dot-circle" style="font-size:8px;opacity:0.6"></i> ' +
                  $('<span>').text(label).html() +
                  ' <i class="fas fa-times" style="font-size:9px;opacity:0.7;margin-left:2px"></i>')
            .on('click', function () { selected.splice($(this).data('idx'), 1); renderTags(); updateHidden(); })
            .hover(
              function () { $(this).css('background', 'rgba(233,30,140,0.25)'); },
              function () { $(this).css('background', 'rgba(233,30,140,0.12)'); }
            );
          box.append(tag);
        });
      }
    }

    function updateHidden() {
      var json = JSON.stringify(selected.map(function (ing) {
        return { ingredient_id: ing.ingredient_id, qty_needed: ing.qty_needed };
      }));
      $('#' + cfg.hiddenId).val(json);
    }

    function addIngredient() {
      var sel  = $('#' + cfg.selectId);
      var id   = parseInt(sel.val());
      var qty  = parseFloat($('#' + cfg.qtyId).val());
      var opt  = sel.find('option:selected');
      var name = opt.data('name');
      var unit = opt.data('unit') || '';

      if (!id)              { alert('Please select an ingredient.'); return; }
      if (!qty || qty <= 0) { alert('Please enter a valid quantity.'); return; }

      var exists = selected.some(function (i) { return i.ingredient_id === id; });
      if (exists) { alert('This ingredient is already added.'); return; }

      selected.push({ ingredient_id: id, name: name, qty_needed: qty, unit: unit });
      sel.val('');
      $('#' + cfg.qtyId).val('');
      renderTags();
      updateHidden();
    }

    $('#' + cfg.addBtnId).on('click', addIngredient);

    if (cfg.preloadFn) cfg.preloadFn(selected, renderTags, updateHidden);

    $('#' + cfg.modalId).on('hidden.bs.modal', function () {
      selected.length = 0;
      renderTags();
      updateHidden();
      $('#' + cfg.selectId).val('');
      $('#' + cfg.qtyId).val('');
    });

    renderTags();
  }

  $(function () {
    // ── ADD modal ingredient builder ──────────────────────────
    makeIngredientBuilder({
      selectId: 'ingredientSelect',
      qtyId:    'ingredientQty',
      addBtnId: 'addIngredientBtn',
      tagsId:   'ingredientTags',
      hiddenId: 'itemIngredients',
      modalId:  'addMenuModal'
    });

    // Reset full Add form on close (image clear is handled separately below)
    $('#addMenuModal').on('hidden.bs.modal', function () {
      $(this).find('form')[0].reset();
      // Re-sync the custom-file label after form reset
      $('#addImageInput').next('.custom-file-label').text('Choose image…');
      $('#addImagePreview').hide();
      $('#addImageThumb').attr('src', '');
    });

    // ── EDIT modal ────────────────────────────────────────────
    var editSelected = [];

    function editRenderTags() {
      var box = $('#editIngredientTags');
      box.empty();
      if (!editSelected.length) {
        box.append('<span style="color:#aaa;font-size:12px;align-self:center;font-style:italic;">No ingredients added yet</span>');
      } else {
        $.each(editSelected, function (i, ing) {
          var label = ing.name + ' ' + ing.qty_needed + ing.unit;
          var tag = $('<span>').css({
              display:'inline-flex',alignItems:'center',gap:'5px',
              padding:'4px 10px',borderRadius:'20px',
              background:'rgba(233,30,140,0.12)',
              border:'1px solid rgba(233,30,140,0.35)',
              color:'#e91e8c',fontSize:'12.5px',fontWeight:'500',cursor:'pointer'
            })
            .attr('title', 'Click to remove')
            .data('idx', i)
            .html('<i class="fas fa-dot-circle" style="font-size:8px;opacity:0.6"></i> ' +
                  $('<span>').text(label).html() +
                  ' <i class="fas fa-times" style="font-size:9px;opacity:0.7;margin-left:2px"></i>')
            .on('click', function () { editSelected.splice($(this).data('idx'), 1); editRenderTags(); editUpdateHidden(); })
            .hover(
              function () { $(this).css('background', 'rgba(233,30,140,0.25)'); },
              function () { $(this).css('background', 'rgba(233,30,140,0.12)'); }
            );
          box.append(tag);
        });
      }
    }

    function editUpdateHidden() {
      var json = JSON.stringify(editSelected.map(function (ing) {
        return { ingredient_id: ing.ingredient_id, qty_needed: ing.qty_needed };
      }));
      $('#editItemIngredients').val(json);
    }

    $('#editAddIngredientBtn').on('click', function () {
      var sel  = $('#editIngredientSelect');
      var id   = parseInt(sel.val());
      var qty  = parseFloat($('#editIngredientQty').val());
      var opt  = sel.find('option:selected');
      var name = opt.data('name');
      var unit = opt.data('unit') || '';

      if (!id)              { alert('Please select an ingredient.'); return; }
      if (!qty || qty <= 0) { alert('Please enter a valid quantity.'); return; }
      if (editSelected.some(function (i) { return i.ingredient_id === id; })) {
        alert('Already added.'); return;
      }
      editSelected.push({ ingredient_id: id, name: name, qty_needed: qty, unit: unit });
      sel.val(''); $('#editIngredientQty').val('');
      editRenderTags(); editUpdateHidden();
    });

    // Pre-fill Edit modal from server-side data
    $('#editMenuModal').on('show.bs.modal', function (event) {
      var btn = $(event.relatedTarget);
      $('#editId').val(btn.data('id'));
      $('#editName').val(btn.data('name'));
      $('#editCategory').val(btn.data('category'));
      $('#editPrice').val(btn.data('price'));
      $('#editStatus').val(String(btn.data('status')));   // cast to string so <select> option matches
      $('#editDescription').val(btn.data('description'));

      // Populate image fields
      var img = btn.data('image') || '';
      $('#editExistingImage').val(img);
      $('#editImageInput').val('');
      $('#editImageInput').next('.custom-file-label').text('Choose new image…');
      $('#editNewImagePreview').hide();
      if (img) {
        $('#editCurrentThumb').attr('src', '../' + img.replace('Frontend/', ''));
        $('#editCurrentImageWrap').show();
      } else {
        $('#editCurrentImageWrap').hide();
      }

      // Reset hidden field immediately so stale data never gets submitted if AJAX is slow
      $('#editItemIngredients').val('[]');
      editRenderTags();

      // Load existing ingredients via AJAX
      editSelected.length = 0;
      var menuId = btn.data('id');
      $.getJSON('../../Backend/menu_process.php', { action: 'get_ingredients', id: menuId }, function (res) {
        if (res.success && res.data) {
          $.each(res.data, function (i, ing) {
            editSelected.push({
              ingredient_id: ing.ingredient_id,
              name:          ing.name,
              qty_needed:    ing.qty_needed,
              unit:          ing.unit
            });
          });
          editRenderTags();
          editUpdateHidden();
        }
      });
    });

    $('#editMenuModal').on('hidden.bs.modal', function () {
      editSelected.length = 0;
      editRenderTags();
      editUpdateHidden();
      $('#editIngredientSelect').val('');
      $('#editIngredientQty').val('');
    });

    editRenderTags();

    // ── Image preview — Add modal ─────────────────────────────
    $('#addImageInput').on('change', function () {
      var file = this.files[0];
      if (file) {
        var reader = new FileReader();
        reader.onload = function (e) {
          $('#addImageThumb').attr('src', e.target.result);
          $('#addImagePreview').show();
        };
        reader.readAsDataURL(file);
        // update custom-file label
        $('#addImageInput').next('.custom-file-label').text(file.name);
      }
    });
    $('#addImageClear').on('click', function () {
      $('#addImageInput').val('');
      $('#addImageInput').next('.custom-file-label').text('Choose image…');
      $('#addImagePreview').hide();
      $('#addImageThumb').attr('src', '');
    });
    // ── Image preview — Edit modal ────────────────────────────
    $('#editImageInput').on('change', function () {
      var file = this.files[0];
      if (file) {
        var reader = new FileReader();
        reader.onload = function (e) {
          $('#editNewImageThumb').attr('src', e.target.result);
          $('#editNewImagePreview').show();
        };
        reader.readAsDataURL(file);
        $('#editImageInput').next('.custom-file-label').text(file.name);
      }
    });
    $('#editImageClear').on('click', function () {
      $('#editImageInput').val('');
      $('#editImageInput').next('.custom-file-label').text('Choose new image…');
      $('#editNewImagePreview').hide();
    });
    $('#editRemoveCurrentBtn').on('click', function () {
      // Clear existing image — backend will see empty existing_image and no new file → sets NULL
      $('#editExistingImage').val('');
      $('#editCurrentImageWrap').hide();
    });

  }); // end $(function)

  function showImagePreview(name, src) {
    $('#imgPreviewLabel').text(name);
    $('#imgPreviewFull').attr('src', src);
    $('#imagePreviewModal').modal('show');
  }

  function confirmDelete(id, name) {
    document.getElementById('deleteItemName').textContent = name;
    document.getElementById('deleteMenuId').value = id;
    var overlay = document.getElementById('deleteConfirmModal');
    var box = document.getElementById('deleteConfirmBox');
    overlay.style.display = 'flex';
    requestAnimationFrame(function(){ box.style.transform='scale(1) translateY(0)'; box.style.opacity='1'; });
  }
  function closeDeleteMenuModal() {
    var overlay = document.getElementById('deleteConfirmModal');
    var box = document.getElementById('deleteConfirmBox');
    box.style.transform='scale(.88) translateY(20px)'; box.style.opacity='0';
    setTimeout(function(){ overlay.style.display='none'; }, 280);
  }
  document.getElementById('deleteConfirmModal').addEventListener('click', function(e){
    if(e.target === this) closeDeleteMenuModal();
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

</body>
</html>