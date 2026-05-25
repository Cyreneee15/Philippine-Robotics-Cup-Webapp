<?php
// ═══════════════════════════════════════════════════════════
//  PRC Admin — Analytics  (admin-analytics.php)
//  Dynamic version — all data pulled from prc_db via PDO
// ═══════════════════════════════════════════════════════════

define('DB_HOST', 'localhost');
define('DB_NAME', 'prc_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHAR', 'utf8mb4');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHAR;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function peso(float $v): string {
    return '₱ ' . number_format($v, 0);
}

try {
    $db = db();

    // ── HEADLINE KPIs ──────────────────────────────────────
    $total_regs   = (int)$db->query("SELECT COUNT(*) FROM prc_registrations WHERE reg_status='Active'")->fetchColumn();
    $total_rev    = (float)$db->query("SELECT COALESCE(SUM(package_amount),0) FROM prc_registrations WHERE payment_status='Confirmed' AND reg_status='Active'")->fetchColumn();
    $total_schools= (int)$db->query("SELECT COUNT(DISTINCT school_id) FROM prc_registrations WHERE reg_status='Active'")->fetchColumn();
    $total_members= (int)$db->query("SELECT COALESCE(SUM(member_count),0) FROM prc_registrations WHERE reg_status='Active'")->fetchColumn();
    $total_orders = (int)$db->query("SELECT COUNT(*) FROM prc_shop_orders")->fetchColumn();
    $shop_rev     = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM prc_shop_orders WHERE order_status IN ('confirmed','shipped','completed')")->fetchColumn();
    $conversion   = $total_regs > 0 ? round(
        $db->query("SELECT COUNT(*) FROM prc_registrations WHERE payment_status='Confirmed' AND reg_status='Active'")->fetchColumn()
        / $total_regs * 100, 1
    ) : 0;

    // ── REGISTRATIONS OVER TIME (daily, last 30 days) ──────
    $reg_daily = $db->query("
        SELECT DATE(submitted_at) AS day, COUNT(*) AS cnt
        FROM prc_registrations
        WHERE reg_status='Active'
          AND submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(submitted_at)
        ORDER BY day ASC
    ")->fetchAll();

    // ── REVENUE OVER TIME (daily confirmed, last 30 days) ─
    $rev_daily = $db->query("
        SELECT DATE(updated_at) AS day, SUM(package_amount) AS total
        FROM prc_registrations
        WHERE payment_status='Confirmed' AND reg_status='Active'
          AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(updated_at)
        ORDER BY day ASC
    ")->fetchAll();

    // ── TRACK DISTRIBUTION ─────────────────────────────────
    $track_raw = $db->query("
        SELECT t.category, COUNT(*) AS cnt
        FROM prc_reg_teams t
        JOIN prc_registrations r ON r.team_id=t.team_id
        WHERE r.reg_status='Active'
        GROUP BY t.category
        ORDER BY cnt DESC
    ")->fetchAll();

    $track_map = ['RoboVenture'=>0,'MakeX'=>0,'Drone Soccer'=>0,'Other'=>0];
    foreach ($track_raw as $row) {
        $cat = $row['category'];
        if (str_contains($cat,'Drone')||str_contains($cat,'Robot Soccer')) $track_map['Drone Soccer']+=$row['cnt'];
        elseif (str_contains($cat,'MakeX')) $track_map['MakeX']+=$row['cnt'];
        elseif (str_contains($cat,'Emerging')||str_contains($cat,'Innovator')||str_contains($cat,'Maker')||str_contains($cat,'Navigation')||str_contains($cat,'Aspiring')) $track_map['RoboVenture']+=$row['cnt'];
        else $track_map['Other']+=$row['cnt'];
    }
    if ($track_map['Other'] === 0) unset($track_map['Other']);
    $track_total = max(1, array_sum($track_map));

    // ── CATEGORY BREAKDOWN (all distinct categories) ───────
    $cat_breakdown = $db->query("
        SELECT t.category, COUNT(*) AS cnt
        FROM prc_reg_teams t
        JOIN prc_registrations r ON r.team_id=t.team_id
        WHERE r.reg_status='Active'
        GROUP BY t.category
        ORDER BY cnt DESC
        LIMIT 10
    ")->fetchAll();
    $cat_max = max(1,(int)($cat_breakdown[0]['cnt']??1));

    // ── REGION BREAKDOWN ───────────────────────────────────
    $region_data = $db->query("
        SELECT COALESCE(s.school_region,'Unknown') AS region, COUNT(*) AS cnt
        FROM prc_registrations r
        JOIN prc_reg_schools s ON r.school_id=s.school_id
        WHERE r.reg_status='Active'
        GROUP BY s.school_region
        ORDER BY cnt DESC
        LIMIT 8
    ")->fetchAll();
    $region_max = max(1,(int)($region_data[0]['cnt']??1));

    // ── PAYMENT METHOD & STATUS MIX ────────────────────────
    $pay_method = $db->query("
        SELECT payment_method, COUNT(*) AS cnt
        FROM prc_registrations WHERE reg_status='Active'
        GROUP BY payment_method
    ")->fetchAll();
    $pay_total = max(1,array_sum(array_column($pay_method,'cnt')));

    $pay_status = $db->query("
        SELECT payment_status, COUNT(*) AS cnt
        FROM prc_registrations WHERE reg_status='Active'
        GROUP BY payment_status
        ORDER BY FIELD(payment_status,'Confirmed','Proof Submitted','Pending Payment','Rejected')
    ")->fetchAll();

    // ── PACKAGE DISTRIBUTION ───────────────────────────────
    $packages = $db->query("
        SELECT package_name, COUNT(*) AS cnt, SUM(package_amount) AS revenue
        FROM prc_registrations WHERE reg_status='Active'
        GROUP BY package_name
        ORDER BY cnt DESC
    ")->fetchAll();
    $pkg_max = max(1,(int)($packages[0]['cnt']??1));

    // ── TOP SCHOOLS ────────────────────────────────────────
    $top_schools = $db->query("
        SELECT s.school_name, s.school_region, COUNT(*) AS teams,
               SUM(r.member_count) AS members,
               SUM(CASE WHEN r.payment_status='Confirmed' THEN r.package_amount ELSE 0 END) AS rev
        FROM prc_registrations r
        JOIN prc_reg_schools s ON r.school_id=s.school_id
        WHERE r.reg_status='Active'
        GROUP BY r.school_id
        ORDER BY teams DESC
        LIMIT 8
    ")->fetchAll();

    // ── WEEKLY COHORT: regs per ISO week last 8 weeks ──────
    $weekly = $db->query("
        SELECT YEARWEEK(submitted_at,1) AS yw,
               MIN(DATE(submitted_at)) AS week_start,
               COUNT(*) AS cnt
        FROM prc_registrations
        WHERE reg_status='Active'
          AND submitted_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
        GROUP BY YEARWEEK(submitted_at,1)
        ORDER BY yw ASC
    ")->fetchAll();
    $wk_max = max(1,array_max(array_column($weekly,'cnt')??[1]));

    // ── SHOP: orders by status ─────────────────────────────
    $shop_status = $db->query("
        SELECT order_status, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS rev
        FROM prc_shop_orders
        GROUP BY order_status
        ORDER BY cnt DESC
    ")->fetchAll();

    // ── SHOP: top products ─────────────────────────────────
    $top_products = $db->query("
        SELECT oi.product_name, SUM(oi.quantity) AS qty, SUM(oi.subtotal) AS rev
        FROM prc_shop_order_items oi
        JOIN prc_shop_orders o ON o.order_id=oi.order_id
        WHERE o.order_status IN ('confirmed','shipped','completed')
        GROUP BY oi.product_name
        ORDER BY qty DESC
        LIMIT 6
    ")->fetchAll();
    $prod_max = max(1,(int)($top_products[0]['qty']??1));

    // ── MEMBER COUNT HISTOGRAM ─────────────────────────────
    $member_hist = $db->query("
        SELECT member_count, COUNT(*) AS freq
        FROM prc_registrations WHERE reg_status='Active'
        GROUP BY member_count
        ORDER BY member_count ASC
    ")->fetchAll();

    // ── CONTACT ROLE BREAKDOWN ─────────────────────────────
    $role_data = $db->query("
        SELECT LOWER(TRIM(contact_role)) AS role, COUNT(*) AS cnt
        FROM prc_registrations WHERE reg_status='Active' AND contact_role IS NOT NULL
        GROUP BY role ORDER BY cnt DESC LIMIT 6
    ")->fetchAll();

    // ── MONTH-OVER-MONTH comparison ────────────────────────
    $this_month  = (int)$db->query("SELECT COUNT(*) FROM prc_registrations WHERE reg_status='Active' AND MONTH(submitted_at)=MONTH(NOW()) AND YEAR(submitted_at)=YEAR(NOW())")->fetchColumn();
    $last_month  = (int)$db->query("SELECT COUNT(*) FROM prc_registrations WHERE reg_status='Active' AND MONTH(submitted_at)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND YEAR(submitted_at)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))")->fetchColumn();
    $mom_delta   = $last_month > 0 ? round(($this_month - $last_month) / $last_month * 100, 1) : ($this_month > 0 ? 100 : 0);

    $this_month_rev = (float)$db->query("SELECT COALESCE(SUM(package_amount),0) FROM prc_registrations WHERE payment_status='Confirmed' AND reg_status='Active' AND MONTH(updated_at)=MONTH(NOW()) AND YEAR(updated_at)=YEAR(NOW())")->fetchColumn();

} catch (PDOException $e) {
    $db_error = 'DB Error: ' . $e->getMessage();
}

// ── helpers for chart data ─────────────────────────────────
function chartJson(array $rows, string $keyCol, string $valCol): string {
    $out = [];
    foreach ($rows as $r) $out[] = ['label'=>$r[$keyCol],'value'=>(float)$r[$valCol]];
    return json_encode($out);
}

function array_max(array $a): int {
    return $a ? (int)max($a) : 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex,nofollow" />
  <title>Analytics — PRC Admin</title>
  <link rel="icon" type="image/png" href="assets/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css'>

  <style>
    /* ═══════════════════════════════════════
       PRC ADMIN — ANALYTICS PAGE STYLES
    ═══════════════════════════════════════ */
    :root {
      --sb-width:       248px;
      --sb-collapsed:   68px;
      --topbar-h:       60px;
      --bg-void:        #03020D;
      --bg-deep:        #06051A;
      --bg-card:        rgba(10,8,30,0.80);
      --prc-violet:     #8B7EFF;
      --prc-ice:        #C4EEFF;
      --creo-amber:     #FFA030;
      --creo-volt:      #FFE930;
      --creo-sky:       #44D9FF;
      --neon-green:     #44FF88;
      --admin-red:      #FF4D6A;
      --border-neon:    rgba(139,126,255,0.18);
      --text-high:      #F2EEFF;
      --text-mid:       #C8C0F0;
      --text-soft:      #9A90CC;
      --text-dim:       #6058A0;
      --font-hud:       'Orbitron', monospace;
      --font-body:      'Exo 2', sans-serif;
      --radius:         3px;
      --transition:     all 0.25s ease;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    html{scroll-behavior:smooth;}
    body{
      font-family:var(--font-body);
      background:var(--bg-void);
      color:var(--text-high);
      overflow-x:hidden;
      line-height:1.6;
      min-height:100vh;
      cursor:none;
    }
    img{max-width:100%;display:block;}
    a{text-decoration:none;color:inherit;}
    ul{list-style:none;}
    button{font-family:inherit;border:none;background:none;cursor:none;}

    /* Grid background */
    body::before{
      content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
      background-image:
        linear-gradient(rgba(139,126,255,0.03) 1px,transparent 1px),
        linear-gradient(90deg,rgba(139,126,255,0.03) 1px,transparent 1px);
      background-size:44px 44px;
    }
    body::after{
      content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
      background:repeating-linear-gradient(to bottom,transparent,transparent 2px,rgba(0,0,0,0.025) 2px,rgba(0,0,0,0.025) 4px);
    }

    /* ── CURSOR ── */
    .cursor-dot{position:fixed;width:8px;height:8px;border-radius:50%;background:var(--prc-violet);pointer-events:none;z-index:99999;transform:translate(-50%,-50%);box-shadow:0 0 18px rgba(139,126,255,.80);transition:transform .1s}
    .cursor-ring{position:fixed;width:36px;height:36px;border-radius:50%;border:1px solid rgba(139,126,255,.60);pointer-events:none;z-index:99998;transform:translate(-50%,-50%);transition:width .25s,height .25s}
    .cursor-ring.hovered{width:52px;height:52px;border-color:var(--creo-amber)}

    /* ═══ LAYOUT ═══ */
    .admin-shell{
      display:grid;
      grid-template-columns:var(--sb-width) 1fr;
      grid-template-rows:var(--topbar-h) 1fr;
      min-height:100vh;
      position:relative;z-index:1;
      transition:grid-template-columns 0.30s cubic-bezier(0.77,0,0.175,1);
    }
    .admin-shell.sb-collapsed{grid-template-columns:var(--sb-collapsed) 1fr;}
    .admin-sidebar-slot{grid-row:1/-1;grid-column:1;}

    /* ── TOP BAR ── */
    .admin-topbar{
      grid-column:2;grid-row:1;
      height:var(--topbar-h);
      background:rgba(3,2,13,0.92);
      backdrop-filter:blur(20px);
      border-bottom:1px solid var(--border-neon);
      display:flex;align-items:center;
      padding:0 28px;gap:16px;
      position:sticky;top:0;z-index:800;
      box-shadow:0 1px 30px rgba(139,126,255,0.07);
    }
    .topbar-breadcrumb{display:flex;align-items:center;gap:8px;font-family:var(--font-hud);font-size:0.60rem;color:var(--text-dim);letter-spacing:0.10em;text-transform:uppercase;}
    .topbar-breadcrumb span{color:var(--prc-violet);}
    .topbar-right{margin-left:auto;display:flex;align-items:center;gap:10px;}
    .topbar-icon-btn{width:36px;height:36px;background:rgba(139,126,255,0.05);border:1px solid var(--border-neon)!important;border-radius:var(--radius);display:flex;align-items:center;justify-content:center;color:var(--text-soft);transition:var(--transition);cursor:none;}
    .topbar-icon-btn i{font-size:0.90rem;}
    .topbar-icon-btn:hover{background:rgba(139,126,255,0.14);color:var(--prc-violet);border-color:var(--prc-violet)!important;}
    .topbar-date{font-family:var(--font-hud);font-size:0.52rem;color:var(--text-dim);letter-spacing:0.10em;white-space:nowrap;}
    .topbar-date span{color:var(--creo-volt);}

    /* ── FILTER TABS ── */
    .filter-row{display:flex;align-items:center;gap:8px;margin-bottom:24px;flex-wrap:wrap;}
    .filter-tab{font-family:var(--font-hud);font-size:0.54rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:7px 16px;border:1px solid rgba(139,126,255,0.22);background:transparent;color:var(--text-dim);cursor:pointer!important;transition:all 0.20s;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);}
    .filter-tab.active,.filter-tab:hover{border-color:var(--prc-violet);background:rgba(139,126,255,0.10);color:var(--prc-violet);}
    .filter-sep{width:1px;height:24px;background:rgba(139,126,255,0.18);margin:0 4px;}

    /* ═══ MAIN ═══ */
    .admin-main{
      grid-column:2;grid-row:2;
      padding:28px 28px 60px;
      overflow-y:auto;
      min-height:calc(100vh - var(--topbar-h));
    }

    /* ═══ PAGE HEADER ═══ */
    .page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:24px;}
    .page-eyebrow{font-family:var(--font-hud);font-size:0.52rem;letter-spacing:0.20em;text-transform:uppercase;color:var(--prc-ice);margin-bottom:6px;display:flex;align-items:center;gap:8px;}
    .page-eyebrow::before{content:'//';color:rgba(139,126,255,0.38);}
    .page-title{font-family:var(--font-hud);font-size:clamp(1.3rem,3vw,2rem);font-weight:900;color:#fff;letter-spacing:-0.01em;}
    .page-title .accent{color:var(--prc-violet);text-shadow:0 0 22px rgba(139,126,255,0.60);}
    .page-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
    .btn{display:inline-flex;align-items:center;gap:8px;font-family:var(--font-hud);font-size:0.58rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:9px 18px;border-radius:var(--radius);transition:var(--transition);white-space:nowrap;cursor:pointer!important;}
    .btn-ghost{background:rgba(139,126,255,0.04);border:1px solid var(--border-neon)!important;color:var(--text-soft);}
    .btn-ghost:hover{background:rgba(139,126,255,0.10);color:var(--text-mid);}
    .btn-primary{background:rgba(139,126,255,0.10);border:1px solid var(--prc-violet)!important;color:var(--prc-violet);clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);}
    .btn-primary:hover{background:rgba(139,126,255,0.22);color:#fff;}

    /* ═══ KPI STRIP ═══ */
    .kpi-strip{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:24px;}
    .kpi-cell{
      background:var(--bg-card);border:1px solid var(--border-neon);
      padding:16px 18px;position:relative;overflow:hidden;
      clip-path:polygon(0 0,calc(100% - 8px) 0,100% 8px,100% 100%,8px 100%,0 calc(100% - 8px));
      transition:border-color .25s,box-shadow .25s;
    }
    .kpi-cell::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;}
    .kpi-cell.v::before{background:linear-gradient(90deg,transparent,var(--prc-violet),transparent);}
    .kpi-cell.g::before{background:linear-gradient(90deg,transparent,var(--neon-green),transparent);}
    .kpi-cell.a::before{background:linear-gradient(90deg,transparent,var(--creo-amber),transparent);}
    .kpi-cell.y::before{background:linear-gradient(90deg,transparent,var(--creo-volt),transparent);}
    .kpi-cell.s::before{background:linear-gradient(90deg,transparent,var(--creo-sky),transparent);}
    .kpi-cell.r::before{background:linear-gradient(90deg,transparent,var(--admin-red),transparent);}
    .kpi-cell:hover{border-color:rgba(139,126,255,0.38);box-shadow:0 0 20px rgba(139,126,255,0.10);}
    .kpi-cell-num{font-family:var(--font-hud);font-size:1.60rem;font-weight:800;display:block;line-height:1.1;margin-bottom:3px;}
    .kpi-cell.v .kpi-cell-num{color:var(--prc-violet);text-shadow:0 0 16px rgba(139,126,255,.55);}
    .kpi-cell.g .kpi-cell-num{color:var(--neon-green);text-shadow:0 0 16px rgba(68,255,136,.55);}
    .kpi-cell.a .kpi-cell-num{color:var(--creo-amber);text-shadow:0 0 16px rgba(255,160,48,.55);}
    .kpi-cell.y .kpi-cell-num{color:var(--creo-volt);text-shadow:0 0 16px rgba(255,233,48,.55);}
    .kpi-cell.s .kpi-cell-num{color:var(--creo-sky);text-shadow:0 0 16px rgba(68,217,255,.55);}
    .kpi-cell.r .kpi-cell-num{color:var(--admin-red);text-shadow:0 0 16px rgba(255,77,106,.55);}
    .kpi-cell-lbl{font-family:var(--font-hud);font-size:0.48rem;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.12em;}
    .kpi-cell-sub{font-size:0.72rem;color:var(--text-dim);margin-top:5px;}
    .kpi-cell-delta{
      position:absolute;top:12px;right:12px;
      font-family:var(--font-hud);font-size:0.44rem;font-weight:700;
      padding:2px 7px;clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%);
    }
    .delta-up{background:rgba(68,255,136,0.12);color:var(--neon-green);border:1px solid rgba(68,255,136,0.25);}
    .delta-dn{background:rgba(255,77,106,0.12);color:var(--admin-red);border:1px solid rgba(255,77,106,0.25);}
    .delta-eq{background:rgba(255,233,48,0.10);color:var(--creo-volt);border:1px solid rgba(255,233,48,0.25);}

    /* ═══ GRID LAYOUTS ═══ */
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px;}
    .grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;margin-bottom:20px;}
    .grid-wide{display:grid;grid-template-columns:2fr 1fr;gap:18px;margin-bottom:20px;}
    .grid-1-3{display:grid;grid-template-columns:1fr 3fr;gap:18px;margin-bottom:20px;}

    /* ═══ PANEL ═══ */
    .panel{background:var(--bg-card);border:1px solid var(--border-neon);position:relative;overflow:hidden;}
    .panel::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent);opacity:.35;}
    .panel.accent-green::before{background:linear-gradient(90deg,transparent,var(--neon-green),transparent);}
    .panel.accent-amber::before{background:linear-gradient(90deg,transparent,var(--creo-amber),transparent);}
    .panel.accent-volt::before{background:linear-gradient(90deg,transparent,var(--creo-volt),transparent);}
    .panel.accent-sky::before{background:linear-gradient(90deg,transparent,var(--creo-sky),transparent);}

    .panel-hdr{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid rgba(139,126,255,0.10);background:rgba(139,126,255,0.025);}
    .panel-title{font-family:var(--font-hud);font-size:0.63rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--prc-ice);display:flex;align-items:center;gap:8px;}
    .panel-title i{color:var(--prc-violet);font-size:0.90rem;}
    .panel-body{padding:18px 20px;}
    .panel-action{font-family:var(--font-hud);font-size:0.48rem;font-weight:600;letter-spacing:0.10em;text-transform:uppercase;color:var(--prc-violet);border:1px solid rgba(139,126,255,0.28)!important;background:rgba(139,126,255,0.06);padding:5px 12px;border-radius:2px;cursor:pointer!important;transition:var(--transition);}
    .panel-action:hover{background:rgba(139,126,255,0.16);}

    /* ═══ SECTION LABEL ═══ */
    .section-label{
      font-family:var(--font-hud);font-size:0.52rem;font-weight:700;
      letter-spacing:0.20em;text-transform:uppercase;color:var(--text-dim);
      display:flex;align-items:center;gap:10px;margin-bottom:14px;
    }
    .section-label::after{content:'';flex:1;height:1px;background:rgba(139,126,255,0.12);}

    /* ═══ BAR CHART ═══ */
    .bar-chart{display:flex;flex-direction:column;gap:9px;}
    .bar-row{display:grid;align-items:center;gap:10px;}
    .bar-row.cols-3{grid-template-columns:130px 1fr 52px;}
    .bar-row.cols-2{grid-template-columns:1fr 52px;}
    .bar-label{font-family:var(--font-hud);font-size:0.50rem;color:var(--text-soft);letter-spacing:0.06em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .bar-track{height:8px;background:rgba(139,126,255,0.08);border-radius:2px;overflow:hidden;position:relative;}
    .bar-track-inner{height:100%;border-radius:2px;width:0;transition:width 1.2s cubic-bezier(0.77,0,0.175,1);}
    .bar-v{background:linear-gradient(90deg,#5541CC,var(--prc-violet));box-shadow:0 0 8px rgba(139,126,255,.40);}
    .bar-g{background:linear-gradient(90deg,#22AA55,var(--neon-green));box-shadow:0 0 8px rgba(68,255,136,.40);}
    .bar-a{background:linear-gradient(90deg,#CC7020,var(--creo-amber));box-shadow:0 0 8px rgba(255,160,48,.40);}
    .bar-y{background:linear-gradient(90deg,#BBAA00,var(--creo-volt));box-shadow:0 0 8px rgba(255,233,48,.40);}
    .bar-s{background:linear-gradient(90deg,#1499AA,var(--creo-sky));box-shadow:0 0 8px rgba(68,217,255,.40);}
    .bar-r{background:linear-gradient(90deg,#CC2244,var(--admin-red));box-shadow:0 0 8px rgba(255,77,106,.40);}
    .bar-val{font-family:var(--font-hud);font-size:0.54rem;color:var(--text-soft);text-align:right;}

    /* ═══ CANVAS CHARTS ═══ */
    .chart-wrap{position:relative;width:100%;}
    canvas{display:block;width:100%!important;}

    /* ═══ DONUT ═══ */
    .donut-wrap{display:flex;align-items:center;gap:20px;}
    .donut-svg{width:110px;height:110px;flex-shrink:0;}
    .donut-legend{display:flex;flex-direction:column;gap:7px;flex:1;}
    .donut-leg-item{display:flex;align-items:center;gap:8px;}
    .donut-leg-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
    .donut-leg-label{font-size:0.80rem;color:var(--text-mid);flex:1;}
    .donut-leg-pct{font-family:var(--font-hud);font-size:0.54rem;color:var(--text-soft);}
    .donut-leg-cnt{font-family:var(--font-hud);font-size:0.58rem;font-weight:700;color:var(--text-high);min-width:28px;text-align:right;}

    /* ═══ TABLE ═══ */
    .data-table{width:100%;border-collapse:collapse;}
    .data-table th{font-family:var(--font-hud);font-size:0.48rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-dim);padding:10px 12px;border-bottom:1px solid var(--border-neon);text-align:left;white-space:nowrap;}
    .data-table td{font-size:0.82rem;color:var(--text-mid);padding:10px 12px;border-bottom:1px solid rgba(139,126,255,0.07);vertical-align:middle;}
    .data-table tbody tr:hover{background:rgba(139,126,255,0.05);}
    .data-table tbody tr:last-child td{border-bottom:none;}
    .td-hi{font-weight:600;color:var(--text-high);}
    .td-sm{font-size:0.72rem;color:var(--text-dim);}

    /* ═══ SPARKLINE MINI ═══ */
    .spark-val{font-family:var(--font-hud);font-size:0.72rem;color:var(--neon-green);}

    /* ═══ PILLS ═══ */
    .pill{display:inline-flex;align-items:center;gap:5px;font-family:var(--font-hud);font-size:0.46rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:3px 10px;clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%);white-space:nowrap;}
    .pill-dot{width:5px;height:5px;border-radius:50%;}
    .pill.confirmed{background:rgba(68,255,136,0.10);color:var(--neon-green);border:1px solid rgba(68,255,136,.25);}
    .pill.confirmed .pill-dot{background:var(--neon-green);}
    .pill.pending{background:rgba(255,233,48,0.10);color:var(--creo-volt);border:1px solid rgba(255,233,48,.25);}
    .pill.pending .pill-dot{background:var(--creo-volt);}
    .pill.processing{background:rgba(255,160,48,0.10);color:var(--creo-amber);border:1px solid rgba(255,160,48,.25);}
    .pill.processing .pill-dot{background:var(--creo-amber);}
    .pill.cancelled{background:rgba(255,77,106,0.10);color:var(--admin-red);border:1px solid rgba(255,77,106,.25);}
    .pill.cancelled .pill-dot{background:var(--admin-red);}

    /* ═══ RANK NUMBER ═══ */
    .rank-num{font-family:var(--font-hud);font-size:0.60rem;font-weight:800;color:var(--text-dim);width:22px;flex-shrink:0;}
    .rank-num.top{color:var(--creo-volt);}

    /* ═══ STAT CALLOUT ═══ */
    .callout-row{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;}
    .callout{flex:1;min-width:100px;background:rgba(139,126,255,0.05);border:1px solid rgba(139,126,255,0.14);padding:12px 14px;}
    .callout-lbl{font-family:var(--font-hud);font-size:0.46rem;color:var(--text-dim);letter-spacing:0.12em;text-transform:uppercase;margin-bottom:4px;}
    .callout-val{font-family:var(--font-hud);font-size:0.95rem;font-weight:700;}

    /* ═══ EMPTY ═══ */
    .empty{text-align:center;padding:30px;font-family:var(--font-hud);font-size:0.56rem;color:var(--text-dim);letter-spacing:0.10em;}

    /* ═══ SCROLLBAR ═══ */
    ::-webkit-scrollbar{width:4px;}
    ::-webkit-scrollbar-track{background:var(--bg-void);}
    ::-webkit-scrollbar-thumb{background:var(--prc-violet);border-radius:2px;}

    /* ═══ ANIMATIONS ═══ */
    @keyframes fadeInUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
    .anim-1{animation:fadeInUp .5s ease .05s both}
    .anim-2{animation:fadeInUp .5s ease .12s both}
    .anim-3{animation:fadeInUp .5s ease .19s both}
    .anim-4{animation:fadeInUp .5s ease .26s both}
    .anim-5{animation:fadeInUp .5s ease .33s both}

    /* ═══ RESPONSIVE ═══ */
    @media(max-width:1200px){
      .kpi-strip{grid-template-columns:repeat(3,1fr);}
      .grid-3{grid-template-columns:1fr 1fr;}
      .grid-wide{grid-template-columns:1fr;}
    }
    @media(max-width:900px){
      .admin-shell{grid-template-columns:0 1fr;}
      .admin-sidebar-slot{display:none;}
      .admin-main{padding:18px 16px 48px;}
      body{cursor:auto;}
      .cursor-dot,.cursor-ring{display:none;}
      .kpi-strip{grid-template-columns:repeat(2,1fr);}
      .grid-2,.grid-wide,.grid-1-3{grid-template-columns:1fr;}
    }
    @media(max-width:600px){
      .kpi-strip{grid-template-columns:1fr 1fr;}
      .grid-3{grid-template-columns:1fr;}
    }
  </style>
</head>
<body>

<div class="cursor-dot"  id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<div class="admin-shell" id="adminShell">

  <!-- SIDEBAR SLOT -->
  <div class="admin-sidebar-slot" id="sidebarSlot"></div>

  <!-- TOP BAR -->
  <header class="admin-topbar">
    <div class="topbar-breadcrumb">
      PRC Admin
      <i class="fi fi-rr-angle-right" style="font-size:.55rem;color:var(--text-dim)"></i>
      <span>Analytics</span>
    </div>
    <div class="topbar-right">
      <button class="topbar-icon-btn" title="Refresh" onclick="location.reload()">
        <i class="fi fi-rr-refresh"></i>
      </button>
      <a href="admin-dashboard.php" class="topbar-icon-btn" title="Dashboard">
        <i class="fi fi-rr-layout-fluid"></i>
      </a>
      <button class="topbar-icon-btn" title="Print / Export" onclick="window.print()">
        <i class="fi fi-rr-print"></i>
      </button>
      <div class="topbar-date"><span id="topbar-date-display"></span></div>
    </div>
  </header>

  <!-- MAIN -->
  <main class="admin-main">

    <?php if (isset($db_error)): ?>
    <div style="background:rgba(255,77,106,0.08);border:1px solid rgba(255,77,106,0.30);padding:16px 20px;margin-bottom:24px;font-family:var(--font-hud);font-size:0.62rem;color:var(--admin-red);">
      <i class="fi fi-rr-exclamation"></i> <?= h($db_error) ?>
    </div>
    <?php else: ?>

    <!-- PAGE HEADER -->
    <div class="page-header anim-1">
      <div>
        <div class="page-eyebrow">PRC 2026 // Intelligence Layer</div>
        <h1 class="page-title"><span class="accent">Analytics</span> &amp; Insights</h1>
      </div>
      <div class="page-actions">
        <a href="admin-registrations.php" class="btn btn-ghost">
          <i class="fi fi-rr-users"></i> Registrations
        </a>
        <a href="admin-shop.php" class="btn btn-primary">
          <i class="fi fi-rr-shopping-cart"></i> Shop
        </a>
      </div>
    </div>

    <!-- ═══ KPI STRIP ═══ -->
    <div class="kpi-strip anim-2">

      <div class="kpi-cell v">
        <?php $d = $mom_delta; ?>
        <span class="kpi-cell-delta <?= $d>0?'delta-up':($d<0?'delta-dn':'delta-eq') ?>">
          <?= $d>0?'▲ +':($d<0?'▼ ':'► ') ?><?= abs($d) ?>% MoM
        </span>
        <span class="kpi-cell-num" data-count="<?= $total_regs ?>"><?= $total_regs ?></span>
        <div class="kpi-cell-lbl">Active Registrations</div>
        <div class="kpi-cell-sub"><?= $this_month ?> this month</div>
      </div>

      <div class="kpi-cell g">
        <span class="kpi-cell-num" style="font-size:1.1rem;margin-top:3px" data-count-peso="<?= $total_rev ?>"><?= peso($total_rev) ?></span>
        <div class="kpi-cell-lbl">Confirmed Rev.</div>
        <div class="kpi-cell-sub"><?= peso($this_month_rev) ?> this month</div>
      </div>

      <div class="kpi-cell a">
        <span class="kpi-cell-num" data-count="<?= $total_schools ?>"><?= $total_schools ?></span>
        <div class="kpi-cell-lbl">Schools</div>
        <div class="kpi-cell-sub">Across <?= count($region_data) ?> region<?= count($region_data)!=1?'s':'' ?></div>
      </div>

      <div class="kpi-cell y">
        <span class="kpi-cell-num" data-count="<?= $total_members ?>"><?= $total_members ?></span>
        <div class="kpi-cell-lbl">Total Members</div>
        <div class="kpi-cell-sub">avg <?= $total_regs>0?round($total_members/$total_regs,1):0 ?> per team</div>
      </div>

      <div class="kpi-cell s">
        <span class="kpi-cell-num" data-count="<?= $total_orders ?>"><?= $total_orders ?></span>
        <div class="kpi-cell-lbl">Shop Orders</div>
        <div class="kpi-cell-sub"><?= peso($shop_rev) ?> confirmed</div>
      </div>

      <div class="kpi-cell r">
        <span class="kpi-cell-num"><?= $conversion ?>%</span>
        <div class="kpi-cell-lbl">Conversion Rate</div>
        <div class="kpi-cell-sub">Proof→Confirmed</div>
      </div>

    </div><!-- /kpi-strip -->

    <!-- ═══ SECTION: REGISTRATION TRENDS ═══ -->
    <div class="section-label anim-3">
      <i class="fi fi-rr-chart-line-up" style="color:var(--prc-violet);font-size:.80rem"></i>
      Registration Trends
    </div>

    <!-- DAILY CHART + WEEKLY BARS -->
    <div class="grid-wide anim-3">

      <!-- Daily Registrations Line -->
      <div class="panel">
        <div class="panel-hdr">
          <div class="panel-title"><i class="fi fi-rr-line-width"></i> Daily Registrations — Last 30 Days</div>
        </div>
        <div class="panel-body">
          <div class="chart-wrap" style="height:200px">
            <canvas id="chartDailyRegs"></canvas>
          </div>
        </div>
      </div>

      <!-- Weekly cohort bars -->
      <div class="panel">
        <div class="panel-hdr">
          <div class="panel-title"><i class="fi fi-rr-calendar-week"></i> Weekly Cohorts</div>
        </div>
        <div class="panel-body">
          <?php if (empty($weekly)): ?>
            <div class="empty">No weekly data available.</div>
          <?php else: ?>
          <div class="bar-chart" style="gap:12px">
            <?php foreach ($weekly as $wk):
              $pct = round($wk['cnt'] / $wk_max * 100);
              $lbl = date('M j', strtotime($wk['week_start']));
            ?>
            <div class="bar-row cols-3" style="grid-template-columns:72px 1fr 36px">
              <div class="bar-label"><?= h($lbl) ?></div>
              <div class="bar-track"><div class="bar-track-inner bar-v" data-width="<?= $pct ?>%"></div></div>
              <div class="bar-val"><?= $wk['cnt'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /grid-wide -->

    <!-- ═══ SECTION: TRACK & CATEGORY ═══ -->
    <div class="section-label anim-4">
      <i class="fi fi-rr-layers" style="color:var(--prc-violet);font-size:.80rem"></i>
      Track &amp; Category Distribution
    </div>

    <div class="grid-3 anim-4">

      <!-- Track Donut -->
      <div class="panel">
        <div class="panel-hdr">
          <div class="panel-title"><i class="fi fi-rr-chart-pie"></i> Track Split</div>
        </div>
        <div class="panel-body">
          <?php
            $colors = ['RoboVenture'=>'#8B7EFF','MakeX'=>'#FFA030','Drone Soccer'=>'#44FF88','Other'=>'#44D9FF'];
            $circ = 188.50; $offset = 0;
          ?>
          <div class="donut-wrap">
            <svg class="donut-svg" viewBox="0 0 80 80">
              <circle cx="40" cy="40" r="30" fill="none" stroke="rgba(139,126,255,0.07)" stroke-width="12"/>
              <?php foreach ($track_map as $track => $cnt):
                $pct  = $cnt / $track_total;
                $dash = round($pct * $circ, 2);
                $col  = $colors[$track] ?? '#8B7EFF';
                $off  = -$offset;
              ?>
              <circle cx="40" cy="40" r="30" fill="none" stroke="<?= $col ?>" stroke-width="12"
                stroke-dasharray="<?= $dash ?> <?= $circ - $dash ?>"
                stroke-dashoffset="<?= $off ?>" stroke-linecap="butt"
                transform="rotate(-90 40 40)"
                style="filter:drop-shadow(0 0 3px <?= $col ?>80)"/>
              <?php $offset += $dash; endforeach; ?>
              <text x="40" y="37" text-anchor="middle" font-family="Orbitron,monospace" font-size="9" font-weight="700" fill="#F2EEFF"><?= $total_regs ?></text>
              <text x="40" y="46" text-anchor="middle" font-family="Exo 2,sans-serif" font-size="5" fill="#9A90CC" letter-spacing="0.5">ACTIVE</text>
            </svg>
            <div class="donut-legend">
              <?php foreach ($track_map as $track => $cnt):
                $pct = round($cnt / $track_total * 100);
                $col = $colors[$track] ?? '#8B7EFF';
              ?>
              <div class="donut-leg-item">
                <div class="donut-leg-dot" style="background:<?= $col ?>;box-shadow:0 0 5px <?= $col ?>80"></div>
                <div class="donut-leg-label"><?= h($track) ?></div>
                <div class="donut-leg-pct"><?= $pct ?>%</div>
                <div class="donut-leg-cnt"><?= $cnt ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Category breakdown bars -->
      <div class="panel" style="grid-column:span 2">
        <div class="panel-hdr">
          <div class="panel-title"><i class="fi fi-rr-list"></i> Category Breakdown</div>
        </div>
        <div class="panel-body">
          <div class="bar-chart">
            <?php
            $cat_colors = ['bar-v','bar-a','bar-g','bar-y','bar-s','bar-r','bar-v','bar-a','bar-g','bar-y'];
            foreach ($cat_breakdown as $i => $row):
              $pct = round($row['cnt'] / $cat_max * 100);
              $col = $cat_colors[$i % count($cat_colors)];
            ?>
            <div class="bar-row cols-3">
              <div class="bar-label" title="<?= h($row['category']) ?>"><?= h(mb_strimwidth($row['category'],0,22,'…')) ?></div>
              <div class="bar-track"><div class="bar-track-inner <?= $col ?>" data-width="<?= $pct ?>%"></div></div>
              <div class="bar-val"><?= $row['cnt'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    </div><!-- /grid-3 -->

    <!-- ═══ SECTION: GEOGRAPHIC & PAYMENT ═══ -->
    <div class="section-label anim-5">
      <i class="fi fi-rr-globe" style="color:var(--prc-violet);font-size:.80rem"></i>
      Geographic &amp; Payment Analysis
    </div>

    <div class="grid-2 anim-5">

      <!-- Region Breakdown -->
      <div class="panel">
        <div class="panel-hdr">
          <div class="panel-title"><i class="fi fi-rr-marker"></i> Registrations by Region</div>
        </div>
        <div class="panel-body">
          <?php if (empty($region_data)): ?>
            <div class="empty">No region data.</div>
          <?php else: ?>
          <div class="bar-chart">
            <?php
            $rc = ['bar-v','bar-s','bar-a','bar-g','bar-y','bar-r','bar-v','bar-s'];
            foreach ($region_data as $i => $row):
              $pct = round($row['cnt'] / $region_max * 100);
              $lbl = preg_replace('/\s*[-–]\s*.+$/','',$row['region']);
              $lbl = mb_strimwidth($lbl,0,20,'…');
            ?>
            <div class="bar-row cols-3">
              <div class="bar-label" title="<?= h($row['region']) ?>"><?= h($lbl) ?></div>
              <div class="bar-track"><div class="bar-track-inner <?= $rc[$i%count($rc)] ?>" data-width="<?= $pct ?>%"></div></div>
              <div class="bar-val"><?= $row['cnt'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Payment Analysis -->
      <div class="panel">
        <div class="panel-hdr">
          <div class="panel-title"><i class="fi fi-rr-wallet"></i> Payment Breakdown</div>
        </div>
        <div class="panel-body">

          <!-- Method split -->
          <div class="callout-row">
            <?php foreach ($pay_method as $pm):
              $pct = round($pm['cnt'] / $pay_total * 100);
              $col = $pm['payment_method']==='GCash' ? '#8B7EFF' : ($pm['payment_method']==='Bank Transfer' ? '#FFA030' : '#6058A0');
            ?>
            <div class="callout">
              <div class="callout-lbl"><?= h($pm['payment_method']) ?></div>
              <div class="callout-val" style="color:<?= $col ?>"><?= $pct ?>% <span style="font-size:0.62rem;color:var(--text-dim)">(<?= $pm['cnt'] ?>)</span></div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Payment status funnel -->
          <div style="margin-top:12px">
            <div style="font-family:var(--font-hud);font-size:0.48rem;color:var(--text-dim);letter-spacing:0.12em;text-transform:uppercase;margin-bottom:10px">Status Funnel</div>
            <div class="bar-chart">
              <?php
              $ps_colors=['Confirmed'=>'bar-g','Proof Submitted'=>'bar-a','Pending Payment'=>'bar-y','Rejected'=>'bar-r'];
              $ps_total = max(1,array_sum(array_column($pay_status,'cnt')));
              foreach ($pay_status as $ps):
                $pct = round($ps['cnt']/$ps_total*100);
                $col = $ps_colors[$ps['payment_status']] ?? 'bar-v';
              ?>
              <div class="bar-row" style="grid-template-columns:120px 1fr 36px;gap:10px">
                <div class="bar-label"><?= h($ps['payment_status']) ?></div>
                <div class="bar-track"><div class="bar-track-inner <?= $col ?>" data-width="<?= $pct ?>%"></div></div>
                <div class="bar-val"><?= $ps['cnt'] ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Revenue confirmed chart -->
          <?php if (!empty($rev_daily)): ?>
          <div style="margin-top:18px">
            <div style="font-family:var(--font-hud);font-size:0.48rem;color:var(--text-dim);letter-spacing:0.12em;text-transform:uppercase;margin-bottom:10px">Revenue Timeline (30d)</div>
            <div class="chart-wrap" style="height:90px">
              <canvas id="chartRevenue"></canvas>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </div>

    </div><!-- /grid-2 -->

    <!-- ═══ SECTION: PACKAGES & SCHOOLS ═══ -->
    <div class="section-label">
      <i class="fi fi-rr-box-alt" style="color:var(--prc-violet);font-size:.80rem"></i>
      Packages &amp; Top Schools
    </div>

    <div class="grid-2">

      <!-- Package distribution -->
      <div class="panel accent-volt">
        <div class="panel-hdr">
          <div class="panel-title"><i class="fi fi-rr-tags"></i> Package Distribution</div>
        </div>
        <div class="panel-body">
          <?php if (empty($packages)): ?>
            <div class="empty">No package data.</div>
          <?php else: ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Package</th>
                <th>Registrations</th>
                <th>Bar</th>
                <th>Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($packages as $pkg):
                $pct = round($pkg['cnt']/$pkg_max*100);
              ?>
              <tr>
                <td class="td-hi"><?= h($pkg['package_name']) ?></td>
                <td><span style="font-family:var(--font-hud);font-size:0.70rem;color:var(--creo-volt)"><?= $pkg['cnt'] ?></span></td>
                <td style="min-width:80px">
                  <div class="bar-track" style="height:6px"><div class="bar-track-inner bar-y" data-width="<?= $pct ?>%" style="height:6px"></div></div>
                </td>
                <td style="font-family:var(--font-hud);font-size:0.60rem;color:var(--neon-green)"><?= peso($pkg['revenue']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>

      <!-- Top schools table -->
      <div class="panel accent-sky">
        <div class="panel-hdr">
          <div class="panel-title"><i class="fi fi-rr-graduation-cap"></i> Top Schools by Engagement</div>
          <a href="admin-registrations.php" class="panel-action">View All →</a>
        </div>
        <div class="panel-body" style="padding:0">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>School</th>
                <th>Teams</th>
                <th>Members</th>
                <th>Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($top_schools)): ?>
                <tr><td colspan="5"><div class="empty">No school data.</div></td></tr>
              <?php else: ?>
              <?php foreach ($top_schools as $i => $s): ?>
              <tr>
                <td><span class="rank-num <?= $i<3?'top':'' ?>"><?= $i+1 ?></span></td>
                <td>
                  <div class="td-hi" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($s['school_name']) ?>"><?= h(mb_strimwidth($s['school_name'],0,24,'…')) ?></div>
                  <div class="td-sm"><?= h(preg_replace('/\s*[-–]\s*.+$/','',($s['school_region']??'—'))) ?></div>
                </td>
                <td><span style="font-family:var(--font-hud);font-size:0.72rem;color:var(--creo-sky)"><?= $s['teams'] ?></span></td>
                <td style="color:var(--text-mid)"><?= $s['members'] ?></td>
                <td style="font-family:var(--font-hud);font-size:0.58rem;color:var(--neon-green)"><?= $s['rev']>0?peso($s['rev']):'—' ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /grid-2 -->

    <!-- ═══ SECTION: SHOP ANALYTICS ═══ -->
    <div class="section-label">
      <i class="fi fi-rr-shopping-bag" style="color:var(--prc-violet);font-size:.80rem"></i>
      Shop Analytics
    </div>

    <div class="grid-2">

      <!-- Order status -->
      <div class="panel accent-amber">
        <div class="panel-hdr">
          <div class="panel-title"><i class="fi fi-rr-receipt"></i> Order Status Overview</div>
        </div>
        <div class="panel-body">
          <?php
          $ord_clr=['pending'=>'var(--creo-volt)','confirmed'=>'var(--neon-green)','shipped'=>'var(--creo-sky)','completed'=>'#44FF88','cancelled'=>'var(--admin-red)'];
          $ord_pill=['pending'=>'pending','confirmed'=>'confirmed','shipped'=>'confirmed','completed'=>'confirmed','cancelled'=>'cancelled'];
          $ord_total = max(1,array_sum(array_column($shop_status,'cnt')));
          ?>
          <?php if (empty($shop_status)): ?>
            <div class="empty">No order data.</div>
          <?php else: ?>
          <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px">
            <?php foreach ($shop_status as $so): ?>
            <div class="callout" style="min-width:80px">
              <div class="callout-lbl"><?= ucfirst(h($so['order_status'])) ?></div>
              <div class="callout-val" style="color:<?= $ord_clr[$so['order_status']]??'var(--text-mid)' ?>"><?= $so['cnt'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="bar-chart">
            <?php foreach ($shop_status as $so):
              $pct = round($so['cnt']/$ord_total*100);
              $bc  = ['pending'=>'bar-y','confirmed'=>'bar-g','shipped'=>'bar-s','completed'=>'bar-g','cancelled'=>'bar-r'];
              $col = $bc[$so['order_status']] ?? 'bar-v';
            ?>
            <div class="bar-row" style="grid-template-columns:90px 1fr 36px;gap:10px">
              <div class="bar-label"><?= ucfirst(h($so['order_status'])) ?></div>
              <div class="bar-track"><div class="bar-track-inner <?= $col ?>" data-width="<?= $pct ?>%"></div></div>
              <div class="bar-val"><?= $so['cnt'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Top products -->
      <div class="panel accent-green">
        <div class="panel-hdr">
          <div class="panel-title"><i class="fi fi-rr-box-alt"></i> Top Products Sold</div>
        </div>
        <div class="panel-body">
          <?php if (empty($top_products)): ?>
            <div class="empty">No sales data yet.</div>
          <?php else: ?>
          <div class="bar-chart">
            <?php
            $tp_cols=['bar-v','bar-g','bar-a','bar-y','bar-s','bar-r'];
            foreach ($top_products as $i => $tp):
              $pct = round((int)$tp['qty'] / $prod_max * 100);
            ?>
            <div class="bar-row cols-3" style="grid-template-columns:140px 1fr 80px">
              <div class="bar-label" title="<?= h($tp['product_name']) ?>"><?= h(mb_strimwidth($tp['product_name'],0,20,'…')) ?></div>
              <div class="bar-track"><div class="bar-track-inner <?= $tp_cols[$i%count($tp_cols)] ?>" data-width="<?= $pct ?>%"></div></div>
              <div class="bar-val" style="text-align:right">
                <span style="color:var(--text-high);font-size:0.58rem"><?= $tp['qty'] ?>×</span>
                <span style="color:var(--neon-green);font-size:0.56rem;display:block;margin-top:1px"><?= peso($tp['rev']) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /grid-2 -->

    <!-- ═══ SECTION: ADDITIONAL INSIGHTS ═══ -->
    <div class="section-label">
      <i class="fi fi-rr-sparkles" style="color:var(--prc-violet);font-size:.80rem"></i>
      Additional Insights
    </div>

    <div class="grid-3" style="margin-bottom:0">

      <!-- Member count histogram -->
      <div class="panel">
        <div class="panel-hdr">
          <div class="panel-title"><i class="fi fi-rr-users"></i> Team Size Distribution</div>
        </div>
        <div class="panel-body">
          <?php if (empty($member_hist)): ?>
            <div class="empty">No data.</div>
          <?php else:
            $mh_max = max(1,array_max(array_column($member_hist,'freq')));
          ?>
          <div class="bar-chart">
            <?php foreach ($member_hist as $mh):
              $pct = round($mh['freq']/$mh_max*100);
            ?>
            <div class="bar-row" style="grid-template-columns:80px 1fr 36px;gap:10px">
              <div class="bar-label"><?= $mh['member_count'] ?> member<?= $mh['member_count']!=1?'s':'' ?></div>
              <div class="bar-track"><div class="bar-track-inner bar-s" data-width="<?= $pct ?>%"></div></div>
              <div class="bar-val"><?= $mh['freq'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Registrant roles -->
      <div class="panel">
        <div class="panel-hdr">
          <div class="panel-title"><i class="fi fi-rr-badge"></i> Contact Roles</div>
        </div>
        <div class="panel-body">
          <?php if (empty($role_data)): ?>
            <div class="empty">No role data.</div>
          <?php else:
            $role_max = max(1,array_max(array_column($role_data,'cnt')));
          ?>
          <div class="bar-chart">
            <?php foreach ($role_data as $i => $rd):
              $pct = round($rd['cnt']/$role_max*100);
              $col = ['bar-v','bar-a','bar-g','bar-y','bar-s','bar-r'][$i%6];
            ?>
            <div class="bar-row" style="grid-template-columns:80px 1fr 36px;gap:10px">
              <div class="bar-label"><?= h(ucfirst($rd['role'])) ?></div>
              <div class="bar-track"><div class="bar-track-inner <?= $col ?>" data-width="<?= $pct ?>%"></div></div>
              <div class="bar-val"><?= $rd['cnt'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick summary stats -->
      <div class="panel">
        <div class="panel-hdr">
          <div class="panel-title"><i class="fi fi-rr-chart-histogram"></i> Quick Stats</div>
        </div>
        <div class="panel-body">
          <?php
          $avg_pkg = $total_regs>0 ? $db->query("SELECT AVG(package_amount) FROM prc_registrations WHERE reg_status='Active'")->fetchColumn() : 0;
          $max_pkg = $db->query("SELECT MAX(package_amount) FROM prc_registrations WHERE reg_status='Active'")->fetchColumn();
          $cancelled = (int)$db->query("SELECT COUNT(*) FROM prc_registrations WHERE reg_status='Cancelled'")->fetchColumn();
          $cancel_rate = ($total_regs+$cancelled)>0 ? round($cancelled/($total_regs+$cancelled)*100,1):0;
          $gcash_cnt = 0;
          foreach($pay_method as $pm) if($pm['payment_method']==='GCash') $gcash_cnt=$pm['cnt'];
          ?>
          <div style="display:flex;flex-direction:column;gap:0">
            <?php
            $stats = [
              ['Avg Package Value', peso((float)$avg_pkg), 'var(--creo-volt)'],
              ['Max Package Value', peso((float)$max_pkg), 'var(--neon-green)'],
              ['Cancellation Rate', $cancel_rate.'%', 'var(--admin-red)'],
              ['GCash Adoption', ($pay_total>0?round($gcash_cnt/$pay_total*100):0).'%', 'var(--prc-violet)'],
              ['Total Confirmed', $db->query("SELECT COUNT(*) FROM prc_registrations WHERE payment_status='Confirmed' AND reg_status='Active'")->fetchColumn(), 'var(--neon-green)'],
              ['Proof Pending', $db->query("SELECT COUNT(*) FROM prc_registrations WHERE payment_status='Proof Submitted' AND reg_status='Active'")->fetchColumn(), 'var(--creo-amber)'],
            ];
            foreach ($stats as $i => $st): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid rgba(139,126,255,<?= $i<count($stats)-1?.07:0 ?>)">
              <span style="font-family:var(--font-hud);font-size:0.50rem;color:var(--text-soft);letter-spacing:0.08em;text-transform:uppercase"><?= $st[0] ?></span>
              <span style="font-family:var(--font-hud);font-size:0.72rem;font-weight:700;color:<?= $st[2] ?>"><?= $st[1] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    </div><!-- /grid-3 -->

    <?php endif; // end !db_error ?>
  </main>

</div><!-- /admin-shell -->

<!-- SIDEBAR INJECTION -->
<script>
(function(){
  var slot = document.getElementById('sidebarSlot');
  if(!slot) return;
  fetch('admin-sidebar.html')
    .then(function(r){return r.text();})
    .then(function(html){
      slot.innerHTML=html;
      slot.querySelectorAll('script').forEach(function(old){
        var s=document.createElement('script');s.textContent=old.textContent;document.body.appendChild(s);
      });
    })
    .catch(function(){
      slot.innerHTML='<aside style="position:fixed;top:0;left:0;height:100vh;width:248px;background:#04031A;border-right:1px solid rgba(139,126,255,0.18);display:flex;align-items:center;justify-content:center;font-family:Orbitron,monospace;font-size:0.55rem;color:rgba(139,126,255,0.40);letter-spacing:0.12em;text-align:center;padding:20px;">SIDEBAR<br/>COMPONENT</aside>';
    });
})();
</script>

<!-- CHART DATA (PHP → JS) -->
<script>
var chartDailyData = <?= json_encode(array_map(fn($r)=>['label'=>$r['day'],'value'=>(int)$r['cnt']],$reg_daily??[])) ?>;
var chartRevData   = <?= json_encode(array_map(fn($r)=>['label'=>$r['day'],'value'=>(float)$r['total']],$rev_daily??[])) ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── TOPBAR DATE ──
(function(){
  var el=document.getElementById('topbar-date-display');
  function upd(){
    var d=new Date(),M=['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
    var h=d.getHours(),m=d.getMinutes(),ap=h>=12?'PM':'AM';h=h%12||12;
    el.innerHTML=M[d.getMonth()]+' '+String(d.getDate()).padStart(2,'0')+', '+d.getFullYear()+' &nbsp;<span>'+String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+' '+ap+'</span>';
  }
  upd();setInterval(upd,30000);
})();

// ── CUSTOM CURSOR ──
(function(){
  var dot=document.getElementById('cursorDot'),ring=document.getElementById('cursorRing');
  if(!dot||!ring)return;
  var mx=0,my=0,rx=0,ry=0;
  document.addEventListener('mousemove',function(e){mx=e.clientX;my=e.clientY;dot.style.left=mx+'px';dot.style.top=my+'px';});
  (function l(){rx+=(mx-rx)*.12;ry+=(my-ry)*.12;ring.style.left=rx+'px';ring.style.top=ry+'px';requestAnimationFrame(l);})();
  document.querySelectorAll('a,button,.panel,.kpi-cell').forEach(function(el){
    el.addEventListener('mouseenter',function(){ring.classList.add('hovered');});
    el.addEventListener('mouseleave',function(){ring.classList.remove('hovered');});
  });
})();

// ── SIDEBAR COLLAPSE ──
document.addEventListener('prc-sidebar-toggle',function(e){
  document.getElementById('adminShell').classList.toggle('sb-collapsed',e.detail.collapsed);
});
(function(){
  if(localStorage.getItem('prc_sidebar_collapsed')==='1')
    document.getElementById('adminShell').classList.add('sb-collapsed');
})();

// ── BAR ANIMATIONS (IntersectionObserver) ──
(function(){
  var bars=document.querySelectorAll('.bar-track-inner[data-width]');
  var ob=new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if(e.isIntersecting){e.target.style.width=e.target.getAttribute('data-width');ob.unobserve(e.target);}
    });
  },{threshold:0.1});
  bars.forEach(function(b){ob.observe(b);});
})();

// ── KPI COUNTERS ──
(function(){
  document.querySelectorAll('[data-count]').forEach(function(el){
    var ob=new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if(!e.isIntersecting||e.target._done)return;
        e.target._done=true;
        var target=parseInt(e.target.getAttribute('data-count'),10);
        var t0=Date.now(),dur=1100;
        (function loop(){
          var p=Math.min((Date.now()-t0)/dur,1);
          e.target.textContent=Math.floor((1-Math.pow(1-p,3))*target);
          if(p<1)requestAnimationFrame(loop);else e.target.textContent=target;
        })();
      });
    },{threshold:.5});
    ob.observe(el);
  });
})();

// ── CHART.JS DEFAULTS ──
Chart.defaults.color = '#9A90CC';
Chart.defaults.borderColor = 'rgba(139,126,255,0.10)';
Chart.defaults.font.family = "'Exo 2', sans-serif";

// ── DAILY REGISTRATIONS CHART ──
(function(){
  var ctx=document.getElementById('chartDailyRegs');
  if(!ctx||!chartDailyData||chartDailyData.length===0){
    if(ctx){ctx.parentNode.innerHTML='<div style="display:flex;align-items:center;justify-content:center;height:100%;font-family:Orbitron,monospace;font-size:.55rem;color:#6058A0;letter-spacing:.10em">NO DATA FOR LAST 30 DAYS</div>';}
    return;
  }
  var labels=chartDailyData.map(function(d){
    var p=d.label.split('-');return ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][parseInt(p[1])-1]+' '+parseInt(p[2]);
  });
  var values=chartDailyData.map(function(d){return d.value;});
  new Chart(ctx,{
    type:'line',
    data:{
      labels:labels,
      datasets:[{
        label:'Registrations',
        data:values,
        borderColor:'#8B7EFF',
        backgroundColor:'rgba(139,126,255,0.10)',
        borderWidth:1.5,
        fill:true,
        tension:0.4,
        pointBackgroundColor:'#8B7EFF',
        pointBorderColor:'#8B7EFF',
        pointRadius:3,
        pointHoverRadius:5,
      }]
    },
    options:{
      responsive:true,maintainAspectRatio:false,
      plugins:{legend:{display:false},tooltip:{backgroundColor:'rgba(6,5,26,0.96)',borderColor:'rgba(139,126,255,0.30)',borderWidth:1,titleFont:{family:'Orbitron,monospace',size:10},bodyFont:{size:12},padding:10,cornerRadius:0}},
      scales:{
        x:{ticks:{font:{size:9},maxTicksLimit:8},grid:{color:'rgba(139,126,255,0.06)'}},
        y:{ticks:{font:{size:9},stepSize:1},grid:{color:'rgba(139,126,255,0.06)'},min:0}
      }
    }
  });
})();

// ── REVENUE TIMELINE CHART ──
(function(){
  var ctx=document.getElementById('chartRevenue');
  if(!ctx||!chartRevData||chartRevData.length===0)return;
  var labels=chartRevData.map(function(d){
    var p=d.label.split('-');return ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][parseInt(p[1])-1]+' '+parseInt(p[2]);
  });
  var values=chartRevData.map(function(d){return d.value;});
  new Chart(ctx,{
    type:'bar',
    data:{
      labels:labels,
      datasets:[{
        label:'Revenue',
        data:values,
        backgroundColor:'rgba(68,255,136,0.25)',
        borderColor:'rgba(68,255,136,0.70)',
        borderWidth:1,
        borderRadius:2,
      }]
    },
    options:{
      responsive:true,maintainAspectRatio:false,
      plugins:{legend:{display:false},tooltip:{backgroundColor:'rgba(6,5,26,0.96)',borderColor:'rgba(68,255,136,0.30)',borderWidth:1,cornerRadius:0,callbacks:{label:function(c){return '₱ '+Number(c.raw).toLocaleString();}}}},
      scales:{
        x:{ticks:{font:{size:8},maxTicksLimit:6},grid:{display:false}},
        y:{ticks:{font:{size:8},callback:function(v){return '₱'+v.toLocaleString();}},grid:{color:'rgba(139,126,255,0.06)'},min:0}
      }
    }
  });
})();
</script>
</body>
</html>