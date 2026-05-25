<?php
// ══════════════════════════════════════════════════════════════════
// admin-registrations.php — PRC 2026 Admin · Registrations
// Directly queries prc_db (MariaDB/MySQL via XAMPP)
// ══════════════════════════════════════════════════════════════════

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'prc_db');

// ── DB CONNECT ────────────────────────────────────────────────────
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    die('DB connection failed: ' . $db->connect_error);
}
$db->set_charset('utf8mb4');

// ── AJAX HANDLER (POST actions from JS) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $ref    = $db->real_escape_string($_POST['ref'] ?? '');

    if ($action === 'confirm' && $ref) {
        $db->query("UPDATE prc_registrations SET payment_status='Confirmed' WHERE ref_no='$ref'");
        echo json_encode(['ok' => true, 'affected' => $db->affected_rows]);
    } elseif ($action === 'reject' && $ref) {
        $db->query("UPDATE prc_registrations SET payment_status='Rejected', reg_status='Cancelled' WHERE ref_no='$ref'");
        echo json_encode(['ok' => true, 'affected' => $db->affected_rows]);
    } elseif ($action === 'cancel' && $ref) {
        $db->query("UPDATE prc_registrations SET reg_status='Cancelled' WHERE ref_no='$ref'");
        echo json_encode(['ok' => true, 'affected' => $db->affected_rows]);
    } elseif ($action === 'reactivate' && $ref) {
        $db->query("UPDATE prc_registrations SET reg_status='Active' WHERE ref_no='$ref'");
        echo json_encode(['ok' => true, 'affected' => $db->affected_rows]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
    $db->close();
    exit;
}

// ── READ FILTER PARAMS ───────────────────────────────────────────
$status_filter = $_GET['status'] ?? 'all';
$track_filter  = $_GET['track']  ?? 'all';
$search        = trim($_GET['search'] ?? '');
$sort_col      = $_GET['sort'] ?? 'submitted_at';
$sort_dir      = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$page          = max(1, intval($_GET['page'] ?? 1));
$per_page      = in_array(intval($_GET['pp'] ?? 25), [10, 25, 50]) ? intval($_GET['pp'] ?? 25) : 25;

// Whitelist sortable columns
$allowed_sort = [
    'ref_no'         => 'r.ref_no',
    'team_name'      => 't.team_name',
    'school_name'    => 's.school_name',
    'category'       => 't.category',
    'package_name'   => 'r.package_name',
    'payment_method' => 'r.payment_method',
    'package_amount' => 'r.package_amount',
    'payment_status' => 'r.payment_status',
    'submitted_at'   => 'r.submitted_at',
];
$sort_expr = $allowed_sort[$sort_col] ?? 'r.submitted_at';

// ── BUILD WHERE CLAUSE ────────────────────────────────────────────
$wheres = [];

if ($status_filter !== 'all') {
    $s = $db->real_escape_string($status_filter);
    $wheres[] = "r.payment_status = '$s'";
}

// Track filter maps category names to track groups
if ($track_filter !== 'all') {
    if ($track_filter === 'RoboVenture') {
        $wheres[] = "t.category NOT LIKE 'MakeX%' AND t.category != 'Drone Soccer'";
    } elseif ($track_filter === 'MakeX') {
        $wheres[] = "t.category LIKE 'MakeX%'";
    } elseif ($track_filter === 'Drone Soccer') {
        $wheres[] = "t.category = 'Drone Soccer'";
    }
}

if ($search !== '') {
    $sq = $db->real_escape_string($search);
    $wheres[] = "(r.ref_no LIKE '%$sq%' OR t.team_name LIKE '%$sq%' OR s.school_name LIKE '%$sq%'
                  OR r.contact_name LIKE '%$sq%' OR r.contact_email LIKE '%$sq%' OR t.category LIKE '%$sq%')";
}

$where_sql = count($wheres) ? 'WHERE ' . implode(' AND ', $wheres) : '';

// ── KPI COUNTS (always over all records) ─────────────────────────
$kpi = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(payment_status='Confirmed')      AS confirmed,
        SUM(payment_status='Proof Submitted') AS proof_sub,
        SUM(payment_status='Pending Payment') AS pending,
        SUM(payment_status='Rejected')        AS rejected,
        SUM(CASE WHEN payment_status IN ('Proof Submitted','Confirmed') THEN package_amount ELSE 0 END) AS revenue
    FROM prc_registrations r
")->fetch_assoc();

// ── COUNT FILTERED ────────────────────────────────────────────────
$count_sql = "
    SELECT COUNT(*) AS cnt
    FROM prc_registrations r
    JOIN prc_reg_schools s ON s.school_id = r.school_id
    JOIN prc_reg_teams   t ON t.team_id   = r.team_id
    $where_sql
