<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../../Frontend/lockscreen.html");
    exit();
}

require_once '../../Backend/conn.php';

// ── Helper: check if a table exists ───────────────────────────
function tableExists($conn, $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

$hasOrderItems = tableExists($conn, 'order_items');

// ── Summary Stats ─────────────────────────────────────────────
$totalOrders  = 0;
$totalRevenue = 0.0;
$res = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS rev FROM orders");
if ($res && $row = $res->fetch_assoc()) {
    $totalOrders  = (int)$row['cnt'];
    $totalRevenue = (float)$row['rev'];
}

$totalTables = 0;
$res2 = $conn->query("SELECT COUNT(DISTINCT table_no) AS cnt FROM orders");
if ($res2 && $row2 = $res2->fetch_assoc()) {
    $totalTables = (int)$row2['cnt'];
}

$topItem = 'No orders yet';
if ($hasOrderItems) {
    $res3 = $conn->query(
        "SELECT m.name, SUM(oi.qty) AS total_qty
         FROM order_items oi
         JOIN menu m ON m.id = oi.menu_id
         GROUP BY oi.menu_id ORDER BY total_qty DESC LIMIT 1"
    );
    if ($res3 && $row3 = $res3->fetch_assoc()) {
        $topItem = htmlspecialchars($row3['name']) . ' (' . (int)$row3['total_qty'] . ' sold)';
    }
}

// ── Sales Chart (last 7 days) ─────────────────────────────────
$chartLabels = [];
$chartData   = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('M d', strtotime("-$i days"));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at) = ?");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $chartData[] = (float)$stmt->get_result()->fetch_assoc()['rev'];
    $stmt->close();
}

// ── Inventory ─────────────────────────────────────────────────
$inventoryRows = [];
$res4 = $conn->query("SELECT name, unit, stock_qty, low_stock_threshold FROM ingredients ORDER BY name");
if ($res4) while ($row = $res4->fetch_assoc()) $inventoryRows[] = $row;

// ── Orders ────────────────────────────────────────────────────
$orderRows = [];
if ($hasOrderItems) {
    $res5 = $conn->query(
        "SELECT o.id AS order_id, o.created_at, o.table_no, o.status, o.total_amt,
                GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items,
                SUM(oi.qty) AS total_qty
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         JOIN menu m ON m.id = oi.menu_id
         GROUP BY o.id ORDER BY o.created_at DESC LIMIT 100"
    );
    if ($res5) while ($row = $res5->fetch_assoc()) $orderRows[] = $row;
} else {
    // Fallback: show orders without item detail if order_items doesn't exist yet
    $res5 = $conn->query(
        "SELECT id AS order_id, created_at, table_no, status, total_amt,
                '—' AS items, 0 AS total_qty
         FROM orders ORDER BY created_at DESC LIMIT 100"
    );
    if ($res5) while ($row = $res5->fetch_assoc()) $orderRows[] = $row;
}

// ── Category Sales ────────────────────────────────────────────
$catSales = [];
if ($hasOrderItems) {
    $res6 = $conn->query(
        "SELECT m.category, SUM(oi.qty * oi.unit_price) AS revenue
         FROM order_items oi JOIN menu m ON m.id = oi.menu_id
         GROUP BY m.category ORDER BY revenue DESC"
    );
    if ($res6) while ($row = $res6->fetch_assoc()) $catSales[] = $row;
}

