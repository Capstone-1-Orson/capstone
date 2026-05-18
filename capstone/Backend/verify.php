<?php
// ─────────────────────────────────────────────────────────────────
//  verify.php  — validates the 6-digit OTP and finalises the session
// ─────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['otp'])) {
    header("Location: ../verify.html?error=invalid");
    exit();
}

$role = $_POST['role'] ?? '';

// Start the correct session (must match what login.php opened)
if ($role === 'admin') {
    session_name('ADMIN_SESSION');
} elseif ($role === 'staff') {
    session_name('STAFF_SESSION');
} else {
    header("Location: ../login-v2.html?error=invalid");
    exit();
}

session_start();

// ── Guard: session must have a pending OTP ────────────────────────
if (
    empty($_SESSION['otp']) ||
    empty($_SESSION['otp_expires']) ||
    empty($_SESSION['otp_role']) ||
    empty($_SESSION['otp_identity'])
) {
    header("Location: ../login-v2.html?error=invalid");
    exit();
}

// ── Guard: OTP must not be expired ───────────────────────────────
if (time() > $_SESSION['otp_expires']) {
    // Clean up stale OTP data
    unset($_SESSION['otp'], $_SESSION['otp_expires'],
          $_SESSION['otp_role'], $_SESSION['otp_identity'], $_SESSION['otp_email']);
    header("Location: ../verify.html?role={$role}&error=expired");
    exit();
}

// ── Guard: submitted OTP must match stored hash ───────────────────
$submitted = trim($_POST['otp']);
if (!password_verify($submitted, $_SESSION['otp'])) {
    header("Location: ../verify.html?role={$role}&error=invalid");
    exit();
}

// ── OTP is valid — finalise login ─────────────────────────────────
$identity = $_SESSION['otp_identity'];

// Clean up OTP data (one-time use)
unset($_SESSION['otp'], $_SESSION['otp_expires'],
      $_SESSION['otp_role'], $_SESSION['otp_identity'], $_SESSION['otp_email']);

session_regenerate_id(true);

if ($role === 'admin') {
    $_SESSION['user']     = 'admin';
    $_SESSION['position'] = 'admin';
    header("Location: ../Frontend/ADMIN/index2.php");
    exit();
}

if ($role === 'staff') {
    $_SESSION['user']     = $identity;
    $_SESSION['position'] = 'staff';
    header("Location: ../Frontend/POS.php");
    exit();
}

// Fallback
header("Location: ../login-v2.html?error=invalid");
exit();
?>
