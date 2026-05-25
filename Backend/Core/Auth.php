<?php
// Backend/Core/Auth.php

require_once __DIR__ . '/Session.php';

/**
 * Auth – checks whether the current session has a valid, authorised user.
 *
 * Call Auth::requireAdmin() / Auth::requireStaff() at the top of every
 * controller that needs access control.
 */
class Auth
{
    /** Abort with a 403 and redirect if the session is not an admin session. */
    public static function requireAdmin(string $redirectTo = '../../login-v2.html'): void
    {
        Session::start(Session::ADMIN);

        if (
            !Session::get('user') ||
            Session::get('position') !== 'admin'
        ) {
            http_response_code(403);
            header("Location: $redirectTo");
            exit();
        }
    }

    /** Abort with a 403 and redirect if the session is not a staff session. */
    public static function requireStaff(string $redirectTo = '../../login-v2.html'): void
    {
        Session::start(Session::STAFF);

        if (!Session::get('user')) {
            http_response_code(403);
            header("Location: $redirectTo");
            exit();
        }
    }

    /** Verify and rotate the CSRF token; redirects on failure. */
    public static function verifyCsrf(string $redirectTo = '../../Frontend/ADMIN/staff-list.php'): void
    {
        $submitted = trim($_POST['csrf_token'] ?? '');
        $stored    = Session::get('csrf_token', '');

        if (empty($submitted) || !hash_equals($stored, $submitted)) {
            Session::flashError('Invalid request. Please try again.');
            header("Location: $redirectTo");
            exit();
        }

        // Rotate after a successful check
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /** Generate (or return existing) CSRF token for the current session. */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
