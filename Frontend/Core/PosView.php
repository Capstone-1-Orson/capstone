<?php
// Frontend/Core/PosView.php

require_once __DIR__ . '/View.php';

/**
 * PosView – data for Frontend/POS.php.
 *
 * Handles:
 *   ?rt=1       → JSON: today's order statuses (live-update polling)
 *   ?menu_rt=1  → JSON: full menu availability list
 *
 * Usage at top of POS.php:
 *   require_once '../Frontend/Core/PosView.php';   // path from Frontend/
 *   $view = new PosView();
 */
class PosView extends View
{
    // ── Cashier info (populated from session + DB) ───────────────
    public string $cashierFirst = '';
    public string $cashierLast  = '';
    public string $cashierImage = '';

    // ── Menu ─────────────────────────────────────────────────────
    public array  $menuItems    = [];
    public array  $categories   = [];

    // ── Stats ────────────────────────────────────────────────────
    public float  $dbTodayRevenue = 0.0;
    public int    $dbTodayOrders  = 0;
    public float  $dbTotalRevenue = 0.0;
    public int    $dbTotalOrders  = 0;
    public string $dbTopItem      = '—';

    // ── History / tables ─────────────────────────────────────────
    public array  $dbHistory      = [];
    public array  $dbCatRevenue   = [];
    public array  $dbTransactions = [];

    // ── Ingredient map (menu_id → [{id, name}]) ──────────────────
    public array  $menuIngredientsMap = [];

    // ── Pre-encoded JSON for inline scripts ──────────────────────
    public string $dbHistoryJson     = '[]';
    public string $dbCatRevenueJson  = '[]';
    public string $dbTransactionsJson= '[]';

    public bool $hasOrderItems   = false;
    private bool $hasMenuIngredients = false;
    private string $VALID = "status NOT IN ('voided','refunded','partial_refund')";

    public function __construct()
    {
        parent::__construct();
        $this->requireStaff();

        $this->hasOrderItems      = $this->tableExists('order_items');
        $this->hasMenuIngredients = $this->tableExists('menu_ingredients');

        if (isset($_GET['rt']))      { $this->ajaxOrderStatus(); /* exits */ }
        if (isset($_GET['menu_rt'])) { $this->ajaxMenu();        /* exits */ }

        $this->load();
    }

    // ─────────────────────────────────────────────────────────────
    //  AJAX: order statuses for today (?rt=1)
    // ─────────────────────────────────────────────────────────────

    private function ajaxOrderStatus(): never
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $rows = array_map(fn($r) => [
            'id'         => (int)   $r['id'],
            'status'     =>         $r['status'],
            'total_amt'  => (float) $r['total_amt'],
        ], $this->fetchAll(
            "SELECT id, status, total_amt FROM orders
             WHERE DATE(created_at) = CURDATE() ORDER BY id DESC LIMIT 100"
        ));

        $VALID = $this->VALID;
        $row2 = $this->fetchOne("SELECT COUNT(*) AS c, COALESCE(SUM(total_amt),0) AS r FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");

