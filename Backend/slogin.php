<?php
session_start();
include('conn.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']    ?? '');
    $pass  = trim($_POST['password'] ?? '');

    // Basic input validation
    if (empty($email) || empty($pass)) {
        header("Location: ../Frontend/login-staff.html?error=invalid");
        exit();
    }

    if (strlen($pass) < 8 || strlen($pass) > 72 || !preg_match('/^[\x20-\x7E]+$/', $pass)) {
        header("Location: ../Frontend/login-staff.html?error=invalid");
        exit();
    }

    $sql  = "SELECT password, position FROM user WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($db_pass, $db_position);
    $stmt->fetch();
    $stmt->close();

    // Plain-text comparison (matches existing auth pattern)
    if ($db_pass && $pass === $db_pass) {

        // Only allow staff position
        if ($db_position !== 'staff') {
            header("Location: ../Frontend/login-staff.html?error=unauthorized");
            exit();
        }

        session_regenerate_id(true);
        $_SESSION['user']     = $email;
        $_SESSION['position'] = 'staff';

        // ✅ Redirect to POS
        header("Location: ../Frontend/POS/POS.html");
        exit();
    }

    // Wrong email or password
    header("Location: ../Frontend/login-staff.html?error=invalid");
    exit();
}
?>