<?php
// ═══════════════════════════════════════════════════════════
//  PRC Admin — Dashboard  (admin-dashboard.php)
//  Dynamic version — all data pulled from prc_db via PDO
// ═══════════════════════════════════════════════════════════

// ── DB CONFIG ─────────────────────────────────────────────
// Place your real credentials in a separate config file
// and require it here in production. e.g. require 'config.php';
define('DB_HOST', 'localhost');
define('DB_NAME', 'prc_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHAR', 'utf8mb4');

// ── PDO CONNECTION ────────────────────────────────────────
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

// ── HELPER: safe html output ──────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── HELPER: format money ──────────────────────────────────
function peso(float $v): string {
    return '₱ ' . number_format($v, 0);
}

// ── HELPER: relative time ────────────────────────────────
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff / 60) . 'm ago';
    if ($diff < 86400)   return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)  return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}

// ── HELPER: avatar initials ──────────────────────────────
function initials(string $name): string {
    $parts = preg_split('/[\s,]+/', trim($name));
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out ?: '??';
}

// ═══════════════════════════════════════════════════════════
//  QUERIES — All live from prc_db
// ═══════════════════════════════════════════════════════════

try {
    $db = db();

    // ── KPI 1: Total active registrations ────────────────
    $kpi_regs = (int) $db->query("
        SELECT COUNT(*) FROM prc_registrations WHERE reg_status = 'Active'
    ")->fetchColumn();

    // ── KPI 1b: Regs added this week ─────────────────────
    $kpi_regs_week = (int) $db->query("
        SELECT COUNT(*) FROM prc_registrations
        WHERE reg_status = 'Active'
          AND submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ")->fetchColumn();

    // ── KPI 2: Total shop orders ──────────────────────────
    $kpi_orders = (int) $db->query("
        SELECT COUNT(*) FROM prc_shop_orders
    ")->fetchColumn();

    // ── KPI 2b: Pending orders (not yet confirmed/shipped) 
    $kpi_orders_pending = (int) $db->query("
        SELECT COUNT(*) FROM prc_shop_orders WHERE order_status = 'pending'
    ")->fetchColumn();

    // ── KPI 2c: Total shop revenue ────────────────────────
    $kpi_shop_revenue = (float) $db->query("
        SELECT COALESCE(SUM(total_amount),0) FROM prc_shop_orders
        WHERE order_status IN ('confirmed','shipped','completed')
    ")->fetchColumn();

    // ── KPI 3: Active competition categories ─────────────
    $kpi_categories = (int) $db->query("
        SELECT COUNT(*) FROM prc_categories_subs WHERE is_active = 1
    ")->fetchColumn();

    // ── KPI 3b: Competition tracks ────────────────────────
    $kpi_tracks = (int) $db->query("
        SELECT COUNT(*) FROM prc_categories_tracks WHERE is_active = 1
    ")->fetchColumn();

    // ── KPI 4: Registrations needing action ──────────────
    //    (Proof Submitted but not yet Confirmed/Rejected)
    $kpi_pending_review = (int) $db->query("
        SELECT COUNT(*) FROM prc_registrations
        WHERE payment_status = 'Proof Submitted'
          AND reg_status = 'Active'
    ")->fetchColumn();

    // ── KPI 4b: Pending payment (no proof yet) ────────────
    $kpi_pending_payment = (int) $db->query("
        SELECT COUNT(*) FROM prc_registrations
        WHERE payment_status = 'Pending Payment'
          AND reg_status = 'Active'
    ")->fetchColumn();

    // ── Total confirmed revenue (registrations) ───────────
    $kpi_reg_revenue = (float) $db->query("
        SELECT COALESCE(SUM(package_amount),0) FROM prc_registrations
        WHERE payment_status = 'Confirmed' AND reg_status = 'Active'
    ")->fetchColumn();

    // ── RECENT REGISTRATIONS (last 7, with school + team) ─
    $stmt_regs = $db->query("
        SELECT
            r.reg_id,
            r.ref_no,
            r.contact_name,
            r.payment_status,
            r.reg_status,
            r.submitted_at,
            r.member_count,
            s.school_name,
            s.school_region,
            t.team_name,
            t.category,
            r.package_name
        FROM prc_registrations r
        JOIN prc_reg_schools   s ON r.school_id = s.school_id
        JOIN prc_reg_teams     t ON r.team_id   = t.team_id
        ORDER BY r.submitted_at DESC
        LIMIT 7
    ");
    $recent_regs = $stmt_regs->fetchAll();

    // ── REGISTRATION PIPELINE ─────────────────────────────
    $pipeline_submitted  = (int) $db->query("
        SELECT COUNT(*) FROM prc_registrations WHERE reg_status = 'Active'
    ")->fetchColumn();

    $pipeline_proof = (int) $db->query("
        SELECT COUNT(*) FROM prc_registrations
        WHERE reg_status = 'Active'
          AND payment_status IN ('Proof Submitted','Confirmed')
    ")->fetchColumn();

    $pipeline_confirmed = (int) $db->query("
        SELECT COUNT(*) FROM prc_registrations
        WHERE reg_status = 'Active' AND payment_status = 'Confirmed'
    ")->fetchColumn();

    $pipeline_cancelled = (int) $db->query("
        SELECT COUNT(*) FROM prc_registrations WHERE reg_status = 'Cancelled'
    ")->fetchColumn();

    // ── REGISTRATIONS BY TRACK (category) ────────────────
    $track_stats_raw = $db->query("
        SELECT t.category,
               COUNT(*) AS cnt
        FROM prc_reg_teams t
        JOIN prc_registrations r ON r.team_id = t.team_id
        WHERE r.reg_status = 'Active'
        GROUP BY t.category
        ORDER BY cnt DESC
    ")->fetchAll();

    // Map into broad track labels
    $track_map = [
        'RoboVenture' => 0, 'MakeX' => 0, 'Drone Soccer' => 0
    ];
    foreach ($track_stats_raw as $row) {
        $cat = $row['category'];
        if (str_contains($cat, 'Drone') || str_contains($cat, 'Robot Soccer')) {
            $track_map['Drone Soccer'] += $row['cnt'];
        } elseif (str_contains($cat, 'MakeX')) {
            $track_map['MakeX'] += $row['cnt'];
        } else {
            $track_map['RoboVenture'] += $row['cnt'];
        }
    }
    $track_total = max(1, array_sum($track_map));

    // ── REGISTRATIONS BY REGION ───────────────────────────
    $region_stats = $db->query("
        SELECT
            COALESCE(s.school_region, 'Unknown') AS region,
            COUNT(*) AS cnt
        FROM prc_registrations r
        JOIN prc_reg_schools s ON r.school_id = s.school_id
        WHERE r.reg_status = 'Active'
        GROUP BY s.school_region
        ORDER BY cnt DESC
        LIMIT 5
    ")->fetchAll();
    $region_max = max(1, (int)($region_stats[0]['cnt'] ?? 1));

    // ── RECENT SHOP ORDERS ────────────────────────────────
    $recent_orders = $db->query("
        SELECT
            o.order_id,
            o.order_ref,
            o.customer_name,
            o.total_amount,
            o.order_status,
            o.created_at,
            GROUP_CONCAT(oi.product_name ORDER BY oi.item_id SEPARATOR ', ') AS items
        FROM prc_shop_orders o
        LEFT JOIN prc_shop_order_items oi ON oi.order_id = o.order_id
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
        LIMIT 6
    ")->fetchAll();

    // ── ACTIVITY FEED (merged: registrations + orders) ───
    // Pull last 8 events across both tables, union-sorted
    $activity_items = $db->query("
        (
            SELECT
                'reg' AS type,
                CONCAT(contact_name, ' registered for ', package_name) AS text,
                submitted_at AS event_time,
                ref_no AS ref
            FROM prc_registrations
            WHERE reg_status = 'Active'
            ORDER BY submitted_at DESC
            LIMIT 5
        )
        UNION ALL
        (
            SELECT
                'order' AS type,
                CONCAT('Order ', order_ref, ' placed by ', customer_name) AS text,
                created_at AS event_time,
                order_ref AS ref
            FROM prc_shop_orders
            ORDER BY created_at DESC
            LIMIT 5
        )
        ORDER BY event_time DESC
        LIMIT 8
    ")->fetchAll();

    // ── PROOF-SUBMITTED QUEUE (action needed) ─────────────
    $proof_queue = $db->query("
        SELECT
            r.reg_id,
            r.ref_no,
            r.contact_name,
            r.contact_email,
            r.package_name,
            r.package_amount,
            r.proof_link,
            r.submitted_at,
            s.school_name,
            t.category
        FROM prc_registrations r
        JOIN prc_reg_schools   s ON r.school_id = s.school_id
        JOIN prc_reg_teams     t ON r.team_id   = t.team_id
        WHERE r.payment_status = 'Proof Submitted'
          AND r.reg_status = 'Active'
        ORDER BY r.submitted_at ASC
        LIMIT 5
    ")->fetchAll();

    // ── LOW-STOCK PRODUCTS ALERT ──────────────────────────
    $low_stock = $db->query("
        SELECT product_name, stock_status
        FROM prc_shop_products
        WHERE stock_status IN ('low-stock','out-of-stock')
          AND is_active = 1
    ")->fetchAll();

    // ── PAYMENT METHOD SPLIT ──────────────────────────────
    $pay_methods = $db->query("
        SELECT payment_method, COUNT(*) AS cnt
        FROM prc_registrations
        WHERE reg_status = 'Active'
        GROUP BY payment_method
    ")->fetchAll();
    $pay_total = max(1, array_sum(array_column($pay_methods, 'cnt')));

    // ── TRACK CATEGORY DISTRIBUTION (for donut) ──────────
    $track_pct = [];
    foreach ($track_map as $track => $cnt) {
        $track_pct[$track] = round($cnt / $track_total * 100);
    }

} catch (PDOException $e) {
    // In production: log this. Never expose PDO errors publicly.
    $db_error = 'Database connection error. Please check your DB config.';
    // You could also: error_log($e->getMessage());
}

// ── STATUS PILL HELPER ────────────────────────────────────
function paymentPill(string $status): string {
    $map = [
        'Confirmed'      => ['confirmed',  'Confirmed'],
        'Proof Submitted'=> ['processing', 'Proof Submitted'],
        'Pending Payment'=> ['pending',    'Pending'],
        'Rejected'       => ['cancelled',  'Rejected'],
    ];
    [$cls, $label] = $map[$status] ?? ['pending', $status];
    return '<span class="pill ' . $cls . '"><span class="pill-dot"></span>' . h($label) . '</span>';
}

// ── TRACK BADGE HELPER ────────────────────────────────────
function trackBadge(string $category): string {
    if (str_contains($category, 'Drone') || str_contains($category, 'Robot Soccer')) {
        return '<span class="track-badge track-ds">Drone Soccer</span>';
    }
    if (str_contains($category, 'MakeX')) {
        return '<span class="track-badge track-mx">MakeX</span>';
    }
    return '<span class="track-badge track-rv">RoboVenture</span>';
}

// ── ORDER STATUS PILL HELPER ─────────────────────────────
function orderPill(string $status): string {
    $map = [
        'confirmed' => 'confirmed',
        'shipped'   => 'confirmed',
        'completed' => 'confirmed',
        'pending'   => 'pending',
        'cancelled' => 'cancelled',
    ];
    $cls = $map[$status] ?? 'pending';
    return '<span class="pill ' . $cls . '"><span class="pill-dot"></span>' . h(ucfirst($status)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PRC Admin — Dashboard</title>
  <link rel="icon" type="image/png" href="assets/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css'>

  <style>
    /* ═══════════════════════════════════════════════
       PRC ADMIN DASHBOARD — Global + Layout
    ═══════════════════════════════════════════════ */
    :root {
      --sb-width:        248px;
      --sb-collapsed:    68px;
      --topbar-h:        60px;
      --bg-void:         #03020D;
      --bg-deep:         #06051A;
      --bg-card:         rgba(10,8,30,0.80);
      --prc-violet:      #8B7EFF;
      --prc-ice:         #C4EEFF;
      --creo-purple:     #7733FF;
      --creo-amber:      #FFA030;
      --creo-volt:       #FFE930;
      --neon-green:      #44FF88;
      --border-neon:     rgba(139,126,255,0.18);
      --border-hot:      rgba(139,126,255,0.45);
      --text-high:       #F2EEFF;
      --text-mid:        #C8C0F0;
      --text-soft:       #9A90CC;
      --text-dim:        #6058A0;
      --font-hud:        'Orbitron', monospace;
      --font-body:       'Exo 2', sans-serif;
      --radius:          3px;
      --transition:      all 0.25s ease;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: var(--font-body);
      background: var(--bg-void);
      color: var(--text-high);
      overflow-x: hidden;
      line-height: 1.6;
      min-height: 100vh;
    }
    img { max-width: 100%; display: block; }
    a { text-decoration: none; color: inherit; }
    ul { list-style: none; }
    button { font-family: inherit; border: none; background: none; cursor: pointer; }
    input, select, textarea { font-family: inherit; }

    /* ── GRID BG ── */
    body::before {
      content: '';
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background-image:
        linear-gradient(rgba(139,126,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(139,126,255,0.03) 1px, transparent 1px);
      background-size: 44px 44px;
    }

    /* ── SCANLINES ── */
    body::after {
      content: '';
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background: repeating-linear-gradient(
        to bottom, transparent, transparent 2px,
        rgba(0,0,0,0.025) 2px, rgba(0,0,0,0.025) 4px
      );
    }

    /* ═══ LAYOUT ═══ */
    .admin-shell {
      display: grid;
      grid-template-columns: var(--sb-width) 1fr;
      grid-template-rows: var(--topbar-h) 1fr;
      min-height: 100vh;
      position: relative; z-index: 1;
      transition: grid-template-columns 0.30s cubic-bezier(0.77,0,0.175,1);
    }
    .admin-shell.sb-collapsed { grid-template-columns: var(--sb-collapsed) 1fr; }

    .admin-sidebar-slot { grid-row: 1 / -1; grid-column: 1; }

    /* ── TOP BAR ── */
    .admin-topbar {
      grid-column: 2; grid-row: 1;
      height: var(--topbar-h);
      background: rgba(3,2,13,0.92);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border-neon);
      display: flex; align-items: center;
      padding: 0 28px; gap: 16px;
      position: sticky; top: 0; z-index: 800;
      box-shadow: 0 1px 30px rgba(139,126,255,0.07);
    }
    .topbar-breadcrumb {
      display: flex; align-items: center; gap: 8px;
      font-family: var(--font-hud); font-size: 0.60rem;
      color: var(--text-dim); letter-spacing: 0.10em; text-transform: uppercase;
    }
    .topbar-breadcrumb span { color: var(--prc-violet); }
    .topbar-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
    .topbar-search {
      display: flex; align-items: center; gap: 8px;
      background: rgba(139,126,255,0.06);
      border: 1px solid var(--border-neon);
      padding: 6px 14px; border-radius: var(--radius);
      transition: var(--transition);
    }
    .topbar-search:focus-within { border-color: var(--prc-violet); box-shadow: 0 0 14px rgba(139,126,255,0.22); }
    .topbar-search input {
      background: none; border: none; outline: none;
      font-family: var(--font-body); font-size: 0.80rem;
      color: var(--text-mid); width: 180px;
    }
    .topbar-search input::placeholder { color: var(--text-dim); }
    .topbar-search svg { width: 14px; height: 14px; stroke: var(--text-dim); flex-shrink: 0; }
    .topbar-icon-btn {
      width: 36px; height: 36px;
      background: rgba(139,126,255,0.05);
      border: 1px solid var(--border-neon) !important;
      border-radius: var(--radius);
      display: flex; align-items: center; justify-content: center;
      color: var(--text-soft); position: relative;
      transition: var(--transition);
    }
    .topbar-icon-btn svg { width: 16px; height: 16px; stroke: currentColor; }
    .topbar-icon-btn:hover { background: rgba(139,126,255,0.14); color: var(--prc-violet); border-color: var(--prc-violet) !important; }
    .topbar-badge {
      position: absolute; top: -4px; right: -4px;
      width: 16px; height: 16px; border-radius: 50%;
      background: var(--creo-amber); font-family: var(--font-hud);
      font-size: 0.42rem; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      color: var(--bg-void); border: 1px solid var(--bg-void);
    }
    .topbar-date { font-family: var(--font-hud); font-size: 0.52rem; color: var(--text-dim); letter-spacing: 0.10em; white-space: nowrap; }
    .topbar-date span { color: var(--creo-volt); }

    /* ── MAIN ── */
    .admin-main {
      grid-column: 2; grid-row: 2;
      padding: 28px 28px 48px;
      overflow-y: auto;
      min-height: calc(100vh - var(--topbar-h));
    }

    /* ═══ PAGE HEADER ═══ */
    .page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-bottom: 28px; }
    .page-eyebrow { font-family: var(--font-hud); font-size: 0.52rem; letter-spacing: 0.20em; text-transform: uppercase; color: var(--prc-ice); margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
    .page-eyebrow::before { content: '//'; color: rgba(139,126,255,0.38); }
    .page-title { font-family: var(--font-hud); font-size: 1.55rem; font-weight: 800; color: #fff; letter-spacing: -0.01em; text-shadow: 0 0 30px rgba(139,126,255,0.18); }
    .page-title .accent { color: var(--prc-violet); text-shadow: 0 0 14px rgba(139,126,255,0.60); }
    .page-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

    /* ═══ BUTTONS ═══ */
    .btn { display: inline-flex; align-items: center; gap: 8px; font-family: var(--font-hud); font-size: 0.60rem; font-weight: 700; letter-spacing: 0.10em; text-transform: uppercase; padding: 9px 20px; border-radius: var(--radius); transition: var(--transition); white-space: nowrap; }
    .btn svg { width: 14px; height: 14px; stroke: currentColor; }
    .btn-primary { background: rgba(139,126,255,0.14); border: 1px solid var(--prc-violet) !important; color: var(--prc-violet); clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%); box-shadow: 0 0 18px rgba(139,126,255,0.22), inset 0 0 18px rgba(139,126,255,0.06); }
    .btn-primary:hover { background: rgba(139,126,255,0.26); color: #fff; box-shadow: 0 0 30px rgba(139,126,255,0.45); }
    .btn-sky { background: rgba(139,126,255,0.10); border: 1px solid rgba(139,126,255,0.50) !important; color: var(--prc-violet); clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%); }
    .btn-sky:hover { background: rgba(139,126,255,0.22); color: #fff; }
    .btn-ghost { background: rgba(139,126,255,0.04); border: 1px solid var(--border-neon) !important; color: var(--text-soft); }
    .btn-ghost:hover { background: rgba(139,126,255,0.10); color: var(--text-mid); }

    /* ═══ ALERT BANNER ═══ */
    .alert-bar {
      background: rgba(255,160,48,0.07);
      border: 1px solid rgba(255,160,48,0.25);
      border-left: 3px solid var(--creo-amber);
      padding: 12px 18px;
      margin-bottom: 20px;
      display: flex; align-items: center; gap: 14px;
      font-size: 0.82rem; color: var(--creo-amber);
    }
    .alert-bar svg { width: 16px; height: 16px; stroke: currentColor; flex-shrink: 0; }
    .alert-bar strong { font-weight: 700; }
    .alert-bar span { color: var(--text-mid); }
    .alert-bar a { color: var(--creo-amber); text-decoration: underline; }
    .alert-bar.danger { background: rgba(255,80,80,0.07); border-color: rgba(255,80,80,0.25); border-left-color: #FF5050; color: #FF5050; }
    .alert-bar.danger svg { stroke: #FF5050; }

    /* ═══ KPI CARDS ═══ */
    .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
    .kpi-card { background: var(--bg-card); border: 1px solid var(--border-neon); padding: 20px 22px; position: relative; overflow: hidden; transition: border-color 0.25s, box-shadow 0.25s; clip-path: polygon(0 0, calc(100% - 10px) 0, 100% 10px, 100% 100%, 10px 100%, 0 calc(100% - 10px)); }
    .kpi-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px; }
    .kpi-card.violet::before { background: linear-gradient(90deg, transparent, var(--prc-violet), transparent); }
    .kpi-card.sky::before    { background: linear-gradient(90deg, transparent, var(--creo-amber), transparent); }
    .kpi-card.volt::before   { background: linear-gradient(90deg, transparent, var(--creo-volt), transparent); }
    .kpi-card.amber::before  { background: linear-gradient(90deg, transparent, var(--creo-amber), transparent); }
    .kpi-card:hover { border-color: rgba(139,126,255,0.38); box-shadow: 0 0 24px rgba(139,126,255,0.12); }
    .kpi-card.sky:hover   { box-shadow: 0 0 24px rgba(255,160,48,0.14); border-color: rgba(255,160,48,0.38); }
    .kpi-card.volt:hover  { box-shadow: 0 0 24px rgba(255,233,48,0.14); border-color: rgba(255,233,48,0.35); }
    .kpi-card.amber:hover { box-shadow: 0 0 24px rgba(255,160,48,0.14); border-color: rgba(255,160,48,0.35); }
    .kpi-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px; }
    .kpi-icon { width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border: 1px solid; }
    .kpi-icon svg { width: 18px; height: 18px; stroke: currentColor; }
    .kpi-card.violet .kpi-icon { color: var(--prc-violet); border-color: rgba(139,126,255,0.30); background: rgba(139,126,255,0.10); }
    .kpi-card.sky    .kpi-icon { color: var(--creo-amber); border-color: rgba(255,160,48,0.30); background: rgba(255,160,48,0.10); }
    .kpi-card.volt   .kpi-icon { color: var(--creo-volt); border-color: rgba(255,233,48,0.30); background: rgba(255,233,48,0.08); }
    .kpi-card.amber  .kpi-icon { color: var(--creo-amber); border-color: rgba(255,160,48,0.30); background: rgba(255,160,48,0.10); }
    .kpi-delta { font-family: var(--font-hud); font-size: 0.48rem; font-weight: 700; letter-spacing: 0.10em; padding: 3px 8px; clip-path: polygon(3px 0%, 100% 0%, calc(100% - 3px) 100%, 0% 100%); }
    .kpi-delta.up   { background: rgba(68,255,136,0.12); color: #44FF88; border: 1px solid rgba(68,255,136,0.25); }
    .kpi-delta.warn { background: rgba(255,233,48,0.10); color: var(--creo-volt); border: 1px solid rgba(255,233,48,0.25); }
    .kpi-delta.down { background: rgba(255,80,80,0.12); color: #FF5050; border: 1px solid rgba(255,80,80,0.25); }
    .kpi-num { font-family: var(--font-hud); font-size: 2.0rem; font-weight: 800; line-height: 1; display: block; margin-bottom: 4px; }
    .kpi-card.violet .kpi-num { color: var(--prc-violet); text-shadow: 0 0 20px rgba(139,126,255,0.55); }
    .kpi-card.sky    .kpi-num { color: var(--creo-amber); text-shadow: 0 0 20px rgba(255,160,48,0.55); }
    .kpi-card.volt   .kpi-num { color: var(--creo-volt); text-shadow: 0 0 20px rgba(255,233,48,0.55); }
    .kpi-card.amber  .kpi-num { color: var(--creo-amber); text-shadow: 0 0 20px rgba(255,160,48,0.55); }
    .kpi-label { font-family: var(--font-hud); font-size: 0.52rem; color: var(--text-soft); letter-spacing: 0.10em; text-transform: uppercase; }
    .kpi-sub { font-size: 0.75rem; color: var(--text-dim); margin-top: 6px; }

    /* ═══ CONTENT GRID ═══ */
    .content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 20px; }
    .content-grid.wide { grid-template-columns: 2fr 1fr; }
    .section-gap { margin-bottom: 20px; }

    /* ═══ PANEL ═══ */
    .panel { background: var(--bg-card); border: 1px solid var(--border-neon); position: relative; overflow: hidden; }
    .panel::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, var(--prc-violet), transparent); opacity: 0.40; }
    .panel-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border-neon); background: rgba(139,126,255,0.025); }
    .panel-title { font-family: var(--font-hud); font-size: 0.65rem; font-weight: 700; letter-spacing: 0.10em; text-transform: uppercase; color: var(--prc-ice); display: flex; align-items: center; gap: 8px; }
    .panel-title svg { width: 14px; height: 14px; stroke: var(--prc-violet); }
    .panel-body { padding: 18px 20px; }
    .panel-action { font-family: var(--font-hud); font-size: 0.50rem; font-weight: 600; letter-spacing: 0.10em; text-transform: uppercase; color: var(--prc-violet); border: 1px solid rgba(139,126,255,0.28) !important; background: rgba(139,126,255,0.06); padding: 5px 12px; border-radius: 2px; cursor: pointer; transition: var(--transition); }
    .panel-action:hover { background: rgba(139,126,255,0.16); }

    /* ═══ TABLE ═══ */
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { font-family: var(--font-hud); font-size: 0.48rem; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: var(--text-dim); padding: 10px 12px; border-bottom: 1px solid var(--border-neon); text-align: left; white-space: nowrap; }
    .data-table td { font-size: 0.82rem; color: var(--text-mid); padding: 11px 12px; border-bottom: 1px solid rgba(139,126,255,0.07); vertical-align: middle; }
    .data-table tbody tr { transition: background 0.15s; }
    .data-table tbody tr:hover { background: rgba(139,126,255,0.05); }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .td-team { display: flex; align-items: center; gap: 10px; }
    .td-avatar { width: 28px; height: 28px; border-radius: 2px; background: rgba(139,126,255,0.14); border: 1px solid rgba(139,126,255,0.22); display: flex; align-items: center; justify-content: center; font-family: var(--font-hud); font-size: 0.48rem; font-weight: 700; color: var(--prc-violet); flex-shrink: 0; }
    .td-name { font-weight: 600; color: var(--text-high); }
    .td-sub  { font-size: 0.72rem; color: var(--text-dim); }

    /* ═══ STATUS PILLS ═══ */
    .pill { display: inline-flex; align-items: center; gap: 5px; font-family: var(--font-hud); font-size: 0.48rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; padding: 3px 10px; clip-path: polygon(3px 0%, 100% 0%, calc(100% - 3px) 100%, 0% 100%); white-space: nowrap; }
    .pill-dot { width: 5px; height: 5px; border-radius: 50%; }
    .pill.confirmed  { background: rgba(68,255,136,0.10); color: #44FF88; border: 1px solid rgba(68,255,136,0.25); }
    .pill.confirmed  .pill-dot { background: #44FF88; box-shadow: 0 0 6px rgba(68,255,136,0.70); }
    .pill.pending    { background: rgba(255,233,48,0.10); color: var(--creo-volt); border: 1px solid rgba(255,233,48,0.25); }
    .pill.pending    .pill-dot { background: var(--creo-volt); box-shadow: 0 0 6px rgba(255,233,48,0.70); }
    .pill.processing { background: rgba(255,160,48,0.10); color: var(--creo-amber); border: 1px solid rgba(255,160,48,0.25); }
    .pill.processing .pill-dot { background: var(--creo-amber); box-shadow: 0 0 6px rgba(255,160,48,0.70); }
    .pill.cancelled  { background: rgba(255,80,80,0.10); color: #FF5050; border: 1px solid rgba(255,80,80,0.25); }
    .pill.cancelled  .pill-dot { background: #FF5050; box-shadow: 0 0 6px rgba(255,80,80,0.70); }

    /* ═══ TRACK BADGE ═══ */
    .track-badge { font-family: var(--font-hud); font-size: 0.46rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; padding: 2px 9px; border-radius: 2px; }
    .track-rv  { background: rgba(139,126,255,0.12); color: var(--prc-violet); border: 1px solid rgba(139,126,255,0.25); }
    .track-mx  { background: rgba(255,233,48,0.08);  color: var(--creo-volt);  border: 1px solid rgba(255,233,48,0.22); }
    .track-ds  { background: rgba(68,255,136,0.08);  color: var(--neon-green); border: 1px solid rgba(68,255,136,0.22); }

    /* ═══ TABLE ACTION BTNS ═══ */
    .tbl-btn { width: 28px; height: 28px; background: rgba(139,126,255,0.05); border: 1px solid var(--border-neon) !important; border-radius: 2px; display: inline-flex; align-items: center; justify-content: center; color: var(--text-dim); transition: var(--transition); }
    .tbl-btn svg { width: 12px; height: 12px; stroke: currentColor; }
    .tbl-btn:hover { background: rgba(139,126,255,0.16); color: var(--prc-violet); border-color: var(--prc-violet) !important; }
    .tbl-btn.green:hover { background: rgba(68,255,136,0.12); color: var(--neon-green); border-color: var(--neon-green) !important; }
    .tbl-btn.red:hover   { background: rgba(255,80,80,0.12);  color: #FF5050; border-color: #FF5050 !important; }
    .tbl-actions { display: flex; gap: 4px; }

    /* ═══ ACTIVITY FEED ═══ */
    .activity-list { display: flex; flex-direction: column; gap: 0; }
    .activity-item { display: flex; align-items: flex-start; gap: 12px; padding: 13px 0; border-bottom: 1px solid rgba(139,126,255,0.07); }
    .activity-item:last-child { border-bottom: none; padding-bottom: 0; }
    .activity-dot-col { display: flex; flex-direction: column; align-items: center; gap: 0; padding-top: 3px; }
    .activity-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
    .activity-dot.reg   { background: var(--prc-violet); box-shadow: 0 0 8px rgba(139,126,255,0.70); }
    .activity-dot.order { background: var(--creo-amber); box-shadow: 0 0 8px rgba(255,160,48,0.70); }
    .activity-line { width: 1px; flex: 1; background: rgba(139,126,255,0.12); margin-top: 6px; min-height: 18px; }
    .activity-item:last-child .activity-line { display: none; }
    .activity-content { flex: 1; }
    .activity-text { font-size: 0.82rem; color: var(--text-mid); line-height: 1.5; }
    .activity-text strong { color: var(--text-high); font-weight: 600; }
    .activity-time { font-family: var(--font-hud); font-size: 0.46rem; color: var(--text-dim); letter-spacing: 0.08em; margin-top: 3px; }

    /* ═══ BAR CHART ═══ */
    .bar-chart { display: flex; flex-direction: column; gap: 10px; }
    .bar-row { display: grid; grid-template-columns: 110px 1fr 50px; align-items: center; gap: 10px; }
    .bar-label { font-family: var(--font-hud); font-size: 0.50rem; color: var(--text-soft); letter-spacing: 0.06em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bar-track { height: 8px; background: rgba(139,126,255,0.08); border-radius: 2px; overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 2px; transition: width 1s cubic-bezier(0.77,0,0.175,1); width: 0; }
    .bar-fill.violet { background: linear-gradient(90deg, var(--creo-purple), var(--prc-violet)); box-shadow: 0 0 8px rgba(139,126,255,0.40); }
    .bar-fill.sky    { background: linear-gradient(90deg, #BBAA00, var(--creo-volt)); box-shadow: 0 0 8px rgba(255,233,48,0.40); }
    .bar-fill.green  { background: linear-gradient(90deg, #22AA55, var(--neon-green)); box-shadow: 0 0 8px rgba(68,255,136,0.40); }
    .bar-fill.amber  { background: linear-gradient(90deg, #CC7020, var(--creo-amber)); box-shadow: 0 0 8px rgba(255,160,48,0.40); }
    .bar-val { font-family: var(--font-hud); font-size: 0.56rem; color: var(--text-soft); text-align: right; }

    /* ═══ PIPELINE ═══ */
    .pipeline { display: flex; gap: 0; }
    .pipeline-stage { flex: 1; text-align: center; position: relative; }
    .pipeline-stage::after { content: '▶'; position: absolute; right: -8px; top: 50%; transform: translateY(-50%); color: rgba(139,126,255,0.22); font-size: 0.70rem; z-index: 2; }
    .pipeline-stage:last-child::after { display: none; }
    .pipeline-num { font-family: var(--font-hud); font-size: 1.35rem; font-weight: 800; display: block; line-height: 1; }
    .pipeline-stage:nth-child(1) .pipeline-num { color: var(--creo-amber); text-shadow: 0 0 14px rgba(255,160,48,0.50); }
    .pipeline-stage:nth-child(2) .pipeline-num { color: var(--creo-volt); text-shadow: 0 0 14px rgba(255,233,48,0.50); }
    .pipeline-stage:nth-child(3) .pipeline-num { color: var(--neon-green); text-shadow: 0 0 14px rgba(68,255,136,0.50); }
    .pipeline-stage:nth-child(4) .pipeline-num { color: #FF5050; text-shadow: 0 0 14px rgba(255,80,80,0.50); }
    .pipeline-lbl { font-family: var(--font-hud); font-size: 0.46rem; color: var(--text-dim); letter-spacing: 0.10em; text-transform: uppercase; margin-top: 5px; }
    .pipeline-bar { height: 4px; margin: 10px 10px 0; border-radius: 2px; }
    .pipeline-stage:nth-child(1) .pipeline-bar { background: var(--creo-amber); }
    .pipeline-stage:nth-child(2) .pipeline-bar { background: var(--creo-volt); }
    .pipeline-stage:nth-child(3) .pipeline-bar { background: var(--neon-green); }
    .pipeline-stage:nth-child(4) .pipeline-bar { background: #FF5050; }

    /* ═══ ORDERS MINI ═══ */
    .orders-mini { display: flex; flex-direction: column; gap: 0; }
    .order-row { display: flex; align-items: center; gap: 10px; padding: 11px 0; border-bottom: 1px solid rgba(139,126,255,0.07); transition: background 0.15s; }
    .order-row:last-child { border-bottom: none; }
    .order-row:hover { background: rgba(139,126,255,0.04); margin: 0 -20px; padding: 11px 20px; }
    .order-id { font-family: var(--font-hud); font-size: 0.48rem; color: var(--text-dim); letter-spacing: 0.08em; width: 88px; flex-shrink: 0; }
    .order-name { flex: 1; font-size: 0.82rem; color: var(--text-mid); min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .order-amount { font-family: var(--font-hud); font-size: 0.60rem; font-weight: 700; color: var(--creo-volt); white-space: nowrap; }

    /* ═══ DONUT ═══ */
    .donut-wrap { display: flex; align-items: center; gap: 20px; }
    .donut-svg { width: 100px; height: 100px; flex-shrink: 0; }
    .donut-legend { display: flex; flex-direction: column; gap: 8px; }
    .donut-leg-item { display: flex; align-items: center; gap: 8px; }
    .donut-leg-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .donut-leg-label { font-size: 0.78rem; color: var(--text-mid); }
    .donut-leg-val { font-family: var(--font-hud); font-size: 0.58rem; color: var(--text-soft); margin-left: auto; padding-left: 14px; }

    /* ═══ PROOF QUEUE ═══ */
    .proof-queue { display: flex; flex-direction: column; gap: 0; }
    .proof-row { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid rgba(139,126,255,0.07); }
    .proof-row:last-child { border-bottom: none; }
    .proof-ref { font-family: var(--font-hud); font-size: 0.46rem; color: var(--text-dim); flex-shrink: 0; }
    .proof-info { flex: 1; min-width: 0; }
    .proof-name { font-size: 0.80rem; color: var(--text-high); font-weight: 600; }
    .proof-school { font-size: 0.70rem; color: var(--text-dim); }
    .proof-amount { font-family: var(--font-hud); font-size: 0.56rem; color: var(--creo-volt); flex-shrink: 0; }

    /* ═══ STAT MINI CARDS ═══ */
    .mini-stat-row { display: flex; gap: 10px; flex-wrap: wrap; }
    .mini-stat { flex: 1; min-width: 90px; background: rgba(139,126,255,0.05); border: 1px solid rgba(139,126,255,0.14); padding: 12px 14px; }
    .mini-stat-lbl { font-family: var(--font-hud); font-size: 0.46rem; color: var(--text-dim); letter-spacing: 0.14em; text-transform: uppercase; margin-bottom: 4px; }
    .mini-stat-val { font-family: var(--font-hud); font-size: 1.0rem; font-weight: 700; }

    /* ═══ EMPTY STATE ═══ */
    .empty-state { text-align: center; padding: 28px 20px; color: var(--text-dim); font-family: var(--font-hud); font-size: 0.55rem; letter-spacing: 0.10em; text-transform: uppercase; }

    /* ═══ SCROLLBAR ═══ */
    ::-webkit-scrollbar { width: 3px; height: 3px; }
    ::-webkit-scrollbar-track { background: var(--bg-void); }
    ::-webkit-scrollbar-thumb { background: rgba(139,126,255,0.30); border-radius: 2px; }

    /* ═══ RESPONSIVE ═══ */
    @media (max-width: 1280px) {
      .kpi-grid { grid-template-columns: repeat(2,1fr); }
      .content-grid.three { grid-template-columns: 1fr 1fr; }
      .content-grid.three > *:first-child { grid-column: 1/-1; }
    }
    @media (max-width: 1024px) {
      .content-grid, .content-grid.wide { grid-template-columns: 1fr; }
    }
    @media (max-width: 900px) {
      .admin-shell { grid-template-columns: 0 1fr; }
      .admin-sidebar-slot { display: none; }
      .admin-main { padding: 18px 16px 40px; }
      .topbar-search { display: none; }
    }
    @media (max-width: 600px) {
      .kpi-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    }

    /* ═══ ANIMATIONS ═══ */
    @keyframes fadeInUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
    .anim-1 { animation: fadeInUp 0.5s ease 0.05s both; }
    .anim-2 { animation: fadeInUp 0.5s ease 0.12s both; }
    .anim-3 { animation: fadeInUp 0.5s ease 0.19s both; }
    .anim-4 { animation: fadeInUp 0.5s ease 0.26s both; }
    .anim-5 { animation: fadeInUp 0.5s ease 0.33s both; }
  </style>
</head>
<body>

<div class="admin-shell" id="adminShell">

  <!-- ── SIDEBAR SLOT ── -->
  <div class="admin-sidebar-slot" id="sidebarSlot"></div>

  <!-- ── TOP BAR ── -->
  <header class="admin-topbar">
    <div class="topbar-breadcrumb">
      PRC Admin <i class="fi fi-rr-angle-right"></i> <span>Dashboard</span>
    </div>
    <div class="topbar-right">
      <div class="topbar-search">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="Search registrations, orders…" />
      </div>
      <button class="topbar-icon-btn" title="Notifications">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <?php if ($kpi_pending_review > 0): ?>
          <span class="topbar-badge"><?= min($kpi_pending_review, 99) ?></span>
        <?php endif; ?>
      </button>
      <button class="topbar-icon-btn" title="Export" onclick="window.print()">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      </button>
      <div class="topbar-date"><span id="topbar-date-display"></span></div>
    </div>
  </header>

  <!-- ── MAIN ── -->
  <main class="admin-main" id="adminMain">

    <?php if (isset($db_error)): ?>
    <!-- DB ERROR BANNER -->
    <div class="alert-bar danger" style="margin-bottom:24px;">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span><strong>DB Error:</strong> <?= h($db_error) ?></span>
    </div>
    <?php endif; ?>

    <?php if (!isset($db_error)): ?>

    <!-- ALERT: LOW STOCK -->
    <?php if (!empty($low_stock)): ?>
    <div class="alert-bar anim-1">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <div>
        <strong>Stock Alert</strong>
        <span> —
          <?php foreach ($low_stock as $i => $ls): ?>
            <?= h($ls['product_name']) ?> is <strong><?= h($ls['stock_status']) ?></strong><?= $i < count($low_stock)-1 ? ', ' : '' ?>
          <?php endforeach; ?>
        </span>
      </div>
    </div>
    <?php endif; ?>

    <!-- ALERT: PENDING PROOF REVIEW -->
    <?php if ($kpi_pending_review > 0): ?>
    <div class="alert-bar anim-1">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <div>
        <strong><?= $kpi_pending_review ?> proof<?= $kpi_pending_review > 1 ? 's' : '' ?> awaiting review</strong>
        <span> — payment screenshots submitted and need your confirmation. See the queue below.</span>
      </div>
    </div>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header anim-1">
      <div class="page-title-group">
        <div class="page-eyebrow">PRC 2026 // Control Panel</div>
        <h1 class="page-title">Admin <span class="accent">Dashboard</span></h1>
      </div>
      <div class="page-actions">
        <a href="?" class="btn btn-ghost">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.35-4.36"/></svg>
          Refresh
        </a>
        <a href="admin-analytics.php" class="btn btn-sky">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          Analytics
        </a>
        <a href="admin-registrations.php" class="btn btn-primary">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
          Manage Registrations
        </a>
      </div>
    </div>

    <!-- ══ KPI CARDS ══ -->
    <div class="kpi-grid anim-2">

      <!-- KPI 1: Registrations -->
      <div class="kpi-card violet">
        <div class="kpi-top">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <?php if ($kpi_regs_week > 0): ?>
            <span class="kpi-delta up">▲ +<?= $kpi_regs_week ?> this wk</span>
          <?php else: ?>
            <span class="kpi-delta warn">► No new</span>
          <?php endif; ?>
        </div>
        <span class="kpi-num" data-target="<?= $kpi_regs ?>">0</span>
        <div class="kpi-label">Active Registrations</div>
        <div class="kpi-sub">+<?= $kpi_regs_week ?> this week · <?= $kpi_pending_payment ?> pending payment</div>
      </div>

      <!-- KPI 2: Orders -->
      <div class="kpi-card sky">
        <div class="kpi-top">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          </div>
          <?php if ($kpi_orders_pending > 0): ?>
            <span class="kpi-delta warn">► <?= $kpi_orders_pending ?> Pending</span>
          <?php else: ?>
            <span class="kpi-delta up">▲ All clear</span>
          <?php endif; ?>
        </div>
        <span class="kpi-num" data-target="<?= $kpi_orders ?>">0</span>
        <div class="kpi-label">Shop Orders</div>
        <div class="kpi-sub"><?= peso($kpi_shop_revenue) ?> confirmed revenue</div>
      </div>

      <!-- KPI 3: Categories -->
      <div class="kpi-card volt">
        <div class="kpi-top">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          </div>
          <span class="kpi-delta up">▲ <?= $kpi_tracks ?> tracks</span>
        </div>
        <span class="kpi-num" data-target="<?= $kpi_categories ?>">0</span>
        <div class="kpi-label">Event Categories</div>
        <div class="kpi-sub"><?= $kpi_tracks ?> competition tracks active</div>
      </div>

      <!-- KPI 4: Action Queue -->
      <div class="kpi-card amber">
        <div class="kpi-top">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
          </div>
          <?php if ($kpi_pending_review > 0): ?>
            <span class="kpi-delta down">▼ Needs Action</span>
          <?php else: ?>
            <span class="kpi-delta up">▲ All reviewed</span>
          <?php endif; ?>
        </div>
        <span class="kpi-num" data-target="<?= $kpi_pending_review ?>">0</span>
        <div class="kpi-label">Proofs to Review</div>
        <div class="kpi-sub"><?= $kpi_pending_payment ?> still awaiting payment</div>
      </div>

    </div><!-- /kpi-grid -->

    <!-- ══ ROW 2: Recent Registrations + Activity ══ -->
    <div class="content-grid wide section-gap anim-3">

      <!-- RECENT REGISTRATIONS -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            Recent Registrations
          </div>
          <a href="admin-registrations.php" class="panel-action">View All →</a>
        </div>
        <div class="panel-body" style="padding:0;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Ref / Contact</th>
                <th>Track</th>
                <th>School</th>
                <th>Payment</th>
                <th>Date</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recent_regs)): ?>
                <tr><td colspan="6"><div class="empty-state">No registrations yet</div></td></tr>
              <?php else: ?>
              <?php foreach ($recent_regs as $reg): ?>
              <tr>
                <td>
                  <div class="td-team">
                    <div class="td-avatar"><?= h(initials($reg['contact_name'])) ?></div>
                    <div>
                      <div class="td-name"><?= h($reg['contact_name']) ?></div>
                      <div class="td-sub"><?= h($reg['ref_no']) ?> · <?= (int)$reg['member_count'] ?> member<?= $reg['member_count'] != 1 ? 's' : '' ?></div>
                    </div>
                  </div>
                </td>
                <td><?= trackBadge($reg['category']) ?></td>
                <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= h($reg['school_name']) ?>"><?= h($reg['school_name']) ?></td>
                <td><?= paymentPill($reg['payment_status']) ?></td>
                <td style="color:var(--text-dim);font-size:0.76rem;"><?= date('M j', strtotime($reg['submitted_at'])) ?></td>
                <td>
                  <div class="tbl-actions">
                    <a href="admin-registrations.php?view=<?= $reg['reg_id'] ?>" class="tbl-btn" title="View">
                      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </a>
                    <a href="admin-registrations.php?edit=<?= $reg['reg_id'] ?>" class="tbl-btn" title="Edit">
                      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ACTIVITY FEED -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Live Activity
          </div>
        </div>
        <div class="panel-body">
          <div class="activity-list">
            <?php if (empty($activity_items)): ?>
              <div class="empty-state">No activity yet</div>
            <?php else: ?>
            <?php foreach ($activity_items as $act): ?>
            <div class="activity-item">
              <div class="activity-dot-col">
                <div class="activity-dot <?= $act['type'] ?>"></div>
                <div class="activity-line"></div>
              </div>
              <div class="activity-content">
                <div class="activity-text"><?= h($act['text']) ?></div>
                <div class="activity-time"><?= timeAgo($act['event_time']) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div><!-- /row 2 -->

    <!-- ══ ROW 3: Pipeline + Region + Orders ══ -->
    <div class="content-grid anim-4" style="grid-template-columns:1fr 1fr 1fr;gap:18px;margin-bottom:20px;">

      <!-- REGISTRATION PIPELINE -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Registration Pipeline
          </div>
        </div>
        <div class="panel-body">
          <div class="pipeline">
            <div class="pipeline-stage">
              <span class="pipeline-num"><?= $pipeline_submitted ?></span>
              <div class="pipeline-lbl">Active</div>
              <div class="pipeline-bar"></div>
            </div>
            <div class="pipeline-stage">
              <span class="pipeline-num"><?= $pipeline_proof ?></span>
              <div class="pipeline-lbl">Proof / Paid</div>
              <div class="pipeline-bar"></div>
            </div>
            <div class="pipeline-stage">
              <span class="pipeline-num"><?= $pipeline_confirmed ?></span>
              <div class="pipeline-lbl">Confirmed</div>
              <div class="pipeline-bar"></div>
            </div>
            <div class="pipeline-stage">
              <span class="pipeline-num"><?= $pipeline_cancelled ?></span>
              <div class="pipeline-lbl">Cancelled</div>
              <div class="pipeline-bar"></div>
            </div>
          </div>

          <!-- Track breakdown bars -->
          <div style="margin-top:22px;">
            <div class="bar-chart">
              <?php foreach ($track_map as $track => $cnt):
                $pct = $track_total > 0 ? round($cnt / $track_total * 100) : 0;
                $colors = ['RoboVenture' => 'violet', 'MakeX' => 'sky', 'Drone Soccer' => 'green'];
                $color = $colors[$track] ?? 'violet';
              ?>
              <div class="bar-row">
                <div class="bar-label"><?= h($track) ?></div>
                <div class="bar-track"><div class="bar-fill <?= $color ?>" data-width="<?= $pct ?>%"></div></div>
                <div class="bar-val"><?= $cnt ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- REGION BREAKDOWN -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            Registrations by Region
          </div>
        </div>
        <div class="panel-body">
          <?php if (empty($region_stats)): ?>
            <div class="empty-state">No region data</div>
          <?php else: ?>
          <?php $bar_colors = ['violet','sky','amber','green','amber']; ?>
          <div class="bar-chart">
            <?php foreach ($region_stats as $i => $row):
              $pct = round($row['cnt'] / $region_max * 100);
              // Shorten region label
              $label = preg_replace('/\s*-\s*.+$/', '', $row['region']);
              $label = strlen($label) > 14 ? substr($label, 0, 12) . '…' : $label;
            ?>
            <div class="bar-row">
              <div class="bar-label" title="<?= h($row['region']) ?>"><?= h($label) ?></div>
              <div class="bar-track"><div class="bar-fill <?= $bar_colors[$i % count($bar_colors)] ?>" data-width="<?= $pct ?>%"></div></div>
              <div class="bar-val"><?= $row['cnt'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- RECENT ORDERS -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            Recent Orders
          </div>
          <a href="admin-shop.php" class="panel-action">View All →</a>
        </div>
        <div class="panel-body" style="padding-top:6px;padding-bottom:6px;">
          <?php if (empty($recent_orders)): ?>
            <div class="empty-state" style="padding:24px 0;">No orders yet</div>
          <?php else: ?>
          <div class="orders-mini">
            <?php foreach ($recent_orders as $ord): ?>
            <div class="order-row">
              <div class="order-id"><?= h($ord['order_ref']) ?></div>
              <div class="order-name" title="<?= h($ord['items'] ?? $ord['customer_name']) ?>"><?= h($ord['items'] ?? $ord['customer_name']) ?></div>
              <div><?= orderPill($ord['order_status']) ?></div>
              <div class="order-amount"><?= peso($ord['total_amount']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /row 3 -->

    <!-- ══ ROW 4: Distribution + Proof Queue ══ -->
    <div class="content-grid anim-5" style="grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px;">

      <!-- CATEGORY DISTRIBUTION -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
            Category Distribution
          </div>
        </div>
        <div class="panel-body">
          <?php
          // Compute donut segments dynamically
          $rv_cnt  = $track_map['RoboVenture'] ?? 0;
          $mx_cnt  = $track_map['MakeX'] ?? 0;
          $ds_cnt  = $track_map['Drone Soccer'] ?? 0;
          $total_t = max(1, $rv_cnt + $mx_cnt + $ds_cnt);
          $rv_pct  = round($rv_cnt / $total_t * 100);
          $mx_pct  = round($mx_cnt / $total_t * 100);
          $ds_pct  = 100 - $rv_pct - $mx_pct;
          // SVG donut: circumference = 2πr = 2×π×38 ≈ 238.76
          $circ    = 238.76;
          $rv_dash = round($rv_pct / 100 * $circ, 2);
          $mx_dash = round($mx_pct / 100 * $circ, 2);
          $ds_dash = round($ds_pct / 100 * $circ, 2);
          $mx_off  = -$rv_dash;
          $ds_off  = -($rv_dash + $mx_dash);
          ?>
          <div class="donut-wrap">
            <svg class="donut-svg" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
              <circle cx="50" cy="50" r="38" fill="none" stroke="rgba(139,126,255,0.07)" stroke-width="14"/>
              <!-- RoboVenture -->
              <circle cx="50" cy="50" r="38" fill="none" stroke="#8B7EFF" stroke-width="14"
                stroke-dasharray="<?= $rv_dash ?> <?= $circ - $rv_dash ?>"
                stroke-dashoffset="0" stroke-linecap="butt"
                transform="rotate(-90 50 50)"
                style="filter:drop-shadow(0 0 3px rgba(139,126,255,0.60))"/>
              <!-- MakeX -->
              <circle cx="50" cy="50" r="38" fill="none" stroke="#FFA030" stroke-width="14"
                stroke-dasharray="<?= $mx_dash ?> <?= $circ - $mx_dash ?>"
                stroke-dashoffset="<?= $mx_off ?>" stroke-linecap="butt"
                transform="rotate(-90 50 50)"
                style="filter:drop-shadow(0 0 3px rgba(255,160,48,0.60))"/>
              <!-- Drone Soccer -->
              <circle cx="50" cy="50" r="38" fill="none" stroke="#44FF88" stroke-width="14"
                stroke-dasharray="<?= $ds_dash ?> <?= $circ - $ds_dash ?>"
                stroke-dashoffset="<?= $ds_off ?>" stroke-linecap="butt"
                transform="rotate(-90 50 50)"
                style="filter:drop-shadow(0 0 3px rgba(68,255,136,0.60))"/>
              <text x="50" y="47" text-anchor="middle" font-family="Orbitron,monospace" font-size="11" font-weight="700" fill="#F2EEFF"><?= $kpi_regs ?></text>
              <text x="50" y="57" text-anchor="middle" font-family="Exo 2,sans-serif" font-size="6" fill="#9A90CC" letter-spacing="0.5">ACTIVE</text>
            </svg>
            <div class="donut-legend">
              <div class="donut-leg-item">
                <div class="donut-leg-dot" style="background:#8B7EFF;box-shadow:0 0 6px rgba(139,126,255,0.60)"></div>
                <div class="donut-leg-label">RoboVenture</div>
                <div class="donut-leg-val"><?= $rv_pct ?>% · <?= $rv_cnt ?></div>
              </div>
              <div class="donut-leg-item">
                <div class="donut-leg-dot" style="background:#FFA030;box-shadow:0 0 6px rgba(255,160,48,0.60)"></div>
                <div class="donut-leg-label">MakeX</div>
                <div class="donut-leg-val"><?= $mx_pct ?>% · <?= $mx_cnt ?></div>
              </div>
              <div class="donut-leg-item">
                <div class="donut-leg-dot" style="background:#44FF88;box-shadow:0 0 6px rgba(68,255,136,0.60)"></div>
                <div class="donut-leg-label">Drone Soccer</div>
                <div class="donut-leg-val"><?= $ds_pct ?>% · <?= $ds_cnt ?></div>
              </div>
            </div>
          </div>

          <!-- Payment method / Revenue mini stats -->
          <div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(139,126,255,0.10);">
            <div class="mini-stat-row">
              <div class="mini-stat">
                <div class="mini-stat-lbl">Reg Revenue</div>
                <div class="mini-stat-val" style="color:var(--creo-volt);font-size:0.85rem;"><?= peso($kpi_reg_revenue) ?></div>
              </div>
              <?php foreach ($pay_methods as $pm): ?>
              <div class="mini-stat">
                <div class="mini-stat-lbl"><?= h($pm['payment_method']) ?></div>
                <div class="mini-stat-val" style="color:var(--prc-violet);"><?= round($pm['cnt'] / $pay_total * 100) ?>%</div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- PROOF REVIEW QUEUE -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            Proof Review Queue
          </div>
          <a href="admin-registrations.php?filter=proof_submitted" class="panel-action">View All →</a>
        </div>
        <div class="panel-body" style="padding-top:6px;padding-bottom:6px;">
          <?php if (empty($proof_queue)): ?>
            <div class="empty-state" style="padding:40px 0;">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--neon-green)" stroke-width="1.5" style="width:32px;height:32px;margin:0 auto 8px;display:block;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              All proofs reviewed!
            </div>
          <?php else: ?>
          <div class="proof-queue">
            <?php foreach ($proof_queue as $pq): ?>
            <div class="proof-row">
              <div class="proof-ref"><?= h($pq['ref_no']) ?></div>
              <div class="proof-info">
                <div class="proof-name"><?= h($pq['contact_name']) ?></div>
                <div class="proof-school"><?= h(mb_strimwidth($pq['school_name'], 0, 32, '…')) ?> · <?= h($pq['category']) ?></div>
              </div>
              <div class="proof-amount"><?= peso($pq['package_amount']) ?></div>
              <div class="tbl-actions">
                <?php if ($pq['proof_link']): ?>
                <a href="<?= h($pq['proof_link']) ?>" target="_blank" class="tbl-btn" title="View Proof">
                  <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                </a>
                <?php endif; ?>
                <a href="admin-registrations.php?confirm=<?= $pq['reg_id'] ?>" class="tbl-btn green" title="Confirm">
                  <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </a>
                <a href="admin-registrations.php?reject=<?= $pq['reg_id'] ?>" class="tbl-btn red" title="Reject">
                  <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /row 4 -->

    <?php endif; // end !db_error ?>

  </main>
</div>

<!-- SIDEBAR INJECTION -->
<script>
(function() {
  var slot = document.getElementById('sidebarSlot');
  if (!slot) return;
  fetch('admin-sidebar.html')
    .then(function(r) { return r.text(); })
    .then(function(html) {
      slot.innerHTML = html;
      slot.querySelectorAll('script').forEach(function(old) {
        var s = document.createElement('script');
        s.textContent = old.textContent;
        document.body.appendChild(s);
      });
    })
    .catch(function() {
      slot.innerHTML = '<aside style="position:fixed;top:0;left:0;height:100vh;width:248px;background:#04031A;border-right:1px solid rgba(139,126,255,0.18);display:flex;align-items:center;justify-content:center;font-family:Orbitron,monospace;font-size:0.55rem;color:rgba(139,126,255,0.40);letter-spacing:0.12em;text-align:center;padding:20px;">SIDEBAR<br/>COMPONENT<br/><span style="margin-top:8px;display:block;font-size:0.44rem;color:rgba(139,126,255,0.25)">admin-sidebar.html</span></aside>';
    });
})();
</script>

<script>
// ── TOPBAR DATE ──────────────────────────────────────────
(function() {
  var el = document.getElementById('topbar-date-display');
  if (!el) return;
  function updateDate() {
    var d = new Date();
    var months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
    var h = d.getHours(), m = d.getMinutes(), ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    el.innerHTML = months[d.getMonth()] + ' ' + String(d.getDate()).padStart(2,'0') + ', ' + d.getFullYear()
      + ' &nbsp;<span>' + String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ' ' + ampm + '</span>';
  }
  updateDate(); setInterval(updateDate, 30000);
})();

// ── KPI COUNTER ANIMATION ────────────────────────────────
(function() {
  var counters = document.querySelectorAll('.kpi-num[data-target]');
  var ob = new IntersectionObserver(function(entries) {
    entries.forEach(function(e) {
      if (!e.isIntersecting || e.target._done) return;
      e.target._done = true;
      var target = parseInt(e.target.getAttribute('data-target'), 10);
      var t0 = Date.now(), dur = 1200;
      (function loop() {
        var p = Math.min((Date.now() - t0) / dur, 1);
        e.target.textContent = Math.floor((1 - Math.pow(1 - p, 3)) * target);
        if (p < 1) requestAnimationFrame(loop);
        else e.target.textContent = target;
      })();
    });
  }, { threshold: 0.5 });
  counters.forEach(function(el) { ob.observe(el); });
})();

// ── BAR CHART ANIMATION ──────────────────────────────────
(function() {
  var bars = document.querySelectorAll('.bar-fill[data-width]');
  var ob = new IntersectionObserver(function(entries) {
    entries.forEach(function(e) {
      if (e.isIntersecting) {
        e.target.style.width = e.target.getAttribute('data-width');
        ob.unobserve(e.target);
      }
    });
  }, { threshold: 0.1 });
  bars.forEach(function(b) { ob.observe(b); });
})();

// ── SIDEBAR COLLAPSE SYNC ────────────────────────────────
document.addEventListener('prc-sidebar-toggle', function(e) {
  var shell = document.getElementById('adminShell');
  if (shell) shell.classList.toggle('sb-collapsed', e.detail.collapsed);
});
(function() {
  if (localStorage.getItem('prc_sidebar_collapsed') === '1') {
    var s = document.getElementById('adminShell');
    if (s) s.classList.add('sb-collapsed');
  }
})();
</script>

</body>
</html>