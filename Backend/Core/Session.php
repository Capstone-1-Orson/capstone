<?php
// Backend/Core/Session.php

/**
 * Session – thin helper for named PHP sessions.
 *
 * The project uses two named sessions:
 *   ADMIN_SESSION  – admin panel routes
 *   STAFF_SESSION  – POS / cashier routes
 *
 * Each session uses its own cookie name so admin and staff can be
 * logged in simultaneously without overwriting each other.
 */
class Session
{
    public const ADMIN = 'ADMIN_SESSION';
    public const STAFF = 'STAFF_SESSION';

    // Each session name gets its own browser cookie so they never collide.
    private const COOKIE_NAMES = [
        self::ADMIN => 'admin_sid',
        self::STAFF => 'staff_sid',
    ];

    /** Start (or resume) a named session with its own dedicated cookie. */
    public static function start(string $name = self::ADMIN): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (session_name() === $name) {
                return; // Already on the right session
            }
            session_write_close(); // Close the other session first
        }

        $cookieName = self::COOKIE_NAMES[$name] ?? $name;

        session_name($name);

        // Use a unique cookie name per session so the two sessions
        // store separate cookies in the browser and never overwrite each other.
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => false,   // set true if using HTTPS
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Manually read the correct cookie so PHP resumes the right session
        $cookieId = $_COOKIE[$cookieName] ?? '';
        if ($cookieId) {
            session_id($cookieId);
        }

        session_start();

        // Write the session ID back under our custom cookie name
        if (!$cookieId || session_id() !== $cookieId) {
            setcookie(
                $cookieName,
                session_id(),
                [
                    'expires'  => 0,
                    'path'     => '/',
                    'secure'   => false,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
        }
    }

    /** Read a session value (returns $default if key absent). */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /** Write a session value. */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /** Remove a session key. */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Destroy the current session entirely. */
    public static function destroy(): void
    {
        session_unset();
        session_destroy();
    }

    // ── Flash messages ───────────────────────────────────────────

    public static function flash(string $key, string $message): void
    {
        $_SESSION[$key] = $message;
    }

    public static function flashSuccess(string $message): void
    {
        static::flash('success', $message);
    }

    public static function flashError(string $message): void
    {
        static::flash('error', $message);
    }
}