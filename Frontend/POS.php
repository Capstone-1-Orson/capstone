<?php
// Frontend/POS.php  (sits directly inside Frontend/)
session_start();
if (!isset($_SESSION['user'])) {
    header("Location:login-v2.html");
    exit();
}

require_once '../Backend/conn.php';

if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? 'conn.php did not define $conn'));
}

$menu_items = [];
$res = $conn->query("SELECT id, name, description, price, category FROM menu WHERE is_available = 1 ORDER BY category, name");
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

$conn->close();

$cat_emoji = [
    'Main Course' => '🍽️',
    'Appetizer'   => '🥗',
    'Dessert'     => '🍰',
    'Beverage'    => '☕',
];

$products_json = json_encode(array_map(function($item) use ($cat_emoji) {
    return [
        'id'    => (int)$item['id'],
        'cat'   => $item['category'],
        'emoji' => $cat_emoji[$item['category']] ?? '🍽️',
        'name'  => $item['name'],
        'price' => (float)$item['price'],
        'desc'  => $item['description'] ?? '',
        'badge' => '',
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
.pos-main{flex:1;display:flex;flex-direction:column;overflow:hidden;padding:20px 18px;gap:16px;min-width:0;background:var(--bg);transition:background var(--tr);}
.pos-topbar{display:flex;align-items:center;gap:11px;flex-shrink:0;animation:fadeUp .4s ease;}
.pos-title{font-family:'Playfair Display',serif;font-size:23px;font-weight:700;white-space:nowrap;}
.pos-title span{color:var(--accent);}
.pos-search{flex:1;max-width:360px;position:relative;margin-left:auto;}
.pos-search i.si{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none;}
.pos-search input{width:100%;padding:10px 36px 10px 36px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13.5px;transition:all var(--tr);}
.pos-search input::placeholder{color:var(--muted);}
.pos-search input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);}
.pos-search .sc{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px;display:none;}
.pos-search input:not(:placeholder-shown) ~ .sc{display:block;}
.t-chip{display:flex;align-items:center;gap:7px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;padding:8px 13px;font-size:12.5px;color:var(--muted2);white-space:nowrap;}
.t-chip strong{color:var(--accent);font-weight:700;}

/* STATS */
.stats-strip{display:flex;gap:11px;flex-shrink:0;animation:fadeUp .4s .06s ease both;}
.stat-card{flex:1;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:13px 16px;display:flex;align-items:center;gap:13px;transition:all var(--tr);min-width:0;cursor:default;}
.stat-card:hover{border-color:var(--border2);transform:translateY(-2px);box-shadow:0 8px 24px var(--accent-glow);}
.stat-ic{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.stat-v{font-size:18px;font-weight:700;line-height:1.1;}
.stat-l{font-size:11px;color:var(--muted);margin-top:2px;}

/* CATEGORIES */
.cats{display:flex;gap:8px;overflow-x:auto;flex-shrink:0;padding-bottom:2px;animation:fadeUp .4s .1s ease both;}
.cats::-webkit-scrollbar{display:none;}
.cat-pill{display:flex;align-items:center;gap:6px;padding:7px 16px;border-radius:50px;border:1px solid var(--border2);background:var(--surface);color:var(--muted2);font-size:13px;font-weight:500;white-space:nowrap;flex-shrink:0;transition:all var(--tr);}
.cat-pill:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-soft);}
.cat-pill.active{background:var(--accent);border-color:var(--accent);color:#fff;font-weight:700;box-shadow:0 4px 14px var(--accent-glow);}
.cat-cnt{font-size:10px;background:rgba(255,255,255,.15);padding:1px 6px;border-radius:20px;font-weight:600;}
.cat-pill.active .cat-cnt{background:rgba(255,255,255,.25);color:#fff;}
.sec-label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;flex-shrink:0;}

/* PRODUCT GRID */
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(168px,1fr));gap:13px;overflow-y:auto;padding-right:2px;align-content:start;}
.product-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;display:flex;flex-direction:column;transition:all var(--tr);cursor:pointer;position:relative;animation:fadeUp .35s ease both;}
.product-card:hover{border-color:var(--accent);transform:translateY(-3px);box-shadow:0 12px 28px var(--accent-glow);}
.product-card:hover .card-add-btn{opacity:1;transform:translateY(0);}
.card-img-w{height:108px;position:relative;overflow:hidden;background:var(--surface2);}
.card-emoji{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:42px;transition:transform .45s ease;}
.product-card:hover .card-emoji{transform:scale(1.12);}
.card-bdg{position:absolute;top:8px;right:8px;background:var(--green);color:#fff;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:20px;}
.card-bdg.hot{background:var(--red);}
.card-bdg.new{background:var(--blue);}
.card-body-i{padding:11px;flex:1;display:flex;flex-direction:column;gap:7px;}
.card-name{font-weight:600;font-size:13.5px;line-height:1.3;}
.card-desc{font-size:11px;color:var(--muted);line-height:1.4;}
.card-foot{display:flex;align-items:center;justify-content:space-between;}
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
.tbl-row{display:flex;align-items:center;gap:7px;}
.tbl-label{font-size:11.5px;color:var(--muted);flex-shrink:0;}
.tbl-chips{display:flex;gap:5px;flex-wrap:wrap;}
.tbl-chip{padding:5px 10px;border-radius:7px;background:var(--surface2);border:1px solid var(--border2);font-size:12px;font-weight:600;color:var(--muted2);transition:all var(--tr);}
.tbl-chip.active{background:var(--accent-soft);border-color:rgba(233,30,140,.4);color:var(--accent);}
.tbl-chip:hover:not(.active){border-color:var(--accent);color:var(--accent);}

/* CART */
.cart-scroll{flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:8px;min-height:0;}
.empty-cart{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:9px;color:var(--muted);font-size:13px;text-align:center;animation:fadeIn .4s ease;}
.empty-cart i{font-size:38px;color:var(--surface3);margin-bottom:3px;}
.cart-item{display:flex;align-items:center;gap:10px;background:var(--surface2);border:1px solid var(--border);padding:10px 11px;border-radius:11px;transition:all var(--tr);animation:scaleIn .22s ease;}
.cart-item:hover{border-color:var(--border2);}
.ci-emoji{width:34px;height:34px;background:var(--surface3);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
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

/* COUPON */
.coupon-row{display:flex;gap:7px;}
.coupon-inp{flex:1;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;padding:8px 12px;color:var(--text);font-size:13px;transition:all var(--tr);}
.coupon-inp::placeholder{color:var(--muted);}
.coupon-inp:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);}
.btn-coupon{background:var(--surface3);color:var(--muted2);font-size:13px;font-weight:600;padding:8px 13px;border-radius:9px;border:1px solid var(--border2);transition:all var(--tr);}
.btn-coupon:hover{background:var(--accent-soft);color:var(--accent);border-color:rgba(233,30,140,.3);}

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
.pay-methods{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;}
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
@media(max-width:860px){.pos-order{display:none;}}
</style>
</head>
<body>

