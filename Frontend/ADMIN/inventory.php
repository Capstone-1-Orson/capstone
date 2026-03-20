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
    <title>OPERLYTICS | Inventory Tracking</title>

    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">

    <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="../plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

    <link rel="stylesheet" href="../plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">
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
                    <a class="nav-link" data-widget="fullscreen"><i class="fas fa-expand-arrows-alt"></i></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="darkModeToggle" href="#" role="button">
                        <i class="fas fa-moon"></i>
                    </a>
                </li>
            </ul>
        </nav>

        <aside class="main-sidebar sidebar-dark-primary elevation-4">

            <a href="index2.php" class="brand-link">
                <img src="../dist/img/AdminLTELogo.png" class="brand-image img-circle elevation-3">
                <span class="brand-text font-weight-light">OPERLYTICS</span>
            </a>

            <div class="sidebar">

                <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                    <div class="image">
                        <img src="../dist/img/user2-160x160.jpg" class="img-circle elevation-2">
                    </div>
                    <div class="info">
                        <a href="#" class="d-block">Manager</a>
                    </div>
                </div>

                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview">

                        <li class="nav-item">
                            <a href="index2.php" class="nav-link">
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
                            <a href="./inventory.php" class="nav-link active">
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

                        <li class="nav-item">
                            <a href="sale_revenue.php" class="nav-link">
                                <i class="nav-icon fas fa-chart-line"></i>
                                <p>Sales & Revenue</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="report.php" class="nav-link">
                                <i class="nav-icon fas fa-file-alt"></i>
                                <p>Reports</p>
                            </a>
                        </li>

                        <!-- Settings (Bonus addition) -->
                        <li class="nav-item mt-auto">
                            <a href="./settings.php" class="nav-link">
                                <i class="nav-icon fas fa-cog"></i>
                                <p>Settings</p>
                            </a>
                        </li>

                    </ul>
                </nav>
            </div>
        </aside>


        <div class="content-wrapper">

            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Inventory Tracking</h1>
                        </div>

                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="index2.php">Home</a></li>
                                <li class="breadcrumb-item active">Inventory</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">

                    <div class="row mb-4">

                        <div class="col-lg-3 col-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-boxes"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Products</span>
                                    <span class="info-box-number">240</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">In Stock</span>
                                    <span class="info-box-number">180</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning"><i
                                        class="fas fa-exclamation-triangle"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Low Stock</span>
                                    <span class="info-box-number">40</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-danger"><i class="fas fa-times"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Out of Stock</span>
                                    <span class="info-box-number">20</span>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="row mb-4">

                        <div class="col-md-6">
                            <div class="card card-warning">
                                <div class="card-header">
                                    <h3 class="card-title">Low Stock Alerts</h3>

                                </div>
                                <div class="card-body">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Chicken
                                            <span class="badge badge-warning badge-pill">15 units</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Tomatoes
                                            <span class="badge badge-warning badge-pill">8 units</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Milk
                                            <span class="badge badge-danger badge-pill">0 units</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Bread
                                            <span class="badge badge-warning badge-pill">12 units</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card card-info">
                                <div class="card-header">
                                    <h3 class="card-title">Quick Actions</h3>

                                </div>
                                <div class="card-body">
                                    <div class="btn-group-vertical d-block">
                                        <button class="btn btn-primary mb-2" data-toggle="modal"
                                            data-target="#bulkRestockModal">
                                            <i class="fas fa-truck"></i> Bulk Restock
                                        </button>
                                        <button class="btn btn-secondary mb-2">
                                            <i class="fas fa-file-export"></i> Export Inventory
                                        </button>
                                        <button class="btn btn-success mb-2">
                                            <i class="fas fa-chart-line"></i> View Trends
                                        </button>
                                        <button class="btn btn-warning">
                                            <i class="fas fa-bell"></i> Set Alerts
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                       

                    </div>
                    <div class="card card-dark">

                        <div class="card-header">
                            <h3 class="card-title">Inventory Items</h3>

                            <div class="card-tools">


                                <button class="btn btn-tool" data-card-widget="maximize">
                                    <i class="fas fa-expand"></i>
                                </button>

                                <a href="#" class="btn btn-sm btn-success" data-toggle="modal"
                                    data-target="#addInventoryModal">
                                    <i class="fas fa-plus"></i> Add Product
                                </a>
                            </div>
                        </div>

                        <div class="card-body">

                            <table id="inventoryTable" class="table table-dark table-hover">

                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th>Supplier</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <tr>
                                        <td><img src="https://via.placeholder.com/50/28a745/ffffff?text=RC"
                                                class="img-circle"></td>
                                        <td>Rice</td>
                                        <td>Grains</td>
                                        <td>Local Supplier</td>
                                        <td><span class="badge badge-success">150</span></td>
                                        <td><span class="badge badge-success">Available</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-info"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td><img src="https://via.placeholder.com/50/dc3545/ffffff?text=CK"
                                                class="img-circle"></td>
                                        <td>Chicken</td>
                                        <td>Meat</td>
                                        <td>Fresh Farm</td>
                                        <td><span class="badge badge-warning">15</span></td>
                                        <td><span class="badge badge-warning">Low Stock</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-info"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td><img src="https://via.placeholder.com/50/ffc107/000000?text=EG"
                                                class="img-circle"></td>
                                        <td>Eggs</td>
                                        <td>Dairy</td>
                                        <td>Golden Farm</td>
                                        <td><span class="badge badge-danger">0</span></td>
                                        <td><span class="badge badge-danger">Out of Stock</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-info"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td><img src="https://via.placeholder.com/50/17a2b8/ffffff?text=TM"
                                                class="img-circle"></td>
                                        <td>Tomatoes</td>
                                        <td>Vegetables</td>
                                        <td>Green Valley</td>
                                        <td><span class="badge badge-warning">8</span></td>
                                        <td><span class="badge badge-warning">Low Stock</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-info"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td><img src="https://via.placeholder.com/50/6f42c1/ffffff?text=ML"
                                                class="img-circle"></td>
                                        <td>Milk</td>
                                        <td>Dairy</td>
                                        <td>Dairy Co.</td>
                                        <td><span class="badge badge-danger">0</span></td>
                                        <td><span class="badge badge-danger">Out of Stock</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-info"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td><img src="https://via.placeholder.com/50/e83e8c/ffffff?text=BR"
                                                class="img-circle"></td>
                                        <td>Bread</td>
                                        <td>Bakery</td>
                                        <td>Bakery Plus</td>
                                        <td><span class="badge badge-warning">12</span></td>
                                        <td><span class="badge badge-warning">Low Stock</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-info"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td><img src="https://via.placeholder.com/50/20c997/ffffff?text=PT"
                                                class="img-circle"></td>
                                        <td>Potatoes</td>
                                        <td>Vegetables</td>
                                        <td>Farm Fresh</td>
                                        <td><span class="badge badge-success">200</span></td>
                                        <td><span class="badge badge-success">Available</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-info"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td><img src="https://via.placeholder.com/50/fc6d26/ffffff?text=CH"
                                                class="img-circle"></td>
                                        <td>Cheese</td>
                                        <td>Dairy</td>
                                        <td>Cheese Factory</td>
                                        <td><span class="badge badge-success">75</span></td>
                                        <td><span class="badge badge-success">Available</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-info"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>

                                </tbody>

                            </table>
                        </div>
                    </div>
            </section>
        </div>

        <div class="modal fade" id="addInventoryModal">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">

                    <div class="modal-header">
                        <h4 class="modal-title">Add Inventory Item</h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>

                    <div class="modal-body">
                        <form>

                            <div class="row">

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Product Name</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Category</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>

                            </div>

                            <div class="row">

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Supplier</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Stock</label>
                                        <input type="number" class="form-control">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select class="form-control">
                                            <option>Available</option>
                                            <option>Low Stock</option>
                                            <option>Out of Stock</option>
                                        </select>
                                    </div>
                                </div>

                            </div>

                        </form>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-default" data-dismiss="modal">Close</button>
                        <button class="btn btn-success">Save Item</button>
                    </div>

                </div>
            </div>
        </div>

        <div class="modal fade" id="bulkRestockModal">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">

                    <div class="modal-header">
                        <h4 class="modal-title">Bulk Restock</h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>

                    <div class="modal-body">
                        <p>Select items to restock and enter quantities:</p>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Product</th>
                                    <th>Current Stock</th>
                                    <th>Restock Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><input type="checkbox" class="restock-check"></td>
                                    <td>Chicken</td>
                                    <td>15</td>
                                    <td><input type="number" class="form-control restock-qty" min="0"></td>
                                </tr>
                                <tr>
                                    <td><input type="checkbox" class="restock-check"></td>
                                    <td>Tomatoes</td>
                                    <td>8</td>
                                    <td><input type="number" class="form-control restock-qty" min="0"></td>
                                </tr>
                                <tr>
                                    <td><input type="checkbox" class="restock-check"></td>
                                    <td>Milk</td>
                                    <td>0</td>
                                    <td><input type="number" class="form-control restock-qty" min="0"></td>
                                </tr>
                                <tr>
                                    <td><input type="checkbox" class="restock-check"></td>
                                    <td>Bread</td>
                                    <td>12</td>
                                    <td><input type="number" class="form-control restock-qty" min="0"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button class="btn btn-success">Restock Selected</button>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <script src="../plugins/jquery/jquery.min.js"></script>
    <script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script src="../plugins/datatables/jquery.dataTables.min.js"></script>
    <script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>

    <script src="../dist/js/adminlte.js"></script>

    <script>
        $(function () {
            $("#inventoryTable").DataTable({
                "responsive": true,
                "autoWidth": false,
                "columnDefs": [
                    { "orderable": false, "targets": [0, 6] }, // Disable sorting on Image (0) and Actions (6) columns
                    { "type": "num", "targets": 4 } // Treat Stock column as numeric for proper sorting
                ],
                "order": [[1, "asc"]] // Default sort by Product Name ascending
            });
        });
    </script>

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
```