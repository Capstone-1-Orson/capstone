<?php
// Backend/smtp-test.php
//
// DIAGNOSTIC ONLY — delete this file once you're done testing.
// It tries every realistic Gmail SMTP configuration and reports exactly
// what happened for each one, so you know definitively whether this
// hosting account's outbound SMTP is blocked (and if so, on which
// port), instead of guessing from a generic "SMTP connect() failed".
//
// Usage: upload this file to Backend/ on your live host, then visit
//   https://yourdomain.tld/Backend/smtp-test.php
// in a browser. Read the results, then delete the file — it contains
// your SMTP password in the source and shouldn't stay on a live server.

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

// ── Same credentials as MailerService.php ──────────────────────────
const SMTP_USER     = 'dummyacctest099@gmail.com';
const SMTP_PASSWORD = 'dzsmxafqhxqgarto';

// Send the test email to yourself so you can also confirm delivery,
// not just that the SMTP handshake succeeded.
const TEST_RECIPIENT = SMTP_USER;

// Every combination worth trying for Gmail specifically.
$configs = [
    ['label' => 'smtp.gmail.com : 587 (STARTTLS)', 'host' => 'smtp.gmail.com', 'port' => 587, 'secure' => PHPMailer::ENCRYPTION_STARTTLS],
    ['label' => 'smtp.gmail.com : 465 (implicit SSL)', 'host' => 'smtp.gmail.com', 'port' => 465, 'secure' => PHPMailer::ENCRYPTION_SMTPS],
];

header('Content-Type: text/plain; charset=utf-8');
echo "SMTP diagnostic — " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 60) . "\n\n";

$anySucceeded = false;

foreach ($configs as $cfg) {
    echo "Trying {$cfg['label']} ...\n";
    $start = microtime(true);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host        = $cfg['host'];
        $mail->Port        = $cfg['port'];
        $mail->SMTPSecure  = $cfg['secure'];
        $mail->SMTPAuth    = true;
        $mail->Username    = SMTP_USER;
        $mail->Password    = SMTP_PASSWORD;
        $mail->Timeout     = 12;          // fail fast instead of hanging
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->SMTPDebug   = 2;           // verbose protocol log
        $mail->Debugoutput = function ($str) {
            echo "    | " . trim($str) . "\n";
        };

        $mail->setFrom(SMTP_USER, 'SMTP Diagnostic');
        $mail->addAddress(TEST_RECIPIENT);
        $mail->Subject = 'SMTP diagnostic test — ' . $cfg['label'];
        $mail->Body    = 'If you received this, ' . $cfg['label'] . ' works on this host.';

        $mail->send();
        $elapsed = round(microtime(true) - $start, 2);
        echo "  RESULT: SUCCESS ({$elapsed}s) — check {$cfg['host']}'s inbox to confirm delivery.\n\n";
        $anySucceeded = true;

    } catch (MailerException $e) {
        $elapsed = round(microtime(true) - $start, 2);
        $err = $mail->ErrorInfo ?: $e->getMessage();
        echo "  RESULT: FAILED ({$elapsed}s)\n";
        echo "  ERROR: {$err}\n\n";
    }
}

echo str_repeat('=', 60) . "\n";
if ($anySucceeded) {
    echo "At least one config worked — use that host/port/encryption in MailerService.php.\n";
} else {
    echo "Every config failed. If every failure above happened in well under\n";
    echo "the 12s timeout (near-instant \"Connection refused\" or similar), that's\n";
    echo "a network-level block on this hosting account, not a credentials or\n";
    echo "code problem — SMTP genuinely can't reach out from here.\n";
    echo "If instead they timed out near the full 12s, it may be a slower/less\n";
    echo "consistent block worth retrying, or a temporary network issue.\n";
}

echo "\nDelete this file (Backend/smtp-test.php) now that you're done.\n";