<!-- ── Sidebar ─────────────────────────────────────────────── -->
<aside class="pos-sidebar">
  <div class="s-logo">E</div>
  <button class="nav-btn active" title="Orders"><i class="fa-solid fa-receipt"></i>Orders</button>
  <button class="nav-btn" title="Dashboard"><i class="fa-solid fa-chart-pie"></i>Stats</button>
  <button class="nav-btn" title="Menu"><i class="fa-solid fa-utensils"></i>Menu</button>
  <button class="nav-btn" title="Tables"><i class="fa-solid fa-table-cells-large"></i>Tables</button>
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
  <div class="s-avatar" title="<?= htmlspecialchars($_SESSION['user']['firstname'] ?? 'User') ?>">
    <?= strtoupper(substr($_SESSION['user']['firstname'] ?? 'U', 0, 1) . substr($_SESSION['user']['lastname'] ?? '', 0, 1)) ?>
  </div>
</aside>

<!-- ── Main ───────────────────────────────────────────────── -->
<main class="pos-main">
  <div class="pos-topbar">
    <div class="pos-title">New <span>Order</span></div>
    <div class="pos-search">
      <i class="fa-solid fa-magnifying-glass si"></i>
      <input type="text" id="searchInput" placeholder="Search menu items…" autocomplete="off">
      <button class="sc" id="searchClear"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="t-chip">
      <i class="fa-solid fa-house-flag" style="color:var(--accent);font-size:11px"></i>
      Table <strong id="tableChip">#01</strong>
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
  <div class="sec-label">Menu Items</div>
  <div class="product-grid" id="productGrid"></div>
