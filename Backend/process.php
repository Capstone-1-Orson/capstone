<?php
session_start();
include('conn.php');

// ── Role guard: admin only ────────────────────────────────────
if (!isset($_SESSION['user']) || ($_SESSION['position'] ?? '') !== 'admin') {
    http_response_code(403);
    header("Location: ../Frontend/login-v2.html");
    exit();
}

// ── CSRF verification ─────────────────────────────────────────
$submitted_token = trim($_POST['csrf_token'] ?? '');
$session_token   = $_SESSION['csrf_token'] ?? '';
if (empty($submitted_token) || !hash_equals($session_token, $submitted_token)) {
    $_SESSION['error'] = 'Invalid request. Please try again.';
    header("Location: ../Frontend/ADMIN/staff-list.php");
    exit();
}
// Regenerate token after use
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$redirect_back = "../Frontend/ADMIN/staff-list.php";

// ==================== ADD USER ====================
if (isset($_POST['save_user'])) {

    $firstname = trim($_POST['firstname']);
    $lastname  = trim($_POST['lastname']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];
    $position  = trim($_POST['position']);
    $contact   = trim($_POST['contact']);
    $address   = trim($_POST['address']);

    if (!preg_match('/^[0-9]{11}$/', $contact)) {
        $_SESSION['error'] = 'Contact number must be exactly 11 digits!';
        header("Location: $redirect_back"); exit();
    }

    if (!preg_match('/^[a-zA-Z0-9._%+\-]+@(gmail|yahoo)\.(com|com\.ph)$/', $email)) {
        $_SESSION['error'] = 'Only Gmail or Yahoo email addresses are allowed!';
        header("Location: $redirect_back"); exit();
    }

    if (strlen($password) < 8) {
        $_SESSION['error'] = 'Password must be at least 8 characters!';
        header("Location: $redirect_back"); exit();
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $_SESSION['error'] = 'Password must contain at least one uppercase letter!';
        header("Location: $redirect_back"); exit();
    }

    if (!preg_match('/[0-9]/', $password)) {
        $_SESSION['error'] = 'Password must contain at least one number!';
        header("Location: $redirect_back"); exit();
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $_SESSION['error'] = 'Password must contain at least one special character!';
        header("Location: $redirect_back"); exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check duplicate email
    $check = $conn->prepare("SELECT id FROM user WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['error'] = 'Email already exists!';
        header("Location: $redirect_back"); exit();
    }
    $check->close();

    // Check duplicate contact
    $checkContact = $conn->prepare("SELECT id FROM user WHERE contact = ?");
    $checkContact->bind_param("s", $contact);
    $checkContact->execute();
    if ($checkContact->get_result()->num_rows > 0) {
        $_SESSION['error'] = 'Contact number already exists!';
        header("Location: $redirect_back"); exit();
    }
    $checkContact->close();

    // Handle profile image upload
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../Frontend/ADMIN/uploads/staff/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $ext         = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed     = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileMime    = mime_content_type($_FILES['image']['tmp_name']);
        if (!in_array($ext, $allowed) || !in_array($fileMime, $allowedMime)) {
            $_SESSION['error'] = 'Only JPG, PNG, GIF, or WEBP images are allowed!';
            header("Location: $redirect_back"); exit();
        }
        $filename = 'staff_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
            $image_path = 'Frontend/ADMIN/uploads/staff/' . $filename;
        }
    }

    $stmt = $conn->prepare("INSERT INTO user (firstname, lastname, email, password, position, contact, address, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $firstname, $lastname, $email, $hashed_password, $position, $contact, $address, $image_path);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Staff member \"$firstname $lastname\" added successfully!";
    } else {
        $_SESSION['error'] = "Error: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
    header("Location: $redirect_back"); exit();
}

