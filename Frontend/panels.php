<?php
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

$cat_emoji = ['Main Course'=>'🍽️','Appetizer'=>'🥗','Dessert'=>'🍰','Beverage'=>'☕'];

$products_json = json_encode(array_map(function($item) use ($cat_emoji) {
    return [
        'id'    => (int)$item['id'],
        'cat'   => $item['category'],
        'emoji' => $cat_emoji[$item['category']] ?? '🍽️',
        'name'  => $item['name'],
        'price' => (float)$item['price'],
        'desc'  => $item['description'] ?? '',
    ];
}, $menu_items));

$cats_json = json_encode($cats_raw);
$user_name = htmlspecialchars(($_SESSION['user']['firstname'] ?? 'User') . ' ' . ($_SESSION['user']['lastname'] ?? ''));
$user_initial = strtoupper(substr($_SESSION['user']['firstname'] ?? 'U', 0, 1) . substr($_SESSION['user']['lastname'] ?? '', 0, 1));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Empress · Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;0,800;1,700&display=swap" rel="stylesheet"/>
<style>
/* ═══════════════════════════════════════
   TOKENS
═══════════════════════════════════════ */
:root {
  --pk:#f0287a; --pk2:#ff6ec7;
  --pk-grad:linear-gradient(135deg,#f0287a 0%,#ff6ec7 100%);
  --pk-glow:rgba(240,40,122,.28); --pk-soft:rgba(240,40,122,.11);
  --green:#22c55e; --green-s:rgba(34,197,94,.13);
  --red:#ef4444;   --red-s:rgba(239,68,68,.12);
  --amber:#f59e0b; --blue:#3b82f6;
  --bg:#070709; --s1:#0f0f14; --s2:#16161e; --s3:#1e1e28;
  --b1:rgba(255,255,255,.055); --b2:rgba(255,255,255,.10);
  --tx:#eeeef5; --mu:#55556a; --mu2:#8080a0;
  --r:16px; --rsm:10px;
  --ease:.22s cubic-bezier(.4,0,.2,1);
  --nav-h:64px;
}
[data-theme="light"] {
  --bg:#f2f2f7; --s1:#ffffff; --s2:#fdf4f9; --s3:#fce7f3;
  --b1:rgba(233,30,140,.07); --b2:rgba(233,30,140,.16);
  --tx:#111118; --mu:#b090a8; --mu2:#7a4a6a;
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;background:var(--bg);color:var(--tx);font-family:'Outfit',sans-serif;overflow:hidden;}
button,input,select,textarea{font-family:inherit;}
button{border:none;background:none;cursor:pointer;color:inherit;outline:none;}
a{text-decoration:none;color:inherit;}
::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:var(--s3);border-radius:4px;}
::-webkit-scrollbar-thumb:hover{background:var(--pk);}

/* ── AMBIENT ── */
body::before{content:'';position:fixed;top:-15%;right:-10%;width:700px;height:700px;background:radial-gradient(ellipse,rgba(240,40,122,.07) 0%,transparent 65%);pointer-events:none;z-index:0;}
body::after{content:'';position:fixed;bottom:-10%;left:15%;width:500px;height:500px;background:radial-gradient(ellipse,rgba(240,40,122,.04) 0%,transparent 65%);pointer-events:none;z-index:0;}

/* ═══════════════════════════════════════
   LAYOUT SHELL
═══════════════════════════════════════ */
.shell{position:relative;z-index:1;height:100vh;display:flex;flex-direction:column;}