</main>

<!-- ── Order Panel ─────────────────────────────────────────── -->
<aside class="pos-order">
  <div class="o-top">
    <div class="o-title">Bill Order</div>
    <div class="o-date" id="orderDate"></div>
  </div>
  <div class="o-tabs">
    <button class="o-tab active" data-tab="dine"><i class="fa-solid fa-bowl-food"></i> Dine In</button>
    <button class="o-tab" data-tab="take"><i class="fa-solid fa-bag-shopping"></i> Takeaway</button>
    <button class="o-tab" data-tab="del"><i class="fa-solid fa-motorcycle"></i> Delivery</button>
  </div>
  <div class="tbl-row">
    <span class="tbl-label"><i class="fa-solid fa-chair" style="font-size:11px"></i> Table:</span>
    <div class="tbl-chips" id="tableChips"></div>
  </div>
  <div class="cart-scroll" id="cartScroll">
    <div class="empty-cart" id="emptyMsg">
      <i class="fa-solid fa-cart-shopping"></i>
      <strong>No items yet</strong>
      <small style="color:var(--muted);font-size:11.5px">Tap any item to add it here</small>
    </div>
  </div>
  <div class="coupon-row">
    <input type="text" class="coupon-inp" id="couponInput" placeholder="Promo / coupon code…">
    <button class="btn-coupon" id="btnCoupon"><i class="fa-solid fa-ticket me-1"></i>Apply</button>
  </div>
  <div class="o-summary">
    <div class="sum-row"><span class="sum-lbl">Subtotal</span><span class="sum-val" id="sumSub">₱ 0.00</span></div>
    <div class="sum-row" id="discRow" style="display:none"><span class="sum-lbl">Discount</span><span class="sum-val sum-disc" id="sumDisc">-₱ 0.00</span></div>
    <div class="sum-row"><span class="sum-lbl">Tax (1% VAT)</span><span class="sum-val" id="sumTax">₱ 0.00</span></div>
    <hr class="sum-div">
    <div class="sum-row sum-total"><span>Total</span><span class="sum-val" id="sumTotal">₱ 0.00</span></div>
  </div>

  <!-- Payment Method -->
  <div>
    <div class="pay-label mb-2">Payment Method</div>
    <div class="pay-methods">
      <button class="pay-btn active" data-method="Cash" onclick="selectPay(this)">
        <i class="fa-solid fa-money-bill-wave"></i>Cash
      </button>
      <button class="pay-btn" data-method="Card" onclick="selectPay(this)">
        <i class="fa-regular fa-credit-card"></i>Card
      </button>
      <button class="pay-btn" data-method="E-Pay" onclick="selectPay(this)">
        <i class="fa-solid fa-mobile-screen-button"></i>E-Pay
      </button>
    </div>
  </div>

  <!-- ── Cash Tendered & Change Calculator ──
  <div class="cash-section" id="cashSection">
    <div class="cash-section-title">
      <i class="fa-solid fa-money-bill-wave" style="color:var(--green)"></i> Cash Received
    </div>
    <div class="cash-input-wrap">
      <span class="cash-prefix">₱</span>
      <input type="number" class="cash-inp" id="cashInput"
             placeholder="Enter amount tendered…" min="0" step="1">
    </div>
    Quick-amount shortcut buttons, rendered by JS
    <div class="quick-amounts" id="quickAmounts"></div>
     Live change display
    <div class="change-display zero" id="changeDisplay">
      <span class="change-label" id="changeLabel">Change</span>
      <span class="change-amount zero" id="changeAmount">₱ —</span>
    </div> -->
  </div>

  <button class="btn-place" id="btnPlace" disabled>
    <i class="fa-solid fa-check"></i> Place Order
  </button>
</aside>

