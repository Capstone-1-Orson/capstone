<?php
// Backend/Models/Order.php

require_once __DIR__ . '/../Core/Database.php';

/**
 * Order model – reads and writes `orders`, `order_items`, and `order_refunds`.
 *
 * Heavy order-placement logic lives in OrderService; this model owns the
 * raw DB operations only.
 */
class Order
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
        $stmt = $this->db->prepare(
            'SELECT * FROM orders WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /** Return all order_items rows joined with menu name for one order. */
    public function getItems(int $orderId): array
    {
        $stmt = $this->db->prepare(
            'SELECT oi.menu_id, oi.qty, oi.unit_price,
                    oi.removed_ingredient_ids, m.name AS menu_name
             FROM order_items oi
             JOIN menu m ON m.id = oi.menu_id
             WHERE oi.order_id = ?'
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Decode the JSON column into a real array
        return array_map(function (array $row) {
            $row['removed_ingredient_ids'] =
                json_decode($row['removed_ingredient_ids'] ?? '[]', true) ?: [];
            return $row;
        }, $rows);
    }

    // ─────────────────────────────────────────────────────────────
    //  Write
    // ─────────────────────────────────────────────────────────────

    /**
     * Insert one order header row.
     * Detects available columns at runtime so it works with any schema version.
     *
     * @param array $data  Keys: table_no, user_id, total_amt,
     *                     discount_amt, discount_type, order_type,
     *                     pay_method, cash_tendered
     * @return int  New order id, or 0 on failure.
     */
    public function create(array $data): int
    {
        // Detect which optional columns exist so we never INSERT into a missing column
        $existing = [];
        $res = $this->db->query("SHOW COLUMNS FROM orders");
        while ($col = $res->fetch_assoc()) {
            $existing[] = $col['Field'];
        }

        $cols = ['table_no', 'status', 'total_amt', 'discount_amt', 'discount_type', 'created_at'];
        $vals = [
            $data['table_no']     ?? '01',
            'Done',
            (float) ($data['total_amt']     ?? 0),
            (float) ($data['discount_amt']  ?? 0),
            $data['discount_type'] ?? '',
        ];
        $types = 'ssddss'; // table_no, status, total_amt, discount_amt, discount_type + NOW() literal

        // Remove 'created_at' from $cols since we use NOW() literal (not a param)
        $cols = array_filter($cols, fn($c) => $c !== 'created_at');

        // Optional columns
        $optional = [
            'order_type'    => ['s', $data['order_type']    ?? 'Dine In'],
            'pay_method'    => ['s', $data['pay_method']    ?? 'Cash'],
            'cash_tendered' => ['d', (float)($data['cash_tendered'] ?? 0)],
            'user_id'       => ['i', (int)($data['user_id']   ?? 0)   ?: null],
            'cashier_id'    => ['i', (int)($data['cashier_id'] ?? 0)  ?: null],
        ];

        foreach ($optional as $col => [$type, $value]) {
            if (in_array($col, $existing, true) && $value !== null) {
                $cols[]  = $col;
                $vals[]  = $value;
                $types  .= $type;
            }
        }

        $colStr  = implode(', ', $cols);
        $phStr   = implode(', ', array_fill(0, count($cols), '?')) . ', NOW()';

        $stmt = $this->db->prepare("INSERT INTO orders ($colStr, created_at) VALUES ($phStr)");
        // Remove 'created_at' count from placeholders — it's a literal NOW()
        $stmt->bind_param($types, ...$vals);
        $ok = $stmt->execute();
        $id = $ok ? (int) $this->db->insert_id : 0;
        $stmt->close();
        return $id;
    }

    /**
     * Insert one order_items row.
     *
     * @param array $data  Keys: order_id, menu_id, qty, unit_price,
     *                     removed_ids (array), removed_names (array),
     *                     addons (string), notes (string)
     */
    public function addItem(array $data): bool
    {
        $removedIds   = json_encode($data['removed_ids']   ?? []);
        $removedNames = json_encode($data['removed_names'] ?? []);

        $stmt = $this->db->prepare(
            'INSERT INTO order_items
             (order_id, menu_id, qty, unit_price,
              removed_ingredient_ids, removed_ingredient_names,
              addons, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iiidssss',
            $data['order_id'],
            $data['menu_id'],
            $data['qty'],
            $data['unit_price'],
            $removedIds,
            $removedNames,
            $data['addons'],
            $data['notes']
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /** Update the status of an order. */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ─────────────────────────────────────────────────────────────
    //  Refund log
    // ─────────────────────────────────────────────────────────────

    /**
     * Ensure the `order_refunds` table exists and insert a record.
     *
     * @param array $data  Keys: order_id, action, refund_amt,
     *                     reason, items (array), created_by
     * @return int  New log id.
     */
    public function logRefund(array $data): int
    {
        // Create the table if it does not exist yet
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS order_refunds (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                order_id    INT NOT NULL,
                action      ENUM('void','refund') NOT NULL,
                refund_amt  DECIMAL(10,2) NOT NULL DEFAULT 0,
                reason      VARCHAR(255) DEFAULT NULL,
                items_json  TEXT DEFAULT NULL,
                created_by  VARCHAR(100) DEFAULT NULL,
                created_at  DATETIME DEFAULT NOW()
            )"
        );

        $itemsJson = json_encode($data['items'] ?? []);
        $stmt      = $this->db->prepare(
            'INSERT INTO order_refunds
             (order_id, action, refund_amt, reason, items_json, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'isdsss',
            $data['order_id'],
            $data['action'],
            $data['refund_amt'],
            $data['reason'],
            $itemsJson,
            $data['created_by']
        );
        $ok = $stmt->execute();
        $id = $ok ? (int) $this->db->insert_id : 0;
        $stmt->close();
        return $id;
    }

    // ─────────────────────────────────────────────────────────────
    //  Transaction helpers (delegate to underlying connection)
    // ─────────────────────────────────────────────────────────────

    public function beginTransaction(): void
    {
        $this->db->begin_transaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollback(): void
    {
        $this->db->rollback();
    }
}