<?php
// Frontend/Core/View.php

require_once __DIR__ . '/../../Backend/Core/Auth.php';
require_once __DIR__ . '/../../Backend/Core/Session.php';
require_once __DIR__ . '/../../Backend/Core/Database.php';

/**
 * View – base helper shared by all frontend page controllers.
 *
 * Provides:
 *   - Auth guards (requireAdmin / requireStaff)
 *   - A single shared DB connection
 *   - Flash-message renderer
 *   - CSRF token accessor
 */
class View
{
    protected mysqli $db;

    protected function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ── Auth guards ──────────────────────────────────────────────

    protected function requireAdmin(): void
    {
        Auth::requireAdmin('../../lockscreen.html');
    }

    protected function requireStaff(): void
    {
        Auth::requireStaff('../login-v2.html');
    }

    // ── Convenience DB query wrappers ────────────────────────────

    /** Run a SELECT and return all rows as an associative array. */
    protected function fetchAll(string $sql): array
    {
        $res = $this->db->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    /** Run a SELECT and return the first row (or null). */
    protected function fetchOne(string $sql): ?array
    {
        $res = $this->db->query($sql);
        if (!$res) {
            return null;
        }
        $row = $res->fetch_assoc();
        return $row ?: null;
    }

    // ── Flash messages ───────────────────────────────────────────

    /**
     * Render any pending success/error flash messages and clear them.
     * Call this inside the HTML layout where you want the alerts to appear.
     */
    public static function renderFlash(): void
    {
        if (!empty($_SESSION['success'])) {
            $msg = htmlspecialchars($_SESSION['success']);
            unset($_SESSION['success']);
            echo "<div class='alert alert-success alert-dismissible fade show mx-3 mt-3' role='alert'>
                    <i class='fas fa-check-circle mr-2'></i>{$msg}
                    <button type='button' class='close' data-dismiss='alert'>&times;</button>
                  </div>";
        }
        if (!empty($_SESSION['error'])) {
            $msg = htmlspecialchars($_SESSION['error']);
            unset($_SESSION['error']);
            echo "<div class='alert alert-danger alert-dismissible fade show mx-3 mt-3' role='alert'>
                    <i class='fas fa-exclamation-circle mr-2'></i>{$msg}
                    <button type='button' class='close' data-dismiss='alert'>&times;</button>
                  </div>";
        }
    }

    // ── CSRF ─────────────────────────────────────────────────────

    /** Return (and generate if absent) the CSRF token for the current session. */
    public static function csrfToken(): string
    {
        return Auth::csrfToken();
    }

    /** Print a hidden CSRF input field. */
    public static function csrfField(): void
    {
        echo "<input type='hidden' name='csrf_token' value='" . static::csrfToken() . "'>";
    }

    // ── Session shortcuts ────────────────────────────────────────

    public static function adminName(): string
    {
        return htmlspecialchars($_SESSION['user']['firstname'] ?? 'Admin');
    }

    public static function staffName(): string
    {
        return htmlspecialchars($_SESSION['user'] ?? 'Staff');
    }
}
