<?php
session_start();
include('conn.php');

// ── LOCKSCREEN re-auth (field name: "pass") ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {

    $pass = trim($_POST['pass'] ?? '');

    if (empty($pass)) {
        header("Location: ../Frontend/lockscreen.html?error=invalid");
        exit();
    }

    $email = "Admin";
    $sql   = "SELECT password, position FROM user WHERE email = ?";
    $stmt  = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($db_pass, $db_position);
    $stmt->fetch();
    $stmt->close();

    // Admin password is plain text
    if ($db_pass && $pass === $db_pass && $db_position === 'admin') {
        session_regenerate_id(true);
        $_SESSION['user']     = $email;
        $_SESSION['position'] = 'admin';
        header("Location: ../Frontend/ADMIN/index2.php");
        exit();
    }

    header("Location: ../Frontend/lockscreen.html?error=invalid");
    exit();
}

// ── MAIN LOGIN (fields: "email" + "password") ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {

    $email = trim($_POST['email']    ?? '');
    $pass  = trim($_POST['password'] ?? '');

    if (empty($email) || empty($pass)) {
        header("Location: ../Frontend/login-v2.html?error=invalid");
        exit();
    }

    $sql  = "SELECT password, position FROM user WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($db_pass, $db_position);
    $stmt->fetch();
    $stmt->close();

    if ($db_pass) {
        // Detect bcrypt hash or plain text and verify accordingly
        $match = (str_starts_with($db_pass, '$2y$') || str_starts_with($db_pass, '$2b$'))
            ? password_verify($pass, $db_pass)
            : ($pass === $db_pass);

        if ($match) {
            session_regenerate_id(true);
            $_SESSION['user']     = $email;
            $_SESSION['position'] = $db_position;

            if ($db_position === 'admin') {
                header("Location: ../Frontend/lockscreen.html");
                exit();
            }

            if ($db_position === 'staff') {
                header("Location: ../Frontend/POS.php");
                exit();
            }

            header("Location: ../Frontend/login-v2.html?error=unauthorized");
            exit();
        }
    }

    header("Location: ../Frontend/login-v2.html?error=invalid");
    exit();
}
?>