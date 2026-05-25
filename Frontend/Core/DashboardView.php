<?php
// Frontend/Core/DashboardView.php

require_once __DIR__ . '/View.php';

/**
 * DashboardView – all data for Frontend/ADMIN/index2.php.
 *
 * Also handles:
 *   ?sse=1   → Server-Sent Events stream (live dashboard stats)
 *
 * Usage at top of index2.php:
 *   require_once '../../Frontend/Core/DashboardView.php';
 *   $view = new DashboardView();
 */
class DashboardView extends View
{
    // ── Info-box stats ───────────────────────────────────────────
    public int   $dailyCustomers   = 0;
    public float $totalRevenue     = 0.0;
    public int   $ordersToday      = 0;
    public int   $staffCount       = 0;

    // ── Right-panel stats ────────────────────────────────────────
    public int   $dailyItemsSold   = 0;
    public float $dailyRevenue     = 0.0;
    public int   $lowStockCount    = 0;
    public int   $expiredCount     = 0;
    public int   $expiringSoonCount= 0;

    // ── Monthly summary ──────────────────────────────────────────
    public float $thisMonthRev     = 0.0;
    public float $lastMonthRev     = 0.0;
    public float $revChange        = 0.0;
    public int   $totalOrders      = 0;

    // ── Chart data ───────────────────────────────────────────────
    public array  $chartLabels    = [];
    public array  $chartData      = [];
    public array  $forecastData   = [];
    public array  $forecastLabels = [];
    public float  $projNextMonth  = 0.0;
    public float  $projGrowthPct  = 0.0;
    public float  $avgMonthRev    = 0.0;

    // ── Category revenue ─────────────────────────────────────────
    public array  $catRevenue     = [];
    public float  $maxCatRev      = 1.0;

    // ── Lists ────────────────────────────────────────────────────
    public array  $expiredNames   = [];
    public array  $newMenuItems   = [];
    public array  $staffList      = [];
    public array  $recentOrders   = [];

    // ── Date filter (from GET) ───────────────────────────────────
    public string $selectedRange  = '7days';
    public string $dateFrom       = '';
    public string $dateTo         = '';

    // ── JSON helpers for inline <script> tags ────────────────────
    public string $chartLabelsJson = '[]';
    public string $chartDataJson   = '[]';
    public string $revTrend        = '0%';
    public string $revTrendClass   = 'text-success';
    public string $revTrendIcon    = 'fa-caret-up';

    public bool $hasOrderItems = false;

    public function __construct()
    {
        parent::__construct();

        // SSE must fire before session is written, but after auth
        Session::start(Session::ADMIN);
        if (!Session::get('user') || Session::get('position') !== 'admin') {
            if (isset($_GET['sse'])) { http_response_code(403); exit; }
            header('Location: ../../lockscreen.html');
            exit();
        }

        if (isset($_GET['sse']))    { $this->streamSse();    /* exits */ }
        if (isset($_GET['upload'])) { $this->handleUpload(); /* exits */ }
        if (isset($_GET['ajax']) && $_GET['ajax'] === 'topitems') { $this->ajaxTopItems(); /* exits */ }

        $this->hasOrderItems = $this->tableExists('order_items');
        $this->buildDateFilter();
        $this->load();
    }

    // ─────────────────────────────────────────────────────────────
    //  SSE stream
    // ─────────────────────────────────────────────────────────────

    private function streamSse(): never
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        @ob_end_clean();
        ob_implicit_flush(true);

        $VALID         = "status NOT IN ('voided','refunded','partial_refund')";
        $hasOrderItems = $this->tableExists('order_items');
        $data          = [];

        $data['dailyCustomers'] = (int) ($this->fetchOne("SELECT COUNT(DISTINCT table_no) AS c FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID")['c'] ?? 0);
        $data['totalRevenue']   = (float)($this->fetchOne("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE $VALID")['rev'] ?? 0);
        $data['ordersToday']    = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID")['c'] ?? 0);
        $data['staffCount']     = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM user WHERE position='staff'")['c'] ?? 0);

        if ($hasOrderItems) {
            $data['dailyItemsSold'] = (int)($this->fetchOne("SELECT COALESCE(SUM(oi.qty),0) AS c FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE DATE(o.created_at)=CURDATE() AND $VALID")['c'] ?? 0);
        } else { $data['dailyItemsSold'] = 0; }