/* ── TOP NAV BAR ── */
.topnav{height:var(--nav-h);background:var(--s1);border-bottom:1px solid var(--b1);display:flex;align-items:center;padding:0 24px;gap:16px;flex-shrink:0;backdrop-filter:blur(16px);}
.tn-logo{display:flex;align-items:center;gap:11px;}
.tn-logo-icon{width:38px;height:38px;background:var(--pk-grad);border-radius:12px;display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:#fff;box-shadow:0 0 20px var(--pk-glow);}
.tn-logo-text{font-family:'Playfair Display',serif;font-size:18px;font-weight:700;}
.tn-logo-text span{background:var(--pk-grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.tn-divider{width:1px;height:28px;background:var(--b2);margin:0 4px;}
.tn-tabs{display:flex;gap:2px;flex:1;}
.tn-tab{display:flex;align-items:center;gap:8px;padding:8px 18px;border-radius:10px;font-size:13.5px;font-weight:600;color:var(--mu2);transition:all var(--ease);position:relative;white-space:nowrap;}
.tn-tab i{font-size:14px;}
.tn-tab:hover{background:var(--s2);color:var(--tx);}
.tn-tab.active{background:var(--pk-soft);color:var(--pk);}
.tn-tab.active::after{content:'';position:absolute;bottom:-1px;left:50%;transform:translateX(-50%);width:60%;height:2px;background:var(--pk-grad);border-radius:2px 2px 0 0;}
.tn-tab .tab-badge{background:var(--pk);color:#fff;font-size:9px;font-weight:800;padding:1px 6px;border-radius:20px;min-width:18px;text-align:center;}
.tn-right{display:flex;align-items:center;gap:10px;margin-left:auto;}
.tn-back{display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:10px;background:var(--s2);border:1px solid var(--b2);font-size:12.5px;font-weight:600;color:var(--mu2);transition:all var(--ease);}
.tn-back:hover{border-color:var(--pk);color:var(--pk);background:var(--pk-soft);}
.tn-theme{width:36px;height:36px;border-radius:10px;background:var(--s2);border:1px solid var(--b2);color:var(--mu2);font-size:14px;display:flex;align-items:center;justify-content:center;transition:all var(--ease);}
.tn-theme:hover{background:var(--pk-soft);color:var(--pk);}
.tn-avatar{width:36px;height:36px;border-radius:50%;background:var(--pk-grad);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;color:#fff;box-shadow:0 0 14px var(--pk-glow);cursor:default;}

/* ── PAGE BODY ── */
.page-body{flex:1;overflow:hidden;position:relative;}
.tab-page{position:absolute;inset:0;overflow-y:auto;padding:28px 28px 40px;display:none;opacity:0;transform:translateY(10px);transition:opacity .28s ease,transform .28s ease;}
.tab-page.active{display:block;opacity:1;transform:translateY(0);}

/* ═══════════════════════════════════════
   SHARED COMPONENTS
═══════════════════════════════════════ */
.section-hd{display:flex;align-items:center;gap:10px;margin-bottom:14px;}
.section-hd-line{flex:1;height:1px;background:var(--b2);}
.section-label{font-size:10.5px;font-weight:800;color:var(--mu);text-transform:uppercase;letter-spacing:.14em;white-space:nowrap;}

.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:24px;}
.kpi-card{background:var(--s1);border:1px solid var(--b1);border-radius:var(--r);padding:18px 20px;display:flex;align-items:center;gap:14px;transition:all var(--ease);position:relative;overflow:hidden;}
.kpi-card::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.025) 0%,transparent 70%);pointer-events:none;}
.kpi-card:hover{border-color:var(--b2);transform:translateY(-2px);box-shadow:0 12px 32px rgba(0,0,0,.35);}
.kpi-icon{width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.kpi-val{font-size:22px;font-weight:800;line-height:1;letter-spacing:-.03em;}
.kpi-lbl{font-size:11px;color:var(--mu);margin-top:3px;font-weight:500;}

/* ═══════════════════════════════════════
   STATS PAGE
═══════════════════════════════════════ */
.stats-hero{background:var(--pk-grad);border-radius:var(--r);padding:28px 32px;display:flex;align-items:center;justify-content:space-between;position:relative;overflow:hidden;margin-bottom:20px;}
.stats-hero::before{content:'';position:absolute;top:-60px;right:-60px;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.08);}
.stats-hero::after{content:'';position:absolute;bottom:-40px;left:80px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.05);}
.sh-left{position:relative;z-index:1;}
.sh-label{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.15em;color:rgba(255,255,255,.6);margin-bottom:8px;}
.sh-val{font-size:52px;font-weight:900;color:#fff;line-height:1;letter-spacing:-.04em;font-family:'Playfair Display',serif;}
.sh-sub{font-size:13px;color:rgba(255,255,255,.65);margin-top:8px;font-weight:500;}
.sh-right{position:relative;z-index:1;display:flex;flex-direction:column;align-items:flex-end;gap:12px;}
.sh-badge{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.18);backdrop-filter:blur(8px);border-radius:12px;padding:10px 16px;font-size:13px;font-weight:700;color:#fff;}
.sh-badge i{font-size:16px;}

.stats-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;}
.stat-block{background:var(--s1);border:1px solid var(--b1);border-radius:var(--r);padding:20px;}
.stat-block-title{font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:var(--mu);margin-bottom:14px;}
.bar-row{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--s2);border:1px solid var(--b1);border-radius:11px;margin-bottom:8px;transition:all var(--ease);}
.bar-row:last-child{margin-bottom:0;}
.bar-row:hover{border-color:rgba(240,40,122,.25);}
.bar-cat-icon{width:34px;height:34px;background:var(--s3);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.bar-info{flex:1;min-width:0;}
.bar-name{font-size:12.5px;font-weight:700;}
.bar-track{height:5px;background:var(--s3);border-radius:20px;overflow:hidden;margin-top:5px;}
.bar-fill{height:100%;border-radius:20px;background:var(--pk-grad);width:0;transition:width 1s cubic-bezier(.4,0,.2,1);}
.bar-amt{font-size:13px;font-weight:800;background:var(--pk-grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;white-space:nowrap;}

.top-item{display:flex;align-items:center;gap:12px;padding:11px 14px;background:var(--s2);border:1px solid var(--b1);border-radius:12px;margin-bottom:8px;transition:all var(--ease);}
.top-item:last-child{margin-bottom:0;}
.top-item:hover{border-color:rgba(240,40,122,.25);transform:translateX(3px);}
.rank-badge{width:26px;height:26px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:900;flex-shrink:0;}
.rank-1{background:rgba(251,191,36,.15);color:#fbbf24;}
.rank-2{background:rgba(156,163,175,.15);color:#9ca3af;}
.rank-3{background:rgba(180,127,50,.15);color:#b45309;}
.rank-n{background:var(--s3);color:var(--mu2);}
.top-emoji{font-size:22px;flex-shrink:0;}
.top-info{flex:1;min-width:0;}
.top-name{font-size:13px;font-weight:700;}
.top-rev{font-size:11px;color:var(--mu2);margin-top:1px;}
.top-qty{font-size:15px;font-weight:900;background:var(--pk-grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}

/* ═══════════════════════════════════════
   MENU PAGE
═══════════════════════════════════════ */
.menu-toolbar{display:flex;align-items:center;gap:12px;margin-bottom:20px;}
.menu-search-wrap{position:relative;flex:1;max-width:420px;}
.menu-search-wrap i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--mu);font-size:13px;pointer-events:none;}
.menu-search{width:100%;padding:11px 14px 11px 38px;background:var(--s2);border:1px solid var(--b2);border-radius:12px;color:var(--tx);font-size:13.5px;transition:all var(--ease);}
.menu-search::placeholder{color:var(--mu);}
.menu-search:focus{outline:none;border-color:var(--pk);box-shadow:0 0 0 3px var(--pk-glow);}
.menu-filter-scroll{display:flex;gap:7px;overflow-x:auto;flex:1;}
.menu-filter-scroll::-webkit-scrollbar{display:none;}
.mf-pill{padding:8px 18px;border-radius:50px;border:1px solid var(--b2);background:var(--s1);font-size:13px;font-weight:600;color:var(--mu2);white-space:nowrap;flex-shrink:0;transition:all var(--ease);}
.mf-pill:hover{border-color:var(--pk);color:var(--pk);}
.mf-pill.active{background:var(--pk-grad);border-color:transparent;color:#fff;box-shadow:0 4px 16px var(--pk-glow);}
.menu-count{font-size:12px;color:var(--mu);white-space:nowrap;}

.menu-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;}
.menu-card{background:var(--s1);border:1px solid var(--b1);border-radius:var(--r);padding:16px 18px;display:flex;align-items:center;gap:14px;transition:all var(--ease);position:relative;overflow:hidden;animation:fadeUp .3s ease both;}
.menu-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--pk-grad);transform:scaleX(0);transition:transform var(--ease);transform-origin:left;}
.menu-card:hover{border-color:rgba(240,40,122,.35);transform:translateY(-2px);box-shadow:0 12px 32px rgba(0,0,0,.35);}
.menu-card:hover::before{transform:scaleX(1);}
.mc-emoji-wrap{width:60px;height:60px;border-radius:14px;background:var(--s2);display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0;border:1px solid var(--b1);transition:transform var(--ease);}
.menu-card:hover .mc-emoji-wrap{transform:scale(1.08) rotate(-3deg);}
.mc-info{flex:1;min-width:0;}
.mc-name{font-size:15px;font-weight:700;}
.mc-cat{font-size:10.5px;color:var(--mu2);background:var(--s3);padding:2px 8px;border-radius:20px;display:inline-block;margin:4px 0;font-weight:600;}
.mc-desc{font-size:11.5px;color:var(--mu);line-height:1.4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.mc-right{display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0;}
.mc-price{font-size:17px;font-weight:900;background:var(--pk-grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.mc-add{width:36px;height:36px;border-radius:10px;background:var(--pk-grad);color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;transition:all var(--ease);box-shadow:0 2px 10px var(--pk-glow);}
.mc-add:hover{transform:scale(1.12) rotate(90deg);box-shadow:0 4px 16px var(--pk-glow);}
.mc-in-cart{font-size:10px;font-weight:800;color:var(--pk);background:var(--pk-soft);border:1px solid rgba(240,40,122,.25);padding:2px 8px;border-radius:20px;}

/* ═══════════════════════════════════════
   HISTORY PAGE
═══════════════════════════════════════ */
.hist-hero-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:24px;}
.hist-hero-card{background:var(--s1);border:1px solid var(--b1);border-radius:var(--r);padding:20px 22px;position:relative;overflow:hidden;}
.hist-hero-card::after{content:'';position:absolute;top:-20px;right:-20px;width:100px;height:100px;border-radius:50%;background:var(--pk-soft);pointer-events:none;}
.hhc-val{font-size:30px;font-weight:900;letter-spacing:-.03em;position:relative;z-index:1;}
.hhc-val.accent{background:var(--pk-grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.hhc-lbl{font-size:10.5px;color:var(--mu);text-transform:uppercase;letter-spacing:.12em;font-weight:700;margin-top:4px;position:relative;z-index:1;}

.hist-content{display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;}
.hist-timeline-wrap{display:flex;flex-direction:column;gap:0;}
.ht-entry{display:flex;gap:0;animation:slideR .28s ease both;}
.ht-line{display:flex;flex-direction:column;align-items:center;width:40px;flex-shrink:0;}
.ht-dot{width:13px;height:13px;border-radius:50%;background:var(--pk-grad);border:2px solid var(--bg);box-shadow:0 0 0 3px var(--pk-soft);flex-shrink:0;margin-top:16px;}
.ht-connector{width:2px;flex:1;background:var(--b2);margin:4px 0;}
.ht-entry:last-child .ht-connector{display:none;}
.ht-card{flex:1;background:var(--s1);border:1px solid var(--b1);border-radius:14px;padding:14px 18px;margin-bottom:12px;transition:all var(--ease);}
.ht-card:hover{border-color:rgba(240,40,122,.3);box-shadow:0 6px 24px rgba(0,0,0,.3);}
.ht-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
.ht-order-id{font-weight:800;font-size:14px;}
.ht-order-id span{background:var(--pk-grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.ht-total{font-size:16px;font-weight:900;background:var(--pk-grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.ht-pills{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;}
.ht-pill{display:flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:10.5px;font-weight:600;background:var(--s3);border:1px solid var(--b1);color:var(--mu2);}
.ht-pill i{font-size:9px;}
.ht-items{display:flex;flex-wrap:wrap;gap:5px;}
.ht-itag{background:var(--s3);border:1px solid var(--b1);border-radius:8px;padding:3px 10px;font-size:11px;color:var(--mu2);display:flex;align-items:center;gap:5px;}

.hist-sidebar{display:flex;flex-direction:column;gap:14px;position:sticky;top:0;}
.hist-empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:60px 20px;color:var(--mu);text-align:center;}
.hist-empty-state i{font-size:52px;color:var(--s3);}
.hist-empty-state strong{font-size:15px;color:var(--mu2);}

/* Pay method breakdown in sidebar */
.pay-breakdown{background:var(--s1);border:1px solid var(--b1);border-radius:var(--r);padding:18px;}
.pay-method-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px dashed var(--b2);font-size:13px;}
.pay-method-row:last-child{border-bottom:none;}
.pay-method-name{display:flex;align-items:center;gap:8px;font-weight:600;}
.pay-method-name i{width:28px;height:28px;background:var(--pk-soft);color:var(--pk);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;}
.pay-method-val{font-weight:800;font-size:13px;}

/* ═══════════════════════════════════════
   REPORTS PAGE
═══════════════════════════════════════ */
.rpt-hero{background:var(--s1);border:1px solid var(--b1);border-radius:var(--r);padding:28px 32px;display:grid;grid-template-columns:1fr auto;gap:20px;align-items:center;margin-bottom:20px;position:relative;overflow:hidden;}
.rpt-hero::before{content:'';position:absolute;top:0;right:0;width:300px;height:100%;background:linear-gradient(90deg,transparent,var(--pk-soft));pointer-events:none;}
.rpt-hero-label{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.15em;color:var(--mu);margin-bottom:8px;}
.rpt-hero-val{font-size:48px;font-weight:900;letter-spacing:-.04em;font-family:'Playfair Display',serif;background:var(--pk-grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1;}
.rpt-hero-sub{font-size:13px;color:var(--mu2);margin-top:8px;}
.rpt-hero-date{text-align:right;font-size:12px;color:var(--mu);position:relative;z-index:1;}
.rpt-hero-date strong{display:block;font-size:16px;font-weight:800;color:var(--tx);margin-top:4px;}

.rpt-main{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px;}
.rpt-block{background:var(--s1);border:1px solid var(--b1);border-radius:var(--r);padding:22px;}
.rpt-block-hd{font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:var(--mu);margin-bottom:16px;display:flex;align-items:center;gap:8px;}
.rpt-block-hd::after{content:'';flex:1;height:1px;background:var(--b2);}

.rpt-cat-item{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px dashed var(--b1);}
.rpt-cat-item:last-child{border-bottom:none;}
.rpt-cat-icon{width:36px;height:36px;background:var(--s3);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.rpt-cat-info{flex:1;min-width:0;}
.rpt-cat-name{font-size:13px;font-weight:700;}
.rpt-cat-track{height:6px;background:var(--s3);border-radius:20px;overflow:hidden;margin-top:5px;}
.rpt-cat-fill{height:100%;border-radius:20px;background:var(--pk-grad);width:0;transition:width 1.1s cubic-bezier(.4,0,.2,1);}
.rpt-cat-amt{font-size:14px;font-weight:900;background:var(--pk-grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;white-space:nowrap;}

.rpt-top-item{display:flex;align-items:center;gap:12px;padding:11px 14px;background:var(--s2);border:1px solid var(--b1);border-radius:12px;margin-bottom:8px;transition:all var(--ease);}
.rpt-top-item:last-child{margin-bottom:0;}
.rpt-top-item:hover{border-color:rgba(240,40,122,.25);}

.rpt-summary-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;}
.rpt-sum-card{background:var(--s1);border:1px solid var(--b1);border-radius:var(--r);padding:18px 20px;}
.rpt-sum-val{font-size:26px;font-weight:900;letter-spacing:-.03em;}
.rpt-sum-lbl{font-size:10.5px;color:var(--mu);text-transform:uppercase;letter-spacing:.1em;font-weight:700;margin-top:4px;}

.rpt-admin-btn{display:flex;align-items:center;justify-content:center;gap:10px;background:var(--s1);border:1px solid var(--b2);border-radius:var(--r);padding:16px;font-weight:700;font-size:14px;transition:all var(--ease);}
.rpt-admin-btn:hover{background:var(--pk-soft);border-color:rgba(240,40,122,.4);color:var(--pk);transform:translateY(-1px);}

/* ═══════════════════════════════════════
   EMPTY STATES
═══════════════════════════════════════ */
.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:60px 20px;color:var(--mu);text-align:center;}
.empty-state i{font-size:50px;color:var(--s3);}
.empty-state strong{font-size:15px;color:var(--mu2);}
.empty-state small{font-size:12px;}

/* ═══════════════════════════════════════
   ANIMATIONS
═══════════════════════════════════════ */
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@keyframes slideR{from{opacity:0;transform:translateX(-16px)}to{opacity:1;transform:translateX(0)}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes countUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

/* ── RESPONSIVE ── */
@media(max-width:1100px){
  .stats-row,.hist-content,.rpt-main{grid-template-columns:1fr;}
  .rpt-summary-row{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:768px){
  .tn-tab span{display:none;}
  .menu-grid{grid-template-columns:1fr;}
  .hist-hero-row,.rpt-summary-row{grid-template-columns:1fr 1fr;}
}
</style>
</head>
<body>

<div class="shell">

  <!-- ── TOP NAV ──────────────────────────────────────────── -->
  <nav class="topnav">
    <div class="tn-logo">
      <div class="tn-logo-icon">E</div>
      <div class="tn-logo-text">Empress <span>POS</span></div>
    </div>
    <div class="tn-divider"></div>

    <div class="tn-tabs">
      <button class="tn-tab active" data-page="stats">
        <i class="fa-solid fa-chart-pie"></i>
        <span>Stats</span>
      </button>
      <button class="tn-tab" data-page="menu">
        <i class="fa-solid fa-utensils"></i>
        <span>Menu</span>
        <span class="tab-badge" id="tab-menu-count"><?= count($menu_items) ?></span>
      </button>
      <button class="tn-tab" data-page="history">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <span>History</span>
        <span class="tab-badge" id="tab-hist-count" style="display:none">0</span>
      </button>
      <button class="tn-tab" data-page="reports">
        <i class="fa-solid fa-file-invoice-dollar"></i>
        <span>Reports</span>
      </button>
    </div>

    <div class="tn-right">
      <a href="POS.php" class="tn-back">
        <i class="fa-solid fa-arrow-left"></i> Back to POS
      </a>
      <button class="tn-theme" id="themeBtn" title="Toggle theme">
        <i class="fa-solid fa-sun" id="themeIco"></i>
      </button>
      <div class="tn-avatar" title="<?= $user_name ?>"><?= $user_initial ?></div>
    </div>
  </nav>

  <!-- ── PAGE BODY ─────────────────────────────────────────── -->
  <div class="page-body">

    <!-- ════════════════════════════════════
         STATS PAGE
    ═════════════════════════════════════ -->
    <div class="tab-page active" id="page-stats">

      <!-- Hero -->
      <div class="stats-hero">
        <div class="sh-left">
          <div class="sh-label">Session Revenue</div>
          <div class="sh-val" id="s-revenue">₱0.00</div>
          <div class="sh-sub" id="s-hero-sub">Place your first order to see data here</div>
        </div>
        <div class="sh-right">
          <div class="sh-badge"><i class="fa-solid fa-fire-flame-curved"></i> <span id="s-orders">0</span> Orders</div>
          <div class="sh-badge"><i class="fa-solid fa-bag-shopping"></i> <span id="s-items">0</span> Items Sold</div>
          <div class="sh-badge"><i class="fa-solid fa-chart-line"></i> Avg: <span id="s-avg">₱0</span></div>
        </div>
      </div>

      <!-- KPI row -->
      <div class="kpi-grid" style="margin-bottom:20px;">
        <div class="kpi-card">
          <div class="kpi-icon" style="background:var(--pk-soft)"><i class="fa-solid fa-peso-sign" style="color:var(--pk)"></i></div>
          <div><div class="kpi-val" id="sk-revenue" style="background:var(--pk-grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">₱0.00</div><div class="kpi-lbl">Total Revenue</div></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon" style="background:var(--green-s)"><i class="fa-solid fa-receipt" style="color:var(--green)"></i></div>
          <div><div class="kpi-val" id="sk-orders">0</div><div class="kpi-lbl">Orders Placed</div></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon" style="background:rgba(59,130,246,.12)"><i class="fa-solid fa-box" style="color:var(--blue)"></i></div>
          <div><div class="kpi-val" id="sk-items">0</div><div class="kpi-lbl">Items Sold</div></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon" style="background:rgba(245,158,11,.12)"><i class="fa-solid fa-chart-bar" style="color:var(--amber)"></i></div>
          <div><div class="kpi-val" id="sk-avg">₱0</div><div class="kpi-lbl">Avg Order Value</div></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon" style="background:var(--pk-soft)"><i class="fa-solid fa-table-cells" style="color:var(--pk)"></i></div>
          <div><div class="kpi-val" id="sk-tables">0</div><div class="kpi-lbl">Tables Served</div></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon" style="background:var(--green-s)"><i class="fa-solid fa-utensils" style="color:var(--green)"></i></div>
          <div><div class="kpi-val"><?= count($menu_items) ?></div><div class="kpi-lbl">Menu Items</div></div>
        </div>
      </div>

      <!-- Charts row -->
      <div class="stats-row">
        <div class="stat-block">
          <div class="stat-block-title">Sales by Category</div>
          <div id="s-cat-bars">
            <div class="empty-state" style="padding:30px 0;">
              <i class="fa-solid fa-chart-bar" style="font-size:32px;"></i>
              <small>No orders yet</small>
            </div>
          </div>
        </div>
        <div class="stat-block">
          <div class="stat-block-title">Top Ordered Items</div>
          <div id="s-top-items">
            <div class="empty-state" style="padding:30px 0;">
              <i class="fa-solid fa-star" style="font-size:32px;"></i>
              <small>No items ordered yet</small>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /page-stats -->

    <!-- ════════════════════════════════════
         MENU PAGE
    ═════════════════════════════════════ -->
    <div class="tab-page" id="page-menu">

      <div class="menu-toolbar">
        <div class="menu-search-wrap">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" class="menu-search" id="menuSearch" placeholder="Search menu items…" autocomplete="off">
        </div>
        <div class="menu-filter-scroll" id="menuFilters"></div>
        <span class="menu-count" id="menuCount"><?= count($menu_items) ?> items</span>
      </div>

      <div class="menu-grid" id="menuGrid"></div>

    </div><!-- /page-menu -->

    <!-- ════════════════════════════════════
         HISTORY PAGE
    ═════════════════════════════════════ -->
    <div class="tab-page" id="page-history">

      <!-- Hero stats -->
      <div class="hist-hero-row">
        <div class="hist-hero-card">
          <div class="hhc-val accent" id="h-revenue">₱0.00</div>
          <div class="hhc-lbl">Session Revenue</div>
        </div>
        <div class="hist-hero-card">
          <div class="hhc-val" id="h-orders">0</div>
          <div class="hhc-lbl">Orders Placed</div>
        </div>
        <div class="hist-hero-card">
          <div class="hhc-val" id="h-items">0</div>
          <div class="hhc-lbl">Items Sold</div>
        </div>
      </div>

      <!-- Main content -->
      <div class="hist-content" id="histContent">

        <!-- Timeline -->
        <div>
          <div class="section-hd">
            <div class="section-label">Order Timeline</div>
            <div class="section-hd-line"></div>
          </div>
          <div class="hist-timeline-wrap" id="histTimeline"></div>
          <div class="hist-empty-state" id="histEmpty">
            <i class="fa-solid fa-receipt"></i>
            <strong>No orders yet</strong>
            <small>Orders placed in POS will appear here</small>
          </div>
        </div>

        <!-- Sidebar breakdown -->
        <div class="hist-sidebar" id="histSidebar" style="display:none;">
          <div class="section-hd">
            <div class="section-label">Payment Methods</div>
            <div class="section-hd-line"></div>
          </div>
          <div class="pay-breakdown" id="payBreakdown"></div>
        </div>

      </div>

    </div><!-- /page-history -->

    <!-- ════════════════════════════════════
         REPORTS PAGE
    ═════════════════════════════════════ -->
    <div class="tab-page" id="page-reports">

      <!-- Hero -->
      <div class="rpt-hero">
        <div>
          <div class="rpt-hero-label">Session Revenue</div>
          <div class="rpt-hero-val" id="r-revenue">₱0.00</div>
          <div class="rpt-hero-sub" id="r-sub">No orders placed this session yet</div>
        </div>
        <div class="rpt-hero-date">
          Today's Date
          <strong id="r-date">—</strong>
        </div>
      </div>

      <!-- Summary KPIs -->
      <div class="rpt-summary-row">
        <div class="rpt-sum-card">
          <div class="rpt-sum-val" id="r-orders">0</div>
          <div class="rpt-sum-lbl">Total Orders</div>
        </div>
        <div class="rpt-sum-card">
          <div class="rpt-sum-val" id="r-items">0</div>
          <div class="rpt-sum-lbl">Items Sold</div>
        </div>
        <div class="rpt-sum-card">
          <div class="rpt-sum-val" id="r-avg">₱0</div>
          <div class="rpt-sum-lbl">Avg Order</div>
        </div>
        <div class="rpt-sum-card">
          <div class="rpt-sum-val" id="r-tables">0</div>
          <div class="rpt-sum-lbl">Tables Served</div>
        </div>
      </div>

      <!-- Main grid -->
      <div class="rpt-main">
        <div class="rpt-block">
          <div class="rpt-block-hd">Revenue by Category</div>
          <div id="r-cat-breakdown">
            <div class="empty-state" style="padding:30px 0;">
              <i class="fa-solid fa-chart-pie" style="font-size:32px;"></i>
              <small>No data yet</small>
            </div>
          </div>
        </div>
        <div class="rpt-block">
          <div class="rpt-block-hd">Top Selling Items</div>
          <div id="r-top-items">
            <div class="empty-state" style="padding:30px 0;">
              <i class="fa-solid fa-trophy" style="font-size:32px;"></i>
              <small>No items ordered yet</small>
            </div>
          </div>
        </div>
      </div>

      <a href="ADMIN/report.php" class="rpt-admin-btn">
        <i class="fa-solid fa-arrow-up-right-from-square" style="color:var(--pk)"></i>
        Open Full Admin Reports
        <i class="fa-solid fa-chevron-right" style="font-size:11px;margin-left:auto;color:var(--mu)"></i>
      </a>

    </div><!-- /page-reports -->

  </div><!-- /page-body -->
</div><!-- /shell -->

<script>
// ── Data ─────────────────────────────────────────────────────
const products = <?= $products_json ?>;
const DB_CATS  = <?= $cats_json ?>;
const CAT_ICON = {'Main Course':'🍽️','Appetizer':'🥗','Dessert':'🍰','Beverage':'☕'};

// ── Shared session state (populated from sessionStorage / POS bridge) ──
// These will be populated by reading from sessionStorage written by POS.php
let sessionHistory = [];
let sessionRevenue = 0;
let sessionOrders  = 0;
const occupiedTables = new Set();

// ── Helpers ─────────────────────────────────────────────────
const fmt = n => '₱' + Number(n).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
const fmtShort = n => n >= 1000 ? '₱' + (n/1000).toFixed(1) + 'k' : fmt(n);

// ── Tab Navigation ───────────────────────────────────────────
document.querySelectorAll('.tn-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    const page = tab.dataset.page;
    document.querySelectorAll('.tn-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');

    document.querySelectorAll('.tab-page').forEach(p => {
      p.classList.remove('active');
      p.style.display = 'none';
    });
    const pg = document.getElementById('page-' + page);
    pg.style.display = 'block';
    // Force reflow for animation
    pg.offsetHeight;
    pg.classList.add('active');

    // Refresh on open
    if (page === 'stats')   refreshStats();
    if (page === 'menu')    renderMenuGrid();
    if (page === 'history') refreshHistory();
    if (page === 'reports') refreshReports();
  });
});

// ── Theme ────────────────────────────────────────────────────
function initTheme() {
  const saved = localStorage.getItem('pos-theme') || 'dark';
  applyTheme(saved);
  document.getElementById('themeBtn').addEventListener('click', () => {
    applyTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
  });
}
function applyTheme(t) {
  document.documentElement.setAttribute('data-theme', t);
  localStorage.setItem('pos-theme', t);
  document.getElementById('themeIco').className = t === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
}

// ── Load session data from sessionStorage (written by POS.php) ──
function loadSessionData() {
  try {
    const raw = sessionStorage.getItem('empress_session');
    if (raw) {
      const data = JSON.parse(raw);
      sessionHistory  = data.history  || [];
      sessionRevenue  = data.revenue  || 0;
      sessionOrders   = data.orders   || 0;
      (data.occupied || []).forEach(t => occupiedTables.add(t));
    }
  } catch(e) { /* no session data yet */ }
}

// ── STATS ────────────────────────────────────────────────────
function refreshStats() {
  const items = sessionHistory.reduce((s,o) => s + o.items.reduce((ss,i) => ss+i.qty, 0), 0);
  const avg   = sessionOrders > 0 ? sessionRevenue / sessionOrders : 0;

  // Hero
  document.getElementById('s-revenue').textContent = fmt(sessionRevenue);
  document.getElementById('s-orders').textContent  = sessionOrders;
  document.getElementById('s-items').textContent   = items;
  document.getElementById('s-avg').textContent     = fmtShort(avg);
  document.getElementById('s-hero-sub').textContent =
    sessionOrders > 0
      ? sessionOrders + ' order' + (sessionOrders!==1?'s':'') + ' · ' + items + ' items sold this session'
      : 'Place your first order to see data here';

  // KPIs
  document.getElementById('sk-revenue').textContent = fmt(sessionRevenue);
  document.getElementById('sk-orders').textContent  = sessionOrders;
  document.getElementById('sk-items').textContent   = items;
  document.getElementById('sk-avg').textContent     = fmtShort(avg);
  document.getElementById('sk-tables').textContent  = occupiedTables.size;

  // Category bars
  const catTotals = {};
  sessionHistory.forEach(o => o.items.forEach(i => {
    catTotals[i.cat] = (catTotals[i.cat]||0) + i.price * i.qty;
  }));
  const maxCat  = Math.max(...Object.values(catTotals), 1);
  const barsEl  = document.getElementById('s-cat-bars');
  if (!Object.keys(catTotals).length) {
    barsEl.innerHTML = `<div class="empty-state" style="padding:30px 0;"><i class="fa-solid fa-chart-bar" style="font-size:32px;"></i><small>No orders yet</small></div>`;
  } else {
    barsEl.innerHTML = Object.entries(catTotals).sort((a,b)=>b[1]-a[1]).map(([cat, val]) => {
      const pct = (val/maxCat*100).toFixed(1);
      return `<div class="bar-row">
        <div class="bar-cat-icon">${CAT_ICON[cat]||'🍽️'}</div>
        <div class="bar-info">
          <div class="bar-name">${cat}</div>
          <div class="bar-track"><div class="bar-fill" style="width:${pct}%"></div></div>
        </div>
        <span class="bar-amt">${fmt(val)}</span>
      </div>`;
    }).join('');
  }

  // Top items
  const itemCounts = {};
  sessionHistory.forEach(o => o.items.forEach(i => {
    if(!itemCounts[i.name]) itemCounts[i.name]={qty:0,emoji:i.emoji,rev:0};
    itemCounts[i.name].qty += i.qty;
    itemCounts[i.name].rev += i.price * i.qty;
  }));
  const sorted  = Object.entries(itemCounts).sort((a,b)=>b[1].qty-a[1].qty).slice(0,5);
  const topEl   = document.getElementById('s-top-items');
  if (!sorted.length) {
    topEl.innerHTML = `<div class="empty-state" style="padding:30px 0;"><i class="fa-solid fa-star" style="font-size:32px;"></i><small>No items ordered yet</small></div>`;
  } else {
    const rankCls = ['rank-1','rank-2','rank-3','rank-n','rank-n'];
    topEl.innerHTML = sorted.map(([name,d],i) => `
      <div class="top-item" style="animation:fadeUp .22s ${i*.07}s ease both;">
        <div class="rank-badge ${rankCls[i]}">${i+1}</div>
        <span class="top-emoji">${d.emoji}</span>
        <div class="top-info">
          <div class="top-name">${name}</div>
          <div class="top-rev">${fmt(d.rev)} revenue</div>
        </div>
        <span class="top-qty">×${d.qty}</span>
      </div>`).join('');
  }
}

// ── MENU ─────────────────────────────────────────────────────
let menuActiveCat = 'all', menuQ = '';

function initMenu() {
  // Build filter pills
  const filters = document.getElementById('menuFilters');
  const mkPill = (label, cat) => {
    const b = document.createElement('button');
    b.className = 'mf-pill' + (menuActiveCat === cat ? ' active' : '');
    b.textContent = label;
    b.addEventListener('click', () => {
      menuActiveCat = cat;
      document.querySelectorAll('.mf-pill').forEach(p => p.classList.remove('active'));
      b.classList.add('active');
      renderMenuGrid();
    });
    filters.appendChild(b);
  };
  mkPill('All', 'all');
  DB_CATS.forEach(cat => mkPill((CAT_ICON[cat]||'') + ' ' + cat, cat));

  // Search
  document.getElementById('menuSearch').addEventListener('input', e => {
    menuQ = e.target.value.trim().toLowerCase();
    renderMenuGrid();
  });

  renderMenuGrid();
}

function renderMenuGrid() {
  const grid = document.getElementById('menuGrid');
  const filtered = products.filter(p => {
    const cOk = menuActiveCat === 'all' || p.cat === menuActiveCat;
    const qOk = !menuQ || p.name.toLowerCase().includes(menuQ) || (p.desc||'').toLowerCase().includes(menuQ);
    return cOk && qOk;
  });
  document.getElementById('menuCount').textContent = filtered.length + ' item' + (filtered.length !== 1 ? 's' : '');
  grid.innerHTML = '';
  if (!filtered.length) {
    grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;"><i class="fa-solid fa-bowl-rice"></i><strong>No items found</strong><small>Try a different search or category</small></div>`;
    return;
  }
  filtered.forEach((p, i) => {
    const el = document.createElement('div');
    el.className = 'menu-card';
    el.style.animationDelay = (i * .04) + 's';
    el.innerHTML = `
      <div class="mc-emoji-wrap">${p.emoji}</div>
      <div class="mc-info">
        <div class="mc-name">${p.name}</div>
        <span class="mc-cat">${p.cat}</span>
        ${p.desc ? `<div class="mc-desc">${p.desc}</div>` : ''}
      </div>
      <div class="mc-right">
        <div class="mc-price">₱${p.price.toLocaleString()}</div>
        <button class="mc-add" title="Add to order" onclick="window.location.href='POS.php'">
          <i class="fa-solid fa-plus"></i>
        </button>
      </div>`;
    grid.appendChild(el);
  });
}

// ── HISTORY ──────────────────────────────────────────────────
function refreshHistory() {
  const items = sessionHistory.reduce((s,o) => s + o.items.reduce((ss,i) => ss+i.qty, 0), 0);
  document.getElementById('h-revenue').textContent = fmt(sessionRevenue);
  document.getElementById('h-orders').textContent  = sessionOrders;
  document.getElementById('h-items').textContent   = items;

  // Badge on tab
  const badge = document.getElementById('tab-hist-count');
  if (sessionOrders > 0) { badge.textContent = sessionOrders; badge.style.display = ''; }
  else badge.style.display = 'none';

  const timeline = document.getElementById('histTimeline');
  const empty    = document.getElementById('histEmpty');
  const sidebar  = document.getElementById('histSidebar');

  timeline.innerHTML = '';
  if (!sessionHistory.length) {
    empty.style.display = 'flex';
    sidebar.style.display = 'none';
    return;
  }
  empty.style.display = 'none';
  sidebar.style.display = 'flex';

  sessionHistory.forEach((o, idx) => {
    const d = new Date(o.timestamp);
    const timeStr = d.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'});
    const tagsHTML = o.items.map(i => `<div class="ht-itag"><span>${i.emoji}</span>${i.name} ×${i.qty}</div>`).join('');
    const entry = document.createElement('div');
    entry.className = 'ht-entry';
    entry.style.animationDelay = (idx * .07) + 's';
    entry.innerHTML = `
      <div class="ht-line">
        <div class="ht-dot"></div>
        <div class="ht-connector"></div>
      </div>
      <div class="ht-card">
        <div class="ht-top">
          <div class="ht-order-id">Order <span>#${o.orderId}</span></div>
          <div class="ht-total">${fmt(o.total)}</div>
        </div>
        <div class="ht-pills">
          <div class="ht-pill"><i class="fa-solid fa-chair"></i> Table #${o.table}</div>
          <div class="ht-pill"><i class="fa-solid fa-clock"></i> ${timeStr}</div>
          <div class="ht-pill"><i class="fa-solid fa-wallet"></i> ${o.payMethod}</div>
          <div class="ht-pill"><i class="fa-solid fa-tag"></i> ${o.orderType||'Dine In'}</div>
        </div>
        <div class="ht-items">${tagsHTML}</div>
      </div>`;
    timeline.appendChild(entry);
  });

  // Payment method breakdown
  const payMap = {};
  sessionHistory.forEach(o => {
    payMap[o.payMethod] = (payMap[o.payMethod]||0) + o.total;
  });
  const payIcons = {'Cash':'fa-money-bill-wave','Card':'fa-credit-card','E-Pay':'fa-mobile-screen-button'};
  const pb = document.getElementById('payBreakdown');
  pb.innerHTML = Object.entries(payMap).map(([method, val]) => `
    <div class="pay-method-row">
      <div class="pay-method-name">
        <i class="fa-solid ${payIcons[method]||'fa-wallet'}"></i> ${method}
      </div>
      <span class="pay-method-val">${fmt(val)}</span>
    </div>`).join('');
}

// ── REPORTS ──────────────────────────────────────────────────
function refreshReports() {
  const items = sessionHistory.reduce((s,o) => s + o.items.reduce((ss,i) => ss+i.qty, 0), 0);
  const avg   = sessionOrders > 0 ? sessionRevenue / sessionOrders : 0;

  document.getElementById('r-revenue').textContent = fmt(sessionRevenue);
  document.getElementById('r-sub').textContent     = sessionOrders > 0
    ? sessionOrders + ' order' + (sessionOrders!==1?'s':'') + ' · ' + items + ' items sold this session'
    : 'No orders placed this session yet';
  document.getElementById('r-orders').textContent  = sessionOrders;
  document.getElementById('r-items').textContent   = items;
  document.getElementById('r-avg').textContent     = fmtShort(avg);
  document.getElementById('r-tables').textContent  = occupiedTables.size;
  document.getElementById('r-date').textContent    = new Date().toLocaleDateString('en-US',{weekday:'short',month:'long',day:'numeric'});

  // Category breakdown
  const catRev = {};
  sessionHistory.forEach(o => o.items.forEach(i => {
    catRev[i.cat] = (catRev[i.cat]||0) + i.price * i.qty;
  }));
  const maxCatRev = Math.max(...Object.values(catRev), 1);
  const breakdownEl = document.getElementById('r-cat-breakdown');
  if (!Object.keys(catRev).length) {
    breakdownEl.innerHTML = `<div class="empty-state" style="padding:30px 0;"><i class="fa-solid fa-chart-pie" style="font-size:32px;"></i><small>No data yet</small></div>`;
  } else {
    breakdownEl.innerHTML = Object.entries(catRev).sort((a,b)=>b[1]-a[1]).map(([cat, val]) => {
      const pct = (val/maxCatRev*100).toFixed(1);
      return `<div class="rpt-cat-item">
        <div class="rpt-cat-icon">${CAT_ICON[cat]||'🍽️'}</div>
        <div class="rpt-cat-info" style="flex:1;min-width:0;">
          <div class="rpt-cat-name">${cat}</div>
          <div class="rpt-cat-track"><div class="rpt-cat-fill" style="width:${pct}%"></div></div>
        </div>
        <span class="rpt-cat-amt">${fmt(val)}</span>
      </div>`;
    }).join('');
  }

  // Top items
  const itemCounts = {};
  sessionHistory.forEach(o => o.items.forEach(i => {
    if(!itemCounts[i.name]) itemCounts[i.name]={qty:0,rev:0,emoji:i.emoji};
    itemCounts[i.name].qty += i.qty;
    itemCounts[i.name].rev += i.price * i.qty;
  }));
  const sorted = Object.entries(itemCounts).sort((a,b)=>b[1].qty-a[1].qty).slice(0,5);
  const topEl  = document.getElementById('r-top-items');
  if (!sorted.length) {
    topEl.innerHTML = `<div class="empty-state" style="padding:30px 0;"><i class="fa-solid fa-trophy" style="font-size:32px;"></i><small>No items ordered yet</small></div>`;
  } else {
    const rankCls = ['rank-1','rank-2','rank-3','rank-n','rank-n'];
    topEl.innerHTML = sorted.map(([name,d],i) => `
      <div class="rpt-top-item" style="animation:fadeUp .22s ${i*.07}s ease both;">
        <div class="rank-badge ${rankCls[i]}">${i+1}</div>
        <span class="top-emoji">${d.emoji}</span>
        <div class="top-info">
          <div class="top-name">${name}</div>
          <div class="top-rev">${fmt(d.rev)} revenue</div>
        </div>
        <span class="top-qty">×${d.qty}</span>
      </div>`).join('');
  }
}

// ── BOOT ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  loadSessionData();
  initMenu();
  refreshStats();

  // Set initial date for reports
  document.getElementById('r-date').textContent =
    new Date().toLocaleDateString('en-US',{weekday:'short',month:'long',day:'numeric'});
});
</script>
</body>
</html>
