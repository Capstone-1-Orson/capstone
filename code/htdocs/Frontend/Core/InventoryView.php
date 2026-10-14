<?php
// Frontend/Core/InventoryView.php

require_once __DIR__ . '/View.php';

/**
 * InventoryView – loads all data needed by Frontend/ADMIN/inventory.php.
 *
 * Replaces the raw PHP block at the top of that file.
 * Usage (at the very top of inventory.php):
 *
 *   require_once '../../Frontend/Core/InventoryView.php';
 *   $view = new InventoryView();
 */
class InventoryView extends View
{
    public int    $total;
    public int    $inStock;
    public int    $lowStock;
    public int    $outStock;
    public int    $expiringSoon;
    public int    $expired;

    public array  $items        = [];
    public array  $lowAlerts    = [];
    public array  $expiryAlerts = [];
    public array  $expiredItems = [];

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        $this->load();
    }

    private function load(): void
    {
        // ── Stats — single query ─────────────────────────────────
        $stats = $this->fetchOne(
            "SELECT
                COUNT(*) AS total,
                SUM(stock_qty > low_stock_threshold) AS in_stock,
                SUM(stock_qty > 0 AND stock_qty <= low_stock_threshold) AS low_stock,
                SUM(stock_qty = 0) AS out_stock,
                SUM(expiry_date IS NOT NULL
                    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                    AND expiry_date >= CURDATE()) AS expiring_soon,
                SUM(expiry_date IS NOT NULL AND expiry_date < CURDATE()) AS expired
             FROM ingredients"
        );

        $this->total        = (int) ($stats['total']         ?? 0);
        $this->inStock      = (int) ($stats['in_stock']      ?? 0);
        $this->lowStock     = (int) ($stats['low_stock']     ?? 0);
        $this->outStock     = (int) ($stats['out_stock']     ?? 0);
        $this->expiringSoon = (int) ($stats['expiring_soon'] ?? 0);
        $this->expired      = (int) ($stats['expired']       ?? 0);

        // ── All ingredients ──────────────────────────────────────
        $this->items = $this->fetchAll(
            'SELECT * FROM ingredients ORDER BY name ASC'
        );

        // ── Low-stock alerts (up to 8) ───────────────────────────
        $this->lowAlerts = $this->fetchAll(
            'SELECT name, stock_qty, unit
             FROM ingredients
             WHERE stock_qty <= low_stock_threshold
             ORDER BY stock_qty ASC LIMIT 8'
        );

        // ── Expiring soon (within 30 days, up to 10) ─────────────
        $this->expiryAlerts = $this->fetchAll(
            'SELECT name, stock_qty, unit, expiry_date,
                    DATEDIFF(expiry_date, CURDATE()) AS days_left
             FROM ingredients
             WHERE expiry_date IS NOT NULL
               AND expiry_date >= CURDATE()
               AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             ORDER BY expiry_date ASC LIMIT 10'
        );

        // ── Already expired ──────────────────────────────────────
        $this->expiredItems = $this->fetchAll(
            'SELECT name, stock_qty, unit, expiry_date
             FROM ingredients
             WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()
             ORDER BY expiry_date ASC'
        );
    }

    /** Format a quantity: strip trailing zeros, max 2 dp. */
    public static function fmtQty(mixed $val): string
    {
        return rtrim(rtrim(number_format((float) $val, 2, '.', ''), '0'), '.');
    }
}
