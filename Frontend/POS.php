<?php
// Frontend/POS.php  (sits directly inside Frontend/)
session_name('STAFF_SESSION');
session_start();
if (!isset($_SESSION['user']) || $_SESSION['position'] !== 'staff') {
    header("Location:../login-v2.html");
    exit();
}

require_once '../Backend/conn.php';

if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? 'conn.php did not define $conn'));
}

// ══ REAL-TIME POLLING ENDPOINT (?rt=1) ════════════════════════
// Returns current status of today's orders so the POS can live-update
// history rows when admin voids/refunds an order without a page reload.
if (isset($_GET['rt'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    // Return id + status for all of today's orders
    $rows = [];
    $r = $conn->query(
        "SELECT id, status, total_amt FROM orders
         WHERE DATE(created_at) = CURDATE()
         ORDER BY id DESC LIMIT 100"
    );
    if ($r) while ($row = $r->fetch_assoc()) {
        $rows[] = ['id' => (int)$row['id'], 'status' => $row['status'], 'total_amt' => (float)$row['total_amt']];
    }

    // Also return today's valid revenue/order count so stat cards stay accurate
    $rev = 0.0; $cnt = 0;
    $r2 = $conn->query(
        "SELECT COUNT(*) AS c, COALESCE(SUM(total_amt),0) AS r FROM orders
         WHERE DATE(created_at)=CURDATE()
         AND status NOT IN ('voided','refunded','partial_refund')"
    );
    if ($r2 && $row2 = $r2->fetch_assoc()) { $cnt=(int)$row2['c']; $rev=(float)$row2['r']; }

    $conn->close();
    echo json_encode(['orders' => $rows, 'today_revenue' => $rev, 'today_orders' => $cnt]);
    exit();
}
// ══ END REAL-TIME ENDPOINT ════════════════════════════════════

// ══ MENU REAL-TIME ENDPOINT (?menu_rt=1) ══════════════════════
// Returns full menu availability so POS can live-update product
// cards and menu panel when admin toggles items on/off.
if (isset($_GET['menu_rt'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $items = [];
    $r = $conn->query(
        "SELECT id, name, price, category, image, is_available
         FROM menu ORDER BY category, name"
    );
    if ($r) while ($row = $r->fetch_assoc()) {
        $items[] = [
            'id'           => (int)$row['id'],
            'name'         => $row['name'],
            'price'        => (float)$row['price'],
            'category'     => $row['category'],
            'image'        => $row['image'] ?? '',
            'is_available' => (bool)$row['is_available'],
        ];
    }
    $conn->close();
    echo json_encode(['menu' => $items, 'ts' => time()]);
    exit();
}
// ══ END MENU REAL-TIME ENDPOINT ═══════════════════════════════

// ── Resolve cashier full name from DB (always re-fetch to reflect current logged-in user) ──
$email_key = $_SESSION['user'] ?? '';
$stmt = $conn->prepare("SELECT firstname, lastname, image FROM user WHERE email = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('s', $email_key);
    $stmt->execute();
    $stmt->bind_result($_sFirst, $_sLast, $_sImage);
    if ($stmt->fetch()) {
        $_SESSION['firstname'] = $_sFirst;
        $_SESSION['lastname']  = $_sLast;
        $_SESSION['image']     = $_sImage;
    }
    $stmt->close();
}

// ── NOTE: run this once in phpMyAdmin if the column doesn't exist yet ──
// ALTER TABLE menu ADD COLUMN image VARCHAR(255) NULL DEFAULT NULL AFTER description;

$menu_items = [];
$res = $conn->query("SELECT id, name, description, price, category, image FROM menu WHERE is_available = 1 ORDER BY category, name");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $menu_items[] = $row;
    }
}

$cats_raw = [];
$res2 = $conn->query("SELECT DISTINCT category FROM menu WHERE is_available = 1 ORDER BY category");
if ($res2 && $res2->num_rows > 0) {
    while ($row = $res2->fetch_assoc()) {
        $cats_raw[] = $row['category'];
    }
}

// ── Helper: check table exists ─────────────────────────────────
function tableExists($conn, $table) {
    $safe = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '$safe'");
    return $r && $r->num_rows > 0;
}
$hasOrderItems = tableExists($conn, 'order_items');

// ── Valid order filter ─────────────────────────────────────────
$VALID = "status NOT IN ('voided','refunded','partial_refund')";

// ── Today's DB stats (for Reports panel initial load) ──────────
$db_today_revenue = 0.0;
$db_today_orders  = 0;
$r = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID");
if ($r && $row = $r->fetch_assoc()) {
    $db_today_revenue = (float)$row['rev'];
    $db_today_orders  = (int)$row['cnt'];
}

// ── All-time DB stats ──────────────────────────────────────────
$db_total_revenue = 0.0;
$db_total_orders  = 0;
$r = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amt),0) AS rev FROM orders WHERE $VALID");
if ($r && $row = $r->fetch_assoc()) {
    $db_total_revenue = (float)$row['rev'];
    $db_total_orders  = (int)$row['cnt'];
}

// ── Top selling item (all-time) ────────────────────────────────
$db_top_item = '—';
if ($hasOrderItems) {
    $r = $conn->query(
        "SELECT m.name, SUM(oi.qty) AS total_qty
         FROM order_items oi
         JOIN menu m ON m.id = oi.menu_id
         JOIN orders o ON o.id = oi.order_id
         WHERE $VALID
         GROUP BY oi.menu_id ORDER BY total_qty DESC LIMIT 1"
    );
    if ($r && $row = $r->fetch_assoc()) {
        $db_top_item = htmlspecialchars($row['name']) . ' (' . (int)$row['total_qty'] . ' sold)';
    }
}

// ── Order history from DB (last 50 orders today) ───────────────
$db_history = [];
if ($hasOrderItems) {
    $r = $conn->query(
        "SELECT o.id, o.table_no, o.total_amt, o.status, o.created_at,
                COALESCE(o.discount_amt, 0) AS discount_amt,
                COALESCE(o.discount_type, '') AS discount_type,
                SUM(oi.qty) AS total_qty,
                GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS item_names,
                GROUP_CONCAT(
                    CONCAT(
                        m.name,'|',oi.qty,'|',
                        COALESCE(oi.addons,''),'|',
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
                                WHERE JSON_SEARCH(oi.removed_ingredient_ids, 'one', CAST(i2.id AS CHAR)) IS NOT NULL
                            )
                            ELSE ''
                        END
                    )
                    ORDER BY m.name SEPARATOR ';;'
                ) AS item_details
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         JOIN menu m ON m.id = oi.menu_id
         WHERE DATE(o.created_at) = CURDATE()
         GROUP BY o.id ORDER BY o.created_at DESC LIMIT 50"
    );
    if ($r) while ($row = $r->fetch_assoc()) $db_history[] = $row;
} else {
    $r = $conn->query(
        "SELECT id, table_no, total_amt, status, created_at, 0 AS total_qty, '—' AS item_names, '' AS item_details,
                COALESCE(discount_amt,0) AS discount_amt, COALESCE(discount_type,'') AS discount_type
         FROM orders WHERE DATE(created_at)=CURDATE() ORDER BY created_at DESC LIMIT 50"
    );
    if ($r) while ($row = $r->fetch_assoc()) $db_history[] = $row;
}

// ── Category revenue today (valid only) ───────────────────────
$db_cat_revenue = [];
if ($hasOrderItems) {
    $r = $conn->query(
        "SELECT m.category, SUM(oi.qty * oi.unit_price) AS revenue
         FROM order_items oi
         JOIN menu m ON m.id = oi.menu_id
         JOIN orders o ON o.id = oi.order_id
         WHERE DATE(o.created_at) = CURDATE() AND $VALID
         GROUP BY m.category ORDER BY revenue DESC"
    );
    if ($r) while ($row = $r->fetch_assoc()) $db_cat_revenue[] = $row;
}

// ── Recent transactions today (valid only) ────────────────────
$db_transactions = [];
if ($hasOrderItems) {
    $r = $conn->query(
        "SELECT o.id, o.table_no, o.total_amt, o.created_at,
                SUM(oi.qty) AS total_qty
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         WHERE DATE(o.created_at) = CURDATE() AND $VALID
         GROUP BY o.id ORDER BY o.created_at DESC LIMIT 20"
    );
    if ($r) while ($row = $r->fetch_assoc()) $db_transactions[] = $row;
} else {
    $r = $conn->query(
        "SELECT id, table_no, total_amt, created_at, 0 AS total_qty
         FROM orders WHERE DATE(created_at)=CURDATE() AND $VALID ORDER BY created_at DESC LIMIT 20"
    );
    if ($r) while ($row = $r->fetch_assoc()) $db_transactions[] = $row;
}

// ── Fetch real ingredients per menu item ───────────────────────
$menu_ingredients_map = [];
$hasMenuIngredients = tableExists($conn, 'menu_ingredients');
if ($hasMenuIngredients) {
    $r = $conn->query(
        "SELECT mi.menu_id, i.id AS ingredient_id, i.name
         FROM menu_ingredients mi
         JOIN ingredients i ON i.id = mi.ingredient_id
         ORDER BY mi.menu_id, i.name"
    );
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $menu_ingredients_map[(int)$row['menu_id']][] = [
                'id'   => (int)$row['ingredient_id'],
                'name' => $row['name'],
            ];
        }
    }
}

$conn->close();

// JSON for JS
$db_history_json      = json_encode($db_history);
$db_cat_revenue_json  = json_encode($db_cat_revenue);
$db_transactions_json = json_encode($db_transactions);

$cat_emoji = [
    'Main Course'   => '🍽️',
    'Appetizer'     => '🥗',
    'Dessert'       => '🍰',
    'Beverage'      => '☕',
    'Coffee'        => '☕',
    'Beer & Wine'   => '🍷',
    'Bites & Treats'=> '🥐',
    'Croffle'       => '🧇',
    'Croffle Box'   => '📦',
    'Frappe'        => '🥤',
    'Smoothie'      => '🥤',
    'Tea'           => '🍵',
    'Pasta'         => '🍝',
    'Pizza'         => '🍕',
    'Sandwich'      => '🥪',
    'Salad'         => '🥗',
    'Soup'          => '🍲',
    'Rice'          => '🍚',
    'Breakfast'     => '🍳',
    'Snacks'        => '🍟',
    'Juice'         => '🍊',
    'Milkshake'     => '🥛',
    'Cake'          => '🎂',
    'Waffles'       => '🧇',
];

$products_json = json_encode(array_map(function($item) use ($cat_emoji, $menu_ingredients_map) {
    return [
        'id'          => (int)$item['id'],
        'cat'         => $item['category'],
        'emoji'       => $cat_emoji[$item['category']] ?? '🍽️',
        'name'        => $item['name'],
        'price'       => (float)$item['price'],
        'desc'        => $item['description'] ?? '',
        'image'       => $item['image'] ?? '',
        'badge'       => '',
        'ingredients' => $menu_ingredients_map[(int)$item['id']] ?? [],
    ];
}, $menu_items));

$cats_json = json_encode($cats_raw);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Empress POS · Restaurant</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet"/>
<link href="dist/css/empress-animations.css" rel="stylesheet"/>
<style>
/* ── DARK MODE (black + pink) ── */
:root {
  --accent:#e91e8c; --accent-hover:#c2185b; --accent-glow:rgba(233,30,140,.22); --accent-soft:rgba(233,30,140,.10);
  --green:#22c55e; --red:#ef4444; --blue:#3b82f6;
  --bg:#0a0a0a; --surface:#111111; --surface2:#1a1a1a; --surface3:#242424;
  --border:rgba(255,255,255,.06); --border2:rgba(255,255,255,.12);
  --text:#f5f5f5; --muted:#666666; --muted2:#999999;
  --sidebar-w:72px; --order-w:340px; --radius:14px; --radius-sm:9px;
  --tr:.22s cubic-bezier(.4,0,.2,1);
}
[data-theme="light"] {
  --accent:#e91e8c; --accent-hover:#c2185b; --accent-glow:rgba(233,30,140,.18); --accent-soft:rgba(233,30,140,.08);
  --bg:#ffffff; --surface:#ffffff; --surface2:#fdf4f9; --surface3:#fce7f3;
  --border:rgba(233,30,140,.10); --border2:rgba(233,30,140,.20);
  --text:#1a1a1a; --muted:#b48ca8; --muted2:#7a4a6a;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;overflow:hidden;}
body{font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);display:flex;transition:background var(--tr),color var(--tr);}
::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:var(--surface3);border-radius:4px;}
::-webkit-scrollbar-thumb:hover{background:var(--accent);}
button,input,select{font-family:inherit;}
button{border:none;background:none;cursor:pointer;outline:none;color:inherit;}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes scaleIn{from{opacity:0;transform:scale(.92)}to{opacity:1;transform:scale(1)}}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 var(--accent-glow)}50%{box-shadow:0 0 0 8px transparent}}
@keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
/* Prevent flash of invisible content during animation delay */
.stats-strip,.cats,.product-card{opacity:1;}

/* SIDEBAR */
.pos-sidebar{width:var(--sidebar-w);flex-shrink:0;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;align-items:center;padding:16px 0 14px;gap:3px;z-index:100;transition:background var(--tr);}
.s-logo{width:42px;height:42px;background:var(--accent);border-radius:12px;display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-size:20px;color:#fff;font-weight:700;margin-bottom:20px;box-shadow:0 0 24px var(--accent-glow);transition:transform var(--tr),box-shadow var(--tr);cursor:pointer;}
.s-logo:hover{transform:scale(1.08);box-shadow:0 0 32px rgba(233,30,140,.42);}
.nav-btn{width:48px;height:48px;border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;color:var(--muted);font-size:9px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;transition:all var(--tr);position:relative;}
.nav-btn i{font-size:17px;transition:transform var(--tr);}
.nav-btn:hover{background:var(--surface2);color:var(--muted2);}
.nav-btn:hover i{transform:scale(1.15);}
.nav-btn.active{background:var(--accent-soft);color:var(--accent);}
.nav-btn.active::before{content:'';position:absolute;left:-1px;top:50%;transform:translateY(-50%);width:3px;height:26px;background:var(--accent);border-radius:0 3px 3px 0;}
.nav-btn .bdot{position:absolute;top:8px;right:8px;width:7px;height:7px;background:var(--red);border-radius:50%;border:2px solid var(--surface);animation:pulse 2s infinite;}
.nav-logout{text-decoration:none;color:var(--muted);}
.nav-logout:hover{background:rgba(239,68,68,.12)!important;color:#ef4444!important;}
.nav-logout:hover i{color:#ef4444;}
.s-spacer{flex:1;}
.s-theme{width:38px;height:38px;border-radius:10px;background:var(--surface2);border:1px solid var(--border);color:var(--muted2);font-size:15px;display:flex;align-items:center;justify-content:center;transition:all var(--tr);}
.s-theme:hover{background:var(--accent-soft);color:var(--accent);border-color:rgba(233,30,140,.3);}
.s-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#c2185b);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#fff;border:2px solid var(--border2);cursor:pointer;transition:transform var(--tr);margin-top:4px;}
.s-avatar:hover{transform:scale(1.1);}

/* MAIN */
.pos-main{flex:1;display:flex;flex-direction:column;overflow:hidden;padding:20px 18px;gap:16px;min-width:0;min-height:0;background:var(--bg);transition:background var(--tr);}
.pos-topbar{display:flex;align-items:center;gap:11px;flex-shrink:0;animation:fadeUp .4s ease forwards;}
.pos-title{font-family:'Playfair Display',serif;font-size:23px;font-weight:700;white-space:nowrap;}
.pos-title span{color:var(--accent);}
.pos-search{flex:1;max-width:360px;position:relative;margin-left:auto;}
.pos-search i.si{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none;}
.pos-search input{width:100%;padding:10px 36px 10px 36px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13.5px;transition:all var(--tr);}
.pos-search input::placeholder{color:var(--muted);}
.pos-search input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);}
.pos-search .sc{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px;display:none;}
.pos-search input:not(:placeholder-shown) ~ .sc{display:block;}
/* STATS */
.stats-strip{display:flex;gap:11px;flex-shrink:0;animation:fadeUp .4s .06s ease forwards;}
.stat-card{flex:1;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:13px 16px;display:flex;align-items:center;gap:13px;transition:all var(--tr);min-width:0;cursor:default;}
.stat-card:hover{border-color:var(--border2);transform:translateY(-2px);box-shadow:0 8px 24px var(--accent-glow);}
.stat-ic{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.stat-v{font-size:18px;font-weight:700;line-height:1.1;}
.stat-l{font-size:11px;color:var(--muted);margin-top:2px;}

/* CATEGORIES */
.cats{display:flex;gap:8px;overflow-x:auto;flex-shrink:0;padding-bottom:6px;animation:fadeUp .4s .1s ease forwards;scroll-behavior:smooth;}
.cats::-webkit-scrollbar{height:3px;}
.cats::-webkit-scrollbar-track{background:transparent;}
.cats::-webkit-scrollbar-thumb{background:var(--surface3);border-radius:4px;}
.cats::-webkit-scrollbar-thumb:hover{background:var(--accent);}
.cat-pill{display:flex;align-items:center;gap:6px;padding:7px 16px;border-radius:50px;border:1px solid var(--border2);background:var(--surface);color:var(--muted2);font-size:13px;font-weight:500;white-space:nowrap;flex-shrink:0;transition:all var(--tr);}
.cat-pill:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-soft);}
.cat-pill.active{background:var(--accent);border-color:var(--accent);color:#fff;font-weight:700;box-shadow:0 4px 14px var(--accent-glow);}
.cat-cnt{font-size:10px;background:rgba(255,255,255,.15);padding:1px 6px;border-radius:20px;font-weight:600;}
.cat-pill.active .cat-cnt{background:rgba(255,255,255,.25);color:#fff;}
.sec-label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;flex-shrink:0;}

/* MENU AREA WRAPPER */
.menu-area{display:flex;flex-direction:column;flex:1;min-height:0;gap:8px;overflow:hidden;}

/* PRODUCT GRID */
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(168px,1fr));gap:13px;overflow-y:auto;padding-right:4px;padding-bottom:12px;align-content:start;flex:1;min-height:0;}
.product-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;display:flex;flex-direction:column;transition:all var(--tr);cursor:pointer;position:relative;animation:fadeUp .35s ease forwards;min-height:200px;}
.product-card:hover{border-color:var(--accent);transform:translateY(-3px);box-shadow:0 12px 28px var(--accent-glow);}
.product-card:hover .card-add-btn{opacity:1;transform:translateY(0);}
.card-img-w{height:108px;min-height:108px;flex-shrink:0;position:relative;overflow:hidden;background:var(--surface2);}
.card-emoji{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:42px;transition:transform .45s ease;}
.card-img-real{width:100%;height:100%;object-fit:cover;display:block;transition:transform .45s ease;}
.card-img-placeholder{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;background:linear-gradient(135deg,var(--surface2),var(--surface3));color:var(--muted);font-size:11px;font-weight:500;letter-spacing:.03em;}
.card-img-placeholder i{font-size:30px;opacity:.35;}
.product-card:hover .card-emoji,.product-card:hover .card-img-real{transform:scale(1.12);}
.card-bdg{position:absolute;top:8px;right:8px;background:var(--green);color:#fff;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:20px;}
.card-bdg.hot{background:var(--red);}
.card-bdg.new{background:var(--blue);}
.card-body-i{padding:11px;flex:1;display:flex;flex-direction:column;gap:7px;min-height:0;}
.card-name{font-weight:600;font-size:13.5px;line-height:1.3;flex-shrink:0;}
.card-foot{display:flex;align-items:center;justify-content:space-between;flex-shrink:0;margin-top:auto;}
.card-price{font-size:15px;font-weight:700;color:var(--accent);}
.card-add-btn{width:100%;background:var(--accent);color:#fff;font-weight:700;font-size:12.5px;padding:9px;display:flex;align-items:center;justify-content:center;gap:5px;opacity:0;transform:translateY(4px);transition:all var(--tr);flex-shrink:0;}
.card-add-btn:hover{background:var(--accent-hover);}
.product-card.in-cart{border-color:rgba(233,30,140,.4);}
.product-card.in-cart .card-add-btn{opacity:1;transform:translateY(0);background:var(--surface3);color:var(--muted2);}
.product-card.in-cart .card-add-btn:hover{background:var(--accent);color:#fff;}
.empty-products{grid-column:1/-1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:11px;padding:50px 20px;color:var(--muted);animation:fadeIn .4s ease;}
.empty-products i{font-size:44px;color:var(--surface3);}

/* ORDER PANEL */
.pos-order{width:var(--order-w);flex-shrink:0;background:var(--surface);border-left:1px solid var(--border);display:flex;flex-direction:column;padding:20px 17px;gap:11px;overflow:hidden;transition:background var(--tr);}
.o-top{display:flex;align-items:center;justify-content:space-between;}
.o-title{font-family:'Playfair Display',serif;font-size:18px;font-weight:700;}
.o-date{font-size:11px;color:var(--muted);background:var(--surface2);border:1px solid var(--border2);padding:4px 10px;border-radius:7px;}
.o-tabs{display:flex;gap:5px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;padding:4px;}
.o-tab{flex:1;padding:7px 4px;border-radius:7px;font-size:11.5px;font-weight:600;color:var(--muted);transition:all var(--tr);display:flex;align-items:center;justify-content:center;gap:4px;}
.o-tab i{font-size:11px;}
.o-tab.active{background:var(--accent);color:#fff;box-shadow:0 2px 10px var(--accent-glow);}
.o-tab:hover:not(.active){background:var(--surface3);color:var(--muted2);}
.tbl-row{display:flex;align-items:center;gap:8px;}
.tbl-label{font-size:11.5px;color:var(--muted);flex-shrink:0;}
.tbl-number-inp{width:72px;padding:6px 10px;background:var(--surface2);border:1.5px solid var(--border2);border-radius:9px;color:var(--text);font-size:15px;font-weight:700;text-align:center;transition:all var(--tr);}
.tbl-number-inp:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);}
.tbl-number-inp::-webkit-inner-spin-button,.tbl-number-inp::-webkit-outer-spin-button{opacity:1;}
/* CART */
.cart-scroll{flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:8px;min-height:0;}
.empty-cart{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:9px;color:var(--muted);font-size:13px;text-align:center;animation:fadeIn .4s ease;}
.empty-cart i{font-size:38px;color:var(--surface3);margin-bottom:3px;}
.cart-item{display:flex;align-items:center;gap:10px;background:var(--surface2);border:1px solid var(--border);padding:10px 11px;border-radius:11px;transition:all var(--tr);animation:scaleIn .22s ease;}
.cart-item:hover{border-color:var(--border2);}
.ci-emoji{width:34px;height:34px;background:var(--surface3);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;overflow:hidden;}
.ci-emoji img{width:100%;height:100%;object-fit:cover;border-radius:8px;}
.ci-info{flex:1;min-width:0;}
.ci-name{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ci-price{font-size:11px;color:var(--muted2);margin-top:1px;}
.ci-total{font-size:13px;font-weight:700;color:var(--accent);}
.qty-ctrl{display:flex;align-items:center;gap:6px;flex-shrink:0;}
.qty-btn{width:24px;height:24px;border-radius:7px;background:var(--surface3);color:var(--muted2);font-size:11px;display:flex;align-items:center;justify-content:center;transition:all var(--tr);}
.qty-btn:hover{background:var(--accent);color:#fff;}
.qty-v{font-size:13px;font-weight:700;min-width:16px;text-align:center;}
.btn-rm{color:var(--muted);font-size:12px;transition:color var(--tr);padding:2px 4px;}
.btn-rm:hover{color:var(--red);}

/* DISCOUNT */
.discount-row{display:flex;flex-direction:column;gap:6px;}
.discount-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);display:flex;align-items:center;gap:5px;}
.discount-btns{display:flex;gap:7px;}
.btn-discount{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 10px;border-radius:9px;border:1.5px solid var(--border2);background:var(--surface2);color:var(--muted2);font-size:12.5px;font-weight:700;transition:all var(--tr);cursor:pointer;}
.btn-discount:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-soft);}
.btn-discount.active{background:var(--accent);border-color:var(--accent);color:#fff;box-shadow:0 4px 14px var(--accent-glow);}
.btn-discount .disc-pct{font-size:10px;font-weight:800;background:rgba(255,255,255,.22);padding:1px 5px;border-radius:5px;margin-left:2px;}

/* SUMMARY */
.o-summary{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:13px;display:flex;flex-direction:column;gap:8px;}
.sum-row{display:flex;justify-content:space-between;align-items:center;font-size:13px;}
.sum-lbl{color:var(--muted2);}
.sum-val{font-weight:500;}
.sum-disc{color:var(--green)!important;}
.sum-div{border:none;border-top:1px dashed var(--border2);margin:2px 0;}
.sum-total{font-size:15px;font-weight:700;}
.sum-total .sum-val{color:var(--accent);font-size:16px;}

/* PAYMENT */
.pay-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);}
.pay-methods{display:grid;grid-template-columns:repeat(2,1fr);gap:7px;}
.pay-btn{padding:10px 5px;border:1px solid var(--border2);border-radius:10px;background:var(--surface2);font-size:11px;font-weight:600;color:var(--muted2);display:flex;flex-direction:column;align-items:center;gap:5px;transition:all var(--tr);}
.pay-btn i{font-size:17px;}
.pay-btn:hover{background:var(--accent-soft);border-color:rgba(233,30,140,.35);color:var(--accent);}
.pay-btn.active{background:var(--accent-soft);border-color:rgba(233,30,140,.5);color:var(--accent);box-shadow:0 2px 10px var(--accent-glow);}