";
$total_filtered = $db->query($count_sql)->fetch_assoc()['cnt'];
$total_pages    = max(1, ceil($total_filtered / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

// ── FETCH PAGE RECORDS ────────────────────────────────────────────
$sql = "
    SELECT
        r.reg_id, r.ref_no, r.submitted_at, r.updated_at,
        r.contact_name, r.contact_email, r.contact_phone, r.contact_role,
        r.package_name, r.package_amount, r.member_count,
        r.payment_method, r.payment_status, r.proof_link,
        r.reg_status, r.notes,
        s.school_name, s.school_region,
        t.team_name, t.category, t.team_id
    FROM prc_registrations r
    JOIN prc_reg_schools s ON s.school_id = r.school_id
    JOIN prc_reg_teams   t ON t.team_id   = r.team_id
    $where_sql
    ORDER BY $sort_expr $sort_dir
    LIMIT $per_page OFFSET $offset
";
$result  = $db->query($sql);
$records = [];
while ($row = $result->fetch_assoc()) { $records[] = $row; }

// ── FETCH PLAYERS for each team (for modal) ───────────────────────
$team_ids = array_unique(array_column($records, 'team_id'));
$players_map = [];
if ($team_ids) {
    $tid_str = implode(',', array_map('intval', $team_ids));
    $pr = $db->query("SELECT team_id, player_name, player_birthdate, player_grade FROM prc_reg_players WHERE team_id IN ($tid_str) ORDER BY player_id");
    while ($p = $pr->fetch_assoc()) {
        $players_map[$p['team_id']][] = $p;
    }
}

$db->close();

// ── HELPERS ───────────────────────────────────────────────────────
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function getTrack($cat) {
    if (str_starts_with($cat, 'MakeX'))  return 'MakeX';
    if ($cat === 'Drone Soccer')          return 'Drone Soccer';
    return 'RoboVenture';
}
function statusCls($s) {
    return match($s) {
        'Confirmed'       => 'confirmed',
        'Proof Submitted' => 'proof',
        'Pending Payment' => 'pending',
        'Rejected'        => 'cancelled',
        default           => 'processing'
    };
}
function trackCls($t) {
    return match($t) {
        'MakeX'       => 'track-mx',
        'Drone Soccer'=> 'track-ds',
        default       => 'track-rv'
    };
}
function payBadgeCls($m) {
    return match($m) {
        'GCash'         => 'pay-gcash',
        'Bank Transfer' => 'pay-bank',
        default         => 'pay-none'
    };
}
function sortLink($col, $label, $cur_col, $cur_dir, $params) {
    $new_dir = ($cur_col === $col && $cur_dir === 'DESC') ? 'ASC' : 'DESC';
    $arrow   = $cur_col === $col ? ($cur_dir === 'DESC' ? ' ↓' : ' ↑') : ' ↕';
    $active  = $cur_col === $col ? ' sorted' : '';
    $p       = array_merge($params, ['sort' => $col, 'dir' => $new_dir]);
    $url     = '?' . http_build_query($p);
    return "<th class=\"$active\" onclick=\"window.location='$url'\">$label<span class='sort-arrow'>$arrow</span></th>";
}

// Params for pagination / sort links
$link_params = [
    'status' => $status_filter,
    'track'  => $track_filter,
    'search' => $search,
    'sort'   => $sort_col,
    'dir'    => $sort_dir,
    'pp'     => $per_page,
];
?>
<!DOCTYPE html>
<!-- PRC-WebApp/admin-registrations.php -->
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PRC Admin — Registrations</title>
  <link rel="icon" type="image/png" href="assets/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css'>

  <style>
    :root {
      --sb-width:      248px;
      --sb-collapsed:  68px;
      --topbar-h:      60px;
      --bg-void:       #03020D;
      --bg-deep:       #06051A;
      --bg-card:       rgba(10,8,30,0.80);
      --prc-violet:    #8B7EFF;
      --prc-ice:       #C4EEFF;
      --creo-amber:    #FFA030;
      --creo-volt:     #FFE930;
      --neon-green:    #44FF88;
      --gcash-blue:    #007CFF;
      --border-neon:   rgba(139,126,255,0.18);
      --text-high:     #F2EEFF;
      --text-mid:      #C8C0F0;
      --text-soft:     #9A90CC;
      --text-dim:      #6058A0;
      --font-hud:      'Orbitron', monospace;
      --font-body:     'Exo 2', sans-serif;
      --radius:        3px;
      --transition:    all 0.25s ease;
    }
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    html { scroll-behavior:smooth; }
    body { font-family:var(--font-body); background:var(--bg-void); color:var(--text-high); overflow-x:hidden; line-height:1.6; min-height:100vh; }
    a { text-decoration:none; color:inherit; }
    ul { list-style:none; }
    button { font-family:inherit; border:none; background:none; cursor:pointer; }
    input, select { font-family:inherit; }

    body::before { content:''; position:fixed; inset:0; z-index:0; pointer-events:none;
      background-image:linear-gradient(rgba(139,126,255,0.03) 1px,transparent 1px),
                       linear-gradient(90deg,rgba(139,126,255,0.03) 1px,transparent 1px);
      background-size:44px 44px; }
    body::after { content:''; position:fixed; inset:0; z-index:0; pointer-events:none;
      background:repeating-linear-gradient(to bottom,transparent,transparent 2px,rgba(0,0,0,0.025) 2px,rgba(0,0,0,0.025) 4px); }

    /* ── LAYOUT ── */
    .admin-shell { display:grid; grid-template-columns:var(--sb-width) 1fr; grid-template-rows:var(--topbar-h) 1fr; min-height:100vh; position:relative; z-index:1; transition:grid-template-columns 0.30s cubic-bezier(0.77,0,0.175,1); }
    .admin-shell.sb-collapsed { grid-template-columns:var(--sb-collapsed) 1fr; }
    .admin-sidebar-slot { grid-row:1/-1; grid-column:1; }

    /* ── TOP BAR ── */
    .admin-topbar { grid-column:2; grid-row:1; height:var(--topbar-h); background:rgba(3,2,13,0.92); backdrop-filter:blur(20px); border-bottom:1px solid var(--border-neon); display:flex; align-items:center; padding:0 28px; gap:16px; position:sticky; top:0; z-index:800; }
    .topbar-breadcrumb { display:flex; align-items:center; gap:8px; font-family:var(--font-hud); font-size:0.60rem; color:var(--text-dim); letter-spacing:0.10em; text-transform:uppercase; }
    .topbar-breadcrumb a { color:var(--text-dim); transition:color 0.2s; }
    .topbar-breadcrumb a:hover { color:var(--prc-violet); }
    .topbar-breadcrumb span { color:var(--prc-violet); }
    .topbar-right { margin-left:auto; display:flex; align-items:center; gap:10px; }
    .topbar-search-form { display:flex; align-items:center; gap:8px; background:rgba(139,126,255,0.06); border:1px solid var(--border-neon); padding:6px 14px; border-radius:var(--radius); transition:var(--transition); }
    .topbar-search-form:focus-within { border-color:var(--prc-violet); box-shadow:0 0 14px rgba(139,126,255,0.22); }
    .topbar-search-form input { background:none; border:none; outline:none; font-family:var(--font-body); font-size:0.80rem; color:var(--text-mid); width:200px; }
    .topbar-search-form input::placeholder { color:var(--text-dim); }
    .topbar-search-form i { color:var(--text-dim); font-size:0.85rem; }
    .topbar-date { font-family:var(--font-hud); font-size:0.52rem; color:var(--text-dim); letter-spacing:0.10em; white-space:nowrap; }
    .topbar-date span { color:var(--creo-volt); }

    /* ── MAIN ── */
    .admin-main { grid-column:2; grid-row:2; padding:28px 28px 48px; overflow-y:auto; min-height:calc(100vh - var(--topbar-h)); }

    /* ── PAGE HEADER ── */
    .page-header { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; flex-wrap:wrap; margin-bottom:24px; }
    .page-eyebrow { font-family:var(--font-hud); font-size:0.52rem; letter-spacing:0.20em; text-transform:uppercase; color:var(--prc-ice); margin-bottom:6px; display:flex; align-items:center; gap:8px; }
    .page-eyebrow::before { content:'//'; color:rgba(139,126,255,0.38); }
    .page-title { font-family:var(--font-hud); font-size:1.55rem; font-weight:800; color:#fff; letter-spacing:-0.01em; text-shadow:0 0 30px rgba(139,126,255,0.18); }
    .page-title .accent { color:var(--prc-violet); text-shadow:0 0 14px rgba(139,126,255,0.60); }
    .page-actions { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }

    /* ── BUTTONS ── */
    .btn { display:inline-flex; align-items:center; gap:8px; font-family:var(--font-hud); font-size:0.60rem; font-weight:700; letter-spacing:0.10em; text-transform:uppercase; padding:9px 20px; border-radius:var(--radius); transition:var(--transition); white-space:nowrap; cursor:pointer; }
    .btn i { font-size:0.90rem; }
    .btn-primary { background:rgba(139,126,255,0.14); border:1px solid var(--prc-violet) !important; color:var(--prc-violet); clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%); box-shadow:0 0 18px rgba(139,126,255,0.22),inset 0 0 18px rgba(139,126,255,0.06); }
    .btn-primary:hover { background:rgba(139,126,255,0.26); color:#fff; box-shadow:0 0 30px rgba(139,126,255,0.45); }
    .btn-volt { background:rgba(255,233,48,0.08); border:1px solid rgba(255,233,48,0.35) !important; color:var(--creo-volt); clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%); }
    .btn-volt:hover { background:rgba(255,233,48,0.18); color:#fff; }
    .btn-ghost { background:rgba(139,126,255,0.04); border:1px solid var(--border-neon) !important; color:var(--text-soft); }
    .btn-ghost:hover { background:rgba(139,126,255,0.10); color:var(--text-mid); }
    .btn-sm { padding:6px 14px; font-size:0.54rem; }
    .btn-approve { background:rgba(68,255,136,0.10); border:1px solid rgba(68,255,136,0.35) !important; color:#44FF88; }
    .btn-approve:hover { background:rgba(68,255,136,0.22); color:#fff; }
    .btn-reject { background:rgba(255,80,80,0.08); border:1px solid rgba(255,80,80,0.30) !important; color:#FF5050; }
    .btn-reject:hover { background:rgba(255,80,80,0.20); color:#fff; }
    .btn-cancel-reg { background:rgba(255,160,48,0.08); border:1px solid rgba(255,160,48,0.28) !important; color:var(--creo-amber); }
    .btn-cancel-reg:hover { background:rgba(255,160,48,0.18); color:#fff; }

    /* ── KPI STRIP ── */
    .kpi-strip { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; margin-bottom:22px; }
    .kpi-mini { background:var(--bg-card); border:1px solid var(--border-neon); padding:14px 16px; position:relative; overflow:hidden; clip-path:polygon(0 0,calc(100% - 8px) 0,100% 8px,100% 100%,8px 100%,0 calc(100% - 8px)); }
    .kpi-mini::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; }
    .kpi-v::before { background:linear-gradient(90deg,transparent,var(--prc-violet),transparent); }
    .kpi-g::before { background:linear-gradient(90deg,transparent,#44FF88,transparent); }
    .kpi-a::before { background:linear-gradient(90deg,transparent,var(--creo-amber),transparent); }
    .kpi-y::before { background:linear-gradient(90deg,transparent,var(--creo-volt),transparent); }
    .kpi-r::before { background:linear-gradient(90deg,transparent,#FF5050,transparent); }
    .kpi-mini-num { font-family:var(--font-hud); font-size:1.65rem; font-weight:800; line-height:1; display:block; }
    .kpi-v .kpi-mini-num { color:var(--prc-violet); text-shadow:0 0 18px rgba(139,126,255,0.55); }
    .kpi-g .kpi-mini-num { color:#44FF88;            text-shadow:0 0 18px rgba(68,255,136,0.55); }
    .kpi-a .kpi-mini-num { color:var(--creo-amber);  text-shadow:0 0 18px rgba(255,160,48,0.55); }
    .kpi-y .kpi-mini-num { color:var(--creo-volt);   text-shadow:0 0 18px rgba(255,233,48,0.55); }
    .kpi-r .kpi-mini-num { color:#FF5050;             text-shadow:0 0 18px rgba(255,80,80,0.55); }
    .kpi-mini-label { font-family:var(--font-hud); font-size:0.48rem; color:var(--text-soft); letter-spacing:0.10em; text-transform:uppercase; margin-top:5px; }

    /* ── FILTER BAR ── */
    .filter-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:18px; padding:14px 18px; background:var(--bg-card); border:1px solid var(--border-neon); position:relative; overflow:hidden; }
    .filter-bar::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--prc-violet),transparent); opacity:0.35; }
    .filter-label { font-family:var(--font-hud); font-size:0.50rem; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:var(--text-dim); white-space:nowrap; }
    .filter-label::before { content:'//'; margin-right:6px; color:rgba(139,126,255,0.30); }
    .filter-group { display:flex; gap:6px; flex-wrap:wrap; }
    .filter-btn { font-family:var(--font-hud); font-size:0.54rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; padding:6px 14px; border:1px solid rgba(139,126,255,0.20); color:var(--text-soft); background:transparent; clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%); transition:var(--transition); cursor:pointer; text-decoration:none; display:inline-block; }
    .filter-btn:hover { border-color:var(--prc-violet); color:var(--prc-violet); background:rgba(139,126,255,0.08); }
    .filter-btn.active { border-color:var(--prc-violet); color:var(--prc-violet); background:rgba(139,126,255,0.14); box-shadow:0 0 12px rgba(139,126,255,0.22); }
    .filter-btn.active-g { border-color:#44FF88; color:#44FF88; background:rgba(68,255,136,0.10); }
    .filter-btn.active-a { border-color:var(--creo-amber); color:var(--creo-amber); background:rgba(255,160,48,0.10); }
    .filter-btn.active-y { border-color:var(--creo-volt); color:var(--creo-volt); background:rgba(255,233,48,0.08); }
    .filter-btn.active-r { border-color:#FF5050; color:#FF5050; background:rgba(255,80,80,0.08); }
    .filter-sep { width:1px; height:26px; background:rgba(139,126,255,0.14); margin:0 4px; flex-shrink:0; }
    .filter-right { margin-left:auto; display:flex; gap:8px; align-items:center; }
    .filter-select { background:rgba(139,126,255,0.06); border:1px solid rgba(139,126,255,0.22); color:var(--text-mid); font-family:var(--font-hud); font-size:0.52rem; letter-spacing:0.06em; padding:6px 12px; outline:none; cursor:pointer; }
    .filter-select:focus { border-color:var(--prc-violet); }
    .filter-select option { background:var(--bg-deep); color:var(--text-high); }

    /* ── PANEL ── */
    .panel { background:var(--bg-card); border:1px solid var(--border-neon); position:relative; overflow:hidden; }
    .panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--prc-violet),transparent); opacity:0.40; }
    .panel-header { display:flex; align-items:center; justify-content:space-between; padding:14px 20px; border-bottom:1px solid var(--border-neon); background:rgba(139,126,255,0.025); flex-wrap:wrap; gap:10px; }
    .panel-title { font-family:var(--font-hud); font-size:0.65rem; font-weight:700; letter-spacing:0.10em; text-transform:uppercase; color:var(--prc-ice); display:flex; align-items:center; gap:8px; }
    .panel-title i { color:var(--prc-violet); font-size:0.90rem; }
    .panel-meta { font-family:var(--font-hud); font-size:0.50rem; color:var(--text-dim); letter-spacing:0.08em; }
    .panel-meta span { color:var(--prc-violet); }

    /* ── TABLE ── */
    .table-wrap { overflow-x:auto; }
    .data-table { width:100%; border-collapse:collapse; min-width:980px; }
    .data-table th { font-family:var(--font-hud); font-size:0.48rem; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:var(--text-dim); padding:10px 14px; border-bottom:1px solid var(--border-neon); text-align:left; white-space:nowrap; cursor:pointer; user-select:none; transition:color 0.2s; }
    .data-table th:hover { color:var(--prc-violet); }
    .data-table th.sorted { color:var(--prc-violet); }
    .data-table th .sort-arrow { margin-left:4px; opacity:0.45; font-size:0.55rem; }
    .data-table th.sorted .sort-arrow { opacity:1; }
    .data-table td { font-size:0.82rem; color:var(--text-mid); padding:12px 14px; border-bottom:1px solid rgba(139,126,255,0.07); vertical-align:middle; }
    .data-table tbody tr { transition:background 0.15s; }
    .data-table tbody tr:hover { background:rgba(139,126,255,0.05); }
    .data-table tbody tr:last-child td { border-bottom:none; }
    .data-table tbody tr.row-cancelled td { opacity:0.50; }

    .td-team { display:flex; align-items:center; gap:10px; }
    .td-avatar { width:30px; height:30px; border-radius:2px; background:rgba(139,126,255,0.14); border:1px solid rgba(139,126,255,0.22); display:flex; align-items:center; justify-content:center; font-family:var(--font-hud); font-size:0.48rem; font-weight:700; color:var(--prc-violet); flex-shrink:0; }
    .td-name { font-weight:600; color:var(--text-high); }
    .td-sub  { font-size:0.72rem; color:var(--text-dim); margin-top:2px; }

    .pill { display:inline-flex; align-items:center; gap:5px; font-family:var(--font-hud); font-size:0.48rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; padding:3px 10px; clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%); white-space:nowrap; }
    .pill-dot { width:5px; height:5px; border-radius:50%; }
    .pill.confirmed  { background:rgba(68,255,136,0.10);  color:#44FF88;            border:1px solid rgba(68,255,136,0.25); }
    .pill.confirmed  .pill-dot { background:#44FF88;           box-shadow:0 0 6px rgba(68,255,136,0.70); }
    .pill.pending    { background:rgba(255,233,48,0.10);  color:var(--creo-volt);   border:1px solid rgba(255,233,48,0.25); }
    .pill.pending    .pill-dot { background:var(--creo-volt);   box-shadow:0 0 6px rgba(255,233,48,0.70); }
    .pill.processing { background:rgba(255,160,48,0.10);  color:var(--creo-amber);  border:1px solid rgba(255,160,48,0.25); }
    .pill.processing .pill-dot { background:var(--creo-amber);  box-shadow:0 0 6px rgba(255,160,48,0.70); }
    .pill.cancelled  { background:rgba(255,80,80,0.10);   color:#FF5050;            border:1px solid rgba(255,80,80,0.25); }
    .pill.cancelled  .pill-dot { background:#FF5050;           box-shadow:0 0 6px rgba(255,80,80,0.70); }
    .pill.proof      { background:rgba(68,217,255,0.10);  color:#44D9FF;            border:1px solid rgba(68,217,255,0.25); }
    .pill.proof      .pill-dot { background:#44D9FF;           box-shadow:0 0 6px rgba(68,217,255,0.70); }

    .track-badge { font-family:var(--font-hud); font-size:0.46rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; padding:2px 9px; border-radius:2px; }
    .track-rv { background:rgba(139,126,255,0.12); color:var(--prc-violet); border:1px solid rgba(139,126,255,0.25); }
    .track-mx { background:rgba(68,217,255,0.08);  color:#44D9FF;           border:1px solid rgba(68,217,255,0.22); }
    .track-ds { background:rgba(255,160,48,0.10);  color:var(--creo-amber); border:1px solid rgba(255,160,48,0.25); }

    .pay-badge { font-family:var(--font-hud); font-size:0.46rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; padding:2px 9px; border-radius:2px; }
    .pay-gcash { background:rgba(0,124,255,0.12); color:#007CFF; border:1px solid rgba(0,124,255,0.28); }
    .pay-bank  { background:rgba(255,160,48,0.10); color:var(--creo-amber); border:1px solid rgba(255,160,48,0.25); }
    .pay-none  { background:rgba(139,126,255,0.06); color:var(--text-dim); border:1px solid rgba(139,126,255,0.14); }

    .tbl-btn { width:28px; height:28px; background:rgba(139,126,255,0.05); border:1px solid var(--border-neon) !important; border-radius:2px; display:inline-flex; align-items:center; justify-content:center; color:var(--text-dim); transition:var(--transition); font-size:0.80rem; cursor:pointer; }
    .tbl-btn:hover { background:rgba(139,126,255,0.16); color:var(--prc-violet); border-color:var(--prc-violet) !important; }
    .tbl-btn.approve:hover { background:rgba(68,255,136,0.16); color:#44FF88; border-color:#44FF88 !important; }
    .tbl-btn.reject:hover  { background:rgba(255,80,80,0.14);  color:#FF5050; border-color:#FF5050 !important; }
    .tbl-actions { display:flex; gap:4px; }
    .ref-badge { font-family:var(--font-hud); font-size:0.52rem; font-weight:700; color:var(--creo-volt); letter-spacing:0.08em; }

    /* ── PAGINATION ── */
    .pagination { display:flex; align-items:center; justify-content:space-between; padding:14px 20px; border-top:1px solid var(--border-neon); flex-wrap:wrap; gap:10px; }
    .page-info { font-family:var(--font-hud); font-size:0.50rem; color:var(--text-dim); letter-spacing:0.08em; }
    .page-info span { color:var(--prc-violet); }
    .page-btns { display:flex; gap:4px; flex-wrap:wrap; }
    .page-btn { min-width:30px; height:30px; padding:0 8px; background:rgba(139,126,255,0.05); border:1px solid rgba(139,126,255,0.18) !important; border-radius:2px; display:inline-flex; align-items:center; justify-content:center; font-family:var(--font-hud); font-size:0.52rem; color:var(--text-soft); cursor:pointer; transition:var(--transition); text-decoration:none; }
    .page-btn:hover { background:rgba(139,126,255,0.14); color:var(--prc-violet); border-color:var(--prc-violet) !important; }
    .page-btn.active { background:rgba(139,126,255,0.20); color:var(--prc-violet); border-color:var(--prc-violet) !important; box-shadow:0 0 10px rgba(139,126,255,0.25); pointer-events:none; }
    .page-btn.disabled { opacity:0.35; pointer-events:none; }

    /* ── MODAL ── */
    .modal-overlay { position:fixed; inset:0; z-index:9000; background:rgba(3,2,13,0.90); backdrop-filter:blur(8px); display:none; align-items:center; justify-content:center; padding:24px; }
    .modal-overlay.open { display:flex; }
    .modal { background:var(--bg-deep); border:1px solid var(--border-neon); max-width:660px; width:100%; max-height:88vh; overflow-y:auto; position:relative; }
    .modal::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg,transparent,var(--prc-violet),var(--prc-ice),transparent); }
    .modal-hdr { padding:18px 24px 14px; border-bottom:1px solid var(--border-neon); background:rgba(139,126,255,0.06); display:flex; align-items:center; justify-content:space-between; }
    .modal-title { font-family:var(--font-hud); font-size:0.72rem; font-weight:700; letter-spacing:0.08em; color:var(--prc-violet); text-shadow:0 0 10px rgba(139,126,255,0.50); }
    .modal-close { width:30px; height:30px; background:rgba(139,126,255,0.06); border:1px solid rgba(139,126,255,0.20) !important; color:var(--text-soft); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:0.90rem; transition:var(--transition); border-radius:2px; }
    .modal-close:hover { background:rgba(139,126,255,0.14); color:var(--prc-violet); }
    .modal-body { padding:22px 24px; }
    .modal-section-label { font-family:var(--font-hud); font-size:0.52rem; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:var(--text-dim); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
    .modal-section-label::before { content:'//'; color:rgba(139,126,255,0.35); }
    .modal-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; margin-bottom:20px; }
    .modal-field { display:flex; flex-direction:column; gap:4px; }
    .modal-field.full { grid-column:1/-1; }
    .modal-field-label { font-family:var(--font-hud); font-size:0.46rem; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:var(--text-soft); }
    .modal-field-val { font-size:0.875rem; color:var(--text-high); font-weight:500; word-break:break-word; }
    .modal-members-list { font-size:0.858rem; color:var(--text-mid); line-height:1.80; }
    .modal-members-list li { padding:5px 0; border-bottom:1px solid rgba(139,126,255,0.06); display:flex; align-items:baseline; gap:10px; }
    .modal-members-list li:last-child { border-bottom:none; }
    .member-num { font-family:var(--font-hud); font-size:0.44rem; color:var(--text-dim); flex-shrink:0; }
    .modal-proof-wrap { margin-top:8px; }
    .modal-proof-img { max-width:100%; border:1px solid rgba(139,126,255,0.22); background:rgba(0,0,0,0.30); display:block; }
    .modal-proof-link { display:inline-flex; align-items:center; gap:8px; margin-top:8px; font-family:var(--font-hud); font-size:0.58rem; color:#44D9FF; border:1px solid rgba(68,217,255,0.28); padding:6px 14px; clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%); transition:var(--transition); }
    .modal-proof-link:hover { background:rgba(68,217,255,0.10); }
    .modal-footer { display:flex; gap:10px; flex-wrap:wrap; padding-top:18px; border-top:1px solid rgba(139,126,255,0.12); }

    /* TOAST */
    .toast-container { position:fixed; bottom:28px; right:28px; display:flex; flex-direction:column; gap:10px; z-index:9999; pointer-events:none; }
    .toast { background:var(--bg-deep); border:1px solid var(--border-neon); padding:12px 20px; font-family:var(--font-hud); font-size:0.58rem; font-weight:700; letter-spacing:0.08em; color:var(--text-mid); display:flex; align-items:center; gap:10px; min-width:260px; animation:toastIn 0.3s ease; pointer-events:all; }
    .toast.success { border-color:rgba(68,255,136,0.40); color:#44FF88; }
    .toast.error   { border-color:rgba(255,80,80,0.40);  color:#FF5050; }
    .toast.info    { border-color:rgba(139,126,255,0.40);color:var(--prc-violet); }
    @keyframes toastIn { from{opacity:0;transform:translateX(16px);}to{opacity:1;transform:translateX(0);} }

    /* ANIM */
    @keyframes fadeInUp { from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);} }
    .anim-1{animation:fadeInUp .40s ease .05s both;}
    .anim-2{animation:fadeInUp .40s ease .12s both;}
    .anim-3{animation:fadeInUp .40s ease .19s both;}
    .anim-4{animation:fadeInUp .40s ease .26s both;}

    /* EMPTY */
    .empty-state { text-align:center; padding:60px 20px; }
    .empty-state i { font-size:2.5rem; color:rgba(139,126,255,0.25); display:block; margin-bottom:16px; }
    .empty-state-title { font-family:var(--font-hud); font-size:0.78rem; font-weight:700; color:var(--text-soft); margin-bottom:8px; }
    .empty-state-sub { font-size:0.875rem; color:var(--text-dim); }

    /* MISC */
    ::-webkit-scrollbar { width:3px; height:3px; }
    ::-webkit-scrollbar-track { background:var(--bg-void); }
    ::-webkit-scrollbar-thumb { background:rgba(139,126,255,0.30); border-radius:2px; }

    .no-proof { font-family:var(--font-hud); font-size:0.48rem; color:var(--text-dim); letter-spacing:0.08em; }

    @media(max-width:1280px){ .kpi-strip{grid-template-columns:repeat(3,1fr);} }
    @media(max-width:900px){ .admin-shell{grid-template-columns:0 1fr;} .admin-sidebar-slot{display:none;} .admin-main{padding:18px 16px 40px;} .topbar-search-form{display:none;} }
    @media(max-width:600px){ .kpi-strip{grid-template-columns:1fr 1fr;} .page-header{flex-direction:column;} .modal-grid{grid-template-columns:1fr;} }
  </style>
</head>
<body>

<div class="admin-shell" id="adminShell">

  <!-- SIDEBAR SLOT -->
  <div class="admin-sidebar-slot" id="sidebarSlot"></div>

  <!-- TOP BAR -->
  <header class="admin-topbar">
    <div class="topbar-breadcrumb">
      <a href="admin-dashboard.html">PRC Admin</a>
      <i class="fi fi-rr-angle-right"></i>
      <span>Registrations</span>
    </div>
    <div class="topbar-right">
      <form class="topbar-search-form" method="GET" action="">
        <?php foreach (['status'=>$status_filter,'track'=>$track_filter,'sort'=>$sort_col,'dir'=>$sort_dir,'pp'=>$per_page] as $k=>$v): ?>
          <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>" />
        <?php endforeach; ?>
        <i class="fi fi-rr-search"></i>
        <input type="text" name="search" placeholder="Search team, school, ref…"
               value="<?= h($search) ?>" autocomplete="off" />
      </form>
      <div class="topbar-date"><span id="topbar-clock">—</span></div>
    </div>
  </header>

  <!-- MAIN -->
  <main class="admin-main">

    <!-- PAGE HEADER -->
    <div class="page-header anim-1">
      <div>
        <div class="page-eyebrow">PRC 2026 // Registration Management</div>
        <h1 class="page-title">All <span class="accent">Registrations</span></h1>
      </div>
      <div class="page-actions">
        <a class="btn btn-volt" href="https://docs.google.com/spreadsheets/d/1UhpRnLOGndDl_wcWMTke0BAaYJ-lkynBxYs7zd7Ur1g/edit?gid=177270871#gid=177270871">
          <i class="fi fi-rr-download"></i> Go to Google Sheets
        </a>
        <a href="?" class="btn btn-primary">
          <i class="fi fi-rr-refresh"></i> Refresh
        </a>
      </div>
    </div>

    <!-- KPI STRIP -->
    <div class="kpi-strip anim-2">
      <div class="kpi-mini kpi-v">
        <span class="kpi-mini-num"><?= intval($kpi['total']) ?></span>
        <div class="kpi-mini-label">Total Registrations</div>
      </div>
      <div class="kpi-mini kpi-g">
        <span class="kpi-mini-num"><?= intval($kpi['confirmed']) ?></span>
        <div class="kpi-mini-label">Confirmed</div>
      </div>
      <div class="kpi-mini kpi-a">
        <span class="kpi-mini-num"><?= intval($kpi['proof_sub']) ?></span>
        <div class="kpi-mini-label">Proof Submitted</div>
      </div>
      <div class="kpi-mini kpi-y">
        <span class="kpi-mini-num"><?= intval($kpi['pending']) ?></span>
        <div class="kpi-mini-label">Pending Payment</div>
      </div>
      <div class="kpi-mini kpi-v">
        <span class="kpi-mini-num" style="font-size:1.05rem;">₱<?= number_format($kpi['revenue'], 0) ?></span>
        <div class="kpi-mini-label">Total Revenue</div>
      </div>
    </div>

    <!-- FILTER BAR -->
    <?php
    function filterUrl($params, $overrides) {
        $p = array_merge($params, $overrides, ['page' => 1]);
        return '?' . http_build_query($p);
    }
    $base = ['search'=>$search,'sort'=>$sort_col,'dir'=>$sort_dir,'pp'=>$per_page];
    $statusBtns = [
        'all'             => ['All',             'active'],
        'Confirmed'       => ['Confirmed',        'active-g'],
        'Proof Submitted' => ['Proof Submitted',  'active-a'],
        'Pending Payment' => ['Pending',          'active-y'],
        'Rejected'        => ['Rejected',         'active-r'],
    ];
    $trackBtns = [
        'all'          => 'All Tracks',
        'RoboVenture'  => 'RoboVenture',
        'MakeX'        => 'MakeX',
        'Drone Soccer' => 'Drone Soccer',
    ];
    ?>
    <div class="filter-bar anim-3">
      <span class="filter-label">Filter</span>
      <div class="filter-group">
        <?php foreach ($statusBtns as $val => [$label, $activeCls]): ?>
          <?php $isActive = ($status_filter === $val); ?>
          <a href="<?= filterUrl($base, ['status'=>$val,'track'=>$track_filter]) ?>"
             class="filter-btn <?= $isActive ? $activeCls : '' ?>">
            <?= h($label) ?>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="filter-sep"></div>
      <div class="filter-group">
        <?php foreach ($trackBtns as $val => $label): ?>
          <a href="<?= filterUrl($base, ['track'=>$val,'status'=>$status_filter]) ?>"
             class="filter-btn <?= $track_filter === $val ? 'active' : '' ?>">
            <?= h($label) ?>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="filter-right">
        <form method="GET" id="sort-form" style="display:contents;">
          <?php foreach (['status'=>$status_filter,'track'=>$track_filter,'search'=>$search,'sort'=>$sort_col,'dir'=>$sort_dir,'page'=>$page] as $k=>$v): ?>
            <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>" />
          <?php endforeach; ?>
          <select class="filter-select" name="pp" onchange="document.getElementById('sort-form').submit()">
            <?php foreach ([10,25,50] as $opt): ?>
              <option value="<?= $opt ?>" <?= $per_page === $opt ? 'selected' : '' ?>><?= $opt ?> / page</option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <!-- TABLE PANEL -->
    <div class="panel anim-4">
      <div class="panel-header">
        <div class="panel-title">
          <i class="fi fi-rr-users"></i>
          Registration Records
        </div>
        <div class="panel-meta">
          Showing <span><?= count($records) ?></span> of <span><?= $total_filtered ?></span> filtered
          &nbsp;·&nbsp; <span><?= intval($kpi['total']) ?></span> total
        </div>
      </div>

      <?php if (empty($records)): ?>
        <div class="empty-state">
          <i class="fi fi-rr-user-slash"></i>
          <div class="empty-state-title">No registrations found</div>
          <div class="empty-state-sub">Try adjusting your filters or search query.</div>
        </div>
      <?php else: ?>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <?php
              $sp = ['status'=>$status_filter,'track'=>$track_filter,'search'=>$search,'pp'=>$per_page,'page'=>$page];
              echo sortLink('ref_no',         'Ref No.',      $sort_col, $sort_dir, $sp);
              echo sortLink('team_name',      'Team / School',$sort_col, $sort_dir, $sp);
              echo sortLink('category',       'Category',     $sort_col, $sort_dir, $sp);
              echo sortLink('package_name',   'Package',      $sort_col, $sort_dir, $sp);
              echo sortLink('payment_method', 'Payment',      $sort_col, $sort_dir, $sp);
              echo sortLink('package_amount', 'Amount',       $sort_col, $sort_dir, $sp);
              echo sortLink('payment_status', 'Status',       $sort_col, $sort_dir, $sp);
              echo sortLink('submitted_at',   'Date',         $sort_col, $sort_dir, $sp);
              ?>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $i => $r):
              $track    = getTrack($r['category']);
              $tCls     = trackCls($track);
              $sCls     = statusCls($r['payment_status']);
              $pCls     = payBadgeCls($r['payment_method']);
              $initials = strtoupper(mb_substr(preg_replace('/[^A-Za-z ]/','',$r['team_name']),0,2));
              $players  = $players_map[$r['team_id']] ?? [];
              $isCancelled = ($r['reg_status'] === 'Cancelled');
              // Encode row data for modal
              $modal_data = json_encode([
                'ref'        => $r['ref_no'],
                'team'       => $r['team_name'],
                'school'     => $r['school_name'],
                'region'     => $r['school_region'],
                'category'   => $r['category'],
                'contact'    => $r['contact_name'],
                'email'      => $r['contact_email'],
                'phone'      => $r['contact_phone'],
                'role'       => $r['contact_role'],
                'package'    => $r['package_name'],
                'amount'     => '₱' . number_format($r['package_amount'], 0),
                'members'    => $r['member_count'],
                'method'     => $r['payment_method'],
                'status'     => $r['payment_status'],
                'reg_status' => $r['reg_status'],
                'proof'      => $r['proof_link'],
                'notes'      => $r['notes'],
                'date'       => date('M d, Y · h:i A', strtotime($r['submitted_at'])),
                'players'    => $players,
            ]);
            ?>
            <tr class="<?= $isCancelled ? 'row-cancelled' : '' ?>">
              <td><span class="ref-badge"><?= h($r['ref_no']) ?></span></td>
              <td>
                <div class="td-team">
                  <div class="td-avatar"><?= h($initials) ?></div>
                  <div>
                    <div class="td-name"><?= h($r['team_name']) ?></div>
                    <div class="td-sub"><?= h($r['school_name']) ?></div>
                  </div>
                </div>
              </td>
              <td><span class="track-badge <?= $tCls ?>"><?= h($r['category']) ?></span></td>
              <td style="font-size:0.78rem;color:var(--text-soft);"><?= h(str_replace('Package ','Pkg ',$r['package_name'])) ?></td>
              <td><span class="pay-badge <?= $pCls ?>"><?= h($r['payment_method']) ?></span></td>
              <td style="font-family:var(--font-hud);font-size:0.62rem;font-weight:700;color:var(--prc-violet);">
                ₱<?= number_format($r['package_amount'], 0) ?>
              </td>
              <td>
                <span class="pill <?= $sCls ?>">
                  <span class="pill-dot"></span><?= h($r['payment_status']) ?>
                </span>
                <?php if ($isCancelled): ?>
                  <span class="pill cancelled" style="margin-top:3px;display:inline-flex;">
                    <span class="pill-dot"></span>Cancelled
                  </span>
                <?php endif; ?>
              </td>
              <td style="color:var(--text-dim);font-size:0.76rem;">
                <?= date('M d, Y', strtotime($r['submitted_at'])) ?>
              </td>
              <td>
                <div class="tbl-actions">
                  <button class="tbl-btn" title="View details"
                          onclick='openModal(<?= htmlspecialchars(json_encode($modal_data), ENT_QUOTES) ?>)'>
                    <i class="fi fi-rr-eye"></i>
                  </button>
                  <?php if ($r['payment_status'] !== 'Confirmed' && !$isCancelled): ?>
                  <button class="tbl-btn approve" title="Confirm payment"
                          onclick="doAction('confirm','<?= h($r['ref_no']) ?>','<?= h($r['team_name']) ?>')">
                    <i class="fi fi-rr-check"></i>
                  </button>
                  <?php endif; ?>
                  <?php if ($r['payment_status'] !== 'Rejected' && !$isCancelled): ?>
                  <button class="tbl-btn reject" title="Reject / cancel"
                          onclick="doAction('reject','<?= h($r['ref_no']) ?>','<?= h($r['team_name']) ?>')">
                    <i class="fi fi-rr-cross"></i>
                  </button>
                  <?php endif; ?>
                  <?php if ($r['proof_link']): ?>
                  <a class="tbl-btn" title="View proof" href="<?= h($r['proof_link']) ?>" target="_blank" rel="noopener">
                    <i class="fi fi-rr-file-check"></i>
                  </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- PAGINATION -->
      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <div class="page-info">
          Page <span><?= $page ?></span> of <span><?= $total_pages ?></span>
          &nbsp;·&nbsp; <span><?= $total_filtered ?></span> records
        </div>
        <div class="page-btns">
          <?php
          $pBase = array_merge($link_params, []);
          // Prev
          $prevClass = $page <= 1 ? 'disabled' : '';
          echo "<a class='page-btn $prevClass' href='?" . http_build_query(array_merge($pBase,['page'=>max(1,$page-1)])) . "'>&lsaquo;</a>";
          // Page numbers
          $start = max(1, $page - 2);
          $end   = min($total_pages, $start + 4);
          for ($p = $start; $p <= $end; $p++) {
              $cls = ($p === $page) ? 'active' : '';
              echo "<a class='page-btn $cls' href='?" . http_build_query(array_merge($pBase,['page'=>$p])) . "'>$p</a>";
          }
          // Next
          $nextClass = $page >= $total_pages ? 'disabled' : '';
          echo "<a class='page-btn $nextClass' href='?" . http_build_query(array_merge($pBase,['page'=>min($total_pages,$page+1)])) . "'>&rsaquo;</a>";
          ?>
        </div>
      </div>
      <?php endif; ?>

      <?php endif; ?>
    </div><!-- /panel -->

  </main>
</div><!-- /admin-shell -->

<!-- DETAIL MODAL -->
<div class="modal-overlay" id="modal-overlay" onclick="closeModalOutside(event)">
  <div class="modal">
    <div class="modal-hdr">
      <div class="modal-title" id="modal-title">Registration Details</div>
      <button class="modal-close" onclick="closeModal()"><i class="fi fi-rr-cross"></i></button>
    </div>
    <div class="modal-body" id="modal-body"></div>
  </div>
</div>

<!-- TOAST -->
<div class="toast-container" id="toast-container"></div>

<!-- SIDEBAR LOADER -->
<script>
(function(){
  var slot = document.getElementById('sidebarSlot');
  if (!slot) return;
  fetch('admin-sidebar.html')
    .then(function(r){ return r.text(); })
    .then(function(html){
      slot.innerHTML = html;
      slot.querySelectorAll('script').forEach(function(old){
        var s = document.createElement('script');
        s.textContent = old.textContent;
        document.body.appendChild(s);
      });
    })
    .catch(function(){
      slot.innerHTML = '<aside style="position:fixed;top:0;left:0;height:100vh;width:248px;background:#04031A;border-right:1px solid rgba(139,126,255,0.18);display:flex;align-items:center;justify-content:center;font-family:Orbitron,monospace;font-size:0.55rem;color:rgba(139,126,255,0.40);letter-spacing:0.12em;text-align:center;padding:20px;">SIDEBAR</aside>';
    });
})();
if (localStorage.getItem('prc_sidebar_collapsed') === '1') {
  document.getElementById('adminShell').classList.add('sb-collapsed');
}
document.addEventListener('prc-sidebar-toggle', function(e){
  document.getElementById('adminShell').classList.toggle('sb-collapsed', e.detail.collapsed);
});
</script>

<script>
// ── CLOCK ──
(function(){
  var el = document.getElementById('topbar-clock');
  function tick(){
    var d=new Date(),mo=['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
    var h=d.getHours(),m=d.getMinutes(),ap=h>=12?'PM':'AM';
    h=h%12||12;
    el.textContent=mo[d.getMonth()]+' '+String(d.getDate()).padStart(2,'0')+', '+d.getFullYear()+'  '+String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+' '+ap;
  }
  tick(); setInterval(tick,30000);
})();

// ── MODAL ──
function openModal(jsonStr) {
  var d = JSON.parse(jsonStr);
  document.getElementById('modal-title').textContent = (d.team || 'Team') + ' · ' + (d.ref || '');

  var proofHtml = '';
  if (d.proof) {
    var ext = d.proof.split('.').pop().toLowerCase();
    if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
      proofHtml = '<img src="' + escHtml(d.proof) + '" class="modal-proof-img" alt="Proof of payment" />';
    }
    proofHtml += '<a href="' + escHtml(d.proof) + '" target="_blank" rel="noopener" class="modal-proof-link">'
              + '<i class="fi fi-rr-external-link"></i> Open Full Receipt</a>';
  } else {
    proofHtml = '<span class="no-proof">No proof uploaded yet.</span>';
  }

  var playersHtml = '';
  if (d.players && d.players.length) {
    playersHtml = '<ul class="modal-members-list">' +
      d.players.map(function(p, i){
        return '<li><span class="member-num">0' + (i+1) + '</span>'
          + escHtml(p.player_name)
          + (p.player_grade ? ' <span style="color:var(--text-dim);font-size:0.75rem;">(' + escHtml(p.player_grade) + ')</span>' : '')
          + (p.player_birthdate ? ' <span style="color:var(--text-dim);font-size:0.70rem;margin-left:auto;">' + escHtml(p.player_birthdate) + '</span>' : '')
          + '</li>';
      }).join('') + '</ul>';
  } else {
    playersHtml = '<span style="color:var(--text-dim);font-size:0.82rem;">No member records found.</span>';
  }

  var confirmBtn = '';
  if (d.status !== 'Confirmed' && d.reg_status !== 'Cancelled') {
    confirmBtn = '<button class="btn btn-approve btn-sm" onclick="doAction(\'confirm\',\'' + escHtml(d.ref) + '\',\'' + escHtml(d.team) + '\');closeModal();">'
               + '<i class="fi fi-rr-check"></i> Confirm Slot</button>';
  }
  var rejectBtn = '';
  if (d.status !== 'Rejected' && d.reg_status !== 'Cancelled') {
    rejectBtn = '<button class="btn btn-reject btn-sm" onclick="doAction(\'reject\',\'' + escHtml(d.ref) + '\',\'' + escHtml(d.team) + '\');closeModal();">'
              + '<i class="fi fi-rr-cross"></i> Reject</button>';
  }

  document.getElementById('modal-body').innerHTML =
    '<div class="modal-section-label">Registrant Info</div>' +
    '<div class="modal-grid">' +
      mf('Full Name',    d.contact) +
      mf('Email',        d.email) +
      mf('Contact No.',  d.phone) +
      mf('Role',         d.role || '—') +
    '</div>' +
    '<div class="modal-section-label">Team Info</div>' +
    '<div class="modal-grid">' +
      mf('School', d.school, true) +
      mf('Region', d.region) +
      mf('Team Name', d.team) +
      mf('Category', '<span class="track-badge ' + trackClsJs(d.category) + '">' + escHtml(d.category) + '</span>', false, true) +
      mf('Members Declared', d.members + ' member(s)') +
      (d.notes ? mf('Notes', d.notes, true) : '') +
    '</div>' +
    '<div class="modal-section-label">Registered Players</div>' +
    playersHtml +
    '<div class="modal-section-label" style="margin-top:20px;">Payment Info</div>' +
    '<div class="modal-grid">' +
      mf('Package', d.package, true) +
      mf('Amount', '<span style="font-family:var(--font-hud);font-size:1.0rem;font-weight:700;color:var(--prc-violet);">' + escHtml(d.amount) + '</span>', false, true) +
      mf('Method', '<span class="pay-badge ' + payClsJs(d.method) + '">' + escHtml(d.method) + '</span>', false, true) +
      mf('Status', '<span class="pill ' + statusClsJs(d.status) + '"><span class="pill-dot"></span>' + escHtml(d.status) + '</span>', false, true) +
      mf('Reference No.', '<span class="ref-badge">' + escHtml(d.ref) + '</span>', false, true) +
      mf('Submitted', d.date) +
    '</div>' +
    '<div class="modal-section-label">Proof of Payment</div>' +
    '<div class="modal-proof-wrap">' + proofHtml + '</div>' +
    '<div class="modal-footer">' + confirmBtn + rejectBtn +
      '<button class="btn btn-ghost btn-sm" onclick="closeModal()">Close</button>' +
    '</div>';

  document.getElementById('modal-overlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function mf(label, value, full, raw) {
  return '<div class="modal-field' + (full ? ' full' : '') + '">'
    + '<div class="modal-field-label">' + escHtml(label) + '</div>'
    + '<div class="modal-field-val">' + (raw ? value : escHtml(String(value || '—'))) + '</div>'
    + '</div>';
}

function closeModal() {
  document.getElementById('modal-overlay').classList.remove('open');
  document.body.style.overflow = '';
}
function closeModalOutside(e) {
  if (e.target === document.getElementById('modal-overlay')) closeModal();
}
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });

// ── AJAX ACTION ──
function doAction(action, ref, teamName) {
  var msgs = {
    confirm: 'Confirm slot for "' + teamName + '"?',
    reject:  'Reject registration for "' + teamName + '"? This will also mark it Cancelled.',
    cancel:  'Cancel registration for "' + teamName + '"?',
  };
  if (!confirm(msgs[action] || 'Are you sure?')) return;

  var fd = new FormData();
  fd.append('action', action);
  fd.append('ref', ref);

  fetch(window.location.pathname, { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok) {
        toast(action === 'confirm' ? 'Slot confirmed for ' + teamName
              : action === 'reject' ? 'Registration rejected.'
              : 'Registration cancelled.', 'success');
        setTimeout(function(){ location.reload(); }, 1200);
      } else {
        toast('Action failed: ' + (d.error || 'unknown error'), 'error');
      }
    })
    .catch(function(){ toast('Network error — please try again.', 'error'); });
}

// ── EXPORT CSV ──
function exportCSV() {
  var rows = document.querySelectorAll('.data-table tbody tr');
  if (!rows.length) { toast('No records to export', 'error'); return; }
  var headers = ['Ref No.','Team','School','Category','Package','Payment Method','Amount','Status','Date'];
  var lines = [headers.join(',')];
  rows.forEach(function(row) {
    var cells = row.querySelectorAll('td');
    var ref    = cells[0]?.textContent.trim() || '';
    var teamEl = cells[1]?.querySelectorAll('div');
    var team   = teamEl?.[1]?.textContent.trim() || '';
    var school = teamEl?.[2]?.textContent.trim() || '';
    var cat    = cells[2]?.textContent.trim() || '';
    var pkg    = cells[3]?.textContent.trim() || '';
    var pay    = cells[4]?.textContent.trim() || '';
    var amt    = cells[5]?.textContent.trim() || '';
    var stat   = cells[6]?.textContent.trim().split('\n')[0] || '';
    var date   = cells[7]?.textContent.trim() || '';
    lines.push([ref,team,school,cat,pkg,pay,amt,stat,date].map(function(v){
      return '"' + v.replace(/"/g,'""') + '"';
    }).join(','));
  });
  var blob = new Blob([lines.join('\n')], {type:'text/csv'});
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'PRC-2026-Registrations-' + new Date().toISOString().slice(0,10) + '.csv';
  a.click();
  toast('Exported ' + rows.length + ' records', 'success');
}

// ── TOAST ──
function toast(msg, type) {
  var c = document.getElementById('toast-container');
  var t = document.createElement('div');
  t.className = 'toast ' + (type || 'info');
  var icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation' : 'info';
  t.innerHTML = '<i class="fi fi-rr-' + icon + '"></i> ' + escHtml(msg);
  c.appendChild(t);
  setTimeout(function(){
    t.style.transition = 'opacity 0.4s';
    t.style.opacity = '0';
    setTimeout(function(){ t.remove(); }, 400);
  }, 3500);
}

// ── HELPERS ──
function escHtml(str) {
  return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function trackClsJs(cat) {
  if (!cat) return 'track-rv';
  if (cat.startsWith('MakeX'))   return 'track-mx';
  if (cat === 'Drone Soccer')    return 'track-ds';
  return 'track-rv';
}
function statusClsJs(s) {
  var m = {Confirmed:'confirmed','Proof Submitted':'proof','Pending Payment':'pending',Rejected:'cancelled'};
  return m[s] || 'processing';
}
function payClsJs(m) {
  if (m === 'GCash')          return 'pay-gcash';
  if (m === 'Bank Transfer')  return 'pay-bank';
  return 'pay-none';
}
</script>

</body>
</html>