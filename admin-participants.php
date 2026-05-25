<?php
// ══════════════════════════════════════════════════════════════════
// admin-participants.php — PRC 2026 Admin · Participants
// Lists every individual player across all registered teams
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

// ── AJAX: toggle attendance ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action    = $_POST['action'];
    $player_id = intval($_POST['player_id'] ?? 0);

    if ($action === 'toggle_present' && $player_id) {
        $cur = $db->query("SELECT player_ispresent FROM prc_reg_players WHERE player_id = $player_id")->fetch_assoc();
        if ($cur) {
            $new = $cur['player_ispresent'] ? 0 : 1;
            $db->query("UPDATE prc_reg_players SET player_ispresent = $new WHERE player_id = $player_id");
            echo json_encode(['ok' => true, 'present' => $new]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Player not found']);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
    $db->close();
    exit;
}

// ── READ FILTER PARAMS ────────────────────────────────────────────
$track_filter   = $_GET['track']   ?? 'all';
$grade_filter   = $_GET['grade']   ?? 'all';
$present_filter = $_GET['present'] ?? 'all';
$search         = trim($_GET['search'] ?? '');
$sort_col       = $_GET['sort'] ?? 'p.player_id';
$sort_dir       = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$page           = max(1, intval($_GET['page'] ?? 1));
$per_page       = in_array(intval($_GET['pp'] ?? 25), [10, 25, 50, 100]) ? intval($_GET['pp'] ?? 25) : 25;

$allowed_sort = [
    'player_name'  => 'p.player_name',
    'player_grade' => 'p.player_grade',
    'birthdate'    => 'p.player_birthdate',
    'team_name'    => 't.team_name',
    'school_name'  => 's.school_name',
    'category'     => 't.category',
    'present'      => 'p.player_ispresent',
    'registered'   => 'p.created_at',
];
$sort_expr = $allowed_sort[$sort_col] ?? 'p.player_id';

// ── BUILD WHERE ───────────────────────────────────────────────────
$wheres = [];

if ($track_filter !== 'all') {
    if ($track_filter === 'RoboVenture') {
        $wheres[] = "t.category NOT LIKE 'MakeX%' AND t.category != 'Drone Soccer'";
    } elseif ($track_filter === 'MakeX') {
        $wheres[] = "t.category LIKE 'MakeX%'";
    } elseif ($track_filter === 'Drone Soccer') {
        $wheres[] = "t.category = 'Drone Soccer'";
    }
}

if ($grade_filter !== 'all') {
    $gf = $db->real_escape_string($grade_filter);
    $wheres[] = "p.player_grade = '$gf'";
}

if ($present_filter === 'present') {
    $wheres[] = "p.player_ispresent = 1";
} elseif ($present_filter === 'absent') {
    $wheres[] = "p.player_ispresent = 0";
}

if ($search !== '') {
    $sq = $db->real_escape_string($search);
    $wheres[] = "(p.player_name LIKE '%$sq%' OR t.team_name LIKE '%$sq%'
                  OR s.school_name LIKE '%$sq%' OR t.category LIKE '%$sq%'
                  OR r.ref_no LIKE '%$sq%' OR p.player_grade LIKE '%$sq%')";
}

$where_sql = count($wheres) ? 'WHERE ' . implode(' AND ', $wheres) : '';

// ── KPI COUNTS ────────────────────────────────────────────────────
$kpi = $db->query("
    SELECT
        COUNT(*)                                                              AS total,
        SUM(p.player_ispresent = 1)                                           AS present,
        SUM(p.player_ispresent = 0)                                           AS absent,
        SUM(t.category NOT LIKE 'MakeX%' AND t.category != 'Drone Soccer')   AS roboventure,
        SUM(t.category LIKE 'MakeX%')                                         AS makex,
        SUM(t.category = 'Drone Soccer')                                      AS drone_soccer
    FROM prc_reg_players p
    JOIN prc_reg_teams   t ON t.team_id   = p.team_id
    JOIN prc_reg_schools s ON s.school_id = t.school_id
    LEFT JOIN prc_registrations r ON r.team_id = t.team_id
")->fetch_assoc();

// ── DISTINCT GRADES for filter ────────────────────────────────────
$grades_res = $db->query("
    SELECT DISTINCT player_grade
    FROM prc_reg_players
    WHERE player_grade IS NOT NULL AND player_grade != ''
    ORDER BY player_grade
");
$grades = [];
while ($g = $grades_res->fetch_assoc()) { $grades[] = $g['player_grade']; }

// ── COUNT FILTERED ────────────────────────────────────────────────
$cnt_sql = "
    SELECT COUNT(*) AS cnt
    FROM prc_reg_players p
    JOIN prc_reg_teams   t ON t.team_id   = p.team_id
    JOIN prc_reg_schools s ON s.school_id = t.school_id
    LEFT JOIN prc_registrations r ON r.team_id = t.team_id
    $where_sql
";
$total_filtered = $db->query($cnt_sql)->fetch_assoc()['cnt'];
$total_pages    = max(1, ceil($total_filtered / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

// ── FETCH PAGE RECORDS ────────────────────────────────────────────
$sql = "
    SELECT
        p.player_id, p.player_name, p.player_birthdate,
        p.player_grade, p.player_ispresent, p.created_at AS registered_at,
        t.team_id, t.team_name, t.category, t.package,
        s.school_name, s.school_region,
        r.ref_no, r.payment_status, r.reg_status,
        r.contact_name, r.contact_email, r.contact_phone
    FROM prc_reg_players p
    JOIN prc_reg_teams   t ON t.team_id   = p.team_id
    JOIN prc_reg_schools s ON s.school_id = t.school_id
    LEFT JOIN prc_registrations r ON r.team_id = t.team_id
    $where_sql
    ORDER BY $sort_expr $sort_dir
    LIMIT $per_page OFFSET $offset
";
$result  = $db->query($sql);
$records = [];
while ($row = $result->fetch_assoc()) { $records[] = $row; }

$db->close();

// ── HELPERS ───────────────────────────────────────────────────────
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function getTrack($cat) {
    if (str_starts_with($cat, 'MakeX')) return 'MakeX';
    if ($cat === 'Drone Soccer')        return 'Drone Soccer';
    return 'RoboVenture';
}
function trackCls($t) {
    return match($t) {
        'MakeX'        => 'track-mx',
        'Drone Soccer' => 'track-ds',
        default        => 'track-rv'
    };
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

// Calculate age from birthdate
function calcAge($bdate) {
    if (!$bdate) return null;
    $birth = new DateTime($bdate);
    $now   = new DateTime();
    return $birth->diff($now)->y;
}

function sortLink($col, $label, $cur_col, $cur_dir, $params) {
    $new_dir = ($cur_col === $col && $cur_dir === 'ASC') ? 'DESC' : 'ASC';
    $arrow   = $cur_col === $col ? ($cur_dir === 'ASC' ? ' ↑' : ' ↓') : ' ↕';
    $active  = $cur_col === $col ? ' sorted' : '';
    $p       = array_merge($params, ['sort' => $col, 'dir' => $new_dir]);
    $url     = '?' . http_build_query($p);
    return "<th class=\"$active\" onclick=\"window.location='$url'\">$label<span class='sort-arrow'>$arrow</span></th>";
}

function filterUrl($params, $overrides) {
    $p = array_merge($params, $overrides, ['page' => 1]);
    return '?' . http_build_query($p);
}

$link_params = [
    'track'   => $track_filter,
    'grade'   => $grade_filter,
    'present' => $present_filter,
    'search'  => $search,
    'sort'    => $sort_col,
    'dir'     => $sort_dir,
    'pp'      => $per_page,
];
$base = ['search' => $search, 'sort' => $sort_col, 'dir' => $sort_dir, 'pp' => $per_page];
?>
<!DOCTYPE html>
<!-- PRC-WebApp/admin-participants.php -->
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PRC Admin — Participants</title>
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
    .btn-ghost { background:rgba(139,126,255,0.04); border:1px solid var(--border-neon) !important; color:var(--text-soft); clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%); }
    .btn-ghost:hover { background:rgba(139,126,255,0.10); color:var(--text-mid); }
    .btn-sm { padding:6px 14px; font-size:0.54rem; }

    /* ── KPI STRIP ── */
    .kpi-strip { display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:22px; }
    .kpi-mini { background:var(--bg-card); border:1px solid var(--border-neon); padding:14px 16px; position:relative; overflow:hidden; clip-path:polygon(0 0,calc(100% - 8px) 0,100% 8px,100% 100%,8px 100%,0 calc(100% - 8px)); }
    .kpi-mini::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; }
    .kpi-v::before { background:linear-gradient(90deg,transparent,var(--prc-violet),transparent); }
    .kpi-g::before { background:linear-gradient(90deg,transparent,#44FF88,transparent); }
    .kpi-r::before { background:linear-gradient(90deg,transparent,#FF5050,transparent); }
    .kpi-a::before { background:linear-gradient(90deg,transparent,var(--creo-amber),transparent); }
    .kpi-b::before { background:linear-gradient(90deg,transparent,#44D9FF,transparent); }
    .kpi-mini-num { font-family:var(--font-hud); font-size:1.65rem; font-weight:800; line-height:1; display:block; }
    .kpi-v .kpi-mini-num { color:var(--prc-violet); text-shadow:0 0 18px rgba(139,126,255,0.55); }
    .kpi-g .kpi-mini-num { color:#44FF88;            text-shadow:0 0 18px rgba(68,255,136,0.55); }
    .kpi-r .kpi-mini-num { color:#FF5050;             text-shadow:0 0 18px rgba(255,80,80,0.55); }
    .kpi-a .kpi-mini-num { color:var(--creo-amber);  text-shadow:0 0 18px rgba(255,160,48,0.55); }
    .kpi-b .kpi-mini-num { color:#44D9FF;             text-shadow:0 0 18px rgba(68,217,255,0.55); }
    .kpi-mini-label { font-family:var(--font-hud); font-size:0.48rem; color:var(--text-soft); letter-spacing:0.10em; text-transform:uppercase; margin-top:5px; }

    /* ── FILTER BAR ── */
    .filter-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:18px; padding:14px 18px; background:var(--bg-card); border:1px solid var(--border-neon); position:relative; overflow:hidden; }
    .filter-bar::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--prc-violet),transparent); opacity:0.35; }
    .filter-label { font-family:var(--font-hud); font-size:0.50rem; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:var(--text-dim); white-space:nowrap; }
    .filter-label::before { content:'//'; margin-right:6px; color:rgba(139,126,255,0.30); }
    .filter-group { display:flex; gap:6px; flex-wrap:wrap; }
    .filter-btn { font-family:var(--font-hud); font-size:0.54rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; padding:6px 14px; border:1px solid rgba(139,126,255,0.20); color:var(--text-soft); background:transparent; clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%); transition:var(--transition); cursor:pointer; text-decoration:none; display:inline-block; }
    .filter-btn:hover { border-color:var(--prc-violet); color:var(--prc-violet); background:rgba(139,126,255,0.08); }
    .filter-btn.active   { border-color:var(--prc-violet); color:var(--prc-violet); background:rgba(139,126,255,0.14); box-shadow:0 0 12px rgba(139,126,255,0.22); }
    .filter-btn.active-g { border-color:#44FF88; color:#44FF88; background:rgba(68,255,136,0.10); }
    .filter-btn.active-r { border-color:#FF5050; color:#FF5050; background:rgba(255,80,80,0.08); }
    .filter-btn.active-b { border-color:#44D9FF; color:#44D9FF; background:rgba(68,217,255,0.08); }
    .filter-btn.active-a { border-color:var(--creo-amber); color:var(--creo-amber); background:rgba(255,160,48,0.10); }
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
    .data-table { width:100%; border-collapse:collapse; min-width:900px; }
    .data-table th { font-family:var(--font-hud); font-size:0.48rem; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:var(--text-dim); padding:10px 14px; border-bottom:1px solid var(--border-neon); text-align:left; white-space:nowrap; cursor:pointer; user-select:none; transition:color 0.2s; }
    .data-table th:hover { color:var(--prc-violet); }
    .data-table th.sorted { color:var(--prc-violet); }
    .data-table th .sort-arrow { margin-left:4px; opacity:0.45; font-size:0.55rem; }
    .data-table th.sorted .sort-arrow { opacity:1; }
    .data-table td { font-size:0.82rem; color:var(--text-mid); padding:11px 14px; border-bottom:1px solid rgba(139,126,255,0.07); vertical-align:middle; }
    .data-table tbody tr { transition:background 0.15s; cursor:pointer; }
    .data-table tbody tr:hover { background:rgba(139,126,255,0.05); }
    .data-table tbody tr:hover td { color:var(--text-high); }
    .data-table tbody tr:last-child td { border-bottom:none; }
    .data-table tbody tr td:last-child { cursor:default; }

    /* participant cell */
    .td-participant { display:flex; align-items:center; gap:10px; }
    .td-avatar { width:34px; height:34px; border-radius:50%; background:rgba(139,126,255,0.14); border:1px solid rgba(139,126,255,0.28); display:flex; align-items:center; justify-content:center; font-family:var(--font-hud); font-size:0.52rem; font-weight:700; color:var(--prc-violet); flex-shrink:0; }
    .td-name { font-weight:600; color:var(--text-high); }
    .td-sub  { font-size:0.72rem; color:var(--text-dim); margin-top:2px; }

    /* attendance toggle */
    .attendance-toggle { display:inline-flex; align-items:center; gap:7px; font-family:var(--font-hud); font-size:0.48rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; padding:5px 12px; border-radius:2px; cursor:pointer; transition:var(--transition); border:none; clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%); }
    .attendance-toggle.present { background:rgba(68,255,136,0.10); color:#44FF88; border:1px solid rgba(68,255,136,0.28); }
    .attendance-toggle.present:hover { background:rgba(68,255,136,0.22); box-shadow:0 0 10px rgba(68,255,136,0.30); }
    .attendance-toggle.absent  { background:rgba(139,126,255,0.06); color:var(--text-dim); border:1px solid rgba(139,126,255,0.18); }
    .attendance-toggle.absent:hover  { background:rgba(139,126,255,0.12); color:var(--prc-violet); border-color:var(--prc-violet); }
    .attendance-dot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }
    .present .attendance-dot { background:#44FF88; box-shadow:0 0 6px rgba(68,255,136,0.80); }
    .absent  .attendance-dot { background:var(--text-dim); }

    /* badges */
    .pill { display:inline-flex; align-items:center; gap:5px; font-family:var(--font-hud); font-size:0.48rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; padding:3px 10px; clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%); white-space:nowrap; }
    .pill-dot { width:5px; height:5px; border-radius:50%; }
    .pill.confirmed  { background:rgba(68,255,136,0.10);  color:#44FF88;           border:1px solid rgba(68,255,136,0.25); }
    .pill.confirmed  .pill-dot { background:#44FF88; box-shadow:0 0 6px rgba(68,255,136,0.70); }
    .pill.pending    { background:rgba(255,233,48,0.10);  color:var(--creo-volt);  border:1px solid rgba(255,233,48,0.25); }
    .pill.pending    .pill-dot { background:var(--creo-volt); box-shadow:0 0 6px rgba(255,233,48,0.70); }
    .pill.processing { background:rgba(255,160,48,0.10);  color:var(--creo-amber); border:1px solid rgba(255,160,48,0.25); }
    .pill.processing .pill-dot { background:var(--creo-amber); box-shadow:0 0 6px rgba(255,160,48,0.70); }
    .pill.cancelled  { background:rgba(255,80,80,0.10);   color:#FF5050;           border:1px solid rgba(255,80,80,0.25); }
    .pill.cancelled  .pill-dot { background:#FF5050; box-shadow:0 0 6px rgba(255,80,80,0.70); }
    .pill.proof      { background:rgba(68,217,255,0.10);  color:#44D9FF;           border:1px solid rgba(68,217,255,0.25); }
    .pill.proof      .pill-dot { background:#44D9FF; box-shadow:0 0 6px rgba(68,217,255,0.70); }

    .track-badge { font-family:var(--font-hud); font-size:0.46rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; padding:2px 9px; border-radius:2px; }
    .track-rv { background:rgba(139,126,255,0.12); color:var(--prc-violet); border:1px solid rgba(139,126,255,0.25); }
    .track-mx { background:rgba(68,217,255,0.08);  color:#44D9FF;           border:1px solid rgba(68,217,255,0.22); }
    .track-ds { background:rgba(255,160,48,0.10);  color:var(--creo-amber); border:1px solid rgba(255,160,48,0.25); }

    .grade-badge { font-family:var(--font-hud); font-size:0.46rem; font-weight:700; letter-spacing:0.06em; padding:2px 9px; border-radius:2px; background:rgba(196,238,255,0.06); color:var(--prc-ice); border:1px solid rgba(196,238,255,0.14); white-space:nowrap; }
    .ref-badge { font-family:var(--font-hud); font-size:0.52rem; font-weight:700; color:var(--creo-volt); letter-spacing:0.08em; }

    /* action buttons */
    .tbl-btn { width:28px; height:28px; background:rgba(139,126,255,0.05); border:1px solid var(--border-neon) !important; border-radius:2px; display:inline-flex; align-items:center; justify-content:center; color:var(--text-dim); transition:var(--transition); font-size:0.80rem; cursor:pointer; text-decoration:none; }
    .tbl-btn:hover { background:rgba(139,126,255,0.16); color:var(--prc-violet); border-color:var(--prc-violet) !important; }
    .tbl-actions { display:flex; gap:4px; }

    /* ── MODAL ── */
    .modal-overlay { position:fixed; inset:0; z-index:9000; background:rgba(3,2,13,0.90); backdrop-filter:blur(8px); display:none; align-items:center; justify-content:center; padding:24px; }
    .modal-overlay.open { display:flex; }
    .modal { background:var(--bg-deep); border:1px solid var(--border-neon); max-width:620px; width:100%; max-height:90vh; overflow-y:auto; position:relative; }
    .modal::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg,transparent,var(--prc-violet),var(--prc-ice),transparent); }
    .modal-hdr { padding:18px 24px 14px; border-bottom:1px solid var(--border-neon); background:rgba(139,126,255,0.06); display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
    .modal-hdr-left { display:flex; align-items:center; gap:14px; }
    .modal-avatar { width:48px; height:48px; border-radius:50%; background:rgba(139,126,255,0.14); border:1px solid rgba(139,126,255,0.30); display:flex; align-items:center; justify-content:center; font-family:var(--font-hud); font-size:0.80rem; font-weight:800; color:var(--prc-violet); flex-shrink:0; text-shadow:0 0 10px rgba(139,126,255,0.60); }
    .modal-title { font-family:var(--font-hud); font-size:0.80rem; font-weight:700; letter-spacing:0.06em; color:var(--text-high); }
    .modal-title-sub { font-family:var(--font-hud); font-size:0.48rem; color:var(--text-dim); letter-spacing:0.10em; margin-top:4px; }
    .modal-close { width:30px; height:30px; background:rgba(139,126,255,0.06); border:1px solid rgba(139,126,255,0.20) !important; color:var(--text-soft); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:0.90rem; transition:var(--transition); border-radius:2px; flex-shrink:0; }
    .modal-close:hover { background:rgba(139,126,255,0.14); color:var(--prc-violet); }
    .modal-body { padding:22px 24px; }
    .modal-section-label { font-family:var(--font-hud); font-size:0.52rem; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:var(--text-dim); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
    .modal-section-label::before { content:'//'; color:rgba(139,126,255,0.35); }
    .modal-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; margin-bottom:20px; }
    .modal-field { display:flex; flex-direction:column; gap:4px; }
    .modal-field.full { grid-column:1/-1; }
    .modal-field-label { font-family:var(--font-hud); font-size:0.46rem; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:var(--text-soft); }
    .modal-field-val { font-size:0.875rem; color:var(--text-high); font-weight:500; word-break:break-word; }

    /* presence card inside modal */
    .presence-card { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border:1px solid rgba(139,126,255,0.16); background:rgba(139,126,255,0.04); margin-bottom:20px; flex-wrap:wrap; gap:12px; }
    .presence-card-label { font-family:var(--font-hud); font-size:0.52rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--text-soft); }
    .presence-card-label span { color:var(--text-dim); font-size:0.46rem; display:block; margin-top:3px; letter-spacing:0.08em; }
    .modal-footer { display:flex; gap:10px; flex-wrap:wrap; padding-top:18px; border-top:1px solid rgba(139,126,255,0.12); }

    /* ── PAGINATION ── */
    .pagination { display:flex; align-items:center; justify-content:space-between; padding:14px 20px; border-top:1px solid var(--border-neon); flex-wrap:wrap; gap:10px; }
    .page-info { font-family:var(--font-hud); font-size:0.50rem; color:var(--text-dim); letter-spacing:0.08em; }
    .page-info span { color:var(--prc-violet); }
    .page-btns { display:flex; gap:4px; flex-wrap:wrap; }
    .page-btn { min-width:30px; height:30px; padding:0 8px; background:rgba(139,126,255,0.05); border:1px solid rgba(139,126,255,0.18) !important; border-radius:2px; display:inline-flex; align-items:center; justify-content:center; font-family:var(--font-hud); font-size:0.52rem; color:var(--text-soft); cursor:pointer; transition:var(--transition); text-decoration:none; }
    .page-btn:hover { background:rgba(139,126,255,0.14); color:var(--prc-violet); border-color:var(--prc-violet) !important; }
    .page-btn.active { background:rgba(139,126,255,0.20); color:var(--prc-violet); border-color:var(--prc-violet) !important; box-shadow:0 0 10px rgba(139,126,255,0.25); pointer-events:none; }
    .page-btn.disabled { opacity:0.35; pointer-events:none; }

    /* ── TOAST ── */
    .toast-container { position:fixed; bottom:28px; right:28px; display:flex; flex-direction:column; gap:10px; z-index:9999; pointer-events:none; }
    .toast { background:var(--bg-deep); border:1px solid var(--border-neon); padding:12px 20px; font-family:var(--font-hud); font-size:0.58rem; font-weight:700; letter-spacing:0.08em; color:var(--text-mid); display:flex; align-items:center; gap:10px; min-width:260px; animation:toastIn 0.3s ease; pointer-events:all; }
    .toast.success { border-color:rgba(68,255,136,0.40); color:#44FF88; }
    .toast.error   { border-color:rgba(255,80,80,0.40);  color:#FF5050; }
    .toast.info    { border-color:rgba(139,126,255,0.40); color:var(--prc-violet); }
    @keyframes toastIn { from{opacity:0;transform:translateX(16px);}to{opacity:1;transform:translateX(0);} }

    /* ── ANIMATIONS ── */
    @keyframes fadeInUp { from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);} }
    .anim-1{animation:fadeInUp .40s ease .05s both;}
    .anim-2{animation:fadeInUp .40s ease .12s both;}
    .anim-3{animation:fadeInUp .40s ease .19s both;}
    .anim-4{animation:fadeInUp .40s ease .26s both;}

    /* ── EMPTY STATE ── */
    .empty-state { text-align:center; padding:60px 20px; }
    .empty-state i { font-size:2.5rem; color:rgba(139,126,255,0.25); display:block; margin-bottom:16px; }
    .empty-state-title { font-family:var(--font-hud); font-size:0.78rem; font-weight:700; color:var(--text-soft); margin-bottom:8px; }
    .empty-state-sub { font-size:0.875rem; color:var(--text-dim); }

    /* ── MISC ── */
    ::-webkit-scrollbar { width:3px; height:3px; }
    ::-webkit-scrollbar-track { background:var(--bg-void); }
    ::-webkit-scrollbar-thumb { background:rgba(139,126,255,0.30); border-radius:2px; }

    @media(max-width:1400px){ .kpi-strip{grid-template-columns:repeat(3,1fr);} }
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
      <span>Participants</span>
    </div>
    <div class="topbar-right">
      <form class="topbar-search-form" method="GET" action="">
        <?php foreach (['track'=>$track_filter,'grade'=>$grade_filter,'present'=>$present_filter,'sort'=>$sort_col,'dir'=>$sort_dir,'pp'=>$per_page] as $k=>$v): ?>
          <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>" />
        <?php endforeach; ?>
        <i class="fi fi-rr-search"></i>
        <input type="text" name="search" placeholder="Search name, team, school, grade…"
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
        <div class="page-eyebrow">PRC 2026 // Player Management</div>
        <h1 class="page-title">All <span class="accent">Participants</span></h1>
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
        <div class="kpi-mini-label">Total Players</div>
      </div>
      <div class="kpi-mini kpi-g">
        <span class="kpi-mini-num"><?= intval($kpi['present']) ?></span>
        <div class="kpi-mini-label">Present</div>
      </div>
      <div class="kpi-mini kpi-r">
        <span class="kpi-mini-num"><?= intval($kpi['absent']) ?></span>
        <div class="kpi-mini-label">Absent</div>
      </div>
      <div class="kpi-mini kpi-v">
        <span class="kpi-mini-num"><?= intval($kpi['roboventure']) ?></span>
        <div class="kpi-mini-label">RoboVenture</div>
      </div>
      <div class="kpi-mini kpi-b">
        <span class="kpi-mini-num"><?= intval($kpi['makex']) ?></span>
        <div class="kpi-mini-label">MakeX</div>
      </div>
      <div class="kpi-mini kpi-a">
        <span class="kpi-mini-num"><?= intval($kpi['drone_soccer']) ?></span>
        <div class="kpi-mini-label">Drone Soccer</div>
      </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar anim-3">
      <span class="filter-label">Track</span>
      <div class="filter-group">
        <?php
        $trackBtns = [
          'all'          => ['All Tracks',   'active'],
          'RoboVenture'  => ['RoboVenture',  'active'],
          'MakeX'        => ['MakeX',        'active-b'],
          'Drone Soccer' => ['Drone Soccer', 'active-a'],
        ];
        foreach ($trackBtns as $val => [$label, $cls]):
          $isActive = ($track_filter === $val);
        ?>
          <a href="<?= filterUrl($base, ['track'=>$val,'grade'=>$grade_filter,'present'=>$present_filter]) ?>"
             class="filter-btn <?= $isActive ? $cls : '' ?>">
            <?= h($label) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="filter-sep"></div>
      <span class="filter-label">Attendance</span>
      <div class="filter-group">
        <?php
        $presBtns = [
          'all'     => ['All',     'active'],
          'present' => ['Present', 'active-g'],
          'absent'  => ['Absent',  'active-r'],
        ];
        foreach ($presBtns as $val => [$label, $cls]):
          $isActive = ($present_filter === $val);
        ?>
          <a href="<?= filterUrl($base, ['present'=>$val,'track'=>$track_filter,'grade'=>$grade_filter]) ?>"
             class="filter-btn <?= $isActive ? $cls : '' ?>">
            <?= h($label) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if ($grades): ?>
      <div class="filter-sep"></div>
      <span class="filter-label">Grade</span>
      <div class="filter-group">
        <a href="<?= filterUrl($base, ['grade'=>'all','track'=>$track_filter,'present'=>$present_filter]) ?>"
           class="filter-btn <?= $grade_filter === 'all' ? 'active' : '' ?>">All</a>
        <?php foreach ($grades as $g): ?>
          <a href="<?= filterUrl($base, ['grade'=>$g,'track'=>$track_filter,'present'=>$present_filter]) ?>"
             class="filter-btn <?= $grade_filter === $g ? 'active' : '' ?>">
            <?= h($g) ?>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="filter-right">
        <form method="GET" id="pp-form" style="display:contents;">
          <?php foreach (['track'=>$track_filter,'grade'=>$grade_filter,'present'=>$present_filter,'search'=>$search,'sort'=>$sort_col,'dir'=>$sort_dir,'page'=>$page] as $k=>$v): ?>
            <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>" />
          <?php endforeach; ?>
          <select class="filter-select" name="pp" onchange="document.getElementById('pp-form').submit()">
            <?php foreach ([10,25,50,100] as $opt): ?>
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
          <i class="fi fi-rr-user"></i>
          Player Roster
        </div>
        <div class="panel-meta">
          Showing <span><?= count($records) ?></span> of <span><?= $total_filtered ?></span> filtered
          &nbsp;·&nbsp; <span><?= intval($kpi['total']) ?></span> total players
        </div>
      </div>

      <?php if (empty($records)): ?>
        <div class="empty-state">
          <i class="fi fi-rr-user-slash"></i>
          <div class="empty-state-title">No participants found</div>
          <div class="empty-state-sub">Try adjusting your filters or search query.</div>
        </div>
      <?php else: ?>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <?php
              $sp = ['track'=>$track_filter,'grade'=>$grade_filter,'present'=>$present_filter,'search'=>$search,'pp'=>$per_page,'page'=>$page];
              echo sortLink('player_name',  'Participant',   $sort_col, $sort_dir, $sp);
              echo sortLink('player_grade', 'Grade',         $sort_col, $sort_dir, $sp);
              echo sortLink('birthdate',    'Age / Birthday',$sort_col, $sort_dir, $sp);
              echo sortLink('team_name',    'Team',          $sort_col, $sort_dir, $sp);
              echo sortLink('category',     'Category',      $sort_col, $sort_dir, $sp);
              echo sortLink('school_name',  'School',        $sort_col, $sort_dir, $sp);
              echo sortLink('present',      'Attendance',    $sort_col, $sort_dir, $sp);
              ?>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $r):
              $track    = getTrack($r['category']);
              $tCls     = trackCls($track);
              $sCls     = $r['payment_status'] ? statusCls($r['payment_status']) : '';
              $isPresent = (bool)$r['player_ispresent'];
              $age      = calcAge($r['player_birthdate']);
              $initials = strtoupper(mb_substr(preg_replace('/[^A-Za-z ]/','', $r['player_name']), 0, 2));

              $modal_data = json_encode([
                'player_id'      => $r['player_id'],
                'name'           => $r['player_name'],
                'grade'          => $r['player_grade']     ?? '',
                'birthdate'      => $r['player_birthdate'] ?? '',
                'age'            => $age,
                'present'        => $isPresent,
                'team'           => $r['team_name'],
                'team_id'        => $r['team_id'],
                'category'       => $r['category'],
                'track'          => $track,
                'package'        => $r['package'],
                'school'         => $r['school_name'],
                'region'         => $r['school_region'],
                'ref'            => $r['ref_no']         ?? '',
                'pay_status'     => $r['payment_status'] ?? '',
                'reg_status'     => $r['reg_status']     ?? '',
                'contact_name'   => $r['contact_name']   ?? '',
                'contact_email'  => $r['contact_email']  ?? '',
                'contact_phone'  => $r['contact_phone']  ?? '',
              ]);
            ?>
            <tr onclick='openModal(<?= htmlspecialchars(json_encode($modal_data), ENT_QUOTES) ?>)'>

              <!-- Participant -->
              <td>
                <div class="td-participant">
                  <div class="td-avatar"><?= h($initials) ?></div>
                  <div>
                    <div class="td-name"><?= h($r['player_name']) ?></div>
                    <?php if ($r['contact_name']): ?>
                      <div class="td-sub">via <?= h($r['contact_name']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>

              <!-- Grade -->
              <td>
                <?php if ($r['player_grade']): ?>
                  <span class="grade-badge"><?= h($r['player_grade']) ?></span>
                <?php else: ?>
                  <span style="color:var(--text-dim);font-size:0.76rem;">—</span>
                <?php endif; ?>
              </td>

              <!-- Age / Birthday -->
              <td>
                <?php if ($r['player_birthdate']): ?>
                  <div style="font-family:var(--font-hud);font-size:0.62rem;font-weight:700;color:var(--text-high);">
                    <?= $age ?> <span style="color:var(--text-dim);font-weight:400;">yrs</span>
                  </div>
                  <div style="font-size:0.72rem;color:var(--text-dim);margin-top:1px;">
                    <?= date('M d, Y', strtotime($r['player_birthdate'])) ?>
                  </div>
                <?php else: ?>
                  <span style="color:var(--text-dim);font-size:0.76rem;">—</span>
                <?php endif; ?>
              </td>

              <!-- Team -->
              <td>
                <div style="font-weight:600;color:var(--text-high);font-size:0.82rem;"><?= h($r['team_name']) ?></div>
                <?php if ($r['ref_no']): ?>
                  <div style="margin-top:2px;"><span class="ref-badge" style="font-size:0.46rem;"><?= h($r['ref_no']) ?></span></div>
                <?php endif; ?>
              </td>

              <!-- Category -->
              <td>
                <span class="track-badge <?= $tCls ?>"><?= h($r['category']) ?></span>
              </td>

              <!-- School -->
              <td>
                <div style="font-size:0.80rem;color:var(--text-mid);"><?= h($r['school_name']) ?></div>
                <div style="font-size:0.70rem;color:var(--text-dim);margin-top:1px;"><?= h($r['school_region']) ?></div>
              </td>

              <!-- Attendance -->
              <td>
                <button class="attendance-toggle <?= $isPresent ? 'present' : 'absent' ?>"
                        id="att-<?= $r['player_id'] ?>"
                        data-id="<?= $r['player_id'] ?>"
                        data-present="<?= $isPresent ? '1' : '0' ?>"
                        onclick="event.stopPropagation(); toggleAttendance(<?= $r['player_id'] ?>, this)">
                  <span class="attendance-dot"></span>
                  <span class="att-label"><?= $isPresent ? 'Present' : 'Absent' ?></span>
                </button>
              </td>

              <!-- Actions -->
              <td onclick="event.stopPropagation()">
                <div class="tbl-actions">
                  <button class="tbl-btn" title="View participant details"
                          onclick='openModal(<?= htmlspecialchars(json_encode($modal_data), ENT_QUOTES) ?>)'>
                    <i class="fi fi-rr-eye"></i>
                  </button>
                  <?php if ($r['ref_no']): ?>
                  <a class="tbl-btn" title="View registration"
                     href="admin-registrations.php?search=<?= urlencode($r['ref_no']) ?>">
                    <i class="fi fi-rr-file-check"></i>
                  </a>
                  <?php endif; ?>
                  <a class="tbl-btn" title="View team"
                     href="admin-teams.php?search=<?= urlencode($r['team_name']) ?>">
                    <i class="fi fi-rr-robot"></i>
                  </a>
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
          &nbsp;·&nbsp; <span><?= $total_filtered ?></span> participants
        </div>
        <div class="page-btns">
          <?php
          $prevClass = $page <= 1 ? 'disabled' : '';
          echo "<a class='page-btn $prevClass' href='?" . http_build_query(array_merge($link_params,['page'=>max(1,$page-1)])) . "'>&lsaquo;</a>";
          $start = max(1, $page - 2);
          $end   = min($total_pages, $start + 4);
          for ($p = $start; $p <= $end; $p++) {
              $cls = ($p === $page) ? 'active' : '';
              echo "<a class='page-btn $cls' href='?" . http_build_query(array_merge($link_params,['page'=>$p])) . "'>$p</a>";
          }
          $nextClass = $page >= $total_pages ? 'disabled' : '';
          echo "<a class='page-btn $nextClass' href='?" . http_build_query(array_merge($link_params,['page'=>min($total_pages,$page+1)])) . "'>&rsaquo;</a>";
          ?>
        </div>
      </div>
      <?php endif; ?>

      <?php endif; ?>
    </div><!-- /panel -->

  </main>
</div><!-- /admin-shell -->

<!-- PARTICIPANT MODAL -->
<div class="modal-overlay" id="modal-overlay" onclick="closeModalOutside(event)">
  <div class="modal">
    <div class="modal-hdr">
      <div class="modal-hdr-left">
        <div class="modal-avatar" id="modal-avatar">—</div>
        <div>
          <div class="modal-title" id="modal-title">Participant</div>
          <div class="modal-title-sub" id="modal-sub"></div>
        </div>
      </div>
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
  var d = (typeof jsonStr === 'string') ? JSON.parse(jsonStr) : jsonStr;
  var initials = d.name.replace(/[^A-Za-z ]/g,'').trim().substring(0,2).toUpperCase() || '?';

  document.getElementById('modal-avatar').textContent = initials;
  document.getElementById('modal-title').textContent  = d.name;
  document.getElementById('modal-sub').textContent    = d.school + '  ·  ' + d.region;

  var presClass = d.present ? 'present' : 'absent';
  var presLabel = d.present ? 'Present' : 'Absent';
  var presIcon  = d.present ? 'fi-rr-check-circle' : 'fi-rr-circle';

  document.getElementById('modal-body').innerHTML =
    // Attendance card at top
    '<div class="presence-card">' +
      '<div class="presence-card-label">Attendance Status' +
        '<span>Click to toggle on competition day</span>' +
      '</div>' +
      '<button class="attendance-toggle ' + presClass + '" id="modal-att-btn" ' +
              'data-id="' + d.player_id + '" ' +
              'onclick="toggleAttendance(' + d.player_id + ', this)">' +
        '<span class="attendance-dot"></span>' +
        '<span class="att-label">' + presLabel + '</span>' +
      '</button>' +
    '</div>' +

    '<div class="modal-section-label">Player Info</div>' +
    '<div class="modal-grid">' +
      mf('Full Name',    d.name) +
      mf('Grade Level',  d.grade  || '—') +
      mf('Birthdate',    d.birthdate ? formatDate(d.birthdate) : '—') +
      mf('Age',          d.age !== null ? d.age + ' years old' : '—') +
    '</div>' +

    '<div class="modal-section-label">Team & Competition</div>' +
    '<div class="modal-grid">' +
      mf('Team Name', d.team) +
      mf('School',    d.school) +
      mf('Region',    d.region) +
      mf('Category',  '<span class="track-badge ' + trackClsJs(d.category) + '">' + escHtml(d.category) + '</span>', false, true) +
      mf('Track',     d.track) +
      mf('Package',   d.package || '—') +
      (d.ref ? mf('Reference No.', '<span class="ref-badge">' + escHtml(d.ref) + '</span>', false, true) : '') +
      (d.pay_status ? mf('Pay Status', '<span class="pill ' + statusClsJs(d.pay_status) + '"><span class="pill-dot"></span>' + escHtml(d.pay_status) + '</span>', false, true) : '') +
    '</div>' +

    (d.contact_name ?
      '<div class="modal-section-label">Contact Person</div>' +
      '<div class="modal-grid">' +
        mf('Name',   d.contact_name) +
        mf('Email',  d.contact_email || '—') +
        mf('Phone',  d.contact_phone || '—') +
      '</div>'
    : '') +

    '<div class="modal-footer">' +
      (d.ref ? '<a class="btn btn-primary btn-sm" href="admin-registrations.php?search=' + encodeURIComponent(d.ref) + '">'
             + '<i class="fi fi-rr-file-check"></i> View Registration</a>' : '') +
      '<a class="btn btn-ghost btn-sm" href="admin-teams.php?search=' + encodeURIComponent(d.team) + '">'
      + '<i class="fi fi-rr-robot"></i> View Team</a>' +
      '<button class="btn btn-ghost btn-sm" onclick="closeModal()">Close</button>' +
    '</div>';

  document.getElementById('modal-overlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  document.getElementById('modal-overlay').classList.remove('open');
  document.body.style.overflow = '';
}
function closeModalOutside(e) {
  if (e.target === document.getElementById('modal-overlay')) closeModal();
}
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });

// ── ATTENDANCE TOGGLE ──
function toggleAttendance(playerId, btn) {
  var fd = new FormData();
  fd.append('action', 'toggle_present');
  fd.append('player_id', playerId);

  fetch(window.location.pathname, { method:'POST', body:fd })
    .then(function(r){ return r.json(); })
    .then(function(res) {
      if (!res.ok) { toast('Failed to update attendance', 'error'); return; }

      var isNowPresent = res.present == 1;

      // Update every button/element with this player's id on the page
      document.querySelectorAll('[data-id="' + playerId + '"]').forEach(function(el) {
        el.classList.toggle('present', isNowPresent);
        el.classList.toggle('absent',  !isNowPresent);
        el.dataset.present = isNowPresent ? '1' : '0';
        var label = el.querySelector('.att-label');
        if (label) label.textContent = isNowPresent ? 'Present' : 'Absent';
        var dot = el.querySelector('.attendance-dot');
        // dot styling handled by CSS classes above
      });

      toast(isNowPresent ? 'Marked as Present' : 'Marked as Absent',
            isNowPresent ? 'success' : 'info');
    })
    .catch(function(){ toast('Network error — please try again.', 'error'); });
}

// ── EXPORT CSV ──
function exportCSV() {
  var rows = document.querySelectorAll('.data-table tbody tr');
  if (!rows.length) { toast('No records to export', 'error'); return; }
  var headers = ['Name','Grade','Age','Birthdate','Team','Ref No.','Category','School','Region','Attendance'];
  var lines = [headers.join(',')];
  rows.forEach(function(row) {
    var cells = row.querySelectorAll('td');
    var name      = cells[0]?.querySelector('.td-name')?.textContent.trim() || '';
    var grade     = cells[1]?.textContent.trim() || '';
    var ageLine   = cells[2]?.querySelector('div')?.textContent.trim() || '';
    var bday      = cells[2]?.querySelectorAll('div')?.[1]?.textContent.trim() || '';
    var team      = cells[3]?.querySelector('div')?.textContent.trim() || '';
    var ref       = cells[3]?.querySelector('.ref-badge')?.textContent.trim() || '';
    var cat       = cells[4]?.textContent.trim() || '';
    var school    = cells[5]?.querySelector('div')?.textContent.trim() || '';
    var region    = cells[5]?.querySelectorAll('div')?.[1]?.textContent.trim() || '';
    var att       = cells[6]?.querySelector('.att-label')?.textContent.trim() || '';
    lines.push([name,grade,ageLine,bday,team,ref,cat,school,region,att].map(function(v){
      return '"' + String(v).replace(/"/g,'""') + '"';
    }).join(','));
  });
  var blob = new Blob([lines.join('\n')], {type:'text/csv'});
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'PRC-2026-Participants-' + new Date().toISOString().slice(0,10) + '.csv';
  a.click();
  toast('Exported ' + rows.length + ' participants', 'success');
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
    t.style.opacity    = '0';
    setTimeout(function(){ t.remove(); }, 400);
  }, 3000);
}

// ── HELPERS ──
function mf(label, value, full, raw) {
  return '<div class="modal-field' + (full ? ' full' : '') + '">'
    + '<div class="modal-field-label">' + escHtml(label) + '</div>'
    + '<div class="modal-field-val">'   + (raw ? value : escHtml(String(value || '—'))) + '</div>'
    + '</div>';
}
function escHtml(str) {
  return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function trackClsJs(cat) {
  if (!cat) return 'track-rv';
  if (cat.startsWith('MakeX'))  return 'track-mx';
  if (cat === 'Drone Soccer')   return 'track-ds';
  return 'track-rv';
}
function statusClsJs(s) {
  var m = {Confirmed:'confirmed','Proof Submitted':'proof','Pending Payment':'pending',Rejected:'cancelled'};
  return m[s] || 'processing';
}
function formatDate(str) {
  if (!str) return '—';
  var d = new Date(str);
  var mo = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  return mo[d.getUTCMonth()] + ' ' + String(d.getUTCDate()).padStart(2,'0') + ', ' + d.getUTCFullYear();
}
</script>

</body>
</html>