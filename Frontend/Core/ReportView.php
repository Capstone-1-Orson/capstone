<?php
// Frontend/Core/ReportView.php

require_once __DIR__ . '/View.php';

/**
 * ReportView – data for Frontend/ADMIN/report.php.
 *
 * Also handles:
 *   ?sse=1              → SSE stream (live order ticker)
 *   ?ajax=inventory     → JSON inventory table refresh
 *   ?ajax=topitems      → JSON top-items refresh
 *
 * Usage:
 *   require_once '../../Frontend/Core/ReportView.php';
 *   $view = new ReportView();
 */
class ReportView extends View
{
    // ── Summary stats ────────────────────────────────────────────
    public int    $totalOrders    = 0;
    public float  $totalRevenue   = 0.0;
    public int    $totalRefunds   = 0;
    public float  $totalRefundAmt = 0.0;
    public int    $totalTables    = 0;
    public string $topItem        = 'No orders yet';

    // ── Date filter ──────────────────────────────────────────────
    public string $selectedRange  = 'alltime';
    public string $dateFrom       = '';
    public string $dateTo         = '';

    // ── Chart ────────────────────────────────────────────────────
    public array  $chartLabels    = [];
    public array  $chartData      = [];
    public string $chartLabelsJson= '[]';
    public string $chartDataJson  = '[]';

    // ── Tables ───────────────────────────────────────────────────
    public array  $inventoryRows  = [];
    public array  $orderRows      = [];
    public array  $catSales       = [];
    public array  $topItems       = [];

    public bool   $hasOrderItems = false;
    private string $VALID  = "status NOT IN ('voided','refunded','partial_refund')";
    private string $df     = '1=1';
    private string $dfO    = '1=1';

    public function __construct()
    {
        parent::__construct();

        // SSE needs auth but no session write
        Session::start(Session::ADMIN);
        if (!Session::get('user') || Session::get('position') !== 'admin') {
            if (isset($_GET['sse'])) { http_response_code(403); exit; }
            header('Location: ../../lockscreen.html');
            exit();
        }

        $this->hasOrderItems = $this->tableExists('order_items');
        $this->buildDateFilter();

        if (isset($_GET['sse']))                                   { $this->streamSse();       }
        if (isset($_GET['ajax']) && $_GET['ajax'] === 'inventory') { $this->ajaxInventory();  }
        if (isset($_GET['ajax']) && $_GET['ajax'] === 'topitems')  { $this->ajaxTopItems();   }

        $this->load();
    }

    // ─────────────────────────────────────────────────────────────
    //  SSE stream (?sse=1)
    // ─────────────────────────────────────────────────────────────

    private function streamSse(): never
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        @ob_end_clean();
        ob_implicit_flush(true);

        $VALID = $this->VALID;
        $dfO   = $this->dfO;
        $data  = [];

        $row = $this->fetchOne("SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");
        $data['ordersToday']  = (int)($row['c'] ?? 0);

        $row = $this->fetchOne("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");
        $data['dailyRevenue'] = (float)($row['rev'] ?? 0);

        $row = $this->fetchOne("SELECT MAX(id) AS mid FROM orders");
        $data['latestOrderId'] = (int)($row['mid'] ?? 0);

        $lo = $this->fetchOne("SELECT id, table_no, total_amt, created_at FROM orders ORDER BY id DESC LIMIT 1");
        $data['latestOrder'] = $lo ? [
            'id'        => (int)   $lo['id'],
            'table_no'  =>         $lo['table_no'],
            'total_amt' => (float) $lo['total_amt'],
            'created_at'=>         $lo['created_at'],
        ] : null;

        $recent = [];
        if ($this->hasOrderItems) {
            $recent = $this->fetchAll(
                "SELECT o.id AS order_id, o.created_at, o.table_no, o.status, o.total_amt,
                        COALESCE(o.discount_amt,0) AS discount_amt,
                        COALESCE(o.discount_type,'') AS discount_type,
                        COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                        GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items,
                        SUM(oi.qty) AS total_qty,
                        GROUP_CONCAT(
                            CONCAT(m.name,'|',oi.qty,'|',COALESCE(oi.addons,''),'|',
                                CASE
                                    WHEN oi.removed_ingredient_names IS NOT NULL
                                         AND oi.removed_ingredient_names != '[]'
                                         AND oi.removed_ingredient_names != ''
                                    THEN oi.removed_ingredient_names ELSE '' END
                            ) ORDER BY m.name SEPARATOR ';;'
                        ) AS item_details
                 FROM orders o
                 LEFT JOIN user u ON u.id = o.user_id
                 JOIN order_items oi ON oi.order_id = o.id
                 JOIN menu m ON m.id = oi.menu_id
                 WHERE $dfO GROUP BY o.id ORDER BY o.created_at DESC LIMIT 50"
            );
        }
        $data['recentOrders'] = $recent;

