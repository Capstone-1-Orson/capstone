<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../../Frontend/lockscreen.html");
    exit();
}

require_once '../../Backend/conn.php';

// ── Helper ─────────────────────────────────────────────────────
function tableExists($conn, $table) {
    $r = $conn->query("SHOW TABLES LIKE '$table'");
    return $r && $r->num_rows > 0;
}
$hasOrderItems = tableExists($conn, 'order_items');

// ── Top info-box stats ─────────────────────────────────────────
// Daily customers served = distinct table_no ordered today
$dailyCustomers = 0;
$r = $conn->query("SELECT COUNT(DISTINCT table_no) AS c FROM orders WHERE DATE(created_at)=CURDATE()");
if ($r && $row = $r->fetch_assoc()) $dailyCustomers = (int)$row['c'];

// Total revenue (all time)
$totalRevenue = 0.0;
$r = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders");
if ($r && $row = $r->fetch_assoc()) $totalRevenue = (float)$row['rev'];

// Orders today
$ordersToday = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at)=CURDATE()");
if ($r && $row = $r->fetch_assoc()) $ordersToday = (int)$row['c'];

// Staff count (from user table)
$staffCount = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM user WHERE position = 'staff'");
if ($r && $row = $r->fetch_assoc()) $staffCount = (int)$row['c'];

// ── Monthly recap chart (last 6 months) ────────────────────────
$chartLabels = [];
$chartData   = [];
for ($i = 5; $i >= 0; $i--) {
    $y = date('Y', strtotime("-$i months"));
    $m = date('m', strtotime("-$i months"));
    $chartLabels[] = date('M Y', strtotime("-$i months"));
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders
         WHERE MONTH(created_at)=? AND YEAR(created_at)=?"
    );
    $stmt->bind_param('ss', $m, $y);
    $stmt->execute();
    $chartData[] = (float)$stmt->get_result()->fetch_assoc()['rev'];
    $stmt->close();
}

// ── Monthly summary footer ─────────────────────────────────────
$thisMonthRev = 0.0;
$lastMonthRev = 0.0;
$r = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())");
if ($r && $row = $r->fetch_assoc()) $thisMonthRev = (float)$row['rev'];
$r = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()-INTERVAL 1 MONTH) AND YEAR(created_at)=YEAR(CURDATE()-INTERVAL 1 MONTH)");
if ($r && $row = $r->fetch_assoc()) $lastMonthRev = (float)$row['rev'];
$revChange = $lastMonthRev > 0 ? round((($thisMonthRev - $lastMonthRev) / $lastMonthRev) * 100, 1) : 0;

$totalOrders = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM orders");
if ($r && $row = $r->fetch_assoc()) $totalOrders = (int)$row['c'];

// ── Category revenue progress bars ────────────────────────────
$catRevenue = [];
if ($hasOrderItems) {
    $r = $conn->query(
        "SELECT m.category, SUM(oi.qty * oi.unit_price) AS revenue
         FROM order_items oi JOIN menu m ON m.id = oi.menu_id
         GROUP BY m.category ORDER BY revenue DESC LIMIT 4"
    );
    if ($r) while ($row = $r->fetch_assoc()) $catRevenue[] = $row;
}
$maxCatRev = !empty($catRevenue) ? (float)$catRevenue[0]['revenue'] : 1;

// ── Right-side stats ───────────────────────────────────────────
// Daily items sold from order_items (qty today)
$dailyItemsSold = 0;
if ($hasOrderItems) {
    $r = $conn->query(
        "SELECT COALESCE(SUM(oi.qty),0) AS c FROM order_items oi
         JOIN orders o ON o.id=oi.order_id WHERE DATE(o.created_at)=CURDATE()"
    );
    if ($r && $row = $r->fetch_assoc()) $dailyItemsSold = (int)$row['c'];
}

// Daily revenue
$dailyRevenue = 0.0;
$r = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at)=CURDATE()");
if ($r && $row = $r->fetch_assoc()) $dailyRevenue = (float)$row['rev'];

