<?php
// Backend/menu_process.php
session_name('ADMIN_SESSION');
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../Frontend/lockscreen.html");
    exit();
}

require_once 'conn.php';

// ── Helper: upload image ───────────────────────────────────────
function uploadImage($file, $conn) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // no file uploaded — not an error
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload error code: ' . $file['error']);
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types)) {
        throw new RuntimeException('Invalid file type. Only JPG, PNG, and WEBP are allowed.');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Image must be 2 MB or smaller.');
    }

    $ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
    $filename = uniqid('menu_', true) . '.' . $ext;
    $dir      = __DIR__ . '/../Frontend/uploads/menu/';

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $dest = $dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    return 'Frontend/uploads/menu/' . $filename; // relative path stored in DB
}

// ── Helper: delete old image file ─────────────────────────────
function deleteImageFile($path) {
    if (empty($path)) return;
    $full = __DIR__ . '/../' . $path;
    if (file_exists($full)) {
        unlink($full);
    }
}

// ── AJAX: get_ingredients for Edit modal ──────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_ingredients') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit(); }

    $rows = [];
    $stmt = $conn->prepare(
        "SELECT mi.ingredient_id, mi.qty_needed, i.name, i.unit
         FROM menu_ingredients mi
         JOIN ingredients i ON i.id = mi.ingredient_id
         WHERE mi.menu_id = ?
         ORDER BY i.name"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

// ── DELETE ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        $_SESSION['error'] = 'Invalid item ID.';
        header('Location: ../Frontend/ADMIN/menu-management.php');
        exit();
    }

    // Check if item has order history
    $check = $conn->prepare("SELECT COUNT(*) AS c FROM order_items WHERE menu_id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $hasOrders = (int)$check->get_result()->fetch_assoc()['c'];
    $check->close();

    if ($hasOrders > 0) {
        // Soft delete — mark inactive
        $stmt = $conn->prepare("UPDATE menu SET is_available = 0 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = 'Item has order history and was hidden (set to Inactive) instead of deleted.';
    } else {
        // Fetch image path before hard delete
        $imgRow = $conn->query("SELECT image FROM menu WHERE id = $id")->fetch_assoc();

        // Delete menu_ingredients first
        $conn->query("DELETE FROM menu_ingredients WHERE menu_id = $id");

        // Hard delete
        $stmt = $conn->prepare("DELETE FROM menu WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        // Remove image file
        if (!empty($imgRow['image'])) {
            deleteImageFile($imgRow['image']);
        }

        $_SESSION['success'] = 'Menu item deleted successfully.';
    }

    $conn->close();
    header('Location: ../Frontend/ADMIN/menu-management.php');
    exit();
}

// ── SAVE (Add new item) ────────────────────────────────────────
if (isset($_POST['save_menu'])) {
    $name        = trim($_POST['name']        ?? '');
    $category    = trim($_POST['category']    ?? '');
    $price       = (float)($_POST['price']    ?? 0);
    $is_available= (int)($_POST['is_available'] ?? 1);
    $description = trim($_POST['description'] ?? '');
    $ingredients = $_POST['ingredients_json'] ?? '[]';

    if (!$name || !$category || $price <= 0) {
        $_SESSION['error'] = 'Name, category, and a valid price are required.';
        header('Location: ../Frontend/ADMIN/menu-management.php');
        exit();
    }

    try {
        $imagePath = uploadImage($_FILES['image'] ?? null, $conn);

        $stmt = $conn->prepare(
            "INSERT INTO menu (name, category, price, is_available, description, image)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('ssdiss', $name, $category, $price, $is_available, $description, $imagePath);
        $stmt->execute();
        $newId = $conn->insert_id;
        $stmt->close();

        // Insert ingredients
        $ings = json_decode($ingredients, true);
        if (is_array($ings) && count($ings)) {
            $istmt = $conn->prepare(
                "INSERT INTO menu_ingredients (menu_id, ingredient_id, qty_needed) VALUES (?, ?, ?)"
            );
            foreach ($ings as $ing) {
                $iid = (int)$ing['ingredient_id'];
                $qty = (float)$ing['qty_needed'];
                $istmt->bind_param('iid', $newId, $iid, $qty);
                $istmt->execute();
            }
            $istmt->close();
        }

        $_SESSION['success'] = "\"$name\" added to the menu successfully.";
    } catch (RuntimeException $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    $conn->close();
    header('Location: ../Frontend/ADMIN/menu-management.php');
    exit();
}

// ── UPDATE (Edit item) ────────────────────────────────────────
if (isset($_POST['update_menu'])) {
    $id           = (int)($_POST['id']           ?? 0);
    $name         = trim($_POST['name']           ?? '');
    $category     = trim($_POST['category']       ?? '');
    $price        = (float)($_POST['price']       ?? 0);
    $is_available = (int)($_POST['is_available']  ?? 1);
    $description  = trim($_POST['description']    ?? '');
    $existing_img = trim($_POST['existing_image'] ?? '');
    $ingredients  = $_POST['ingredients_json']    ?? '[]';

    if (!$id || !$name || !$category || $price <= 0) {
        $_SESSION['error'] = 'Name, category, and a valid price are required.';
        header('Location: ../Frontend/ADMIN/menu-management.php');
        exit();
    }

    try {
        $newImagePath = uploadImage($_FILES['image'] ?? null, $conn);

        if ($newImagePath) {
            // New file uploaded — delete the old one
            deleteImageFile($existing_img);
            $imagePath = $newImagePath;
        } else {
            // Keep existing image
            $imagePath = $existing_img ?: null;
        }

        $stmt = $conn->prepare(
            "UPDATE menu
             SET name=?, category=?, price=?, is_available=?, description=?, image=?
             WHERE id=?"
        );
        $stmt->bind_param('ssdissi', $name, $category, $price, $is_available, $description, $imagePath, $id);
        $stmt->execute();
        $stmt->close();

        // Replace ingredients
        $conn->query("DELETE FROM menu_ingredients WHERE menu_id = $id");
        $ings = json_decode($ingredients, true);
        if (is_array($ings) && count($ings)) {
            $istmt = $conn->prepare(
                "INSERT INTO menu_ingredients (menu_id, ingredient_id, qty_needed) VALUES (?, ?, ?)"
            );
            foreach ($ings as $ing) {
                $iid = (int)$ing['ingredient_id'];
                $qty = (float)$ing['qty_needed'];
                $istmt->bind_param('iid', $id, $iid, $qty);
                $istmt->execute();
            }
            $istmt->close();
        }

        $_SESSION['success'] = "\"$name\" updated successfully.";
    } catch (RuntimeException $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    $conn->close();
    header('Location: ../Frontend/ADMIN/menu-management.php');
    exit();
}

// Fallback
$conn->close();
header('Location: ../Frontend/ADMIN/menu-management.php');
exit();