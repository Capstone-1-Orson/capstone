<?php
// Backend/Controllers/LogoutController.php

/**
 * LogoutController - the simplest controller in the app.
 *
 * Whatever session the user currently has (admin or staff) gets fully
 * destroyed, and they're bounced back to the login page. There is no
 * branching logic here at all - visiting this script always logs you out.
 */

require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Core/Auth.php';

class LogoutController
{
    // Wrapper around PHP's native session functions (start/destroy/etc).
    private Session $session;

    public function __construct()
    {
        $this->session = new Session();
    }

    public function handle(): void
    {
        // Destroy the session completely - clears $_SESSION data, expires
        // the session cookie, and invalidates the session ID so it can't
        // be reused (protects against session fixation after logout).
        $this->session->destroy();

        // Redirect to login page
        header('Location: ../../login-v2.html');
        exit;
    }
}

// ── Dispatch ────────────────────────────────────────────────────
$controller = new LogoutController();
$controller->handle();