// Low stock count
$lowStockCount = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM ingredients WHERE stock_qty <= low_stock_threshold");
if ($r && $row = $r->fetch_assoc()) $lowStockCount = (int)$row['c'];

// ── New menu items (last 5 added) ──────────────────────────────
$newMenuItems = [];
$r = $conn->query("SELECT name, price, description FROM menu ORDER BY id DESC LIMIT 4");
if ($r) while ($row = $r->fetch_assoc()) $newMenuItems[] = $row;

// ── Staff list ────────────────────────────────────────────────
$staffList = [];
$r = $conn->query("SELECT firstname, lastname FROM user WHERE position = 'staff' ORDER BY id DESC LIMIT 5");
if ($r) while ($row = $r->fetch_assoc()) $staffList[] = $row;

// ── Recent orders ─────────────────────────────────────────────
$recentOrders = [];
if ($hasOrderItems) {
    $r = $conn->query(
        "SELECT o.id, o.table_no, o.total_amt,
                GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         JOIN menu m ON m.id = oi.menu_id
         GROUP BY o.id ORDER BY o.created_at DESC LIMIT 5"
    );
    if ($r) while ($row = $r->fetch_assoc()) $recentOrders[] = $row;
} else {
    $r = $conn->query("SELECT id, table_no, total_amt, '—' AS items FROM orders ORDER BY created_at DESC LIMIT 5");
    if ($r) while ($row = $r->fetch_assoc()) $recentOrders[] = $row;
}

$conn->close();

$chartLabelsJson = json_encode($chartLabels);
$chartDataJson   = json_encode($chartData);
$revTrend        = $revChange >= 0 ? '+' . $revChange . '%' : $revChange . '%';
$revTrendClass   = $revChange >= 0 ? 'text-success' : 'text-danger';
$revTrendIcon    = $revChange >= 0 ? 'fa-caret-up' : 'fa-caret-down';