// ==================== UPDATE USER ====================
if (isset($_POST['update_user'])) {
    $id        = intval($_POST['user_id']);
    $firstname = trim($_POST['firstname']);
    $lastname  = trim($_POST['lastname']);
    $email     = trim($_POST['email']);
    $contact   = trim($_POST['contact']);
    $address   = trim($_POST['address']);
    $position  = trim($_POST['position']);

    if (!preg_match('/^[0-9]{11}$/', $contact)) {
        $_SESSION['error'] = 'Contact number must be exactly 11 digits!';
        header("Location: $redirect_back"); exit();
    }

    if (!preg_match('/^[a-zA-Z0-9._%+\-]+@(gmail|yahoo)\.(com|com\.ph)$/', $email)) {
        $_SESSION['error'] = 'Only Gmail or Yahoo email addresses are allowed!';
        header("Location: $redirect_back"); exit();
    }

    // Check email uniqueness (excluding current user)
    $checkEmail = $conn->prepare("SELECT id FROM user WHERE email = ? AND id != ?");
    $checkEmail->bind_param("si", $email, $id);
    $checkEmail->execute();
    if ($checkEmail->get_result()->num_rows > 0) {
        $_SESSION['error'] = 'Email already in use by another account!';
        header("Location: $redirect_back"); exit();
    }
    $checkEmail->close();

    // Handle profile image upload
    $existing_image = trim($_POST['existing_image'] ?? '');
    $image_path     = $existing_image; // default: keep current image

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../Frontend/ADMIN/uploads/staff/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $ext         = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed     = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileMime    = mime_content_type($_FILES['image']['tmp_name']);
        if (!in_array($ext, $allowed) || !in_array($fileMime, $allowedMime)) {
            $_SESSION['error'] = 'Only JPG, PNG, GIF, or WEBP images are allowed!';
            header("Location: $redirect_back"); exit();
        }
        $filename = 'staff_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
            // Delete old image file if it exists
            if ($existing_image && file_exists(__DIR__ . '/../' . $existing_image)) {
                unlink(__DIR__ . '/../' . $existing_image);
            }
            $image_path = 'Frontend/ADMIN/uploads/staff/' . $filename;
        }
    }

    // Optional password update
    $password = $_POST['password'] ?? '';
    if ($password !== '') {
        if (strlen($password) < 8) {
            $_SESSION['error'] = 'Password must be at least 8 characters!';
            header("Location: $redirect_back"); exit();
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $_SESSION['error'] = 'Password must contain at least one uppercase letter!';
            header("Location: $redirect_back"); exit();
        }
        if (!preg_match('/[0-9]/', $password)) {
            $_SESSION['error'] = 'Password must contain at least one number!';
            header("Location: $redirect_back"); exit();
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $_SESSION['error'] = 'Password must contain at least one special character!';
            header("Location: $redirect_back"); exit();
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE user SET firstname=?, lastname=?, email=?, contact=?, address=?, position=?, password=?, image=? WHERE id=?");
        $stmt->bind_param("ssssssssi", $firstname, $lastname, $email, $contact, $address, $position, $hashed_password, $image_path, $id);
    } else {
        $stmt = $conn->prepare("UPDATE user SET firstname=?, lastname=?, email=?, contact=?, address=?, position=?, image=? WHERE id=?");
        $stmt->bind_param("sssssssi", $firstname, $lastname, $email, $contact, $address, $position, $image_path, $id);
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = "Staff member \"$firstname $lastname\" updated successfully!";
    } else {
        $_SESSION['error'] = "Error: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
    header("Location: $redirect_back"); exit();
}

// ==================== DELETE USER ====================
if (isset($_POST['delete_user'])) {
    $id = intval($_POST['user_id']);

    $nameStmt = $conn->prepare("SELECT firstname, lastname, image FROM user WHERE id = ?");
    $nameStmt->bind_param("i", $id);
    $nameStmt->execute();
    $nameRow  = $nameStmt->get_result()->fetch_assoc();
    $nameStmt->close();
    $fullName = $nameRow ? htmlspecialchars($nameRow['firstname'] . ' ' . $nameRow['lastname']) : 'User';

    $stmt = $conn->prepare("DELETE FROM user WHERE id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // Remove profile photo file if it exists
        if (!empty($nameRow['image']) && file_exists(__DIR__ . '/../' . $nameRow['image'])) {
            unlink(__DIR__ . '/../' . $nameRow['image']);
        }
        $_SESSION['success'] = "Staff member \"$fullName\" deleted successfully.";
    } else {
        $_SESSION['error'] = "Error: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
    header("Location: $redirect_back"); exit();
}

$conn->close();
header("Location: $redirect_back");
exit();