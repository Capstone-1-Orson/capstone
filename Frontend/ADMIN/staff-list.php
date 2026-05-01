<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['position'] !== 'admin') {
  header("Location: ../../Frontend/lockscreen.html"); 
  exit();
}
// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OPERLYTICS | Staff List</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <!-- overlayScrollbars -->
  <link rel="stylesheet" href="../plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">

  <link rel="stylesheet" href="../dist/css/empress-cafe-theme.css">
</head>
<style>
  body,
  .main-header.navbar {
    transition: background-color 0.5s ease, color 0.5s ease;
  }

  #darkModeToggle {
    transition: box-shadow 0.3s ease;
  }

  

  #darkModeToggle.clicked {
    box-shadow: 0 0 15px rgba(255, 255, 255, 0.8);
  }

  

  .password-strength-bar {
    height: 5px;
    border-radius: 3px;
    margin-top: 5px;
    transition: width 0.3s ease, background-color 0.3s ease;
    width: 0%;
  }

    
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

</style>

<body class="hold-transition dark-mode sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
  <div class="wrapper">

    <!-- Navbar -->
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
          <a class="nav-link" data-widget="navbar-search" href="#" role="button">
            <i class="fas fa-search"></i>
          </a>
          <div class="navbar-search-block">
            <form class="form-inline">
              <div class="input-group input-group-sm">
                <input class="form-control form-control-navbar" type="search" placeholder="Search" aria-label="Search">
                <div class="input-group-append">
                  <button class="btn btn-navbar" type="submit">
                    <i class="fas fa-search"></i>
                  </button>
                  <button class="btn btn-navbar" type="button" data-widget="navbar-search">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>
            </form>
          </div>
        </li>
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

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
      <a href="#" class="brand-link">
        <img src="../dist/img/Empress%27 Cafe Boracay.jpg" alt="AdminLTE Logo" class="brand-image img-circle elevation-3"
          style="opacity: .8">
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
              <a href="./suppliers.php" class="nav-link"><i class="nav-icon fas fa-truck"></i><p>Supplier Info</p></a>
            </li>
            <li class="nav-item">
              <a href="./staff-list.php" class="nav-link active"><i class="far fa-user nav-icon"></i><p>Staff List</p></a>
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

    <!-- Content Wrapper -->
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

      <!-- Content Header -->
      <div class="content-header">
        <div class="container-fluid">
          <div class="row mb-2">
            <div class="col-sm-6">
              <h1 class="m-0">Staff List</h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="#">Home</a></li>
                <li class="breadcrumb-item active">Staff List</li>
              </ol>
            </div>
          </div>
        </div>
      </div>

      <!-- Main content -->
      <section class="content">
        <div class="container-fluid">
          <div class="row">
            <div class="col-12">
              <div class="card">

                <div class="card-header">
                  <h3 class="card-title">Staff Directory</h3>
                  <button type="button" class="btn btn-success float-right" data-toggle="modal" data-target="#addUserModal">
                    + Add User
                  </button>
                </div>

                <div class="card-body table-responsive p-0">
                  <table class="table table-hover text-nowrap">
                    <thead>
                      <tr>
                        <!-- <th>ID</th> -->
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Password</th>
                        <th>Position</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Actions</th>
                      </tr>
                    </thead>

                    <tbody>
                      <?php
                      include('../../Backend/conn.php');
                      $result = mysqli_query($conn, "SELECT * FROM user WHERE position = 'staff'");
                      while ($row = mysqli_fetch_assoc($result)) {
                        $fullname = htmlspecialchars($row['firstname'] . ' ' . $row['lastname']);
                      ?>
                      <tr
                        data-id="<?= $row['id']; ?>"
                        data-firstname="<?= htmlspecialchars($row['firstname'], ENT_QUOTES); ?>"
                        data-lastname="<?= htmlspecialchars($row['lastname'], ENT_QUOTES); ?>"
                        data-email="<?= htmlspecialchars($row['email'], ENT_QUOTES); ?>"
                        data-position="<?= htmlspecialchars($row['position'], ENT_QUOTES); ?>"
                        data-contact="<?= htmlspecialchars($row['contact'], ENT_QUOTES); ?>"
                        data-address="<?= htmlspecialchars($row['address'], ENT_QUOTES); ?>"
                        data-image="<?= htmlspecialchars($row['image'] ?? '', ENT_QUOTES); ?>"
                      >
                        <!-- <td>*</td> -->
                        <td>
                          <?php if (!empty($row['image'])): ?>
                            <img src="../../<?= htmlspecialchars($row['image']); ?>" alt="Staff Photo" class="img-circle elevation-1" style="width:38px;height:38px;object-fit:cover;">
                          <?php else: ?>
                            <img src="../dist/img/user2-160x160.jpg" alt="No Photo" class="img-circle elevation-1" style="width:38px;height:38px;object-fit:cover;">
                          <?php endif; ?>
                        </td>
                        <td><?= $fullname; ?></td>
                        <td><?= htmlspecialchars($row['email']); ?></td>
                        <td>*****</td>
                        <td>
                          <?php if ($row['position'] == 'admin'): ?>
                            <span class='badge badge-danger'>Admin</span>
                          <?php else: ?>
                            <span class='badge badge-success'>Staff</span>
                          <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['contact']); ?></td>
                        <td><?= htmlspecialchars($row['address']); ?></td>
                        <td>
                          <button class="btn btn-sm btn-primary view-btn">View</button>
                        </td>
                      </tr>
                      <?php } ?>
                    </tbody>
                  </table>
                </div>

              </div>
            </div>
          </div>
        </div>
      </section>

    </div>
    <!-- /.content-wrapper -->

    <!-- ==================== ADD USER MODAL ==================== -->
    <div class="modal fade" id="addUserModal" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add New Staff Member</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <form action="../../Backend/process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="modal-body">

              <div class="form-group text-center">
                <label>Profile Photo</label><br>
                <img id="add_image_preview" src="../dist/img/blank.jpg"
                  class="img-circle elevation-2 mb-2"
                  style="width:90px;height:90px;object-fit:cover;cursor:pointer;"
                  onclick="document.getElementById('add_image_input').click()"
                  title="Click to upload photo">
                <br>
                <input type="file" id="add_image_input" name="image" accept="image/*" class="d-none"
                  onchange="previewImage(this,'add_image_preview')">
                <small class="text-muted">Click photo to upload (JPG, PNG, GIF, WEBP)</small>
              </div>

              <div class="form-group">
                <label>First Name</label>
                <input type="text" name="firstname" class="form-control" required>
              </div>

              <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="lastname" class="form-control" required>
              </div>

              <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control"
                  pattern="[a-zA-Z0-9._%+\-]+@(gmail|yahoo)\.(com|com\.ph)"
                  title="Only Gmail or Yahoo email addresses are allowed"
                  placeholder="example@gmail.com or example@yahoo.com" required>
              </div>

              <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" id="add_password" class="form-control" required
                  oninput="checkPasswordStrength(this.value, 'add_password_strength', 'add_password_bar')">
                <div class="password-strength-bar" id="add_password_bar"></div>
                <small id="add_password_strength" class="form-text"></small>
              </div>

              <div class="form-group">
                <label>Contact Number</label>
                <input type="tel" name="contact" class="form-control"
                  placeholder="e.g. 09123456789"
                  pattern="[0-9]{11}" maxlength="11"
                  title="Contact number must be exactly 11 digits"
                  oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)" required>
              </div>

              <div class="form-group">
                <label>Address</label>
                <textarea name="address" class="form-control" rows="3" placeholder="Enter full address" required></textarea>
              </div>

              <div class="form-group">
                <label>Position</label>
                <input type="text" class="form-control" value="staff" readonly>
                <input type="hidden" name="position" value="staff">
              </div>

            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
              <button type="submit" name="save_user" class="btn btn-primary">Save Staff</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ==================== VIEW / EDIT MODAL ==================== -->
    <div class="modal fade" id="viewUserModal" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Staff Details</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <form action="../../Backend/process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="modal-body">

              <input type="hidden" name="user_id" id="view_id">
              <input type="hidden" name="existing_image" id="view_existing_image">

              <div class="form-group text-center">
                <label>Profile Photo</label><br>
                <img id="view_image_preview" src="../dist/img/user2-160x160.jpg"
                  class="img-circle elevation-2 mb-2"
                  style="width:90px;height:90px;object-fit:cover;cursor:pointer;"
                  onclick="document.getElementById('view_image_input').click()"
                  title="Click to change photo">
                <br>
                <input type="file" id="view_image_input" name="image" accept="image/*" class="d-none"
                  onchange="previewImage(this,'view_image_preview')">
                <small class="text-muted">Click photo to change (leave as-is to keep current)</small>
              </div>

              <div class="form-group">
                <label>First Name</label>
                <input type="text" name="firstname" id="view_firstname" class="form-control" required>
              </div>

              <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="lastname" id="view_lastname" class="form-control" required>
              </div>

              <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" id="view_email" class="form-control"
                  pattern="[a-zA-Z0-9._%+\-]+@(gmail|yahoo)\.(com|com\.ph)"
                  title="Only Gmail or Yahoo email addresses are allowed" required>
              </div>

              <div class="form-group">
                <label>New Password <small class="text-muted">(leave blank to keep current)</small></label>
                <input type="password" name="password" id="view_password" class="form-control"
                  oninput="checkPasswordStrength(this.value, 'update_password_strength', 'update_password_bar')">
                <div class="password-strength-bar" id="update_password_bar"></div>
                <small id="update_password_strength" class="form-text"></small>
              </div>

              <div class="form-group">
                <label>Contact Number</label>
                <input type="tel" name="contact" id="view_contact" class="form-control"
                  pattern="[0-9]{11}" maxlength="11"
                  title="Contact number must be exactly 11 digits"
                  oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)" required>
              </div>

              <div class="form-group">
                <label>Address</label>
                <textarea name="address" id="view_address" class="form-control" rows="3" required></textarea>
              </div>

              <div class="form-group">
                <label>Position</label>
                <input type="text" class="form-control" value="staff" readonly>
                <input type="hidden" name="position" value="staff">
              </div>

            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-danger" id="deleteBtn">Delete</button>
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
              <button type="submit" name="update_user" class="btn btn-primary">Update</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ==================== DELETE CONFIRM MODAL ==================== -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirm Delete</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete <strong id="delete_name"></strong>?</p>
          </div>
          <div class="modal-footer">
            <form action="../../Backend/process.php" method="POST">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <input type="hidden" name="user_id" id="delete_id">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
              <button type="submit" name="delete_user" class="btn btn-danger">Yes, Delete</button>
            </form>
          </div>
        </div>
      </div>
    </div>

  </div>
  <!-- ./wrapper -->

  <!-- REQUIRED SCRIPTS -->
  <script src="../plugins/jquery/jquery.min.js"></script>
  <script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="../plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
  <script src="../dist/js/adminlte.js"></script>
  <script src="../dist/js/pages/dashboard2.js"></script>

  <script>
    // ── Password strength checker ────────────────────────────────
    function checkPasswordStrength(password, textId, barId) {
      const indicator = document.getElementById(textId);
      const bar       = document.getElementById(barId);
      let strength    = 0;

      if (password.length >= 8)           strength++;
      if (/[A-Z]/.test(password))         strength++;
      if (/[0-9]/.test(password))         strength++;
      if (/[^A-Za-z0-9]/.test(password))  strength++;

      if (password.length === 0) {
        indicator.textContent  = '';
        indicator.style.color  = '';
        bar.style.width        = '0%';
        bar.style.background   = 'transparent';
      } else if (strength <= 1) {
        indicator.textContent  = 'Weak';
        indicator.style.color  = '#dc3545';
        bar.style.width        = '25%';
        bar.style.background   = '#dc3545';
      } else if (strength === 2) {
        indicator.textContent  = 'Fair';
        indicator.style.color  = '#fd7e14';
        bar.style.width        = '50%';
        bar.style.background   = '#fd7e14';
      } else if (strength === 3) {
        indicator.textContent  = 'Medium';
        indicator.style.color  = '#ffc107';
        bar.style.width        = '75%';
        bar.style.background   = '#ffc107';
      } else {
        indicator.textContent  = 'Strong';
        indicator.style.color  = '#28a745';
        bar.style.width        = '100%';
        bar.style.background   = '#28a745';
      }
    }

    $(function () {

      // ── View button: populate modal ────────────────────────────
      $(document).on('click', '.view-btn', function () {
        var row = $(this).closest('tr');

        $('#view_id').val(row.data('id'));
        $('#view_firstname').val(row.data('firstname'));
        $('#view_lastname').val(row.data('lastname'));
        $('#view_email').val(row.data('email'));
        $('#view_contact').val(row.data('contact'));
        $('#view_address').val(row.data('address'));

        // Populate image
        var image = row.data('image');
        var defaultImg = '../dist/img/user2-160x160.jpg';
        $('#view_existing_image').val(image || '');
        $('#view_image_preview').attr('src', image ? '../../' + image : defaultImg);
        $('#view_image_input').val(''); // clear previous file selection

        // Clear password field and strength indicator when opening modal
        $('#view_password').val('');
        $('#update_password_strength').text('');
        $('#update_password_bar').css({ width: '0%', background: 'transparent' });

        $('#viewUserModal').modal('show');
      });

      // ── Delete button: open confirm modal ─────────────────────
      $('#deleteBtn').on('click', function () {
        var id        = $('#view_id').val();
        var firstname = $('#view_firstname').val();
        var lastname  = $('#view_lastname').val();

        $('#delete_id').val(id);
        $('#delete_name').text(firstname + ' ' + lastname);

        $('#viewUserModal').modal('hide');
        $('#deleteConfirmModal').modal('show');
      });

      // ── Dark mode toggle ───────────────────────────────────────
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
        const isDark = $('body').hasClass('dark-mode');
        localStorage.setItem('darkMode', isDark);
        $(this).addClass('clicked');
        setTimeout(() => $(this).removeClass('clicked'), 300);
      });

    });

    function previewImage(input, previewId) {
      if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) {
          document.getElementById(previewId).src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
      }
    }
  </script>

</body>
</html>