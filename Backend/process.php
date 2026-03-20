<?php
session_start();
include('conn.php');

if (isset($_POST['save_user'])) {

    // 1️⃣ Collect form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $position = trim($_POST['position']);

    // 2️⃣ Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 3️⃣ Check if email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // Email exists
        echo "<script>alert('Email already exists!'); window.history.back();</script>";
        exit();
    }

    // 4️⃣ Insert new user
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, position) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $hashed_password, $position);

    if ($stmt->execute()) {
        // Success → redirect back to staff list
        header("Location: ../Frontend/staff-list.php?success=1");
        exit();
    } else {
        // Error
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>