/* ── CASH TENDERED SECTION ── */
.cash-section{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:12px;display:flex;flex-direction:column;gap:9px;animation:slideUp .28s ease;}
.cash-section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);display:flex;align-items:center;gap:5px;}
.cash-input-wrap{position:relative;}
.cash-prefix{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:15px;font-weight:700;color:var(--accent);pointer-events:none;z-index:1;}
.cash-inp{width:100%;padding:11px 12px 11px 28px;background:var(--surface3);border:1.5px solid var(--border2);border-radius:10px;color:var(--text);font-size:18px;font-weight:700;transition:all var(--tr);}
.cash-inp:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);}
.cash-inp::placeholder{color:var(--muted);font-weight:400;font-size:13px;}
/* Quick amount buttons */
.quick-amounts{display:flex;gap:5px;flex-wrap:wrap;}
.qty-quick{padding:5px 10px;border-radius:7px;background:var(--surface3);border:1px solid var(--border2);font-size:11.5px;font-weight:600;color:var(--muted2);transition:all var(--tr);}
.qty-quick:hover{background:var(--accent-soft);border-color:rgba(233,30,140,.35);color:var(--accent);}
/* Change display */
.change-display{border-radius:10px;padding:10px 13px;display:flex;justify-content:space-between;align-items:center;transition:all .2s ease;}
.change-display.positive{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);}
.change-display.negative{background:rgba(239,68,68,.10);border:1px solid rgba(239,68,68,.22);}
.change-display.zero{background:var(--surface3);border:1px solid var(--border2);}
.change-label{font-size:12px;font-weight:600;color:var(--muted2);}
.change-label.positive,.change-label.exact{color:var(--green);}
.change-label.negative{color:var(--red);}
.change-amount{font-size:16px;font-weight:800;}
.change-amount.positive,.change-amount.exact{color:var(--green);}
.change-amount.negative{color:var(--red);}
.change-amount.zero{color:var(--muted2);}

