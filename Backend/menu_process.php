<?php
// Backend/menu_process.php
// DB: operlytics | table: menu | columns: id, name, description, price, category, is_available, created_at

session_start();
require_once 'conn.php';

$redirect = "../Frontend/ADMIN/menu-management.php";

// ═══════════════════════════════════════════════
//  ADD  (POST + name="save_menu")
// ═══════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_menu'])) {

    $name         = trim($_POST['name']         ?? '');
    $description  = trim($_POST['description']  ?? '');
    $price        = floatval($_POST['price']     ?? 0);
    $category     = trim($_POST['category']      ?? '');
    $is_available = intval($_POST['is_available'] ?? 1);
    $created_at   = date('Y-m-d H:i:s');

    if (empty($name) || empty($category) || $price <= 0) {
        $_SESSION['error'] = "Please fill in all required fields correctly.";
        header("Location: $redirect");
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO menu (name, description, price, category, is_available, created_at)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('ssdsis', $name, $description, $price, $category, $is_available, $created_at);

    if ($stmt->execute()) {
        $new_menu_id = $conn->insert_id;

        // ── Save menu_ingredients ─────────────────────────────
        $ingredients_json = trim($_POST['ingredients_json'] ?? '[]');
        $ingredients      = json_decode($ingredients_json, true);

        if (!empty($ingredients) && is_array($ingredients)) {
            $stmt2 = $conn->prepare(
                "INSERT INTO menu_ingredients (menu_id, ingredient_id, qty_needed)
                 VALUES (?, ?, ?)"
            );
            foreach ($ingredients as $ing) {
                $ing_id     = intval($ing['ingredient_id']  ?? 0);
                $qty_needed = floatval($ing['qty_needed']   ?? 0);
                if ($ing_id <= 0 || $qty_needed <= 0) continue;
                $stmt2->bind_param('iid', $new_menu_id, $ing_id, $qty_needed);
                $stmt2->execute();
            }
            $stmt2->close();
        }

        $_SESSION['success'] = "Menu item \"" . htmlspecialchars($name) . "\" added successfully!";
    } else {
        $_SESSION['error'] = "Failed to add item: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
    header("Location: $redirect");
    exit;
}

// ═══════════════════════════════════════════════
//  EDIT  (POST + name="update_menu")
// ═══════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_menu'])) {

    $id           = intval($_POST['id']           ?? 0);
    $name         = trim($_POST['name']           ?? '');
    $description  = trim($_POST['description']    ?? '');
    $price        = floatval($_POST['price']       ?? 0);
    $category     = trim($_POST['category']        ?? '');
    $is_available = intval($_POST['is_available']  ?? 1);

    if ($id <= 0 || empty($name) || empty($category) || $price <= 0) {
        $_SESSION['error'] = "Please fill in all required fields correctly.";
        header("Location: $redirect");
        exit;
    }

    $stmt = $conn->prepare(
        "UPDATE menu
         SET name=?, description=?, price=?, category=?, is_available=?
         WHERE id=?"
    );
    $stmt->bind_param('ssdsii', $name, $description, $price, $category, $is_available, $id);

    if ($stmt->execute()) {
        // ── Replace menu_ingredients ──────────────────────────
        $ingredients_json = trim($_POST['ingredients_json'] ?? '[]');
        $ingredients      = json_decode($ingredients_json, true);

        // Delete old entries first
        $del = $conn->prepare("DELETE FROM menu_ingredients WHERE menu_id = ?");
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();

        // Insert new entries
        if (!empty($ingredients) && is_array($ingredients)) {
            $stmt2 = $conn->prepare(
                "INSERT INTO menu_ingredients (menu_id, ingredient_id, qty_needed)
                 VALUES (?, ?, ?)"
            );
            foreach ($ingredients as $ing) {
                $ing_id     = intval($ing['ingredient_id']  ?? 0);
                $qty_needed = floatval($ing['qty_needed']   ?? 0);
                if ($ing_id <= 0 || $qty_needed <= 0) continue;
                $stmt2->bind_param('iid', $id, $ing_id, $qty_needed);
                $stmt2->execute();
            }
            $stmt2->close();
        }

        $_SESSION['success'] = "Menu item \"" . htmlspecialchars($name) . "\" updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update item: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
    header("Location: $redirect");
    exit;
}

// ═══════════════════════════════════════════════
//  DELETE  (GET  ?action=delete&id=N)
// ═══════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['action']) && $_GET['action'] === 'delete' &&
    isset($_GET['id'])) {

    $id = intval($_GET['id']);

    if ($id <= 0) {
        $_SESSION['error'] = "Invalid item ID.";
        header("Location: $redirect");
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM menu WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Menu item deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete item: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
    header("Location: $redirect");
    exit;
}

// ═══════════════════════════════════════════════
//  GET INGREDIENTS for Edit modal (AJAX)
//  GET ?action=get_ingredients&id=N
// ═══════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['action']) && $_GET['action'] === 'get_ingredients' &&
    isset($_GET['id'])) {

    header('Content-Type: application/json');
    $menu_id = intval($_GET['id']);

    $stmt = $conn->prepare(
        "SELECT mi.ingredient_id, mi.qty_needed, i.name, i.unit
         FROM menu_ingredients mi
         JOIN ingredients i ON i.id = mi.ingredient_id
         WHERE mi.menu_id = ?
         ORDER BY i.name"
    );
    $stmt->bind_param('i', $menu_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// ── Fallback ──────────────────────────────────────────────────
$conn->close();
header("Location: $redirect");
exit;
?>