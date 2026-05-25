<?php
// Backend/Controllers/LoginController.php

/**
 * LoginController – handles both login entry points.
 *
 * Replaces: Backend/login.php
 *
 * Routes (POST fields present):
 *   pass   → lockscreen re-auth (admin)
 *   email  → main login form (staff / admin)
 */

require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Services/MailerService.php';

class LoginController
{
    private User          $userModel;
    private MailerService $mailer;

    public function __construct()
    {
        $this->userModel = new User();
        $this->mailer    = new MailerService();
    }

    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return; // Nothing to do
        }

        if (isset($_POST['pass'])) {
            $this->lockscreenReauth();
        } elseif (isset($_POST['email'])) {
            $this->mainLogin();
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Lockscreen re-auth  (admin only)
    // ─────────────────────────────────────────────────────────────

    private function lockscreenReauth(): void
    {
        $pass = trim($_POST['pass'] ?? '');

        if (empty($pass)) {
            $this->redirect('../../lockscreen.html?error=invalid');
        }

        Session::start(Session::ADMIN);

        $user = $this->userModel->findAdminByPosition();   // find admin by position='admin'

        if (
            $user &&
            $user['position'] === 'admin' &&
            password_verify($pass, $user['password'])
        ) {
            $otp = $this->generateOtp();
            $this->storeOtp($otp, 'admin', 'admin', $user['notify_email']);

            if (!$this->mailer->sendOtp($user['notify_email'], 'Admin', $otp)) {
                $this->redirect('../../lockscreen.html?error=mail');
            }

            $this->redirect('../../verify.html?role=admin');
        }

        $this->redirect('../../lockscreen.html?error=invalid');
    }

    // ─────────────────────────────────────────────────────────────
    //  Main login form  (staff)
    // ─────────────────────────────────────────────────────────────

    private function mainLogin(): void
    {
        $email = trim($_POST['email']    ?? '');
        $pass  = trim($_POST['password'] ?? '');

        if (empty($email) || empty($pass)) {
            $this->redirect('../../login-v2.html?error=invalid');
        }

        Session::start(Session::STAFF);

        $user = $this->userModel->findByEmail($email);

        if ($user && password_verify($pass, $user['password'])) {
            if ($user['position'] !== 'staff') {
                $this->redirect('../../login-v2.html?error=unauthorized');
            }

            $otp = $this->generateOtp();
            $this->storeOtp($otp, 'staff', $email, $email);

            if (!$this->mailer->sendOtp($email, $email, $otp)) {
                $this->redirect('../../login-v2.html?error=mail');
            }

            $this->redirect('../../verify.html?role=staff');
        }

        $this->redirect('../../login-v2.html?error=invalid');
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    /** Generate a zero-padded 6-digit OTP string. */
    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** Store hashed OTP + metadata in the current session. */
    private function storeOtp(
        string $otp,
        string $role,
        string $identity,
        string $notifyEmail
    ): void {
        Session::set('otp',          password_hash($otp, PASSWORD_DEFAULT));
        Session::set('otp_expires',  time() + 300);   // 5 minutes
        Session::set('otp_role',     $role);
        Session::set('otp_identity', $identity);
        Session::set('otp_email',    $notifyEmail);
    }

    private function redirect(string $url): never
    {
        header("Location: $url");
        exit();
    }
}

(new LoginController())->handle();