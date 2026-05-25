<?php
// Backend/Models/Ingredient.php

require_once __DIR__ . '/../Core/Database.php';

/**
 * Ingredient model – CRUD for the `ingredients` table.
 *
 * Also provides deduct() / restore() used by the POS order flow.
 */
class Ingredient
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
        $stmt = $this->db->prepare('SELECT * FROM ingredients WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function all(): array
    {
        $result = $this->db->query(
            'SELECT * FROM ingredients ORDER BY name ASC'
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // ─────────────────────────────────────────────────────────────
    //  Write
    // ─────────────────────────────────────────────────────────────

    /**
     * @param array $data  Keys: name, unit, stock_qty, low_stock_threshold,
     *                     expiry_date (nullable)
     */
    public function create(array $data): bool
    {
        $now         = date('Y-m-d H:i:s');
        $expiryDate  = $data['expiry_date'] ?: null;

        $stmt = $this->db->prepare(
            'INSERT INTO ingredients
             (name, unit, stock_qty, low_stock_threshold, expiry_date, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'ssddsss',
            $data['name'],
            $data['unit'],
            $data['stock_qty'],
            $data['low_stock_threshold'],
            $expiryDate,
            $now,
            $now
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
        $now        = date('Y-m-d H:i:s');
        $expiryDate = $data['expiry_date'] ?: null;

        $stmt = $this->db->prepare(
            'UPDATE ingredients
             SET name=?, unit=?, stock_qty=?, low_stock_threshold=?,
                 expiry_date=?, updated_at=?
             WHERE id=?'
        );
        $stmt->bind_param(
            'ssddssi',
            $data['name'],
            $data['unit'],
            $data['stock_qty'],
            $data['low_stock_threshold'],
            $expiryDate,
            $now,
            $id
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Add qty to an ingredient's stock (restock operation).
     *
     * @param int   $id
     * @param float $qty  Amount to add
     */
    public function restock(int $id, float $qty): bool
    {
        $now  = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            'UPDATE ingredients
             SET stock_qty = stock_qty + ?, updated_at = ?
             WHERE id = ?'
        );
        $stmt->bind_param('dsi', $qty, $now, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Subtract qty from stock — floor at 0.
     *
     * @param int   $id
     * @param float $qty  Amount to subtract
     */
    public function deduct(int $id, float $qty): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE ingredients
             SET stock_qty = GREATEST(stock_qty - ?, 0), updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->bind_param('di', $qty, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Add qty back to stock (used during void / refund).
     *
     * @param int   $id
     * @param float $qty  Amount to restore
     */
    public function restore(int $id, float $qty): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE ingredients SET stock_qty = stock_qty + ? WHERE id = ?'
        );
        $stmt->bind_param('di', $qty, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Update the low-stock threshold for a single ingredient.
     */
    public function updateThreshold(int $id, float $threshold): bool
    {
        $now  = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            'UPDATE ingredients
             SET low_stock_threshold = ?, updated_at = ?
             WHERE id = ?'
        );
        $stmt->bind_param('dsi', $threshold, $now, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Hard-delete an ingredient and all its recipe links.
     */
    public function delete(int $id): bool
    {
        // Remove FK-linked rows first
        $del1 = $this->db->prepare('DELETE FROM menu_ingredients WHERE ingredient_id = ?');
        $del1->bind_param('i', $id);
        $del1->execute();
        $del1->close();

        $del2 = $this->db->prepare('DELETE FROM ingredients WHERE id = ?');
        $del2->bind_param('i', $id);
        $ok = $del2->execute();
        $del2->close();

        return $ok;
    }

    // ─────────────────────────────────────────────────────────────
    //  Recipe helpers (used by OrderService)
    // ─────────────────────────────────────────────────────────────

    /**
     * Return the ingredient requirements for a menu item.
     *
     * @return array[]  Each row: [ ingredient_id, qty_needed, name, unit, stock_qty ]
     */
    public function getRequirementsForMenu(int $menuId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.id, i.name, i.unit, i.stock_qty, mi.qty_needed
             FROM menu_ingredients mi
             JOIN ingredients i ON i.id = mi.ingredient_id
             WHERE mi.menu_id = ?'
        );
        $stmt->bind_param('i', $menuId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}
