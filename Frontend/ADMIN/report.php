<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../../Frontend/lockscreen.html"); // Send them back if not logged in
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Reports</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <!-- overlayScrollbars -->
  <link rel="stylesheet" href="../plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <!-- DataTables -->
  <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
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

        <!-- Notifications Dropdown Menu -->

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
            <!-- Dashboard -->
            <li class="nav-item">
              <a href="./index2.php" class="nav-link">
                <i class="nav-icon fas fa-tachometer-alt"></i>
                <p>Overview</p>
              </a>
            </li>

            <!-- Operations -->
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

            <!-- Staff Management (NEW SECTION) -->
            <li class="nav-item">
              <a href="./staff-list.php" class="nav-link">
                <i class="far fa-user nav-icon"></i>
                <p>Staff List</p>
              </a>
            </li>

            <!-- Sales & Revenue -->
            <li class="nav-item">
              <a href="./sale_revenue.php" class="nav-link">
                <i class="nav-icon fas fa-chart-line"></i>
                <p>Sales & Revenue</p>
              </a>
            </li>

            <!-- Reports -->
            <li class="nav-item">
              <a href="./report.php" class="nav-link active">
                <i class="nav-icon fas fa-file-alt"></i>
                <p>Reports</p>
              </a>
            </li>

            <!-- Settings (Bonus addition) -->
            <li class="nav-item mt-auto">
              <a href="../../Backend/logout.php" class="nav-link">
                  <i class="nav-icon fas fa-cog"></i>
                  <p>Log Out</p>
              </a>
            </li>
          </ul>
        </nav>
      </div>
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
      <!-- Content Header (Page header) -->
      <div class="content-header">
        <div class="container-fluid">
          <div class="row mb-2">
            <div class="col-sm-6">
              <h1 class="m-0">Reports</h1>
            </div><!-- /.col -->
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="#">Home</a></li>
                <li class="breadcrumb-item active">Reports</li>
              </ol>
            </div>
          </div>
        </div>
      </div>
      <!-- Summary Report -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Summary Report</h3>
        </div>
        <div class="card-body">
          <div class="row text-center">
            <div class="col-md-3">
              <h5>1,250</h5>
              <span>Total Orders</span>
            </div>
            <div class="col-md-3">
              <h5>₱85,400</h5>
              <span>Total Revenue</span>
            </div>
            <div class="col-md-3">
              <h5>₱42,300</h5>
              <span>Net Profit</span>
            </div>
            <div class="col-md-3">
              <h5>320</h5>
              <span>Customers</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Sales Overview Chart -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Sales Overview</h3>
        </div>
        <div class="card-body">
          <canvas id="salesChart" height="100"></canvas>
        </div>
      </div>

      <!-- Main content -->
      <section class="content">
        <div class="container-fluid">
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Inventory Report</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                  <table id="example1" class="table table-bordered table-striped">
                    <thead>
                      <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Stock Quantity</th>
                        <th>Unit Price</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>Coffee Beans</td>
                        <td>Beverage</td>
                        <td>120 kg</td>
                        <td>$15/kg</td>
                        <td><span class="badge badge-success">In Stock</span></td>
                      </tr>
                      <tr>
                        <td>Milk</td>
                        <td>Dairy</td>
                        <td>80 L</td>
                        <td>$2/L</td>
                        <td><span class="badge badge-warning">Low</span></td>
                      </tr>
                      <tr>
                        <td>Sugar</td>
                        <td>Ingredient</td>
                        <td>50 kg</td>
                        <td>$1/kg</td>
                        <td><span class="badge badge-danger">Critical</span></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="content">
        <div class="container-fluid">
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Order Report</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                  <table id="example2" class="table table-bordered table-striped">
                    <thead>
                      <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Item Ordered</th>
                        <th>Quantity</th>
                        <th>Order Status</th>
                        <th>Total Price</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>ORD1001</td>
                        <td>2023-10-01</td>
                        <td>Coffee Beans</td>
                        <td>2 kg</td>
                        <td><span class="badge badge-success">Completed</span></td>
                        <td>$30</td>
                      </tr>
                      <tr>
                        <td>ORD1002</td>
                        <td>2023-10-02</td>
                        <td>Milk</td>
                        <td>10 L</td>
                        <td><span class="badge badge-warning">Pending</span></td>
                        <td>$20</td>
                      </tr>
                      <tr>
                        <td>ORD1003</td>
                        <td>2023-10-03</td>
                        <td>Sugar</td>
                        <td>5 kg</td>
                        <td><span class="badge badge-danger">Cancelled</span></td>
                        <td>$5</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>


    </div>



  </div>
  <!-- ./wrapper -->

  <!-- REQUIRED SCRIPTS -->
  <script src="../plugins/jquery/jquery.min.js"></script>
  <script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="../plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
  <script src="../dist/js/adminlte.js"></script>

  <!-- PAGE PLUGINS -->
  <!-- jQuery Mapael -->
  <script src="../plugins/jquery-mousewheel/jquery.mousewheel.js"></script>
  <script src="../plugins/raphael/raphael.min.js"></script>
  <script src="../plugins/jquery-mapael/jquery.mapael.min.js"></script>
  <script src="../plugins/jquery-mapael/maps/usa_states.min.js"></script>
  <!-- ChartJS -->
  <script src="../plugins/chart.js/Chart.min.js"></script>

  <!-- AdminLTE dashboard demo (This is only for demo purposes) -->
  <script src="../dist/js/pages/dashboard2.js"></script>
  <!-- DataTables  & Plugins -->
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
    $(function () {
      $("#example1").DataTable({
        "responsive": true,
        "lengthChange": false,
        "autoWidth": false,
        "buttons": [
          {
            extend: 'copy',
            title: 'INVENTORY Report'
          },
          {
            extend: 'csv',
            title: 'INVENTORY_Report'
          },
          {
            extend: 'excel',
            title: 'INVENTORY_Report'
          },
          {
            extend: 'pdf',
            title: 'INVENTORY_Report'
          },
          {
            extend: 'print',
            title: 'INVENTORY Report'
          },
          'colvis'
        ]
      }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
    });

    $(function () {
      $("#example2").DataTable({
        "responsive": true, "lengthChange": false, "autoWidth": false,
        "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
      }).buttons().container().appendTo('#example2_wrapper .col-md-6:eq(0)');
    });
  </script>

  <script>
    $(function () {
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
      $('#darkModeToggle').on('click', function (e) {
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
  </script>
</body>

</html>