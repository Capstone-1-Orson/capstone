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

// ── Summary Stats ──────────────────────────────────────────────
$totalRevenue = 0.0;
$totalOrders  = 0;
$r = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS rev FROM orders");
if ($r && $row = $r->fetch_assoc()) {
    $totalOrders  = (int)$row['cnt'];
    $totalRevenue = (float)$row['rev'];
}

// Today's revenue
$todayRevenue = 0.0;
$r2 = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at) = CURDATE()");
if ($r2 && $row2 = $r2->fetch_assoc()) $todayRevenue = (float)$row2['rev'];

// This month's revenue
$monthRevenue = 0.0;
$r3 = $conn->query("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())");
if ($r3 && $row3 = $r3->fetch_assoc()) $monthRevenue = (float)$row3['rev'];

// Average order value
$avgOrder = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// ── Monthly Sales Chart (last 6 months) ────────────────────────
$monthLabels = [];
$monthData   = [];
for ($i = 5; $i >= 0; $i--) {
    $y = date('Y', strtotime("-$i months"));
    $m = date('m', strtotime("-$i months"));
    $monthLabels[] = date('M Y', strtotime("-$i months"));
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders
         WHERE MONTH(created_at)=? AND YEAR(created_at)=?"
    );
    $stmt->bind_param('ss', $m, $y);
    $stmt->execute();
    $monthData[] = (float)$stmt->get_result()->fetch_assoc()['rev'];
    $stmt->close();
}

// ── Top Selling Items (for donut chart + sales table) ──────────
$topItems   = [];
$tableItems = [];
if ($hasOrderItems) {
    $r4 = $conn->query(
        "SELECT m.name, m.category, m.price,
                SUM(oi.qty) AS qty_sold,
                SUM(oi.qty * oi.unit_price) AS revenue
         FROM order_items oi
         JOIN menu m ON m.id = oi.menu_id
         GROUP BY oi.menu_id
         ORDER BY qty_sold DESC
         LIMIT 10"
    );
    if ($r4) while ($row = $r4->fetch_assoc()) {
        $topItems[]   = $row;
        $tableItems[] = $row;
    }
}

// ── Latest Orders ──────────────────────────────────────────────
$latestOrders = [];
if ($hasOrderItems) {
    $r5 = $conn->query(
        "SELECT o.id, o.created_at, o.table_no, o.total_amt,
                GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         JOIN menu m ON m.id = oi.menu_id
         GROUP BY o.id
         ORDER BY o.created_at DESC LIMIT 10"
    );
    if ($r5) while ($row = $r5->fetch_assoc()) $latestOrders[] = $row;
} else {
    $r5 = $conn->query("SELECT id, created_at, table_no, total_amt, '—' AS items FROM orders ORDER BY created_at DESC LIMIT 10");
    if ($r5) while ($row = $r5->fetch_assoc()) $latestOrders[] = $row;
}

// ── Daily Revenue – last 7 days (line chart) ───────────────────
$dayLabels = [];
$dayData   = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayLabels[] = date('M d', strtotime("-$i days"));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at)=?");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $dayData[] = (float)$stmt->get_result()->fetch_assoc()['rev'];
    $stmt->close();
}

// ── Category Revenue (for progress bars) ──────────────────────
$catRevenue = [];
if ($hasOrderItems) {
    $r6 = $conn->query(
        "SELECT m.category, SUM(oi.qty * oi.unit_price) AS revenue
         FROM order_items oi JOIN menu m ON m.id = oi.menu_id
         GROUP BY m.category ORDER BY revenue DESC LIMIT 5"
    );
    if ($r6) while ($row = $r6->fetch_assoc()) $catRevenue[] = $row;
}
$maxCatRev = !empty($catRevenue) ? (float)$catRevenue[0]['revenue'] : 1;

$conn->close();

