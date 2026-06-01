<?php
/**
 * debug_inventory.php
 * Drop this file into: C:\xampp\htdocs\capstone_final\capstone\
 * Then visit: http://localhost/capstone_final/capstone/debug_inventory.php
 * DELETE this file after you're done fixing!
 */

$host   = 'localhost';
$user   = 'root';
$pass   = '';
$dbName = 'empress_cafe';

$conn = new mysqli($host, $user, $pass, $dbName);
if ($conn->connect_error) {
    die('<h2 style="color:red">DB Connect Failed: ' . $conn->connect_error . '</h2>');
}

// ── Handle POST actions ──────────────────────────────────────────────────────
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Create menu_ingredients table
    if (isset($_POST['create_table'])) {
        // Detect if menu.id and ingredients.id are INT or INT UNSIGNED
        $menuIdType = 'INT';
        $ingIdType  = 'INT';

        $r1 = $conn->query("SHOW COLUMNS FROM menu WHERE Field='id'");
        if ($r1 && $row = $r1->fetch_assoc()) {
            $menuIdType = stripos($row['Type'], 'unsigned') !== false ? 'INT UNSIGNED' : 'INT';
        }
        $r2 = $conn->query("SHOW COLUMNS FROM ingredients WHERE Field='id'");
        if ($r2 && $row = $r2->fetch_assoc()) {
            $ingIdType = stripos($row['Type'], 'unsigned') !== false ? 'INT UNSIGNED' : 'INT';
        }

        $sql = "CREATE TABLE IF NOT EXISTS menu_ingredients (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            menu_id       $menuIdType NOT NULL,
            ingredient_id $ingIdType NOT NULL,
            qty_needed    DECIMAL(10,3) NOT NULL DEFAULT 1.000,
            UNIQUE KEY uq_menu_ing (menu_id, ingredient_id),
            FOREIGN KEY (menu_id)       REFERENCES menu(id)        ON DELETE CASCADE,
            FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
        )";

        if ($conn->query($sql)) {
            $msg = '<div class="ok">✅ menu_ingredients table created successfully!</div>';
        } else {
            $msg = '<div class="err">❌ Create failed: ' . $conn->error . '<br><pre>' . htmlspecialchars($sql) . '</pre></div>';
        }
    }

    // 2. Assign ingredient to menu item
    if (isset($_POST['assign_ing'])) {
        $menu_id       = (int)$_POST['assign_menu_id'];
        $ingredient_id = (int)$_POST['assign_ing_id'];
        $qty_needed    = (float)$_POST['assign_qty'];

        if ($menu_id && $ingredient_id && $qty_needed > 0) {
            $stmt = $conn->prepare(
                'INSERT INTO menu_ingredients (menu_id, ingredient_id, qty_needed)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE qty_needed = VALUES(qty_needed)'
            );
            $stmt->bind_param('iid', $menu_id, $ingredient_id, $qty_needed);
            if ($stmt->execute()) {
                $msg = '<div class="ok">✅ Recipe saved! Menu item linked to ingredient.</div>';
            } else {
                $msg = '<div class="err">❌ Failed: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        } else {
            $msg = '<div class="err">❌ Fill in all fields and qty must be > 0.</div>';
        }
    }

    // 3. Delete a recipe row
    if (isset($_POST['delete_recipe'])) {
        $id = (int)$_POST['recipe_id'];
        if ($conn->query("DELETE FROM menu_ingredients WHERE id = $id")) {
            $msg = '<div class="ok">✅ Recipe row deleted.</div>';
        } else {
            $msg = '<div class="err">❌ ' . $conn->error . '</div>';
        }
    }

    // 4. Simulate deduction test
    if (isset($_POST['test_deduct'])) {
        $menu_id = (int)$_POST['test_menu_id'];
        $qty     = (int)$_POST['test_qty'];

        $r = $conn->query("SELECT mi.ingredient_id, mi.qty_needed, i.name, i.stock_qty, i.unit
                           FROM menu_ingredients mi
                           JOIN ingredients i ON i.id = mi.ingredient_id
                           WHERE mi.menu_id = $menu_id");

        if (!$r || $r->num_rows === 0) {
            $msg = '<div class="err">❌ No ingredients linked to that menu item. Add recipes first.</div>';
        } else {
            $lines = [];
            while ($row = $r->fetch_assoc()) {
                $deduct = $row['qty_needed'] * $qty;
                $before = (float)$row['stock_qty'];
                $after  = max($before - $deduct, 0);
                $conn->query("UPDATE ingredients SET stock_qty = GREATEST(stock_qty - $deduct, 0), updated_at = NOW() WHERE id = {$row['ingredient_id']}");
                $lines[] = "• <strong>{$row['name']}</strong>: {$before} → {$after} {$row['unit']} (deducted {$deduct})";
            }
            $msg = '<div class="ok">✅ Test deduction done!<br>' . implode('<br>', $lines) . '</div>';
        }
    }
}

// ── Read current state ───────────────────────────────────────────────────────
$tables = [];
$tr = $conn->query("SHOW TABLES");
while ($t = $tr->fetch_row()) $tables[] = $t[0];

$hasMenuIng   = in_array('menu_ingredients', $tables);
$hasMenu      = in_array('menu', $tables);
$hasIng       = in_array('ingredients', $tables);
$hasOrderItems= in_array('order_items', $tables);

$menuItems   = $hasMenu ? [] : [];
$ingredients = $hasIng  ? [] : [];
$recipes     = [];
$recentOrders= [];

if ($hasMenu) {
    $r = $conn->query("SELECT id, name, category FROM menu WHERE is_available=1 ORDER BY category, name");
    while ($row = $r->fetch_assoc()) $menuItems[] = $row;
}
if ($hasIng) {
    $r = $conn->query("SELECT id, name, unit, stock_qty FROM ingredients ORDER BY name");
    while ($row = $r->fetch_assoc()) $ingredients[] = $row;
}
if ($hasMenuIng) {
    $r = $conn->query("SELECT mi.id, m.name AS menu_name, i.name AS ing_name, i.unit, mi.qty_needed
                       FROM menu_ingredients mi
                       JOIN menu m ON m.id = mi.menu_id
                       JOIN ingredients i ON i.id = mi.ingredient_id
                       ORDER BY m.name, i.name");
    while ($row = $r->fetch_assoc()) $recipes[] = $row;
}
if ($hasOrderItems) {
    $r = $conn->query("SELECT o.id, o.total_amt, o.status, o.created_at,
                              GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items
                       FROM orders o
                       JOIN order_items oi ON oi.order_id = o.id
                       JOIN menu m ON m.id = oi.menu_id
                       GROUP BY o.id ORDER BY o.id DESC LIMIT 5");
    if ($r) while ($row = $r->fetch_assoc()) $recentOrders[] = $row;
}

?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Inventory Debug Tool – Empress Café</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; background: #f4f6fb; padding: 20px; color: #222; }
  h1 { color: #c0185e; margin-bottom: 4px; }
  .sub { color: #888; font-size: 13px; margin-bottom: 24px; }
  .warn { background: #fff3cd; border: 1px solid #ffc107; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; }
  .card { background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
  h2 { font-size: 16px; color: #333; margin-bottom: 14px; border-bottom: 1px solid #eee; padding-bottom: 8px; }
  .ok  { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px; }
  .err { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px; }
  .badge-ok  { background: #28a745; color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 20px; }
  .badge-err { background: #dc3545; color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 20px; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { background: #f8f9fa; text-align: left; padding: 7px 10px; border-bottom: 2px solid #dee2e6; }
  td { padding: 6px 10px; border-bottom: 1px solid #f0f0f0; }
  tr:hover td { background: #fafafa; }
  .btn { display: inline-block; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
  .btn-primary { background: #c0185e; color: #fff; }
  .btn-success { background: #28a745; color: #fff; }
  .btn-danger  { background: #dc3545; color: #fff; padding: 4px 10px; font-size: 12px; }
  .btn-info    { background: #17a2b8; color: #fff; }
  select, input[type=number] { padding: 6px 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 13px; }
  .row3 { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
  .row3 label { font-size: 12px; color: #555; display: block; margin-bottom: 3px; }
  .status-ok  { color: #28a745; font-weight: 700; }
  .status-err { color: #dc3545; font-weight: 700; }
  .del-form { display: inline; }
</style>
</head>
<body>

<h1>🔧 Inventory Debug Tool</h1>
<p class="sub">Empress Café · empress_cafe database · <strong style="color:red">DELETE this file when done!</strong></p>

<div class="warn">⚠️ <strong>Security warning:</strong> This file has no login check. Delete it immediately after fixing your issue.</div>

<?= $msg ?>

<!-- ── STEP 1: Table Status ──────────────────────────────────────────────── -->
<div class="card">
  <h2>Step 1 — Check Required Tables</h2>
  <table>
    <tr><th>Table</th><th>Status</th><th>Action</th></tr>
    <tr>
      <td><code>menu</code></td>
      <td><?= $hasMenu ? '<span class="status-ok">✅ Exists</span>' : '<span class="status-err">❌ Missing</span>' ?></td>
      <td><?= $hasMenu ? count($menuItems) . ' menu items found' : 'Create menu table first' ?></td>
    </tr>
    <tr>
      <td><code>ingredients</code></td>
      <td><?= $hasIng ? '<span class="status-ok">✅ Exists</span>' : '<span class="status-err">❌ Missing</span>' ?></td>
      <td><?= $hasIng ? count($ingredients) . ' ingredients found' : 'Create ingredients table first' ?></td>
    </tr>
    <tr>
      <td><code>menu_ingredients</code></td>
      <td><?= $hasMenuIng ? '<span class="status-ok">✅ Exists</span>' : '<span class="status-err">❌ MISSING — this is the problem!</span>' ?></td>
      <td>
        <?php if (!$hasMenuIng): ?>
          <form method="POST">
            <button name="create_table" class="btn btn-primary">Create It Now</button>
          </form>
        <?php else: ?>
          <?= count($recipes) ?> recipe rows
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td><code>order_items</code></td>
      <td><?= $hasOrderItems ? '<span class="status-ok">✅ Exists</span>' : '<span class="status-err">❌ Missing</span>' ?></td>
      <td><?= $hasOrderItems ? 'OK' : 'Create order_items table' ?></td>
    </tr>
  </table>
</div>

<?php if ($hasMenuIng && $hasMenu && $hasIng): ?>

<!-- ── STEP 2: Assign Ingredients to Menu Items ──────────────────────────── -->
<div class="card">
  <h2>Step 2 — Assign Ingredients to Menu Items (Recipes)</h2>
  <p style="font-size:13px;color:#666;margin-bottom:12px;">
    This is what tells the system how much of each ingredient to deduct when an item is ordered.
  </p>
  <form method="POST">
    <div class="row3">
      <div>
        <label>Menu Item</label>
        <select name="assign_menu_id" required style="min-width:200px">
          <option value="">— select menu item —</option>
          <?php foreach ($menuItems as $m): ?>
            <option value="<?= $m['id'] ?>">[<?= htmlspecialchars($m['category']) ?>] <?= htmlspecialchars($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Ingredient</label>
        <select name="assign_ing_id" required style="min-width:200px">
          <option value="">— select ingredient —</option>
          <?php foreach ($ingredients as $i): ?>
            <option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['name']) ?> (<?= htmlspecialchars($i['unit']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Qty needed per order</label>
        <input type="number" name="assign_qty" step="0.001" min="0.001" placeholder="e.g. 0.2" style="width:120px" required>
      </div>
      <div>
        <button name="assign_ing" class="btn btn-success">Add Recipe Row</button>
      </div>
    </div>
  </form>
</div>

<!-- ── STEP 3: Current Recipes ───────────────────────────────────────────── -->
<div class="card">
  <h2>Step 3 — Current Recipes (menu_ingredients rows)</h2>
  <?php if (empty($recipes)): ?>
    <p style="color:#dc3545;font-weight:600">⚠️ No recipes yet! Add some above. Without recipes, no ingredients will be deducted when orders are placed.</p>
  <?php else: ?>
    <table>
      <tr><th>Menu Item</th><th>Ingredient</th><th>Qty per Order</th><th>Unit</th><th></th></tr>
      <?php foreach ($recipes as $rec): ?>
        <tr>
          <td><?= htmlspecialchars($rec['menu_name']) ?></td>
          <td><?= htmlspecialchars($rec['ing_name']) ?></td>
          <td><?= $rec['qty_needed'] ?></td>
          <td><?= htmlspecialchars($rec['unit']) ?></td>
          <td>
            <form class="del-form" method="POST" onsubmit="return confirm('Delete this recipe row?')">
              <input type="hidden" name="recipe_id" value="<?= $rec['id'] ?>">
              <button name="delete_recipe" class="btn btn-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<!-- ── STEP 4: Test Deduction ────────────────────────────────────────────── -->
<div class="card">
  <h2>Step 4 — Test Deduction (simulates a POS order)</h2>
  <p style="font-size:13px;color:#666;margin-bottom:12px;">
    This will actually deduct from your ingredients — use with a menu item that has recipes set up.
  </p>
  <form method="POST">
    <div class="row3">
      <div>
        <label>Menu Item</label>
        <select name="test_menu_id" required style="min-width:200px">
          <option value="">— select menu item —</option>
          <?php foreach ($menuItems as $m): ?>
            <option value="<?= $m['id'] ?>">[<?= htmlspecialchars($m['category']) ?>] <?= htmlspecialchars($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Quantity ordered</label>
        <input type="number" name="test_qty" min="1" value="1" style="width:80px" required>
      </div>
      <div>
        <button name="test_deduct" class="btn btn-info" onclick="return confirm('This will actually change stock_qty. Proceed?')">Run Test Deduction</button>
      </div>
    </div>
  </form>
</div>

<!-- ── STEP 5: Current Stock ─────────────────────────────────────────────── -->
<div class="card">
  <h2>Step 5 — Current Ingredient Stock</h2>
  <table>
    <tr><th>Ingredient</th><th>Stock Qty</th><th>Unit</th></tr>
    <?php foreach ($ingredients as $i): ?>
      <tr>
        <td><?= htmlspecialchars($i['name']) ?></td>
        <td><?= $i['stock_qty'] ?></td>
        <td><?= htmlspecialchars($i['unit']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<!-- ── STEP 6: Recent Orders ─────────────────────────────────────────────── -->
<?php if (!empty($recentOrders)): ?>
<div class="card">
  <h2>Step 6 — Recent Orders (last 5)</h2>
  <table>
    <tr><th>Order ID</th><th>Items</th><th>Total</th><th>Status</th><th>Created</th></tr>
    <?php foreach ($recentOrders as $o): ?>
      <tr>
        <td>#<?= $o['id'] ?></td>
        <td><?= htmlspecialchars($o['items']) ?></td>
        <td>₱<?= number_format($o['total_amt'], 2) ?></td>
        <td><?= htmlspecialchars($o['status']) ?></td>
        <td><?= $o['created_at'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php else: ?>
  <div class="card">
    <p style="color:#c0185e;font-weight:600">⚠️ Fix the missing tables above before continuing.</p>
  </div>
<?php endif; ?>

</body>
</html>
