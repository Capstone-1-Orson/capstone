<?php
// Frontend/Core/VoidRefundView.php

require_once __DIR__ . '/View.php';

/**
 * VoidRefundView – data for Frontend/ADMIN/void_refund.php.
 *
 * Also handles: ?rt=1 → real-time JSON polling endpoint
 *
 * Usage:
 *   require_once '../../Frontend/Core/VoidRefundView.php';
 *   $view = new VoidRefundView();
 */
class VoidRefundView extends View
{
    public int   $voidedCount    = 0;
    public int   $refundedCount  = 0;
    public float $totalVoided    = 0.0;
    public float $totalRefunded  = 0.0;

    public array $voidedOrders   = [];
    public array $refundedOrders = [];
    public array $pendingOrders  = [];

    public bool $hasOrderItems   = false;
    private bool $hasOrderRefunds = false;

    /* True DB-wide max IDs — used to seed the JS polling cursor correctly,
       independent of what $pendingOrders / JOIN queries returned */
    public int $maxActiveOrderId  = 0;
    public int $maxVoidedId       = 0;
    public int $maxRefundedId     = 0;

    public function __construct()
    {
        parent::__construct();

        // Auth: check before dispatching AJAX
        Session::start(Session::ADMIN);
        if (!Session::get('user') || Session::get('position') !== 'admin') {
            if (isset($_GET['rt'])) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['error' => 'Unauthorized']);
                exit();
            }
            header('Location: ../../lockscreen.html');
            exit();
        }

        $this->hasOrderItems   = $this->tableExists('order_items');
        $this->hasOrderRefunds = $this->tableExists('order_refunds');

        if (isset($_GET['rt'])) { $this->ajaxRealtime(); /* exits */ }

        $this->load();
    }

    // ─────────────────────────────────────────────────────────────
    //  Real-time JSON API  (?rt=1)
    // ─────────────────────────────────────────────────────────────

    private function ajaxRealtime(): never
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $vc = $rc = $pc = 0;
        $tv = $tr = 0.0;

        $row = $this->fetchOne("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS amt FROM orders WHERE status='voided'");
        if ($row) { $vc = (int)$row['cnt']; $tv = (float)$row['amt']; }

        $row = $this->fetchOne("SELECT COUNT(*) AS cnt FROM orders WHERE status IN ('refunded','partial_refund')");
        if ($row) $rc = (int)$row['cnt'];

        if ($this->hasOrderRefunds) {
            $row = $this->fetchOne("SELECT COALESCE(SUM(refund_amt),0) AS amt FROM order_refunds");
            if ($row) $tr = (float)$row['amt'];
        }

        $row = $this->fetchOne("SELECT COUNT(*) AS cnt FROM orders WHERE status NOT IN ('voided','refunded','partial_refund')");
        if ($row) $pc = (int)$row['cnt'];

        $row      = $this->fetchOne("SELECT MAX(id) AS mid FROM orders WHERE status NOT IN ('voided','refunded','partial_refund')");
        $latestId = (int)($row['mid'] ?? 0);

        // New orders since client's last known id
        $newOrders = [];
        $since     = isset($_GET['since']) ? (int)$_GET['since'] : 0;
        if ($since >= 0) {
            // LEFT JOIN order_items so orders without items still appear
            $sql = $this->hasOrderItems
                ? "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                          COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                          COALESCE(GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', '),'—') AS items
                   FROM orders o
                   LEFT JOIN user u ON u.id = o.user_id
                   LEFT JOIN order_items oi ON oi.order_id = o.id
                   LEFT JOIN menu m ON m.id = oi.menu_id
                   WHERE o.status NOT IN ('voided','refunded','partial_refund') AND o.id > $since
                   GROUP BY o.id ORDER BY o.id DESC LIMIT 20"
                : "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                          COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name, '—' AS items
                   FROM orders o LEFT JOIN user u ON u.id = o.user_id
                   WHERE o.status NOT IN ('voided','refunded','partial_refund') AND o.id > $since
                   ORDER BY o.id DESC LIMIT 20";
            $newOrders = $this->fetchAll($sql);
        }

        // Orders recently voided/refunded (for client-side removal from Active tab)
        $removedIds = array_column(
            $this->fetchAll("SELECT id FROM orders WHERE status IN ('voided','refunded','partial_refund') AND id > " . max(0, $since)),
            'id'
        );
        $removedIds = array_map('intval', $removedIds);

        // ── New voided rows since client's last known voided id ──
        $sinceVoided = isset($_GET['sinceVoided']) ? (int)$_GET['sinceVoided'] : 0;
        $newVoided   = [];
        if ($sinceVoided >= 0) {
            $sql = $this->hasOrderItems
                ? "SELECT o.id, o.table_no, o.total_amt, o.created_at,
                          COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                          COALESCE(GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', '),'—') AS items,
                          COALESCE(SUM(oi.qty),0) AS total_qty
                   FROM orders o
                   LEFT JOIN user u ON u.id = o.user_id
                   LEFT JOIN order_items oi ON oi.order_id = o.id
                   LEFT JOIN menu m ON m.id = oi.menu_id
                   WHERE o.status = 'voided' AND o.id > $sinceVoided
                   GROUP BY o.id ORDER BY o.id DESC LIMIT 20"
                : "SELECT o.id, o.table_no, o.total_amt, o.created_at,
                          COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                          '—' AS items, 0 AS total_qty
                   FROM orders o LEFT JOIN user u ON u.id = o.user_id
                   WHERE o.status = 'voided' AND o.id > $sinceVoided
                   ORDER BY o.id DESC LIMIT 20";
            $newVoided = $this->fetchAll($sql);
        }

        // ── New refunded rows since client's last known refunded id ──
        $sinceRefunded = isset($_GET['sinceRefunded']) ? (int)$_GET['sinceRefunded'] : 0;
        $newRefunded   = [];
        if ($sinceRefunded >= 0 && $this->hasOrderItems && $this->hasOrderRefunds) {
            $newRefunded = $this->fetchAll(
                "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                        COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                        COALESCE(GROUP_CONCAT(DISTINCT m.name ORDER BY m.name SEPARATOR ', '),'—') AS items,
                        COALESCE(SUM(oi.qty),0) AS total_qty,
                        (SELECT SUM(r2.refund_amt) FROM order_refunds r2 WHERE r2.order_id = o.id) AS refund_total,
                        (SELECT r2.reason FROM order_refunds r2 WHERE r2.order_id = o.id ORDER BY r2.id DESC LIMIT 1) AS refund_reason,
                        (SELECT r2.created_by FROM order_refunds r2 WHERE r2.order_id = o.id ORDER BY r2.id DESC LIMIT 1) AS processed_by,
                        (SELECT r2.created_at FROM order_refunds r2 WHERE r2.order_id = o.id ORDER BY r2.id DESC LIMIT 1) AS refund_at
                 FROM orders o
                 LEFT JOIN user u ON u.id = o.user_id
                 LEFT JOIN order_items oi ON oi.order_id = o.id
                 LEFT JOIN menu m ON m.id = oi.menu_id
                 WHERE o.status IN ('refunded','partial_refund') AND o.id > $sinceRefunded
                 GROUP BY o.id ORDER BY o.id DESC LIMIT 20"
            );
        } elseif ($sinceRefunded >= 0) {
            $newRefunded = $this->fetchAll(
                "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                        COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                        '—' AS items, 0 AS total_qty, 0 AS refund_total,
                        '' AS refund_reason, '' AS processed_by, o.created_at AS refund_at
                 FROM orders o LEFT JOIN user u ON u.id = o.user_id
                 WHERE o.status IN ('refunded','partial_refund') AND o.id > $sinceRefunded
                 ORDER BY o.id DESC LIMIT 20"
            );
        }

        // Latest voided/refunded ids
        $rowV = $this->fetchOne("SELECT MAX(id) AS mid FROM orders WHERE status='voided'");
        $rowR = $this->fetchOne("SELECT MAX(id) AS mid FROM orders WHERE status IN ('refunded','partial_refund')");
        $latestVoidedId   = (int)($rowV['mid'] ?? 0);
        $latestRefundedId = (int)($rowR['mid'] ?? 0);

        echo json_encode([
            'voidedCount'      => $vc,    'refundedCount'    => $rc,    'pendingCount'     => $pc,
            'totalVoided'      => $tv,    'totalRefunded'    => $tr,    'latestOrderId'    => $latestId,
            'newOrders'        => $newOrders, 'removedIds'   => $removedIds,
            'newVoided'        => $newVoided, 'newRefunded'  => $newRefunded,
            'latestVoidedId'   => $latestVoidedId, 'latestRefundedId' => $latestRefundedId,
            '_debug' => [
                'since'          => $since,
                'sinceVoided'    => $sinceVoided,
                'sinceRefunded'  => $sinceRefunded,
                'latestId'       => $latestId,
                'newOrdersCount' => count($newOrders),
                'newOrderIds'    => array_column($newOrders, 'id'),
                'removedIds'     => $removedIds,
                'hasOrderItems'  => $this->hasOrderItems,
                'sql_used'       => $this->hasOrderItems ? 'WITH_order_items' : 'WITHOUT_order_items',
            ],
        ]);
        exit();
    }

    // ─────────────────────────────────────────────────────────────
    //  Page data  (LEFT JOINs so every order always appears)
    // ─────────────────────────────────────────────────────────────

    private function load(): void
    {
        // ── Stats ────────────────────────────────────────────────
        $row = $this->fetchOne("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS amt
                                FROM orders WHERE status='voided'");
        if ($row) { $this->voidedCount = (int)$row['cnt']; $this->totalVoided = (float)$row['amt']; }

        $row = $this->fetchOne("SELECT COUNT(*) AS cnt FROM orders
                                WHERE status IN ('refunded','partial_refund')");
        if ($row) $this->refundedCount = (int)$row['cnt'];

        if ($this->hasOrderRefunds) {
            $row = $this->fetchOne("SELECT COALESCE(SUM(refund_amt),0) AS amt FROM order_refunds");
            if ($row) $this->totalRefunded = (float)$row['amt'];
        }

        // ── Voided orders — LEFT JOIN so orders with no items still show ──
        $this->voidedOrders = $this->hasOrderItems
            ? $this->fetchAll(
                "SELECT o.id, o.table_no, o.total_amt, o.created_at,
                        COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                        COALESCE(GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', '),'—') AS items,
                        COALESCE(SUM(oi.qty),0) AS total_qty
                 FROM orders o
                 LEFT JOIN user u ON u.id = o.user_id
                 LEFT JOIN order_items oi ON oi.order_id = o.id
                 LEFT JOIN menu m ON m.id = oi.menu_id
                 WHERE o.status = 'voided'
                 GROUP BY o.id ORDER BY o.created_at DESC"
              )
            : $this->fetchAll(
                "SELECT o.id, o.table_no, o.total_amt, o.created_at,
                        COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                        '—' AS items, 0 AS total_qty
                 FROM orders o LEFT JOIN user u ON u.id = o.user_id
                 WHERE o.status = 'voided' ORDER BY o.created_at DESC"
              );

        // ── Refunded orders — LEFT JOIN so orders with no items still show ──
        $this->refundedOrders = ($this->hasOrderItems && $this->hasOrderRefunds)
            ? $this->fetchAll(
                "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                        COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                        COALESCE(GROUP_CONCAT(DISTINCT m.name ORDER BY m.name SEPARATOR ', '),'—') AS items,
                        COALESCE(SUM(oi.qty),0) AS total_qty,
                        (SELECT SUM(r2.refund_amt) FROM order_refunds r2 WHERE r2.order_id = o.id) AS refund_total,
                        (SELECT r2.reason FROM order_refunds r2 WHERE r2.order_id = o.id ORDER BY r2.id DESC LIMIT 1) AS refund_reason,
                        (SELECT r2.created_by FROM order_refunds r2 WHERE r2.order_id = o.id ORDER BY r2.id DESC LIMIT 1) AS processed_by,
                        (SELECT r2.created_at FROM order_refunds r2 WHERE r2.order_id = o.id ORDER BY r2.id DESC LIMIT 1) AS refund_at
                 FROM orders o
                 LEFT JOIN user u ON u.id = o.user_id
                 LEFT JOIN order_items oi ON oi.order_id = o.id
                 LEFT JOIN menu m ON m.id = oi.menu_id
                 WHERE o.status IN ('refunded','partial_refund')
                 GROUP BY o.id ORDER BY o.created_at DESC"
              )
            : $this->fetchAll(
                "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                        COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                        '—' AS items, 0 AS total_qty, 0 AS refund_total,
                        '' AS refund_reason, '' AS processed_by, o.created_at AS refund_at
                 FROM orders o LEFT JOIN user u ON u.id = o.user_id
                 WHERE o.status IN ('refunded','partial_refund') ORDER BY o.created_at DESC"
              );

        // ── Active orders — LEFT JOIN so ALL orders always appear ──
        $this->pendingOrders = $this->hasOrderItems
            ? $this->fetchAll(
                "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                        COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                        COALESCE(GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', '),'—') AS items,
                        COALESCE(SUM(oi.qty),0) AS total_qty,
                        COALESCE(GROUP_CONCAT(
                            CONCAT(oi.id,'|',m.id,'|',m.name,'|',oi.qty,'|',oi.unit_price)
                            ORDER BY oi.id SEPARATOR ';;'
                        ),'') AS item_details_raw
                 FROM orders o
                 LEFT JOIN user u ON u.id = o.user_id
                 LEFT JOIN order_items oi ON oi.order_id = o.id
                 LEFT JOIN menu m ON m.id = oi.menu_id
                 WHERE o.status NOT IN ('voided','refunded','partial_refund')
                 GROUP BY o.id ORDER BY o.created_at DESC LIMIT 200"
              )
            : $this->fetchAll(
                "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                        COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                        '—' AS items, 0 AS total_qty, '' AS item_details_raw
                 FROM orders o LEFT JOIN user u ON u.id = o.user_id
                 WHERE o.status NOT IN ('voided','refunded','partial_refund')
                 ORDER BY o.created_at DESC LIMIT 200"
              );

        $this->loadMaxIds();
    }

    private function tableExists(string $table): bool
    {
        $r = $this->db->query("SHOW TABLES LIKE '$table'");
        return $r && $r->num_rows > 0;
    }

    /* ── True DB-wide max IDs (not limited by JOIN / LIMIT) ── */
    private function loadMaxIds(): void
    {
        $r = $this->fetchOne("SELECT MAX(id) AS mid FROM orders
                              WHERE status NOT IN ('voided','refunded','partial_refund')");
        $this->maxActiveOrderId = (int)($r['mid'] ?? 0);

        $r = $this->fetchOne("SELECT MAX(id) AS mid FROM orders WHERE status = 'voided'");
        $this->maxVoidedId = (int)($r['mid'] ?? 0);

        $r = $this->fetchOne("SELECT MAX(id) AS mid FROM orders
                              WHERE status IN ('refunded','partial_refund')");
        $this->maxRefundedId = (int)($r['mid'] ?? 0);
    }
}