// JSON for charts
$monthLabelsJson = json_encode($monthLabels);
$monthDataJson   = json_encode($monthData);
$dayLabelsJson   = json_encode($dayLabels);
$dayDataJson     = json_encode($dayData);
$donutLabels     = json_encode(array_column($topItems, 'name'));
$donutData       = json_encode(array_map(fn($i) => (float)$i['qty_sold'], $topItems));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OPERLYTICS | Sales &amp; Revenue</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" href="../dist/css/empress-cafe-theme.css">
  <style>
    body, .main-header.navbar { transition: background-color .5s ease, color .5s ease; }
    #darkModeToggle            { transition: box-shadow .3s ease; }
    #darkModeToggle i          { transition: transform .3s ease; }
    #darkModeToggle.clicked    { box-shadow: 0 0 15px rgba(255,255,255,.8); }
    #darkModeToggle.clicked i  { transform: rotate(180deg) scale(1.2); }
    .stat-label { font-size: .82rem; color: #aaa; }
    .stat-val   { font-size: 1.5rem; font-weight: 700; margin-bottom: 2px; }
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
          <li class="nav-item"><a href="./index2.php"          class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Overview</p></a></li>
          <li class="nav-item"><a href="./menu-management.php" class="nav-link"><i class="nav-icon fas fa-utensils"></i><p>Menu Management</p></a></li>
          <li class="nav-item"><a href="./inventory.php"       class="nav-link"><i class="nav-icon fas fa-boxes"></i><p>Inventory Tracking</p></a></li>
          <li class="nav-item"><a href="./suppliers.php"       class="nav-link"><i class="nav-icon fas fa-truck"></i><p>Supplier Info</p></a></li>
          <li class="nav-item"><a href="./staff-list.php"      class="nav-link"><i class="far fa-user nav-icon"></i><p>Staff List</p></a></li>
          <li class="nav-item"><a href="./sale_revenue.php"    class="nav-link active"><i class="nav-icon fas fa-chart-line"></i><p>Sales &amp; Revenue</p></a></li>
          <li class="nav-item"><a href="./report.php"          class="nav-link"><i class="nav-icon fas fa-file-alt"></i><p>Reports</p></a></li>
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
          <div class="col-sm-6"><h1 class="m-0">Sales &amp; Revenue</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="index2.php">Home</a></li>
              <li class="breadcrumb-item active">Sales &amp; Revenue</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">

        <?php if (!$hasOrderItems): ?>
        <div class="alert alert-warning alert-dismissible fade show">
          <i class="fas fa-exclamation-triangle mr-2"></i>
          <strong>Note:</strong> The <code>order_items</code> table is missing. Item-level stats are unavailable.
          Run <strong>create_order_items_table.sql</strong> in phpMyAdmin to enable full data.
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php endif; ?>

        <!-- ── Stat Cards ──────────────────────────────────── -->
        <div class="row">
          <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-info">
              <span class="info-box-icon"><i class="fas fa-peso-sign"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Revenue</span>
                <span class="info-box-number">&#8369;<?= number_format($totalRevenue, 2) ?></span>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-success">
              <span class="info-box-icon"><i class="fas fa-calendar-day"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Today's Revenue</span>
                <span class="info-box-number">&#8369;<?= number_format($todayRevenue, 2) ?></span>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-warning">
              <span class="info-box-icon"><i class="fas fa-calendar-alt"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">This Month</span>
                <span class="info-box-number">&#8369;<?= number_format($monthRevenue, 2) ?></span>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-danger">
              <span class="info-box-icon"><i class="fas fa-shopping-cart"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Orders</span>
                <span class="info-box-number"><?= number_format($totalOrders) ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Monthly Revenue Chart + Category Breakdown ─── -->
        <div class="row">
          <div class="col-md-8">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>Monthly Revenue — Last 6 Months</h3>
              </div>
              <div class="card-body">
                <canvas id="salesChart" height="120"></canvas>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fas fa-tags mr-2"></i>Revenue by Category</h3>
              </div>
              <div class="card-body">
                <?php if (!empty($catRevenue)): ?>
                  <?php foreach ($catRevenue as $cat):
                    $pct = $maxCatRev > 0 ? round(((float)$cat['revenue'] / $maxCatRev) * 100) : 0;
                    $colors = ['bg-primary','bg-success','bg-warning','bg-danger','bg-info'];
                    static $ci = 0;
                    $color = $colors[$ci++ % count($colors)];
                  ?>
                  <div class="progress-group mb-3">
                    <?= htmlspecialchars($cat['category']) ?>
                    <span class="float-right"><b>&#8369;<?= number_format((float)$cat['revenue'], 2) ?></b></span>
                    <div class="progress progress-sm">
                      <div class="progress-bar <?= $color ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="text-muted text-center mt-3">No category data yet.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Daily Revenue (7 days) + Donut Chart ─────────── -->
        <div class="row">
          <div class="col-md-8">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-line mr-2"></i>Daily Revenue — Last 7 Days</h3>
              </div>
              <div class="card-body">
                <canvas id="lineChart" height="120"></canvas>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Top Items by Qty Sold</h3>
              </div>
              <div class="card-body text-center">
                <?php if (!empty($topItems)): ?>
                  <canvas id="donutChart" style="max-height:250px;"></canvas>
                <?php else: ?>
                  <p class="text-muted mt-4">No item data yet.<br><small>Requires <code>order_items</code> table.</small></p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Latest Orders ─────────────────────────────────── -->
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fas fa-receipt mr-2"></i>Latest Orders</h3>
              </div>
              <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                  <thead>
                    <tr>
                      <th>Order ID</th>
                      <th>Date &amp; Time</th>
                      <th>Items</th>
                      <th>Revenue</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($latestOrders)): ?>
                      <?php foreach ($latestOrders as $lo): ?>
                      <tr>
                        <td><strong>#<?= (int)$lo['id'] ?></strong></td>
                        <td><?= htmlspecialchars($lo['created_at']) ?></td>
                        <td><?= htmlspecialchars($lo['items']) ?></td>
                        <td><span class="text-success font-weight-bold">&#8369;<?= number_format((float)$lo['total_amt'], 2) ?></span></td>
                      </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="5" class="text-center text-muted">No orders found.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Top Selling Items Table ───────────────────────── -->
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fas fa-star mr-2"></i>Top Selling Items</h3>
              </div>
              <div class="card-body">
                <table id="example1" class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Category</th>
                      <th>Unit Price (&#8369;)</th>
                      <th>Qty Sold</th>
                      <th>Revenue (&#8369;)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($tableItems)): ?>
                      <?php foreach ($tableItems as $ti): ?>
                      <tr>
                        <td><?= htmlspecialchars($ti['name']) ?></td>
                        <td><?= htmlspecialchars($ti['category']) ?></td>
                        <td><?= number_format((float)$ti['price'], 2) ?></td>
                        <td><?= (int)$ti['qty_sold'] ?></td>
                        <td><?= number_format((float)$ti['revenue'], 2) ?></td>
                      </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="5" class="text-center text-muted">No sales data yet.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
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
// ── Monthly Revenue Bar Chart ─────────────────────────────────
$(function () {
  new Chart($('#salesChart').get(0).getContext('2d'), {
    type: 'bar',
    data: {
      labels: <?= $monthLabelsJson ?>,
      datasets: [{
        label: 'Revenue (₱)',
        data: <?= $monthDataJson ?>,
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
          label: i => '₱' + parseFloat(i.yLabel).toLocaleString('en', {minimumFractionDigits:2})
        }
      },
      scales: {
        yAxes: [{ ticks: { beginAtZero: true, callback: v => '₱' + v.toLocaleString() } }]
      }
    }
  });
});

// ── Daily Revenue Line Chart ──────────────────────────────────
$(function () {
  new Chart($('#lineChart').get(0).getContext('2d'), {
    type: 'line',
    data: {
      labels: <?= $dayLabelsJson ?>,
      datasets: [{
        label: 'Daily Revenue (₱)',
        data: <?= $dayDataJson ?>,
        fill: true,
        backgroundColor: 'rgba(60,141,188,0.15)',
        borderColor:     'rgba(60,141,188,1)',
        borderWidth: 2,
        pointRadius: 4,
        pointBackgroundColor: 'rgba(60,141,188,1)'
      }]
    },
    options: {
      responsive: true,
      legend: { display: false },
      tooltips: {
        callbacks: {
          label: i => '₱' + parseFloat(i.yLabel).toLocaleString('en', {minimumFractionDigits:2})
        }
      },
      scales: {
        yAxes: [{ ticks: { beginAtZero: true, callback: v => '₱' + v.toLocaleString() } }]
      }
    }
  });
});

// ── Donut Chart ───────────────────────────────────────────────
<?php if (!empty($topItems)): ?>
$(function () {
  new Chart($('#donutChart').get(0).getContext('2d'), {
    type: 'doughnut',
    data: {
      labels: <?= $donutLabels ?>,
      datasets: [{
        data: <?= $donutData ?>,
        backgroundColor: [
          '#f56954','#00a65a','#f39c12','#00c0ef',
          '#3c8dbc','#d2d6de','#e91e8c','#8e44ad',
          '#27ae60','#e74c3c'
        ]
      }]
    },
    options: { maintainAspectRatio: false, responsive: true }
  });
});
<?php endif; ?>

// ── DataTable ─────────────────────────────────────────────────
$(function () {
  $('#example1').DataTable({
    responsive: true, lengthChange: false, autoWidth: false,
    order: [[3, 'desc']],
    buttons: ['copy','csv','excel','pdf','print','colvis']
  }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
});

// ── Dark Mode ─────────────────────────────────────────────────
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