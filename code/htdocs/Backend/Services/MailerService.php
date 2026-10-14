<?php
// Backend/Services/MailerService.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

/**
 * MailerService – all outbound email in one place.
 *
 * NOTE: InfinityFree's free tier blocks all outbound SMTP connections
 * at the network level (port 25/465/587 all fail with "SMTP connect()
 * failed", regardless of host/provider). This SMTP-based version will
 * NOT send mail there unless you're on a paid InfinityFree plan, or
 * you host this elsewhere (XAMPP locally, Railway, etc. all allow it).
 *
 * Centralises SMTP credentials and one mail template:
 *   - sendEmailVerification()   new-account activation link
 */
class MailerService
{
    // ── SMTP credentials ─────────────────────────────────────────
    private const SMTP_USER     = 'dummyacctest099@gmail.com';
    private const SMTP_PASSWORD = 'dzsmxafqhxqgarto';
    private const FROM_NAME     = 'OPERLYTICS';

    // Every Gmail SMTP submission config worth trying, in order.
    // Some hosts only block certain outbound ports, so if 587 fails,
    // fall back to try 465 before giving up — costs nothing when 587
    // works, and gives a real second chance when it doesn't.
    private const SMTP_CONFIGS = [
        ['host' => 'smtp.gmail.com', 'port' => 587, 'secure' => PHPMailer::ENCRYPTION_STARTTLS],
        ['host' => 'smtp.gmail.com', 'port' => 465, 'secure' => PHPMailer::ENCRYPTION_SMTPS],
    ];

    // ── DB columns required by User::getVerifyToken() ────────────
    // If you dropped verify_token / token_expiry, run this once:
    //   ALTER TABLE user
    //     ADD COLUMN verify_token VARCHAR(64) NULL,
    //     ADD COLUMN token_expiry DATETIME    NULL;

    /** Last SMTP error message — readable after a failed send. */
    public string $lastError = '';

    // ─────────────────────────────────────────────────────────────
    //  Public API
    // ─────────────────────────────────────────────────────────────

    /**
     * Send an email-verification link for a newly created staff account.
     *
     * @param string $toEmail      Recipient email
     * @param string $firstName    Recipient first name
     * @param string $verifyToken  Raw verification token (will be URL-encoded)
     */
    public function sendEmailVerification(
        string $toEmail,
        string $firstName,
        string $verifyToken
    ): bool {
        $verifyLink = $this->buildVerifyLink($verifyToken);
        $subject    = "Verify Your Email – Empress' Cafe Staff Account";
        $html       = $this->buildVerifyHtml($firstName, $verifyLink);
        $plain      = "Hi $firstName, verify your account: $verifyLink (expires in 24 hours)";

        return $this->send($toEmail, $firstName, $subject, $html, $plain);
    }

    // ─────────────────────────────────────────────────────────────
    //  Private helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Core send method — tries each config in SMTP_CONFIGS in order,
     * stopping at the first one that succeeds. Only reports failure
     * (and only sets $lastError) once every config has been tried.
     */
    private function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $html,
        string $plain
    ): bool {
        $errors = [];

        foreach (self::SMTP_CONFIGS as $cfg) {
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = $cfg['host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = self::SMTP_USER;
                $mail->Password   = self::SMTP_PASSWORD;
                $mail->SMTPSecure = $cfg['secure'];
                $mail->Port       = $cfg['port'];
                $mail->Timeout    = 15;
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ],
                ];

                $mail->setFrom(self::SMTP_USER, self::FROM_NAME);
                $mail->addAddress($toEmail, $toName);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $html;
                $mail->AltBody = $plain;

                $mail->send();
                return true; // first config that works wins

            } catch (MailerException $e) {
                $errors[] = "{$cfg['host']}:{$cfg['port']} — " . ($mail->ErrorInfo ?: $e->getMessage());
                // try the next config instead of giving up immediately
            }
        }

        $this->lastError = implode(' | ', $errors);
        error_log("MailerService error (all SMTP configs failed): {$this->lastError}");
        return false;
    }

    /** Build the email-verification HTML body. */
    private function buildVerifyHtml(string $firstName, string $link): string
    {
        $year = date('Y');
        return "
        <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;
                    border:1px solid #eee;border-radius:8px;overflow:hidden;'>
          <div style='background:#e91e8c;padding:24px;text-align:center;'>
            <h2 style='color:#fff;margin:0;'>Empress&#39; Cafe</h2>
          </div>
          <div style='padding:32px;'>
            <p style='font-size:16px;'>Hi <strong>{$firstName}</strong>,</p>
            <p>Your staff account has been created.
               Please verify your email address to activate it.</p>
            <p style='text-align:center;margin:32px 0;'>
              <a href='{$link}'
                 style='background:#e91e8c;color:#fff;padding:14px 32px;border-radius:6px;
                        text-decoration:none;font-weight:bold;font-size:15px;'>
                Verify My Email
              </a>
            </p>
            <p style='color:#888;font-size:13px;'>
              This link expires in <strong>24 hours</strong>.
              If you did not expect this email, please ignore it.</p>
          </div>
          <div style='background:#f8f8f8;padding:12px;text-align:center;
                      color:#aaa;font-size:12px;'>
            &copy; {$year} Empress&#39; Cafe. All rights reserved.
          </div>
        </div>";
    }

    /** Construct the full verification URL from a raw token. */
    private function buildVerifyLink(string $token): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = $this->detectBasePath();
        return "{$scheme}://{$host}{$base}/Backend/email-verify.php?token=" . urlencode($token);
    }

    /**
     * Figure out the project's base path under the web root, without
     * hardcoding any folder names and without depending on which script
     * triggered the email (Backend controller, Frontend/ADMIN AJAX
     * endpoint, etc — this file's own on-disk location is the anchor).
     *
     * This file lives at:  <projectRoot>/Backend/Services/MailerService.php
     * so two levels up from __DIR__ is always <projectRoot>. Comparing
     * that real filesystem path against DOCUMENT_ROOT gives the URL
     * base path, however deeply the project is nested
     * (e.g. '/CAPSSTONE/capstone/code').
     */
    private function detectBasePath(): string
    {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $projectRoot = realpath(dirname(__DIR__, 2)); // .../Backend/Services -> up 2

        if ($docRoot === false || $projectRoot === false) {
            return '';
        }

        // Normalise Windows backslashes to forward slashes for URL use.
        $docRoot     = str_replace('\\', '/', $docRoot);
        $projectRoot = str_replace('\\', '/', $projectRoot);

        if (strpos($projectRoot, $docRoot) !== 0) {
            // Project root isn't under the document root (unusual setup) — bail safely.
            return '';
        }

        $base = substr($projectRoot, strlen($docRoot));
        return rtrim($base, '/');
    }
}