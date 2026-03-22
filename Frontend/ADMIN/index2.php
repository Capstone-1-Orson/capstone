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
  <title>OPERLYTICS | Dashboard 2</title>

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
</head>

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
              <a href="./index2.php" class="nav-link active">
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
              <a href="./suppliers.php" class="nav-link ">
                <i class="nav-icon fas fa-truck"></i>
                <p>Suppliers Orders</p>
              </a>
            </li>

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
      <!-- Content Header (Page header) -->
      <div class="content-header">
        <div class="container-fluid">
          <div class="row mb-2">
            <div class="col-sm-6">
              <h1 class="m-0">Cafe Shop Analytics</h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="#">Home</a></li>
                <li class="breadcrumb-item active">Cafe Shop Analytics</li>
              </ol>
            </div>
          </div>
        </div>
      </div>

      <!-- Main content -->
      <section class="content">
        <div class="container-fluid">
          <!-- Info boxes -->
          <div class="row">
            <div class="col-12 col-sm-6 col-md-3">
              <div class="info-box">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-users"></i></span>

                <div class="info-box-content">
                  <span class="info-box-text">Daily Customers Served</span>
                  <span class="info-box-number">
                    150
                  </span>
                </div>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
              <div class="info-box mb-3">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-dollar-sign"></i></span>

                <div class="info-box-content">
                  <span class="info-box-text">Total Revenue</span>
                  <span class="info-box-number">$12,500</span>
                </div>
              </div>
            </div>

            <!-- fix for small devices only -->
            <div class="clearfix hidden-md-up"></div>

            <div class="col-12 col-sm-6 col-md-3">
              <div class="info-box mb-3">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-shopping-cart"></i></span>

                <div class="info-box-content">
                  <span class="info-box-text">Orders Today</span>
                  <span class="info-box-number">245</span>
                </div>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
              <div class="info-box mb-3">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-users"></i></span>

                <div class="info-box-content">
                  <span class="info-box-text">Staff on Duty</span>
                  <span class="info-box-number">12</span>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-12">
              <div class="card">
                <div class="card-header">
                  <h5 class="card-title">Monthly Recap Report</h5>

                  <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                      <i class="fas fa-minus"></i>
                    </button>
                    <div class="btn-group">
                      <button type="button" class="btn btn-tool dropdown-toggle" data-toggle="dropdown">
                        <i class="fas fa-wrench"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-right" role="menu">
                        <a href="#" class="dropdown-item">Action</a>
                        <a href="#" class="dropdown-item">Another action</a>
                        <a href="#" class="dropdown-item">Something else here</a>
                        <a class="dropdown-divider"></a>
                        <a href="#" class="dropdown-item">Separated link</a>
                      </div>
                    </div>
                    <button type="button" class="btn btn-tool" data-card-widget="remove">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-8">
                      <p class="text-center">
                        <strong>Revenue: 1 Jan, 2024 - 30 Jul, 2024</strong>
                      </p>

                      <div class="chart">
                        <!-- Sales Chart Canvas -->
                        <canvas id="salesChart" height="180" style="height: 180px;"></canvas>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <p class="text-center">
                        <strong>Goal Completion</strong>
                      </p>

                      <div class="progress-group">
                        Add Products to Cart
                        <span class="float-right"><b>160</b>/200</span>
                        <div class="progress progress-sm">
                          <div class="progress-bar bg-primary" style="width: 80%"></div>
                        </div>
                      </div>

                      <div class="progress-group">
                        Complete Purchase
                        <span class="float-right"><b>310</b>/400</span>
                        <div class="progress progress-sm">
                          <div class="progress-bar bg-danger" style="width: 75%"></div>
                        </div>
                      </div>

                      <div class="progress-group">
                        <span class="progress-text">Visit Premium Page</span>
                        <span class="float-right"><b>480</b>/800</span>
                        <div class="progress progress-sm">
                          <div class="progress-bar bg-success" style="width: 60%"></div>
                        </div>
                      </div>

                      <div class="progress-group">
                        Send Inquiries
                        <span class="float-right"><b>250</b>/500</span>
                        <div class="progress progress-sm">
                          <div class="progress-bar bg-warning" style="width: 50%"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="card-footer">
                  <div class="row">
                    <div class="col-sm-3 col-6">
                      <div class="description-block border-right">
                        <span class="description-percentage text-success"><i class="fas fa-caret-up"></i> 17%</span>
                        <h5 class="description-header">$35,210.43</h5>
                        <span class="description-text">TOTAL REVENUE</span>
                      </div>
                    </div>

                    <div class="col-sm-3 col-6">
                      <div class="description-block border-right">
                        <span class="description-percentage text-warning"><i class="fas fa-caret-left"></i> 0%</span>
                        <h5 class="description-header">$10,390.90</h5>
                        <span class="description-text">TOTAL COST</span>
                      </div>
                    </div>

                    <div class="col-sm-3 col-6">
                      <div class="description-block border-right">
                        <span class="description-percentage text-success"><i class="fas fa-caret-up"></i> 20%</span>
                        <h5 class="description-header">$24,813.53</h5>
                        <span class="description-text">TOTAL PROFIT</span>
                      </div>
                    </div>

                    <div class="col-sm-3 col-6">
                      <div class="description-block">
                        <span class="description-percentage text-danger"><i class="fas fa-caret-down"></i> 18%</span>
                        <h5 class="description-header">1200</h5>
                        <span class="description-text">GOAL COMPLETIONS</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Main row -->
          <div class="row">
            <!-- Left col -->
            <div class="col-md-8">
              <div class="row">

                <!-- Recently Added Menu -->
                <div class="col-md-6">
                  <div class="card">
                    <div class="card-header">
                      <h3 class="card-title">New Menu Items</h3>
                    </div>

                    <div class="card-body p-0">
                      <ul class="products-list product-list-in-card pl-2 pr-2">

                        <li class="item">
                          <div class="product-img">
                            <img src="../dist/img/default-150x150.png" class="img-size-50">
                          </div>
                          <div class="product-info">
                            <a href="#" class="product-title">
                              Caramel Macchiato
                              <span class="badge badge-warning float-right">₱180</span>
                            </a>
                            <span class="product-description">
                              Fresh espresso with caramel syrup and steamed milk.
                            </span>
                          </div>
                        </li>

                        <li class="item">
                          <div class="product-img">
                            <img src="../dist/img/default-150x150.png" class="img-size-50">
                          </div>
                          <div class="product-info">
                            <a href="#" class="product-title">
                              Iced Latte
                              <span class="badge badge-info float-right">₱150</span>
                            </a>
                            <span class="product-description">
                              Chilled espresso with milk over ice.
                            </span>
                          </div>
                        </li>

                        <li class="item">
                          <div class="product-img">
                            <img src="../dist/img/default-150x150.png" class="img-size-50">
                          </div>
                          <div class="product-info">
                            <a href="#" class="product-title">
                              Chocolate Cake
                              <span class="badge badge-danger float-right">₱120</span>
                            </a>
                            <span class="product-description">
                              Moist chocolate cake with rich frosting.
                            </span>
                          </div>
                        </li>

                        <li class="item">
                          <div class="product-img">
                            <img src="../dist/img/default-150x150.png" class="img-size-50">
                          </div>
                          <div class="product-info">
                            <a href="#" class="product-title">
                              Croissant
                              <span class="badge badge-success float-right">₱90</span>
                            </a>
                            <span class="product-description">
                              Freshly baked buttery croissant.
                            </span>
                          </div>
                        </li>

                      </ul>
                    </div>

                    <div class="card-footer text-center">
                      <a href="menu-management.php" class="uppercase">View Full Menu</a>
                    </div>
                  </div>
                </div>

                <!-- Latest Customers -->
                <div class="col-md-6">
                  <div class="card">
                    <div class="card-header">
                      <h3 class="card-title">Staff Members</h3>
                      <span class="badge badge-info">5 Active Staff</span>
                    </div>

                    <div class="card-body p-0">
                      <ul class="users-list clearfix">

                        <li>
                          <img src="../dist/img/user1-128x128.jpg">
                          <a class="users-list-name" href="#">Maria</a>
                          <span class="users-list-date">Barista</span>
                        </li>

                        <li>
                          <img src="../dist/img/user8-128x128.jpg">
                          <a class="users-list-name" href="#">Juan</a>
                          <span class="users-list-date">Cashier</span>
                        </li>

                        <li>
                          <img src="../dist/img/user7-128x128.jpg">
                          <a class="users-list-name" href="#">Anna</a>
                          <span class="users-list-date">Manager</span>
                        </li>

                        <li>
                          <img src="../dist/img/user6-128x128.jpg">
                          <a class="users-list-name" href="#">Leo</a>
                          <span class="users-list-date">Kitchen Staff</span>
                        </li>

                        <li>
                          <img src="../dist/img/user5-128x128.jpg">
                          <a class="users-list-name" href="#">Sara</a>
                          <span class="users-list-date">Server</span>
                        </li>

                      </ul>
                    </div>

                    <div class="card-footer text-center">
                      <a href="staff-list.php">View All Staff</a>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Latest Orders -->

            </div>

            <!-- RIGHT SIDE -->
            <div class="col-md-4">

              <div class="info-box mb-3 bg-warning">
                <span class="info-box-icon"><i class="fas fa-coffee"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Daily Cups Sold</span>
                  <span class="info-box-number">520</span>
                </div>
              </div>

              <div class="info-box mb-3 bg-success">
                <span class="info-box-icon"><i class="fas fa-peso-sign"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Daily Revenue</span>
                  <span class="info-box-number">₱12,450</span>
                </div>
              </div>

              <div class="info-box mb-3 bg-danger">
                <span class="info-box-icon"><i class="fas fa-bread-slice"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Pastries Sold</span>
                  <span class="info-box-number">210</span>
                </div>
              </div>

              <div class="info-box mb-3 bg-info">
                <span class="info-box-icon"><i class="fas fa-users"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Customers Today</span>
                  <span class="info-box-number">163</span>
                </div>
              </div>

            </div>
            <div class="container-fluid">
              <div class="row">
                <div class="col-12">

                  <div class="card w-100">
                    <div class="card-header border-transparent">
                      <h3 class="card-title">Recent Orders</h3>
                    </div>

                    <div class="card-body p-0">
                      <div class="table-responsive">
                        <table class="table m-0 w-100">
                          <thead>
                            <tr>
                              <th>Order ID</th>
                              <th>Menu Item</th>
                              <th>Status</th>
                              <th>Sales Trend</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                              <td>CF1021</td>
                              <td>Cappuccino</td>
                              <td><span class="badge badge-success">Served</span></td>
                              <td>
                                <div class="sparkbar">90,80,90</div>
                              </td>
                            </tr>
                            <tr>
                              <td>CF1022</td>
                              <td>Latte</td>
                              <td><span class="badge badge-warning">Preparing</span></td>
                              <td>
                                <div class="sparkbar">70,60,75</div>
                              </td>
                            </tr>
                            <tr>
                              <td>CF1023</td>
                              <td>Cheesecake</td>
                              <td><span class="badge badge-danger">Completed</span></td>
                              <td>
                                <div class="sparkbar">85,88,90</div>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <div class="card-footer clearfix">
                      <a href="#" class="btn btn-sm btn-secondary float-right">All Orders</a>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>



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