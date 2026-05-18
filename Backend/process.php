<?php
session_name('ADMIN_SESSION');
session_start();
include('conn.php');

// ══════════════════════════════════════════════════════════════════
//  Shared helper — sends a verification email and returns true/false
//  Also populates $mailerError on failure (passed by reference)
// ══════════════════════════════════════════════════════════════════
function sendVerificationEmail(
    string $email,
    string $firstname,
    string $verify_token,
    string &$mailerError
): bool {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';

    $verify_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST']
        . '/CAPSSTONE/capstone/Backend/email-verify.php?token=' . $verify_token;

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'dummyacctest099@gmail.com';  // ← replace with your Gmail
        $mail->Password   = 'dzsm xafq hxqg arto';     // ← replace with your App Password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 10; // seconds — fail fast if SMTP unreachable

        $mail->setFrom('dummyacctest099@gmail.com', "Empress' Cafe");
        $mail->addAddress($email, $firstname);
        $mail->isHTML(true);
        $mail->Subject = "Verify Your Email \xe2\x80\x93 Empress' Cafe Staff Account";
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;border:1px solid #eee;border-radius:8px;overflow:hidden;'>
              <div style='background:#e91e8c;padding:24px;text-align:center;'>
                <h2 style='color:#fff;margin:0;'>Empress&#39; Cafe</h2>
              </div>
              <div style='padding:32px;'>
                <p style='font-size:16px;'>Hi <strong>$firstname</strong>,</p>
                <p>Your staff account has been created. Please verify your email address to activate it.</p>
                <p style='text-align:center;margin:32px 0;'>
                  <a href='$verify_link'
                     style='background:#e91e8c;color:#fff;padding:14px 32px;border-radius:6px;
                            text-decoration:none;font-weight:bold;font-size:15px;'>
                    Verify My Email
                  </a>
                </p>
                <p style='color:#888;font-size:13px;'>This link expires in <strong>24 hours</strong>. If you did not expect this email, please ignore it.</p>
              </div>
              <div style='background:#f8f8f8;padding:12px;text-align:center;color:#aaa;font-size:12px;'>
                &copy; " . date('Y') . " Empress&#39; Cafe. All rights reserved.
              </div>
            </div>";
        $mail->AltBody = "Hi $firstname, verify your account: $verify_link (expires in 24 hours)";
        $mail->send();
        return true;
    } catch (\Exception $e) {
        $mailerError = $mail->ErrorInfo ?: $e->getMessage();
        return false;
    }
}


// ── Role guard: admin only ────────────────────────────────────
if (!isset($_SESSION['user']) || ($_SESSION['position'] ?? '') !== 'admin') {
    http_response_code(403);
    header("Location: ../Frontend/login-v2.html");
    exit();
}

// ── CSRF verification ─────────────────────────────────────────
$submitted_token = trim($_POST['csrf_token'] ?? '');
$session_token   = $_SESSION['csrf_token'] ?? '';
if (empty($submitted_token) || !hash_equals($session_token, $submitted_token)) {
    $_SESSION['error'] = 'Invalid request. Please try again.';
    header("Location: ../Frontend/ADMIN/staff-list.php");
    exit();
}
// Regenerate token after use
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$redirect_back = "../Frontend/ADMIN/staff-list.php";

