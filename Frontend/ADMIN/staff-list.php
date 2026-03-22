<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../../Frontend/lockscreen.html"); 
  exit();
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

  #darkModeToggle i {
    transition: transform 0.3s ease;
  }

  #darkModeToggle.clicked {
    box-shadow: 0 0 15px rgba(255, 255, 255, 0.8);
  }

  #darkModeToggle.clicked i {
    transform: rotate(180deg) scale(1.2);
  }
</style>

<body class="hold-transition dark-mode sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
  <div class="wrapper">

    <!-- Preloader -->
    

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-dark">
      <!-- Left navbar links -->
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
          <a href="index2.php" class="nav-link">Home</a>
        </li>
      </ul>

      <!-- Right navbar links -->
      <ul class="navbar-nav ml-auto">
        <!-- Navbar Search -->
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
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
      <!-- Brand Logo -->
      <a href="#" class="brand-link">
        <img src="../dist/img/Empress' Cafe Boracay.jpg" alt="AdminLTE Logo" class="brand-image img-circle elevation-3"
          style="opacity: .8">
        <span class="brand-text font-weight-light">Empress' Cafe</span>
      </a>


      <!-- Sidebar -->
      <div class="sidebar">
        <!-- Sidebar user panel (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
          <div class="image">
            <img src="../dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
          </div>
          <div class="info">
            <a href="#" class="d-block">Alexander Pierce</a>
          </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
          <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
            <li class="nav-item">
              <a href="./index2.php" class="nav-link">
                <i class="nav-icon fas fa-tachometer-alt"></i>
                <p>Overview</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="./menu-management.php" class="nav-link">
                <i class="nav-icon fas fa-utensils"></i>
                <p>Menu Management</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="./inventory.php" class="nav-link">
                <i class="nav-icon fas fa-boxes"></i>
                <p>Inventory Tracking</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="./suppliers.php" class="nav-link">
                <i class="nav-icon fas fa-truck"></i>
                <p>Suppliers Orders</p>
              </a>
            </li>

            <li class="nav-item">
              <a href="./staff-list.php" class="nav-link active">
                <i class="far fa-user nav-icon"></i>
                <p>Staff List</p>
              </a>
            </li>

            <li class="nav-item">
              <a href="./sale_revenue.php" class="nav-link">
                <i class="nav-icon fas fa-chart-line"></i>
                <p>Sales & Revenue</p>
              </a>
            </li>

            <li class="nav-item">
              <a href="./report.php" class="nav-link">
                <i class="nav-icon fas fa-file-alt"></i>
                <p>Reports</p>
              </a>
            </li>

            <li class="nav-item mt-auto">
              <a href="../../Backend/logout.php" class="nav-link">
                  <i class="nav-icon fas fa-cog"></i>
                  <p>Log Out</p>
              </a>
            </li>
          </ul>
        </nav>
        <!-- /.sidebar-menu -->
      </div>
      <!-- /.sidebar -->
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
      <!-- Content Header (Page header) -->
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
                        <th>ID</th>
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

                      $result = mysqli_query($conn, "SELECT * FROM user");

                      while ($row = mysqli_fetch_assoc($result)) {
                        $fullname = $row['firstname'] . ' ' . $row['lastname'];
                      ?>
                      <tr
                        data-id="<?= $row['id']; ?>"
                        data-firstname="<?= $row['firstname']; ?>"
                        data-lastname="<?= $row['lastname']; ?>"
                        data-email="<?= $row['email']; ?>"
                        data-position="<?= $row['position']; ?>"
                        data-contact="<?= $row['contact']; ?>"
                        data-address="<?= $row['address']; ?>"
                      >
                        <td><?= $row['id']; ?></td>
                        <td><?= $fullname; ?></td>
                        <td><?= $row['email']; ?></td>
                        <td>****</td>

                        <td>
                          <?php 
                          if ($row['position'] == 'admin') {
                            echo "<span class='badge badge-danger'>Admin</span>";
                          } else {
                            echo "<span class='badge badge-success'>Staff</span>";
                          }
                          ?>
                        </td>

                        <td><?= $row['contact']; ?></td>
                        <td><?= $row['address']; ?></td>

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
      <div class="modal fade" id="addUserModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Add New Staff Member</h5>
              <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="../../Backend/process.php" method="POST">
              <div class="modal-body">
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
                  <input type="email" name="email" class="form-control" pattern="[a-zA-Z0-9._%+\-]+@(gmail|yahoo)\.(com|com\.ph)" title="Only Gmail or Yahoo email addresses are allowed (e.g. example@gmail.com)" placeholder="example@gmail.com or example@yahoo.com" required>
                </div>

                <div class="form-group">
                  <label>Password</label>
                  <input type="password" name="password" id="password" class="form-control" required oninput="checkPasswordStrength(this.value)">
                  <small id="password-strength" class="form-text"></small>
                </div>

                <div class="form-group">
                  <label>Contact Number</label>
                  <input type="tel" name="contact" class="form-control" placeholder="e.g. 09123456789" pattern="[0-9]{11}" maxlength="11" title="Contact number must be exactly 11 digits"  oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)" required>
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

      <!-- View/Edit/Delete Modal -->
      <div class="modal fade" id="viewUserModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Staff Details</h5>
              <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="../../Backend/process.php" method="POST">
              <div class="modal-body">

                <input type="hidden" name="user_id" id="view_id">

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
                        title="Only Gmail or Yahoo email addresses are allowed"
                        required>
                </div>

                <div class="form-group">
                  <label>Contact Number</label>
                  <input type="tel" name="contact" id="view_contact" class="form-control"
                        pattern="[0-9]{11}"
                        maxlength="11"
                        title="Contact number must be exactly 11 digits"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                        required>
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

      <!-- Delete Confirmation Modal -->
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
  <!-- jQuery -->
  <script src="../plugins/jquery/jquery.min.js"></script>
  <!-- Bootstrap -->
  <script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <!-- overlayScrollbars -->
  <script src="../plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
  <!-- AdminLTE App -->
  <script src="../dist/js/adminlte.js"></script>

  <!-- AdminLTE for demo purposes -->
  <script src="../dist/js/pages/dashboard2.js"></script>

  <!-- Staff list modal handler -->
  <script>
    $(function() {
      $('.view-staff-btn').on('click', function() {
        var staff = $(this).closest('tr').data('staff');
        if (!staff) {
          return;
        }

        $('#staffProfileAvatar').attr('src', staff.image);
        $('#staffProfileName').text(staff.name);
        $('#staffProfileRole').text(staff.role);
        $('#staffProfileEmail').text(staff.email);
        $('#staffProfilePhone').text(staff.phone);
        $('#staffProfileBio').text(staff.bio);
      });
    });
  </script>

  <!-- Dark mode toggle -->
  <script>
    $(function() {
      // Check for saved dark mode preference
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
      $('#darkModeToggle').on('click', function(e) {
        e.preventDefault();
        $('body').toggleClass('dark-mode');
        $('.main-header.navbar').toggleClass('navbar-dark navbar-white navbar-light bg-white');
        // Toggle icon between moon and sun
        $(this).find('i').toggleClass('fa-moon fa-sun');
        // Save preference
        const isDark = $('body').hasClass('dark-mode');
        localStorage.setItem('darkMode', isDark);
        // Animate the icon and button
        $(this).addClass('clicked');
        $(this).find('i').addClass('clicked');
        setTimeout(() => {
          $(this).removeClass('clicked');
          $(this).find('i').removeClass('clicked');
        }, 300);
      });
    });

    function checkPasswordStrength(password) {
  const indicator = document.getElementById('password-strength');
  let strength = 0;

  if (password.length >= 8)          strength++; // min length
  if (/[A-Z]/.test(password))        strength++; // uppercase
  if (/[0-9]/.test(password))        strength++; // number
  if (/[^A-Za-z0-9]/.test(password)) strength++; // special char

  if (password.length === 0) {
    indicator.textContent = '';
    indicator.style.color = '';
  } else if (strength <= 1) {
    indicator.textContent = '🔴 Weak';
    indicator.style.color = 'red';
  } else if (strength === 2 || strength === 3) {
    indicator.textContent = '🟠 Medium';
    indicator.style.color = 'orange';
  } else {
    indicator.textContent = '🟢 Strong password!';
    indicator.style.color = 'green';
  }
}

 // View button click - populate modal
  $(document).on('click', '.view-btn', function () {
    var row = $(this).closest('tr');

    $('#view_id').val(row.data('id'));
    $('#view_firstname').val(row.data('firstname'));
    $('#view_lastname').val(row.data('lastname'));
    $('#view_email').val(row.data('email'));
    $('#view_contact').val(row.data('contact'));
    $('#view_address').val(row.data('address'));

    $('#viewUserModal').modal('show');
  });

  // Delete button click - open confirm modal
  $('#deleteBtn').on('click', function () {
    var id        = $('#view_id').val();
    var firstname = $('#view_firstname').val();
    var lastname  = $('#view_lastname').val();

    $('#delete_id').val(id);
    $('#delete_name').text(firstname + ' ' + lastname);

    $('#viewUserModal').modal('hide');
    $('#deleteConfirmModal').modal('show');
  });
  </script>

</body>

</html>