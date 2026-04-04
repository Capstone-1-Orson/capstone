<?php
session_start();
include('conn.php');

$redirect_back = "../Frontend/ADMIN/staff-list.php";

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

    $check = $conn->prepare("SELECT id FROM user WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error'] = 'Email already exists!';
        header("Location: $redirect_back"); exit();
    }
    $check->close();

    $checkContact = $conn->prepare("SELECT id FROM user WHERE contact = ?");
    $checkContact->bind_param("s", $contact);
    $checkContact->execute();
    $contactResult = $checkContact->get_result();

    if ($contactResult->num_rows > 0) {
        $_SESSION['error'] = 'Contact number already exists!';
        header("Location: $redirect_back"); exit();
    }
    $checkContact->close();

    $stmt = $conn->prepare("INSERT INTO user (firstname, lastname, email, password, position, contact, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $firstname, $lastname, $email, $hashed_password, $position, $contact, $address);

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

        $stmt = $conn->prepare("UPDATE user SET firstname=?, lastname=?, email=?, contact=?, address=?, position=?, password=? WHERE id=?");
        $stmt->bind_param("sssssssi", $firstname, $lastname, $email, $contact, $address, $position, $hashed_password, $id);
    } else {
        $stmt = $conn->prepare("UPDATE user SET firstname=?, lastname=?, email=?, contact=?, address=?, position=? WHERE id=?");
        $stmt->bind_param("ssssssi", $firstname, $lastname, $email, $contact, $address, $position, $id);
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

    // Fetch name before deleting for the success message
    $nameRow = $conn->query("SELECT firstname, lastname FROM user WHERE id = $id")->fetch_assoc();
    $fullName = $nameRow ? htmlspecialchars($nameRow['firstname'] . ' ' . $nameRow['lastname']) : 'User';

    $stmt = $conn->prepare("DELETE FROM user WHERE id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
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