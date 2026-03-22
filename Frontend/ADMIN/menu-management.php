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
  <title>OPERLYTICS | Menu Management</title>

  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">

  <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

  <link rel="stylesheet" href="../plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
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

      <a href="#" class="brand-link">
        <img src="../dist/img/Empress' Cafe Boracay.jpg" alt="AdminLTE Logo" class="brand-image img-circle elevation-3"
          style="opacity: .8">
        <span class="brand-text font-weight-light">Empress' Cafe</span>
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
              <a href="./menu-management.php" class="nav-link active">
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
              <a href="../../Backend/logout.php" class="nav-link">
                  <i class="nav-icon fas fa-cog"></i>
                  <p>Log Out</p>
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
              <h1 class="m-0">Menu Management</h1>
            </div>

            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="index2.php">Home</a></li>
                <li class="breadcrumb-item active">Menu</li>
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
                <span class="info-box-icon bg-info"><i class="fas fa-utensils"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Total Items</span>
                  <span class="info-box-number">45</span>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-6">
              <div class="info-box">
                <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Active Items</span>
                  <span class="info-box-number">40</span>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-6">
              <div class="info-box">
                <span class="info-box-icon bg-warning"><i class="fas fa-pause"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Inactive Items</span>
                  <span class="info-box-number">5</span>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-6">
              <div class="info-box">
                <span class="info-box-icon bg-danger"><i class="fas fa-tags"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Categories</span>
                  <span class="info-box-number">8</span>
                </div>
              </div>
            </div>

          </div>

          <div class="row">
            <div class="col-12">

              <div class="card card-dark">

                <div class="card-header">
                  <h3 class="card-title">Menu Items</h3>

                  <div class="card-tools">
                    <button class="btn btn-tool" data-card-widget="maximize">
                      <i class="fas fa-expand"></i>
                    </button>

                    <a href="#" class="btn btn-sm btn-success" data-toggle="modal" data-target="#addMenuModal">
                      <i class="fas fa-plus"></i> Add Item
                    </a>
                  </div>
                </div>

                <div class="card-body">

                  <table id="menuTable" class="table table-dark table-hover">

                    <thead>
                      <tr>
                        <th>Image</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                      </tr>
                    </thead>

                    <tbody>

                      <tr>
                        <td><img src="https://via.placeholder.com/50/28a745/ffffff?text=PZ" class="img-circle"></td>
                        <td>Margherita Pizza</td>
                        <td>Main Course</td>
                        <td>$12.99</td>
                        <td><span class="badge badge-success">Active</span></td>
                        <td>
                          <button class="btn btn-sm btn-info"><i class="fas fa-edit"></i></button>
                          <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </td>
                      </tr>

                      <tr>
                        <td><img src="https://via.placeholder.com/50/dc3545/ffffff?text=BG" class="img-circle"></td>
                        <td>Cheeseburger</td>
                        <td>Main Course</td>
                        <td>$9.99</td>
                        <td><span class="badge badge-success">Active</span></td>
                        <td>
                          <button class="btn btn-sm btn-info"><i class="fas fa-edit"></i></button>
                          <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </td>
                      </tr>

                      <tr>
                        <td><img src="https://via.placeholder.com/50/ffc107/000000?text=SL" class="img-circle"></td>
                        <td>Caesar Salad</td>
                        <td>Appetizer</td>
                        <td>$7.99</td>
                        <td><span class="badge badge-warning">Inactive</span></td>
                        <td>
                          <button class="btn btn-sm btn-info"><i class="fas fa-edit"></i></button>
                          <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </td>
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

    <div class="modal fade" id="addMenuModal">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">

          <div class="modal-header">
            <h4 class="modal-title">Add Menu Item</h4>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>

          <div class="modal-body">
            <form id="menuForm">

              <div class="row">

                <div class="col-md-6">
                  <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" class="form-control" id="itemName" required>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="form-group">
                    <label>Category</label>
                    <select class="form-control" id="itemCategory">
                      <option>Main Course</option>
                      <option>Appetizer</option>
                      <option>Dessert</option>
                      <option>Beverage</option>
                    </select>
                  </div>
                </div>

              </div>

              <div class="row">

                <div class="col-md-6">
                  <div class="form-group">
                    <label>Price</label>
                    <input type="number" step="0.01" class="form-control" id="itemPrice" required>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="form-group">
                    <label>Status</label>
                    <select class="form-control" id="itemStatus">
                      <option>Active</option>
                      <option>Inactive</option>
                    </select>
                  </div>
                </div>

              </div>

              <div class="form-group">
                <label>Image URL (optional)</label>
                <input type="url" class="form-control" id="itemImage" placeholder="https://via.placeholder.com/50/...">
              </div>

              <div class="form-group">
                <label>Ingredients</label>
                <div class="input-group mb-2">
                  <input type="text" class="form-control" id="ingredientInput" placeholder="e.g. Cheese, Tomato Sauce, Basil…">
                  <div class="input-group-append">
                    <button type="button" class="btn btn-success btn-sm px-3" id="addIngredientBtn">
                      <i class="fas fa-plus"></i> Add
                    </button>
                  </div>
                </div>
                <div id="ingredientTags" style="display:flex;flex-wrap:wrap;gap:6px;min-height:38px;padding:7px 10px;border:1px solid rgba(233,30,140,0.25);border-radius:8px;background:rgba(233,30,140,0.04);">
                  <span id="ingredientPlaceholder" style="color:#aaa;font-size:12px;align-self:center;font-style:italic;">No ingredients added yet</span>
                </div>
                <input type="hidden" id="itemIngredients">
                <small class="text-muted mt-1 d-block">Press <kbd>Enter</kbd> or click <strong>Add</strong> after each ingredient. Click a tag to remove it.</small>
              </div>

            </form>
          </div>

          <div class="modal-footer">
            <button class="btn btn-default" data-dismiss="modal">Close</button>
            <button class="btn btn-success" id="saveMenuBtn">Save Item</button>
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
  
  <!-- overlayScrollbars -->
  <script src="../plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
  <!-- AdminLTE App -->

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


  <script>
    $(function () {
      $("#menuTable").DataTable({
        "responsive": true,
        "autoWidth": false
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
  <!-- Ingredient Tag Input -->
  <script>
    $(function () {

      var ingredients = [];

      function renderTags() {
        var container = $('#ingredientTags');
        container.empty();
        if (ingredients.length === 0) {
          container.append('<span id="ingredientPlaceholder" style="color:#aaa;font-size:12px;align-self:center;font-style:italic;">No ingredients added yet</span>');
        } else {
          $.each(ingredients, function (i, ing) {
            var tag = $('<span>')
              .css({
                display: 'inline-flex',
                alignItems: 'center',
                gap: '5px',
                padding: '4px 10px',
                borderRadius: '20px',
                background: 'rgba(233,30,140,0.12)',
                border: '1px solid rgba(233,30,140,0.35)',
                color: 'var(--pink, #e91e8c)',
                fontSize: '12.5px',
                fontWeight: '500',
                cursor: 'pointer',
                transition: 'all 0.2s'
              })
              .attr('title', 'Click to remove')
              .html('<i class="fas fa-circle-dot" style="font-size:8px;opacity:0.6"></i> ' + $('<div>').text(ing).html() + ' <i class="fas fa-times" style="font-size:9px;opacity:0.7;margin-left:2px"></i>')
              .on('click', function () {
                ingredients.splice(i, 1);
                renderTags();
                updateHidden();
              })
              .on('mouseenter', function () {
                $(this).css('background', 'rgba(233,30,140,0.22)');
              })
              .on('mouseleave', function () {
                $(this).css('background', 'rgba(233,30,140,0.12)');
              });
            container.append(tag);
          });
        }
      }

      function addIngredient() {
        var val = $('#ingredientInput').val().trim();
        if (!val) return;
        // Support comma-separated: "Cheese, Basil, Tomato"
        var parts = val.split(',');
        $.each(parts, function (_, part) {
          var p = part.trim();
          if (p && !ingredients.includes(p)) {
            ingredients.push(p);
          }
        });
        $('#ingredientInput').val('');
        renderTags();
        updateHidden();
      }

      function updateHidden() {
        $('#itemIngredients').val(ingredients.join(', '));
      }

      $('#addIngredientBtn').on('click', addIngredient);

      $('#ingredientInput').on('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          addIngredient();
        }
      });

      // Clear ingredients when modal is hidden
      $('#addMenuModal').on('hidden.bs.modal', function () {
        ingredients = [];
        renderTags();
        updateHidden();
        $('#ingredientInput').val('');
      });

      // On Save — log the ingredients (extend with your AJAX/backend here)
      $('#saveMenuBtn').on('click', function () {
        var name  = $('#itemName').val().trim();
        var price = $('#itemPrice').val().trim();
        if (!name || !price) {
          alert('Item Name and Price are required.');
          return;
        }
        console.log('Saving item:', {
          name:        name,
          category:    $('#itemCategory').val(),
          price:       price,
          status:      $('#itemStatus').val(),
          image:       $('#itemImage').val(),
          ingredients: $('#itemIngredients').val()
        });
        // TODO: replace console.log with your $.ajax() call to the backend
        $('#addMenuModal').modal('hide');
      });

    });
  </script>

</body>

</html>
```