$staffImages = [
    '../dist/img/user1-128x128.jpg',
    '../dist/img/user8-128x128.jpg',
    '../dist/img/user7-128x128.jpg',
    '../dist/img/user6-128x128.jpg',
    '../dist/img/user5-128x128.jpg',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OPERLYTICS | Overview</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../dist/css/empress-cafe-theme.css">
  <style>
    body, .main-header.navbar { transition: background-color .5s ease, color .5s ease; }
    #darkModeToggle           { transition: box-shadow .3s ease; }
    #darkModeToggle i         { transition: transform .3s ease; }
    #darkModeToggle.clicked   { box-shadow: 0 0 15px rgba(255,255,255,.8); }
    #darkModeToggle.clicked i { transform: rotate(180deg) scale(1.2); }
  </style>
</head>
<body class="hold-transition dark-mode sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">

  <!-- ── Navbar ─────────────────────────────────────────────── -->
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
        <a class="nav-link" data-widget="navbar-search" href="#" role="button"><i class="fas fa-search"></i></a>
        <div class="navbar-search-block">
          <form class="form-inline">
            <div class="input-group input-group-sm">
              <input class="form-control form-control-navbar" type="search" placeholder="Search">
              <div class="input-group-append">
                <button class="btn btn-navbar" type="submit"><i class="fas fa-search"></i></button>
                <button class="btn btn-navbar" type="button" data-widget="navbar-search"><i class="fas fa-times"></i></button>
              </div>
            </div>
          </form>
        </div>
      </li>
      <li class="nav-item">
        <a class="nav-link" data-widget="fullscreen" href="#" role="button"><i class="fas fa-expand-arrows-alt"></i></a>
      </li>
      <li class="nav-item">
        <a class="nav-link" id="darkModeToggle" href="#" role="button"><i class="fas fa-moon"></i></a>
      </li>
    </ul>
  </nav>

  <!-- ── Sidebar ───────────────────────────────────────────── -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="#" class="brand-link">
      <img src="../dist/img/Empress' Cafe Boracay.jpg" alt="Logo" class="brand-image img-circle elevation-3" style="opacity:.8">
      <span class="brand-text font-weight-light">Empress' Cafe</span>
    </a>
    <div class="sidebar">
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image"><img src="../dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image"></div>
        <div class="info">
          <a href="#" class="d-block"><?= htmlspecialchars($_SESSION['user'] ?? 'Admin') ?></a>
        </div>
      </div>
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item"><a href="./index2.php"          class="nav-link active"><i class="nav-icon fas fa-tachometer-alt"></i><p>Overview</p></a></li>
          <li class="nav-item"><a href="./menu-management.php" class="nav-link"><i class="nav-icon fas fa-utensils"></i><p>Menu Management</p></a></li>
          <li class="nav-item"><a href="./inventory.php"       class="nav-link"><i class="nav-icon fas fa-boxes"></i><p>Inventory Tracking</p></a></li>
          <li class="nav-item"><a href="./suppliers.php"       class="nav-link"><i class="nav-icon fas fa-truck"></i><p>Supplier Info</p></a></li>
          <li class="nav-item"><a href="./staff-list.php"      class="nav-link"><i class="far fa-user nav-icon"></i><p>Staff List</p></a></li>
          <li class="nav-item"><a href="./sale_revenue.php"    class="nav-link"><i class="nav-icon fas fa-chart-line"></i><p>Sales &amp; Revenue</p></a></li>
          <li class="nav-item"><a href="./report.php"          class="nav-link"><i class="nav-icon fas fa-file-alt"></i><p>Reports</p></a></li>
          <li class="nav-item"><a href="./settings.php" class="nav-link"><i class="nav-icon fas fa-cog"></i><p>Settings</p></a></li>
          <li class="nav-item mt-auto"><a href="../../Backend/logout.php" class="nav-link"><i class="nav-icon fas fa-sign-out-alt"></i><p>Log Out</p></a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <!-- ── Content Wrapper ───────────────────────────────────── -->
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">Cafe Shop Analytics</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Overview</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">

        <!-- ── Top 4 Info Boxes ────────────────────────────── -->
        <div class="row">
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Tables Served Today</span>
                <span class="info-box-number"><?= number_format($dailyCustomers) ?></span>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
              <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-chart-line"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Revenue</span>
                <span class="info-box-number">&#8369;<?= number_format($totalRevenue, 2) ?></span>
              </div>
            </div>
          </div>
          <div class="clearfix hidden-md-up"></div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
              <span class="info-box-icon bg-success elevation-1"><i class="fas fa-shopping-cart"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Orders Today</span>
                <span class="info-box-number"><?= number_format($ordersToday) ?></span>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
              <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-user-tie"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Staff Registered</span>
                <span class="info-box-number"><?= number_format($staffCount) ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Monthly Recap Chart ────────────────────────── -->
        <div class="row">
          <div class="col-md-12">
            <div class="card">
              <div class="card-header">
                <h5 class="card-title">Monthly Revenue — Last 6 Months</h5>
                <div class="card-tools">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                </div>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-8">
                    <p class="text-center">
                      <strong>Revenue: <?= date('M Y', strtotime('-5 months')) ?> – <?= date('M Y') ?></strong>
                    </p>
                    <div class="chart">
                      <canvas id="salesChart" height="180" style="height:180px;"></canvas>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <p class="text-center"><strong>Revenue by Category</strong></p>
                    <?php if (!empty($catRevenue)):
                      $barColors = ['bg-primary','bg-danger','bg-success','bg-warning'];
                      foreach ($catRevenue as $ci => $cat):
                        $pct = $maxCatRev > 0 ? round(((float)$cat['revenue'] / $maxCatRev) * 100) : 0;
                    ?>
                    <div class="progress-group">
                      <?= htmlspecialchars($cat['category']) ?>
                      <span class="float-right"><b>&#8369;<?= number_format((float)$cat['revenue'], 0) ?></b></span>
                      <div class="progress progress-sm">
                        <div class="progress-bar <?= $barColors[$ci % 4] ?>" style="width:<?= $pct ?>%"></div>
                      </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                      <p class="text-muted text-center">No category data yet.</p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="card-footer">
                <div class="row">
                  <div class="col-sm-3 col-6">
                    <div class="description-block border-right">
                      <span class="description-percentage <?= $revTrendClass ?>">
                        <i class="fas <?= $revTrendIcon ?>"></i> <?= $revTrend ?>
                      </span>
                      <h5 class="description-header">&#8369;<?= number_format($thisMonthRev, 2) ?></h5>
                      <span class="description-text">THIS MONTH</span>
                    </div>
                  </div>
                  <div class="col-sm-3 col-6">
                    <div class="description-block border-right">
                      <span class="description-percentage text-muted"><i class="fas fa-minus"></i></span>
                      <h5 class="description-header">&#8369;<?= number_format($lastMonthRev, 2) ?></h5>
                      <span class="description-text">LAST MONTH</span>
                    </div>
                  </div>
                  <div class="col-sm-3 col-6">
                    <div class="description-block border-right">
                      <span class="description-percentage text-info"><i class="fas fa-receipt"></i></span>
                      <h5 class="description-header"><?= number_format($totalOrders) ?></h5>
                      <span class="description-text">TOTAL ORDERS</span>
                    </div>
                  </div>
                  <div class="col-sm-3 col-6">
                    <div class="description-block">
                      <span class="description-percentage text-success"><i class="fas fa-peso-sign"></i></span>
                      <h5 class="description-header">&#8369;<?= number_format($totalRevenue, 2) ?></h5>
                      <span class="description-text">ALL-TIME REVENUE</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Main Row ───────────────────────────────────── -->
        <div class="row">
          <!-- Left col -->
          <div class="col-md-8">
            <div class="row">

              <!-- New Menu Items -->
              <div class="col-md-6">
                <div class="card">
                  <div class="card-header">
                    <h3 class="card-title">New Menu Items</h3>
                  </div>
                  <div class="card-body p-0">
                    <ul class="products-list product-list-in-card pl-2 pr-2">
                      <?php if (!empty($newMenuItems)):
                        $badgeColors = ['badge-warning','badge-info','badge-danger','badge-success'];
                        foreach ($newMenuItems as $mi => $item): ?>
                      <li class="item">
                        <div class="product-img">
                          <img src="../dist/img/default-150x150.png" alt="<?= htmlspecialchars($item['name']) ?>" class="img-size-50">
                        </div>
                        <div class="product-info">
                          <a href="menu-management.php" class="product-title">
                            <?= htmlspecialchars($item['name']) ?>
                            <span class="badge <?= $badgeColors[$mi % 4] ?> float-right">
                              &#8369;<?= number_format((float)$item['price'], 2) ?>
                            </span>
                          </a>
                          <span class="product-description">
                            <?= htmlspecialchars(mb_strimwidth($item['description'] ?? '', 0, 60, '…')) ?>
                          </span>
                        </div>
                      </li>
                      <?php endforeach; ?>
                      <?php else: ?>
                      <li class="item p-3 text-muted">No menu items yet.</li>
                      <?php endif; ?>
                    </ul>
                  </div>
                  <div class="card-footer text-center">
                    <a href="menu-management.php" class="uppercase">View Full Menu</a>
                  </div>
                </div>
              </div>

              <!-- Staff Members -->
              <div class="col-md-6">
                <div class="card">
                  <div class="card-header">
                    <h3 class="card-title">Staff Members</h3>
                    <span class="badge badge-info"><?= $staffCount ?> Staff</span>
                  </div>
                  <div class="card-body p-0">
                    <ul class="users-list clearfix" style="display:flex;flex-wrap:wrap;justify-content:center;">
                      <?php if (!empty($staffList)):
                        foreach ($staffList as $si => $staff): ?>
                      <li style="text-align:center;margin:8px;">
                        <img src="<?= $staffImages[$si % count($staffImages)] ?>" alt="<?= htmlspecialchars($staff['firstname']) ?>">
                        <a class="users-list-name" href="staff-list.php">
                          <?= htmlspecialchars($staff['firstname']) ?> <?= htmlspecialchars($staff['lastname']) ?>
                        </a>
                        <span class="users-list-date">Staff</span>
                      </li>
                      <?php endforeach; ?>
                      <?php else: ?>
                      <li class="p-3 text-muted">No staff records found.</li>
                      <?php endif; ?>
                    </ul>
                  </div>
                  <div class="card-footer text-center">
                    <a href="staff-list.php">View All Staff</a>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- Right col -->
          <div class="col-md-4">
            <div class="info-box mb-3 bg-warning">
              <span class="info-box-icon"><i class="fas fa-boxes"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Items Sold Today</span>
                <span class="info-box-number"><?= number_format($dailyItemsSold) ?></span>
              </div>
            </div>
            <div class="info-box mb-3 bg-success">
              <span class="info-box-icon"><i class="fas fa-peso-sign"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Today's Revenue</span>
                <span class="info-box-number">&#8369;<?= number_format($dailyRevenue, 2) ?></span>
              </div>
            </div>
            <div class="info-box mb-3 bg-danger">
              <span class="info-box-icon"><i class="fas fa-exclamation-triangle"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Low Stock Alerts</span>
                <span class="info-box-number"><?= number_format($lowStockCount) ?></span>
              </div>
            </div>
            <div class="info-box mb-3 bg-info">
              <span class="info-box-icon"><i class="fas fa-shopping-cart"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Orders Today</span>
                <span class="info-box-number"><?= number_format($ordersToday) ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Recent Orders ──────────────────────────────── -->
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header border-transparent">
                <h3 class="card-title"><i class="fas fa-receipt mr-2"></i>Recent Orders</h3>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table m-0 w-100">
                    <thead>
                      <tr>
                        <th>Order ID</th>
                        <th>Items</th>
                        <th>Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($recentOrders)):
                        foreach ($recentOrders as $ro): ?>
                      <tr>
                        <td><strong>#<?= (int)$ro['id'] ?></strong></td>
                        <td><?= htmlspecialchars(mb_strimwidth($ro['items'], 0, 50, '…')) ?></td>
                        <td><span class="text-success font-weight-bold">&#8369;<?= number_format((float)$ro['total_amt'], 2) ?></span></td>
                      </tr>
                      <?php endforeach; ?>
                      <?php else: ?>
                      <tr><td colspan="4" class="text-center text-muted p-3">No recent orders.</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="card-footer clearfix">
                <a href="sale_revenue.php" class="btn btn-sm btn-secondary float-right">View All Orders</a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>
  </div><!-- /.content-wrapper -->
