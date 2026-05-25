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
 * Centralises SMTP credentials and two mail templates:
 *   - sendOtp()                 one-time login code
 *   - sendEmailVerification()   new-account activation link
 */
class MailerService
{
    // ── SMTP credentials ─────────────────────────────────────────
    private const SMTP_HOST     = 'smtp.gmail.com';
    private const SMTP_PORT     = 587;
    private const SMTP_USER     = 'dummyacctest099@gmail.com';
    private const SMTP_PASSWORD = 'dzsmxafqhxqgarto';       // Gmail App Password (no spaces)
    private const FROM_NAME     = 'OPERLYTICS';

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
     * Send a 6-digit OTP to the given address.
     *
     * @param string $toEmail  Recipient email
     * @param string $toName   Recipient display name
     * @param string $otp      6-digit code
     */
    public function sendOtp(string $toEmail, string $toName, string $otp): bool
    {
        $subject = 'Your OPERLYTICS Verification Code';
        $html    = $this->buildOtpHtml($toName, $otp);
        $plain   = "Your OPERLYTICS verification code: {$otp}  (expires in 5 minutes)";

        return $this->send($toEmail, $toName, $subject, $html, $plain);
    }

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

    /** Core send method — configures PHPMailer and dispatches the message. */
    private function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $html,
        string $plain
    ): bool {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = self::SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = self::SMTP_USER;
            $mail->Password   = self::SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = self::SMTP_PORT;
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
            return true;

        } catch (MailerException $e) {
            $this->lastError = $mail->ErrorInfo ?: $e->getMessage();
            error_log("MailerService error: {$this->lastError}");
            return false;
        }
    }

    /** Build the OTP email HTML body. */
    private function buildOtpHtml(string $name, string $otp): string
    {
        return "
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
          <p style='color:#2d1a25;font-size:.95rem;margin-bottom:8px'>Hello {$name},</p>
          <p style='color:#6b3050;font-size:.88rem;margin-bottom:24px'>
            Use the code below to complete your login.
            It expires in <strong>5 minutes</strong>.</p>
          <div style='text-align:center;background:#fdf0f5;border-radius:12px;
                      padding:24px;margin-bottom:24px;border:1px solid rgba(212,74,122,.15)'>
            <span style='font-size:2.4rem;font-weight:700;letter-spacing:.35em;color:#C11C84'>
              {$otp}</span>
          </div>
          <p style='color:#b87090;font-size:.78rem;text-align:center'>
            If you didn't request this code, ignore this email.<br>
            Never share this code with anyone.</p>
        </div>";
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
        return "{$scheme}://{$host}/oop_refactored_fixed/Backend/email-verify.php?token=" . urlencode($token);
    }
}