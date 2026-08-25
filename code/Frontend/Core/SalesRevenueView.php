<?php
// Frontend/Core/SalesRevenueView.php

require_once __DIR__ . '/View.php';

/**
 * SalesRevenueView – data for Frontend/ADMIN/sale_revenue.php.
 *
 * Also handles: ?ajax=topitems → JSON endpoint for real-time chart refresh.
 *
 * Usage:
 *   require_once '../../Frontend/Core/SalesRevenueView.php';
 *   $view = new SalesRevenueView();
 */
class SalesRevenueView extends View
{
    // ── Summary stats ────────────────────────────────────────────
    public float $totalRevenue   = 0.0;
    public int   $totalOrders    = 0;
    public float $totalRefundAmt = 0.0;
    public float $todayRevenue   = 0.0;
    public float $monthRevenue   = 0.0;
    public float $avgOrder       = 0.0;

    // ── Date filter ──────────────────────────────────────────────
    public string $selectedRange = 'alltime';
    public string $dateFrom      = '';
    public string $dateTo        = '';

    // ── Chart data ───────────────────────────────────────────────
    public array  $monthLabels      = [];
    public array  $monthData        = [];
    public array  $forecastData     = [];
    public array  $forecastLabels   = [];
    public array  $dayLabels        = [];
    public array  $dayData          = [];
    public float  $projNextMonth    = 0.0;
    public float  $projGrowthPct    = 0.0;

    // ── Tables ───────────────────────────────────────────────────
    public array  $topItems       = [];
    public array  $latestOrders   = [];
    public array  $catRevenue     = [];
    public float  $maxCatRev      = 1.0;

    // ── Pre-encoded JSON strings for <script> tags ───────────────
    public string $monthLabelsJson    = '[]';
    public string $monthDataJson      = '[]';
    public string $forecastDataJson   = '[]';
    public string $forecastLabelsJson = '[]';
    public string $dayLabelsJson      = '[]';
    public string $dayDataJson        = '[]';
    public string $donutLabels        = '[]';
    public string $donutData          = '[]';

    public bool   $hasOrderItems = false;
    private string $VALID         = "status NOT IN ('voided','refunded','partial_refund')";
    private string $VALIDO        = "o.status NOT IN ('voided','refunded','partial_refund')";
    private string $df            = '1=1';   // date filter (no table alias)
    private string $dfO           = '1=1';   // date filter (with 'o.' alias)

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        $this->hasOrderItems = $this->tableExists('order_items');
        $this->buildDateFilter();

        if (isset($_GET['ajax']) && $_GET['ajax'] === 'topitems') {
            $this->ajaxTopItems(); // exits
        }