// ==================== ADD USER ====================
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

    // Check duplicate email
    $check = $conn->prepare("SELECT id FROM user WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['error'] = 'Email already exists!';
        header("Location: $redirect_back"); exit();
    }
    $check->close();

    // Check duplicate contact
    $checkContact = $conn->prepare("SELECT id FROM user WHERE contact = ?");
    $checkContact->bind_param("s", $contact);
    $checkContact->execute();
    if ($checkContact->get_result()->num_rows > 0) {
        $_SESSION['error'] = 'Contact number already exists!';
        header("Location: $redirect_back"); exit();
    }
    $checkContact->close();

    // Handle profile image upload
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../Frontend/ADMIN/uploads/staff/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $ext         = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed     = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileMime    = mime_content_type($_FILES['image']['tmp_name']);
        if (!in_array($ext, $allowed) || !in_array($fileMime, $allowedMime)) {
            $_SESSION['error'] = 'Only JPG, PNG, GIF, or WEBP images are allowed!';
            header("Location: $redirect_back"); exit();
        }
        $filename = 'staff_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
            $image_path = 'Frontend/ADMIN/uploads/staff/' . $filename;
        }
    }

    // Generate email verification token
    $verify_token   = bin2hex(random_bytes(32));
    $token_expiry   = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $stmt = $conn->prepare("INSERT INTO user (firstname, lastname, email, password, position, contact, address, image, email_verified, verify_token, token_expiry) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
    $stmt->bind_param("ssssssssss", $firstname, $lastname, $email, $hashed_password, $position, $contact, $address, $image_path, $verify_token, $token_expiry);

    if ($stmt->execute()) {
        $mailerError = '';
        if (sendVerificationEmail($email, $firstname, $verify_token, $mailerError)) {
            $_SESSION['success'] = "Staff member \"$firstname $lastname\" added! "
                . "A verification email has been sent to <strong>$email</strong>.";
        } else {
            // Account saved — but warn admin the email failed
            $_SESSION['error'] = "Staff member \"$firstname $lastname\" was added, "
                . "but the verification email could <strong>not</strong> be sent. "
                . "SMTP error: <em>$mailerError</em>. "
                . "Please check your Gmail credentials in process.php, then use "
                . "<strong>Resend Verification</strong> from the staff list.";
        }
    } else {
        $_SESSION['error'] = "Database error: " . $stmt->error;
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

    // Detect if email is being changed (triggers re-verification)
    $emailCheck = $conn->prepare("SELECT email FROM user WHERE id = ?");
    $emailCheck->bind_param("i", $id);
    $emailCheck->execute();
    $emailRow     = $emailCheck->get_result()->fetch_assoc();
    $emailCheck->close();
    $email_changed = ($emailRow && strtolower(trim($emailRow['email'])) !== strtolower($email));

    // Check email uniqueness (excluding current user)
    $checkEmail = $conn->prepare("SELECT id FROM user WHERE email = ? AND id != ?");
    $checkEmail->bind_param("si", $email, $id);
    $checkEmail->execute();
    if ($checkEmail->get_result()->num_rows > 0) {
        $_SESSION['error'] = 'Email already in use by another account!';
        header("Location: $redirect_back"); exit();
    }
    $checkEmail->close();

    // Handle profile image upload
    $existing_image = trim($_POST['existing_image'] ?? '');
    $image_path     = $existing_image; // default: keep current image

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../Frontend/ADMIN/uploads/staff/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $ext         = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed     = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileMime    = mime_content_type($_FILES['image']['tmp_name']);
        if (!in_array($ext, $allowed) || !in_array($fileMime, $allowedMime)) {
            $_SESSION['error'] = 'Only JPG, PNG, GIF, or WEBP images are allowed!';
            header("Location: $redirect_back"); exit();
        }
        $filename = 'staff_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
            // Delete old image file if it exists
            if ($existing_image && file_exists(__DIR__ . '/../' . $existing_image)) {
                unlink(__DIR__ . '/../' . $existing_image);
            }
            $image_path = 'Frontend/ADMIN/uploads/staff/' . $filename;
        }
    }

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

        $stmt = $conn->prepare("UPDATE user SET firstname=?, lastname=?, email=?, contact=?, address=?, position=?, password=?, image=? WHERE id=?");
        $stmt->bind_param("ssssssssi", $firstname, $lastname, $email, $contact, $address, $position, $hashed_password, $image_path, $id);
    } else {
        $stmt = $conn->prepare("UPDATE user SET firstname=?, lastname=?, email=?, contact=?, address=?, position=?, image=? WHERE id=?");
        $stmt->bind_param("sssssssi", $firstname, $lastname, $email, $contact, $address, $position, $image_path, $id);
    }

    if ($stmt->execute()) {
        // ── If email changed, reset verification and resend ────
        if ($email_changed) {
            $newToken  = bin2hex(random_bytes(32));
            $newExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $resetStmt = $conn->prepare(
                "UPDATE user SET email_verified=0, verify_token=?, token_expiry=? WHERE id=?"
            );
            $resetStmt->bind_param("ssi", $newToken, $newExpiry, $id);
            $resetStmt->execute();
            $resetStmt->close();

            $mailerError = '';
            if (sendVerificationEmail($email, $firstname, $newToken, $mailerError)) {
                $_SESSION['success'] = "Staff member \"$firstname $lastname\" updated. "
                    . "Email changed — a new verification link has been sent to <strong>$email</strong>.";
            } else {
                $_SESSION['success'] = "Staff member \"$firstname $lastname\" updated, "
                    . "but the new verification email could <strong>not</strong> be sent. "
                    . "SMTP error: <em>$mailerError</em>. Use <strong>Resend Verification</strong> from the staff list.";
            }
        } else {
            $_SESSION['success'] = "Staff member \"$firstname $lastname\" updated successfully!";
        }
    } else {
        $_SESSION['error'] = "Database error: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
    header("Location: $redirect_back"); exit();
}

