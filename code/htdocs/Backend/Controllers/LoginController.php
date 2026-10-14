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
 * NOTE ON FLOW: Once the password is verified, the user is logged in
 * immediately - their identity is written straight into $_SESSION and
 * they're redirected to the right dashboard. (Previously this project
 * had an email OTP step in between; that has been removed.)
 */

require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/User.php';

class LoginController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (isset($_POST['pass'])) {
            $this->lockscreenReauth();
        } elseif (isset($_POST['email'])) {
            $this->mainLogin();
        }
    }

    // -----------------------------------------------------------------
    //  Lockscreen re-auth  (admin only)
    // -----------------------------------------------------------------

    private function lockscreenReauth(): void
    {
        $email = trim($_POST['email'] ?? '');
        $pass  = trim($_POST['pass']  ?? '');

        if (empty($email) || empty($pass)) {
            $this->redirect('../../index.html?error=invalid');
        }

        Session::start(Session::ADMIN);

        // Look this admin up by email specifically — there can be more
        // than one row with position = 'admin', so the email tells us
        // exactly which one is trying to log in instead of guessing.
        $user = $this->userModel->findByEmail($email);

        if (
            $user &&
            $user['position'] === 'admin' &&
            password_verify($pass, $user['password'])
        ) {
            $this->hydrateAdmin($user);
            $this->redirect('../../Frontend/ADMIN/index2.php');
        }

        $this->redirect('../../index.html?error=invalid');
    }

    // -----------------------------------------------------------------
    //  Main login form  (staff)
    // -----------------------------------------------------------------

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

            $this->hydrateStaff($user);
            $this->redirect('../../Frontend/POS.php');
        }

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

    private function redirect(string $url): never
    {
        header("Location: $url");
        exit();
    }
}
(new LoginController())->handle();