        $this->load();
    }

    // ─────────────────────────────────────────────────────────────
    //  AJAX top-items  (?ajax=topitems)
    // ─────────────────────────────────────────────────────────────

    private function ajaxTopItems(): never
    {
        $items = [];
        $VALID = $this->VALID;
        $dfO   = $this->dfO;
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
        $VALIDO = $this->VALIDO;
        $df    = $this->df;
        $dfO   = $this->dfO;

        // Summary stats
        $row = $this->fetchOne("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE $VALID AND $df");
        if ($row) { $this->totalOrders = (int)$row['cnt']; $this->totalRevenue = (float)$row['rev']; }

        $row = $this->fetchOne("SELECT COALESCE(SUM(refund_amt),0) AS amt FROM order_refunds WHERE $df");
        if ($row) $this->totalRefundAmt = (float)$row['amt'];

        $row = $this->fetchOne("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");
        if ($row) $this->todayRevenue = (float)$row['rev'];

        $row = $this->fetchOne("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) AND $VALID");
        if ($row) $this->monthRevenue = (float)$row['rev'];

        $this->avgOrder = $this->totalOrders > 0 ? $this->totalRevenue / $this->totalOrders : 0;

        // Monthly chart – last 6 months (single query, zero-filled)
        $monthSlots = [];
        for ($i = 5; $i >= 0; $i--) {
            $key                 = date('Y-m', strtotime("-$i months"));
            $monthSlots[$key]    = 0.0;
            $this->monthLabels[] = date('M Y', strtotime("-$i months"));
        }
        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, COALESCE(SUM(total_amt),0) AS rev
             FROM orders
             WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-01')
               AND $VALID GROUP BY ym"
        );
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (isset($monthSlots[$row['ym']])) {
                $monthSlots[$row['ym']] = (float) $row['rev'];
            }
        }
        $stmt->close();
        $this->monthData = array_values($monthSlots);

        // Forecast (linear regression)
        $reg = $this->linearRegression($this->monthData);
        $n   = count($this->monthData);
        for ($f = 1; $f <= 3; $f++) {
            $this->forecastData[]   = round(max(0, $reg['intercept'] + $reg['slope'] * ($n - 1 + $f)), 2);
            $this->forecastLabels[] = date('M Y', strtotime("+$f months"));
        }
        $this->projNextMonth = $this->forecastData[0];
        $lastActual          = end($this->monthData);
        $this->projGrowthPct = $lastActual > 0
            ? (($this->projNextMonth - $lastActual) / $lastActual) * 100 : 0.0;

        // Daily – last 7 days (single query, zero-filled)
        $daySlots = [];
        for ($i = 6; $i >= 0; $i--) {
            $key              = date('Y-m-d', strtotime("-$i days"));
            $daySlots[$key]   = 0.0;
            $this->dayLabels[]= date('M d', strtotime("-$i days"));
        }
        $stmt = $this->db->prepare(
            "SELECT DATE(created_at) AS d, COALESCE(SUM(total_amt),0) AS rev
             FROM orders
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND $VALID GROUP BY d"
        );
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (isset($daySlots[$row['d']])) {
                $daySlots[$row['d']] = (float) $row['rev'];
            }
        }
        $stmt->close();
        $this->dayData = array_values($daySlots);

        // Top items
        if ($this->hasOrderItems) {
            $this->topItems = $this->fetchAll(
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

        // Latest orders
        if ($this->hasOrderItems) {
            $this->latestOrders = $this->fetchAll(
                "SELECT o.id, o.created_at, o.table_no, o.total_amt,
                        COALESCE(o.discount_amt,0) AS discount_amt,
                        COALESCE(o.discount_type,'') AS discount_type,
                        COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                        GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items,
                        GROUP_CONCAT(
                            CONCAT(m.name,'|',oi.qty,'|',COALESCE(oi.addons,''),'|',
                                CASE
                                    WHEN oi.removed_ingredient_names IS NOT NULL
                                         AND oi.removed_ingredient_names != '[]'
                                         AND oi.removed_ingredient_names != ''
                                    THEN oi.removed_ingredient_names
                                    WHEN oi.removed_ingredient_ids IS NOT NULL
                                         AND oi.removed_ingredient_ids != '[]'
                                         AND oi.removed_ingredient_ids != ''
                                    THEN (
                                        SELECT CONCAT('[',GROUP_CONCAT(JSON_QUOTE(i2.name) ORDER BY i2.name),']')
                                        FROM ingredients i2
                                        WHERE JSON_SEARCH(oi.removed_ingredient_ids,'one',CAST(i2.id AS CHAR)) IS NOT NULL
                                    )
                                    ELSE ''
                                END
                            )
                            ORDER BY m.name SEPARATOR ';;'
                        ) AS item_details
                 FROM orders o
                 LEFT JOIN user u ON u.id = o.user_id
                 JOIN order_items oi ON oi.order_id = o.id
                 JOIN menu m ON m.id = oi.menu_id
                 WHERE $VALID AND $dfO
                 GROUP BY o.id ORDER BY o.created_at DESC LIMIT 10"
            );
        } else {
            $this->latestOrders = $this->fetchAll(
                "SELECT o.id, o.created_at, o.table_no, o.total_amt,
                        COALESCE(o.discount_amt,0) AS discount_amt,
                        COALESCE(o.discount_type,'') AS discount_type,
                        COALESCE(CONCAT(u.firstname,' ',u.lastname),'N/A') AS cashier_name,
                        '—' AS items, '' AS item_details
                 FROM orders o LEFT JOIN user u ON u.id = o.user_id
                 WHERE $VALID AND $dfO ORDER BY o.created_at DESC LIMIT 10"
            );
        }

        // Category revenue
        if ($this->hasOrderItems) {
            $this->catRevenue = $this->fetchAll(
                "SELECT m.category, SUM(oi.qty * oi.unit_price) AS revenue
                 FROM order_items oi
                 JOIN menu m ON m.id = oi.menu_id
                 JOIN orders o ON o.id = oi.order_id
                 WHERE $VALID AND $dfO
                 GROUP BY m.category ORDER BY revenue DESC LIMIT 5"
            );
        }
        $this->maxCatRev = !empty($this->catRevenue) ? (float)$this->catRevenue[0]['revenue'] : 1.0;

        // Pre-encode JSON for inline scripts
        $this->monthLabelsJson    = json_encode($this->monthLabels);
        $this->monthDataJson      = json_encode($this->monthData);
        $this->forecastDataJson   = json_encode($this->forecastData);
        $this->forecastLabelsJson = json_encode($this->forecastLabels);
        $this->dayLabelsJson      = json_encode($this->dayLabels);
        $this->dayDataJson        = json_encode($this->dayData);
        $this->donutLabels        = json_encode(array_column($this->topItems, 'name'));
        $this->donutData          = json_encode(array_map(fn($i) => (float)$i['qty_sold'], $this->topItems));
    }

    // ─────────────────────────────────────────────────────────────
    //  Private helpers
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
            $this->df  = "DATE(created_at)=CURDATE()";
            $this->dfO = "DATE(o.created_at)=CURDATE()";
        } elseif ($range === 'custom') {
            $from      = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'] ?? '') ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
            $to        = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to']   ?? '') ? $_GET['date_to']   : date('Y-m-d');
            $this->dateFrom = $from;
            $this->dateTo   = $to;
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
            $days      = (int) $presets[$range];
            $this->df  = "created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
            $this->dfO = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
        }
    }

    private function tableExists(string $table): bool
    {
        $r = $this->db->query("SHOW TABLES LIKE '$table'");
        return $r && $r->num_rows > 0;
    }

    private function linearRegression(array $y): array
    {
        $n = count($y);
        if ($n < 2) return ['slope' => 0, 'intercept' => 0];
        $sumX = $sumY = $sumXY = $sumX2 = 0;
        for ($i = 0; $i < $n; $i++) {
            $sumX  += $i;        $sumY  += $y[$i];
            $sumXY += $i*$y[$i]; $sumX2 += $i*$i;
        }
        $denom = $n * $sumX2 - $sumX * $sumX;
        if ($denom == 0) return ['slope' => 0, 'intercept' => $sumY / $n];
        return [
            'slope'     => ($n * $sumXY - $sumX * $sumY) / $denom,
            'intercept' => ($sumY - (($n * $sumXY - $sumX * $sumY) / $denom) * $sumX) / $n,
        ];
    }
}
