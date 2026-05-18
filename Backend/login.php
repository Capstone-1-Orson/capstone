<?php
include('conn.php');

// ─────────────────────────────────────────────────────────────────
//  Gmail SMTP helper — sends the 6-digit OTP via PHPMailer
//  Requires: composer require phpmailer/phpmailer
// ─────────────────────────────────────────────────────────────────
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';
function sendOtpEmail(string $toEmail, string $toName, string $otp): bool {
    $mail = new PHPMailer(true);
    try {
        // ── Gmail SMTP config ──────────────────────────────────────
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'dummyacctest099@gmail.com';   // ← your Gmail
        $mail->Password   = 'dzsm xafq hxqg arto';       // ← Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // ── Message ────────────────────────────────────────────────
        $mail->setFrom('dummyacctest099@gmail.com', 'OPERLYTICS Security');
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Your OPERLYTICS Verification Code';
        $mail->Body    = "
            <div style='font-family:Inter,sans-serif;max-width:480px;margin:auto;padding:32px;
                        background:#fff;border-radius:16px;border:1px solid #f5c6d8'>
              <div style='text-align:center;margin-bottom:24px'>
                <div style='display:inline-block;width:48px;height:48px;border-radius:12px;
                            background:linear-gradient(135deg,#D44A7A,#C11C84);
                            line-height:48px;font-size:22px;color:#fff'>♛</div>
                <h2 style='color:#2d1a25;margin:12px 0 4px;font-size:1.2rem'>OPERLYTICS</h2>
                <p style='color:#b87090;font-size:.75rem;letter-spacing:.15em;text-transform:uppercase'>
                  Point of Sale</p>
              </div>
              <p style='color:#2d1a25;font-size:.95rem;margin-bottom:8px'>Hello {$toName},</p>
              <p style='color:#6b3050;font-size:.88rem;margin-bottom:24px'>
                Use the code below to complete your login. It expires in <strong>5 minutes</strong>.</p>
              <div style='text-align:center;background:#fdf0f5;border-radius:12px;
                          padding:24px;margin-bottom:24px;border:1px solid rgba(212,74,122,.15)'>
                <span style='font-size:2.4rem;font-weight:700;letter-spacing:.35em;color:#C11C84'>
                  {$otp}</span>
              </div>
              <p style='color:#b87090;font-size:.78rem;text-align:center'>
                If you didn't request this code, ignore this email.<br>
                Never share this code with anyone.</p>
            </div>";
        $mail->AltBody = "Your OPERLYTICS verification code: {$otp}  (expires in 5 minutes)";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("OTP email failed: " . $mail->ErrorInfo);
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────
//  Helper: start the right session cleanly
// ─────────────────────────────────────────────────────────────────
function startSession(string $name): void {
    session_name($name);
    session_start();
}


// ══════════════════════════════════════════════════════════════════
//  LOCKSCREEN re-auth  (field: "pass")  →  ADMIN SESSION
// ══════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {

    $pass = trim($_POST['pass'] ?? '');
    if (empty($pass)) {
        header("Location: ../lockscreen.html?error=invalid");
        exit();
    }

    startSession('ADMIN_SESSION');

    if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'admin');
    $email = ADMIN_EMAIL;

    $sql  = "SELECT password, position, notify_email FROM user WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($db_pass, $db_position, $notify_email);
    $stmt->fetch();
    $stmt->close();

    if ($db_pass && password_verify($pass, $db_pass) && $db_position === 'admin') {

        // ── Generate & store OTP ──────────────────────────────────
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['otp']          = password_hash($otp, PASSWORD_DEFAULT);
        $_SESSION['otp_expires']  = time() + 300;   // 5 minutes
        $_SESSION['otp_role']     = 'admin';
        $_SESSION['otp_identity'] = $email;
        $_SESSION['otp_email']    = $notify_email;  // where to send

        $sent = sendOtpEmail($notify_email, 'Admin', $otp);
        if (!$sent) {
            header("Location: ../lockscreen.html?error=mail");
            exit();
        }

        header("Location: ../verify.html?role=admin");
        exit();
    }

    header("Location: ../lockscreen.html?error=invalid");
    exit();
}


// ══════════════════════════════════════════════════════════════════
//  MAIN LOGIN  (fields: "email" + "password")  →  STAFF SESSION
// ══════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {

    $email = trim($_POST['email']    ?? '');
    $pass  = trim($_POST['password'] ?? '');

    if (empty($email) || empty($pass)) {
        header("Location: ../login-v2.html?error=invalid");
        exit();
    }

    startSession('STAFF_SESSION');

    $sql  = "SELECT password, position FROM user WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($db_pass, $db_position);
    $stmt->fetch();
    $stmt->close();

    if ($db_pass && password_verify($pass, $db_pass)) {

        if ($db_position !== 'staff') {
            header("Location: ../login-v2.html?error=unauthorized");
            exit();
        }

        // ── Generate & store OTP ──────────────────────────────────
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['otp']          = password_hash($otp, PASSWORD_DEFAULT);
        $_SESSION['otp_expires']  = time() + 300;
        $_SESSION['otp_role']     = 'staff';
        $_SESSION['otp_identity'] = $email;
        $_SESSION['otp_email']    = $email;         // staff logs in with their email

        $sent = sendOtpEmail($email, $email, $otp);
        if (!$sent) {
            header("Location: ../login-v2.html?error=mail");
            exit();
        }

        header("Location: ../verify.html?role=staff");
        exit();
    }

    header("Location: ../login-v2.html?error=invalid");
    exit();
}
?>