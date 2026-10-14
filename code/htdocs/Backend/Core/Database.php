<?php
// Backend/Core/Database.php

// The app is used in the Philippines, but the server (and MySQL's own
// default) runs in UTC. Without this, PHP's date()/strtotime() calls and
// MySQL's NOW()/CURDATE() both report UTC time, so every timestamp shown
// in the admin dashboard (and anywhere else that reads created_at) looks
// 8 hours behind the real local time. Setting this once, here, fixes it
// everywhere that includes this file (POS.php, all ADMIN pages via View.php).
date_default_timezone_set('Asia/Manila');

/**
 * Database – Singleton wrapper around MySQLi.
 *
 * Usage:
 *   $db = Database::getInstance();
 *   $conn = $db->getConnection();
 */
class Database
{
    private static ?Database $instance = null;
    private mysqli $conn;

    // ── Connection settings ──────────────────────────────────────
    private string $host     = 'sql211.infinityfree.com';
    private string $user     = 'if0_42749205';
    private string $password = 'V2o0TuIWkeIS';
    private string $dbName   = 'if0_42749205_empress_cafe';
    private int $port        = 3306;

    /** Private constructor – use getInstance(). */
    private function __construct()
    {
        // IMPORTANT: since PHP 8.1, mysqli defaults to throwing a
        // mysqli_sql_exception on any failed query instead of returning
        // false. This codebase (POS.php, etc.) is written the old way —
        // `if ($result) { ... }` checks everywhere — so we restore the
        // pre-8.1 behavior here. Without this, a single query that
        // doesn't quite match the live schema (a missing column, etc.)
        // throws an uncaught exception and the whole page 500s, instead
        // of the code's own defensive checks handling it gracefully.
        mysqli_report(MYSQLI_REPORT_OFF);

        // Railway (and most hosts) inject DB credentials as env vars.
        // Fall back to local XAMPP defaults when they aren't set.
        $this->host     = getenv('MYSQLHOST') ?: $this->host;
        $this->user     = getenv('MYSQLUSER') ?: $this->user;
        $this->password = getenv('MYSQLPASSWORD') ?: $this->password;
        $this->dbName   = getenv('MYSQLDATABASE') ?: $this->dbName;
        $this->port     = (int) (getenv('MYSQLPORT') ?: $this->port);

        $this->conn = new mysqli(
            $this->host,
            $this->user,
            $this->password,
            $this->dbName,
            $this->port
        );

        if ($this->conn->connect_error) {
            // Throw so callers can handle gracefully instead of dying.
            throw new RuntimeException(
                'Database connection failed: ' . $this->conn->connect_error
            );
        }

        $this->conn->set_charset('utf8mb4');

        // Make MySQL's own NOW()/CURDATE()/CURRENT_TIMESTAMP (used all over
        // the app for created_at, voided_at, refunded_at, etc.) agree with
        // the PHP timezone set above, instead of whatever UTC/system default
        // the DB server has. '+08:00' = Asia/Manila, no DST.
        $this->conn->query("SET time_zone = '+08:00'");
    }

    /** Returns the single shared instance. */
    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /** Returns the underlying MySQLi connection. */
    public function getConnection(): mysqli
    {
        return $this->conn;
    }

    /** Prevent cloning of the singleton. */
    private function __clone() {}
}

// Expose a bare $conn for files that expect a plain MySQLi variable.
$conn = Database::getInstance()->getConnection();