</div><!-- /.wrapper -->

<!-- ── Scripts ──────────────────────────────────────────────── -->
<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
<script src="../dist/js/adminlte.js"></script>
<script src="../plugins/chart.js/Chart.min.js"></script>

<!-- Monthly Revenue Chart -->
<script>
$(function () {
  new Chart($('#salesChart').get(0).getContext('2d'), {
    type: 'bar',
    data: {
      labels: <?= $chartLabelsJson ?>,
      datasets: [{
        label: 'Revenue (₱)',
        data: <?= $chartDataJson ?>,
        backgroundColor: 'rgba(233,30,140,0.55)',
        borderColor:     'rgba(233,30,140,1)',
        borderWidth: 2,
        hoverBackgroundColor: 'rgba(233,30,140,0.8)'
      }]
    },
    options: {
      responsive: true,
      legend: { display: false },
      tooltips: {
        callbacks: {
          label: i => '₱' + parseFloat(i.yLabel).toLocaleString('en', {minimumFractionDigits: 2})
        }
      },
      scales: {
        yAxes: [{ ticks: { beginAtZero: true, callback: v => '₱' + v.toLocaleString() } }]
      }
    }
  });
});
</script>

<!-- Dark Mode -->
<script>
$(function () {
  var dm = localStorage.getItem('darkMode');
  if (dm === 'true') {
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