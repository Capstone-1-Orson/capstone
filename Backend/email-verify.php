<?php
/**
 * email-verify.php  —  Email address verification via token link
 * Place this file at:  /capstone/Backend/email-verify.php
 *
 * NOTE: This is SEPARATE from verify.php which handles login OTP.
 *       This file only handles the one-time email verification link
 *       sent to newly added staff members.
 *
 * Required DB columns on the `user` table (run once):
 * ────────────────────────────────────────────────────
 *   ALTER TABLE user
 *     ADD COLUMN email_verified  TINYINT(1)  NOT NULL DEFAULT 0,
 *     ADD COLUMN verify_token    VARCHAR(64) DEFAULT NULL,
 *     ADD COLUMN token_expiry    DATETIME    DEFAULT NULL;
 */

include('conn.php');

// ── Grab & sanitise the token ─────────────────────────────────────
$token = trim($_GET['token'] ?? '');

if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
    showResult('error', 'Invalid Verification Link',
        'The link you followed is malformed or incomplete. Please contact your administrator.');
}

// ── Look up the token ─────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT id, firstname, email_verified, token_expiry
     FROM user
     WHERE verify_token = ?
     LIMIT 1"
);
$stmt->bind_param("s", $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    showResult('error', 'Link Not Found',
        'This verification link is invalid or has already been used. '
        . 'If you believe this is an error, please contact your administrator.');
}

// ── Already verified? ─────────────────────────────────────────────
if ($row['email_verified']) {
    showResult('info', 'Already Verified',
        'Your email address is already verified. You can log in normally.');
}

// ── Check expiry ──────────────────────────────────────────────────
if (!empty($row['token_expiry']) && strtotime($row['token_expiry']) < time()) {
    showResult('expired', 'Link Expired',
        'This verification link has expired — links are valid for <strong>24 hours</strong>. '
        . 'Please ask an administrator to resend the verification email.');
}

// ── All good — mark as verified & clear token ─────────────────────
$upd = $conn->prepare(
    "UPDATE user
     SET email_verified = 1,
         verify_token   = NULL,
         token_expiry   = NULL
     WHERE id = ?"
);
$upd->bind_param("i", $row['id']);
$ok = $upd->execute();
$upd->close();
$conn->close();

if ($ok) {
    showResult('success', 'Email Verified!',
        'Hi <strong>' . htmlspecialchars($row['firstname']) . '</strong>, '
        . 'your email has been verified successfully. You may now log in to your account.');
} else {
    showResult('error', 'Database Error',
        'Something went wrong while verifying your email. Please try again or contact your administrator.');
}

// ─────────────────────────────────────────────────────────────────
//  Helper — render a branded result page and halt
// ─────────────────────────────────────────────────────────────────
function showResult(string $type, string $heading, string $body): never
{
    $map = [
        'success' => ['#28a745', 'fa-check-circle'],
        'info'    => ['#17a2b8', 'fa-info-circle'],
        'expired' => ['#fd7e14', 'fa-clock'],
        'error'   => ['#dc3545', 'fa-times-circle'],
    ];
    [$color, $icon] = $map[$type] ?? $map['error'];
    $loginUrl = '../login-v2.html';
    $showBtn  = in_array($type, ['success', 'info']);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Email Verification – Empress' Cafe</title>
  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:400,600,700&display=fallback">
  <link rel="stylesheet"
    href="../Frontend/plugins/fontawesome-free/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Source Sans Pro', Arial, sans-serif;
      background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.4);
      max-width: 460px;
      width: 100%;
      overflow: hidden;
      text-align: center;
    }

    /* Pink brand header — matches Empress' Cafe theme */
    .card-banner {
      background: #e91e8c;
      padding: 26px 24px 18px;
    }
    .card-banner h1 {
      color: #fff;
      font-size: 1.3rem;
      font-weight: 700;
      letter-spacing: 0.4px;
    }

    .card-body { padding: 36px 32px 28px; }

    .icon-circle {
      width: 78px; height: 78px;
      border-radius: 50%;
      background: <?= $color ?>;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 20px;
      box-shadow: 0 4px 20px <?= $color ?>44;
    }
    .icon-circle i { font-size: 2.1rem; color: #fff; }

    h2 { font-size: 1.45rem; color: #333; margin-bottom: 12px; font-weight: 700; }
    p  { color: #666; font-size: 0.96rem; line-height: 1.65; }

    .btn {
      display: inline-block;
      margin-top: 26px;
      padding: 12px 36px;
      background: #e91e8c;
      color: #fff;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 700;
      font-size: 0.95rem;
      transition: background 0.2s ease;
    }
    .btn:hover { background: #c2185b; }

    .card-footer {
      background: #f8f8f8;
      padding: 11px;
      font-size: 0.77rem;
      color: #bbb;
      border-top: 1px solid #eee;
    }
  </style>
</head>
<body>
  <div class="card">

    <div class="card-banner">
      <h1><i class="fas fa-coffee mr-2"></i>Empress&#39; Cafe &mdash; Staff Portal</h1>
    </div>

    <div class="card-body">
      <div class="icon-circle">
        <i class="fas <?= $icon ?>"></i>
      </div>
      <h2><?= $heading ?></h2>
      <p><?= $body ?></p>
    </div>

    <div class="card-footer">
      &copy; <?= date('Y') ?> Empress&#39; Cafe. All rights reserved.
    </div>

  </div>
</body>
</html>
    <?php
    exit();
}
?>