/* PLACE ORDER */
.btn-place{background:var(--accent);color:#fff;font-family:'Playfair Display',serif;font-size:15px;font-weight:700;padding:15px;border-radius:12px;width:100%;display:flex;align-items:center;justify-content:center;gap:8px;transition:all var(--tr);box-shadow:0 4px 20px var(--accent-glow);}
.btn-place:hover{background:var(--accent-hover);transform:translateY(-2px);box-shadow:0 8px 26px rgba(233,30,140,.45);}
.btn-place:active{transform:translateY(0);}
.btn-place:disabled{opacity:.35;pointer-events:none;}

/* ── RECEIPT MODAL ── */
.receipt-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:2000;display:flex;align-items:center;justify-content:center;padding:20px;animation:fadeIn .22s ease;}
.receipt-modal{background:var(--surface);border:1px solid var(--border2);border-radius:20px;width:100%;max-width:400px;max-height:88vh;overflow-y:auto;animation:slideUp .3s ease;box-shadow:0 24px 60px rgba(0,0,0,.55);}
.receipt-header{padding:22px 22px 14px;border-bottom:1px solid var(--border);text-align:center;position:relative;}
.receipt-store-name{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;}
.receipt-store-name span{color:var(--accent);}
.receipt-subtitle{font-size:12px;color:var(--muted);margin-top:3px;}
.receipt-confirmed-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:var(--green);font-size:11.5px;font-weight:700;padding:4px 12px;border-radius:20px;margin-top:10px;}
.receipt-close-x{position:absolute;top:14px;right:14px;width:30px;height:30px;border-radius:8px;background:var(--surface3);color:var(--muted2);display:flex;align-items:center;justify-content:center;font-size:13px;transition:all var(--tr);}
.receipt-close-x:hover{background:var(--red);color:#fff;}
.receipt-meta{padding:12px 22px;display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12px;color:var(--muted2);border-bottom:1px solid var(--border);background:var(--surface2);}
.receipt-meta strong{color:var(--text);}
.receipt-items{padding:12px 22px;}
.receipt-item-row{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px dashed var(--border);}
.receipt-item-row:last-child{border-bottom:none;}
.ri-name{flex:1;font-size:13px;font-weight:600;}
.ri-qty{font-size:11.5px;color:var(--muted);background:var(--surface3);padding:1px 7px;border-radius:5px;flex-shrink:0;}
.ri-subtotal{font-size:13px;font-weight:700;color:var(--accent);flex-shrink:0;}
.receipt-totals{padding:12px 22px;background:var(--surface2);border-top:1px solid var(--border);border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:7px;}
.rt-row{display:flex;justify-content:space-between;font-size:13px;}
.rt-lbl{color:var(--muted2);}
.rt-val{font-weight:500;}
.rt-disc{color:var(--green)!important;}
.rt-div{border:none;border-top:1px dashed var(--border2);margin:3px 0;}
.rt-total-row{display:flex;justify-content:space-between;font-size:16px;font-weight:800;}
.rt-total-row .rt-val{color:var(--accent);}
/* Cash rows in receipt */
.receipt-cash{padding:12px 22px;display:flex;flex-direction:column;gap:8px;}
.rc-row{display:flex;justify-content:space-between;align-items:center;font-size:13px;}
.rc-lbl{color:var(--muted2);}
.rc-val{font-weight:600;}
.rc-change-box{display:flex;justify-content:space-between;align-items:center;background:rgba(34,197,94,.10);border:1px solid rgba(34,197,94,.25);border-radius:10px;padding:11px 14px;margin-top:2px;}
.rc-change-box .rc-lbl{color:var(--green);font-weight:700;font-size:13px;display:flex;align-items:center;gap:5px;}
.rc-change-box .rc-val{color:var(--green);font-size:17px;font-weight:800;}
/* Receipt footer */
.receipt-footer-btns{padding:14px 22px 20px;display:flex;gap:9px;justify-content:center;}
.btn-print{background:var(--accent);color:#fff;font-weight:700;font-size:13px;padding:11px 22px;border-radius:10px;display:inline-flex;align-items:center;gap:7px;transition:all var(--tr);box-shadow:0 4px 14px var(--accent-glow);}
.btn-print:hover{background:var(--accent-hover);transform:translateY(-1px);}
.btn-new-order{background:var(--surface3);color:var(--muted2);font-weight:600;font-size:13px;padding:11px 18px;border-radius:10px;display:inline-flex;align-items:center;gap:6px;transition:all var(--tr);border:1px solid var(--border2);}
.btn-new-order:hover{background:var(--surface2);color:var(--text);}

/* TOAST */
.pos-toast{position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(80px);background:var(--green);color:#fff;padding:12px 22px;border-radius:10px;font-weight:600;font-size:13.5px;transition:transform .35s cubic-bezier(.4,0,.2,1);z-index:9999;box-shadow:0 8px 24px rgba(34,197,94,.35);display:flex;align-items:center;gap:8px;white-space:nowrap;pointer-events:none;}
.pos-toast.show{transform:translateX(-50%) translateY(0);}

/* MODAL overrides */
.modal-content{background:var(--surface)!important;border:1px solid var(--border2)!important;color:var(--text)!important;border-radius:var(--radius)!important;}
.modal-header,.modal-footer{border-color:var(--border)!important;background:var(--surface2)!important;}
.form-control,.form-select{background:var(--surface2)!important;border:1px solid var(--border2)!important;color:var(--text)!important;border-radius:var(--radius-sm)!important;}
.form-control:focus,.form-select:focus{box-shadow:0 0 0 3px var(--accent-glow)!important;border-color:var(--accent)!important;}
.form-control::placeholder{color:var(--muted)!important;}
.form-label{color:var(--muted2);font-size:13px;font-weight:600;}

/* ── CUSTOMIZATION MODAL ── */
.custom-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:2500;display:flex;align-items:center;justify-content:center;padding:20px;animation:fadeIn .22s ease;}
.custom-modal{background:var(--surface);border:1px solid var(--border2);border-radius:20px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;animation:slideUp .3s ease;box-shadow:0 24px 60px rgba(0,0,0,.55);}
.custom-header{padding:18px 20px 14px;border-bottom:1px solid var(--border);position:relative;display:flex;align-items:center;gap:13px;}
.custom-header-img{width:56px;height:56px;border-radius:12px;background:var(--surface2);display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0;overflow:hidden;}
.custom-header-img img{width:100%;height:100%;object-fit:cover;border-radius:12px;}
.custom-header-info{flex:1;min-width:0;}
.custom-item-name{font-family:'Playfair Display',serif;font-size:17px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.custom-item-price{font-size:14px;color:var(--accent);font-weight:700;margin-top:2px;}
.custom-close-x{position:absolute;top:14px;right:14px;width:30px;height:30px;border-radius:8px;background:var(--surface3);color:var(--muted2);display:flex;align-items:center;justify-content:center;font-size:13px;transition:all var(--tr);}
.custom-close-x:hover{background:var(--red);color:#fff;}
.custom-section{padding:14px 20px;}
.custom-section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.custom-section-title i{font-size:11px;}
/* Remove ingredients */
.ing-grid{display:flex;flex-wrap:wrap;gap:7px;}
.ing-chip{display:flex;align-items:center;gap:5px;padding:6px 11px;border-radius:50px;border:1px solid var(--border2);background:var(--surface2);font-size:12px;font-weight:500;color:var(--muted2);cursor:pointer;transition:all var(--tr);user-select:none;}
.ing-chip:hover{border-color:var(--red);color:var(--red);background:rgba(239,68,68,.08);}
.ing-chip.removed{background:rgba(239,68,68,.10);border-color:rgba(239,68,68,.4);color:var(--red);text-decoration:line-through;opacity:.7;}
.ing-chip i{font-size:10px;}
/* Add-ons */
.addon-list{display:flex;flex-direction:column;gap:7px;}
.addon-row{display:flex;align-items:center;gap:10px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:10px 13px;transition:all var(--tr);}
.addon-row:hover{border-color:var(--border2);}
.addon-row.selected{border-color:rgba(233,30,140,.4);background:var(--accent-soft);}
.addon-info{flex:1;min-width:0;}
.addon-name{font-size:13px;font-weight:600;}
.addon-price{font-size:11.5px;color:var(--accent);margin-top:1px;font-weight:600;}
.addon-qty-ctrl{display:flex;align-items:center;gap:7px;flex-shrink:0;}
.addon-qty-btn{width:26px;height:26px;border-radius:7px;background:var(--surface3);color:var(--muted2);font-size:11px;display:flex;align-items:center;justify-content:center;transition:all var(--tr);}
.addon-qty-btn:hover{background:var(--accent);color:#fff;}
.addon-qty-v{font-size:13px;font-weight:700;min-width:18px;text-align:center;}
/* Special instructions */
.custom-notes{width:100%;padding:10px 12px;background:var(--surface2);border:1.5px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;font-family:inherit;resize:none;transition:all var(--tr);min-height:68px;}
.custom-notes:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);}
.custom-notes::placeholder{color:var(--muted);}
/* Footer */
.custom-footer{padding:14px 20px 20px;display:flex;flex-direction:column;gap:10px;border-top:1px solid var(--border);}
.custom-total-row{display:flex;justify-content:space-between;align-items:center;font-size:14px;}
.custom-total-lbl{color:var(--muted2);font-weight:500;}
.custom-total-val{font-size:18px;font-weight:800;color:var(--accent);}
.btn-add-custom{background:var(--accent);color:#fff;font-weight:700;font-size:14px;padding:13px;border-radius:12px;width:100%;display:flex;align-items:center;justify-content:center;gap:8px;transition:all var(--tr);box-shadow:0 4px 20px var(--accent-glow);}
.btn-add-custom:hover{background:var(--accent-hover);transform:translateY(-1px);}
/* Cart customization notes */
.ci-custom-notes{font-size:10.5px;color:var(--muted);margin-top:2px;line-height:1.4;}
.ci-custom-notes span{display:inline-block;background:var(--surface3);border-radius:4px;padding:1px 5px;margin:1px 2px 1px 0;}
.ci-custom-notes .removed-note{color:var(--red);background:rgba(239,68,68,.1);}
.ci-custom-notes .addon-note{color:var(--accent);background:var(--accent-soft);}

/* Light mode overrides */
[data-theme="light"] .pos-sidebar{background:#fff;border-right-color:rgba(233,30,140,.12);}
[data-theme="light"] .pos-order{background:#fff;border-left-color:rgba(233,30,140,.12);}
[data-theme="light"] .product-card{background:#fff;box-shadow:0 2px 8px rgba(233,30,140,.06);}
[data-theme="light"] .cart-item{background:#fdf4f9;}
[data-theme="light"] .o-summary{background:#fdf4f9;}
[data-theme="light"] .stat-card{background:#fff;box-shadow:0 2px 8px rgba(233,30,140,.06);}
[data-theme="light"] .btn-add,[data-theme="light"] .card-add-btn,[data-theme="light"] .btn-place{color:#fff;}
[data-theme="light"] .o-tab.active{color:#fff;}
[data-theme="light"] .cat-pill.active{color:#fff;}
[data-theme="light"] .cash-section{background:#fdf4f9;}
[data-theme="light"] .cash-inp{background:#fff;border-color:rgba(233,30,140,.2);}
[data-theme="light"] .qty-quick{background:#fff;}
[data-theme="light"] .receipt-modal{background:#fff;}
[data-theme="light"] .receipt-meta,[data-theme="light"] .receipt-totals{background:#fdf4f9;}

@media(max-width:1200px){:root{--order-w:295px;}}
@media(max-width:1024px){.stats-strip{display:none;}}
/* ── MOBILE RESPONSIVE ── */
@media(max-width:860px){
  /* Body stacks vertically */
  body{flex-direction:column;}

  /* Sidebar becomes a bottom nav bar */
  .pos-sidebar{
    width:100%;height:56px;flex-direction:row;
    border-right:none;border-top:1px solid var(--border);
    padding:0 4px;gap:0;
    order:2;flex-shrink:0;
    position:fixed;bottom:0;left:0;right:0;z-index:200;
  }
  .s-logo{display:none;}
  .s-spacer{display:none;}
  .s-theme{width:36px;height:36px;margin:0;flex-shrink:0;}
  .s-avatar{width:30px;height:30px;font-size:11px;margin:0;flex-shrink:0;}
  .nav-btn{
    flex:1;height:100%;border-radius:0;
    font-size:8px;gap:2px;flex-direction:column;padding:4px 2px;
  }
  .nav-btn i{font-size:15px;}
  .nav-btn.active::before{
    left:50%;top:-1px;transform:translateX(-50%);
    width:26px;height:3px;border-radius:0 0 3px 3px;
  }
  .nav-logout{font-size:8px;}

  /* Main fills full screen */
  html,body{height:100%;overflow:hidden;}
  .pos-main{
    order:1;flex:1;
    padding:14px 12px 70px;
    overflow-y:auto;
    min-height:0;
  }

  /* Product grid: 2 columns */
  .product-grid{grid-template-columns:repeat(2,1fr);gap:9px;}
  .card-img-w{height:86px;min-height:86px;}
  .card-body-i{padding:8px;}
  .card-name{font-size:12px;}
  .card-price{font-size:13px;}
  .card-add-btn{opacity:1;transform:translateY(0);font-size:11.5px;padding:7px;}

  /* Search full width */
  .pos-search{max-width:100%;margin-left:0;flex:1;}
  .pos-topbar{flex-wrap:wrap;gap:7px;}
  .pos-title{font-size:19px;}
  #liveClock{font-size:10.5px;padding:4px 7px;}

  /* Side panels: full screen above bottom nav */
  .side-panel{left:0;top:0;bottom:56px;z-index:600;}
  .panel-header{padding:16px 16px 12px;}
  .panel-body{padding:14px 16px;}

  /* Order panel becomes a bottom sheet */
  .pos-order{
    display:flex!important;
    position:fixed;
    bottom:56px;left:0;right:0;
    height:0;max-height:88vh;
    border-left:none;border-top:1px solid var(--border2);
    border-radius:20px 20px 0 0;
    width:100%;
    z-index:700;
    overflow:hidden;
    transition:height .32s cubic-bezier(.4,0,.2,1);
    padding:0;
  }
  .pos-order.mobile-open{
    height:82vh;
    padding:0 16px 16px;
    overflow-y:auto;
  }
  /* Handle bar at top of sheet */
  .pos-order::before{
    content:'';
    display:block;
    width:40px;height:4px;
    background:var(--border2);
    border-radius:2px;
    margin:12px auto 8px;
    flex-shrink:0;
    position:sticky;top:0;
    z-index:1;
  }

  /* Mobile Cart FAB */
  #mobileCartBtn{
    display:flex;
    position:fixed;
    bottom:66px;right:14px;
    width:54px;height:54px;
    background:var(--accent);
    border-radius:50%;
    align-items:center;justify-content:center;
    font-size:21px;color:#fff;
    box-shadow:0 6px 20px var(--accent-glow);
    z-index:300;
    border:none;cursor:pointer;
    transition:transform .18s cubic-bezier(.34,1.56,.64,1),box-shadow var(--tr);
  }
  #mobileCartBtn:active{transform:scale(0.90);}
  #mobileCartCount{
    position:absolute;top:-2px;right:-2px;
    min-width:19px;height:19px;
    background:var(--red);color:#fff;
    border-radius:10px;font-size:10px;font-weight:800;
    display:flex;align-items:center;justify-content:center;
    border:2px solid var(--surface);
    padding:0 3px;
  }
}

@media(min-width:861px){
  #mobileCartBtn{display:none!important;}
  #mobileCartOverlay{display:none!important;}
}

/* ── SIDE PANELS ── */
.side-panel{position:fixed;top:0;left:var(--sidebar-w);right:0;bottom:0;background:var(--bg);z-index:500;display:none;flex-direction:column;overflow:hidden;animation:fadeIn .2s ease;}
.side-panel.open{display:flex;}
.panel-header{display:flex;align-items:center;gap:14px;padding:22px 28px 18px;border-bottom:1px solid var(--border);flex-shrink:0;background:var(--surface);}
.panel-header-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;background:var(--accent-soft);color:var(--accent);flex-shrink:0;}
.panel-title{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;flex:1;}
.panel-title span{color:var(--accent);}
.panel-close{width:38px;height:38px;border-radius:10px;background:var(--surface2);border:1px solid var(--border2);color:var(--muted2);font-size:15px;display:flex;align-items:center;justify-content:center;transition:all var(--tr);}
.panel-close:hover{background:var(--red);color:#fff;border-color:var(--red);}
.panel-body{flex:1;overflow-y:auto;padding:24px 28px;}

/* Panel grid cards */
.panel-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:28px;}
.panel-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;display:flex;flex-direction:column;gap:10px;transition:all var(--tr);}
.panel-card:hover{border-color:var(--border2);transform:translateY(-2px);box-shadow:0 8px 24px var(--accent-glow);}
.panel-card-ic{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.panel-card-v{font-size:26px;font-weight:800;line-height:1.1;}
.panel-card-l{font-size:12px;color:var(--muted);margin-top:2px;}
.panel-card-trend{font-size:11px;font-weight:600;margin-top:4px;}
.panel-card-trend.up{color:var(--green);}
.panel-card-trend.flat{color:var(--muted);}

/* Section title */
.panel-section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:14px;margin-top:4px;}

/* Table */
.panel-table{width:100%;border-collapse:collapse;font-size:13px;}
.panel-table th{text-align:left;padding:8px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);border-bottom:1px solid var(--border2);}
.panel-table td{padding:11px 14px;border-bottom:1px solid var(--border);vertical-align:middle;}
.panel-table tr:last-child td{border-bottom:none;}
.panel-table tr:hover td{background:var(--surface2);}
.badge-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.badge-pill.green{background:rgba(34,197,94,.12);color:var(--green);border:1px solid rgba(34,197,94,.25);}
.badge-pill.red{background:rgba(239,68,68,.10);color:var(--red);border:1px solid rgba(239,68,68,.2);}
.badge-pill.blue{background:rgba(59,130,246,.10);color:var(--blue);border:1px solid rgba(59,130,246,.2);}
.badge-pill.pink{background:var(--accent-soft);color:var(--accent);border:1px solid rgba(233,30,140,.25);}
.badge-pill.gray{background:var(--surface3);color:var(--muted2);border:1px solid var(--border2);}

/* Menu panel grid */
.menu-panel-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;}
.menu-panel-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px;display:flex;align-items:center;gap:14px;transition:all var(--tr);}
.menu-panel-card:hover{border-color:rgba(233,30,140,.35);transform:translateY(-2px);box-shadow:0 8px 20px var(--accent-glow);}
.menu-panel-emoji{width:48px;height:48px;background:var(--surface2);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;}
.menu-panel-info{flex:1;min-width:0;}
.menu-panel-name{font-weight:600;font-size:13.5px;}
.menu-panel-cat{font-size:11px;color:var(--muted);margin-top:2px;}
.menu-panel-price{font-size:15px;font-weight:800;color:var(--accent);margin-top:4px;}

/* ── Real-time menu availability states ── */
/* Product card — unavailable (greyed out, unclickable) */
.product-card.unavailable{
  opacity:.45;
  filter:grayscale(.7);
  pointer-events:none;
  cursor:not-allowed;
  border-color:var(--border)!important;
  box-shadow:none!important;
}
.product-card.unavailable .card-add-btn{display:none;}
.product-card.unavailable .card-img-w::after{
  content:'Unavailable';
  position:absolute;inset:0;
  background:rgba(0,0,0,.52);
  color:#fff;
  font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;
  display:flex;align-items:center;justify-content:center;
  border-radius:0;
  pointer-events:none;
}
/* Flash animation when a card changes state */
@keyframes menuStateFlash{
  0%,100%{box-shadow:0 0 0 0 transparent;}
  25%{box-shadow:0 0 0 4px rgba(239,68,68,.5);}
  75%{box-shadow:0 0 0 4px rgba(34,197,94,.4);}
}
.product-card.state-changed{animation:menuStateFlash .8s ease;}

/* Menu panel card — unavailable */
.menu-panel-card.unavailable{
  opacity:.45;
  filter:grayscale(.7);
  pointer-events:none;
}
.menu-panel-card .avail-badge{
  font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;
  display:inline-flex;align-items:center;gap:4px;margin-top:5px;
  white-space:nowrap;
}
.avail-badge.on{ background:rgba(34,197,94,.12);color:var(--green);border:1px solid rgba(34,197,94,.3);}
.avail-badge.off{background:rgba(239,68,68,.10);color:var(--red);  border:1px solid rgba(239,68,68,.25);}

/* Real-time menu sync indicator in panel header */
.menu-rt-indicator{
  display:flex;align-items:center;gap:6px;
  font-size:11px;font-weight:600;color:var(--muted2);
  background:var(--surface2);border:1px solid var(--border);
  border-radius:20px;padding:4px 10px;
  transition:color .3s,border-color .3s;
}
.menu-rt-indicator .rt-dot{
  width:7px;height:7px;border-radius:50%;
  background:var(--muted);
  transition:background .3s;
  flex-shrink:0;
}
.menu-rt-indicator.live .rt-dot{background:var(--green);animation:pulse 2s infinite;}
.menu-rt-indicator.live{color:var(--green);border-color:rgba(34,197,94,.3);}
.menu-rt-indicator.syncing .rt-dot{background:#eab308;animation:none;}
.menu-rt-indicator.syncing{color:#eab308;border-color:rgba(234,179,8,.3);}

/* History panel */
.history-item{display:flex;align-items:center;gap:14px;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:13px 16px;margin-bottom:10px;transition:all var(--tr);}
.history-item:hover{border-color:var(--border2);}
.history-ic{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.history-info{flex:1;min-width:0;}
.history-id{font-weight:700;font-size:13.5px;}
.history-meta{font-size:11.5px;color:var(--muted);margin-top:2px;}
.history-amt{font-size:14px;font-weight:800;color:var(--accent);text-align:right;flex-shrink:0;}

/* Reports panel */
.report-bar-wrap{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:16px;}
.report-bar-title{font-size:13px;font-weight:600;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;}
.report-bar-title span{font-size:11px;color:var(--muted);font-weight:400;}
.bar-row{display:flex;align-items:center;gap:12px;margin-bottom:10px;}
.bar-label{font-size:12px;color:var(--muted2);width:90px;flex-shrink:0;}
.bar-track{flex:1;height:8px;background:var(--surface3);border-radius:4px;overflow:hidden;}
.bar-fill{height:100%;border-radius:4px;background:var(--accent);transition:width .6s ease;}
.bar-val{font-size:12px;font-weight:700;color:var(--accent);width:60px;text-align:right;flex-shrink:0;}

/* Light overrides for panels */
[data-theme="light"] .side-panel{background:#fff;}
[data-theme="light"] .panel-header{background:#fff;}
[data-theme="light"] .panel-card{background:#fff;}
[data-theme="light"] .menu-panel-card{background:#fff;}
[data-theme="light"] .history-item{background:#fff;}
[data-theme="light"] .report-bar-wrap{background:#fff;}

/* ════════════════════════════════════
   EMPRESS POS — Enhanced Animations
   ════════════════════════════════════ */

/* ── Extra keyframes ── */
@keyframes ripple {
  0%   { transform: scale(0); opacity: 0.6; }
  100% { transform: scale(4); opacity: 0; }
}
@keyframes cartBounce {
  0%,100% { transform: scale(1); }
  30%      { transform: scale(1.35); }
  60%      { transform: scale(0.9); }
  80%      { transform: scale(1.1); }
}
@keyframes shimmerLoad {
  0%   { background-position: -400px 0; }
  100% { background-position: 400px 0; }
}
@keyframes popIn {
  0%   { opacity:0; transform: scale(0.8) translateY(8px); }
  70%  { transform: scale(1.04) translateY(-2px); }
  100% { opacity:1; transform: scale(1) translateY(0); }
}
@keyframes slideRight {
  from { opacity:0; transform: translateX(-18px); }
  to   { opacity:1; transform: translateX(0); }
}
@keyframes glowPulse {
  0%,100% { box-shadow: 0 0 0 0 var(--accent-glow); }
  50%      { box-shadow: 0 0 0 10px transparent; }
}
@keyframes toastSlide {
  0%   { transform: translateX(-50%) translateY(80px) scale(0.9); opacity:0; }
  60%  { transform: translateX(-50%) translateY(-4px) scale(1.02); }
  100% { transform: translateX(-50%) translateY(0) scale(1); opacity:1; }
}
@keyframes badgePop {
  0%   { transform: scale(0); }
  60%  { transform: scale(1.3); }
  100% { transform: scale(1); }
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}
@keyframes floatUp {
  0%   { opacity:1; transform: translateY(0) scale(1); }
  100% { opacity:0; transform: translateY(-40px) scale(0.7); }
}
@keyframes borderFlow {
  0%,100% { border-color: rgba(233,30,140,0.3); }
  50%      { border-color: rgba(233,30,140,0.8); }
}

/* ── Product card enhancements ── */
.product-card {
  position: relative;
  overflow: hidden;
  will-change: transform;
}
.product-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(233,30,140,0.06) 0%, transparent 60%);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
  z-index: 0;
}
.product-card:hover::before { opacity: 1; }
.product-card:hover {
  transform: translateY(-4px) scale(1.012);
  box-shadow: 0 12px 32px rgba(233,30,140,0.2), 0 2px 8px rgba(0,0,0,0.3) !important;
  border-color: rgba(233,30,140,0.35) !important;
}
.product-card:active { transform: scale(0.97); }

/* Ripple on product card click */
.ripple-effect {
  position: absolute;
  border-radius: 50%;
  background: rgba(233,30,140,0.3);
  animation: ripple 0.6s ease-out forwards;
  pointer-events: none;
  z-index: 10;
}

/* ── Cart item enter animation ── */
.cart-item {
  animation: popIn 0.28s cubic-bezier(0.34,1.56,0.64,1) both;
  transition: transform 0.2s ease, opacity 0.2s ease, max-height 0.3s ease, margin 0.3s ease;
}
.cart-item.removing {
  animation: none;
  opacity: 0;
  transform: translateX(20px) scale(0.95);
  max-height: 0;
  margin: 0;
  overflow: hidden;
}

/* ── Qty button spring ── */
.qty-btn {
  transition: transform 0.15s cubic-bezier(0.34,1.56,0.64,1), background 0.18s ease !important;
}
.qty-btn:active { transform: scale(0.85) !important; }

/* ── Cart badge bounce ── */
.cart-badge-bounce {
  animation: cartBounce 0.45s cubic-bezier(0.34,1.56,0.64,1);
}

/* ── Stat cards enhanced ── */
.stat-card {
  transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.25s ease, border-color 0.25s ease !important;
}
.stat-card:hover {
  animation: glowPulse 2s ease infinite;
}
.stat-card .stat-v {
  transition: all 0.3s ease;
}

/* ── Category pills ── */
.cat-pill {
  transition: all 0.22s cubic-bezier(0.34,1.56,0.64,1) !important;
  position: relative;
  overflow: hidden;
}
.cat-pill:active { transform: scale(0.93); }
.cat-pill.active {
  animation: borderFlow 2.5s ease infinite;
}

/* ── Sidebar nav enhanced ── */
.nav-btn {
  transition: all 0.2s cubic-bezier(0.34,1.56,0.64,1) !important;
  position: relative;
  overflow: hidden;
}
.nav-btn:hover { transform: scale(1.08); }
.nav-btn:active { transform: scale(0.92); }
.nav-btn.active { animation: none; }

/* ── Place order button ── */
.btn-place {
  position: relative;
  overflow: hidden;
}
.btn-place:not(:disabled):hover {
  animation: glowPulse 1.5s ease infinite;
}
.btn-place::after {
  content: '';
  position: absolute;
  inset: 50%;
  width: 0; height: 0;
  background: rgba(255,255,255,0.2);
  border-radius: 50%;
  transition: all 0.5s ease;
  opacity: 0;
}
.btn-place:active::after {
  inset: -10%;
  width: 120%; height: 120%;
  opacity: 1;
  transition: 0s;
}

/* ── Toast enhanced ── */
.pos-toast.show {
  animation: toastSlide 0.45s cubic-bezier(0.34,1.56,0.64,1) forwards !important;
}

/* ── Search input focus ring ── */
.pos-search input:focus {
  transform: scale(1.01);
}

/* ── Panel slide-in ── */
.side-panel.open {
  animation: slideRight 0.3s cubic-bezier(0.34,1.2,0.64,1) both !important;
}

/* ── Panel cards stagger ── */
.panel-card {
  transition: transform 0.2s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.2s ease, border-color 0.2s ease !important;
}
.panel-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 24px rgba(233,30,140,0.15) !important;
  border-color: rgba(233,30,140,0.3) !important;
}

/* ── Receipt modal entrance ── */
.receipt-modal {
  animation: popIn 0.35s cubic-bezier(0.34,1.56,0.64,1) both !important;
}
.custom-modal {
  animation: popIn 0.35s cubic-bezier(0.34,1.56,0.64,1) both !important;
}

/* ── History item hover ── */
.history-item {
  transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease !important;
}
.history-item:hover {
  transform: translateX(4px);
  border-color: rgba(233,30,140,0.3) !important;
  box-shadow: 0 4px 16px rgba(233,30,140,0.1) !important;
}

/* ── Logo pulse ── */
.s-logo {
  animation: glowPulse 3s ease infinite;
}

/* ── Theme toggle rotate ── */
.s-theme.spinning i {
  animation: spin 0.4s ease;
}

/* ── Stats strip entrance ── */
.stat-card:nth-child(1) { animation-delay: 0.05s; }
.stat-card:nth-child(2) { animation-delay: 0.1s; }
.stat-card:nth-child(3) { animation-delay: 0.15s; }
.stat-card:nth-child(4) { animation-delay: 0.2s; }

/* ── Product grid stagger via JS class ── */
.product-card.revealed {
  animation: popIn 0.32s cubic-bezier(0.34,1.56,0.64,1) both;
}

/* ── Cart empty state ── */
.empty-cart {
  transition: opacity 0.3s ease;
}
.empty-cart i {
  animation: fadeUp 1.5s ease infinite alternate;
}
@keyframes fadeUp {
  from { transform: translateY(0); opacity: 0.4; }
  to   { transform: translateY(-6px); opacity: 0.8; }
}

/* ── Discount button active flash ── */
.btn-discount.active {
  animation: discountPop 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes discountPop {
  0%   { transform: scale(0.92); }
  100% { transform: scale(1); }
}

/* ── Summary total highlight on change ── */
.sum-total .sum-val {
  transition: color 0.3s ease, transform 0.2s ease;
  display: inline-block;
}
.sum-total .sum-val.updated {
  animation: cartBounce 0.35s ease;
}

/* ── Scrollbar themed ── */
.cart-scroll::-webkit-scrollbar { width: 3px; }
.cart-scroll::-webkit-scrollbar-track { background: transparent; }
.cart-scroll::-webkit-scrollbar-thumb { background: rgba(233,30,140,0.3); border-radius: 3px; }
.cart-scroll::-webkit-scrollbar-thumb:hover { background: var(--accent); }

/* ── Pay modal ── */
.pay-btn {
  transition: all 0.2s cubic-bezier(0.34,1.56,0.64,1) !important;
}
.pay-btn:hover { transform: translateY(-2px); }

/* ── Bar fills animate on open ── */
.bar-fill {
  transition: width 0.8s cubic-bezier(0.4,0,0.2,1) !important;
}

/* ── Float-up score particle ── */
.score-particle {
  position: fixed;
  font-size: 14px;
  font-weight: 800;
  color: var(--green);
  pointer-events: none;
  z-index: 9999;
  animation: floatUp 1s ease forwards;
}

/* ── Top bar clock ── */
#liveClock {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted2);
  letter-spacing: 0.05em;
  padding: 5px 10px;
  background: var(--surface2);
  border-radius: 8px;
  border: 1px solid var(--border);
  font-variant-numeric: tabular-nums;
  transition: color 0.3s ease;
}

</style>
</head>
<body>

<!-- ── Sidebar ─────────────────────────────────────────────── -->
<aside class="pos-sidebar">
  <div class="s-logo"><i class="fa-solid fa-crown" style="font-size:18px;"></i></div>
  <button class="nav-btn active" title="Orders"><i class="fa-solid fa-receipt"></i>Orders</button>
  <button class="nav-btn" title="Menu"><i class="fa-solid fa-utensils"></i>Menu</button>
  <button class="nav-btn" title="History" style="position:relative">
    <i class="fa-solid fa-clock-rotate-left"></i>History
    <span class="bdot"></span>
  </button>
  <button class="nav-btn" title="Reports"><i class="fa-solid fa-file-invoice-dollar"></i>Reports</button>
  <div class="s-spacer"></div>
  <button class="s-theme" id="themeBtn" title="Toggle theme"><i class="fa-solid fa-sun" id="themeIco"></i></button>
  <a href="../Backend/logout.php" class="nav-btn nav-logout" title="Log Out">
    <i class="fa-solid fa-right-from-bracket"></i>Logout
  </a>
  <?php
    $av_firstname = $_SESSION['firstname'] ?? '';
    $av_lastname  = $_SESSION['lastname']  ?? '';
    $av_image     = $_SESSION['image']     ?? '';
    // Fallback: use session user (email) if no name
    if (empty($av_firstname)) {
        $av_fallback  = $_SESSION['user'] ?? 'User';
        // If it looks like an email, use the part before @
        $av_firstname = strpos($av_fallback, '@') !== false
            ? explode('@', $av_fallback)[0]
            : $av_fallback;
    }
    // Build initials: up to 2 chars
    $av_initials = strtoupper(substr($av_firstname, 0, 1) . substr($av_lastname, 0, 1));
    if (empty($av_initials)) $av_initials = '?';
    $av_title = htmlspecialchars(trim($av_firstname . ' ' . $av_lastname));
  ?>
  <div class="s-avatar" title="<?= $av_title ?>">
    <?php if (!empty($av_image)): ?>
      <img src="../<?= htmlspecialchars($av_image) ?>" alt="<?= $av_title ?>"
           style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
    <?php else: ?>
      <?= htmlspecialchars($av_initials) ?>
    <?php endif; ?>
  </div>
</aside>

<!-- ── Mobile Cart FAB ─────────────────────────────────────── -->
<button id="mobileCartBtn" onclick="openMobileCart()" aria-label="View Cart">
  <i class="fa-solid fa-cart-shopping"></i>
  <span id="mobileCartCount" style="display:none">0</span>
</button>

<!-- ── Mobile Cart Overlay ─────────────────────────────────── -->
<div id="mobileCartOverlay" onclick="closeMobileCart()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:699;"></div>
<main class="pos-main">
  <div class="pos-topbar">
    <div class="pos-title">New <span>Order</span></div>
    <span id="liveClock"></span>
    <div class="pos-search">
      <i class="fa-solid fa-magnifying-glass si"></i>
      <input type="text" id="searchInput" placeholder="Search menu items…" autocomplete="off">
      <button class="sc" id="searchClear"><i class="fa-solid fa-xmark"></i></button>
    </div>
  </div>

  <div class="stats-strip">
    <div class="stat-card">
      <div class="stat-ic" style="background:var(--accent-soft)"><i class="fa-solid fa-fire" style="color:var(--accent)"></i></div>
      <div><div class="stat-v" id="statRevenue">₱0.00</div><div class="stat-l">Session Revenue</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-ic" style="background:rgba(34,197,94,.12)"><i class="fa-solid fa-bag-shopping" style="color:var(--green)"></i></div>
      <div><div class="stat-v" id="statOrders">0</div><div class="stat-l">Orders Placed</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-ic" style="background:rgba(59,130,246,.12)"><i class="fa-solid fa-utensils" style="color:var(--blue)"></i></div>
      <div><div class="stat-v"><?= count($menu_items) ?></div><div class="stat-l">Menu Items</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-ic" style="background:var(--accent-soft)"><i class="fa-solid fa-tags" style="color:var(--accent)"></i></div>
      <div><div class="stat-v"><?= count($cats_raw) ?></div><div class="stat-l">Categories</div></div>
    </div>
  </div>

  <div class="cats" id="catRow"></div>
  <div class="menu-area">
    <div class="sec-label">Menu Items</div>
    <div class="product-grid" id="productGrid"></div>
  </div>
</main>

<!-- ── Order Panel ─────────────────────────────────────────── -->
<aside class="pos-order">
  <div class="o-top">
    <div class="o-title">Bill Order <span id="cartBadge" style="display:none;background:var(--accent);color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;margin-left:6px;vertical-align:middle;font-family:'Outfit',sans-serif;"></span></div>
    <div class="o-date" id="orderDate"></div>
  </div>
  <div class="o-tabs">
    <button class="o-tab active" data-tab="dine"><i class="fa-solid fa-bowl-food"></i> Dine In</button>
    <button class="o-tab" data-tab="take"><i class="fa-solid fa-bag-shopping"></i> Takeaway</button>
    <button class="o-tab" data-tab="del"><i class="fa-solid fa-motorcycle"></i> Delivery</button>
  </div>
  <div class="tbl-row">
    <span class="tbl-label"><i class="fa-solid fa-hashtag" style="font-size:11px"></i> Number:</span>
    <input type="number" id="tableInput" class="tbl-number-inp" min="1" max="99" value="1" placeholder="#">
  </div>
  <div class="cart-scroll" id="cartScroll">
    <div class="empty-cart" id="emptyMsg">
      <i class="fa-solid fa-cart-shopping"></i>
      <strong>No items yet</strong>
      <small style="color:var(--muted);font-size:11.5px">Tap any item to add it here</small>
    </div>
  </div>
  <div class="discount-row">
    <div class="discount-label"><i class="fa-solid fa-percent" style="font-size:10px"></i> Discount</div>
    <div class="discount-btns">
      <button class="btn-discount" id="btnSenior" title="Senior Citizen 20% Discount">
        <i class="fa-solid fa-user-clock"></i> Senior <span class="disc-pct">20%</span>
      </button>
      <button class="btn-discount" id="btnPWD" title="PWD 20% Discount">
        <i class="fa-solid fa-wheelchair"></i> PWD <span class="disc-pct">20%</span>
      </button>
    </div>
  </div>
  <div class="o-summary">
    <div class="sum-row" id="orderTypeRow" style="padding-bottom:6px;border-bottom:1px dashed var(--border2);margin-bottom:2px;">
      <span class="sum-lbl"><i class="fa-solid fa-tag" style="font-size:10px;margin-right:3px"></i>Order Type</span>
      <span class="sum-val" id="sumOrderType" style="font-size:12px;background:var(--accent-soft);color:var(--accent);border:1px solid rgba(233,30,140,.25);padding:2px 9px;border-radius:20px;font-weight:700;">Dine In</span>
    </div>
    <div class="sum-row"><span class="sum-lbl">Subtotal</span><span class="sum-val" id="sumSub">₱ 0.00</span></div>
    <div class="sum-row" id="discRow" style="display:none"><span class="sum-lbl">Discount</span><span class="sum-val sum-disc" id="sumDisc">-₱ 0.00</span></div>
    <hr class="sum-div">
    <div class="sum-row sum-total"><span>Total</span><span class="sum-val" id="sumTotal">₱ 0.00</span></div>
  </div>

  <button class="btn-place" id="btnPlace" disabled>
    <i class="fa-solid fa-credit-card"></i> Pay & Place Order
  </button>
</aside>

<!-- ── Customization Modal ─────────────────────────────── -->
<div id="customModal" style="display:none;"></div>

<!-- ── Receipt Modal container ───────────────────────────── -->
<div id="receiptContainer"></div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- STATS PANEL                                               -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="side-panel" id="panel-stats">
  <div class="panel-header">
    <div class="panel-header-icon"><i class="fa-solid fa-chart-pie"></i></div>
    <div class="panel-title">Dashboard <span>Stats</span></div>
    <button class="panel-close" onclick="closePanel()"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="panel-body">
    <div class="panel-section-title">Session Overview</div>
    <div class="panel-grid">
      <div class="panel-card">
        <div class="panel-card-ic" style="background:var(--accent-soft)"><i class="fa-solid fa-fire" style="color:var(--accent)"></i></div>
        <div class="panel-card-v" id="ps-revenue">₱0.00</div>
        <div class="panel-card-l">Session Revenue</div>
        <div class="panel-card-trend flat"><i class="fa-solid fa-clock-rotate-left"></i> Current session</div>
      </div>
      <div class="panel-card">
        <div class="panel-card-ic" style="background:rgba(34,197,94,.12)"><i class="fa-solid fa-bag-shopping" style="color:var(--green)"></i></div>
        <div class="panel-card-v" id="ps-orders">0</div>
        <div class="panel-card-l">Orders Placed</div>
        <div class="panel-card-trend flat"><i class="fa-solid fa-clock-rotate-left"></i> Current session</div>
      </div>
      <div class="panel-card">
        <div class="panel-card-ic" style="background:rgba(59,130,246,.12)"><i class="fa-solid fa-utensils" style="color:var(--blue)"></i></div>
        <div class="panel-card-v"><?= count($menu_items) ?></div>
        <div class="panel-card-l">Active Menu Items</div>
        <div class="panel-card-trend up"><i class="fa-solid fa-circle-check"></i> All available</div>
      </div>
      <div class="panel-card">
        <div class="panel-card-ic" style="background:var(--accent-soft)"><i class="fa-solid fa-tags" style="color:var(--accent)"></i></div>
        <div class="panel-card-v"><?= count($cats_raw) ?></div>
        <div class="panel-card-l">Categories</div>
        <div class="panel-card-trend flat"><i class="fa-solid fa-layer-group"></i> Menu groups</div>
      </div>
      <div class="panel-card">
        <div class="panel-card-ic" style="background:rgba(34,197,94,.12)"><i class="fa-solid fa-calculator" style="color:var(--green)"></i></div>
        <div class="panel-card-v" id="ps-avg">₱0.00</div>
        <div class="panel-card-l">Avg. Order Value</div>
        <div class="panel-card-trend flat"><i class="fa-solid fa-chart-line"></i> Per order</div>
      </div>
    </div>

    <div class="panel-section-title">Category Breakdown</div>
    <div class="report-bar-wrap">
      <div class="report-bar-title">Items per Category <span>Live from menu</span></div>
      <?php foreach($cats_raw as $cat): ?>
      <?php $cnt = count(array_filter($menu_items, fn($i) => $i['category'] === $cat)); ?>
      <?php $pct = count($menu_items) ? round($cnt / count($menu_items) * 100) : 0; ?>
      <div class="bar-row">
        <span class="bar-label"><?= $cat_emoji[$cat] ?? '🍽️' ?> <?= htmlspecialchars($cat) ?></span>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
        <span class="bar-val"><?= $cnt ?> items</span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- MENU PANEL                                                -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="side-panel" id="panel-menu">
  <div class="panel-header">
    <div class="panel-header-icon"><i class="fa-solid fa-utensils"></i></div>
    <div class="panel-title">Menu <span>Items</span></div>
    <!-- Live sync indicator -->
    <div class="menu-rt-indicator" id="menuRtIndicator" title="Real-time menu sync">
      <span class="rt-dot"></span>
      <span id="menuRtLabel">Connecting…</span>
    </div>
    <button class="panel-close" onclick="closePanel()" style="margin-left:8px;"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="panel-body" id="menuPanelBody">
    <!-- Populated by JS renderMenuPanel() -->
    <div style="text-align:center;padding:60px 20px;color:var(--muted);">
      <i class="fa-solid fa-spinner fa-spin" style="font-size:30px;margin-bottom:12px;display:block;"></i>
      Loading menu…
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- HISTORY PANEL                                             -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="side-panel" id="panel-history">
  <div class="panel-header">
    <div class="panel-header-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
    <div class="panel-title">Order <span>History</span></div>
    <button class="panel-close" onclick="closePanel()"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="panel-body">
    <div class="panel-section-title">Session Orders</div>
    <div id="historyList">
      <div style="text-align:center;padding:60px 20px;color:var(--muted);">
        <i class="fa-solid fa-clock-rotate-left" style="font-size:40px;color:var(--surface3);display:block;margin-bottom:12px;"></i>
        <strong>No orders yet this session</strong><br>
        <small style="font-size:12px;">Completed orders will appear here</small>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- REPORTS PANEL                                             -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="side-panel" id="panel-reports">
  <div class="panel-header">
    <div class="panel-header-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
    <div class="panel-title">Sales <span>Reports</span></div>
    <button class="panel-close" onclick="closePanel()"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="panel-body">
    <div class="panel-section-title">Session Performance</div>
    <div class="panel-grid" style="margin-bottom:28px;">
      <div class="panel-card">
        <div class="panel-card-ic" style="background:var(--accent-soft)"><i class="fa-solid fa-peso-sign" style="color:var(--accent)"></i></div>
        <div class="panel-card-v" id="rpt-revenue">₱0.00</div>
        <div class="panel-card-l">Total Revenue</div>
      </div>
      <div class="panel-card">
        <div class="panel-card-ic" style="background:rgba(34,197,94,.12)"><i class="fa-solid fa-receipt" style="color:var(--green)"></i></div>
        <div class="panel-card-v" id="rpt-orders">0</div>
        <div class="panel-card-l">Total Orders</div>
      </div>
      <div class="panel-card">
        <div class="panel-card-ic" style="background:rgba(59,130,246,.12)"><i class="fa-solid fa-chart-line" style="color:var(--blue)"></i></div>
        <div class="panel-card-v" id="rpt-avg">₱0.00</div>
        <div class="panel-card-l">Avg. Order Value</div>
      </div>
      <div class="panel-card">
        <div class="panel-card-ic" style="background:rgba(234,179,8,.12)"><i class="fa-solid fa-star" style="color:#eab308"></i></div>
        <div class="panel-card-v" id="rpt-topitem">—</div>
        <div class="panel-card-l">Top Selling Item</div>
      </div>
    </div>

    <div class="panel-section-title">Revenue by Category</div>
    <div class="report-bar-wrap" id="rpt-catbars">
      <div class="report-bar-title">Category Sales <span>Session totals</span></div>
      <div style="color:var(--muted);font-size:13px;padding:10px 0;">No orders placed yet this session.</div>
    </div>

    <div class="panel-section-title" style="margin-top:8px;">Recent Transactions</div>
    <div id="rpt-txlist">
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:30px;text-align:center;color:var(--muted);font-size:13px;">
        <i class="fa-solid fa-file-invoice" style="font-size:30px;color:var(--surface3);display:block;margin-bottom:10px;"></i>
        No transactions yet
      </div>
    </div>
  </div>
</div>

<!-- ── Pay Modal ─────────────────────────────────────────── -->
<div class="receipt-backdrop" id="payModal" style="display:none;z-index:3000;" onclick="handlePayModalClick(event)">
  <div class="receipt-modal" style="max-width:380px;" onclick="event.stopPropagation()">
    <div class="receipt-header" style="padding:18px 20px 14px;">
      <button class="receipt-close-x" onclick="closePayModal()"><i class="fa-solid fa-xmark"></i></button>
      <div class="receipt-store-name" style="font-size:18px;">Confirm <span>Payment</span></div>
      <div class="receipt-subtitle" id="payModalSubtitle"></div>
    </div>

    <!-- Order summary strip -->
    <div class="receipt-meta" id="payModalMeta" style="grid-template-columns:1fr 1fr 1fr;"></div>

    <!-- Payment method: Cash Only -->
    <div style="padding:14px 20px 0;">
      <div class="pay-label mb-2">Payment Method</div>
      <div class="pay-methods" id="payModalMethods">
        <button class="pay-btn active" data-method="Cash" style="flex:1;pointer-events:none;opacity:1;">
          <i class="fa-solid fa-money-bill-wave"></i>Cash Only
        </button>
      </div>
    </div>

    <!-- Cash tendered (shown when Cash selected) -->
    <div id="payModalCashSection" style="padding:12px 20px 0;display:flex;flex-direction:column;gap:9px;">
      <div class="cash-section-title">
        <i class="fa-solid fa-money-bill-wave" style="color:var(--green)"></i> Cash Received
      </div>
      <div class="cash-input-wrap">
        <span class="cash-prefix">₱</span>
        <input type="number" class="cash-inp" id="cashInput" placeholder="Enter amount tendered…" min="0" step="1">
      </div>
      <div class="quick-amounts" id="quickAmounts"></div>
      <div class="change-display zero" id="changeDisplay">
        <span class="change-label" id="changeLabel">Change</span>
        <span class="change-amount zero" id="changeAmount">₱ —</span>
      </div>
    </div>

    <!-- Confirm button -->
    <div style="padding:16px 20px 20px;">
      <button class="btn-place" id="btnConfirmPay" style="font-size:14px;padding:13px;">
        <i class="fa-solid fa-check"></i> Confirm &amp; Place Order
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- VOID / REFUND MODAL                                       -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="receipt-backdrop" id="vrModal" style="display:none;z-index:3500;" onclick="if(event.target.id==='vrModal')closeVrModal()">
  <div class="receipt-modal" style="max-width:420px;" onclick="event.stopPropagation()">
    <div class="receipt-header" style="padding:18px 20px 14px;">
      <button class="receipt-close-x" onclick="closeVrModal()"><i class="fa-solid fa-xmark"></i></button>
      <div class="receipt-store-name" id="vrModalTitle" style="font-size:18px;">Void <span>Order</span></div>
      <div class="receipt-subtitle" id="vrModalSubtitle"></div>
    </div>

    <!-- Order info strip -->
    <div class="receipt-meta" id="vrOrderMeta" style="grid-template-columns:1fr 1fr 1fr;"></div>

    <!-- Items list (shown for refund partial) -->
    <div id="vrItemsSection" style="padding:14px 20px 0;">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px;" id="vrItemsLabel">Items to Refund</div>
      <div id="vrItemsList" style="display:flex;flex-direction:column;gap:8px;max-height:220px;overflow-y:auto;padding-right:4px;"></div>
    </div>

    <!-- Refund amount display -->
    <div style="padding:14px 20px 0;">
      <div class="change-display positive" id="vrAmtDisplay" style="margin-top:0;">
        <span class="change-label positive" id="vrAmtLabel">Refund Amount</span>
        <span class="change-amount positive" id="vrAmtValue">₱ 0.00</span>
      </div>
    </div>

    <!-- Reason input -->
    <div style="padding:12px 20px 0;">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:8px;">Reason <span style="font-weight:400;text-transform:none;color:var(--muted2)">(optional)</span></div>
      <input type="text" id="vrReason" placeholder="e.g. Customer changed mind, wrong order…"
        style="width:100%;padding:10px 13px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;font-family:inherit;outline:none;transition:border-color .2s;"
        onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border2)'">
    </div>

    <!-- Action button -->
    <div style="padding:16px 20px 20px;">
      <button id="vrConfirmBtn" class="btn-place" style="font-size:14px;padding:13px;background:var(--red);box-shadow:none;"
        onclick="submitVoidRefund()">
        <i class="fa-solid fa-ban"></i> <span id="vrConfirmLabel">Confirm Void</span>
      </button>
    </div>
  </div>
</div>

<div class="pos-toast" id="toast"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Data from PHP ─────────────────────────────────────────────
const products = <?= $products_json ?>;
const DB_CATS  = <?= $cats_json ?>;

// ── DB-loaded data ────────────────────────────────────────────
const DB_HISTORY      = <?= $db_history_json ?>;
var DB_CAT_REVENUE  = <?= $db_cat_revenue_json ?>;
var DB_TRANSACTIONS = <?= $db_transactions_json ?>;
var DB_TODAY_REV    = <?= json_encode($db_today_revenue) ?>;
var DB_TODAY_ORDERS = <?= json_encode($db_today_orders) ?>;
var DB_TOTAL_REV    = <?= json_encode($db_total_revenue) ?>;
var DB_TOTAL_ORDERS = <?= json_encode($db_total_orders) ?>;
var DB_TOP_ITEM     = <?= json_encode($db_top_item) ?>;

// ── State ─────────────────────────────────────────────────────
let cart = [], activeCat = 'all', searchQ = '', discount = 0, selTable = '01';
let sessionRevenue = 0, sessionOrders = 0;
let currentTotal = 0;

// ── Cart persistence across reloads ──────────────────────────
function saveCartState() {
  try {
    const activeTab = document.querySelector('.o-tab.active');
    localStorage.setItem('pos_cart', JSON.stringify({
      cart,
      discount,
      activeDiscountType,
      selTable,
      orderType: activeTab ? activeTab.textContent.trim() : 'Dine In',
    }));
  } catch(e) {}
}

function clearCartState() {
  try { localStorage.removeItem('pos_cart'); } catch(e) {}
}

function restoreCartState() {
  try {
    const saved = localStorage.getItem('pos_cart');
    if (!saved) return;
    const s = JSON.parse(saved);
    if (s.cart && s.cart.length) {
      cart = s.cart;
      discount = s.discount || 0;
      activeDiscountType = s.activeDiscountType || null;
      selTable = s.selTable || '01';

      const inp = document.getElementById('tableInput');
      if (inp) inp.value = parseInt(selTable) || 1;

      if (activeDiscountType === 'senior') {
        const b = document.getElementById('btnSenior');
        if (b) b.classList.add('active');
      } else if (activeDiscountType === 'pwd') {
        const b = document.getElementById('btnPWD');
        if (b) b.classList.add('active');
      }

      if (s.orderType) {
        document.querySelectorAll('.o-tab').forEach(t => {
          t.classList.toggle('active', t.textContent.trim() === s.orderType);
        });
      }

      updateCartUI();
    }
  } catch(e) {}
}

const CAT_ICON = {
  'Main Course':'🍽️','Appetizer':'🥗','Dessert':'🍰','Beverage':'☕',
  'Coffee':'☕','Beer & Wine':'🍷','Bites & Treats':'🥐','Croffle':'🧇',
  'Croffle Box':'📦','Frappe':'🥤','Smoothie':'🥤','Tea':'🍵',
  'Pasta':'🍝','Pizza':'🍕','Sandwich':'🥪','Salad':'🥗',
  'Soup':'🍲','Rice':'🍚','Breakfast':'🍳','Snacks':'🍟',
  'Juice':'🍊','Milkshake':'🥛','Cake':'🎂','Waffles':'🧇',
};

document.addEventListener('DOMContentLoaded', () => {
  setDate();
  renderCats();
  initTableInput();
  renderProducts();
  requestAnimationFrame(staggerCards);
  initSearch();
  initTabs();
  initTheme();
  initDiscount();
  initCashInput();
  document.getElementById('btnPlace').addEventListener('click', openPayModal);
  document.getElementById('btnConfirmPay').addEventListener('click', placeOrder);

  // ── Nav buttons → open panels ──────────────────────────────
  const navMap = {
    'Stats':   'panel-stats',
    'Menu':    'panel-menu',
    'History': 'panel-history',
    'Reports': 'panel-reports',
  };
  document.querySelectorAll('.nav-btn').forEach(b => {
    b.addEventListener('click', () => {
      const label = b.title || b.textContent.trim();
      const panelId = navMap[label];
      if (panelId) {
        openPanel(panelId, b);
      } else {
        // Orders btn — close any open panel
        closePanel();
        document.querySelectorAll('.nav-btn').forEach(x => x.classList.remove('active'));
        b.classList.add('active');
      }
    });
  });

  // Close panel on Escape
  document.addEventListener('keydown', e => { if(e.key==='Escape') closePanel(); });

  // Restore cart if page was reloaded mid-order
  restoreCartState();
});

// ── Order history (session) ───────────────────────────────────
let orderHistory = [];

// ── Panel open / close ────────────────────────────────────────
function openPanel(id, navBtn) {
  document.querySelectorAll('.side-panel').forEach(p => p.classList.remove('open'));
  document.querySelectorAll('.nav-btn').forEach(x => x.classList.remove('active'));
  if(navBtn) navBtn.classList.add('active');
  const panel = document.getElementById(id);
  if(!panel) return;
  panel.classList.add('open');
  // Refresh dynamic data
  if(id === 'panel-stats')   refreshStatsPanel();
  if(id === 'panel-history') refreshHistoryPanel();
  if(id === 'panel-reports') refreshReportsPanel();
}

function closePanel() {
  document.querySelectorAll('.side-panel').forEach(p => p.classList.remove('open'));
  // Re-activate Orders nav
  document.querySelectorAll('.nav-btn').forEach(x => x.classList.remove('active'));
  const ordersBtn = [...document.querySelectorAll('.nav-btn')].find(b => b.title === 'Orders');
  if(ordersBtn) ordersBtn.classList.add('active');
}

// ── Stats panel refresh ───────────────────────────────────────
function refreshStatsPanel() {
  document.getElementById('ps-revenue').textContent = '₱' + sessionRevenue.toLocaleString('en',{minimumFractionDigits:2});
  document.getElementById('ps-orders').textContent  = sessionOrders;
  const avg = sessionOrders > 0 ? sessionRevenue / sessionOrders : 0;
  document.getElementById('ps-avg').textContent = '₱' + avg.toLocaleString('en',{minimumFractionDigits:2});
}

// ── History panel refresh ─────────────────────────────────────
function refreshHistoryPanel() {
  const list = document.getElementById('historyList');
  if(!list) return;

  // Merge DB history + session orders (session takes priority for new ones)
  const sessionIds = new Set(orderHistory.map(o => o.id));
  const dbRows = DB_HISTORY.filter(r => !sessionIds.has(r.id));

  // Build combined list: session orders first (newest), then DB rows not in session
  const combined = [
    ...[...orderHistory].reverse().map(o => ({
      id:           o.id,
      table_no:     o.table,
      total_amt:    o.total,
      created_at:   o.time,
      total_qty:    o.items,
      item_names:   o.itemNames ? o.itemNames.join(', ') : '—',
      payMethod:    o.payMethod,
      status:       o.status || 'pending',
      fromSession:  true,
      cartSnapshot: o.cartSnapshot || [],
      subtotal:     o.subtotal || o.total,
      discount:     o.discount || 0,
      discountType: o.discountType || null,
    })),
    ...dbRows.map(r => ({
      id:           r.id,
      table_no:     r.table_no,
      total_amt:    parseFloat(r.total_amt),
      created_at:   r.created_at,
      total_qty:    r.total_qty,
      item_names:   r.item_names,
      payMethod:    '—',
      status:       r.status || 'pending',
      fromSession:  false,
      cartSnapshot: [],
      subtotal:     null,
      discount:     parseFloat(r.discount_amt) || 0,
      discountType: r.discount_type || null,
      item_details: r.item_details || '',
    }))
  ];

  if(!combined.length){
    list.innerHTML=`<div style="text-align:center;padding:60px 20px;color:var(--muted);">
      <i class="fa-solid fa-clock-rotate-left" style="font-size:40px;color:var(--surface3);display:block;margin-bottom:12px;"></i>
      <strong>No orders today</strong><br>
      <small style="font-size:12px;">Completed orders will appear here</small></div>`;
    return;
  }

  list.innerHTML = combined.map(o=>{
    const isVoided   = o.status === 'voided';
    const isRefunded = o.status === 'refunded' || o.status === 'partial_refund';
    const isDone     = isVoided || isRefunded;
    const statusBadge = isVoided
      ? `<span class="badge-pill red" style="font-size:9px"><i class="fa-solid fa-ban" style="font-size:8px"></i> Voided</span>`
      : isRefunded
        ? `<span class="badge-pill blue" style="font-size:9px"><i class="fa-solid fa-rotate-left" style="font-size:8px"></i> Refunded</span>`
        : '';
    const actionBtns = '';

    // ── Build item detail lines ─────────────────────────────────
    let itemDetailHTML = '';
    if (o.fromSession && o.cartSnapshot && o.cartSnapshot.length) {
      itemDetailHTML = o.cartSnapshot.map(item => {
        const addons   = (item.addons||[]).filter(a=>a.qty>0)
                          .map(a=>`+${a.qty>1?a.qty+'× ':''}${a.name}`).join(', ');
        const removed  = (item.removedIngs||[]).map(r=>r.name||r).join(', ');
        const hasExtra = addons || removed || item.notes;
        return `<div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:4px;margin-top:3px;">
          <span style="font-size:11.5px;font-weight:600;">${item.name}</span>
          <span style="font-size:10.5px;color:var(--muted)">×${item.qty}</span>
          ${addons  ? `<span style="font-size:10px;color:var(--accent);background:var(--accent-soft);padding:1px 5px;border-radius:4px;">+${addons}</span>` : ''}
          ${removed ? `<span style="font-size:10px;color:var(--red);background:rgba(239,68,68,.08);padding:1px 5px;border-radius:4px;">No: ${removed}</span>` : ''}
          ${item.notes ? `<span style="font-size:10px;color:var(--muted2);font-style:italic;">"${item.notes}"</span>` : ''}
        </div>`;
      }).join('');
    } else if (!o.fromSession && o.item_details) {
      // Parse "name|qty|addons|removed_json;;..."
      itemDetailHTML = o.item_details.split(';;').map(seg => {
        // Limit split to 4 parts so JSON in removed field isn't broken by | inside it
        const idx1 = seg.indexOf('|'), idx2 = seg.indexOf('|', idx1+1), idx3 = seg.indexOf('|', idx2+1);
        const name    = seg.substring(0, idx1) || '';
        const qty     = seg.substring(idx1+1, idx2) || '1';
        const addons  = seg.substring(idx2+1, idx3) || '';
        const rawRem  = idx3 >= 0 ? seg.substring(idx3+1) : '';
        // Parse JSON array of removed names e.g. ["Cheese Powder","Onion"]
        let removed = '';
        if (rawRem && rawRem !== '[]') {
          try {
            const arr = JSON.parse(rawRem);
            if (Array.isArray(arr) && arr.length) removed = arr.join(', ');
          } catch(e) {
            if (rawRem !== '[]') removed = rawRem;
          }
        }
        return `<div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:4px;margin-top:3px;">
          <span style="font-size:11.5px;font-weight:600;">${name}</span>
          <span style="font-size:10.5px;color:var(--muted)">×${qty}</span>
          ${addons  ? `<span style="font-size:10px;color:var(--accent);background:var(--accent-soft);padding:1px 5px;border-radius:4px;">+${addons}</span>` : ''}
          ${removed ? `<span style="font-size:10px;color:var(--red);background:rgba(239,68,68,.08);padding:1px 5px;border-radius:4px;">No: ${removed}</span>` : ''}
        </div>`;
      }).join('');
    }

    // ── Discount line ───────────────────────────────────────────
    let discountBadge = '';
    if (o.discount > 0) {
      const dtLabel = o.discountType === 'senior' ? 'Senior 20%' : o.discountType === 'pwd' ? 'PWD 20%' : 'Discount';
      discountBadge = `<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;
        background:rgba(34,197,94,.10);color:var(--green);border:1px solid rgba(34,197,94,.25);
        padding:2px 8px;border-radius:20px;margin-top:4px;">
        <i class="fa-solid fa-percent" style="font-size:8px"></i> ${dtLabel} &minus;&#8369;${o.discount.toFixed(2)}
      </span>`;
    }

    return `
    <div class="history-item" id="hist-order-${o.id}">
      <div class="history-ic" style="background:${isDone?'var(--surface3)':'var(--accent-soft)'}">
        <i class="fa-solid fa-receipt" style="color:${isDone?'var(--muted)':'var(--accent)'}"></i>
      </div>
      <div class="history-info" style="flex:1;min-width:0;">
        <div class="history-id">Order #${o.id} &nbsp;
          ${statusBadge}
          ${!isDone && o.payMethod !== '—' ? `<span class="badge-pill pink">${o.payMethod}</span>` : ''}
          ${o.fromSession && !isDone ? '<span class="badge-pill green" style="font-size:9px">New</span>' : ''}
        </div>
        <div class="history-meta">#${o.table_no} · ${o.total_qty} item${o.total_qty!=1?'s':''} · ${o.created_at}</div>
        <div style="margin-top:4px;">${itemDetailHTML}</div>
        ${discountBadge}
        ${actionBtns}
      </div>
      <div class="history-amt" style="color:${isDone?'var(--muted)':'var(--accent)'}">
        ${isDone ? `<s style="font-size:11px;">₱${parseFloat(o.total_amt).toLocaleString('en',{minimumFractionDigits:2})}</s>` : `₱${parseFloat(o.total_amt).toLocaleString('en',{minimumFractionDigits:2})}`}
      </div>
    </div>`;
  }).join('');
}

// ── Reports panel refresh ─────────────────────────────────────
function refreshReportsPanel() {
  // Combine DB today totals + this session
  const combinedRevenue = DB_TODAY_REV + sessionRevenue;
  const combinedOrders  = DB_TODAY_ORDERS + sessionOrders;
  const avg = combinedOrders > 0 ? combinedRevenue / combinedOrders : 0;

  document.getElementById('rpt-revenue').textContent = '₱'+combinedRevenue.toLocaleString('en',{minimumFractionDigits:2});
  document.getElementById('rpt-orders').textContent  = combinedOrders;
  document.getElementById('rpt-avg').textContent     = '₱'+avg.toLocaleString('en',{minimumFractionDigits:2});

  // Top item: session first, fallback DB
  const itemCounts = {};
  orderHistory.forEach(o => o.itemNames.forEach(n => { itemCounts[n]=(itemCounts[n]||0)+1; }));
  const sessionTop = Object.entries(itemCounts).sort((a,b)=>b[1]-a[1])[0];
  document.getElementById('rpt-topitem').textContent = sessionTop ? sessionTop[0] : DB_TOP_ITEM;

  // Category revenue: merge DB + session
  const catRev = {};
  DB_CAT_REVENUE.forEach(r => { catRev[r.category] = (catRev[r.category]||0) + parseFloat(r.revenue); });
  orderHistory.forEach(o => o.cartSnapshot.forEach(c => {
    catRev[c.cat] = (catRev[c.cat]||0) + c.price*c.qty;
  }));
  const barsEl = document.getElementById('rpt-catbars');
  if(!Object.keys(catRev).length){
    barsEl.innerHTML=`<div class="report-bar-title">Category Sales <span>Today\'s totals</span></div>
      <div style="color:var(--muted);font-size:13px;padding:10px 0;">No orders today.</div>`;
  } else {
    const maxRev = Math.max(...Object.values(catRev));
    barsEl.innerHTML=`<div class="report-bar-title">Category Sales <span>Today\'s totals</span></div>`+
      Object.entries(catRev).sort((a,b)=>b[1]-a[1]).map(([cat,rev])=>`
        <div class="bar-row">
          <span class="bar-label" style="font-size:11.5px">${cat}</span>
          <div class="bar-track"><div class="bar-fill" style="width:${Math.round(rev/maxRev*100)}%"></div></div>
          <span class="bar-val">₱${rev.toLocaleString('en',{minimumFractionDigits:0})}</span>
        </div>`).join('');
  }

  // Transaction list: merge DB + session
  const sessionIds = new Set(orderHistory.map(o => o.id));
  const dbTx = DB_TRANSACTIONS.filter(r => !sessionIds.has(r.id));
  const allTx = [
    ...[...orderHistory].reverse().map(o => ({
      id:o.id, table:o.table, items:o.items, payMethod:o.payMethod, total:o.total, isNew:true
    })),
    ...dbTx.map(r => ({
      id:r.id, table:r.table_no, items:r.total_qty, payMethod:'—', total:parseFloat(r.total_amt), isNew:false
    }))
  ];

  const txEl = document.getElementById('rpt-txlist');
  if(!allTx.length){
    txEl.innerHTML=`<div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:30px;text-align:center;color:var(--muted);font-size:13px;">
      <i class="fa-solid fa-file-invoice" style="font-size:30px;color:var(--surface3);display:block;margin-bottom:10px;"></i>No transactions today</div>`;
  } else {
    txEl.innerHTML=`<div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
      <table class="panel-table" style="width:100%">
        <thead><tr><th>Order</th><th>Items</th><th>Method</th><th style="text-align:right">Total</th></tr></thead>
        <tbody>`+allTx.map(o=>`
          <tr>
            <td><strong>#${o.id}</strong>${o.isNew?'<span class="badge-pill green" style="font-size:9px;margin-left:4px">New</span>':''}</td>
            <td>${o.items}</td>
            <td><span class="badge-pill ${o.payMethod==='Cash'?'green':o.payMethod==='Card'?'blue':o.payMethod==='—'?'':'pink'}">${o.payMethod}</span></td>
            <td style="text-align:right;font-weight:700;color:var(--accent)">₱${o.total.toLocaleString('en',{minimumFractionDigits:2})}</td>
          </tr>`).join('')+
        `</tbody></table></div>
      <div style="margin-top:16px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:14px 16px;">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px;">All-Time Summary</div>
        <div style="display:flex;gap:20px;flex-wrap:wrap;">
          <div><div style="font-size:18px;font-weight:700;color:var(--accent)">₱${DB_TOTAL_REV.toLocaleString('en',{minimumFractionDigits:2})}</div><div style="font-size:11px;color:var(--muted)">Total Revenue</div></div>
          <div><div style="font-size:18px;font-weight:700;color:var(--green)">${DB_TOTAL_ORDERS}</div><div style="font-size:11px;color:var(--muted)">Total Orders</div></div>
          <div><div style="font-size:18px;font-weight:700;color:var(--blue)">₱${DB_TOTAL_ORDERS>0?(DB_TOTAL_REV/DB_TOTAL_ORDERS).toLocaleString('en',{minimumFractionDigits:2}):'0.00'}</div><div style="font-size:11px;color:var(--muted)">Avg. Order</div></div>
        </div>
      </div>`;
  }
}

function setDate() {
  document.getElementById('orderDate').textContent =
    new Date().toLocaleDateString('en-US',{weekday:'short',day:'numeric',month:'short',year:'numeric'});
}

// ── Categories ────────────────────────────────────────────────
function renderCats() {
  const row = document.getElementById('catRow');
  row.innerHTML = '';
  const allBtn = document.createElement('button');
  allBtn.className = 'cat-pill'+(activeCat==='all'?' active':'');
  allBtn.innerHTML = `<i class="fa-solid fa-border-all" style="font-size:12px"></i> All Items <span class="cat-cnt">${products.length}</span>`;
  allBtn.addEventListener('click',()=>{
    activeCat='all'; renderCats(); renderProducts();
    requestAnimationFrame(staggerCards);
  });
  row.appendChild(allBtn);
  DB_CATS.forEach(cat=>{
    const cnt=products.filter(p=>p.cat===cat).length;
    if(!cnt) return;
    const btn=document.createElement('button');
    btn.className='cat-pill'+(activeCat===cat?' active':'');
    btn.innerHTML=`<span>${CAT_ICON[cat]||'🍽️'}</span> ${cat} <span class="cat-cnt">${cnt}</span>`;
    btn.addEventListener('click',()=>{
      activeCat=cat; renderCats(); renderProducts();
      requestAnimationFrame(staggerCards);
    });
    row.appendChild(btn);
  });
}

// ── Table Number Input ────────────────────────────────────────
function initTableInput() {
  const inp=document.getElementById('tableInput');
  inp.addEventListener('input',()=>{
    const v=parseInt(inp.value)||1;
    selTable=String(v).padStart(2,'0');
  });
  selTable=String(parseInt(inp.value)||1).padStart(2,'0');
}

// ── Search ────────────────────────────────────────────────────
function initSearch() {
  const inp=document.getElementById('searchInput');
  const clr=document.getElementById('searchClear');
  inp.addEventListener('input',()=>{ searchQ=inp.value.trim().toLowerCase(); renderProducts(); });
  clr.addEventListener('click',()=>{ inp.value=''; searchQ=''; renderProducts(); inp.focus(); });
}

// ── Product Grid ──────────────────────────────────────────────
function renderProducts() {
  const grid=document.getElementById('productGrid');
  grid.innerHTML='';
  const list=products.filter(p=>{
    const cOk=activeCat==='all'||p.cat===activeCat;
    const qOk=!searchQ||p.name.toLowerCase().includes(searchQ)||(p.desc||'').toLowerCase().includes(searchQ);
    return cOk&&qOk;
  });
  if(!list.length){
    grid.innerHTML=`<div class="empty-products"><i class="fa-solid fa-bowl-rice"></i><strong>No items found</strong><span style="font-size:12px">Try a different search or category</span></div>`;
    return;
  }
  list.forEach((p,i)=>{
    const inCart=cart.some(c=>c.id===p.id);
    const card=document.createElement('div');
    card.className='product-card'+(inCart?' in-cart':'');
    card.style.animationDelay=(Math.min(i*.04, 0.4))+'s';
    card.dataset.id=p.id;
    card.innerHTML=`
      <div class="card-img-w">
        ${p.image
          ? `<img class="card-img-real" src="${p.image.startsWith('http')||p.image.startsWith('/')?p.image:p.image.replace(/^.*Frontend\//,'')}" alt="${p.name}" loading="lazy"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
             <div class="card-img-placeholder" style="display:none">
               <i class="fa-solid fa-image"></i>${p.name.split(' ')[0]}
             </div>`
          : `<div class="card-img-placeholder">
               <i class="fa-solid fa-image"></i>${p.name.split(' ')[0]}
             </div>`
        }
        ${p.badge?`<span class="card-bdg ${p.badge.toLowerCase()==='hot'?'hot':p.badge.toLowerCase()==='new'?'new':''}">${p.badge}</span>`:''}
      </div>
      <div class="card-body-i">
        <div class="card-name">${p.name}</div>
        <div class="card-foot">
          <span class="card-price">₱${p.price.toLocaleString()}</span>
          <span style="font-size:10px;color:var(--muted)">${p.cat}</span>
        </div>
      </div>
      <button class="card-add-btn" onclick="addToCart(${p.id},event)">
        <i class="fa-solid fa-${inCart?'check':'plus'}"></i>
        ${inCart?'Added':'Add to Order'}
      </button>`;
    card.addEventListener('click',e=>{ if(!e.target.closest('.card-add-btn')) addToCart(p.id,e); });
    grid.appendChild(card);
    // stagger entrance
    card.style.opacity = '0';
    card.style.transform = 'scale(0.9) translateY(12px)';
  });
}

// ── Add-ons per category ──────────────────────────────────────
// Real ingredients come from the DB (product.ingredients[]).
// Only add-ons are configured here since they are POS-level upsells.
const ADDONS_BY_CAT = {
  'default':    [{name:'Extra Sauce',price:15},{name:'Extra Serving',price:55}],
  'Coffee':     [{name:'Extra Espresso Shot',price:30},{name:'Oat Milk Upgrade',price:25},{name:'Whipped Cream',price:15},{name:'Extra Syrup',price:15},{name:'Almond Milk Upgrade',price:25}],
  'Frappe':     [{name:'Extra Blended Shot',price:30},{name:'Whipped Cream',price:15},{name:'Extra Drizzle',price:15},{name:'Oat Milk Upgrade',price:25}],
  'Non-Coffee': [{name:'Extra Honey',price:15},{name:'Milk Upgrade',price:20},{name:'Extra Syrup',price:15}],
  'Shake':      [{name:'Protein Powder',price:40},{name:'Extra Fruit',price:25},{name:'Chia Seeds',price:20}],
  'Croffle':    [{name:'Ice Cream Scoop',price:45},{name:'Extra Drizzle',price:15},{name:'Whipped Cream',price:15},{name:'Fresh Berries',price:35}],
  'Croffle Box':[{name:'Ice Cream Scoop',price:45},{name:'Extra Drizzle',price:15},{name:'Whipped Cream',price:15}],
  'Pasta':      [{name:'Extra Pasta',price:40},{name:'Extra Sauce',price:20},{name:'Garlic Bread',price:35},{name:'Extra Parmesan',price:15}],
  'Rice Meal':  [{name:'Extra Rice',price:20},{name:'Extra Sauce/Gravy',price:15},{name:'Fried Egg',price:25},{name:'Side of Fries',price:45}],
  'Bites & Treats':[{name:'Extra Dip',price:15},{name:'Extra Serving',price:45}],
  'Beer & Wine':[{name:'Extra Glass',price:50},{name:'Ice Bucket',price:30}],
};

function getAddons(cat) {
  return ADDONS_BY_CAT[cat] || ADDONS_BY_CAT['default'];
}

// ── Customization State ───────────────────────────────────────
let customProduct   = null;
let customRemoved   = new Set();   // Set of ingredient indices that are removed
let customAddonQty  = [];          // parallel array of qty per addon def
let customAddonDefs = [];          // [{name,price}] for current product

// ── Open customization modal ──────────────────────────────────
function openCustomModal(id) {
  const product = products.find(p => p.id === id);
  if (!product) return;
  customProduct   = product;
  customRemoved   = new Set();
  customAddonDefs = getAddons(product.cat);
  customAddonQty  = new Array(customAddonDefs.length).fill(0);

  renderCustomModal();

  const m = document.getElementById('customModal');
  m.style.cssText = 'display:flex;position:fixed;inset:0;z-index:2500;align-items:center;justify-content:center;padding:20px;background:rgba(0,0,0,.72);animation:fadeIn .22s ease;';
  m.onclick = e => { if (e.target === m) closeCustomModal(); };
}

function closeCustomModal() {
  const m = document.getElementById('customModal');
  m.style.transition = 'opacity .18s ease';
  m.style.opacity = '0';
  setTimeout(() => { m.style.cssText = 'display:none;'; }, 180);
}

function renderCustomModal() {
  const p   = customProduct;
  const ings = p.ingredients || [];   // real {id, name} objects from DB

  const imgHTML = p.image
    ? `<img src="${p.image.replace('Frontend/','')}" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><span style="display:none;font-size:28px;">${p.emoji}</span>`
    : `<span style="font-size:28px;">${p.emoji}</span>`;

  // ── Ingredient chips — index-keyed, no name in DOM id ────────
  let ingsSection = '';
  if (ings.length > 0) {
    const chips = ings.map((ing, i) => `
      <button type="button" class="ing-chip" data-idx="${i}" onclick="toggleIngredient(${i})">
        <i class="fa-solid fa-check ing-icon" style="font-size:9px;pointer-events:none;"></i>
        <span style="pointer-events:none;">${escHtml(ing.name)}</span>
      </button>`).join('');
    ingsSection = `
      <div class="custom-section" style="border-bottom:1px solid var(--border);">
        <div class="custom-section-title">
          <i class="fa-solid fa-ban" style="color:var(--red)"></i> Remove Ingredients
          <span style="font-weight:400;text-transform:none;font-size:11px;letter-spacing:0;color:var(--muted2)">(tap to remove)</span>
        </div>
        <div class="ing-grid" id="ingGrid">${chips}</div>
      </div>`;
  } else {
    // No DB ingredients — show a plain note instead of broken chips
    ingsSection = `
      <div class="custom-section" style="border-bottom:1px solid var(--border);">
        <div class="custom-section-title">
          <i class="fa-solid fa-ban" style="color:var(--red)"></i> Remove Ingredients
        </div>
        <div style="font-size:12px;color:var(--muted);padding:4px 2px;">
          No linked ingredients — use Special Instructions below.
        </div>
      </div>`;
  }

  // ── Add-ons ───────────────────────────────────────────────────
  const addonsHTML = customAddonDefs.map((a, i) => `
    <div class="addon-row" id="arow-${i}">
      <div class="addon-info">
        <div class="addon-name">${escHtml(a.name)}</div>
        <div class="addon-price">+₱${a.price.toLocaleString('en',{minimumFractionDigits:2})}</div>
      </div>
      <div class="addon-qty-ctrl">
        <button type="button" class="addon-qty-btn" onclick="changeAddonQty(${i},-1)"><i class="fa-solid fa-minus" style="font-size:9px"></i></button>
        <span class="addon-qty-v" id="aqty-${i}">0</span>
        <button type="button" class="addon-qty-btn" onclick="changeAddonQty(${i},1)"><i class="fa-solid fa-plus" style="font-size:9px"></i></button>
      </div>
    </div>`).join('');

  document.getElementById('customModal').innerHTML = `
  <div class="custom-modal" onclick="event.stopPropagation()">
    <div class="custom-header">
      <div class="custom-header-img">${imgHTML}</div>
      <div class="custom-header-info">
        <div class="custom-item-name">${escHtml(p.name)}</div>
        <div class="custom-item-price">₱${p.price.toLocaleString('en',{minimumFractionDigits:2})}</div>
      </div>
      <button type="button" class="custom-close-x" onclick="closeCustomModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    ${ingsSection}
    <div class="custom-section" style="border-bottom:1px solid var(--border);">
      <div class="custom-section-title">
        <i class="fa-solid fa-circle-plus" style="color:var(--accent)"></i> Add-Ons
      </div>
      <div class="addon-list">${addonsHTML}</div>
    </div>
    <div class="custom-section" style="border-bottom:1px solid var(--border);">
      <div class="custom-section-title">
        <i class="fa-solid fa-pen-to-square" style="color:var(--blue)"></i> Special Instructions
      </div>
      <textarea class="custom-notes" id="customNotes" placeholder="e.g. No spice, well done, allergy note…" rows="2"></textarea>
    </div>
    <div class="custom-footer">
      <div class="custom-total-row">
        <span class="custom-total-lbl">Item Total</span>
        <span class="custom-total-val" id="customTotalDisplay">₱${p.price.toLocaleString('en',{minimumFractionDigits:2})}</span>
      </div>
      <button type="button" class="btn-add-custom" onclick="confirmAddToCart()">
        <i class="fa-solid fa-cart-plus"></i> Add to Order
      </button>
    </div>
  </div>`;
}

// Safe HTML escape — avoids XSS in ingredient/item names
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Toggle ingredient removal by index — no DOM-id fragility
function toggleIngredient(idx) {
  const grid = document.getElementById('ingGrid');
  if (!grid) return;
  const chip = grid.querySelector(`.ing-chip[data-idx="${idx}"]`);
  if (!chip) return;

  if (customRemoved.has(idx)) {
    customRemoved.delete(idx);
    chip.classList.remove('removed');
    chip.querySelector('.ing-icon').className = 'fa-solid fa-check ing-icon';
  } else {
    customRemoved.add(idx);
    chip.classList.add('removed');
    chip.querySelector('.ing-icon').className = 'fa-solid fa-xmark ing-icon';
  }
}

function changeAddonQty(idx, delta) {
  customAddonQty[idx] = Math.max(0, (customAddonQty[idx] || 0) + delta);
  const qEl  = document.getElementById(`aqty-${idx}`);
  const rEl  = document.getElementById(`arow-${idx}`);
  if (qEl) qEl.textContent = customAddonQty[idx];
  if (rEl) rEl.classList.toggle('selected', customAddonQty[idx] > 0);
  updateCustomTotal();
}

function updateCustomTotal() {
  const addonTotal = customAddonDefs.reduce((s, a, i) => s + a.price * (customAddonQty[i] || 0), 0);
  const el = document.getElementById('customTotalDisplay');
  if (el) el.textContent = '₱' + (customProduct.price + addonTotal).toLocaleString('en', {minimumFractionDigits:2});
}

function confirmAddToCart() {
  const p    = customProduct;
  const ings = p.ingredients || [];
  const notes = (document.getElementById('customNotes')?.value || '').trim();

  // Collect removed ingredients {id, name} from the Set of indices
  const removedIngs = [...customRemoved].map(i => ings[i]).filter(Boolean);

  // Collect selected add-ons
  const selectedAddons = customAddonDefs
    .map((a, i) => ({...a, qty: customAddonQty[i] || 0}))
    .filter(a => a.qty > 0);
  const addonExtra = selectedAddons.reduce((s, a) => s + a.price * a.qty, 0);

  const cartKey = `${p.id}_${Date.now()}`;
  cart.push({
    ...p,
    qty:        1,
    cartKey,
    removedIngs,
    addons:     selectedAddons,
    notes,
    price:      p.price + addonExtra,
    basePrice:  p.price,
  });

  closeCustomModal();
  updateCartUI();
  refreshCardState(p.id);

  const addonCount   = selectedAddons.reduce((s, a) => s + a.qty, 0);
  const removedCount = removedIngs.length;
  let toastMsg = `<i class="fa-solid fa-circle-plus me-1"></i> ${escHtml(p.name)} added!`;
  if (addonCount > 0 || removedCount > 0) {
    const parts = [];
    if (addonCount)   parts.push(`${addonCount} add-on${addonCount > 1 ? 's' : ''}`);
    if (removedCount) parts.push(`${removedCount} removed`);
    toastMsg += ` <small style="opacity:.8">(${parts.join(', ')})</small>`;
  }
  showToast(toastMsg, 'var(--accent)');
}

// ── Cart Operations ───────────────────────────────────────────
function addToCart(id, e) {
  if (e) e.stopPropagation();
  openCustomModal(id);
}


function changeQty(cartKey, d) {
  const item = cart.find(c => c.cartKey === cartKey);
  if (!item) return;
  item.qty += d;
  if (item.qty <= 0) {
    const pid = item.id;
    cart = cart.filter(c => c.cartKey !== cartKey);
    refreshCardState(pid);
  }
  updateCartUI();
}

function removeItem(cartKey) {
  const item = cart.find(c => c.cartKey === cartKey);
  const pid = item ? item.id : null;
  cart = cart.filter(c => c.cartKey !== cartKey);
  if (pid) refreshCardState(pid);
  updateCartUI();
}

function refreshCardState(id) {
  const card = document.querySelector(`.product-card[data-id="${id}"]`);
  if (!card) return;
  const inCart = cart.some(c => c.id === id);
  card.classList.toggle('in-cart', inCart);
  const btn = card.querySelector('.card-add-btn');
  if (btn) btn.innerHTML = `<i class="fa-solid fa-${inCart ? 'check' : 'plus'}"></i> ${inCart ? 'Added' : 'Add to Order'}`;
}

function updateCartUI() {
  const scroll=document.getElementById('cartScroll');
  const emptyMsg=document.getElementById('emptyMsg');
  const placeBtn=document.getElementById('btnPlace');
  scroll.querySelectorAll('.cart-item').forEach(el=>el.remove());

  // Update cart badge
  const badge=document.getElementById('cartBadge');
  const totalQty=cart.reduce((s,c)=>s+c.qty,0);
  if(totalQty>0){ badge.textContent=totalQty; badge.style.display='inline'; }
  else badge.style.display='none';

  // Sync order type label in summary
  const activeTab=document.querySelector('.o-tab.active');
  const orderTypeEl=document.getElementById('sumOrderType');
  if(activeTab && orderTypeEl) orderTypeEl.textContent=activeTab.textContent.trim();

  if(!cart.length){
    emptyMsg.style.display='flex';
    placeBtn.disabled=true;
  } else {
    emptyMsg.style.display='none';
    placeBtn.disabled=false;
    cart.forEach(item=>{
      const el=document.createElement('div');
      el.className='cart-item';

      // Build customization notes HTML
      const removedParts = (item.removedIngs||[]).map(r=>`<span class="removed-note">No ${r.name||r}</span>`).join('');
      const addonParts   = (item.addons||[]).map(a=>`<span class="addon-note">+${a.qty>1?a.qty+'× ':''}${a.name}</span>`).join('');
      const notesPart    = item.notes ? `<span style="color:var(--muted2);font-style:italic;">"${item.notes}"</span>` : '';
      const customNotesHTML = (removedParts||addonParts||notesPart)
        ? `<div class="ci-custom-notes">${removedParts}${addonParts}${notesPart}</div>` : '';

      const addonExtra = (item.addons||[]).reduce((s,a)=>s+a.price*a.qty,0);
      const priceLabel = addonExtra>0
        ? `₱${(item.basePrice||item.price).toLocaleString('en',{minimumFractionDigits:2})} +₱${addonExtra.toFixed(2)} add-ons`
        : `₱${item.price.toLocaleString('en',{minimumFractionDigits:2})} each`;

      const ck = item.cartKey;
      el.innerHTML=`
        <div class="ci-emoji">
          ${item.image
            ? `<img src="${item.image.replace('Frontend/','')}" alt="${item.name}" onerror="this.style.display='none';this.parentElement.innerHTML='${item.emoji}'">`
            : item.emoji
          }
        </div>
        <div class="ci-info">
          <div class="ci-name">${item.name}</div>
          <div class="ci-price">${priceLabel}</div>
          ${customNotesHTML}
        </div>
        <div class="qty-ctrl">
          <button class="qty-btn" onclick="changeQty('${ck}',-1)"><i class="fa-solid fa-minus" style="font-size:9px"></i></button>
          <span class="qty-v">${item.qty}</span>
          <button class="qty-btn" onclick="changeQty('${ck}',1)"><i class="fa-solid fa-plus" style="font-size:9px"></i></button>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0">
          <span class="ci-total">₱${(item.price*item.qty).toLocaleString('en',{minimumFractionDigits:2})}</span>
          <button class="btn-rm" onclick="removeItem('${ck}')"><i class="fa-solid fa-trash-can"></i></button>
        </div>`;
      scroll.appendChild(el);
    });
  }

  const sub=cart.reduce((s,c)=>s+c.price*c.qty,0);
  // Keep 20% discount in sync as cart changes
  if(activeDiscountType) discount=parseFloat((sub*0.20).toFixed(2));
  saveCartState();
  const tot=parseFloat(Math.max(sub-discount,0).toFixed(2));
  currentTotal=tot;

  document.getElementById('sumSub').textContent =`₱ ${sub.toLocaleString('en',{minimumFractionDigits:2})}`;
  document.getElementById('sumTotal').textContent=`₱ ${tot.toFixed(2)}`;
  const dr=document.getElementById('discRow');
  if(discount>0){
    dr.style.display='flex';
    const discLabel = activeDiscountType === 'senior' ? 'Senior 20%' : activeDiscountType === 'pwd' ? 'PWD 20%' : 'Discount';
    dr.querySelector('.sum-lbl').textContent = discLabel;
    document.getElementById('sumDisc').textContent=`-₱ ${discount.toFixed(2)}`;
  }
  else dr.style.display='none';

  renderQuickAmounts(tot);
  updateChange();
}

// ── Cash Tendered & Change ────────────────────────────────────
function initCashInput() {
  document.getElementById('cashInput').addEventListener('input', updateChange);
}

function renderQuickAmounts(total) {
  const wrap=document.getElementById('quickAmounts');
  wrap.innerHTML='';
  if(total<=0) return;
  const suggestions=generateQuickAmounts(total);
  suggestions.forEach(amt=>{
    const btn=document.createElement('button');
    btn.className='qty-quick';
    btn.textContent='₱'+amt.toLocaleString();
    btn.addEventListener('click',()=>{
      document.getElementById('cashInput').value=amt;
      updateChange();
    });
    wrap.appendChild(btn);
  });
}

function generateQuickAmounts(total) {
  const rounds=[];
  const bases=[20,50,100,200,500,1000,2000,5000];
  for(const b of bases){
    const multiple=Math.ceil(total/b)*b;
    if(!rounds.includes(multiple) && multiple>=total && multiple<=total*5){
      rounds.push(multiple);
    }
    if(rounds.length>=4) break;
  }
  return [...new Set([Math.ceil(total),...rounds])].slice(0,5);
}

function updateChange() {
  const cashVal=parseFloat(document.getElementById('cashInput').value)||0;
  const display=document.getElementById('changeDisplay');
  const label=document.getElementById('changeLabel');
  const amount=document.getElementById('changeAmount');

  // Reset classes
  display.className='change-display';
  label.className='change-label';
  amount.className='change-amount';

  if(!cashVal || currentTotal<=0){
    display.classList.add('zero');
    amount.classList.add('zero');
    label.textContent='Change';
    amount.textContent='₱ —';
    return;
  }

  const change=parseFloat((cashVal-currentTotal).toFixed(2));

  if(change>0){
    display.classList.add('positive');
    label.classList.add('positive');
    amount.classList.add('positive');
    label.textContent='Change Due';
    amount.textContent='₱ '+change.toLocaleString('en',{minimumFractionDigits:2});
  } else if(change<0){
    display.classList.add('negative');
    label.classList.add('negative');
    amount.classList.add('negative');
    label.textContent='Short by';
    amount.textContent='₱ '+Math.abs(change).toLocaleString('en',{minimumFractionDigits:2});
  } else {
    display.classList.add('positive');
    label.classList.add('exact');
    amount.classList.add('exact');
    label.textContent='Exact Change ✓';
    amount.textContent='₱ 0.00';
  }
}

function isCashPayment() {
  const active=document.querySelector('#payModalMethods .pay-btn.active');
  return active && active.dataset.method==='Cash';
}

function selectPay(btn) {
  document.querySelectorAll('.pay-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('cashSection') &&
    (document.getElementById('cashSection').style.display=(btn.dataset.method==='Cash')?'flex':'none');
}

function selectModalPay(btn) {
  document.querySelectorAll('#payModalMethods .pay-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('payModalCashSection').style.display=
    (btn.dataset.method==='Cash')?'flex':'none';
  updateChange();
}

// ── Pay Modal ─────────────────────────────────────────────────
function openPayModal() {
  if(!cart.length) return;
  // Populate summary strip
  const activeTab=document.querySelector('.o-tab.active');
  const orderType=activeTab?activeTab.textContent.trim():'Dine In';
  document.getElementById('payModalSubtitle').textContent=orderType+' · #'+selTable;
  document.getElementById('payModalMeta').innerHTML=`
    <div>${cart.length} item${cart.length>1?'s':''}<br><small style="color:var(--muted)">Items</small></div>
    <div style="text-align:center">₱${currentTotal.toLocaleString('en',{minimumFractionDigits:2})}<br><small style="color:var(--muted)">Total</small></div>
    <div style="text-align:right">#${selTable}<br><small style="color:var(--muted)">Number No.</small></div>`;
  // Reset to Cash (always)
  document.getElementById('payModalCashSection').style.display='flex';
  document.getElementById('cashInput').value='';
  renderQuickAmounts(currentTotal);
  updateChange();
  const modal=document.getElementById('payModal');
  modal.style.display='flex';
  modal.style.animation='fadeIn .22s ease';
  setTimeout(()=>document.getElementById('cashInput').focus(),120);
}

function closePayModal() {
  const m=document.getElementById('payModal');
  m.style.transition='opacity .18s ease';
  m.style.opacity='0';
  setTimeout(()=>{ m.style.display='none'; m.style.opacity=''; m.style.transition=''; },180);
}

function handlePayModalClick(e) {
  if(e.target.id==='payModal') closePayModal();
}

// ── Place Order ───────────────────────────────────────────────
function placeOrder() {
  if(!cart.length) return;

  const activePayBtn=document.querySelector('#payModalMethods .pay-btn.active');
  const payMethod='Cash';

  // Capture cash tendered BEFORE closing modal
  const cashTendered=payMethod==='Cash'?(parseFloat(document.getElementById('cashInput').value)||0):0;

  // Validate cash amount
  if(payMethod==='Cash'){
    if(cashTendered<currentTotal){
      showToast('<i class="fa-solid fa-triangle-exclamation me-1"></i> Cash received is less than total!','var(--red)',3000);
      document.getElementById('cashInput').focus();
      return;
    }
  }

  const sub   =cart.reduce((s,c)=>s+c.price*c.qty,0);
  const total =parseFloat(Math.max(sub-discount,0).toFixed(2));
  const changeDue=payMethod==='Cash'?(cashTendered-total):0;

  const payload={
    table_no:      selTable,
    status:        'pending',
    total_amt:     total,
    discount_amt:  discount,
    discount_type: activeDiscountType || '',
    items: cart.map(c=>({
      menu_id:    c.id,
      qty:        c.qty,
      unit_price: c.price,
      removed_ingredient_ids: (c.removedIngs||[]).map(r=>({id: r.id, name: r.name||r})),
      addons:     (c.addons||[]).map(a=>`${a.qty>1?a.qty+'× ':''}${a.name} (+₱${a.price})`).join(', '),
      notes:      c.notes||'',
    }))
  };

  const btn=document.getElementById('btnConfirmPay');
  btn.disabled=true;
  btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

  const activeTab=document.querySelector('.o-tab.active');
  const cartSnapshot=cart.map(c=>({...c}));
  const orderData_base={
    table:selTable,
    items:cartSnapshot.map(c=>({
      ...c,
      removedIngs: c.removedIngs||[],
      addons:      c.addons||[],
      notes:       c.notes||'',
    })),
    subtotal:sub,
    discount,
    total,
    payMethod,
    cashTendered,
    changeDue,
    orderType:activeTab?activeTab.textContent.trim():'Dine In',
  };

  // Close the pay modal immediately on click
  closePayModal();

  fetch('../Backend/pos_process.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify(payload)
  })
  .then(r=>r.json())
  .then(res=>{
    btn.disabled=false;
    btn.innerHTML='<i class="fa-solid fa-check"></i> Confirm & Place Order';

    if(res.success){
      sessionRevenue+=total;
      sessionOrders++;
      document.getElementById('statRevenue').textContent='₱'+sessionRevenue.toLocaleString('en',{minimumFractionDigits:2});
      document.getElementById('statOrders').textContent=sessionOrders;

      // Record to history
      orderHistory.push({
        id:           res.order_id,
        table:        selTable,
        type:         orderData_base.orderType,
        items:        cartSnapshot.reduce((s,c)=>s+c.qty,0),
        itemNames:    cartSnapshot.map(c=>c.name),
        cartSnapshot,
        subtotal:     sub,
        discount,
        discountType: activeDiscountType,
        total,
        payMethod,
        time:         new Date().toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'}),
      });
      // Update history badge dot
      const histBtn=[...document.querySelectorAll('.nav-btn')].find(b=>b.title==='History');
      if(histBtn && !histBtn.querySelector('.bdot')){
        const dot=document.createElement('span'); dot.className='bdot'; histBtn.appendChild(dot);
      }

      // Snapshot cart before reset
      const orderData={
        orderId:res.order_id,
        ...orderData_base,
      };

      // Show receipt after modal fade completes
      setTimeout(()=>{
        showReceipt(orderData);

        // Reset cart state after receipt is shown
        clearCartState();
        cart=[]; discount=0; activeDiscountType=null;
        document.getElementById('btnSenior').classList.remove('active');
        document.getElementById('btnPWD').classList.remove('active');
        document.getElementById('cashInput').value='';
        currentTotal=0;
        updateCartUI();
        document.querySelectorAll('.product-card').forEach(c=>c.classList.remove('in-cart'));
        document.querySelectorAll('.card-add-btn').forEach(b=>{
          b.innerHTML='<i class="fa-solid fa-plus"></i> Add to Order';
          b.style.opacity=0; b.style.transform='translateY(4px)';
        });
        const placeBtn=document.getElementById('btnPlace');
        placeBtn.disabled=true;
        updateChange();
      }, 220);

    } else {
      showToast(`<i class="fa-solid fa-triangle-exclamation me-1"></i> ${res.message||'Error saving order'}`, 'var(--red)', 3500);
    }
  })
  .catch(err=>{
    console.error('pos_process error:', err);
    showToast('<i class="fa-solid fa-triangle-exclamation me-1"></i> Network error. Try again.','var(--red)',3000);
    btn.disabled=false;
    btn.innerHTML='<i class="fa-solid fa-check"></i> Confirm & Place Order';
  });
}

// ── Coupon ────────────────────────────────────────────────────
// ── Senior / PWD 20% Discount ─────────────────────────────────
let activeDiscountType = null; // 'senior' | 'pwd' | null

function initDiscount() {
  const btnSenior = document.getElementById('btnSenior');
  const btnPWD    = document.getElementById('btnPWD');

  function applyDiscount(type, btn, otherBtn) {
    if (activeDiscountType === type) {
      // Toggle off
      activeDiscountType = null;
      discount = 0;
      btn.classList.remove('active');
      showToast('<i class="fa-solid fa-times-circle me-1"></i> Discount removed.', 'var(--muted2)', 2000);
    } else {
      if (!cart.length) {
        showToast('<i class="fa-solid fa-cart-shopping me-1"></i> Add items first!', 'var(--red)');
        return;
      }
      activeDiscountType = type;
      const sub = cart.reduce((s, c) => s + c.price * c.qty, 0);
      discount = parseFloat((sub * 0.20).toFixed(2));
      btn.classList.add('active');
      otherBtn.classList.remove('active');
      const label = type === 'senior' ? 'Senior Citizen' : 'PWD';
      showToast(`<i class="fa-solid fa-percent me-1"></i> ${label} 20% discount applied!`, 'var(--green)', 3000);
    }
    updateCartUI();
  }

  btnSenior.addEventListener('click', () => applyDiscount('senior', btnSenior, btnPWD));
  btnPWD.addEventListener('click',    () => applyDiscount('pwd',    btnPWD,    btnSenior));
}

function initTabs() {
  document.querySelectorAll('.o-tab').forEach(b=>b.addEventListener('click',()=>{
    document.querySelectorAll('.o-tab').forEach(x=>x.classList.remove('active'));
    b.classList.add('active');
    const orderTypeEl=document.getElementById('sumOrderType');
    if(orderTypeEl) orderTypeEl.textContent=b.textContent.trim();
  }));
}

// ── Receipt Modal ─────────────────────────────────────────────
function showReceipt(data) {
  window._lastReceiptData = data; // store for printReceipt()
  const now=new Date();
  const dateStr=now.toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});
  const timeStr=now.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
  const activeTab=document.querySelector('.o-tab.active');
  const orderType=activeTab?activeTab.textContent.trim():'Dine In';

  const itemsHTML=data.items.map(item=>{
    const removedLine = (item.removedIngs||[]).length
      ? `<div style="font-size:10px;color:#ef4444;margin-top:2px;">No: ${item.removedIngs.join(', ')}</div>` : '';
    const addonLine = (item.addons||[]).length
      ? `<div style="font-size:10px;color:#e91e8c;margin-top:1px;">+ ${item.addons.map(a=>`${a.qty>1?a.qty+'× ':''}${a.name}`).join(', ')}</div>` : '';
    const noteLine = item.notes
      ? `<div style="font-size:10px;color:#999;font-style:italic;margin-top:1px;">"${item.notes}"</div>` : '';
    return `
    <div class="receipt-item-row" style="flex-direction:column;align-items:flex-start;gap:3px;">
      <div style="display:flex;align-items:center;gap:8px;width:100%;">
        <span class="ri-name">${item.name}</span>
        <span class="ri-qty">×${item.qty}</span>
        <span class="ri-subtotal">₱${(item.price*item.qty).toLocaleString('en',{minimumFractionDigits:2})}</span>
      </div>
      ${removedLine}${addonLine}${noteLine}
    </div>`;
  }).join('');

  const discHTML=data.discount>0?`
    <div class="rt-row"><span class="rt-lbl">Discount</span><span class="rt-val rt-disc">-₱${data.discount.toFixed(2)}</span></div>`:'';

  const cashHTML=data.payMethod==='Cash'?`
    <div class="receipt-cash">
      <div class="rc-row">
        <span class="rc-lbl">Cash Tendered</span>
        <span class="rc-val">₱${data.cashTendered.toLocaleString('en',{minimumFractionDigits:2})}</span>
      </div>
      <div class="rc-row">
        <span class="rc-lbl">Total Charged</span>
        <span class="rc-val">₱${data.total.toFixed(2)}</span>
      </div>
      <div class="rc-change-box">
        <span class="rc-lbl"><i class="fa-solid fa-coins"></i> Change Due</span>
        <span class="rc-val">₱${data.changeDue.toLocaleString('en',{minimumFractionDigits:2})}</span>
      </div>
    </div>`:'';

  const container=document.getElementById('receiptContainer');
  container.innerHTML=`
    <div class="receipt-backdrop" id="rBackdrop" onclick="handleReceiptClick(event)">
      <div class="receipt-modal" onclick="event.stopPropagation()">
        <div class="receipt-header">
          <button class="receipt-close-x" onclick="closeReceipt()"><i class="fa-solid fa-xmark"></i></button>
          <div class="receipt-store-name">Empress <span>POS</span></div>
          <div class="receipt-subtitle">Restaurant &amp; Dining</div>
          <div class="receipt-confirmed-badge"><i class="fa-solid fa-circle-check"></i> Order Confirmed</div>
        </div>
        <div class="receipt-meta">
          <div>Order <strong>#${data.orderId}</strong></div>
          <div>Number <strong>#${data.table}</strong></div>
          <div>Type <strong>${orderType}</strong></div>
          <div style="text-align:right"><strong>${dateStr}</strong><br>${timeStr}</div>
        </div>
        <div class="receipt-items">${itemsHTML}</div>
        <div class="receipt-totals">
          <div class="rt-row"><span class="rt-lbl">Subtotal</span><span class="rt-val">₱${data.subtotal.toLocaleString('en',{minimumFractionDigits:2})}</span></div>
          ${discHTML}
          <hr class="rt-div">
          <div class="rt-total-row"><span>Total</span><span class="rt-val">₱${data.total.toFixed(2)}</span></div>
        </div>
        ${cashHTML}
        <div class="receipt-footer-btns">
          <button class="btn-print" onclick="printReceipt()"><i class="fa-solid fa-print"></i> Print Receipt</button>
          <button class="btn-new-order" onclick="closeReceipt()"><i class="fa-solid fa-plus"></i> New Order</button>
        </div>
      </div>
    </div>`;
}

function handleReceiptClick(e){
  if(e.target.id==='rBackdrop') closeReceipt();
}

function closeReceipt(){
  const container=document.getElementById('receiptContainer');
  container.innerHTML='';
  location.reload();
}

function printReceipt(){
  // ── Grab the last placed order data from the receipt modal's data attributes ──
  const modal = document.querySelector('.receipt-modal');
  if (!modal) return;

  // Pull live data already rendered in the receipt modal
  const orderIdEl   = modal.querySelector('.receipt-meta strong');
  const metaDivs    = modal.querySelectorAll('.receipt-meta div');

  // Re-collect order data from the last showReceipt() call via a stored reference
  const _d = window._lastReceiptData || {};

  const now       = new Date();
  const dateStr   = now.toLocaleDateString('en-PH', {weekday:'short', month:'short', day:'numeric', year:'numeric'});
  // e.g. "Thu Apr 23 2026"
  const timeStr   = now.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit', hour12:true});
  const invNo     = String(_d.orderId || '').padStart(6,'0');

  // VAT computation (12% VAT-inclusive)
  const total       = parseFloat(_d.total || 0);
  const vatRate     = 0.12;
  const vatSales    = parseFloat((total / (1 + vatRate)).toFixed(2));
  const vatAmt      = parseFloat((total - vatSales).toFixed(2));
  const cashTend    = parseFloat(_d.cashTendered || 0);
  const changeDue   = parseFloat(_d.changeDue || 0);
  const discount    = parseFloat(_d.discount || 0);
  const subtotal    = parseFloat(_d.subtotal || total);

  // Build items HTML (thermal 80mm style)
  const itemsHTML = (_d.items || []).map(item => {
    const lineTotal = (item.price * item.qty).toFixed(2);
    const qtyPrice  = `${item.qty} PCS x @${parseFloat(item.price).toFixed(2)}`;
    const removedLine = (item.removedIngs || []).length
      ? `<div style="font-size:10px;color:#555;margin-left:4px;">No: ${item.removedIngs.map(r=>r.name||r).join(', ')}</div>` : '';
    const addonLine   = (item.addons || []).filter(a=>a.qty>0).length
      ? `<div style="font-size:10px;color:#555;margin-left:4px;">+ ${item.addons.filter(a=>a.qty>0).map(a=>`${a.qty>1?a.qty+'x ':''}${a.name}`).join(', ')}</div>` : '';
    const noteLine    = item.notes
      ? `<div style="font-size:10px;color:#777;font-style:italic;margin-left:4px;">"${item.notes}"</div>` : '';
    return `
      <div style="padding:4px 0;border-bottom:1px dashed #ccc;">
        <div style="display:flex;justify-content:space-between;align-items:baseline;">
          <span style="font-size:12px;font-weight:700;flex:1;">${item.name}</span>
          <span style="font-size:12px;font-weight:700;white-space:nowrap;margin-left:8px;">${parseFloat(lineTotal).toLocaleString('en',{minimumFractionDigits:2})}V</span>
        </div>
        <div style="font-size:11px;color:#555;">${qtyPrice}</div>
        ${removedLine}${addonLine}${noteLine}
      </div>`;
  }).join('');

  const discountRow = discount > 0 ? `
    <div class="t-row"><span>Discount (${_d.discountType === 'senior' ? 'Senior 20%' : _d.discountType === 'pwd' ? 'PWD 20%' : 'Disc'})</span><span>-${discount.toFixed(2)}</span></div>` : '';

  const w = window.open('', '_blank', 'width=380,height=800');
  w.document.write(`<!DOCTYPE html><html><head>
  <meta charset="UTF-8">
  <title>Sales Invoice #${invNo}</title>
  <style>
    @media print {
      @page { margin: 0; size: 80mm auto; }
      body { margin: 0; }
      .no-print { display: none !important; }
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Courier New', Courier, monospace;
      font-size: 12px;
      color: #000;
      background: #fff;
      width: 80mm;
      max-width: 80mm;
      margin: 0 auto;
      padding: 8px 10px 16px;
    }
    .center  { text-align: center; }
    .bold    { font-weight: bold; }
    .small   { font-size: 10px; }
    .xsmall  { font-size: 9px; }
    .dash    { border: none; border-top: 1px dashed #000; margin: 5px 0; }
    .solid   { border: none; border-top: 1px solid #000; margin: 5px 0; }
    .t-row   { display: flex; justify-content: space-between; font-size: 11px; padding: 2px 0; }
    .t-row span:last-child { text-align: right; white-space: nowrap; margin-left: 6px; }
    .t-total { display: flex; justify-content: space-between; font-size: 13px; font-weight: bold; padding: 4px 0 2px; }
    .t-total span:last-child { text-align: right; }
    .block-label { font-size: 10px; color: #444; margin-top: 3px; }
    .field-row { display: flex; font-size: 10px; padding: 2px 0; }
    .field-label { width: 105px; flex-shrink: 0; }
    .field-line { flex: 1; border-bottom: 1px solid #000; min-width: 60px; }
    .btn-print-action {
      display: block; width: 100%; margin-top: 14px;
      padding: 8px; background: #111; color: #fff;
      font-size: 13px; font-weight: bold; border: none;
      cursor: pointer; border-radius: 4px;
    }
  </style>
  </head><body>

  <!-- ── STORE HEADER ── -->
  <div class="center bold" style="font-size:13px; text-transform:uppercase; letter-spacing:1px;">Empress POS</div>
  <div class="center small">Restaurant &amp; Dining</div>
  <div class="center xsmall" style="margin-top:3px;">VAT Reg TIN: 000-000-000-00000</div>
  <div class="center xsmall">10/F Buildcomm Center, Sumilon Rd.,</div>
  <div class="center xsmall">Cebu Business Park, Hipodromo, Cebu City</div>
  <div class="center xsmall" style="margin-top:2px;">AccNo: 0812211728322025102684</div>
  <div class="center xsmall">POS Serial No: PCJL250303616</div>
  <div class="center xsmall">MIN: 000000000000000</div>
  <div class="center xsmall">PTU#: FP122025-074-0570372-00009</div>
  <div class="center xsmall">DATE ISSUED: ${now.toLocaleDateString('en-PH',{month:'2-digit',day:'2-digit',year:'numeric'}).replace(/\//g,'-')}</div>
  <div class="center bold" style="margin:6px 0 4px;letter-spacing:3px;">* * * SALES INVOICE * * *</div>

  <!-- ── ORDER META ── -->
  <div style="display:flex;justify-content:space-between;font-size:11px;margin-top:4px;">
    <div>
      <div>${dateStr}</div>
      <div>TM#DJ01</div>
    </div>
    <div style="text-align:right;">
      <div>${timeStr}</div>
      <div>INV#:${invNo}</div>
    </div>
  </div>

  <hr class="dash">

  <!-- ── ITEMS ── -->
  <div style="padding:2px 0;">${itemsHTML}</div>

  <!-- ── TOTALS ── -->
  <hr class="dash">
  <div class="t-row"><span>TOTAL</span><span>${total.toLocaleString('en',{minimumFractionDigits:2})}</span></div>
  ${discountRow}
  ${cashTend > 0 ? `<div class="t-row"><span>CASH</span><span>${cashTend.toLocaleString('en',{minimumFractionDigits:2})}</span></div>` : ''}
  ${changeDue >= 0 ? `<div class="t-row"><span>CHANGE</span><span>${changeDue.toLocaleString('en',{minimumFractionDigits:2})}</span></div>` : ''}

  <hr class="dash">

  <!-- ── VAT BREAKDOWN ── -->
  <div class="t-row"><span>VAT Sales</span><span>${vatSales.toLocaleString('en',{minimumFractionDigits:2})}</span></div>
  <div class="t-row"><span>VAT Exempt Sales</span><span>0.00</span></div>
  <div class="t-row"><span>VAT Zero-Rated Sales</span><span>0.00</span></div>
  <div class="t-row"><span>Non-Taxable Sales</span><span>0.00</span></div>
  <div class="t-row"><span>VAT (12%)</span><span>${vatAmt.toLocaleString('en',{minimumFractionDigits:2})}</span></div>
  <div class="t-row"><span>S.Charge</span><span>0.00</span></div>
  <div class="t-row bold"><span>Amount Payable</span><span>${total.toLocaleString('en',{minimumFractionDigits:2})}</span></div>
  <div class="t-row"><span>Transaction#</span><span>${invNo}</span></div>

  <hr class="dash">

  <!-- ── ORDER INFO ── -->
  <div style="display:flex;justify-content:space-between;align-items:center;font-size:11px;margin-bottom:2px;">
    <div>
      <div># CUSTOMER : ${(_d.items||[]).reduce((s,c)=>s+c.qty,0)}</div>
      <div>ORDER : Table ${_d.table || selTable}</div>
      <div>CASHIER : <?php
        $c_name = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? ''));
        echo htmlspecialchars($c_name ?: 'Cashier');
      ?></div>
    </div>
    <div style="text-align:right;font-size:10px;">${(_d.items||[]).length} item(s)</div>
  </div>

  <hr class="dash">

  <!-- ── CUSTOMER FIELDS ── -->
  <div class="field-row"><span class="field-label">SOLD TO</span><span>:</span><span class="field-line"></span></div>
  <div class="field-row"><span class="field-label">ADDRESS</span><span>:</span><span class="field-line"></span></div>
  <div class="field-row"><span class="field-label">TIN</span><span>:</span><span class="field-line"></span></div>
  <div class="field-row"><span class="field-label">BUSINESS STYLE</span><span>:</span><span class="field-line"></span></div>
  <div class="field-row"><span class="field-label">SIGNATURE</span><span>:</span><span class="field-line"></span></div>

  <hr class="dash">

  <!-- ── POS PROVIDER FOOTER ── -->
  <div class="center xsmall" style="margin-top:4px;">Alliance End to End Solutions Inc.</div>
  <div class="center xsmall">VAT Reg TIN: 000-000-000-00000</div>
  <div class="center xsmall">10/F Buildcomm Center, Sumilon Rd.,</div>
  <div class="center xsmall">Cebu Business Park, Hipodromo, Cebu City</div>
  <div class="center xsmall">Accr#: 0812211728322025102684</div>
  <div class="center xsmall">Date Issued: 11-13-2025</div>
  <div class="center xsmall">Valid Until: 11-12-2030</div>

  <button class="btn-print-action no-print" onclick="window.print()">🖨️ Print</button>
  </body></html>`);
  w.document.close();
  w.focus();
  setTimeout(() => { w.print(); }, 700);
}

// ── Void / Refund ─────────────────────────────────────────────
let vrCurrentOrderId   = null;
let vrCurrentTable     = '';
let vrCurrentTotal     = 0;
let vrCurrentAction    = 'void';
let vrCurrentItems     = []; // for partial refund: [{menu_id, name, qty, unit_price, refundQty}]

function openVoidModal(orderId, tableNo, total) {
  vrCurrentOrderId = orderId;
  vrCurrentTable   = tableNo;
  vrCurrentTotal   = total;
  vrCurrentAction  = 'void';
  vrCurrentItems   = [];

  document.getElementById('vrModalTitle').innerHTML   = 'Void <span>Order</span>';
  document.getElementById('vrModalSubtitle').textContent = `Bill No. ${tableNo}`;
  document.getElementById('vrOrderMeta').innerHTML = `
    <div>Order <strong>#${orderId}</strong></div>
    <div style="text-align:center">₱${total.toLocaleString('en',{minimumFractionDigits:2})}<br><small style="color:var(--muted)">Total</small></div>
    <div style="text-align:right">Bill No. <strong>${tableNo}</strong></div>`;

  // Hide items section for full void
  document.getElementById('vrItemsSection').style.display = 'none';

  document.getElementById('vrAmtLabel').textContent = 'Amount to Reverse';
  document.getElementById('vrAmtValue').textContent = `₱ ${total.toLocaleString('en',{minimumFractionDigits:2})}`;
  document.getElementById('vrAmtDisplay').className = 'change-display negative';
  document.getElementById('vrAmtValue').className   = 'change-amount negative';
  document.getElementById('vrAmtLabel').className   = 'change-label negative';

  document.getElementById('vrConfirmLabel').textContent = 'Confirm Void';
  document.getElementById('vrConfirmBtn').style.background = 'var(--red)';
  document.getElementById('vrReason').value = '';

  const m = document.getElementById('vrModal');
  m.style.display = 'flex';
  m.style.animation = 'fadeIn .22s ease';
}

async function openRefundModal(orderId, tableNo, total) {
  vrCurrentOrderId = orderId;
  vrCurrentTable   = tableNo;
  vrCurrentTotal   = total;
  vrCurrentAction  = 'refund';

  document.getElementById('vrModalTitle').innerHTML   = 'Refund <span>Order</span>';
  document.getElementById('vrModalSubtitle').textContent = `Bill No. ${tableNo}`;
  document.getElementById('vrOrderMeta').innerHTML = `
    <div>Order <strong>#${orderId}</strong></div>
    <div style="text-align:center">₱${total.toLocaleString('en',{minimumFractionDigits:2})}<br><small style="color:var(--muted)">Total</small></div>
    <div style="text-align:right">Bill No. <strong>${tableNo}</strong></div>`;

  document.getElementById('vrItemsSection').style.display = 'block';
  document.getElementById('vrItemsLabel').textContent = 'Select items to refund:';

  // Fetch order items from server
  const listEl = document.getElementById('vrItemsList');
  listEl.innerHTML = `<div style="text-align:center;padding:20px;color:var(--muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>`;

  try {
    const res  = await fetch(`../Backend/pos_get_order_items.php?order_id=${orderId}`);
    const data = await res.json();
    if (!data.success) throw new Error(data.message);
    vrCurrentItems = data.items.map(i => ({...i, refundQty: i.qty}));
    renderVrItems();
  } catch(e) {
    // Fallback: show full refund only
    vrCurrentItems = [];
    listEl.innerHTML = `<div style="font-size:12px;color:var(--muted);padding:8px 0;">Could not load items — full refund will be applied.</div>`;
  }

  document.getElementById('vrAmtLabel').textContent = 'Refund Amount';
  document.getElementById('vrConfirmLabel').textContent = 'Confirm Refund';
  document.getElementById('vrConfirmBtn').style.background = 'var(--blue)';
  document.getElementById('vrReason').value = '';

  updateVrTotal();

  const m = document.getElementById('vrModal');
  m.style.display = 'flex';
  m.style.animation = 'fadeIn .22s ease';
}

function renderVrItems() {
  const listEl = document.getElementById('vrItemsList');
  if (!vrCurrentItems.length) return;
  listEl.innerHTML = vrCurrentItems.map((item, idx) => `
    <div style="display:flex;align-items:center;gap:10px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:10px 12px;">
      <div style="flex:1;min-width:0;">
        <div style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${item.menu_name}</div>
        <div style="font-size:11px;color:var(--muted);margin-top:2px;">₱${parseFloat(item.unit_price).toLocaleString('en',{minimumFractionDigits:2})} × ordered: ${item.qty}</div>
      </div>
      <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
        <button class="qty-btn" onclick="changeVrQty(${idx},-1)" style="width:28px;height:28px;border-radius:7px;"><i class="fa-solid fa-minus" style="font-size:9px"></i></button>
        <span style="font-size:13px;font-weight:700;min-width:18px;text-align:center;" id="vr-qty-${idx}">${item.refundQty}</span>
        <button class="qty-btn" onclick="changeVrQty(${idx},1)" style="width:28px;height:28px;border-radius:7px;"><i class="fa-solid fa-plus" style="font-size:9px"></i></button>
      </div>
      <div style="font-size:13px;font-weight:700;color:var(--blue);min-width:68px;text-align:right;" id="vr-sub-${idx}">
        ₱${(parseFloat(item.unit_price)*item.refundQty).toLocaleString('en',{minimumFractionDigits:2})}
      </div>
    </div>`).join('');
  updateVrTotal();
}

function changeVrQty(idx, delta) {
  const item = vrCurrentItems[idx];
  if (!item) return;
  item.refundQty = Math.max(0, Math.min(item.qty, item.refundQty + delta));
  document.getElementById(`vr-qty-${idx}`).textContent = item.refundQty;
  document.getElementById(`vr-sub-${idx}`).textContent =
    '₱' + (parseFloat(item.unit_price) * item.refundQty).toLocaleString('en',{minimumFractionDigits:2});
  updateVrTotal();
}

function updateVrTotal() {
  let amt = 0;
  if (vrCurrentAction === 'void') {
    amt = vrCurrentTotal;
  } else if (vrCurrentItems.length) {
    amt = vrCurrentItems.reduce((s, i) => s + parseFloat(i.unit_price) * i.refundQty, 0);
  } else {
    amt = vrCurrentTotal;
  }
  document.getElementById('vrAmtValue').textContent = `₱ ${amt.toLocaleString('en',{minimumFractionDigits:2})}`;
}

function closeVrModal() {
  const m = document.getElementById('vrModal');
  m.style.transition = 'opacity .18s ease';
  m.style.opacity = '0';
  setTimeout(() => { m.style.display = 'none'; m.style.opacity = ''; m.style.transition = ''; }, 180);
}

async function submitVoidRefund() {
  const btn    = document.getElementById('vrConfirmBtn');
  const reason = document.getElementById('vrReason').value.trim();

  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing…';

  const payload = {
    action:   vrCurrentAction,
    order_id: vrCurrentOrderId,
    reason,
  };

  if (vrCurrentAction === 'refund' && vrCurrentItems.length) {
    const refundItems = vrCurrentItems
      .filter(i => i.refundQty > 0)
      .map(i => ({ menu_id: i.menu_id, qty: i.refundQty }));
    if (!refundItems.length) {
      showToast('<i class="fa-solid fa-triangle-exclamation me-1"></i> Select at least 1 item to refund.', 'var(--red)', 3000);
      btn.disabled = false;
      btn.innerHTML = `<i class="fa-solid fa-rotate-left"></i> <span id="vrConfirmLabel">Confirm Refund</span>`;
      return;
    }
    // If all items at full qty, omit refund_items to trigger full refund path
    const isFullRefund = refundItems.every((ri, idx) => ri.qty === vrCurrentItems[idx]?.qty);
    if (!isFullRefund) payload.refund_items = refundItems;
  }

  try {
    const res  = await fetch('../Backend/pos_void_refund.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload),
    });
    const data = await res.json();
    btn.disabled = false;

    if (data.success) {
      closeVrModal();
      const color = vrCurrentAction === 'void' ? 'var(--red)' : 'var(--blue)';
      const icon  = vrCurrentAction === 'void' ? 'fa-ban' : 'fa-rotate-left';
      showToast(`<i class="fa-solid ${icon} me-1"></i> ${data.message}`, color, 4000);

      // Update history row in DOM
      const histRow = document.getElementById(`hist-order-${vrCurrentOrderId}`);
      if (histRow) {
        const statusLabel = data.new_status === 'voided' ? 'Voided' : 'Refunded';
        const statusColor = data.new_status === 'voided' ? 'red'    : 'blue';
        const actionBtnsEl = histRow.querySelector('[style*="display:flex;gap:5px"]');
        if (actionBtnsEl) actionBtnsEl.remove();
        const idEl = histRow.querySelector('.history-id');
        if (idEl) idEl.insertAdjacentHTML('beforeend',
          `<span class="badge-pill ${statusColor}" style="font-size:9px"><i class="fa-solid fa-${data.new_status==='voided'?'ban':'rotate-left'}" style="font-size:8px"></i> ${statusLabel}</span>`);
        const amtEl = histRow.querySelector('.history-amt');
        if (amtEl) { amtEl.style.color = 'var(--muted)'; amtEl.innerHTML = `<s style="font-size:11px;">${amtEl.textContent}</s>`; }
        const ic = histRow.querySelector('.history-ic');
        if (ic) { ic.style.background = 'var(--surface3)'; ic.querySelector('i').style.color = 'var(--muted)'; }
      }

      // Mark in session orderHistory
      const oh = orderHistory.find(o => o.id === vrCurrentOrderId);
      if (oh) oh.status = data.new_status;

    } else {
      showToast(`<i class="fa-solid fa-circle-xmark me-1"></i> ${data.message}`, 'var(--red)', 4000);
      const lbl = vrCurrentAction === 'void' ? 'Confirm Void' : 'Confirm Refund';
      btn.innerHTML = `<i class="fa-solid fa-${vrCurrentAction==='void'?'ban':'rotate-left'}"></i> ${lbl}`;
    }
  } catch (err) {
    btn.disabled = false;
    showToast('<i class="fa-solid fa-triangle-exclamation me-1"></i> Network error. Try again.', 'var(--red)', 3000);
    btn.innerHTML = `<i class="fa-solid fa-${vrCurrentAction==='void'?'ban':'rotate-left'}"></i> Confirm`;
  }
}

// ── Mobile Cart Drawer ───────────────────────────────────────
function openMobileCart() {
  const panel = document.querySelector('.pos-order');
  if (!panel) return;
  panel.classList.add('mobile-open');
  document.getElementById('mobileCartOverlay').style.display = 'block';
  document.body.style.overflow = 'hidden';
}

function closeMobileCart() {
  const panel = document.querySelector('.pos-order');
  if (!panel) return;
  panel.classList.remove('mobile-open');
  document.getElementById('mobileCartOverlay').style.display = 'none';
  document.body.style.overflow = '';
}

function updateMobileCartBadge() {
  const count = (typeof cart !== 'undefined') ? cart.reduce((s, i) => s + i.qty, 0) : 0;
  const badge = document.getElementById('mobileCartCount');
  if (!badge) return;
  if (count > 0) {
    badge.style.display = 'flex';
    badge.textContent = count > 99 ? '99+' : count;
  } else {
    badge.style.display = 'none';
  }
}

// Watch the cart scroll element for changes to update the badge
(function() {
  const obs = new MutationObserver(updateMobileCartBadge);
  document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('cartScroll');
    if (el) obs.observe(el, { childList: true, subtree: true });
    updateMobileCartBadge();
  });
})();

// ── Live Clock ────────────────────────────────────────────────
(function startClock(){
  const el=document.getElementById('liveClock');
  if(!el) return;
  function tick(){
    el.textContent=new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
  }
  tick(); setInterval(tick,1000);
})();

function initTheme(){
  const saved=localStorage.getItem('pos-theme')||'dark';
  applyTheme(saved);
  document.getElementById('themeBtn').addEventListener('click',()=>{
    const btn=document.getElementById('themeBtn');
    btn.classList.add('spinning');
    setTimeout(()=>btn.classList.remove('spinning'),450);
    applyTheme(document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark');
  });
}
function applyTheme(t){
  document.documentElement.setAttribute('data-theme',t);
  localStorage.setItem('pos-theme',t);
  document.getElementById('themeIco').className=t==='dark'?'fa-solid fa-sun':'fa-solid fa-moon';
}

// ── Stagger product cards ────────────────────────────────────
function staggerCards(){
  const cards=document.querySelectorAll('.product-card');
  cards.forEach((card,i)=>{
    card.style.opacity='0';
    card.style.transform='scale(0.9) translateY(10px)';
    setTimeout(()=>{
      card.style.transition='opacity 0.28s ease, transform 0.32s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.25s ease, border-color 0.25s ease';
      card.style.opacity='1';
      card.style.transform='';
    }, i*40);
  });
}

// ── Toast ─────────────────────────────────────────────────────
function spawnParticle(text,x,y){
  const p=document.createElement('div');
  p.className='score-particle';
  p.textContent=text;
  p.style.left=x+'px'; p.style.top=y+'px';
  document.body.appendChild(p);
  setTimeout(()=>p.remove(),1100);
}

let toastTimer;
function showToast(msg,color='var(--accent)',dur=2400){
  const el=document.getElementById('toast');
  el.innerHTML=msg; el.style.background=color;
  el.classList.remove('show');
  void el.offsetWidth;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer=setTimeout(()=>el.classList.remove('show'),dur);
}
</script>
<script src="dist/js/empress-realtime.js" data-scope="pos"></script>

<!-- ══ REAL-TIME VOID / REFUND WATCHER ══════════════════════════ -->
<script>
(function () {
  'use strict';

  var POLL_MS = 10000; // check every 10 s

  // Map of order id → last known status (seeded from DB_HISTORY on first poll)
  var _knownStatus = {};
  var _seeded = false;

  function seed() {
    if (_seeded) return;
    _seeded = true;
    // Seed from DB_HISTORY (PHP-injected)
    if (typeof DB_HISTORY !== 'undefined') {
      DB_HISTORY.forEach(function (o) {
        _knownStatus[o.id] = o.status;
      });
    }
    // Seed from live session orders
    if (typeof orderHistory !== 'undefined') {
      orderHistory.forEach(function (o) {
        if (o.id) _knownStatus[o.id] = o.status || 'pending';
      });
    }
  }

  // Apply voided/refunded styling to a history row without re-rendering the whole list
  function markRowDone(orderId, newStatus) {
    var row = document.getElementById('hist-order-' + orderId);
    if (!row) return;

    // Already marked — skip
    if (row.getAttribute('data-rt-status') === newStatus) return;
    row.setAttribute('data-rt-status', newStatus);

    var isVoided   = newStatus === 'voided';
    var isRefunded = newStatus === 'refunded' || newStatus === 'partial_refund';
    var label      = isVoided ? 'Voided' : 'Refunded';
    var icon       = isVoided ? 'fa-ban' : 'fa-rotate-left';
    var badgeClass = isVoided ? 'red' : 'blue';

    // Icon area
    var ic = row.querySelector('.history-ic');
    if (ic) {
      ic.style.background = 'var(--surface3)';
      var ico = ic.querySelector('i');
      if (ico) ico.style.color = 'var(--muted)';
    }

    // Add status badge next to order id (avoid duplicates)
    var idEl = row.querySelector('.history-id');
    if (idEl && !idEl.querySelector('.rt-status-badge')) {
      var badge = document.createElement('span');
      badge.className = 'badge-pill ' + badgeClass + ' rt-status-badge';
      badge.style.fontSize = '9px';
      badge.innerHTML = '<i class="fa-solid ' + icon + '" style="font-size:8px"></i> ' + label;
      idEl.appendChild(badge);
    }

    // Strike-through the amount
    var amtEl = row.querySelector('.history-amt');
    if (amtEl && !amtEl.querySelector('s')) {
      amtEl.style.color = 'var(--muted)';
      var raw = amtEl.textContent.trim();
      amtEl.innerHTML = '<s style="font-size:11px;">' + raw + '</s>';
    }

    // Remove any void/refund action buttons that were in the row
    var actEl = row.querySelector('[style*="display:flex;gap:5px"]');
    if (actEl) actEl.remove();

    // Flash row to draw attention
    row.style.transition = 'background .15s';
    row.style.background = isVoided
      ? 'rgba(239,68,68,.08)'
      : 'rgba(59,130,246,.08)';
    setTimeout(function () { row.style.background = ''; }, 1800);
  }

  // Update in-memory orderHistory status too
  function syncMemoryStatus(orderId, newStatus) {
    if (typeof orderHistory === 'undefined') return;
    var oh = orderHistory.find(function (o) { return o.id === orderId; });
    if (oh) oh.status = newStatus;
    // Also sync DB_HISTORY if it exists
    if (typeof DB_HISTORY !== 'undefined') {
      var dh = DB_HISTORY.find(function (o) { return parseInt(o.id) === orderId; });
      if (dh) dh.status = newStatus;
    }
  }

  function poll() {
    seed(); // ensure seeded before first real poll

    fetch(window.location.pathname + '?rt=1', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
      .then(function (d) {
        var changed = [];

        (d.orders || []).forEach(function (o) {
          var id        = o.id;
          var newStatus = o.status;
          var prev      = _knownStatus[id];

          // Track all known orders
          if (prev === undefined) {
            _knownStatus[id] = newStatus;
            return; // new order we haven't seen — just record it
          }

          // Detect a status change to voided / refunded
          var wasGood = prev !== 'voided' && prev !== 'refunded' && prev !== 'partial_refund';
          var isBad   = newStatus === 'voided' || newStatus === 'refunded' || newStatus === 'partial_refund';

          if (wasGood && isBad) {
            _knownStatus[id] = newStatus;
            syncMemoryStatus(id, newStatus);
            markRowDone(id, newStatus);
            changed.push({ id: id, status: newStatus });
          }
        });

        // If any changed, show a toast
        if (changed.length) {
          var voidCount   = changed.filter(function (c) { return c.status === 'voided'; }).length;
          var refundCount = changed.filter(function (c) { return c.status !== 'voided'; }).length;
          var msgs = [];
          if (voidCount)   msgs.push(voidCount + ' order' + (voidCount > 1 ? 's' : '') + ' voided');
          if (refundCount) msgs.push(refundCount + ' order' + (refundCount > 1 ? 's' : '') + ' refunded');
          if (typeof showToast === 'function') {
            showToast(
              '<i class="fa-solid fa-triangle-exclamation me-1"></i> ' + msgs.join(' · '),
              changed[0].status === 'voided' ? 'var(--red)' : 'var(--blue)',
              6000
            );
          }
          // Re-render history panel if it's open
          var hp = document.getElementById('panel-history');
          if (hp && hp.classList.contains('open') && typeof refreshHistoryPanel === 'function') {
            refreshHistoryPanel();
          }
        }
      })
      .catch(function () { /* retry silently */ })
      .finally(function () { setTimeout(poll, POLL_MS); });
  }

  // Start after DOM + initial JS is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { setTimeout(poll, 3000); });
  } else {
    setTimeout(poll, 3000);
  }
})();
</script>
<!-- ══ REAL-TIME MENU AVAILABILITY WATCHER ══════════════════════ -->
<script>
(function () {
  'use strict';

  var POLL_MS = 15000; // poll every 15 s

  // Local snapshot: id → { is_available, price, name, image, category }
  var _menuSnapshot = {};
  var _seeded = false;

  // ── Seed initial state from the products array PHP injected ────
  function seed() {
    if (_seeded) return;
    _seeded = true;
    if (typeof products !== 'undefined') {
      products.forEach(function (p) {
        _menuSnapshot[p.id] = { is_available: true, price: p.price, name: p.name, image: p.image || '', category: p.cat };
      });
    }
  }

  // ── Set indicator state ────────────────────────────────────────
  function setIndicator(state, text) {
    var el  = document.getElementById('menuRtIndicator');
    var lbl = document.getElementById('menuRtLabel');
    if (!el || !lbl) return;
    el.className = 'menu-rt-indicator ' + state;
    lbl.textContent = text;
  }

  // ── Apply availability to a single product card in the main grid ──
  function applyCardState(id, isAvail, changed) {
    var card = document.querySelector('.product-card[data-id="' + id + '"]');
    if (!card) return;
    var wasUnavail = card.classList.contains('unavailable');
    if (isAvail && wasUnavail) {
      card.classList.remove('unavailable');
      if (changed) { card.classList.add('state-changed'); setTimeout(function(){card.classList.remove('state-changed');},900); }
    } else if (!isAvail && !wasUnavail) {
      card.classList.add('unavailable');
      if (changed) { card.classList.add('state-changed'); setTimeout(function(){card.classList.remove('state-changed');},900); }
    }
  }

  // ── Rebuild the menu panel body ────────────────────────────────
  function renderMenuPanel(allItems) {
    var body = document.getElementById('menuPanelBody');
    if (!body) return;

    // Group by category
    var cats = {};
    allItems.forEach(function (item) {
      if (!cats[item.category]) cats[item.category] = [];
      cats[item.category].push(item);
    });

    var catOrder = Object.keys(cats).sort();
    var html = '';
    catOrder.forEach(function (cat) {
      var items = cats[cat];
      var emoji = (typeof CAT_ICON !== 'undefined' && CAT_ICON[cat]) ? CAT_ICON[cat] : '🍽️';
      var avCount = items.filter(function(i){ return i.is_available; }).length;

      html += '<div class="panel-section-title">' + emoji + ' ' + esc(cat) +
        ' <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:12px;color:var(--muted2)">(' +
        avCount + '/' + items.length + ' available)</span></div>';
      html += '<div class="menu-panel-grid" style="margin-bottom:24px;">';

      items.forEach(function (item) {
        var isAv  = item.is_available;
        var imgHtml = item.image
          ? '<img src="' + item.image + '" alt="' + esc(item.name) + '" style="width:100%;height:100%;object-fit:cover;border-radius:12px;" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">' +
            '<span style="display:none;width:100%;height:100%;align-items:center;justify-content:center;font-size:24px;">' + emoji + '</span>'
          : '<span style="display:flex;width:100%;height:100%;align-items:center;justify-content:center;font-size:24px;">' + emoji + '</span>';

        var clickAttr = isAv
          ? 'onclick="closePanel();setTimeout(()=>{activeCat=\'' + esc(cat) + '\';renderCats();renderProducts();requestAnimationFrame(staggerCards);},200);" style="cursor:pointer;"'
          : 'style="cursor:not-allowed;"';

        html += '<div class="menu-panel-card' + (isAv ? '' : ' unavailable') + '" data-menu-id="' + item.id + '" ' + clickAttr + '>' +
          '<div class="menu-panel-emoji" style="overflow:hidden;border-radius:12px;flex-shrink:0;">' + imgHtml + '</div>' +
          '<div class="menu-panel-info">' +
            '<div class="menu-panel-name">' + esc(item.name) + '</div>' +
            '<div class="menu-panel-cat">' + esc(item.category) + '</div>' +
            '<div class="menu-panel-price">₱' + parseFloat(item.price).toLocaleString('en', {minimumFractionDigits:2}) + '</div>' +
            '<div><span class="avail-badge ' + (isAv ? 'on' : 'off') + '">' +
              '<i class="fa-solid ' + (isAv ? 'fa-circle-check' : 'fa-circle-xmark') + '" style="font-size:9px"></i> ' +
              (isAv ? 'Available' : 'Unavailable') +
            '</span></div>' +
          '</div>' +
        '</div>';
      });

      html += '</div>';
    });

    body.innerHTML = html;
  }

  // ── Tiny HTML escape helper ────────────────────────────────────
  function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  // ── Update the main POS product grid cards ────────────────────
  function syncProductGrid(allItems, changedIds) {
    allItems.forEach(function (item) {
      var changed = changedIds.indexOf(item.id) !== -1;
      applyCardState(item.id, item.is_available, changed);
    });
  }

  // ── Update the in-memory products array so re-renders respect availability ──
  function syncProductsArray(allItems) {
    if (typeof products === 'undefined') return;
    allItems.forEach(function (serverItem) {
      var local = products.find(function (p) { return p.id === serverItem.id; });
      if (local) {
        local._available = serverItem.is_available; // custom flag (products[] only tracks available ones)
      }
    });
  }

  // ── Main poll ─────────────────────────────────────────────────
  function poll() {
    seed();
    setIndicator('syncing', 'Syncing…');

    fetch(window.location.pathname + '?menu_rt=1', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
      .then(function (d) {
        var allItems   = d.menu || [];
        var changedIds = [];
        var toastMsgs  = [];

        allItems.forEach(function (item) {
          var prev = _menuSnapshot[item.id];
          var prevAvail = prev ? prev.is_available : true; // assume available if unknown

          // Detect availability change
          if (prev !== undefined && prevAvail !== item.is_available) {
            changedIds.push(item.id);
            toastMsgs.push({
              name:    item.name,
              avail:   item.is_available,
            });
          }

          _menuSnapshot[item.id] = {
            is_available: item.is_available,
            price:        item.price,
            name:         item.name,
            image:        item.image || '',
            category:     item.category,
          };
        });

        // Apply to product grid
        syncProductGrid(allItems, changedIds);
        syncProductsArray(allItems);

        // Re-render menu panel if it's open
        var menuPanel = document.getElementById('panel-menu');
        if (menuPanel && menuPanel.classList.contains('open')) {
          renderMenuPanel(allItems);
        }

        // Toast notifications for changed items
        if (toastMsgs.length && typeof showToast === 'function') {
          var nowAvail   = toastMsgs.filter(function(m){ return m.avail; });
          var nowUnAvail = toastMsgs.filter(function(m){ return !m.avail; });
          var parts = [];
          if (nowAvail.length)   parts.push('<i class="fa-solid fa-circle-check" style="color:var(--green)"></i> ' + nowAvail.map(function(m){ return '<strong>' + esc(m.name) + '</strong>'; }).join(', ') + ' now available');
          if (nowUnAvail.length) parts.push('<i class="fa-solid fa-circle-xmark" style="color:var(--red)"></i> ' + nowUnAvail.map(function(m){ return '<strong>' + esc(m.name) + '</strong>'; }).join(', ') + ' unavailable');
          showToast(parts.join('<br>'), nowUnAvail.length ? 'var(--surface3)' : 'var(--green)', 5000);
        }

        setIndicator('live', 'Live');
      })
      .catch(function () {
        setIndicator('', 'Offline');
      })
      .finally(function () {
        setTimeout(poll, POLL_MS);
      });
  }

  // ── openPanel hook: render menu panel with latest data on open ──
  var _origOpenPanel = typeof openPanel === 'function' ? openPanel : null;
  document.addEventListener('DOMContentLoaded', function () {
    // Wrap openPanel to render menu panel when it opens
    if (typeof openPanel === 'function') {
      var _orig = openPanel;
      window.openPanel = function (id, navBtn) {
        _orig(id, navBtn);
        if (id === 'panel-menu') {
          var allItems = Object.keys(_menuSnapshot).map(function(k){
            var s = _menuSnapshot[k];
            return { id: parseInt(k), name: s.name, price: s.price, category: s.category, image: s.image, is_available: s.is_available };
          });
          if (allItems.length) renderMenuPanel(allItems);
        }
      };
    }

    // Start polling after 2 s
    setTimeout(poll, 2000);
  });

})();
</script>
<!-- ══ END REAL-TIME MENU WATCHER ═══════════════════════════════ -->
</body>
</html>