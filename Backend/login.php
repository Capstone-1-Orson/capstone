<?php
session_start();
include('conn.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = "Admin";
    $pass  = trim($_POST['pass'] ?? '');

    // Input validation
    if (empty($pass) || strlen($pass) < 8 || strlen($pass) > 72 || !preg_match('/^[\x20-\x7E]+$/', $pass)) {
        die("Invalid password.");
    }

    $sql = "SELECT password FROM user WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($db_pass);
    $stmt->fetch();
    $stmt->close();

    // ✅ Direct plain text comparison
    if ($db_pass && $pass === $db_pass) {
        session_regenerate_id(true);
        $_SESSION['user'] = $email;
        header("Location: ../Frontend/ADMIN/index2.php");
        exit();
    }

    echo "Invalid password.";
}