<!-- ── Receipt Modal container ───────────────────────────── -->
<div id="receiptContainer"></div>

<div class="pos-toast" id="toast"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Data from PHP ─────────────────────────────────────────────
const products = <?= $products_json ?>;
const DB_CATS  = <?= $cats_json ?>;

// ── State ─────────────────────────────────────────────────────
let cart = [], activeCat = 'all', searchQ = '', discount = 0, selTable = '01';
let sessionRevenue = 0, sessionOrders = 0;
let currentTotal = 0;

const CAT_ICON = {
  'Main Course':'🍽️','Appetizer':'🥗','Dessert':'🍰','Beverage':'☕'
};

document.addEventListener('DOMContentLoaded', () => {
  setDate();
  renderCats();
  renderTables();
  renderProducts();
  initSearch();
  initTabs();
  initTheme();
  initCoupon();
  initCashInput();
  document.getElementById('btnPlace').addEventListener('click', placeOrder);
  document.querySelectorAll('.nav-btn').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('.nav-btn').forEach(x => x.classList.remove('active'));
    b.classList.add('active');
  }));
});

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
  allBtn.addEventListener('click',()=>{ activeCat='all'; renderCats(); renderProducts(); });
  row.appendChild(allBtn);
  DB_CATS.forEach(cat=>{
    const cnt=products.filter(p=>p.cat===cat).length;
    if(!cnt) return;
    const btn=document.createElement('button');
    btn.className='cat-pill'+(activeCat===cat?' active':'');
    btn.innerHTML=`<span>${CAT_ICON[cat]||'🍽️'}</span> ${cat} <span class="cat-cnt">${cnt}</span>`;
    btn.addEventListener('click',()=>{ activeCat=cat; renderCats(); renderProducts(); });
    row.appendChild(btn);
  });
}