        echo json_encode([
            'orders'        => $rows,
            'today_revenue' => (float) ($row2['r'] ?? 0),
            'today_orders'  => (int)   ($row2['c'] ?? 0),
        ]);
        exit();
    }

    // ─────────────────────────────────────────────────────────────
    //  AJAX: menu list (?menu_rt=1)
    // ─────────────────────────────────────────────────────────────

    private function ajaxMenu(): never
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $items = array_map(fn($r) => [
            'id'           => (int)   $r['id'],
            'name'         =>         $r['name'],
            'price'        => (float) $r['price'],
            'category'     =>         $r['category'],
            'image'        =>         $r['image'] ?? '',
            'is_available' => (bool)  $r['is_available'],
        ], $this->fetchAll("SELECT id, name, price, category, image, is_available FROM menu ORDER BY category, name"));

        echo json_encode(['menu' => $items, 'ts' => time()]);
        exit();
    }

    // ─────────────────────────────────────────────────────────────
    //  Page data
    // ─────────────────────────────────────────────────────────────

    private function load(): void
    {
        $VALID = $this->VALID;

        // Resolve cashier name from session (re-fetch from DB to stay fresh)
        $email = Session::get('user', '');
        if ($email) {
            $stmt = $this->db->prepare("SELECT firstname, lastname, image FROM user WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->bind_result($fn, $ln, $img);
            if ($stmt->fetch()) {
                $this->cashierFirst = $fn;
                $this->cashierLast  = $ln;
                $this->cashierImage = $img ?? '';
                Session::set('firstname', $fn);
                Session::set('lastname',  $ln);
                Session::set('image',     $img);
            }
            $stmt->close();
        }

        // Menu items and categories
        $this->menuItems  = $this->fetchAll("SELECT id, name, description, price, category, image FROM menu WHERE is_available = 1 ORDER BY category, name");
        $this->categories = array_column($this->fetchAll("SELECT DISTINCT category FROM menu WHERE is_available = 1 ORDER BY category"), 'category');

        // Stats
        $row = $this->fetchOne("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");
        if ($row) { $this->dbTodayRevenue = (float)$row['rev']; $this->dbTodayOrders = (int)$row['cnt']; }

        $row = $this->fetchOne("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE $VALID");
        if ($row) { $this->dbTotalRevenue = (float)$row['rev']; $this->dbTotalOrders = (int)$row['cnt']; }

        if ($this->hasOrderItems) {
            $row = $this->fetchOne(
                "SELECT m.name, SUM(oi.qty) AS total_qty
                 FROM order_items oi JOIN menu m ON m.id=oi.menu_id
                 JOIN orders o ON o.id=oi.order_id WHERE $VALID
                 GROUP BY oi.menu_id ORDER BY total_qty DESC LIMIT 1"
            );
            if ($row) {
                $this->dbTopItem = htmlspecialchars($row['name']) . ' (' . (int)$row['total_qty'] . ' sold)';
            }
        }

        // Order history (today, last 50)
        $this->dbHistory = $this->hasOrderItems
            ? $this->fetchAll(
                "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                        COALESCE(o.discount_amt,0) AS discount_amt,
                        COALESCE(o.discount_type,'') AS discount_type,
                        SUM(oi.qty) AS total_qty,
                        GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS item_names,
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
                 JOIN order_items oi ON oi.order_id = o.id
                 JOIN menu m ON m.id = oi.menu_id
                 WHERE DATE(o.created_at) = CURDATE()
                 GROUP BY o.id ORDER BY o.created_at DESC LIMIT 50"
              )
            : $this->fetchAll(
                "SELECT id, table_no, total_amt, status, created_at, 0 AS total_qty,
                        '—' AS item_names, '' AS item_details,
                        COALESCE(discount_amt,0) AS discount_amt,
                        COALESCE(discount_type,'') AS discount_type
                 FROM orders WHERE DATE(created_at)=CURDATE() ORDER BY created_at DESC LIMIT 50"
              );

        // Category revenue today
        $this->dbCatRevenue = $this->hasOrderItems
            ? $this->fetchAll(
                "SELECT m.category, SUM(oi.qty * oi.unit_price) AS revenue
                 FROM order_items oi JOIN menu m ON m.id=oi.menu_id
                 JOIN orders o ON o.id=oi.order_id
                 WHERE DATE(o.created_at)=CURDATE() AND $VALID
                 GROUP BY m.category ORDER BY revenue DESC"
              )
            : [];

        // Recent transactions today
        $this->dbTransactions = $this->hasOrderItems
            ? $this->fetchAll(
                "SELECT o.id, o.table_no, o.total_amt, o.created_at, SUM(oi.qty) AS total_qty
                 FROM orders o JOIN order_items oi ON oi.order_id=o.id
                 WHERE DATE(o.created_at)=CURDATE() AND $VALID
                 GROUP BY o.id ORDER BY o.created_at DESC LIMIT 20"
              )
            : $this->fetchAll(
                "SELECT id, table_no, total_amt, created_at, 0 AS total_qty
                 FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID ORDER BY created_at DESC LIMIT 20"
              );

        // Ingredient map for "remove ingredient" feature
        if ($this->hasMenuIngredients) {
            $rows = $this->fetchAll(
                "SELECT mi.menu_id, i.id AS ingredient_id, i.name
                 FROM menu_ingredients mi JOIN ingredients i ON i.id=mi.ingredient_id
                 ORDER BY mi.menu_id, i.name"
            );
            foreach ($rows as $row) {
                $this->menuIngredientsMap[(int)$row['menu_id']][] = [
                    'id'   => (int)    $row['ingredient_id'],
                    'name' =>          $row['name'],
                ];
            }
        }

        // Pre-encode JSON
        $this->dbHistoryJson      = json_encode($this->dbHistory);
        $this->dbCatRevenueJson   = json_encode($this->dbCatRevenue);
        $this->dbTransactionsJson = json_encode($this->dbTransactions);
    }

    private function tableExists(string $table): bool
    {
        $safe = $this->db->real_escape_string($table);
        $r    = $this->db->query("SHOW TABLES LIKE '$safe'");
        return $r && $r->num_rows > 0;
    }
}
