<?php
session_start();
include('conn.php');

if (isset($_POST['save_user'])) {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $position = trim($_POST['position']);

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        echo "<script>alert('Email already exists!'); window.history.back();</script>";
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, position) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $hashed_password, $position);

    if ($stmt->execute()) {
        header("Location: ../Frontend/ADMIN/staff-list.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
