<?php
// Backend/Services/MailerService.php

/**
 * MailerService – all outbound email in one place.
 *
 * Sends via the Resend HTTP API (https://resend.com) instead of raw SMTP.
 *
 * WHY: Railway (and most PaaS hosts) block outbound SMTP ports
 * (25/465/587) at the network level, so PHPMailer/SMTP will always time
 * out in production ("SMTP code: 110, Connection timed out") even with
 * correct credentials. Resend just talks plain HTTPS on port 443, which
 * is never blocked, so it works from any host.
 *
 * SETUP:
 *   1. Sign up at https://resend.com (free tier is plenty for OTP volume).
 *   2. Verify a sending domain (or use their shared onboarding domain for
 *      testing only — real deployments should verify their own domain).
 *   3. Create an API key, then set these env vars on Railway
 *      (Project -> Variables), same place MYSQLHOST etc. live:
 *        RESEND_API_KEY   = re_xxxxxxxxxxxx
 *        MAIL_FROM_EMAIL  = otp@yourdomain.com   (must be on a verified domain)
 *        MAIL_FROM_NAME   = OPERLYTICS            (optional, has a default below)
 *   4. Locally (XAMPP), set the same vars in your php.ini, an .env loader,
 *      or just export them before starting Apache — getenv() reads from
 *      whatever mechanism populates PHP's environment.
 *
 * Two templates:
 *   - sendOtp()                 one-time login code
 *   - sendEmailVerification()   new-account activation link
 */
class MailerService
{
    private const RESEND_API_URL = 'https://api.resend.com/emails';
    private const DEFAULT_FROM_NAME = 'OPERLYTICS';

    // ── DB columns required by User::getVerifyToken() ────────────
    // If you dropped verify_token / token_expiry, run this once:
    //   ALTER TABLE user
    //     ADD COLUMN verify_token VARCHAR(64) NULL,
    //     ADD COLUMN token_expiry DATETIME    NULL;

    /** Last send error message — readable after a failed send. */
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

    /** Core send method — POSTs to the Resend HTTP API over HTTPS (port 443, never blocked). */
    private function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $html,
        string $plain
    ): bool {
        $apiKey    = getenv('RESEND_API_KEY') ?: '';
        $fromEmail = getenv('MAIL_FROM_EMAIL') ?: '';
        $fromName  = getenv('MAIL_FROM_NAME') ?: self::DEFAULT_FROM_NAME;

        if ($apiKey === '' || $fromEmail === '') {
            $this->lastError = 'Mailer not configured: missing RESEND_API_KEY or MAIL_FROM_EMAIL env var.';
            error_log("MailerService error: {$this->lastError}");
            return false;
        }

        $payload = [
            'from'    => "{$fromName} <{$fromEmail}>",
            'to'      => [$toEmail],
            'subject' => $subject,
            'html'    => $html,
            'text'    => $plain,
        ];

        $ch = curl_init(self::RESEND_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response  = curl_exec($ch);
        $curlErr   = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            $this->lastError = "cURL error: {$curlErr}";
            error_log("MailerService error: {$this->lastError}");
            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->lastError = "Resend API error (HTTP {$httpCode}): {$response}";
            error_log("MailerService error: {$this->lastError}");
            return false;
        }

        return true;
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
        return "{$scheme}://{$host}/Backend/email-verify.php?token=" . urlencode($token);
    }
}