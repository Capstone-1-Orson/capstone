<?php
session_start();
include('conn.php');

// ==================== SAVE USER ====================
if (isset($_POST['save_user'])) {

    $firstname = trim($_POST['firstname']);
    $lastname  = trim($_POST['lastname']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];
    $position  = trim($_POST['position']);
    $contact   = trim($_POST['contact']);
    $address   = trim($_POST['address']);

    if (!preg_match('/^[0-9]{11}$/', $contact)) {
        echo "<script>alert('Contact number must be exactly 11 digits!'); window.history.back();</script>";
        exit();
    }

    if (!preg_match('/^[a-zA-Z0-9._%+\-]+@(gmail|yahoo)\.(com|com\.ph)$/', $email)) {
        echo "<script>alert('Only Gmail or Yahoo email addresses are allowed!'); window.history.back();</script>";
        exit();
    }

    if (strlen($password) < 8) {
        echo "<script>alert('Password must be at least 8 characters!'); window.history.back();</script>";
        exit();
    }

    if (!preg_match('/[A-Z]/', $password)) {
        echo "<script>alert('Password must contain at least one uppercase letter!'); window.history.back();</script>";
        exit();
    }

    if (!preg_match('/[0-9]/', $password)) {
        echo "<script>alert('Password must contain at least one number!'); window.history.back();</script>";
        exit();
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        echo "<script>alert('Password must contain at least one special character!'); window.history.back();</script>";
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM user WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        echo "<script>alert('Email already exists!'); window.history.back();</script>";
        exit();
    }

    $checkContact = $conn->prepare("SELECT id FROM user WHERE contact = ?");
    $checkContact->bind_param("s", $contact);
    $checkContact->execute();
    $contactResult = $checkContact->get_result();

    if ($contactResult->num_rows > 0) {
        echo "<script>alert('Contact number already exists!'); window.history.back();</script>";
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO user (firstname, lastname, email, password, position, contact, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $firstname, $lastname, $email, $hashed_password, $position, $contact, $address);

    if ($stmt->execute()) {
        header("Location: ../Frontend/ADMIN/staff-list.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

// ==================== UPDATE USER ====================
if (isset($_POST['update_user'])) {
    $id        = $_POST['user_id'];
    $firstname = trim($_POST['firstname']);
    $lastname  = trim($_POST['lastname']);
    $email     = trim($_POST['email']);
    $contact   = trim($_POST['contact']);
    $address   = trim($_POST['address']);
    $position  = trim($_POST['position']);

    if (!preg_match('/^[0-9]{11}$/', $contact)) {
        echo "<script>alert('Contact number must be exactly 11 digits!'); window.history.back();</script>";
        exit();
    }

    if (!preg_match('/^[a-zA-Z0-9._%+\-]+@(gmail|yahoo)\.(com|com\.ph)$/', $email)) {
        echo "<script>alert('Only Gmail or Yahoo email addresses are allowed!'); window.history.back();</script>";
        exit();
    }

    $stmt = $conn->prepare("UPDATE user SET firstname=?, lastname=?, email=?, contact=?, address=?, position=? WHERE id=?");
    $stmt->bind_param("ssssssi", $firstname, $lastname, $email, $contact, $address, $position, $id);

    if ($stmt->execute()) {
        header("Location: ../Frontend/ADMIN/staff-list.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

// ==================== DELETE USER ====================
if (isset($_POST['delete_user'])) {
    $id = $_POST['user_id'];

    $stmt = $conn->prepare("DELETE FROM user WHERE id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: ../Frontend/ADMIN/staff-list.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>