        echo "retry: 4000\n";
        echo "event: stats\n";
        echo "data: " . json_encode($data) . "\n\n";
        if (ob_get_level()) ob_flush();
        flush();
        exit();
    }

    // ─────────────────────────────────────────────────────────────
    //  AJAX endpoints
    // ─────────────────────────────────────────────────────────────

    private function ajaxInventory(): never
    {
        $rows = $this->fetchAll("SELECT name, unit, stock_qty, low_stock_threshold FROM ingredients ORDER BY name");
        header('Content-Type: application/json');
        echo json_encode(['rows' => $rows]);
        exit();
    }

    private function ajaxTopItems(): never
    {
        $VALID = $this->VALID;
        $dfO   = $this->dfO;
        $items = [];
        if ($this->hasOrderItems) {
            $items = $this->fetchAll(
                "SELECT m.name, m.category, m.price,
                        SUM(oi.qty) AS qty_sold,
                        SUM(oi.qty * oi.unit_price) AS revenue
                 FROM order_items oi
                 JOIN menu m ON m.id = oi.menu_id
                 JOIN orders o ON o.id = oi.order_id
                 WHERE $VALID AND $dfO
                 GROUP BY oi.menu_id ORDER BY qty_sold DESC LIMIT 10"
            );
        }
        header('Content-Type: application/json');
        echo json_encode(['items' => $items]);
        exit();
    }

    // ─────────────────────────────────────────────────────────────
    //  Page data
    // ─────────────────────────────────────────────────────────────

    private function load(): void
    {
        $VALID = $this->VALID;
        $df    = $this->df;
        $dfO   = $this->dfO;

        // Summary
        $row = $this->fetchOne("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE $VALID AND $df");
        if ($row) { $this->totalOrders = (int)$row['cnt']; $this->totalRevenue = (float)$row['rev']; }

        $row = $this->fetchOne("SELECT COUNT(*) AS cnt, COALESCE(SUM(refund_amt),0) AS amt FROM order_refunds WHERE $df");
        if ($row) { $this->totalRefunds = (int)$row['cnt']; $this->totalRefundAmt = (float)$row['amt']; }

        $row = $this->fetchOne("SELECT COUNT(DISTINCT table_no) AS cnt FROM orders WHERE $VALID AND $df");
        if ($row) $this->totalTables = (int)$row['cnt'];

        if ($this->hasOrderItems) {
            $row = $this->fetchOne(
                "SELECT m.name, SUM(oi.qty) AS total_qty
                 FROM order_items oi JOIN menu m ON m.id=oi.menu_id
                 JOIN orders o ON o.id=oi.order_id
                 WHERE $VALID AND $dfO GROUP BY oi.menu_id ORDER BY total_qty DESC LIMIT 1"
            );
            if ($row) {
                $this->topItem = htmlspecialchars($row['name']) . ' (' . (int)$row['total_qty'] . ' sold)';
            }
        }

        // Sales chart (last 7 days, per-day prepared statements)
        for ($i = 6; $i >= 0; $i--) {
            $date              = date('Y-m-d', strtotime("-$i days"));
            $this->chartLabels[] = date('M d', strtotime("-$i days"));
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at)=? AND $VALID");
            $stmt->bind_param('s', $date);
            $stmt->execute();
            $this->chartData[] = (float) $stmt->get_result()->fetch_assoc()['rev'];
            $stmt->close();
        }

        // Inventory
        $this->inventoryRows = $this->fetchAll("SELECT name, unit, stock_qty, low_stock_threshold FROM ingredients ORDER BY name");

        // Orders
        $this->orderRows = $this->hasOrderItems
            ? $this->fetchAll(
                "SELECT o.id AS order_id, o.created_at, o.table_no, o.status, o.total_amt,
                        COALESCE(o.discount_amt,0) AS discount_amt,
                        COALESCE(o.discount_type,'') AS discount_type,
                        COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                        GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items,
                        SUM(oi.qty) AS total_qty,
                        GROUP_CONCAT(
                            CONCAT(m.name,'|',oi.qty,'|',COALESCE(oi.addons,''),'|',
                                CASE
                                    WHEN oi.removed_ingredient_names IS NOT NULL AND oi.removed_ingredient_names != '[]' AND oi.removed_ingredient_names != ''
                                    THEN oi.removed_ingredient_names
                                    WHEN oi.removed_ingredient_ids IS NOT NULL AND oi.removed_ingredient_ids != '[]' AND oi.removed_ingredient_ids != ''
                                    THEN (SELECT CONCAT('[',GROUP_CONCAT(JSON_QUOTE(i2.name) ORDER BY i2.name),']') FROM ingredients i2 WHERE JSON_SEARCH(oi.removed_ingredient_ids,'one',CAST(i2.id AS CHAR)) IS NOT NULL)
                                    ELSE ''
                                END
                            ) ORDER BY m.name SEPARATOR ';;'
                        ) AS item_details
                 FROM orders o
                 LEFT JOIN user u ON u.id = o.user_id
                 JOIN order_items oi ON oi.order_id = o.id
                 JOIN menu m ON m.id = oi.menu_id
                 WHERE $dfO GROUP BY o.id ORDER BY o.created_at DESC LIMIT 500"
              )
            : $this->fetchAll(
                "SELECT o.id AS order_id, o.created_at, o.table_no, o.status, o.total_amt,
                        COALESCE(o.discount_amt,0) AS discount_amt,
                        COALESCE(o.discount_type,'') AS discount_type,
                        COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                        '—' AS items, 0 AS total_qty, '' AS item_details
                 FROM orders o LEFT JOIN user u ON u.id=o.user_id
                 WHERE $dfO ORDER BY o.created_at DESC LIMIT 500"
              );

        // Category sales
        if ($this->hasOrderItems) {
            $this->catSales = $this->fetchAll(
                "SELECT m.category, SUM(oi.qty * oi.unit_price) AS revenue
                 FROM order_items oi
                 JOIN menu m ON m.id=oi.menu_id
                 JOIN orders o ON o.id=oi.order_id
                 WHERE $VALID AND $dfO GROUP BY m.category ORDER BY revenue DESC"
            );
        }

        // Top items
        if ($this->hasOrderItems) {
            $this->topItems = $this->fetchAll(
                "SELECT m.name, m.category, m.price,
                        SUM(oi.qty) AS qty_sold,
                        SUM(oi.qty * oi.unit_price) AS revenue
                 FROM order_items oi
                 JOIN menu m ON m.id=oi.menu_id
                 JOIN orders o ON o.id=oi.order_id
                 WHERE $VALID AND $dfO GROUP BY oi.menu_id ORDER BY qty_sold DESC LIMIT 10"
            );
        }

        $this->chartLabelsJson = json_encode($this->chartLabels);
        $this->chartDataJson   = json_encode($this->chartData);
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    private function buildDateFilter(): void
    {
        $presets = [
            'today'=>0,'7days'=>7,'30days'=>30,'3months'=>90,
            '12months'=>365,'mtd'=>-1,'ytd'=>-1,'alltime'=>-1,'custom'=>-1,
        ];
        $range = isset($_GET['range']) && array_key_exists($_GET['range'], $presets)
            ? $_GET['range'] : 'alltime';
        $this->selectedRange = $range;

        if ($range === 'today') {
            $this->df = $this->dfO = "DATE(created_at)=CURDATE()";
            $this->dfO = "DATE(o.created_at)=CURDATE()";
        } elseif ($range === 'custom') {
            $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'] ?? '') ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
            $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to']   ?? '') ? $_GET['date_to']   : date('Y-m-d');
            $this->dateFrom = $from; $this->dateTo = $to;
            $this->df  = "DATE(created_at) BETWEEN '$from' AND '$to'";
            $this->dfO = "DATE(o.created_at) BETWEEN '$from' AND '$to'";
        } elseif ($range === 'mtd') {
            $this->df  = "YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())";
            $this->dfO = "YEAR(o.created_at)=YEAR(CURDATE()) AND MONTH(o.created_at)=MONTH(CURDATE())";
        } elseif ($range === 'ytd') {
            $this->df  = "YEAR(created_at)=YEAR(CURDATE())";
            $this->dfO = "YEAR(o.created_at)=YEAR(CURDATE())";
        } elseif ($range === 'alltime') {
            $this->df = $this->dfO = '1=1';
        } else {
            $days = (int)$presets[$range];
            $this->df  = "created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
            $this->dfO = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
        }
    }

    private function tableExists(string $table): bool
    {
        $r = $this->db->query("SHOW TABLES LIKE '$table'");
        return $r && $r->num_rows > 0;
    }
}
