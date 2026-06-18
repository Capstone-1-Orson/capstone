<?php
// Backend/Controllers/LoginController.php

/**
 * LoginController - handles both login entry points.
 *
 * Replaces: Backend/login.php
 *
 * Routes (POST fields present):
 *   pass   -> lockscreen re-auth (admin)
 *   email  -> main login form (staff / admin)
 *
 * NOTE ON FLOW: Neither branch logs the user straight in. Both branches
 * verify the password, then generate a one-time-passcode (OTP), email it,
 * and redirect to verify.html. The actual session "login" (writing
 * user/position/etc into $_SESSION) only happens later, in
 * VerifyController, once the correct OTP is submitted. This file is only
 * responsible for: "is this password correct, and if so, send an OTP".
 */

require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Services/MailerService.php';

class LoginController
{
    // Data-access layer for the `user` table (find by email, find admin, etc).
    private User          $userModel;

    // Wraps PHPMailer/SMTP so this controller doesn't need to know mail internals.
    private MailerService $mailer;

    public function __construct()
    {
        $this->userModel = new User();
        $this->mailer    = new MailerService();
    }

    /**
     * Entry point. Inspects which form submitted the request and
     * dispatches to the matching private handler.
     *
     * Two different HTML forms POST to this same script:
     *   - the admin lockscreen (sends only `pass`)
     *   - the main staff/admin login page (sends `email` + `password`)
     * We tell them apart by which field is present in $_POST.
     */
    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return; // Nothing to do - someone just GET-loaded this script directly.
        }

        if (isset($_POST['pass'])) {
            $this->lockscreenReauth();
        } elseif (isset($_POST['email'])) {
            $this->mainLogin();
        }
        // If neither field is present, we silently do nothing (no route matched).
    }

    // -----------------------------------------------------------------
    //  Lockscreen re-auth  (admin only)
    // -----------------------------------------------------------------

    /**
     * Handles the admin "lockscreen" re-authentication form, where an
     * already-known admin re-enters only their password (no email field)
     * to resume their session after it was locked.
     */
    private function lockscreenReauth(): void
    {
        $pass = trim($_POST['pass'] ?? '');

        if (empty($pass)) {
            $this->redirect('../../lockscreen.html?error=invalid');
        }

        // Start (or resume) the dedicated ADMIN_SESSION cookie/scope so the
        // OTP we are about to generate gets stored in the right session.
        Session::start(Session::ADMIN);

        // There is only ever one "admin" account, so we look it up by
        // position rather than by an email the user typed.
        $user = $this->userModel->findAdminByPosition();   // find admin by position='admin'

        if (
            $user &&
            $user['position'] === 'admin' &&
            password_verify($pass, $user['password']) // bcrypt/argon2 hash comparison, never plain-text
        ) {
            $otp = $this->generateOtp();
            $this->storeOtp($otp, 'admin', 'admin', $user['notify_email']);

            // If the email can't actually be sent, there's no point sending
            // the admin to a verify screen they can never pass - bail out.
            if (!$this->mailer->sendOtp($user['notify_email'], 'Admin', $otp)) {
                $this->redirect('../../lockscreen.html?error=mail');
            }

            $this->redirect('../../verify.html?role=admin');
        }

        // Wrong password, account not found, or not actually an admin -
        // always show the same generic "invalid" error (don't leak which
        // part failed).
        $this->redirect('../../lockscreen.html?error=invalid');
    }

    // -----------------------------------------------------------------
    //  Main login form  (staff)
    // -----------------------------------------------------------------

    /**
     * Handles the primary login form used by staff (email + password).
     * Despite the name, this is gated so that only `position === 'staff'`
     * accounts may use it - admins must use the lockscreen flow instead.
     */
    private function mainLogin(): void
    {
        $email = trim($_POST['email']    ?? '');
        $pass  = trim($_POST['password'] ?? '');

        if (empty($email) || empty($pass)) {
            $this->redirect('../../login-v2.html?error=invalid');
        }

        // Start the STAFF named session scope before we touch anything
        // that needs to persist (the OTP) across this request/redirect.
        Session::start(Session::STAFF);

        $user = $this->userModel->findByEmail($email);

        if ($user && password_verify($pass, $user['password'])) {
            // Credentials are correct, but this endpoint is staff-only.
            if ($user['position'] !== 'staff') {
                $this->redirect('../../login-v2.html?error=unauthorized');
            }

            $otp = $this->generateOtp();
            // For staff, the "identity" stored alongside the OTP and the
            // "notify email" are the same value (their own email address).
            $this->storeOtp($otp, 'staff', $email, $email);

            if (!$this->mailer->sendOtp($email, $email, $otp)) {
                $this->redirect('../../login-v2.html?error=mail');
            }

            $this->redirect('../../verify.html?role=staff');
        }

        // Generic failure message - intentionally identical whether the
        // email doesn't exist or the password is wrong, to avoid leaking
        // which emails are registered (prevents account enumeration).
        $this->redirect('../../login-v2.html?error=invalid');
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    /**
     * Generate a zero-padded 6-digit OTP string, e.g. "004821".
     * Uses random_int() (cryptographically secure) rather than rand()/mt_rand().
     */
    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Store the OTP (hashed, never in plain text) plus the metadata
     * VerifyController will need to finish the login once the user submits
     * the correct code.
     *
     * @param string $otp          The plain 6-digit code (only used to hash it; not stored raw).
     * @param string $role         'admin' or 'staff' - tells VerifyController which session to resume.
     * @param string $identity     Who is logging in (email for staff, literal 'admin' for admin).
     * @param string $notifyEmail  Address the OTP was actually emailed to (kept for reference/debug).
     */
    private function storeOtp(
        string $otp,
        string $role,
        string $identity,
        string $notifyEmail
    ): void {
        // Hash the OTP the same way passwords are hashed, so even if the
        // session storage were somehow exposed, the raw code isn't sitting
        // in plain text.
        Session::set('otp',          password_hash($otp, PASSWORD_DEFAULT));
        Session::set('otp_expires',  time() + 300);   // valid for 5 minutes
        Session::set('otp_role',     $role);
        Session::set('otp_identity', $identity);
        Session::set('otp_email',    $notifyEmail);
    }

    /**
     * Send a Location header and immediately stop script execution.
     * Declared `never` because every call site is a true dead end.
     */
    private function redirect(string $url): never
    {
        header("Location: $url");
        exit();
    }
}

// ── Bootstrap ──────────────────────────────────────────────────────
// This file is included directly by the web server for each login POST,
// so it instantiates and runs itself at the bottom rather than being
// `require`'d by a separate front controller/router.
(new LoginController())->handle();