// ── Tables ────────────────────────────────────────────────────
function renderTables() {
  const w=document.getElementById('tableChips');
  w.innerHTML='';
  ['01','02','03','04','05','06','07','08'].forEach(t=>{
    const b=document.createElement('button');
    b.className='tbl-chip'+(t===selTable?' active':'');
    b.textContent='#'+t;
    b.addEventListener('click',()=>{
      selTable=t;
      document.getElementById('tableChip').textContent='#'+t;
      renderTables();
    });
    w.appendChild(b);
  });
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
    card.style.animationDelay=(i*.04)+'s';
    card.dataset.id=p.id;
    card.innerHTML=`
      <div class="card-img-w">
        <div class="card-emoji">${p.emoji}</div>
        ${p.badge?`<span class="card-bdg ${p.badge.toLowerCase()==='hot'?'hot':p.badge.toLowerCase()==='new'?'new':''}">${p.badge}</span>`:''}
      </div>
      <div class="card-body-i">
        <div class="card-name">${p.name}</div>
        <div class="card-desc">${p.desc||''}</div>
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
  });
}

// ── Cart Operations ───────────────────────────────────────────
function addToCart(id,e) {
  const product=products.find(p=>p.id===id);
  if(!product) return;
  const ex=cart.find(c=>c.id===id);
  if(ex) ex.qty++;
  else cart.push({...product,qty:1});
  refreshCardState(id);
  updateCartUI();
  showToast(`<i class="fa-solid fa-circle-plus me-1"></i> ${product.name} added!`,'var(--accent)');
}

function changeQty(id,d) {
  const item=cart.find(c=>c.id===id);
  if(!item) return;
  item.qty+=d;
  if(item.qty<=0){ cart=cart.filter(c=>c.id!==id); refreshCardState(id); }
  updateCartUI();
}

function removeItem(id) {
  cart=cart.filter(c=>c.id!==id);
  refreshCardState(id);
  updateCartUI();
}

function refreshCardState(id) {
  const card=document.querySelector(`.product-card[data-id="${id}"]`);
  if(!card) return;
  const inCart=cart.some(c=>c.id===id);
  card.classList.toggle('in-cart',inCart);
  const btn=card.querySelector('.card-add-btn');
  if(btn) btn.innerHTML=`<i class="fa-solid fa-${inCart?'check':'plus'}"></i> ${inCart?'Added':'Add to Order'}`;
}

function updateCartUI() {
  const scroll=document.getElementById('cartScroll');
  const emptyMsg=document.getElementById('emptyMsg');
  const placeBtn=document.getElementById('btnPlace');
  scroll.querySelectorAll('.cart-item').forEach(el=>el.remove());

  if(!cart.length){
    emptyMsg.style.display='flex';
    placeBtn.disabled=true;
  } else {
    emptyMsg.style.display='none';
    placeBtn.disabled=false;
    cart.forEach(item=>{
      const el=document.createElement('div');
      el.className='cart-item';
      el.innerHTML=`
        <div class="ci-emoji">${item.emoji}</div>
        <div class="ci-info">
          <div class="ci-name">${item.name}</div>
          <div class="ci-price">₱${item.price.toLocaleString()} each</div>
        </div>
        <div class="qty-ctrl">
          <button class="qty-btn" onclick="changeQty(${item.id},-1)"><i class="fa-solid fa-minus" style="font-size:9px"></i></button>
          <span class="qty-v">${item.qty}</span>
          <button class="qty-btn" onclick="changeQty(${item.id},1)"><i class="fa-solid fa-plus" style="font-size:9px"></i></button>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0">
          <span class="ci-total">₱${(item.price*item.qty).toLocaleString()}</span>
          <button class="btn-rm" onclick="removeItem(${item.id})"><i class="fa-solid fa-trash-can"></i></button>
        </div>`;
      scroll.appendChild(el);
    });
  }

  const sub=cart.reduce((s,c)=>s+c.price*c.qty,0);
  const tax=sub*.01;
  const tot=parseFloat(Math.max(sub+tax-discount,0).toFixed(2));
  currentTotal=tot;

  document.getElementById('sumSub').textContent =`₱ ${sub.toLocaleString('en',{minimumFractionDigits:2})}`;
  document.getElementById('sumTax').textContent =`₱ ${tax.toFixed(2)}`;
  document.getElementById('sumTotal').textContent=`₱ ${tot.toFixed(2)}`;
  const dr=document.getElementById('discRow');
  if(discount>0){ dr.style.display='flex'; document.getElementById('sumDisc').textContent=`-₱ ${discount.toFixed(2)}`; }
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
  const active=document.querySelector('.pay-btn.active');
  return active && active.dataset.method==='Cash';
}

function selectPay(btn) {
  document.querySelectorAll('.pay-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('cashSection').style.display=
    (btn.dataset.method==='Cash') ? 'flex' : 'none';
}

// ── Coupon ────────────────────────────────────────────────────
function initCoupon() {
  document.getElementById('btnCoupon').addEventListener('click',()=>{
    const code=document.getElementById('couponInput').value.trim().toUpperCase();
    if(!cart.length){ showToast('<i class="fa-solid fa-cart-shopping me-1"></i> Add items first!','var(--red)'); return; }
    const sub=cart.reduce((s,c)=>s+c.price*c.qty,0);
    if     (code==='EMBER10'){ discount=sub*.10; showToast('<i class="fa-solid fa-tag me-1"></i> 10% discount applied!','var(--accent)'); }
    else if(code==='SAVE20') { discount=sub*.20; showToast('<i class="fa-solid fa-tag me-1"></i> 20% discount applied!','var(--accent)'); }
    else if(code==='FLAT50') { discount=50;      showToast('<i class="fa-solid fa-tag me-1"></i> ₱50 flat discount!','var(--accent)'); }
    else{ showToast('<i class="fa-solid fa-circle-xmark me-1"></i> Invalid coupon code','var(--red)'); return; }
    updateCartUI();
  });
}

function initTabs() {
  document.querySelectorAll('.o-tab').forEach(b=>b.addEventListener('click',()=>{
    document.querySelectorAll('.o-tab').forEach(x=>x.classList.remove('active'));
    b.classList.add('active');
  }));
}

// ── Place Order ───────────────────────────────────────────────
function placeOrder() {
  if(!cart.length) return;

  // Validate cash amount if cash payment
  if(isCashPayment()){
    const cashVal=parseFloat(document.getElementById('cashInput').value)||0;
    if(cashVal<currentTotal){
      showToast('<i class="fa-solid fa-triangle-exclamation me-1"></i> Cash received is less than total!','var(--red)',3000);
      document.getElementById('cashInput').focus();
      return;
    }
  }

  const sub   =cart.reduce((s,c)=>s+c.price*c.qty,0);
  const tax   =sub*.01;
  const total =parseFloat(Math.max(sub+tax-discount,0).toFixed(2));
  const payMethod=document.querySelector('.pay-btn.active')?.dataset.method||'Cash';

  const payload={
    table_no:  selTable,
    status:    'pending',
    total_amt: total,
    items: cart.map(c=>({ menu_id:c.id, qty:c.qty, unit_price:c.price }))
  };

  const btn=document.getElementById('btnPlace');
  btn.disabled=true;
  btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

  fetch('../Backend/pos_process.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify(payload)
  })
  .then(r=>r.json())
  .then(res=>{
    if(res.success){
      sessionRevenue+=total;
      sessionOrders++;
      document.getElementById('statRevenue').textContent='₱'+sessionRevenue.toLocaleString('en',{minimumFractionDigits:2});
      document.getElementById('statOrders').textContent=sessionOrders;

      const cashTendered=isCashPayment()?(parseFloat(document.getElementById('cashInput').value)||0):0;
      const changeDue   =isCashPayment()?(cashTendered-total):0;

      showReceipt({
        orderId:res.order_id,
        table:selTable,
        items:[...cart],
        subtotal:sub,
        tax,
        discount,
        total,
        payMethod,
        cashTendered,
        changeDue,
      });

      // Reset cart state
      setTimeout(()=>{
        cart=[]; discount=0;
        document.getElementById('couponInput').value='';
        document.getElementById('cashInput').value='';
        currentTotal=0;
        updateCartUI();
        document.querySelectorAll('.product-card').forEach(c=>c.classList.remove('in-cart'));
        document.querySelectorAll('.card-add-btn').forEach(b=>{
          b.innerHTML='<i class="fa-solid fa-plus"></i> Add to Order';
          b.style.opacity=0; b.style.transform='translateY(4px)';
        });
        btn.disabled=true;
        btn.innerHTML='<i class="fa-solid fa-check"></i> Place Order';
        updateChange();
      },300);
    } else {
      showToast(`<i class="fa-solid fa-triangle-exclamation me-1"></i> ${res.message}`,'var(--red)',3500);
      btn.disabled=false;
      btn.innerHTML='<i class="fa-solid fa-check"></i> Place Order';
    }
  })
  .catch(()=>{
    showToast('<i class="fa-solid fa-triangle-exclamation me-1"></i> Network error. Try again.','var(--red)',3000);
    btn.disabled=false;
    btn.innerHTML='<i class="fa-solid fa-check"></i> Place Order';
  });
}

// ── Receipt Modal ─────────────────────────────────────────────
function showReceipt(data) {
  const now=new Date();
  const dateStr=now.toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});
  const timeStr=now.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
  const activeTab=document.querySelector('.o-tab.active');
  const orderType=activeTab?activeTab.textContent.trim():'Dine In';

  const itemsHTML=data.items.map(item=>`
    <div class="receipt-item-row">
      <span style="font-size:17px;flex-shrink:0">${item.emoji}</span>
      <span class="ri-name">${item.name}</span>
      <span class="ri-qty">×${item.qty}</span>
      <span class="ri-subtotal">₱${(item.price*item.qty).toLocaleString('en',{minimumFractionDigits:2})}</span>
    </div>`).join('');

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
          <div>Table <strong>#${data.table}</strong></div>
          <div>Type <strong>${orderType}</strong></div>
          <div style="text-align:right"><strong>${dateStr}</strong><br>${timeStr}</div>
        </div>
        <div class="receipt-items">${itemsHTML}</div>
        <div class="receipt-totals">
          <div class="rt-row"><span class="rt-lbl">Subtotal</span><span class="rt-val">₱${data.subtotal.toLocaleString('en',{minimumFractionDigits:2})}</span></div>
          ${discHTML}
          <div class="rt-row"><span class="rt-lbl">Tax (1% VAT)</span><span class="rt-val">₱${data.tax.toFixed(2)}</span></div>
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
}

function printReceipt(){
  const modal=document.querySelector('.receipt-modal');
  if(!modal) return;
  const w=window.open('','_blank','width=420,height=720');
  w.document.write(`<!DOCTYPE html><html><head><title>Receipt</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Outfit',sans-serif;background:#fff;color:#111;padding:20px;max-width:360px;margin:auto;}
    .receipt-store-name{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;text-align:center;}
    .receipt-store-name span{color:#e91e8c;}
    .receipt-subtitle{font-size:12px;color:#888;text-align:center;margin-bottom:4px;}
    .receipt-confirmed-badge{display:table;margin:6px auto 12px;background:#dcfce7;border:1px solid #86efac;color:#16a34a;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;}
    .receipt-close-x{display:none;}
    .receipt-meta{display:grid;grid-template-columns:1fr 1fr;gap:5px;font-size:11px;color:#666;border-top:1px dashed #ddd;border-bottom:1px dashed #ddd;padding:8px 0;margin-bottom:10px;}
    .receipt-meta strong{color:#111;}
    .receipt-header{text-align:center;padding-bottom:10px;}
    .receipt-items{padding:6px 0 10px;}
    .receipt-item-row{display:flex;align-items:center;gap:7px;padding:5px 0;border-bottom:1px dashed #eee;}
    .ri-name{flex:1;font-size:12px;font-weight:600;}
    .ri-qty{font-size:11px;color:#888;background:#f3f4f6;padding:1px 5px;border-radius:4px;}
    .ri-subtotal{font-size:12px;font-weight:700;color:#e91e8c;}
    .receipt-totals{padding:8px 0;border-top:1px dashed #ddd;}
    .rt-row{display:flex;justify-content:space-between;font-size:12px;padding:3px 0;}
    .rt-lbl{color:#888;} .rt-val{font-weight:500;}
    .rt-disc{color:#16a34a!important;}
    .rt-div{border:none;border-top:1px dashed #ddd;margin:5px 0;}
    .rt-total-row{display:flex;justify-content:space-between;font-size:15px;font-weight:800;padding:5px 0;}
    .rt-total-row .rt-val{color:#e91e8c;}
    .receipt-cash{padding:8px 0;border-top:1px dashed #ddd;}
    .rc-row{display:flex;justify-content:space-between;font-size:12px;padding:3px 0;} .rc-lbl{color:#888;} .rc-val{font-weight:600;}
    .rc-change-box{display:flex;justify-content:space-between;font-size:14px;font-weight:800;background:#dcfce7;border:1px solid #86efac;border-radius:8px;padding:8px 10px;margin-top:5px;}
    .rc-change-box .rc-lbl,.rc-change-box .rc-val{color:#16a34a;}
    .receipt-footer-btns{display:none;}
    .footer-note{text-align:center;font-size:11px;color:#aaa;margin-top:14px;padding-top:10px;border-top:1px dashed #ddd;}
  </style></head><body>`);
  w.document.write(modal.innerHTML);
  w.document.write(`<p class="footer-note">Thank you for dining with us! 💕<br>Please come again.</p></body></html>`);
  w.document.close();
  w.focus();
  setTimeout(()=>{ w.print(); },600);
}

// ── Theme ─────────────────────────────────────────────────────
function initTheme(){
  const saved=localStorage.getItem('pos-theme')||'dark';
  applyTheme(saved);
  document.getElementById('themeBtn').addEventListener('click',()=>{
    applyTheme(document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark');
  });
}
function applyTheme(t){
  document.documentElement.setAttribute('data-theme',t);
  localStorage.setItem('pos-theme',t);
  document.getElementById('themeIco').className=t==='dark'?'fa-solid fa-sun':'fa-solid fa-moon';
}

// ── Toast ─────────────────────────────────────────────────────
let toastTimer;
function showToast(msg,color='var(--accent)',dur=2400){
  const el=document.getElementById('toast');
  el.innerHTML=msg; el.style.background=color;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer=setTimeout(()=>el.classList.remove('show'),dur);
}
</script>
</body>
</html>