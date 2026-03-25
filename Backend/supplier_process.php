<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../Frontend/lockscreen.html");
    exit();
}

require_once 'conn.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ── ADD ──────────────────────────────────────────────────────────────────
    case 'add':
        $name           = trim($_POST['name']           ?? '');
        $category       = trim($_POST['category']       ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $phone          = trim($_POST['phone']          ?? '');
        $email          = trim($_POST['email']          ?? '');
        $address        = trim($_POST['address']        ?? '');
        $notes          = trim($_POST['notes']          ?? '');
        $status         = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

        if ($name === '' || $category === '') {
            $_SESSION['error'] = 'Supplier name and category are required.';
            header("Location: ../Frontend/ADMIN/suppliers.php");
            exit();
        }

        $stmt = $conn->prepare(
            "INSERT INTO suppliers (name, category, contact_person, phone, email, address, notes, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('ssssssss', $name, $category, $contact_person, $phone, $email, $address, $notes, $status);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Supplier \"$name\" added successfully.";
        } else {
            $_SESSION['error'] = 'Failed to add supplier: ' . $conn->error;
        }
        $stmt->close();
        break;

    // ── UPDATE ───────────────────────────────────────────────────────────────
    case 'update':
        $id             = (int)($_POST['id']             ?? 0);
        $name           = trim($_POST['name']            ?? '');
        $category       = trim($_POST['category']        ?? '');
        $contact_person = trim($_POST['contact_person']  ?? '');
        $phone          = trim($_POST['phone']           ?? '');
        $email          = trim($_POST['email']           ?? '');
        $address        = trim($_POST['address']         ?? '');
        $notes          = trim($_POST['notes']           ?? '');
        $status         = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

        if ($id <= 0 || $name === '' || $category === '') {
            $_SESSION['error'] = 'Invalid data submitted for update.';
            header("Location: ../Frontend/ADMIN/suppliers.php");
            exit();
        }

        $stmt = $conn->prepare(
            "UPDATE suppliers
             SET name=?, category=?, contact_person=?, phone=?, email=?, address=?, notes=?, status=?
             WHERE id=?"
        );
        $stmt->bind_param('ssssssssi', $name, $category, $contact_person, $phone, $email, $address, $notes, $status, $id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Supplier \"$name\" updated successfully.";
        } else {
            $_SESSION['error'] = 'Failed to update supplier: ' . $conn->error;
        }
        $stmt->close();
        break;

    // ── DELETE ───────────────────────────────────────────────────────────────
    case 'delete':
        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['error'] = 'Invalid supplier ID.';
            header("Location: ../Frontend/ADMIN/suppliers.php");
            exit();
        }

        // Grab name for the flash message before deleting
        $row = $conn->query("SELECT name FROM suppliers WHERE id = $id")->fetch_assoc();
        $supplierName = $row['name'] ?? 'Unknown';

        $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Supplier \"$supplierName\" deleted successfully.";
        } else {
            $_SESSION['error'] = 'Failed to delete supplier: ' . $conn->error;
        }
        $stmt->close();
        break;

    // ── UNKNOWN ──────────────────────────────────────────────────────────────
    default:
        $_SESSION['error'] = 'Unknown action.';
        break;
}

$conn->close();
header("Location: ../Frontend/ADMIN/suppliers.php");
exit();