$conn->close();
$chartLabelsJson = json_encode($chartLabels);
$chartDataJson   = json_encode($chartData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reports | Empress POS</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../dist/css/empress-cafe-theme.css">
  <style>
    body,.main-header.navbar{transition:background-color .5s ease,color .5s ease;}
    #darkModeToggle{transition:box-shadow .3s ease;}
    #darkModeToggle i{transition:transform .3s ease;}
    #darkModeToggle.clicked{box-shadow:0 0 15px rgba(255,255,255,.8);}
    #darkModeToggle.clicked i{transform:rotate(180deg) scale(1.2);}
    .summary-stat h5{font-size:1.6rem;font-weight:700;margin-bottom:2px;}
    .summary-stat span{font-size:.82rem;color:#aaa;}
    .top-item-val{font-size:1rem!important;}
  </style>
</head>
<body class="hold-transition dark-mode sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">

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
          <li class="nav-item"><a href="./index2.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Overview</p></a></li>
          <li class="nav-item"><a href="./menu-management.php" class="nav-link"><i class="nav-icon fas fa-utensils"></i><p>Menu Management</p></a></li>
          <li class="nav-item"><a href="./inventory.php" class="nav-link"><i class="nav-icon fas fa-boxes"></i><p>Inventory Tracking</p></a></li>
          <li class="nav-item"><a href="./suppliers.php" class="nav-link"><i class="nav-icon fas fa-truck"></i><p>Supplier Info</p></a></li>
          <li class="nav-item"><a href="./staff-list.php" class="nav-link"><i class="far fa-user nav-icon"></i><p>Staff List</p></a></li>
          <li class="nav-item"><a href="./sale_revenue.php" class="nav-link"><i class="nav-icon fas fa-chart-line"></i><p>Sales &amp; Revenue</p></a></li>
          <li class="nav-item"><a href="./report.php" class="nav-link active"><i class="nav-icon fas fa-file-alt"></i><p>Reports</p></a></li>
          <li class="nav-item mt-auto"><a href="../../Backend/logout.php" class="nav-link"><i class="nav-icon fas fa-sign-out-alt"></i><p>Log Out</p></a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">Reports</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Reports</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">

        <?php if (!$hasOrderItems): ?>
        <!-- ── Setup Notice ──────────────────────────────── -->
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
          <i class="fas fa-exclamation-triangle mr-2"></i>
          <strong>Setup Required:</strong> The <code>order_items</code> table is missing.
          Please run <strong>create_order_items_table.sql</strong> in phpMyAdmin to enable
          full order detail reporting. Orders are still displayed using basic data.
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php endif; ?>

        <!-- ── Summary Stats ──────────────────────────────── -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-chart-pie mr-2"></i>Summary Report
              <small class="text-muted ml-2" style="font-size:12px;">Live from POS database</small>
            </h3>
          </div>
          <div class="card-body">
            <div class="row text-center">
              <div class="col-md-3 summary-stat">
                <h5 class="text-primary"><?= number_format($totalOrders) ?></h5>
                <span>Total Orders</span>
              </div>
              <div class="col-md-3 summary-stat">
                <h5 class="text-success">&#8369;<?= number_format($totalRevenue, 2) ?></h5>
                <span>Total Revenue</span>
              </div>
              <div class="col-md-3 summary-stat">
                <h5 class="text-warning"><?= $totalTables ?></h5>
                <span>Tables Served</span>
              </div>
              <div class="col-md-3 summary-stat">
                <h5 class="text-danger top-item-val"><?= $topItem ?></h5>
                <span>Top Selling Item</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Sales Chart ───────────────────────────────── -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>Sales Overview &mdash; Last 7 Days</h3>
          </div>
          <div class="card-body">
            <canvas id="salesChart" height="80"></canvas>
          </div>
        </div>

        <!-- ── Category Revenue ──────────────────────────── -->
        <?php if (!empty($catSales)): ?>
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-tags mr-2"></i>Revenue by Category</h3>
          </div>
          <div class="card-body">
            <div class="row text-center">
              <?php foreach ($catSales as $cs): ?>
              <div class="col-md-3 summary-stat mb-3">
                <h5 class="text-info">&#8369;<?= number_format((float)$cs['revenue'], 2) ?></h5>
                <span><?= htmlspecialchars($cs['category']) ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- ── Inventory Report ──────────────────────────── -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-boxes mr-2"></i>Inventory Report</h3>
          </div>
          <div class="card-body">
            <table id="example1" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Ingredient</th>
                  <th>Unit</th>
                  <th>Stock Qty</th>
                  <th>Low Stock Threshold</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($inventoryRows as $inv):
                  $qty = (float)$inv['stock_qty'];
                  $thr = (float)$inv['low_stock_threshold'];
                  if ($qty <= 0)        $badge = '<span class="badge badge-danger">Out of Stock</span>';
                  elseif ($qty <= $thr) $badge = '<span class="badge badge-warning">Low Stock</span>';
                  else                  $badge = '<span class="badge badge-success">In Stock</span>';
                ?>
                <tr>
                  <td><?= htmlspecialchars($inv['name']) ?></td>
                  <td><?= htmlspecialchars($inv['unit']) ?></td>
                  <td><?= number_format($qty, 3) ?></td>
                  <td><?= number_format($thr, 3) ?></td>
                  <td><?= $badge ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ── Order Report ──────────────────────────────── -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-receipt mr-2"></i>Order Report</h3>
          </div>
          <div class="card-body">
            <table id="example2" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Order ID</th>
                  <th>Date &amp; Time</th>
                  <th>Table</th>
                  <th>Items Ordered</th>
                  <th>Total Qty</th>
                  <th>Total (&#8369;)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orderRows as $ord): ?>
                <tr>
                  <td><strong>#<?= (int)$ord['order_id'] ?></strong></td>
                  <td><?= htmlspecialchars($ord['created_at']) ?></td>
                  <td>Table <?= htmlspecialchars($ord['table_no']) ?></td>
                  <td><?= htmlspecialchars($ord['items']) ?></td>
                  <td><?= (int)$ord['total_qty'] ?></td>
                  <td>&#8369;<?= number_format((float)$ord['total_amt'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </section>
  </div><!-- /.content-wrapper -->
</div><!-- ./wrapper -->

<!-- Scripts -->
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
// ── DataTables ────────────────────────────────────────────────
$(function () {
  $("#example1").DataTable({
    responsive: true, lengthChange: false, autoWidth: false,
    order: [[2, "asc"]],
    buttons: [
      { extend:'copy',  title:'Inventory Report' },
      { extend:'csv',   title:'Inventory_Report' },
      { extend:'excel', title:'Inventory_Report' },
      { extend:'pdf',   title:'Inventory_Report' },
      { extend:'print', title:'Inventory Report' },
      'colvis'
    ]
  }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');

  $("#example2").DataTable({
    responsive: true, lengthChange: false, autoWidth: false,
    order: [[0, "desc"]],
    buttons: [
      { extend:'copy',  title:'Order Report' },
      { extend:'csv',   title:'Order_Report' },
      { extend:'excel', title:'Order_Report' },
      { extend:'pdf',   title:'Order_Report' },
      { extend:'print', title:'Order Report' },
      'colvis'
    ]
  }).buttons().container().appendTo('#example2_wrapper .col-md-6:eq(0)');
});

// ── Sales Chart ───────────────────────────────────────────────
$(function () {
  var ctx = document.getElementById('salesChart').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= $chartLabelsJson ?>,
      datasets: [{
        label: 'Revenue',
        data: <?= $chartDataJson ?>,
        backgroundColor: 'rgba(233,30,140,0.55)',
        borderColor: 'rgba(233,30,140,1)',
        borderWidth: 2,
        hoverBackgroundColor: 'rgba(233,30,140,0.8)'
      }]
    },
    options: {
      responsive: true,
      legend: { display: false },
      tooltips: {
        callbacks: {
          label: function(item) {
            return '₱' + parseFloat(item.yLabel).toLocaleString('en', {minimumFractionDigits:2});
          }
        }
      },
      scales: {
        yAxes: [{
          ticks: {
            beginAtZero: true,
            callback: function(val) { return '₱' + val.toLocaleString(); }
          }
        }]
      }
    }
  });
});

// ── Dark Mode ─────────────────────────────────────────────────
$(function () {
  var darkMode = localStorage.getItem('darkMode');
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
    setTimeout(function() { $('#darkModeToggle').removeClass('clicked'); }, 300);
  });
});
</script>
</body>
</html>