// ==================== RESEND VERIFICATION EMAIL ====================
if (isset($_POST['resend_verification'])) {
    $id = intval($_POST['user_id']);

    $sel = $conn->prepare("SELECT firstname, lastname, email, email_verified FROM user WHERE id = ?");
    $sel->bind_param("i", $id);
    $sel->execute();
    $usr = $sel->get_result()->fetch_assoc();
    $sel->close();

    if (!$usr) {
        $_SESSION['error'] = 'Staff member not found.';
        header("Location: $redirect_back"); exit();
    }
    if ($usr['email_verified']) {
        $_SESSION['error'] = "\"" . htmlspecialchars($usr['firstname'] . ' ' . $usr['lastname']) . "\" is already verified.";
        header("Location: $redirect_back"); exit();
    }

    $newToken  = bin2hex(random_bytes(32));
    $newExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $upd = $conn->prepare("UPDATE user SET verify_token=?, token_expiry=? WHERE id=?");
    $upd->bind_param("ssi", $newToken, $newExpiry, $id);
    $upd->execute();
    $upd->close();

    $mailerError = '';
    $fname = $usr['firstname'];
    $femail = $usr['email'];
    $fullName = htmlspecialchars($fname . ' ' . $usr['lastname']);

    if (sendVerificationEmail($femail, $fname, $newToken, $mailerError)) {
        $_SESSION['success'] = "Verification email resent to $femail for $fullName.";
    } else {
        $_SESSION['error'] = "Could not send verification email to $fullName. "
            . "SMTP error: <em>$mailerError</em>. "
            . "Please check your Gmail credentials in process.php.";
    }

    $conn->close();
    header("Location: $redirect_back"); exit();
}

// ==================== DELETE USER ====================
if (isset($_POST['delete_user'])) {
    $id = intval($_POST['user_id']);

    $nameStmt = $conn->prepare("SELECT firstname, lastname, image FROM user WHERE id = ?");
    $nameStmt->bind_param("i", $id);
    $nameStmt->execute();
    $nameRow  = $nameStmt->get_result()->fetch_assoc();
    $nameStmt->close();
    $fullName = $nameRow ? htmlspecialchars($nameRow['firstname'] . ' ' . $nameRow['lastname']) : 'User';

    $stmt = $conn->prepare("DELETE FROM user WHERE id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // Remove profile photo file if it exists
        if (!empty($nameRow['image']) && file_exists(__DIR__ . '/../' . $nameRow['image'])) {
            unlink(__DIR__ . '/../' . $nameRow['image']);
        }
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