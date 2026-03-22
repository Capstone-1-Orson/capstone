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
  <title>OPERLYTICS | Supplier Management</title>

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
            <!-- Dashboard -->
            <li class="nav-item">
              <a href="./index2.php" class="nav-link ">
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
              <a href="./suppliers.php" class="nav-link active">
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
              <a href="./report.php" class="nav-link">
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

      <!-- Header -->
      <div class="content-header">
        <div class="container-fluid">
          <div class="row mb-2">
            <div class="col-sm-6">
              <h1 class="m-0">Supplier Orders Dashboard</h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="#">Home</a></li>
                <li class="breadcrumb-item active">Supplier Orders</li>
              </ol>
            </div>
          </div>
        </div>
      </div>

      <!-- Content -->
      <section class="content">
        <div class="container-fluid">

          <!-- Info Boxes -->
          <div class="row">

            <div class="col-md-3">
              <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fas fa-truck"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Total Deliveries</span>
                  <span class="info-box-number">120</span>
                </div>
              </div>
            </div>

            <div class="col-md-3">
              <div class="info-box">
                <span class="info-box-icon bg-success"><i class="fas fa-box"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Stock Received</span>
                  <span class="info-box-number">2,450</span>
                </div>
              </div>
            </div>

            <div class="col-md-3">
              <div class="info-box">
                <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Pending Orders</span>
                  <span class="info-box-number">18</span>
                </div>
              </div>
            </div>

            <div class="col-md-3">
              <div class="info-box">
                <span class="info-box-icon bg-danger"><i class="fas fa-exclamation"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Delayed Deliveries</span>
                  <span class="info-box-number">6</span>
                </div>
              </div>
            </div>

          </div>

          <!-- Orders Report -->
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Monthly Supplier Orders Report</h3>
            </div>

            <div class="card-body">
              <canvas id="ordersChart" height="180"></canvas>
            </div>

            <div class="card-footer">
              <div class="row text-center">

                <div class="col-md-3">
                  <h5>₱85,000</h5>
                  <span>Total Purchases</span>
                </div>

                <div class="col-md-3">
                  <h5>₱20,000</h5>
                  <span>Inventory Cost</span>
                </div>

                <div class="col-md-3">
                  <h5>₱65,000</h5>
                  <span>Stock Value</span>
                </div>

                <div class="col-md-3">
                  <h5>150</h5>
                  <span>Orders Completed</span>
                </div>

              </div>
            </div>
          </div>

          <div class="row">

            <!-- Supplier List -->
            <div class="col-md-6">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Suppliers</h3>
                </div>

                <div class="card-body p-0">
                  <ul class="users-list clearfix">

                    <li>
                      <img src="../dist/img/user1-128x128.jpg">
                      <a class="users-list-name" href="#">ABC Coffee Supply</a>
                      <span class="users-list-date">Beans Supplier</span>
                    </li>

                    <li>
                      <img src="../dist/img/user8-128x128.jpg">
                      <a class="users-list-name" href="#">Fresh Dairy Co.</a>
                      <span class="users-list-date">Milk Supplier</span>
                    </li>

                    <li>
                      <img src="../dist/img/user7-128x128.jpg">
                      <a class="users-list-name" href="#">Sweet Treats Inc.</a>
                      <span class="users-list-date">Pastry Supplier</span>
                    </li>

                  </ul>
                </div>


              </div>
            </div>

            <!-- Recent Supplies -->
            <div class="col-md-6">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Recent Deliveries</h3>
                </div>

                <div class="card-body p-0">
                  <ul class="products-list product-list-in-card pl-2 pr-2">

                    <li class="item">
                      <div class="product-img">
                        <img src="../dist/img/default-150x150.png" class="img-size-50">
                      </div>
                      <div class="product-info">
                        Coffee Beans
                        <span class="badge badge-success float-right">Delivered</span>
                        <span class="product-description">
                          50kg Arabica beans delivered.
                        </span>
                      </div>
                    </li>

                    <li class="item">
                      <div class="product-img">
                        <img src="../dist/img/default-150x150.png" class="img-size-50">
                      </div>
                      <div class="product-info">
                        Fresh Milk
                        <span class="badge badge-warning float-right">Pending</span>
                        <span class="product-description">
                          100L dairy milk order.
                        </span>
                      </div>
                    </li>

                  </ul>
                </div>


              </div>
            </div>

          </div>

          <!-- Orders Table -->
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Supplier Orders</h3>
            </div>

            <div class="card-body">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>Order ID</th>
                    <th>Supplier</th>
                    <th>Item</th>
                    <th>Status</th>
                    <th>Delivery Date</th>
                  </tr>
                </thead>

                <tbody>
                  <tr>
                    <td>SUP001</td>
                    <td>ABC Coffee Supply</td>
                    <td>Coffee Beans</td>
                    <td><span class="badge badge-success">Delivered</span></td>
                    <td>Mar 10, 2026</td>
                  </tr>

                  <tr>
                    <td>SUP002</td>
                    <td>Fresh Dairy Co.</td>
                    <td>Milk</td>
                    <td><span class="badge badge-warning">Pending</span></td>
                    <td>Mar 18, 2026</td>
                  </tr>

                  <tr>
                    <td>SUP003</td>
                    <td>Sweet Treats Inc.</td>
                    <td>Pastries</td>
                    <td><span class="badge badge-danger">Delayed</span></td>
                    <td>Mar 15, 2026</td>
                  </tr>

                </tbody>
              </table>
            </div>
          </div>

        </div>
      </section>
    </div>

    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
    </aside>

  </div>

  <!-- REQUIRED SCRIPTS -->
  <!-- jQuery -->
  <script src="../plugins/jquery/jquery.min.js"></script>
  <!-- Bootstrap -->
  <script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <!-- overlayScrollbars -->
  <script src="../plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
  <!-- AdminLTE App -->
  <script src="../dist/js/adminlte.js"></script>

  <!-- PAGE PLUGINS -->
  <!-- jQuery Mapael -->
  <script src="../plugins/jquery-mousewheel/jquery.mousewheel.js"></script>
  <script src="../plugins/raphael/raphael.min.js"></script>
  <script src="../plugins/jquery-mapael/jquery.mapael.min.js"></script>
  <script src="../plugins/jquery-mapael/maps/usa_states.min.js"></script>
  <!-- ChartJS -->
  <script src="../plugins/chart.js/Chart.min.js"></script>

  <!-- AdminLTE for demo purposes -->
  <!-- AdminLTE dashboard demo (This is only for demo purposes) -->
  <script src="../dist/js/pages/dashboard2.js"></script>

  <!-- Dark mode toggle -->
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