<?php
// Backend/Controllers/LogoutController.php

/**
 * LogoutController - the simplest controller in the app.
 *
 * Whatever session the user currently has (admin or staff) gets fully
 * destroyed, and they're bounced back to the login page. There is no
 * branching logic here at all - visiting this script always logs you out.
 *
 * IMPORTANT: Session::destroy() only tears down whichever *named* session
 * is currently active. Since admin and staff each live in their own named
 * session (with their own cookie), we have to Session::start() each named
 * session before we can destroy it - otherwise the destroy() call has
 * nothing to act on and the real staff_sid/admin_sid cookie (and its
 * server-side data) survives, letting a "logged out" user reopen a
 * protected page from history/back and still be authenticated.
 */

require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Core/Auth.php';

class LogoutController
{
    public function handle(): void
    {
        // Destroy both named sessions. We don't rely on a `role` query
        // param to decide which one to kill - clearing both is cheap and
        // guarantees a stale cookie from either portal can never grant
        // access again, regardless of which login page the user logged
        // out from.
        $cookieNames = ['STAFF_SESSION' => 'staff_sid', 'ADMIN_SESSION' => 'admin_sid'];
        foreach ([Session::STAFF, Session::ADMIN] as $sessionName) {
            // Only bother starting/destroying a named session if its cookie
            // is actually present - avoids needlessly creating a fresh
            // empty session (and its cookie) just to immediately expire it.
            if (isset($_COOKIE[$cookieNames[$sessionName]])) {
                Session::start($sessionName);
                Session::destroy();
            }
        }

        // Redirect to login page
        header('Location: ../../login-v2.html');
        exit;
    }
}

// ── Dispatch ────────────────────────────────────────────────────
$controller = new LogoutController();
$controller->handle();