        $data['dailyRevenue']      = (float)($this->fetchOne("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID")['rev'] ?? 0);
        $data['lowStockCount']     = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM ingredients WHERE stock_qty <= low_stock_threshold")['c'] ?? 0);
        $data['expiredCount']      = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM ingredients WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()")['c'] ?? 0);
        $data['expiringSoonCount'] = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM ingredients WHERE expiry_date IS NOT NULL AND expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)")['c'] ?? 0);
        $data['thisMonthRev']      = (float)($this->fetchOne("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) AND $VALID")['rev'] ?? 0);
        $data['lastMonthRev']      = (float)($this->fetchOne("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()-INTERVAL 1 MONTH) AND YEAR(created_at)=YEAR(CURDATE()-INTERVAL 1 MONTH) AND $VALID")['rev'] ?? 0);
        $data['totalOrders']       = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM orders WHERE $VALID")['c'] ?? 0);
        $data['latestOrderId']     = (int) ($this->fetchOne("SELECT MAX(id) AS max_id FROM orders")['max_id'] ?? 0);

        $lo = $this->fetchOne("SELECT id, table_no, total_amt, created_at FROM orders ORDER BY id DESC LIMIT 1");
        $data['latestOrder'] = $lo ? [
            'id'         => (int)   $lo['id'],
            'table_no'   =>         $lo['table_no'],
            'total_amt'  => (float) $lo['total_amt'],
            'created_at' =>         $lo['created_at'],
        ] : null;

        $invRows = $this->fetchAll(
            "SELECT id, name, unit, stock_qty, low_stock_threshold,
                    COALESCE(expiry_date,'') AS expiry_date,
                    CASE
                      WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 'expired'
                      WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'soon'
                      WHEN stock_qty <= low_stock_threshold THEN 'low'
                      ELSE 'ok'
                    END AS health
             FROM ingredients ORDER BY name"
        );
        $data['inventory'] = $invRows;

        $recentOrders = [];
        if ($hasOrderItems) {
            $recentOrders = $this->fetchAll(
                "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                        COALESCE(o.discount_amt,0) AS discount_amt,
                        COALESCE(o.discount_type,'') AS discount_type,
                        GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items,
                        NULLIF(TRIM(CONCAT_WS(' ', u.firstname, u.lastname)), '') AS cashier_name,
                        GROUP_CONCAT(
                            CONCAT(m.name,'|',oi.qty,'|',COALESCE(oi.addons,''),'|',
                                CASE
                                    WHEN oi.removed_ingredient_names IS NOT NULL AND oi.removed_ingredient_names != '[]' AND oi.removed_ingredient_names != ''
                                    THEN oi.removed_ingredient_names
                                    WHEN oi.removed_ingredient_ids IS NOT NULL AND oi.removed_ingredient_ids != '[]' AND oi.removed_ingredient_ids != ''
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
                 JOIN order_items oi ON oi.order_id = o.id
                 JOIN menu m ON m.id = oi.menu_id
                 LEFT JOIN user u ON u.id = o.user_id
                 WHERE $VALID AND DATE(o.created_at) = CURDATE()
                 GROUP BY o.id ORDER BY o.created_at DESC LIMIT 50"
            );
        }
        $data['recentOrders']   = $recentOrders;
        $data['menuAvailable']  = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM menu WHERE is_available=1")['c'] ?? 0);
        $data['menuTotal']      = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM menu")['c'] ?? 0);
        $data['suppliersCount'] = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM suppliers")['c'] ?? 0);
        $data['voidedCount']    = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM orders WHERE status='voided'")['c'] ?? 0);
        $data['refundedCount']  = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM orders WHERE status IN ('refunded','partial_refund')")['c'] ?? 0);

        echo "retry: 4000\n";
        echo "event: stats\n";
        echo "data: " . json_encode($data) . "\n\n";
        if (ob_get_level()) ob_flush();
        flush();
        exit();
    }

    // ─────────────────────────────────────────────────────────────
    //  Real-time upload handler  (?upload=1, POST)
    // ─────────────────────────────────────────────────────────────

    private function handleUpload(): never
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-cache');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'POST required.']);
            exit();
        }

        $type = $_POST['upload_type'] ?? '';
        if (!in_array($type, ['menu_image', 'inventory_csv', 'staff_photo'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid upload type.']);
            exit();
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errMap = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'Upload blocked by PHP extension.',
            ];
            $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
            echo json_encode(['success' => false, 'message' => $errMap[$code] ?? 'Upload error.']);
            exit();
        }

        $file     = $_FILES['file'];
        $origName = basename($file['name']);
        $tmpPath  = $file['tmp_name'];
        $sizeMB   = $file['size'] / 1_048_576;
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        /* Per-type validation & destination */
        $baseDir = dirname(__DIR__, 2); // adjust to your project root as needed

        switch ($type) {
            case 'menu_image':
                $allowed = ['jpg','jpeg','png','webp','gif'];
                $maxMB   = 10;
                $destDir = $baseDir . '/Frontend/dist/img/menu/';
                $label   = 'menu image';
                break;
            case 'staff_photo':
                $allowed = ['jpg','jpeg','png','webp'];
                $maxMB   = 5;
                $destDir = $baseDir . '/Frontend/dist/img/staff/';
                $label   = 'staff photo';
                break;
            case 'inventory_csv':
                $allowed = ['csv','txt'];
                $maxMB   = 5;
                $destDir = $baseDir . '/Backend/uploads/inventory/';
                $label   = 'inventory CSV';
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Unknown type.']);
                exit();
        }

        if (!in_array($ext, $allowed, true)) {
            echo json_encode(['success' => false,
                'message' => "Invalid file type .$ext. Allowed: " . implode(', ', $allowed)]);
            exit();
        }
        if ($sizeMB > $maxMB) {
            echo json_encode(['success' => false,
                'message' => "File too large (" . number_format($sizeMB, 1) . " MB). Max {$maxMB} MB."]);
            exit();
        }

        /* Extra MIME check for images */
        if (in_array($type, ['menu_image','staff_photo'], true)) {
            $mime = mime_content_type($tmpPath);
            if (!str_starts_with($mime, 'image/')) {
                echo json_encode(['success' => false, 'message' => "File does not appear to be an image ($mime)."]);
                exit();
            }
        }

        /* Ensure destination directory exists */
        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0755, true)) {
                echo json_encode(['success' => false, 'message' => 'Could not create upload directory.']);
                exit();
            }
        }

        /* Save with a unique name to prevent overwrites */
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
        $destName = date('Ymd_His') . '_' . $safeName . '.' . $ext;
        $destPath = $destDir . $destName;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
            exit();
        }

        /* Optional: for inventory CSV, insert/update ingredients table */
        $extra = '';
        if ($type === 'inventory_csv') {
            $imported = $this->importInventoryCsv($destPath);
            $extra = $imported >= 0
                ? " Imported $imported ingredient row(s)."
                : ' (CSV saved but DB import skipped — check format.)';
        }

        echo json_encode([
            'success'  => true,
            'message'  => ucfirst($label) . ' uploaded successfully.' . $extra,
            'filename' => $destName,
        ]);
        exit();
    }

    /**
     * Minimal CSV import: expects header row with at least "name" column.
     * Optional columns: stock_qty, unit, low_stock_threshold, expiry_date.
     * Returns number of rows imported, or -1 on failure.
     */
    private function importInventoryCsv(string $path): int
    {
        $handle = @fopen($path, 'r');
        if (!$handle) return -1;

        $header = fgetcsv($handle);
        if (!$header) { fclose($handle); return -1; }
        $header = array_map('strtolower', array_map('trim', $header));
        $nameIdx = array_search('name', $header);
        if ($nameIdx === false) { fclose($handle); return -1; }

        $qtyIdx     = array_search('stock_qty',           $header);
        $unitIdx    = array_search('unit',                 $header);
        $threshIdx  = array_search('low_stock_threshold',  $header);
        $expiryIdx  = array_search('expiry_date',          $header);

        $imported = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $name = trim($row[$nameIdx] ?? '');
            if ($name === '') continue;

            $qty     = isset($qtyIdx,    $row[$qtyIdx])    ? (float)$row[$qtyIdx]    : null;
            $unit    = isset($unitIdx,   $row[$unitIdx])   ? trim($row[$unitIdx])    : null;
            $thresh  = isset($threshIdx, $row[$threshIdx]) ? (float)$row[$threshIdx] : null;
            $expiry  = isset($expiryIdx, $row[$expiryIdx]) ? trim($row[$expiryIdx])  : null;
            $expiry  = ($expiry && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) ? $expiry : null;

            /* Upsert by name */
            $safeLookup = $this->db->real_escape_string($name);
            $existing = $this->fetchOne("SELECT id FROM ingredients WHERE name='$safeLookup' LIMIT 1");

            if ($existing) {
                $sets = [];
                if ($qty    !== null) $sets[] = "stock_qty='$qty'";
                if ($unit   !== null) $sets[] = "unit='" . $this->db->real_escape_string($unit) . "'";
                if ($thresh !== null) $sets[] = "low_stock_threshold='$thresh'";
                if ($expiry !== null) $sets[] = "expiry_date='$expiry'";
                if ($sets) {
                    $id = (int)$existing['id'];
                    $this->db->query("UPDATE ingredients SET " . implode(',', $sets) . " WHERE id=$id");
                }
            } else {
                $safeName   = $this->db->real_escape_string($name);
                $safeUnit   = $this->db->real_escape_string($unit ?? 'pcs');
                $safeQty    = $qty    ?? 0;
                $safeThresh = $thresh ?? 0;
                $safeExp    = $expiry ? "'$expiry'" : 'NULL';
                $this->db->query(
                    "INSERT INTO ingredients (name, stock_qty, unit, low_stock_threshold, expiry_date)
                     VALUES ('$safeName', $safeQty, '$safeUnit', $safeThresh, $safeExp)"
                );
            }
            $imported++;
        }
        fclose($handle);
        return $imported;
    }

    // ─────────────────────────────────────────────────────────────
    //  AJAX top-items  (?ajax=topitems) — mirrors SalesRevenueView
    // ─────────────────────────────────────────────────────────────

    private function ajaxTopItems(): never
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-cache');
        $VALID = "o.status NOT IN ('voided','refunded','partial_refund')";
        $items = [];

        // Build date filter from GET params (same logic as buildDateFilter)
        $allowed = ['today'=>0,'7days'=>7,'30days'=>30,'3months'=>90,'12months'=>365,'mtd'=>-1,'ytd'=>-1,'alltime'=>-1,'custom'=>-1];
        $range   = isset($_GET['range']) && array_key_exists($_GET['range'], $allowed) ? $_GET['range'] : 'alltime';
        switch ($range) {
            case 'today':    $df = "DATE(o.created_at)=CURDATE()"; break;
            case 'mtd':      $df = "YEAR(o.created_at)=YEAR(CURDATE()) AND MONTH(o.created_at)=MONTH(CURDATE())"; break;
            case 'ytd':      $df = "YEAR(o.created_at)=YEAR(CURDATE())"; break;
            case 'alltime':  $df = "1=1"; break;
            case 'custom':
                $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'] ?? '') ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
                $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to']   ?? '') ? $_GET['date_to']   : date('Y-m-d');
                $df   = "DATE(o.created_at) BETWEEN '$from' AND '$to'";
                break;
            default:
                $days = (int) $allowed[$range];
                $df   = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
        }

        if ($this->tableExists('order_items')) {
            $items = $this->fetchAll(
                "SELECT m.name, m.category, m.price,
                        SUM(oi.qty) AS qty_sold,
                        SUM(oi.qty * oi.unit_price) AS revenue
                 FROM order_items oi
                 JOIN menu m ON m.id = oi.menu_id
                 JOIN orders o ON o.id = oi.order_id
                 WHERE $VALID AND $df
                 GROUP BY oi.menu_id ORDER BY qty_sold DESC LIMIT 10"
            );
        }
        echo json_encode(['items' => $items]);
        exit();
    }
    // ─────────────────────────────────────────────────────────────

    private function load(): void
    {
        $VALID  = "status NOT IN ('voided','refunded','partial_refund')";
        $VALIDO = "o.status NOT IN ('voided','refunded','partial_refund')";

        // Info boxes
        $this->dailyCustomers = (int) ($this->fetchOne("SELECT COUNT(DISTINCT table_no) AS c FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID")['c'] ?? 0);
        $this->totalRevenue   = (float)($this->fetchOne("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE $VALID")['rev'] ?? 0);
        $this->ordersToday    = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID")['c'] ?? 0);
        $this->staffCount     = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM user WHERE position='staff'")['c'] ?? 0);

        // Monthly chart (last 6 months)
        for ($i = 5; $i >= 0; $i--) {
            $y = date('Y', strtotime("-$i months"));
            $m = date('m', strtotime("-$i months"));
            $this->chartLabels[] = date('M Y', strtotime("-$i months"));
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders
                 WHERE MONTH(created_at)=? AND YEAR(created_at)=? AND $VALID"
            );
            $stmt->bind_param('ss', $m, $y);
            $stmt->execute();
            $this->chartData[] = (float) $stmt->get_result()->fetch_assoc()['rev'];
            $stmt->close();
        }

        // Monthly summary
        $this->thisMonthRev = (float)($this->fetchOne("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) AND $VALID")['rev'] ?? 0);
        $this->lastMonthRev = (float)($this->fetchOne("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()-INTERVAL 1 MONTH) AND YEAR(created_at)=YEAR(CURDATE()-INTERVAL 1 MONTH) AND $VALID")['rev'] ?? 0);
        $this->revChange    = $this->lastMonthRev > 0
            ? round((($this->thisMonthRev - $this->lastMonthRev) / $this->lastMonthRev) * 100, 1)
            : 0.0;
        $this->totalOrders  = (int)($this->fetchOne("SELECT COUNT(*) AS c FROM orders WHERE $VALID")['c'] ?? 0);

        // Category revenue
        if ($this->hasOrderItems) {
            $this->catRevenue = $this->fetchAll(
                "SELECT m.category, SUM(oi.qty * oi.unit_price) AS revenue
                 FROM order_items oi
                 JOIN menu m ON m.id = oi.menu_id
                 JOIN orders o ON o.id = oi.order_id
                 WHERE $VALID
                 GROUP BY m.category ORDER BY revenue DESC LIMIT 4"
            );
        }
        $this->maxCatRev = !empty($this->catRevenue) ? (float) $this->catRevenue[0]['revenue'] : 1.0;

        // Right panel
        if ($this->hasOrderItems) {
            $this->dailyItemsSold = (int)($this->fetchOne("SELECT COALESCE(SUM(oi.qty),0) AS c FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE DATE(o.created_at)=CURDATE() AND $VALID")['c'] ?? 0);
        }
        $this->dailyRevenue      = (float)($this->fetchOne("SELECT COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID")['rev'] ?? 0);
        $this->lowStockCount     = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM ingredients WHERE stock_qty <= low_stock_threshold")['c'] ?? 0);
        $this->expiredCount      = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM ingredients WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()")['c'] ?? 0);
        $this->expiringSoonCount = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM ingredients WHERE expiry_date IS NOT NULL AND expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)")['c'] ?? 0);

        // Lists
        $this->expiredNames  = array_column($this->fetchAll("SELECT name FROM ingredients WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE() ORDER BY expiry_date ASC LIMIT 5"), 'name');
        $this->newMenuItems  = $this->fetchAll("SELECT name, price, description FROM menu ORDER BY id DESC LIMIT 4");
        $this->staffList     = $this->fetchAll("SELECT firstname, lastname, image FROM user WHERE position='staff' ORDER BY id DESC LIMIT 5");

        // Recent orders (date-filtered)
        $df = $this->dateFilter;
        if ($this->hasOrderItems) {
            $this->recentOrders = $this->fetchAll(
                "SELECT o.id, o.table_no, o.total_amt, o.created_at,
                        NULLIF(TRIM(CONCAT_WS(' ', u.firstname, u.lastname)), '') AS cashier_name,
                        o.status,
                        COALESCE(o.discount_amt,0) AS discount_amt,
                        COALESCE(o.discount_type,'') AS discount_type,
                        GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS items,
                        GROUP_CONCAT(
                            CONCAT(m.name,'|',oi.qty,'|',COALESCE(oi.addons,''),'|',
                                CASE
                                    WHEN oi.removed_ingredient_names IS NOT NULL AND oi.removed_ingredient_names != '[]' AND oi.removed_ingredient_names != ''
                                    THEN oi.removed_ingredient_names
                                    WHEN oi.removed_ingredient_ids IS NOT NULL AND oi.removed_ingredient_ids != '[]' AND oi.removed_ingredient_ids != ''
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
                 JOIN order_items oi ON oi.order_id = o.id
                 JOIN menu m ON m.id = oi.menu_id
                 LEFT JOIN user u ON u.id = o.user_id
                 WHERE $VALIDO AND $df
                 GROUP BY o.id ORDER BY o.created_at DESC LIMIT 50"
            );
        } else {
            $this->recentOrders = $this->fetchAll(
                "SELECT o.id, o.table_no, o.total_amt, o.created_at,
                        NULLIF(TRIM(CONCAT_WS(' ', u.firstname, u.lastname)), '') AS cashier_name,
                        o.status,
                        COALESCE(o.discount_amt,0) AS discount_amt,
                        COALESCE(o.discount_type,'') AS discount_type,
                        '—' AS items, '' AS item_details
                 FROM orders o LEFT JOIN user u ON u.id=o.user_id
                 WHERE $VALIDO AND $df ORDER BY o.created_at DESC LIMIT 50"
            );
        }

        // Forecast (linear regression on 6-month chart data)
        $reg = $this->linearRegression($this->chartData);
        $n   = count($this->chartData);
        for ($f = 1; $f <= 3; $f++) {
            $this->forecastData[]   = round(max(0, $reg['intercept'] + $reg['slope'] * ($n - 1 + $f)), 2);
            $this->forecastLabels[] = date('M Y', strtotime("+$f months"));
        }
        $this->projNextMonth  = $this->forecastData[0];
        $lastActual           = end($this->chartData);
        $this->projGrowthPct  = $lastActual > 0
            ? (($this->projNextMonth - $lastActual) / $lastActual) * 100 : 0.0;
        $nonZero              = array_filter($this->chartData, fn($v) => $v > 0);
        $this->avgMonthRev    = $nonZero ? array_sum($this->chartData) / count($nonZero) : 0.0;

        // Pre-encoded JSON for <script> tags
        $this->chartLabelsJson = json_encode($this->chartLabels);
        $this->chartDataJson   = json_encode($this->chartData);
        $this->revTrend        = $this->revChange >= 0 ? '+' . $this->revChange . '%' : $this->revChange . '%';
        $this->revTrendClass   = $this->revChange >= 0 ? 'text-success' : 'text-danger';
        $this->revTrendIcon    = $this->revChange >= 0 ? 'fa-caret-up' : 'fa-caret-down';
    }

    // ─────────────────────────────────────────────────────────────
    //  Private helpers
    // ─────────────────────────────────────────────────────────────

    private string $dateFilter = '1=1';

    private function buildDateFilter(): void
    {
        $allowed = [
            'today'   => 0, '7days'  => 7, '30days'  => 30,
            'custom'  => -1,'3months'=> 90,'12months'=> 365,
            'mtd'     => -1,'ytd'   => -1, 'alltime' => -1,
        ];
        $range = isset($_GET['range']) && array_key_exists($_GET['range'], $allowed)
            ? $_GET['range'] : '7days';
        $this->selectedRange = $range;

        if ($range === 'today') {
            $this->dateFilter = "DATE(o.created_at) = CURDATE()";
        } elseif ($range === 'custom') {
            $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'] ?? '') ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
            $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to']   ?? '') ? $_GET['date_to']   : date('Y-m-d');
            $this->dateFrom   = $from;
            $this->dateTo     = $to;
            $this->dateFilter = "DATE(o.created_at) BETWEEN '$from' AND '$to'";
        } elseif ($range === 'mtd') {
            $this->dateFilter = "YEAR(o.created_at)=YEAR(CURDATE()) AND MONTH(o.created_at)=MONTH(CURDATE())";
        } elseif ($range === 'ytd') {
            $this->dateFilter = "YEAR(o.created_at)=YEAR(CURDATE())";
        } elseif ($range === 'alltime') {
            $this->dateFilter = "1=1";
        } else {
            $days = (int) $allowed[$range];
            $this->dateFilter = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
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
            $sumX  += $i;       $sumY  += $y[$i];
            $sumXY += $i*$y[$i];$sumX2 += $i*$i;
        }
        $denom = $n * $sumX2 - $sumX * $sumX;
        if ($denom == 0) return ['slope' => 0, 'intercept' => $sumY / $n];
        $slope     = ($n * $sumXY - $sumX * $sumY) / $denom;
        $intercept = ($sumY - $slope * $sumX) / $n;
        return ['slope' => $slope, 'intercept' => $intercept];
    }
}