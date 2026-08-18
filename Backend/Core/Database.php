<?php
// Backend/Core/Database.php

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
    private string $host     = 'localhost';
    private string $user     = 'root';
    private string $password = '';
    private string $dbName   = 'empress_cafe';
    private int $port        = 3306;

    /** Private constructor – use getInstance(). */
    private function __construct()
    {
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