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
 * NOTE ON FLOW: OTP/email verification has been removed. Once the
 * password checks out, the user is logged straight in - $_SESSION is
 * populated here and the browser is redirected directly to the
 * dashboard. (Previously this generated an OTP, emailed it, and sent
 * the user to verify.html; VerifyController is no longer used.)
 */

require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Models/User.php';

class LoginController
{
    // Data-access layer for the `user` table (find by email, find admin, etc).
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
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
            $this->redirect('../../index.html?error=invalid');
        }

        // Start (or resume) the dedicated ADMIN_SESSION cookie/scope.
        Session::start(Session::ADMIN);

        // There is only ever one "admin" account, so we look it up by
        // position rather than by an email the user typed.
        $user = $this->userModel->findAdminByPosition();   // find admin by position='admin'

        if (
            $user &&
            $user['position'] === 'admin' &&
            password_verify($pass, $user['password']) // bcrypt/argon2 hash comparison, never plain-text
        ) {
            $this->hydrateAdmin($user);
            $this->redirect('../../Frontend/ADMIN/index2.php');
            return;
        }

        // Wrong password, account not found, or not actually an admin -
        // always show the same generic "invalid" error (don't leak which
        // part failed).
        $this->redirect('../../index.html?error=invalid');
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

        // Start the STAFF named session scope.
        Session::start(Session::STAFF);

        $user = $this->userModel->findByEmail($email);

        if ($user && password_verify($pass, $user['password'])) {
            // Credentials are correct, but this endpoint is staff-only.
            if ($user['position'] !== 'staff') {
                $this->redirect('../../login-v2.html?error=unauthorized');
            }

            $this->hydrateStaff($user);
            $this->redirect('../../Frontend/POS.php');
            return;
        }

        // Generic failure message - intentionally identical whether the
        // email doesn't exist or the password is wrong, to avoid leaking
        // which emails are registered (prevents account enumeration).
        $this->redirect('../../login-v2.html?error=invalid');
    }

    // -----------------------------------------------------------------
    //  Session hydration
    // -----------------------------------------------------------------

    /** Populate $_SESSION with the admin's profile fields. */
    private function hydrateAdmin(array $user): void
    {
        Session::set('user',      $user['email']);
        Session::set('position',  'admin');
        Session::set('firstname', $user['firstname'] ?? '');
        Session::set('lastname',  $user['lastname']  ?? '');
        Session::set('image',     $user['image']     ?? '');
    }

    /** Populate $_SESSION with the staff member's profile fields. */
    private function hydrateStaff(array $user): void
    {
        Session::set('user',      $user['email']);
        Session::set('position',  $user['position'] ?? 'staff');
        Session::set('firstname', $user['firstname'] ?? '');
        Session::set('lastname',  $user['lastname']  ?? '');
        Session::set('image',     $user['image']     ?? '');
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

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