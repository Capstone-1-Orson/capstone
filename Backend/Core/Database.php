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

    /** Private constructor – use getInstance(). */
    private function __construct()
    {
        $this->conn = new mysqli(
            $this->host,
            $this->user,
            $this->password,
            $this->dbName
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