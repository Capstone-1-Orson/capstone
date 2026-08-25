<?php
// Backend/Models/Supplier.php

require_once __DIR__ . '/../Core/Database.php';

/**
 * Supplier model – CRUD for the `suppliers` table.
 */
class Supplier
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ─────────────────────────────────────────────────────────────
    //  Read
    // ─────────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM suppliers WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function all(): array
    {
        $result = $this->db->query('SELECT * FROM suppliers ORDER BY name ASC');
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // ─────────────────────────────────────────────────────────────
    //  Write
    // ─────────────────────────────────────────────────────────────

    /**
     * @param array $data  Keys: name, category, contact_person, phone,
     *                     email, address, notes, status
     */
    public function create(array $data): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO suppliers
             (name, category, contact_person, phone, email, address, notes, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'ssssssss',
            $data['name'],
            $data['category'],
            $data['contact_person'],
            $data['phone'],
            $data['email'],
            $data['address'],
            $data['notes'],
            $data['status']
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * @param int   $id
     * @param array $data  Same keys as create().
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE suppliers
             SET name=?, category=?, contact_person=?, phone=?,
                 email=?, address=?, notes=?, status=?
             WHERE id=?'
        );
        $stmt->bind_param(
            'ssssssssi',
            $data['name'],
            $data['category'],
            $data['contact_person'],
            $data['phone'],
            $data['email'],
            $data['address'],
            $data['notes'],
            $data['status'],
            $id
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM suppliers WHERE id = ?');
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ─────────────────────────────────────────────────────────────
    //  Validation
    // ─────────────────────────────────────────────────────────────

    /** Sanitise the status field to one of two allowed values. */
    public static function sanitiseStatus(string $raw): string
    {
        return $raw === 'Inactive' ? 'Inactive' : 'Active';
    }
}
