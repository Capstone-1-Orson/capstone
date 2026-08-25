<?php
// Backend/Models/User.php

require_once __DIR__ . '/../Core/Database.php';

/**
 * User model – all DB operations for the `user` table.
 *
 * Responsibilities:
 *   - findByEmail / findById
 *   - create / update / delete
 *   - password hashing & verification
 *   - email-verification token management
 */
class User
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ─────────────────────────────────────────────────────────────
    //  Read
    // ─────────────────────────────────────────────────────────────

    /** Fetch a single user row by email. Returns assoc array or null. */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM user WHERE email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /** Fetch the admin user (position = 'admin'). Returns assoc array or null. */
    public function findAdminByPosition(): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM user WHERE position = 'admin' LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /** Fetch a single user row by primary key. Returns assoc array or null. */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM user WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /** Check whether an email is already taken (optionally excluding one id). */
    public function emailExists(string $email, int $excludeId = 0): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM user WHERE email = ? AND id != ? LIMIT 1'
        );
        $stmt->bind_param('si', $email, $excludeId);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /** Check whether a contact number is already taken (optionally excluding one id). */
    public function contactExists(string $contact, int $excludeId = 0): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM user WHERE contact = ? AND id != ? LIMIT 1'
        );
        $stmt->bind_param('si', $contact, $excludeId);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    // ─────────────────────────────────────────────────────────────
    //  Write
    // ─────────────────────────────────────────────────────────────

    /**
     * Insert a new user row.
     *
     * @param array $data  Keys: firstname, lastname, email, password (plain),
     *                     position, contact, address, image (optional)
     * @return int  New row's auto-increment id, or 0 on failure.
     */
    public function create(array $data): int
    {
        $hashed = password_hash($data['password'], PASSWORD_DEFAULT);
        $image  = $data['image'] ?? '';

        $stmt = $this->db->prepare(
            'INSERT INTO user
             (firstname, lastname, email, password, position, contact, address,
              image, email_verified)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)'
        );
        $stmt->bind_param(
            'ssssssss',
            $data['firstname'],
            $data['lastname'],
            $data['email'],
            $hashed,
            $data['position'],
            $data['contact'],
            $data['address'],
            $image
        );

        $ok = $stmt->execute();
        $id = $ok ? (int) $this->db->insert_id : 0;
        $stmt->close();

        return $id;
    }

    /**
     * Generate a secure verification token, store it in the DB, and return it.
     * Uses verify_token and token_expiry columns (re-add these if missing).
     *
     * Schema (run once if columns are absent):
     *   ALTER TABLE user
     *     ADD COLUMN verify_token  VARCHAR(64)  NULL,
     *     ADD COLUMN token_expiry  DATETIME     NULL;
     */
    public function getVerifyToken(int $userId): string
    {
        $token  = bin2hex(random_bytes(32));          // 64-char hex string
        $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $this->db->prepare(
            'UPDATE user SET verify_token = ?, token_expiry = ? WHERE id = ?'
        );
        $stmt->bind_param('ssi', $token, $expiry, $userId);
        $stmt->execute();
        $stmt->close();

        return $token;
    }

    /**
     * Update an existing user row.
     * If $data['password'] is non-empty it will be hashed and saved.
     *
     * @param int   $id
     * @param array $data  Keys: firstname, lastname, email, contact,
     *                     address, position, image, password (optional)
     */
    public function update(int $id, array $data): bool
    {
        if (!empty($data['password'])) {
            $hashed = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt   = $this->db->prepare(
                'UPDATE user
                 SET firstname=?, lastname=?, email=?, contact=?,
                     address=?, position=?, password=?, image=?
                 WHERE id=?'
            );
            $stmt->bind_param(
                'ssssssssi',
                $data['firstname'], $data['lastname'], $data['email'],
                $data['contact'],   $data['address'],  $data['position'],
                $hashed,            $data['image'],    $id
            );
        } else {
            $stmt = $this->db->prepare(
                'UPDATE user
                 SET firstname=?, lastname=?, email=?, contact=?,
                     address=?, position=?, image=?
                 WHERE id=?'
            );
            $stmt->bind_param(
                'sssssssi',
                $data['firstname'], $data['lastname'], $data['email'],
                $data['contact'],   $data['address'],  $data['position'],
                $data['image'],     $id
            );
        }

        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /** Delete a user by id. Returns true on success. */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM user WHERE id = ?');
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ─────────────────────────────────────────────────────────────
    //  Email verification
    // ─────────────────────────────────────────────────────────────

    /**
     * Reset email_verified flag and clear any old token so a fresh one is issued.
     * Returns empty string — kept so StaffController::update() does not break.
     */
    public function resetVerification(int $id): string
    {
        $stmt = $this->db->prepare(
            'UPDATE user SET email_verified = 0, verify_token = NULL, token_expiry = NULL WHERE id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        return '';
    }

    /** Mark a user's email as verified. */
    public function markVerified(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE user SET email_verified=1 WHERE id=?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    // ─────────────────────────────────────────────────────────────
    //  Validation helpers (static — no DB needed)
    // ─────────────────────────────────────────────────────────────

    /** Returns an error string if the password does not meet policy, or '' if OK. */
    public static function validatePassword(string $password): string
    {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters!';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least one uppercase letter!';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one number!';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Password must contain at least one special character!';
        }
        return '';
    }

    /** Returns an error string if the contact number is invalid, or '' if OK. */
    public static function validateContact(string $contact): string
    {
        return preg_match('/^[0-9]{11}$/', $contact)
            ? ''
            : 'Contact number must be exactly 11 digits!';
    }

    /** Returns an error string if the email domain is not allowed, or '' if OK. */
    public static function validateEmail(string $email): string
    {
        return preg_match(
            '/^[a-zA-Z0-9._%+\-]+@(gmail|yahoo)\.(com|com\.ph)$/',
            $email
        )
            ? ''
            : 'Only Gmail or Yahoo email addresses are allowed!';
    }
}