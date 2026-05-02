<?php
include('conn.php');

// ── LOCKSCREEN re-auth (field name: "pass") — ADMIN SESSION ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {

    $pass = trim($_POST['pass'] ?? '');

    if (empty($pass)) {
        header("Location: ../lockscreen.html?error=invalid");
        exit();
    }

    // Use a dedicated admin session so it never collides with staff session
    session_name('ADMIN_SESSION');
    session_start();

    if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'admin');

    $email = ADMIN_EMAIL;
    $sql   = "SELECT password, position FROM user WHERE email = ?";
    $stmt  = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($db_pass, $db_position);
    $stmt->fetch();
    $stmt->close();

    if ($db_pass && password_verify($pass, $db_pass) && $db_position === 'admin') {
        session_regenerate_id(true);
        $_SESSION['user']     = 'admin';
        $_SESSION['position'] = 'admin';
        header("Location: ../Frontend/ADMIN/index2.php");
        exit();
    }

    header("Location: ../lockscreen.html?error=invalid");
    exit();
}

// ── MAIN LOGIN (fields: "email" + "password") — STAFF SESSION ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {

    $email = trim($_POST['email']    ?? '');
    $pass  = trim($_POST['password'] ?? '');

    if (empty($email) || empty($pass)) {
        header("Location: ../login-v2.html?error=invalid");
        exit();
    }

    // Use a dedicated staff session so it never collides with admin session
    session_name('STAFF_SESSION');
    session_start();

    $sql  = "SELECT password, position FROM user WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($db_pass, $db_position);
    $stmt->fetch();
    $stmt->close();

    if ($db_pass && password_verify($pass, $db_pass)) {

        // Staff form only — block admin accounts entirely
        if ($db_position !== 'staff') {
            header("Location: ../login-v2.html?error=unauthorized");
            exit();
        }

        session_regenerate_id(true);
        $_SESSION['user']     = $email;
        $_SESSION['position'] = 'staff';
        header("Location: ../Frontend/POS.php");
        exit();
    }

    header("Location: ../login-v2.html?error=invalid");
    exit();
}
?>