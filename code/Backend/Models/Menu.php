<?php
// Backend/Models/Menu.php

require_once __DIR__ . '/../Core/Database.php';

/**
 * Menu model – CRUD for the `menu` table and its `menu_ingredients` pivot.
 */
class Menu
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
        $stmt = $this->db->prepare('SELECT * FROM menu WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function all(): array
    {
        $result = $this->db->query('SELECT * FROM menu ORDER BY category, name');
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /** Check whether a menu item has ever appeared in an order. */
    public function hasOrderHistory(int $id): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS c FROM order_items WHERE menu_id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) $row['c'] > 0;
    }

    /** Return ingredient assignments for a menu item (for the edit modal). */
    public function getIngredients(int $menuId): array
    {
        $stmt = $this->db->prepare(
            'SELECT mi.ingredient_id, mi.qty_needed, i.name, i.unit
             FROM menu_ingredients mi
             JOIN ingredients i ON i.id = mi.ingredient_id
             WHERE mi.menu_id = ?
             ORDER BY i.name'
        );
        $stmt->bind_param('i', $menuId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    // ─────────────────────────────────────────────────────────────
    //  Write
    // ─────────────────────────────────────────────────────────────

    /**
     * @param array $data  Keys: name, category, price, is_available,
     *                     description, image (path string or null)
     * @return int  New menu id, or 0 on failure.
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO menu (name, category, price, is_available, description, image)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'ssdiss',
            $data['name'],
            $data['category'],
            $data['price'],
            $data['is_available'],
            $data['description'],
            $data['image']
        );
        $ok = $stmt->execute();
        $id = $ok ? (int) $this->db->insert_id : 0;
        $stmt->close();
        return $id;
    }

    /**
     * @param int   $id
     * @param array $data  Same keys as create().
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE menu
             SET name=?, category=?, price=?, is_available=?,
                 description=?, image=?
             WHERE id=?'
        );
        $stmt->bind_param(
            'ssdissi',
            $data['name'],
            $data['category'],
            $data['price'],
            $data['is_available'],
            $data['description'],
            $data['image'],
            $id
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Soft-delete: mark the item unavailable instead of removing it.
     * Use when the item has order history.
     */
    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE menu SET is_available = 0 WHERE id = ?');
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Hard-delete: removes the row and its pivot entries.
     * Only call after confirming there is no order history.
     */
    public function delete(int $id): bool
    {
        $this->db->query("DELETE FROM menu_ingredients WHERE menu_id = $id");

        $stmt = $this->db->prepare('DELETE FROM menu WHERE id = ?');
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ─────────────────────────────────────────────────────────────
    //  Ingredient pivot
    // ─────────────────────────────────────────────────────────────

    /**
     * Replace all ingredient assignments for a menu item.
     *
     * @param int   $menuId
     * @param array $ingredients  Array of [ 'ingredient_id' => int, 'qty_needed' => float ]
     */
    public function syncIngredients(int $menuId, array $ingredients): void
    {
        // Clear existing pivot rows
        $this->db->query("DELETE FROM menu_ingredients WHERE menu_id = $menuId");

        if (empty($ingredients)) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO menu_ingredients (menu_id, ingredient_id, qty_needed)
             VALUES (?, ?, ?)'
        );
        foreach ($ingredients as $ing) {
            $iid = (int) $ing['ingredient_id'];
            $qty = (float) $ing['qty_needed'];
            $stmt->bind_param('iid', $menuId, $iid, $qty);
            $stmt->execute();
        }
        $stmt->close();
    }
}
