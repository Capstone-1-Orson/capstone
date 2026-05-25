<?php
// Frontend/ADMIN/staff-list.php  (OOP refactored)
require_once '../../Frontend/Core/StaffView.php';
$view = new StaffView();   // dispatches ?rt_staff and ?rt_add_staff AJAX early

// Variable aliases
$staffRows  = $view->staffRows;
$csrf_token = $view->csrfToken;

// Keep $_SESSION['csrf_token'] in sync (used by forms in the HTML)
$_SESSION['csrf_token'] = $csrf_token;
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
<style>
@keyframes rtPulse{0%{box-shadow:0 0 0 0 rgba(34,197,94,.55)}70%{box-shadow:0 0 0 7px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
</style>
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
             <a href="#" class="d-block"><?= htmlspecialchars($_SESSION['user']['firstname'] ?? 'Admin') ?></a>
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
            <li class="nav-item"><a href="./void_refund.php"     class="nav-link"><i class="nav-icon fas fa-undo-alt"></i><p>Void &amp; Refund</p></a></li>
          <li class="nav-item"><a href="./settings.php" class="nav-link"><i class="nav-icon fas fa-cog"></i><p>Settings</p></a></li>
            <li class="nav-item mt-auto">
              <a href="../../Backend/Controllers/LogoutController.php" class="nav-link"><i class="nav-icon fas fa-sign-out-alt"></i><p>Log Out</p></a>
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
          <i class="fas fa-check-circle mr-2"></i><?= $_SESSION['success'] ?>
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php unset($_SESSION['success']); ?>
      <?php endif; ?>
      <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mx-3 mt-3" role="alert">
          <i class="fas fa-exclamation-circle mr-2"></i><?= $_SESSION['error'] ?>
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

                <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                  <h3 class="card-title mb-0 mr-2">Staff Directory</h3>
                  <!-- Live indicator -->
                  <span style="display:inline-flex;align-items:center;gap:5px;font-size:.78rem;opacity:.85;">
                    <span class="rt-staff-dot" style="
                      display:inline-block;width:9px;height:9px;border-radius:50%;
                      background:#6b7280;animation:rtPulse 1.8s infinite;transition:background .4s;
                    " title="Connecting..."></span>
                    <span class="rt-staff-label" style="color:#6b7280;">connecting...</span>
                  </span>
                  <small class="rt-staff-updated text-muted ml-1" style="font-size:.75rem;"></small>
                  <button type="button" class="btn btn-success ml-auto" data-toggle="modal" data-target="#addUserModal">
                    + Add User
                  </button>
                </div>

                <div class="card-body table-responsive p-0">
                  <table id="staffTable" class="table table-hover text-nowrap">
                    <thead>
                      <tr>
                        <!-- <th>ID</th> -->
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Verified</th>
                        <th>Password</th>
                        <th>Position</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Actions</th>
                      </tr>
                    </thead>

                    <tbody id="staffTableBody">
                      <?php foreach ($staffRows as $row): ?>
                      <?php $fullname = htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?>
                      <tr
                        data-id="<?= $row['id']; ?>"
                        data-firstname="<?= htmlspecialchars($row['firstname'], ENT_QUOTES); ?>"
                        data-lastname="<?= htmlspecialchars($row['lastname'], ENT_QUOTES); ?>"
                        data-email="<?= htmlspecialchars($row['email'], ENT_QUOTES); ?>"
                        data-position="<?= htmlspecialchars($row['position'], ENT_QUOTES); ?>"
                        data-contact="<?= htmlspecialchars($row['contact'], ENT_QUOTES); ?>"
                        data-address="<?= htmlspecialchars($row['address'], ENT_QUOTES); ?>"
                        data-image="<?= htmlspecialchars($row['image'] ?? '', ENT_QUOTES); ?>"
                        data-verified="<?= intval($row['email_verified'] ?? 0); ?>"
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
                        <td>
                          <?php if (!empty($row['email_verified'])): ?>
                            <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Verified</span>
                          <?php else: ?>
                            <span class="badge badge-warning text-dark"><i class="fas fa-envelope mr-1"></i>Unverified</span>
                          <?php endif; ?>
                        </td>
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
                        <td class="d-flex flex-wrap gap-1" style="gap:4px;">
                          <button class="btn btn-sm btn-primary view-btn">
                            <i class="fas fa-eye mr-1"></i>View
                          </button>
                          <?php if (empty($row['email_verified'])): ?>
                          <button type="button"
                            class="btn btn-sm btn-warning text-dark resend-trigger"
                            data-email="<?= htmlspecialchars($row['email'], ENT_QUOTES) ?>"
                            data-userid="<?= $row['id'] ?>"
                            data-csrf="<?= $_SESSION['csrf_token'] ?>"
                            title="Resend verification email">
                            <i class="fas fa-envelope mr-1"></i>Resend
                          </button>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <?php endforeach; ?>
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
          <form id="addUserForm" action="../../Backend/Controllers/StaffController.php" method="POST" enctype="multipart/form-data" onsubmit="return submitAddStaff(event)">
            <input type="hidden" name="csrf_token" id="addUserCsrf" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="modal-body">
              <!-- Upload progress bar -->
              <div id="addStaffProgress" style="display:none;margin-bottom:12px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                  <span id="addStaffProgressLabel" style="font-size:.82rem;font-weight:600;"></span>
                  <span id="addStaffProgressPct" style="font-size:.78rem;margin-left:auto;color:#e91e8c;font-weight:700;"></span>
                </div>
                <div style="background:rgba(0,0,0,.12);border-radius:6px;height:6px;overflow:hidden;">
                  <div id="addStaffProgressBar" style="height:100%;width:0%;background:linear-gradient(90deg,#e91e8c,#9c27b0);border-radius:6px;transition:width .2s;"></div>
                </div>
              </div>
              <!-- Alert area -->
              <div id="addStaffAlert" style="display:none;" class="alert alert-dismissible fade show" role="alert">
                <span id="addStaffAlertMsg"></span>
                <button type="button" class="close" onclick="document.getElementById('addStaffAlert').style.display='none'">&times;</button>
              </div>

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
              <button type="button" class="btn btn-secondary" data-dismiss="modal" id="addUserCloseBtn">Close</button>
              <button type="submit" id="addUserSaveBtn" class="btn btn-primary">
                <span id="addUserSaveBtnIcon"><i class="fas fa-user-plus mr-1"></i></span>Save Staff
              </button>
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
          <form action="../../Backend/Controllers/StaffController.php" method="POST" enctype="multipart/form-data">
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
            <div style="font-family:'DM Sans',sans-serif;font-weight:700;font-size:1rem;color:#fff;line-height:1.2;">Delete Staff</div>
            <div style="font-family:'DM Sans',sans-serif;font-size:.75rem;color:rgba(255,255,255,.45);margin-top:2px;">Confirm before deleting</div>
          </div>
          <button onclick="closeDeleteModal()" style="
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
            <i class="fas fa-user" style="color:#e91e8c;font-size:.85rem;"></i>
            <span id="delete_name" style="font-family:'DM Sans',sans-serif;font-weight:600;color:#fff;font-size:.9rem;word-break:break-all;"></span>
          </div>
          <p style="font-family:'DM Sans',sans-serif;color:rgba(255,255,255,.35);font-size:.75rem;margin:8px 0 0;">
            <i class="fas fa-info-circle mr-1"></i>This cannot be undone.
          </p>
        </div>
        <!-- Footer -->
        <div style="padding:16px 22px 20px;display:flex;gap:10px;justify-content:flex-end;">
          <button onclick="closeDeleteModal()" style="
            font-family:'DM Sans',sans-serif;font-weight:600;font-size:.82rem;
            background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);
            color:rgba(255,255,255,.65);border-radius:22px;padding:8px 20px;cursor:pointer;
            transition:background .2s,color .2s;
          " onmouseover="this.style.background='rgba(255,255,255,.13)';this.style.color='#fff'"
             onmouseout="this.style.background='rgba(255,255,255,.07)';this.style.color='rgba(255,255,255,.65)'">
            Cancel
          </button>
          <form action="../../Backend/Controllers/StaffController.php" method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="user_id" id="delete_id">
            <button type="submit" name="delete_user" style="
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

        document.getElementById('delete_id').value = id;
        document.getElementById('delete_name').textContent = firstname + ' ' + lastname;

        $('#viewUserModal').modal('hide');

        var overlay = document.getElementById('deleteConfirmModal');
        var box = document.getElementById('deleteConfirmBox');
        overlay.style.display = 'flex';
        requestAnimationFrame(function(){ box.style.transform='scale(1) translateY(0)'; box.style.opacity='1'; });
      });

      document.getElementById('deleteConfirmModal').addEventListener('click', function(e){
        if(e.target === this) closeDeleteModal();
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

    /* ── Real-time Add Staff (AJAX + XHR upload progress) ──────────── */
    function submitAddStaff(e) {
      e.preventDefault();

      var form      = document.getElementById('addUserForm');
      var saveBtn   = document.getElementById('addUserSaveBtn');
      var closeBtn  = document.getElementById('addUserCloseBtn');
      var alertBox  = document.getElementById('addStaffAlert');
      var alertMsg  = document.getElementById('addStaffAlertMsg');
      var progress  = document.getElementById('addStaffProgress');
      var progBar   = document.getElementById('addStaffProgressBar');
      var progPct   = document.getElementById('addStaffProgressPct');
      var progLabel = document.getElementById('addStaffProgressLabel');

      // Reset alert
      alertBox.style.display = 'none';
      alertBox.className     = 'alert alert-dismissible fade show';

      // Disable inputs while uploading
      saveBtn.disabled  = true;
      closeBtn.disabled = true;
      saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Uploading…';

      // Show progress bar
      progBar.style.width   = '0%';
      progPct.textContent   = '0%';
      progLabel.textContent = 'Uploading…';
      progress.style.display = 'block';

      var fd = new FormData(form);
      var xhr = new XMLHttpRequest();

      xhr.open('POST', window.location.pathname + '?rt_add_staff=1', true);
      xhr.withCredentials = true;

      // Upload progress
      xhr.upload.onprogress = function (ev) {
        if (ev.lengthComputable) {
          var pct = Math.round((ev.loaded / ev.total) * 100);
          progBar.style.width = pct + '%';
          progPct.textContent = pct + '%';
          progLabel.textContent = pct < 100 ? 'Uploading…' : 'Processing…';
        }
      };

      xhr.onload = function () {
        saveBtn.disabled  = false;
        closeBtn.disabled = false;

        var resp;
        try {
          resp = JSON.parse(xhr.responseText);
        } catch(ex) {
          // Strip HTML tags from PHP error output for a readable message
          var rawText = xhr.responseText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
          resp = { success: false, message: 'Server error: ' + (rawText.substring(0, 200) || 'No response.') };
        }

        if (xhr.status === 200 && resp.success) {
          // Finish progress bar
          progBar.style.width = '100%';
          progPct.textContent = '100%';
          progLabel.textContent = 'Done!';

          // Update CSRF token in page
          if (resp.new_csrf) {
            document.getElementById('addUserCsrf').value = resp.new_csrf;
            // Also update all other CSRF hidden inputs & data-csrf buttons
            document.querySelectorAll('input[name="csrf_token"]').forEach(function(el){ el.value = resp.new_csrf; });
            document.querySelectorAll('[data-csrf]').forEach(function(el){ el.setAttribute('data-csrf', resp.new_csrf); });
          }

          // Show success, reset form, close modal after short delay
          saveBtn.innerHTML = '<i class="fas fa-check mr-1"></i>Added!';
          setTimeout(function () {
            progress.style.display = 'none';
            progBar.style.width    = '0%';
            saveBtn.innerHTML = '<span id="addUserSaveBtnIcon"><i class="fas fa-user-plus mr-1"></i></span>Save Staff';
            form.reset();
            document.getElementById('add_image_preview').src = '../dist/img/blank.jpg';
            $('#addUserModal').modal('hide');
            // Immediately poll for new staff row
            clearTimeout(window._staffPollTimer);
            window._staffPollTimer = setTimeout(window._staffPoll || function(){}, 300);
          }, 900);

        } else {
          // Show error inside modal
          progress.style.display = 'none';
          alertMsg.textContent   = resp.message || 'An error occurred.';
          alertBox.classList.add('alert-danger');
          alertBox.style.display = 'block';
          saveBtn.innerHTML = '<span id="addUserSaveBtnIcon"><i class="fas fa-user-plus mr-1"></i></span>Save Staff';
        }
      };

      xhr.onerror = function () {
        saveBtn.disabled  = false;
        closeBtn.disabled = false;
        progress.style.display = 'none';
        alertMsg.textContent   = 'Network error — please try again.';
        alertBox.classList.add('alert-danger');
        alertBox.style.display = 'block';
        saveBtn.innerHTML = '<span id="addUserSaveBtnIcon"><i class="fas fa-user-plus mr-1"></i></span>Save Staff';
      };

      xhr.send(fd);
      return false;
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

<!-- ══ STAFF LIST REAL-TIME POLLING ════════════════════════════════ -->
<script>
(function () {
  'use strict';

  var POLL_MS   = 10000; // poll every 10 s
  var CSRF      = <?= json_encode($_SESSION['csrf_token']) ?>;
  var _snapshot = null;  // JSON string of last staff array for diff
  var _timer    = null;
  window._staffPollTimer = null;

  /* ── helpers ───────────────────────────────────────────────────── */
  function setStatus(ok, label) {
    document.querySelectorAll('.rt-staff-dot').forEach(function (el) {
      el.style.background = ok ? '#22c55e' : '#ef4444';
      el.title = ok ? 'Live — auto-refreshing' : 'Reconnecting...';
    });
    document.querySelectorAll('.rt-staff-label').forEach(function (el) {
      el.textContent = ok ? 'live' : 'reconnecting...';
      el.style.color  = ok ? '#22c55e' : '#ef4444';
    });
  }

  function setUpdated() {
    var now = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    document.querySelectorAll('.rt-staff-updated').forEach(function (el) {
      el.textContent = 'Updated ' + now;
    });
  }

  function escHtml(s) {
    return String(s || '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  /* ── build a single <tr> from a staff object ───────────────────── */
  function buildRow(s) {
    var img = s.image
      ? '<img src="../../' + escHtml(s.image) + '" class="img-circle elevation-1" style="width:38px;height:38px;object-fit:cover;">'
      : '<img src="../dist/img/user2-160x160.jpg" class="img-circle elevation-1" style="width:38px;height:38px;object-fit:cover;">';

    var verifiedBadge = s.email_verified
      ? '<span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Verified</span>'
      : '<span class="badge badge-warning text-dark"><i class="fas fa-envelope mr-1"></i>Unverified</span>';

    var posBadge = s.position === 'admin'
      ? '<span class="badge badge-danger">Admin</span>'
      : '<span class="badge badge-success">Staff</span>';

    var resendBtn = !s.email_verified
      ? '<button type="button" class="btn btn-sm btn-warning text-dark resend-trigger"' +
          ' data-email="' + escHtml(s.email) + '"' +
          ' data-userid="' + s.id + '"' +
          ' data-csrf="' + escHtml(CSRF) + '">' +
          '<i class="fas fa-envelope mr-1"></i>Resend</button>'
      : '';

    var tr = document.createElement('tr');
    tr.setAttribute('data-id',        s.id);
    tr.setAttribute('data-firstname',  escHtml(s.firstname));
    tr.setAttribute('data-lastname',   escHtml(s.lastname));
    tr.setAttribute('data-email',      escHtml(s.email));
    tr.setAttribute('data-position',   escHtml(s.position));
    tr.setAttribute('data-contact',    escHtml(s.contact));
    tr.setAttribute('data-address',    escHtml(s.address));
    tr.setAttribute('data-image',      escHtml(s.image));
    tr.setAttribute('data-verified',   s.email_verified);

    tr.innerHTML =
      '<td>' + img + '</td>' +
      '<td>' + escHtml(s.firstname + ' ' + s.lastname) + '</td>' +
      '<td>' + escHtml(s.email) + '</td>' +
      '<td>' + verifiedBadge + '</td>' +
      '<td>*****</td>' +
      '<td>' + posBadge + '</td>' +
      '<td>' + escHtml(s.contact) + '</td>' +
      '<td>' + escHtml(s.address) + '</td>' +
      '<td class="d-flex flex-wrap" style="gap:4px;">' +
        '<button class="btn btn-sm btn-primary view-btn"><i class="fas fa-eye mr-1"></i>View</button>' +
        resendBtn +
      '</td>';

    return tr;
  }

  /* ── diff & patch tbody ────────────────────────────────────────── */
  function applyStaff(staffArr) {
    var newSnap = JSON.stringify(staffArr);
    if (newSnap === _snapshot) return; // nothing changed
    _snapshot = newSnap;

    var tbody = document.getElementById('staffTableBody');
    if (!tbody) return;

    /* build a map of existing rows by id */
    var existing = {};
    tbody.querySelectorAll('tr[data-id]').forEach(function (tr) {
      existing[tr.getAttribute('data-id')] = tr;
    });

    var newIds = staffArr.map(function (s) { return String(s.id); });

    /* remove rows no longer present */
    Object.keys(existing).forEach(function (id) {
      if (newIds.indexOf(id) === -1) {
        existing[id].remove();
        delete existing[id];
      }
    });

    /* add/update rows in order */
    staffArr.forEach(function (s, idx) {
      var id  = String(s.id);
      var newTr = buildRow(s);

      if (!existing[id]) {
        /* new row — flash it */
        newTr.style.transition = 'background-color 1.2s';
        newTr.style.backgroundColor = 'rgba(34,197,94,.25)';
        tbody.appendChild(newTr);
        setTimeout(function () { newTr.style.backgroundColor = ''; }, 1500);
      } else {
        /* check if data changed */
        var oldSnip = existing[id].innerHTML;
        if (oldSnip !== newTr.innerHTML) {
          existing[id].innerHTML  = newTr.innerHTML;
          /* copy data attrs */
          Array.from(newTr.attributes).forEach(function (attr) {
            existing[id].setAttribute(attr.name, attr.value);
          });
          existing[id].style.transition       = 'background-color 1.2s';
          existing[id].style.backgroundColor  = 'rgba(99,102,241,.2)';
          setTimeout(function () { existing[id].style.backgroundColor = ''; }, 1500);
        }
        /* ensure correct DOM order */
        var rows = tbody.children;
        if (rows[idx] !== existing[id]) {
          tbody.insertBefore(existing[id], rows[idx] || null);
        }
      }
    });
  }

  /* ── fetch once ────────────────────────────────────────────────── */
  function poll() {
    fetch(window.location.pathname + '?rt_staff=1', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
      .then(function (data) {
        setStatus(true);
        setUpdated();
        applyStaff(data.staff || []);
      })
      .catch(function () {
        setStatus(false);
      })
      .finally(function () {
        _timer = setTimeout(poll, POLL_MS);
        window._staffPollTimer = _timer;
      });
  }

  /* ── start after DOM ready ─────────────────────────────────────── */
  window._staffPoll = poll; // expose for external callers (e.g. real-time add)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', poll);
  } else {
    poll();
  }

  /* ── pause polling while a modal is open (avoids flicker) ──────── */
  document.addEventListener('show.bs.modal',  function () { clearTimeout(_timer); });
  document.addEventListener('shown.bs.modal', function () { clearTimeout(_timer); });
  document.addEventListener('hidden.bs.modal', function () {
    clearTimeout(_timer);
    _timer = setTimeout(poll, 1000); // quick refresh after modal closes
  });

})();
</script>
<!-- ══ END staff real-time ══════════════════════════════════════════ -->

<!-- ══ RESEND CONFIRM MODAL ════════════════════════════════════════ -->
<div id="resendConfirmModal" style="
  display:none;position:fixed;inset:0;z-index:99998;
  background:rgba(0,0,0,.65);backdrop-filter:blur(6px);
  align-items:center;justify-content:center;
">
  <div id="resendModalBox" style="
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
      "><i class="fas fa-envelope"></i></div>
      <div>
        <div style="font-family:'DM Sans',sans-serif;font-weight:700;font-size:1rem;color:#fff;line-height:1.2;">Resend Verification Email</div>
        <div style="font-family:'DM Sans',sans-serif;font-size:.75rem;color:rgba(255,255,255,.45);margin-top:2px;">Confirm before sending</div>
      </div>
      <button onclick="closeResendModal()" style="
        margin-left:auto;background:none;border:none;cursor:pointer;
        color:rgba(255,255,255,.4);font-size:1.2rem;line-height:1;
        transition:color .2s;padding:2px 6px;border-radius:6px;
      " onmouseover="this.style.color='#e91e8c'" onmouseout="this.style.color='rgba(255,255,255,.4)'">&#x2715;</button>
    </div>

    <!-- Body -->
    <div style="padding:22px 22px 8px;">
      <p style="font-family:'DM Sans',sans-serif;color:rgba(255,255,255,.7);font-size:.875rem;margin:0 0 6px;">
        Send a new verification link to:
      </p>
      <div style="
        background:rgba(233,30,140,.1);border:1px solid rgba(233,30,140,.25);
        border-radius:10px;padding:10px 14px;margin-bottom:4px;
        display:flex;align-items:center;gap:10px;
      ">
        <i class="fas fa-at" style="color:#e91e8c;font-size:.85rem;"></i>
        <span id="resendEmailDisplay" style="font-family:'DM Sans',sans-serif;font-weight:600;color:#fff;font-size:.9rem;word-break:break-all;"></span>
      </div>
      <p style="font-family:'DM Sans',sans-serif;color:rgba(255,255,255,.35);font-size:.75rem;margin:8px 0 0;">
        <i class="fas fa-info-circle mr-1"></i>The previous link will be invalidated.
      </p>
    </div>

    <!-- Footer -->
    <div style="padding:16px 22px 20px;display:flex;gap:10px;justify-content:flex-end;">
      <button onclick="closeResendModal()" style="
        font-family:'DM Sans',sans-serif;font-weight:600;font-size:.82rem;
        background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);
        color:rgba(255,255,255,.65);border-radius:22px;padding:8px 20px;cursor:pointer;
        transition:background .2s,color .2s;
      " onmouseover="this.style.background='rgba(255,255,255,.13)';this.style.color='#fff'"
         onmouseout="this.style.background='rgba(255,255,255,.07)';this.style.color='rgba(255,255,255,.65)'">
        Cancel
      </button>
      <button id="resendConfirmBtn" onclick="submitResend()" style="
        font-family:'DM Sans',sans-serif;font-weight:700;font-size:.82rem;
        background:linear-gradient(135deg,#f59e0b 0%,#e91e8c 100%);
        border:none;color:#fff;border-radius:22px;padding:8px 24px;cursor:pointer;
        box-shadow:0 4px 16px rgba(233,30,140,.4);
        transition:transform .18s,box-shadow .18s;
        display:flex;align-items:center;gap:8px;
      " onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 22px rgba(233,30,140,.55)'"
         onmouseout="this.style.transform='';this.style.boxShadow='0 4px 16px rgba(233,30,140,.4)'">
        <i class="fas fa-paper-plane"></i> Send Email
      </button>
    </div>
  </div>
</div>


<!-- ══ RESEND SUCCESS TOAST ══════════════════════════════════════════ -->
<div id="resendToast" style="
  display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(20px);
  z-index:99999;min-width:300px;max-width:380px;
  background:linear-gradient(135deg,#f59e0b 0%,#e91e8c 60%,#9c27b0 100%);
  color:#fff;border-radius:16px;
  box-shadow:0 12px 40px rgba(233,30,140,.5),0 4px 12px rgba(0,0,0,.3);
  padding:0;font-family:'DM Sans',sans-serif;
  opacity:0;transition:opacity .3s,transform .35s cubic-bezier(.34,1.56,.64,1);
  overflow:hidden;
">
  <!-- Progress bar -->
  <div id="resendToastBar" style="height:3px;background:rgba(255,255,255,.4);width:100%;transform-origin:left;"></div>
  <div style="padding:14px 18px;display:flex;align-items:center;gap:13px;">
    <!-- Animated icon -->
    <div style="
      width:40px;height:40px;border-radius:50%;flex-shrink:0;
      background:rgba(255,255,255,.2);
      display:flex;align-items:center;justify-content:center;font-size:1.1rem;
    ">
      <i class="fas fa-check" id="resendToastIcon"></i>
    </div>
    <div style="flex:1;">
      <div style="font-weight:700;font-size:.9rem;margin-bottom:2px;">Email Sent!</div>
      <div id="resendToastMsg" style="font-size:.78rem;opacity:.88;"></div>
    </div>
    <button onclick="hideResendToast()" style="
      background:none;border:none;color:rgba(255,255,255,.7);font-size:1rem;
      cursor:pointer;padding:2px 4px;line-height:1;transition:color .2s;flex-shrink:0;
    " onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.7)'">&#x2715;</button>
  </div>
</div>

<style>
/* ── Sending spinner state ──────────────────────────────────────── */
@keyframes resendSpin { to { transform: rotate(360deg); } }
.resend-sending { animation: resendSpin .7s linear infinite !important; display:inline-block; }
@keyframes toastBarShrink { from { transform: scaleX(1); } to { transform: scaleX(0); } }
</style>

<script>
/* ══ Resend confirm + toast ════════════════════════════════════════ */
var _resendData = {};
var _toastTimer = null;

/* ── Open modal ────────────────────────────────────────────────── */
document.addEventListener('click', function(e) {
  var btn = e.target.closest('.resend-trigger');
  if (!btn) return;
  _resendData = {
    email:  btn.dataset.email,
    userid: btn.dataset.userid,
    csrf:   btn.dataset.csrf
  };
  document.getElementById('resendEmailDisplay').textContent = _resendData.email;
  var overlay = document.getElementById('resendConfirmModal');
  var box     = document.getElementById('resendModalBox');
  overlay.style.display = 'flex';
  // Force reflow then animate in
  requestAnimationFrame(function(){
    requestAnimationFrame(function(){
      box.style.transform = 'scale(1) translateY(0)';
      box.style.opacity   = '1';
    });
  });
  // Reset confirm button
  var btn2 = document.getElementById('resendConfirmBtn');
  btn2.disabled = false;
  btn2.innerHTML = '<i class="fas fa-paper-plane"></i> Send Email';
});


/* ── Close delete modal ────────────────────────────────────────── */
function closeDeleteModal() {
  var overlay = document.getElementById('deleteConfirmModal');
  var box     = document.getElementById('deleteConfirmBox');
  box.style.transform = 'scale(.88) translateY(20px)';
  box.style.opacity   = '0';
  setTimeout(function(){ overlay.style.display = 'none'; }, 280);
}

/* ── Close modal ───────────────────────────────────────────────── */
function closeResendModal() {
  var overlay = document.getElementById('resendConfirmModal');
  var box     = document.getElementById('resendModalBox');
  box.style.transform = 'scale(.88) translateY(20px)';
  box.style.opacity   = '0';
  setTimeout(function(){ overlay.style.display = 'none'; }, 280);
}

/* Close on backdrop click */
document.getElementById('resendConfirmModal').addEventListener('click', function(e){
  if (e.target === this) closeResendModal();
});

/* ── Submit resend ─────────────────────────────────────────────── */
function submitResend() {
  var btn = document.getElementById('resendConfirmBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-envelope resend-sending"></i> Sending…';

  fetch(window.location.pathname + '?rt_resend_verify=1', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'csrf_token=' + encodeURIComponent(_resendData.csrf) +
          '&staff_id='  + encodeURIComponent(_resendData.userid)
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    closeResendModal();
    if (data.success) {
      showResendToast(_resendData.email);
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Email';
      alert('Error: ' + (data.message || 'Could not send email.'));
    }
  })
  .catch(function() {
    closeResendModal();
    alert('Network error — please try again.');
  });
}

/* ── Show toast ────────────────────────────────────────────────── */
function showResendToast(email) {
  var toast = document.getElementById('resendToast');
  var bar   = document.getElementById('resendToastBar');
  var msg   = document.getElementById('resendToastMsg');
  if (!toast) return;

  msg.textContent = 'Verification link sent to ' + email;
  toast.style.display   = 'block';
  bar.style.animation   = 'none';
  bar.style.transform   = 'scaleX(1)';

  clearTimeout(_toastTimer);
  requestAnimationFrame(function(){
    requestAnimationFrame(function(){
      toast.style.opacity   = '1';
      toast.style.transform = 'translateX(-50%) translateY(0)';
      bar.style.animation   = 'toastBarShrink 5s linear forwards';
    });
  });

  _toastTimer = setTimeout(hideResendToast, 5000);
}

function hideResendToast() {
  var toast = document.getElementById('resendToast');
  toast.style.opacity   = '0';
  toast.style.transform = 'translateX(-50%) translateY(20px)';
  setTimeout(function(){ toast.style.display = 'none'; }, 350);
}
</script>
<!-- ══ END resend notification ══════════════════════════════════════ -->

</body>
</html>