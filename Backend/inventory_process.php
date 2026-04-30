<?php
// Backend/inventory_process.php
// DB: operlytics | table: ingredients
// columns: id, name, unit, stock_qty, low_stock_threshold, created_at, updated_at

session_start();
require_once 'conn.php';

$redirect = "../Frontend/ADMIN/inventory.php";

// ═══════════════════════════════════════════════
//  ADD  (POST + name="save_ingredient")
// ═══════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ingredient'])) {

    $name               = trim($_POST['name']               ?? '');
    $unit               = trim($_POST['unit']               ?? '');
    $stock_qty          = floatval($_POST['stock_qty']       ?? 0);
    $low_stock_threshold = floatval($_POST['low_stock_threshold'] ?? 0);
    $expiry_date        = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $now                = date('Y-m-d H:i:s');

    if (empty($name) || empty($unit)) {
        $_SESSION['error'] = "Name and Unit are required.";
        header("Location: $redirect"); exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO ingredients (name, unit, stock_qty, low_stock_threshold, expiry_date, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('ssddsss', $name, $unit, $stock_qty, $low_stock_threshold, $expiry_date, $now, $now);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Ingredient \"" . htmlspecialchars($name) . "\" added successfully!";
    } else {
        $_SESSION['error'] = "Failed to add: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
    header("Location: $redirect"); exit;
}

// ═══════════════════════════════════════════════
//  EDIT  (POST + name="update_ingredient")
// ═══════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ingredient'])) {

    $id                  = intval($_POST['id']                  ?? 0);
    $name                = trim($_POST['name']                  ?? '');
    $unit                = trim($_POST['unit']                  ?? '');
    $stock_qty           = floatval($_POST['stock_qty']          ?? 0);
    $low_stock_threshold = floatval($_POST['low_stock_threshold'] ?? 0);
    $expiry_date         = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $now                 = date('Y-m-d H:i:s');

    if ($id <= 0 || empty($name) || empty($unit)) {
        $_SESSION['error'] = "Please fill in all required fields.";
        header("Location: $redirect"); exit;
    }

    $stmt = $conn->prepare(
        "UPDATE ingredients
         SET name=?, unit=?, stock_qty=?, low_stock_threshold=?, expiry_date=?, updated_at=?
         WHERE id=?"
    );
    $stmt->bind_param('ssddssi', $name, $unit, $stock_qty, $low_stock_threshold, $expiry_date, $now, $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Ingredient \"" . htmlspecialchars($name) . "\" updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
    header("Location: $redirect"); exit;
}

// ═══════════════════════════════════════════════
//  BULK RESTOCK  (POST + name="bulk_restock")
// ═══════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_restock'])) {

    $restock_ids = $_POST['restock_ids'] ?? [];
    $restock_qty = $_POST['restock_qty'] ?? [];
    $now         = date('Y-m-d H:i:s');
    $updated     = 0;

    if (empty($restock_ids)) {
        $_SESSION['error'] = "No items were selected for restock.";
        header("Location: $redirect"); exit;
    }

    $stmt = $conn->prepare(
        "UPDATE ingredients
         SET stock_qty = stock_qty + ?, updated_at = ?
         WHERE id = ?"
    );

    foreach ($restock_ids as $rid) {
        $rid = intval($rid);
        $qty = floatval($restock_qty[$rid] ?? 0);
        if ($qty <= 0) continue;

        $stmt->bind_param('dsi', $qty, $now, $rid);
        if ($stmt->execute()) $updated++;
    }

    $stmt->close();
    $conn->close();

    $_SESSION['success'] = "$updated ingredient(s) restocked successfully!";
    header("Location: $redirect"); exit;
}

// ═══════════════════════════════════════════════
//  DELETE  (GET  ?action=delete&id=N)
// ═══════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['action']) && $_GET['action'] === 'delete' &&
    isset($_GET['id'])) {

    $id = intval($_GET['id']);

    if ($id <= 0) {
        $_SESSION['error'] = "Invalid ingredient ID.";
        header("Location: $redirect"); exit;
    }

    // 1. Remove all recipe links for this ingredient first (FK constraint)
    $stmt = $conn->prepare("DELETE FROM menu_ingredients WHERE ingredient_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // 2. Now safe to delete the ingredient itself
    $stmt = $conn->prepare("DELETE FROM ingredients WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Ingredient deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
    header("Location: $redirect"); exit;
}

// ═══════════════════════════════════════════════
//  BULK UPDATE THRESHOLDS  (POST + name="bulk_update_thresholds")
// ═══════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_thresholds'])) {

    $thresholds = $_POST['threshold'] ?? [];
    $now        = date('Y-m-d H:i:s');
    $updated    = 0;

    if (empty($thresholds)) {
        $_SESSION['error'] = "No threshold data received.";
        header("Location: $redirect"); exit;
    }

    $stmt = $conn->prepare(
        "UPDATE ingredients SET low_stock_threshold = ?, updated_at = ? WHERE id = ?"
    );

    foreach ($thresholds as $id => $threshold) {
        $id        = intval($id);
        $threshold = floatval($threshold);
        if ($id <= 0) continue;

        $stmt->bind_param('dsi', $threshold, $now, $id);
        if ($stmt->execute()) $updated++;
    }

    $stmt->close();
    $conn->close();

    $_SESSION['success'] = "$updated threshold(s) updated successfully!";
    header("Location: $redirect"); exit;
}

// ── Fallback ──────────────────────────────────────────────────
$conn->close();
header("Location: $redirect");
exit;
?>