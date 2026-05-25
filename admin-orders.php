<?php
// PRC-WebApp/admin-orders.php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'prc_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die('<p style="color:#ff6b6b;padding:40px;font-family:monospace;">DB error: ' . htmlspecialchars($conn->connect_error) . '</p>');
}

// ── GOOGLE SHEETS WEB APP URL ────────────────────────────────────
// Replace with your deployed Apps Script Web App URL
define('GSHEET_WEBHOOK_URL', 'https://script.google.com/macros/s/AKfycbz8KBnsnWYLAflfa7bsAEhQXN9UusyWPJsIJ9KqdrE3W_KFHhrNboagfaEz_YlK-xna/exec');

// ── Google Sheets Sync Helper ────────────────────────────────────
function syncToSheets(string $action, array $payload): void {
    if (!defined('GSHEET_WEBHOOK_URL') || str_contains(GSHEET_WEBHOOK_URL, 'YOUR_DEPLOYMENT')) return;
    $payload['action'] = $action;
    $ch = curl_init(GSHEET_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function set_flash($t, $m) { $_SESSION['oflash'] = ['type' => $t, 'msg' => $m]; }
function get_flash() { if (!empty($_SESSION['oflash'])) { $f = $_SESSION['oflash']; unset($_SESSION['oflash']); return $f; } return null; }

// ── HANDLE PROOF IMAGE SERVING ──────────────────────────────
if (isset($_GET['proof']) && is_numeric($_GET['proof'])) {
    global $conn;
    $oid = (int)$_GET['proof'];
    $row = $conn->query("SELECT proof_data, proof_mimetype, proof_filename FROM prc_shop_orders WHERE order_id = $oid")->fetch_assoc();
    if ($row && $row['proof_data']) {
        header('Content-Type: ' . $row['proof_mimetype']);
        header('Content-Disposition: inline; filename="' . basename($row['proof_filename']) . '"');
        echo $row['proof_data'];
        $conn->close();
        exit;
    }
    http_response_code(404);
    exit;
}

// ── HANDLE POST ACTIONS ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── UPDATE ORDER STATUS ──
    if ($action === 'update_status') {
        $oid    = (int)($_POST['order_id'] ?? 0);
        $status = trim($_POST['order_status'] ?? '');
        $note   = trim($_POST['admin_note'] ?? '');
        $valid  = ['pending', 'processing', 'confirmed', 'shipped', 'completed', 'cancelled'];
        if ($oid && in_array($status, $valid)) {
            $s = $conn->prepare("UPDATE prc_shop_orders SET order_status=?, admin_note=?, updated_at=NOW() WHERE order_id=?");
            $s->bind_param('ssi', $status, $note, $oid);
            if ($s->execute()) {
                set_flash('success', 'Order status updated.');
                // Sync to Google Sheets
                $refRow = $conn->query("SELECT order_ref FROM prc_shop_orders WHERE order_id = $oid")->fetch_assoc();
                if ($refRow) {
                    syncToSheets('update_status', [
                        'order_ref'    => $refRow['order_ref'],
                        'order_status' => $status,
                        'admin_note'   => $note,
                    ]);
                }
            } else {
                set_flash('error', 'Update failed.');
            }
            $s->close();
        }
    }

    // ── DELETE ORDER ──
    if ($action === 'delete_order') {
        $oid = (int)($_POST['order_id'] ?? 0);
        if ($oid) {
            $refRow = $conn->query("SELECT order_ref FROM prc_shop_orders WHERE order_id = $oid")->fetch_assoc();
            $conn->query("DELETE FROM prc_shop_order_items WHERE order_id = $oid");
            $conn->query("DELETE FROM prc_shop_orders WHERE order_id = $oid");
            set_flash('success', 'Order deleted.');
            // Sync to Google Sheets
            if ($refRow) {
                syncToSheets('delete_order', ['order_ref' => $refRow['order_ref']]);
            }
        }
    }

    // ── BULK STATUS UPDATE ──
    if ($action === 'bulk_status') {
        $ids    = json_decode($_POST['order_ids'] ?? '[]', true);
        $status = trim($_POST['bulk_status'] ?? '');
        $valid  = ['pending', 'processing', 'confirmed', 'shipped', 'completed', 'cancelled'];
        if ($ids && in_array($status, $valid)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $stmt = $conn->prepare("UPDATE prc_shop_orders SET order_status=?, updated_at=NOW() WHERE order_id IN ($placeholders)");
            $params = array_merge([$status], $ids);
            $refs = [];
            $refs[] = &$status;
            foreach ($ids as &$id) $refs[] = &$id;
            call_user_func_array([$stmt, 'bind_param'], array_merge(["s$types"], $refs));
            $stmt->execute();
            $stmt->close();
            set_flash('success', count($ids) . ' order(s) updated to "' . $status . '".');
            // Sync each to Google Sheets
            $idList = implode(',', array_map('intval', $ids));
            $refRows = $conn->query("SELECT order_ref FROM prc_shop_orders WHERE order_id IN ($idList)");
            if ($refRows) {
                while ($r = $refRows->fetch_assoc()) {
                    syncToSheets('update_status', [
                        'order_ref'    => $r['order_ref'],
                        'order_status' => $status,
                        'admin_note'   => '',
                    ]);
                }
            }
        }
    }

    header('Location: admin-orders.php');
    exit;
}

// ── FETCH ORDERS ─────────────────────────────────────────────
$orders = [];
$or = $conn->query("
    SELECT o.*,
           COUNT(i.item_id) AS item_count,
           (o.proof_data IS NOT NULL AND LENGTH(o.proof_data) > 0) AS has_proof
    FROM prc_shop_orders o
    LEFT JOIN prc_shop_order_items i ON i.order_id = o.order_id
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
");
if ($or) while ($row = $or->fetch_assoc()) $orders[] = $row;

// ── FETCH ITEMS PER ORDER ────────────────────────────────────
$items_by_order = [];
$ir = $conn->query("SELECT * FROM prc_shop_order_items ORDER BY order_id ASC, item_id ASC");
if ($ir) while ($row = $ir->fetch_assoc()) $items_by_order[$row['order_id']][] = $row;

// ── STATS ────────────────────────────────────────────────────
$stat_total     = count($orders);
$stat_pending   = 0; $stat_confirmed = 0; $stat_processing = 0;
$stat_completed = 0; $stat_cancelled  = 0; $stat_revenue = 0;
foreach ($orders as $o) {
    $stat_revenue += (float)$o['total_amount'];
    switch ($o['order_status']) {
        case 'pending':    $stat_pending++;    break;
        case 'processing': $stat_processing++; break;
        case 'confirmed':  $stat_confirmed++;  break;
        case 'completed':  $stat_completed++;  break;
        case 'cancelled':  $stat_cancelled++;  break;
    }
}

$flash = get_flash();
$conn->close();

// ── STATUS HELPERS ──────────────────────────────────────────
function status_cls($s) {
    return match($s) {
        'confirmed'  => 'confirmed',
        'completed'  => 'completed',
        'processing' => 'processing',
        'shipped'    => 'shipped',
        'cancelled'  => 'cancelled',
        default      => 'pending',
    };
}
function status_label($s) {
    return match($s) {
        'pending'    => 'Pending',
        'processing' => 'Processing',
        'confirmed'  => 'Confirmed',
        'shipped'    => 'Shipped',
        'completed'  => 'Completed',
        'cancelled'  => 'Cancelled',
        default      => ucfirst($s),
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Orders — PRC Admin</title>
  <link rel="icon" type="image/png" href="assets/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css'>
  <style>
    :root {
      --sb-width:248px; --sb-collapsed:68px; --topbar-h:60px;
      --bg-void:#03020D; --bg-deep:#06051A; --bg-card:rgba(10,8,30,0.80);
      --prc-violet:#8B7EFF; --prc-ice:#C4EEFF;
      --creo-amber:#FFA030; --creo-volt:#FFE930; --creo-sky:#44D9FF;
      --admin-green:#44FF88; --admin-red:#FF4D6A;
      --border-neon:rgba(139,126,255,0.18);
      --text-high:#F2EEFF; --text-mid:#C8C0F0; --text-soft:#9A90CC; --text-dim:#6058A0;
      --font-hud:'Orbitron',monospace; --font-body:'Exo 2',sans-serif;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html{scroll-behavior:smooth}
    body{font-family:var(--font-body);background:var(--bg-void);color:var(--text-high);overflow-x:hidden;line-height:1.6;min-height:100vh;cursor:none}
    a{text-decoration:none;color:inherit} ul{list-style:none}
    button{font-family:inherit;border:none;background:none;cursor:none}
    img{max-width:100%;display:block}

    body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(rgba(139,126,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(139,126,255,0.03) 1px,transparent 1px);background-size:44px 44px}
    body::after{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;background:repeating-linear-gradient(to bottom,transparent,transparent 2px,rgba(0,0,0,0.025) 2px,rgba(0,0,0,0.025) 4px)}

    /* CURSOR */
    .cursor-dot{position:fixed;width:8px;height:8px;border-radius:50%;background:var(--prc-violet);pointer-events:none;z-index:99999;transform:translate(-50%,-50%);box-shadow:0 0 18px rgba(139,126,255,.80)}
    .cursor-ring{position:fixed;width:36px;height:36px;border-radius:50%;border:1px solid rgba(139,126,255,.60);pointer-events:none;z-index:99998;transform:translate(-50%,-50%);transition:width .25s,height .25s}
    .cursor-ring.hovered{width:52px;height:52px;border-color:var(--creo-amber)}

    /* SHELL */
    .admin-shell{display:grid;grid-template-columns:var(--sb-width) 1fr;grid-template-rows:var(--topbar-h) 1fr;min-height:100vh;position:relative;z-index:1;transition:grid-template-columns .30s cubic-bezier(.77,0,.175,1)}
    .admin-shell.sb-collapsed{grid-template-columns:var(--sb-collapsed) 1fr}
    .admin-sidebar-slot{grid-row:1/-1;grid-column:1}

    /* TOPBAR */
    .admin-topbar{grid-column:2;grid-row:1;height:var(--topbar-h);background:rgba(3,2,13,0.92);backdrop-filter:blur(20px);border-bottom:1px solid var(--border-neon);display:flex;align-items:center;padding:0 28px;gap:16px;position:sticky;top:0;z-index:800;box-shadow:0 1px 30px rgba(139,126,255,0.07)}
    .topbar-breadcrumb{display:flex;align-items:center;gap:8px;font-family:var(--font-hud);font-size:0.60rem;color:var(--text-dim);letter-spacing:0.10em;text-transform:uppercase}
    .topbar-breadcrumb a{color:var(--text-dim);transition:color 0.2s}
    .topbar-breadcrumb a:hover{color:var(--prc-violet)}
    .topbar-breadcrumb span{color:var(--creo-amber)}
    .topbar-right{margin-left:auto;display:flex;align-items:center;gap:10px}
    .topbar-search{display:flex;align-items:center;gap:8px;background:rgba(139,126,255,0.06);border:1px solid var(--border-neon);padding:6px 14px;transition:all .25s}
    .topbar-search:focus-within{border-color:var(--prc-violet);box-shadow:0 0 14px rgba(139,126,255,0.22)}
    .topbar-search input{background:none;border:none;outline:none;font-family:var(--font-body);font-size:0.80rem;color:var(--text-mid);width:200px}
    .topbar-search input::placeholder{color:var(--text-dim)}
    .topbar-search i{color:var(--text-dim);font-size:0.85rem}
    .topbar-icon-btn{width:36px;height:36px;background:rgba(139,126,255,0.05);border:1px solid var(--border-neon)!important;border-radius:3px;display:flex;align-items:center;justify-content:center;color:var(--text-soft);transition:all .25s;cursor:none}
    .topbar-icon-btn:hover{background:rgba(139,126,255,0.14);color:var(--prc-violet)}
    .topbar-date{font-family:var(--font-hud);font-size:0.52rem;color:var(--text-dim);letter-spacing:0.10em;white-space:nowrap}
    .topbar-date span{color:var(--creo-volt)}

    /* MAIN */
    .admin-main{grid-column:2;grid-row:2;padding:28px 28px 60px;overflow-y:auto;min-height:calc(100vh - var(--topbar-h))}

    /* PAGE HEADER */
    .page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:24px}
    .page-eyebrow{font-family:var(--font-hud);font-size:0.52rem;letter-spacing:0.20em;text-transform:uppercase;color:var(--creo-amber);margin-bottom:6px;display:flex;align-items:center;gap:8px}
    .page-eyebrow::before{content:'//';color:rgba(255,160,48,0.38)}
    .dot-live{width:7px;height:7px;background:var(--creo-amber);border-radius:50%;box-shadow:0 0 8px rgba(255,160,48,0.90);animation:pulse 1s ease-in-out infinite}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:.7}}
    .page-title{font-family:var(--font-hud);font-size:clamp(1.3rem,3vw,2rem);font-weight:900;color:#fff;letter-spacing:-0.01em}
    .page-title .accent{color:var(--creo-amber);text-shadow:0 0 22px rgba(255,160,48,0.70)}
    .page-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}

    /* BUTTONS */
    .btn{display:inline-flex;align-items:center;gap:8px;font-family:var(--font-hud);font-size:0.60rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:9px 20px;transition:all .25s;white-space:nowrap;cursor:none}
    .btn i{font-size:0.90rem}
    .btn-primary{background:rgba(139,126,255,0.10);border:1px solid var(--prc-violet)!important;color:var(--prc-violet);clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);box-shadow:0 0 14px rgba(139,126,255,0.18)}
    .btn-primary:hover{background:rgba(139,126,255,0.22);color:#fff;box-shadow:0 0 28px rgba(139,126,255,0.40)}
    .btn-amber{background:rgba(255,160,48,0.08);border:1px solid rgba(255,160,48,0.40)!important;color:var(--creo-amber);clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
    .btn-amber:hover{background:rgba(255,160,48,0.18);color:#fff;box-shadow:0 0 24px rgba(255,160,48,0.35)}
    .btn-volt{background:rgba(255,233,48,0.06);border:1px solid rgba(255,233,48,0.35)!important;color:var(--creo-volt);clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
    .btn-volt:hover{background:rgba(255,233,48,0.16);color:#fff}
    .btn-ghost{background:rgba(139,126,255,0.04);border:1px solid var(--border-neon)!important;color:var(--text-soft)}
    .btn-ghost:hover{background:rgba(139,126,255,0.10);color:var(--text-mid)}
    .btn-red{background:rgba(255,77,106,0.06);border:1px solid rgba(255,77,106,0.35)!important;color:var(--admin-red);clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
    .btn-red:hover{background:rgba(255,77,106,0.18);color:#fff}
    .btn-sm{padding:6px 14px;font-size:0.54rem;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%)}

    /* KPI STRIP */
    .kpi-strip{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:22px}
    .kpi-mini{background:var(--bg-card);border:1px solid var(--border-neon);padding:14px 16px;position:relative;overflow:hidden;clip-path:polygon(0 0,calc(100% - 8px) 0,100% 8px,100% 100%,8px 100%,0 calc(100% - 8px))}
    .kpi-mini::before{content:'';position:absolute;top:0;left:0;right:0;height:1px}
    .kpi-mini.ka::before{background:linear-gradient(90deg,transparent,var(--creo-amber),transparent)}
    .kpi-mini.kv::before{background:linear-gradient(90deg,transparent,var(--prc-violet),transparent)}
    .kpi-mini.kg::before{background:linear-gradient(90deg,transparent,var(--admin-green),transparent)}
    .kpi-mini.ky::before{background:linear-gradient(90deg,transparent,var(--creo-volt),transparent)}
    .kpi-mini.kr::before{background:linear-gradient(90deg,transparent,var(--admin-red),transparent)}
    .kpi-mini.ks::before{background:linear-gradient(90deg,transparent,var(--creo-sky),transparent)}
    .kpi-mini-num{font-family:var(--font-hud);font-size:1.55rem;font-weight:800;line-height:1;display:block}
    .kpi-mini.ka .kpi-mini-num{color:var(--creo-amber);text-shadow:0 0 16px rgba(255,160,48,0.55)}
    .kpi-mini.kv .kpi-mini-num{color:var(--prc-violet);text-shadow:0 0 16px rgba(139,126,255,0.55)}
    .kpi-mini.kg .kpi-mini-num{color:var(--admin-green);text-shadow:0 0 16px rgba(68,255,136,0.55)}
    .kpi-mini.ky .kpi-mini-num{color:var(--creo-volt);text-shadow:0 0 16px rgba(255,233,48,0.55)}
    .kpi-mini.kr .kpi-mini-num{color:var(--admin-red);text-shadow:0 0 16px rgba(255,77,106,0.55)}
    .kpi-mini.ks .kpi-mini-num{color:var(--creo-sky);text-shadow:0 0 16px rgba(68,217,255,0.55)}
    .kpi-mini-label{font-family:var(--font-hud);font-size:0.46rem;color:var(--text-soft);letter-spacing:0.10em;text-transform:uppercase;margin-top:5px}

    /* FLASH */
    .flash-wrap{margin-bottom:20px}
    .flash-inner{padding:13px 20px;display:flex;align-items:center;gap:10px;font-family:var(--font-hud);font-size:0.63rem;font-weight:600;letter-spacing:0.08em;border:1px solid}
    .flash-inner.success{color:var(--admin-green);border-color:rgba(68,255,136,0.35);background:rgba(68,255,136,0.06)}
    .flash-inner.error{color:var(--admin-red);border-color:rgba(255,77,106,0.35);background:rgba(255,77,106,0.06)}

    /* FILTER BAR */
    .filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:18px;padding:14px 18px;background:var(--bg-card);border:1px solid var(--border-neon);position:relative;overflow:hidden}
    .filter-bar::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--creo-amber),transparent);opacity:0.35}
    .filter-label{font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:var(--text-dim);white-space:nowrap}
    .filter-label::before{content:'//';margin-right:6px;color:rgba(255,160,48,0.30)}
    .filter-group{display:flex;gap:6px;flex-wrap:wrap}
    .filter-btn{font-family:var(--font-hud);font-size:0.54rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:6px 14px;border:1px solid rgba(139,126,255,0.20);color:var(--text-soft);background:transparent;clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%);transition:all .22s;cursor:none}
    .filter-btn:hover{border-color:var(--creo-amber);color:var(--creo-amber);background:rgba(255,160,48,0.08)}
    .filter-btn.active{border-color:var(--creo-amber);color:var(--creo-amber);background:rgba(255,160,48,0.12);box-shadow:0 0 12px rgba(255,160,48,0.20)}
    .filter-sep{width:1px;height:26px;background:rgba(139,126,255,0.14);margin:0 4px;flex-shrink:0}
    .filter-right{margin-left:auto;display:flex;gap:8px;align-items:center}
    .filter-select{background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.22);color:var(--text-mid);font-family:var(--font-hud);font-size:0.52rem;letter-spacing:0.06em;padding:6px 12px;outline:none;cursor:none}
    .filter-select:focus{border-color:var(--prc-violet)}
    .filter-select option{background:var(--bg-deep);color:var(--text-high)}

    /* BULK BAR */
    .bulk-bar{display:none;align-items:center;gap:12px;padding:11px 18px;margin-bottom:12px;background:rgba(255,160,48,0.06);border:1px solid rgba(255,160,48,0.25);position:relative;overflow:hidden}
    .bulk-bar::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--creo-amber),transparent)}
    .bulk-bar.visible{display:flex}
    .bulk-count{font-family:var(--font-hud);font-size:0.58rem;font-weight:700;color:var(--creo-amber);letter-spacing:0.08em;white-space:nowrap}
    .bulk-select{background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.22);color:var(--text-mid);font-family:var(--font-hud);font-size:0.52rem;padding:5px 10px;outline:none}

    /* PANEL */
    .panel{background:var(--bg-card);border:1px solid var(--border-neon);position:relative;overflow:hidden}
    .panel::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--creo-amber),transparent);opacity:0.50}
    .panel-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--border-neon);background:rgba(255,160,48,0.03);flex-wrap:wrap;gap:10px}
    .panel-title{font-family:var(--font-hud);font-size:0.65rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--prc-ice);display:flex;align-items:center;gap:8px}
    .panel-title i{color:var(--creo-amber);font-size:0.90rem}
    .panel-meta{font-family:var(--font-hud);font-size:0.50rem;color:var(--text-dim);letter-spacing:0.08em}
    .panel-meta span{color:var(--creo-amber)}

    /* TABLE */
    .table-wrap{overflow-x:auto}
    .data-table{width:100%;border-collapse:collapse;min-width:1000px}
    .data-table th{font-family:var(--font-hud);font-size:0.48rem;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:var(--text-dim);padding:10px 14px;border-bottom:1px solid var(--border-neon);text-align:left;white-space:nowrap;user-select:none;cursor:none}
    .data-table th.sortable{cursor:none}
    .data-table th:hover{color:var(--creo-amber)}
    .data-table th.sorted{color:var(--creo-amber)}
    .data-table th .sort-arr{margin-left:4px;opacity:0.40;font-size:0.52rem}
    .data-table th.sorted .sort-arr{opacity:1}
    .data-table td{font-size:0.82rem;color:var(--text-mid);padding:12px 14px;border-bottom:1px solid rgba(139,126,255,0.07);vertical-align:middle}
    .data-table tbody tr{transition:background 0.15s}
    .data-table tbody tr:hover{background:rgba(255,160,48,0.03)}
    .data-table tbody tr.selected-row{background:rgba(255,160,48,0.06)!important}
    .data-table tbody tr:last-child td{border-bottom:none}

    /* CHECKBOX */
    .cb-wrap{display:flex;align-items:center;justify-content:center}
    input[type="checkbox"]{appearance:none;width:15px;height:15px;border:1px solid rgba(139,126,255,0.35);background:transparent;cursor:none;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s;position:relative}
    input[type="checkbox"]:checked{background:var(--creo-amber);border-color:var(--creo-amber)}
    input[type="checkbox"]:checked::after{content:'✓';position:absolute;color:#000;font-size:9px;font-weight:900;line-height:1;top:50%;left:50%;transform:translate(-50%,-50%)}

    /* REF */
    .ref-badge{font-family:var(--font-hud);font-size:0.54rem;font-weight:700;color:var(--creo-volt);letter-spacing:0.06em}

    /* ORDER CELL */
    .td-customer{display:flex;align-items:center;gap:10px}
    .td-avatar{width:32px;height:32px;background:rgba(255,160,48,0.10);border:1px solid rgba(255,160,48,0.22);display:flex;align-items:center;justify-content:center;font-family:var(--font-hud);font-size:0.48rem;font-weight:700;color:var(--creo-amber);flex-shrink:0}
    .td-name{font-weight:600;color:var(--text-high);font-size:0.84rem}
    .td-sub{font-size:0.72rem;color:var(--text-dim);margin-top:2px}

    /* STATUS PILLS */
    .pill{display:inline-flex;align-items:center;gap:5px;font-family:var(--font-hud);font-size:0.46rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:3px 10px;clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%);white-space:nowrap;border:1px solid}
    .pill-dot{width:5px;height:5px;border-radius:50%;flex-shrink:0}
    .pill.pending{background:rgba(255,233,48,0.08);color:var(--creo-volt);border-color:rgba(255,233,48,0.25)}
    .pill.pending .pill-dot{background:var(--creo-volt);box-shadow:0 0 6px rgba(255,233,48,.70);animation:pulse 1.2s ease-in-out infinite}
    .pill.processing{background:rgba(255,160,48,0.10);color:var(--creo-amber);border-color:rgba(255,160,48,0.28)}
    .pill.processing .pill-dot{background:var(--creo-amber);box-shadow:0 0 6px rgba(255,160,48,.70)}
    .pill.confirmed{background:rgba(68,217,255,0.10);color:var(--creo-sky);border-color:rgba(68,217,255,0.25)}
    .pill.confirmed .pill-dot{background:var(--creo-sky);box-shadow:0 0 6px rgba(68,217,255,.70)}
    .pill.shipped{background:rgba(139,126,255,0.10);color:var(--prc-violet);border-color:rgba(139,126,255,0.25)}
    .pill.shipped .pill-dot{background:var(--prc-violet);box-shadow:0 0 6px rgba(139,126,255,.70)}
    .pill.completed{background:rgba(68,255,136,0.10);color:var(--admin-green);border-color:rgba(68,255,136,0.25)}
    .pill.completed .pill-dot{background:var(--admin-green);box-shadow:0 0 6px rgba(68,255,136,.70)}
    .pill.cancelled{background:rgba(255,77,106,0.10);color:var(--admin-red);border-color:rgba(255,77,106,0.25)}
    .pill.cancelled .pill-dot{background:var(--admin-red);box-shadow:0 0 6px rgba(255,77,106,.70)}

    /* PAYMENT */
    .pay-badge{font-family:var(--font-hud);font-size:0.46rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;padding:2px 9px;border:1px solid}
    .pay-gcash{background:rgba(0,124,255,0.10);color:#007CFF;border-color:rgba(0,124,255,0.28)}
    .pay-bank{background:rgba(255,160,48,0.08);color:var(--creo-amber);border-color:rgba(255,160,48,0.22)}

    /* PROOF BADGE */
    .proof-badge{display:inline-flex;align-items:center;gap:5px;font-family:var(--font-hud);font-size:0.44rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;padding:2px 8px;border:1px solid;cursor:none}
    .proof-badge.has{color:var(--admin-green);border-color:rgba(68,255,136,0.28);background:rgba(68,255,136,0.06)}
    .proof-badge.has:hover{background:rgba(68,255,136,0.16)}
    .proof-badge.none{color:var(--text-dim);border-color:rgba(139,126,255,0.14);background:rgba(139,126,255,0.03)}

    /* ICON BUTTONS */
    .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:1px solid;border-radius:2px;cursor:none;transition:all .20s;background:transparent;font-size:0.80rem}
    .icon-btn.view{color:var(--creo-sky);border-color:rgba(68,217,255,0.28);background:rgba(68,217,255,0.04)}
    .icon-btn.view:hover{background:rgba(68,217,255,0.14);box-shadow:0 0 10px rgba(68,217,255,0.28)}
    .icon-btn.edit{color:var(--prc-violet);border-color:rgba(139,126,255,0.28);background:rgba(139,126,255,0.04)}
    .icon-btn.edit:hover{background:rgba(139,126,255,0.16);box-shadow:0 0 10px rgba(139,126,255,0.28)}
    .icon-btn.del{color:var(--admin-red);border-color:rgba(255,77,106,0.28);background:rgba(255,77,106,0.04)}
    .icon-btn.del:hover{background:rgba(255,77,106,0.16);box-shadow:0 0 10px rgba(255,77,106,0.28)}
    .icon-btn.confirm{color:var(--admin-green);border-color:rgba(68,255,136,0.28);background:rgba(68,255,136,0.04)}
    .icon-btn.confirm:hover{background:rgba(68,255,136,0.14);box-shadow:0 0 10px rgba(68,255,136,0.28)}
    .tbl-actions{display:flex;gap:4px}

    /* PAGINATION */
    .pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid var(--border-neon);flex-wrap:wrap;gap:10px}
    .page-info{font-family:var(--font-hud);font-size:0.50rem;color:var(--text-dim);letter-spacing:0.08em}
    .page-info span{color:var(--creo-amber)}
    .page-btns{display:flex;gap:4px}
    .page-btn{width:30px;height:30px;background:rgba(139,126,255,0.05);border:1px solid rgba(139,126,255,0.18)!important;display:flex;align-items:center;justify-content:center;font-family:var(--font-hud);font-size:0.52rem;color:var(--text-soft);cursor:none;transition:all .22s}
    .page-btn:hover{background:rgba(255,160,48,0.12);color:var(--creo-amber);border-color:rgba(255,160,48,0.40)!important}
    .page-btn.active{background:rgba(255,160,48,0.16);color:var(--creo-amber);border-color:rgba(255,160,48,0.50)!important;box-shadow:0 0 10px rgba(255,160,48,0.22)}

    /* MODALS */
    .modal-overlay{display:none;position:fixed;inset:0;z-index:9000;background:rgba(3,2,13,0.90);backdrop-filter:blur(10px);align-items:center;justify-content:center;padding:20px;overflow-y:auto}
    .modal-overlay.open{display:flex}
    .modal-box{background:var(--bg-deep);border:1px solid var(--border-neon);width:100%;position:relative;max-height:90vh;overflow-y:auto;margin:auto}
    .modal-box::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--creo-amber),transparent)}
    .modal-box.sm{max-width:440px}
    .modal-box.md{max-width:640px}
    .modal-box.lg{max-width:800px}
    .modal-hdr{padding:16px 22px 13px;border-bottom:1px solid rgba(255,160,48,0.15);background:rgba(255,160,48,0.05);display:flex;align-items:center;justify-content:space-between;gap:12px}
    .modal-hdr h3{font-family:var(--font-hud);font-size:0.72rem;font-weight:700;letter-spacing:0.08em;color:var(--creo-amber)}
    .modal-close{width:28px;height:28px;background:rgba(255,160,48,0.06);border:1px solid rgba(255,160,48,0.22)!important;color:var(--text-soft);display:flex;align-items:center;justify-content:center;cursor:none;font-size:0.72rem;transition:all .2s}
    .modal-close:hover{background:rgba(255,160,48,0.16);color:var(--creo-amber)}
    .modal-body{padding:22px}
    .modal-footer{padding:14px 22px;border-top:1px solid rgba(139,126,255,0.12);display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}

    /* DETAIL MODAL GRID */
    .detail-section-lbl{font-family:var(--font-hud);font-size:0.52rem;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:var(--text-dim);margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid rgba(139,126,255,0.12);display:flex;align-items:center;gap:8px}
    .detail-section-lbl::before{content:'//';color:rgba(255,160,48,0.35)}
    .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 20px;margin-bottom:20px}
    .detail-field{display:flex;flex-direction:column;gap:4px}
    .detail-field.full{grid-column:1/-1}
    .detail-field-lbl{font-family:var(--font-hud);font-size:0.46rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-soft)}
    .detail-field-val{font-size:0.875rem;color:var(--text-high);font-weight:500}

    /* ITEMS TABLE IN MODAL */
    .items-table{width:100%;border-collapse:collapse;margin-bottom:16px}
    .items-table th{font-family:var(--font-hud);font-size:0.46rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-soft);padding:8px 12px;background:rgba(255,160,48,0.05);border-bottom:1px solid rgba(255,160,48,0.12);text-align:left}
    .items-table td{padding:10px 12px;border-bottom:1px solid rgba(139,126,255,0.07);font-size:0.84rem;color:var(--text-mid)}
    .items-table tr:last-child td{border-bottom:none}
    .items-total{display:flex;justify-content:flex-end;padding:10px 12px;border-top:1px solid rgba(255,160,48,0.18);font-family:var(--font-hud)}
    .items-total-lbl{font-size:0.50rem;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.12em;margin-right:16px;align-self:flex-end}
    .items-total-val{font-size:1.10rem;font-weight:800;color:var(--creo-amber);text-shadow:0 0 14px rgba(255,160,48,0.50)}

    /* EDIT STATUS FORM */
    .field{margin-bottom:14px}
    .field-label{font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-soft);margin-bottom:7px;display:block}
    .field-label .req{color:var(--creo-amber)}
    .field-input,.field-select,.field-textarea{width:100%;padding:10px 13px;background:rgba(255,160,48,0.04);border:1px solid rgba(255,160,48,0.22);color:var(--text-high);font-family:var(--font-body);font-size:0.88rem;outline:none;transition:border-color .22s,box-shadow .22s}
    .field-input::placeholder,.field-textarea::placeholder{color:var(--text-dim)}
    .field-input:focus,.field-select:focus,.field-textarea:focus{border-color:var(--creo-amber);box-shadow:0 0 0 2px rgba(255,160,48,0.12)}
    .field-textarea{resize:vertical;min-height:70px}

    /* CONFIRM */
    .confirm-icon{font-size:2rem;color:var(--admin-red);display:block;margin-bottom:12px}
    .confirm-title{font-family:var(--font-hud);font-size:0.88rem;font-weight:800;color:#fff;margin-bottom:8px}
    .confirm-text{font-size:0.875rem;color:var(--text-mid);line-height:1.70}

    /* PROOF MODAL */
    .proof-img-wrap{background:rgba(0,0,0,0.30);border:1px solid rgba(139,126,255,0.18);display:flex;align-items:center;justify-content:center;overflow:hidden;max-height:70vh}
    .proof-img-wrap img{max-width:100%;max-height:70vh;object-fit:contain}
    .proof-pdf-link{display:flex;align-items:center;gap:10px;padding:18px 20px;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.18);font-family:var(--font-hud);font-size:0.62rem;color:var(--prc-violet)}

    /* EMPTY */
    .empty-state{text-align:center;padding:60px 20px}
    .empty-state i{font-size:2.5rem;color:rgba(255,160,48,0.18);display:block;margin-bottom:16px}
    .empty-state-title{font-family:var(--font-hud);font-size:0.78rem;font-weight:700;color:var(--text-soft);margin-bottom:8px}
    .empty-state-sub{font-size:0.875rem;color:var(--text-dim)}

    /* TOAST */
    .toast-wrap{position:fixed;bottom:28px;right:28px;display:flex;flex-direction:column;gap:10px;z-index:99999}
    .toast{background:var(--bg-deep);border:1px solid var(--border-neon);padding:12px 20px;font-family:var(--font-hud);font-size:0.58rem;font-weight:700;letter-spacing:0.08em;display:flex;align-items:center;gap:10px;min-width:260px;animation:toastIn .3s ease}
    .toast.success{border-color:rgba(68,255,136,0.40);color:var(--admin-green)}
    .toast.error{border-color:rgba(255,77,106,0.40);color:var(--admin-red)}
    @keyframes toastIn{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:translateX(0)}}

    /* ANIM */
    @keyframes fadeInUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
    .anim-1{animation:fadeInUp .40s ease .04s both}
    .anim-2{animation:fadeInUp .40s ease .10s both}
    .anim-3{animation:fadeInUp .40s ease .16s both}
    .anim-4{animation:fadeInUp .40s ease .22s both}

    /* SCROLL */
    ::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-track{background:var(--bg-void)}::-webkit-scrollbar-thumb{background:var(--creo-amber);opacity:0.5;border-radius:2px}

    /* RESPONSIVE */
    @media(max-width:1280px){.kpi-strip{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:900px){.admin-shell{grid-template-columns:0 1fr}.admin-sidebar-slot{display:none}.admin-main{padding:18px 16px 48px}.topbar-search{display:none}body{cursor:auto}button{cursor:pointer}.cursor-dot,.cursor-ring{display:none}}
    @media(max-width:600px){.kpi-strip{grid-template-columns:1fr 1fr}.page-header{flex-direction:column}.detail-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="cursor-dot" id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<div class="admin-shell" id="adminShell">
  <div class="admin-sidebar-slot" id="sidebarSlot"></div>

  <!-- TOPBAR -->
  <header class="admin-topbar">
    <div class="topbar-breadcrumb">
      <a href="admin-dashboard.html">PRC Admin</a>
      <i class="fi fi-rr-angle-right" style="font-size:.55rem"></i>
      <span>Orders</span>
    </div>
    <div class="topbar-right">
      <div class="topbar-search">
        <i class="fi fi-rr-search"></i>
        <input type="text" id="topbar-search-input" placeholder="Search ref, customer, email…" oninput="searchTable(this.value)"/>
      </div>
      <button class="topbar-icon-btn" title="Refresh" onclick="location.reload()"><i class="fi fi-rr-refresh"></i></button>
      <a class="topbar-icon-btn" 
        title="Open Google Sheet" 
        href="https://docs.google.com/spreadsheets/d/1nPI_oyAVqbWvJgeBO5aa-81hxipRVIKQ7cMOBDdSono/edit?gid=1646797515#gid=1646797515" 
        target="_blank">
        <i class="fi fi-rr-download"></i>
      </a>
      <div class="topbar-date"><span id="topbar-date-display"></span></div>
    </div>
  </header>

  <!-- MAIN -->
  <main class="admin-main">

    <!-- PAGE HEADER -->
    <div class="page-header anim-1">
      <div>
        <div class="page-eyebrow"><span class="dot-live"></span> Shop Management // Orders</div>
        <h1 class="page-title">Shop <span class="accent">Orders</span></h1>
      </div>
      <div class="page-actions">
        <a href="shop.php" target="_blank" class="btn btn-ghost btn-sm"><i class="fi fi-rr-eye"></i> View Shop</a>
        <a class="btn btn-volt btn-sm" 
          href="https://docs.google.com/spreadsheets/d/1nPI_oyAVqbWvJgeBO5aa-81hxipRVIKQ7cMOBDdSono/edit?gid=1646797515#gid=1646797515" 
          target="_blank">
          <i class="fi fi-rr-download"></i> Go to Google Sheets
        </a>
      </div>
    </div>

    <!-- FLASH -->
    <?php if ($flash): ?>
    <div class="flash-wrap anim-1">
      <div class="flash-inner <?= $flash['type'] ?>">
        <i class="fi fi-rr-<?= $flash['type'] === 'success' ? 'check' : 'exclamation' ?>"></i>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- KPI STRIP -->
    <div class="kpi-strip anim-2">
      <div class="kpi-mini ka">
        <span class="kpi-mini-num"><?= $stat_total ?></span>
        <div class="kpi-mini-label">Total Orders</div>
      </div>
      <div class="kpi-mini ky">
        <span class="kpi-mini-num"><?= $stat_pending ?></span>
        <div class="kpi-mini-label">Pending</div>
      </div>
      <div class="kpi-mini ka">
        <span class="kpi-mini-num"><?= $stat_processing ?></span>
        <div class="kpi-mini-label">Processing</div>
      </div>
      <div class="kpi-mini ks">
        <span class="kpi-mini-num"><?= $stat_confirmed ?></span>
        <div class="kpi-mini-label">Confirmed</div>
      </div>
      <div class="kpi-mini kg">
        <span class="kpi-mini-num"><?= $stat_completed ?></span>
        <div class="kpi-mini-label">Completed</div>
      </div>
      <div class="kpi-mini kv">
        <span class="kpi-mini-num" style="font-size:1.10rem;">&#8369;<?= number_format($stat_revenue, 0) ?></span>
        <div class="kpi-mini-label">Total Revenue</div>
      </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar anim-3">
      <span class="filter-label">Status</span>
      <div class="filter-group">
        <button class="filter-btn active" data-status="all"        onclick="setStatusFilter('all',this)">All</button>
        <button class="filter-btn" data-status="pending"           onclick="setStatusFilter('pending',this)">Pending</button>
        <button class="filter-btn" data-status="processing"        onclick="setStatusFilter('processing',this)">Processing</button>
        <button class="filter-btn" data-status="confirmed"         onclick="setStatusFilter('confirmed',this)">Confirmed</button>
        <button class="filter-btn" data-status="shipped"           onclick="setStatusFilter('shipped',this)">Shipped</button>
        <button class="filter-btn" data-status="completed"         onclick="setStatusFilter('completed',this)">Completed</button>
        <button class="filter-btn" data-status="cancelled"         onclick="setStatusFilter('cancelled',this)">Cancelled</button>
      </div>
      <div class="filter-sep"></div>
      <div class="filter-group">
        <button class="filter-btn active" data-pay="all"           onclick="setPayFilter('all',this)">All Payments</button>
        <button class="filter-btn" data-pay="GCash"                onclick="setPayFilter('GCash',this)">GCash</button>
        <button class="filter-btn" data-pay="Bank Transfer"        onclick="setPayFilter('Bank Transfer',this)">Bank Transfer</button>
      </div>
      <div class="filter-right">
        <select class="filter-select" id="sort-select" onchange="applySort(this.value)">
          <option value="date-desc">Newest First</option>
          <option value="date-asc">Oldest First</option>
          <option value="amount-desc">Amount ↓</option>
          <option value="amount-asc">Amount ↑</option>
          <option value="name-asc">Name A–Z</option>
        </select>
        <select class="filter-select" id="per-page-select" onchange="setPerPage(+this.value)">
          <option value="10">10 / page</option>
          <option value="25" selected>25 / page</option>
          <option value="50">50 / page</option>
        </select>
      </div>
    </div>

    <!-- BULK ACTION BAR -->
    <div class="bulk-bar" id="bulk-bar">
      <span class="bulk-count"><span id="bulk-count-num">0</span> selected</span>
      <select class="bulk-select" id="bulk-status-select">
        <option value="">— Set Status —</option>
        <option value="processing">Processing</option>
        <option value="confirmed">Confirmed</option>
        <option value="shipped">Shipped</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <button class="btn btn-amber btn-sm" onclick="applyBulk()"><i class="fi fi-rr-check"></i> Apply</button>
      <button class="btn btn-ghost btn-sm" onclick="clearSelection()"><i class="fi fi-rr-cross"></i> Clear</button>
    </div>

    <!-- TABLE PANEL -->
    <div class="panel anim-4">
      <div class="panel-header">
        <div class="panel-title"><i class="fi fi-rr-shopping-bag"></i> Order Records</div>
        <div class="panel-meta">Showing <span id="showing-count">—</span> of <span id="total-count"><?= $stat_total ?></span> orders</div>
      </div>

      <div class="table-wrap">
        <table class="data-table" id="orders-table">
          <thead>
            <tr>
              <th style="width:38px;"><div class="cb-wrap"><input type="checkbox" id="select-all" onchange="toggleSelectAll(this)"/></div></th>
              <th class="sortable" onclick="sortBy('ref')">Ref No. <span class="sort-arr">↕</span></th>
              <th class="sortable" onclick="sortBy('customer')">Customer <span class="sort-arr">↕</span></th>
              <th class="sortable" onclick="sortBy('items')">Items <span class="sort-arr">↕</span></th>
              <th class="sortable" onclick="sortBy('payment')">Payment <span class="sort-arr">↕</span></th>
              <th class="sortable" onclick="sortBy('amount')">Amount <span class="sort-arr">↕</span></th>
              <th>Proof</th>
              <th class="sortable" onclick="sortBy('status')">Status <span class="sort-arr">↕</span></th>
              <th class="sortable" onclick="sortBy('date')">Date <span class="sort-arr">↕</span></th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="orders-tbody">
            <?php foreach ($orders as $idx => $o):
              $oid    = $o['order_id'];
              $scls   = status_cls($o['order_status']);
              $slbl   = status_label($o['order_status']);
              $payCls = $o['payment_method'] === 'GCash' ? 'pay-gcash' : 'pay-bank';
              $initials = strtoupper(substr($o['customer_name'], 0, 1) . (strpos($o['customer_name'], ' ') ? substr($o['customer_name'], strpos($o['customer_name'], ' ') + 1, 1) : ''));
              $items = $items_by_order[$oid] ?? [];
              $itemNames = implode(', ', array_map(fn($i) => $i['product_name'] . ' x' . $i['quantity'], $items));
              $dateStr = $o['created_at'] ? date('M d, Y', strtotime($o['created_at'])) : '—';
              $dateTimeStr = $o['created_at'] ? date('M d, Y H:i', strtotime($o['created_at'])) : '—';
            ?>
            <tr data-order-id="<?= $oid ?>"
                data-status="<?= htmlspecialchars($o['order_status']) ?>"
                data-pay="<?= htmlspecialchars($o['payment_method']) ?>"
                data-search="<?= strtolower(htmlspecialchars($o['order_ref'] . ' ' . $o['customer_name'] . ' ' . $o['customer_email'] . ' ' . $o['school_org'])) ?>"
                data-amount="<?= (float)$o['total_amount'] ?>"
                data-date="<?= strtotime($o['created_at'] ?? '0') ?>"
                data-ref="<?= htmlspecialchars($o['order_ref']) ?>">
              <td><div class="cb-wrap"><input type="checkbox" class="row-cb" data-id="<?= $oid ?>" onchange="onRowCheck()"/></div></td>
              <td><span class="ref-badge"><?= htmlspecialchars($o['order_ref']) ?></span></td>
              <td>
                <div class="td-customer">
                  <div class="td-avatar"><?= htmlspecialchars($initials) ?></div>
                  <div>
                    <div class="td-name"><?= htmlspecialchars($o['customer_name']) ?></div>
                    <div class="td-sub"><?= htmlspecialchars($o['school_org']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span style="font-family:var(--font-hud);font-size:0.58rem;color:var(--creo-amber);font-weight:700;"><?= $o['item_count'] ?></span>
                <span style="font-size:0.74rem;color:var(--text-dim);margin-left:4px;"><?= $o['item_count'] == 1 ? 'item' : 'items' ?></span>
                <?php if ($items): ?>
                <div style="font-size:0.70rem;color:var(--text-dim);margin-top:2px;max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($itemNames) ?>"><?= htmlspecialchars(mb_strimwidth($itemNames, 0, 40, '…')) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="pay-badge <?= $payCls ?>"><?= htmlspecialchars($o['payment_method']) ?></span></td>
              <td><span style="font-family:var(--font-hud);font-size:0.68rem;font-weight:800;color:var(--creo-amber);">&#8369;<?= number_format((float)$o['total_amount'], 0) ?></span></td>
              <td>
                <?php if ($o['has_proof']): ?>
                  <button class="proof-badge has" onclick="viewProof(<?= $oid ?>)" title="View proof of payment"><i class="fi fi-rr-file-check"></i> View</button>
                <?php else: ?>
                  <span class="proof-badge none"><i class="fi fi-rr-file-slash"></i> None</span>
                <?php endif; ?>
              </td>
              <td><span class="pill <?= $scls ?>"><span class="pill-dot"></span><?= $slbl ?></span></td>
              <td style="font-size:0.76rem;color:var(--text-dim);white-space:nowrap;"><?= $dateStr ?></td>
              <td>
                <div class="tbl-actions">
                  <button class="icon-btn view" title="View details"
                    onclick="openDetail(<?= htmlspecialchars(json_encode([
                      'order_id'       => $oid,
                      'order_ref'      => $o['order_ref'],
                      'customer_name'  => $o['customer_name'],
                      'customer_email' => $o['customer_email'],
                      'customer_phone' => $o['customer_phone'],
                      'school_org'     => $o['school_org'],
                      'notes'          => $o['notes'],
                      'payment_method' => $o['payment_method'],
                      'total_amount'   => $o['total_amount'],
                      'order_status'   => $o['order_status'],
                      'admin_note'     => $o['admin_note'] ?? '',
                      'created_at'     => $dateTimeStr,
                      'has_proof'      => (bool)$o['has_proof'],
                      'items'          => $items,
                    ])) ?>)">
                    <i class="fi fi-rr-eye"></i>
                  </button>
                  <button class="icon-btn edit" title="Edit status"
                    onclick="openEdit(<?= $oid ?>,'<?= htmlspecialchars(addslashes($o['order_ref'])) ?>','<?= $o['order_status'] ?>','<?= htmlspecialchars(addslashes($o['admin_note'] ?? '')) ?>')">
                    <i class="fi fi-rr-edit"></i>
                  </button>
                  <?php if ($o['order_status'] === 'pending' || $o['order_status'] === 'processing'): ?>
                  <button class="icon-btn confirm" title="Quick confirm"
                    onclick="quickConfirm(<?= $oid ?>,'<?= htmlspecialchars(addslashes($o['order_ref'])) ?>')">
                    <i class="fi fi-rr-check"></i>
                  </button>
                  <?php endif; ?>
                  <button class="icon-btn del" title="Delete order"
                    onclick="openConfirm(<?= $oid ?>,'<?= htmlspecialchars(addslashes($o['order_ref'])) ?>')">
                    <i class="fi fi-rr-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
            <tr><td colspan="10">
              <div class="empty-state">
                <i class="fi fi-rr-shopping-bag"></i>
                <div class="empty-state-title">No orders yet</div>
                <div class="empty-state-sub">Orders placed through the shop will appear here.</div>
              </div>
            </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div id="empty-state-filter" style="display:none;">
        <div class="empty-state">
          <i class="fi fi-rr-search"></i>
          <div class="empty-state-title">No orders match your filters</div>
          <div class="empty-state-sub">Try adjusting the status or search query.</div>
        </div>
      </div>

      <div class="pagination" id="pagination">
        <div class="page-info">Page <span id="page-current">1</span> of <span id="page-total">1</span> &nbsp;·&nbsp; <span id="showing-count-2">0</span> records</div>
        <div class="page-btns" id="page-btns"></div>
      </div>
    </div>

  </main>
</div>

<!-- ── ORDER DETAIL MODAL ── -->
<div class="modal-overlay" id="modal-detail" onclick="closeModalOutside(event,'modal-detail')">
  <div class="modal-box lg">
    <div class="modal-hdr">
      <h3><i class="fi fi-rr-receipt" style="margin-right:8px"></i><span id="detail-ref">Order Details</span></h3>
      <button class="modal-close" onclick="closeModal('modal-detail')"><i class="fi fi-rr-cross"></i></button>
    </div>
    <div class="modal-body" id="detail-body"></div>
  </div>
</div>

<!-- ── EDIT STATUS MODAL ── -->
<div class="modal-overlay" id="modal-edit" onclick="closeModalOutside(event,'modal-edit')">
  <div class="modal-box md">
    <div class="modal-hdr">
      <h3><i class="fi fi-rr-edit" style="margin-right:8px"></i>Update Order Status</h3>
      <button class="modal-close" onclick="closeModal('modal-edit')"><i class="fi fi-rr-cross"></i></button>
    </div>
    <form method="POST" action="admin-orders.php">
      <input type="hidden" name="action" value="update_status"/>
      <input type="hidden" name="order_id" id="edit-id"/>
      <div class="modal-body">
        <div style="font-family:var(--font-hud);font-size:0.52rem;color:var(--text-dim);letter-spacing:0.10em;margin-bottom:16px;">Editing: <span id="edit-ref" style="color:var(--creo-amber)"></span></div>
        <div class="field">
          <label class="field-label">Order Status <span class="req">*</span></label>
          <select class="field-select" name="order_status" id="edit-status">
            <option value="pending">Pending</option>
            <option value="processing">Processing</option>
            <option value="confirmed">Confirmed</option>
            <option value="shipped">Shipped</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div class="field">
          <label class="field-label">Admin Note <span style="color:var(--text-dim);font-weight:400;">(optional)</span></label>
          <textarea class="field-textarea" name="admin_note" id="edit-note" placeholder="Internal note, e.g. 'Ready for pickup on Oct 18'"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('modal-edit')">Cancel</button>
        <button type="submit" class="btn btn-amber btn-sm"><i class="fi fi-rr-check"></i> Save Status</button>
      </div>
    </form>
  </div>
</div>

<!-- ── CONFIRM DELETE MODAL ── -->
<div class="modal-overlay" id="modal-confirm" onclick="closeModalOutside(event,'modal-confirm')">
  <div class="modal-box sm" style="text-align:center">
    <div class="modal-hdr" style="justify-content:center;border-color:rgba(255,77,106,0.22);background:rgba(255,77,106,0.05)">
      <h3 style="color:var(--admin-red)">Confirm Delete</h3>
    </div>
    <div class="modal-body">
      <i class="fi fi-rr-trash confirm-icon"></i>
      <div class="confirm-title" id="confirm-title">Delete Order?</div>
      <div class="confirm-text">This will permanently delete the order and all its items. This cannot be undone.</div>
    </div>
    <div class="modal-footer" style="justify-content:center">
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('modal-confirm')">Cancel</button>
      <form method="POST" action="admin-orders.php" style="display:inline">
        <input type="hidden" name="action" value="delete_order"/>
        <input type="hidden" name="order_id" id="confirm-oid"/>
        <button type="submit" class="btn btn-red btn-sm"><i class="fi fi-rr-trash"></i> Yes, Delete</button>
      </form>
    </div>
  </div>
</div>

<!-- ── QUICK CONFIRM MODAL ── -->
<div class="modal-overlay" id="modal-quick-confirm" onclick="closeModalOutside(event,'modal-quick-confirm')">
  <div class="modal-box sm" style="text-align:center">
    <div class="modal-hdr" style="justify-content:center;background:rgba(68,255,136,0.05);border-color:rgba(68,255,136,0.22)">
      <h3 style="color:var(--admin-green)">Confirm Order</h3>
    </div>
    <div class="modal-body">
      <i class="fi fi-rr-check-circle" style="font-size:2rem;color:var(--admin-green);display:block;margin-bottom:12px"></i>
      <div class="confirm-title" id="qc-title">Confirm this order?</div>
      <div class="confirm-text">This will set the status to <strong>Confirmed</strong> and notify the team.</div>
    </div>
    <div class="modal-footer" style="justify-content:center">
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('modal-quick-confirm')">Cancel</button>
      <form method="POST" action="admin-orders.php" style="display:inline">
        <input type="hidden" name="action" value="update_status"/>
        <input type="hidden" name="order_status" value="confirmed"/>
        <input type="hidden" name="admin_note" value=""/>
        <input type="hidden" name="order_id" id="qc-oid"/>
        <button type="submit" class="btn btn-sm" style="background:rgba(68,255,136,0.12);border:1px solid rgba(68,255,136,0.40)!important;color:var(--admin-green)"><i class="fi fi-rr-check"></i> Confirm</button>
      </form>
    </div>
  </div>
</div>

<!-- ── PROOF MODAL ── -->
<div class="modal-overlay" id="modal-proof" onclick="closeModalOutside(event,'modal-proof')">
  <div class="modal-box md">
    <div class="modal-hdr">
      <h3><i class="fi fi-rr-file-check" style="margin-right:8px"></i>Proof of Payment</h3>
      <button class="modal-close" onclick="closeModal('modal-proof')"><i class="fi fi-rr-cross"></i></button>
    </div>
    <div class="modal-body">
      <div class="proof-img-wrap" id="proof-img-wrap"></div>
      <div style="display:flex;justify-content:center;margin-top:14px">
        <a id="proof-download-link" href="#" target="_blank" class="btn btn-ghost btn-sm"><i class="fi fi-rr-download"></i> Open Full Size</a>
      </div>
    </div>
  </div>
</div>

<!-- ── BULK FORM (hidden) ── -->
<form id="bulk-form" method="POST" action="admin-orders.php" style="display:none">
  <input type="hidden" name="action" value="bulk_status"/>
  <input type="hidden" name="order_ids" id="bulk-ids-input"/>
  <input type="hidden" name="bulk_status" id="bulk-status-input"/>
</form>

<!-- TOAST -->
<div class="toast-wrap" id="toast-wrap"></div>

<!-- SIDEBAR -->
<script>
(function(){
  var slot = document.getElementById('sidebarSlot');
  if (!slot) return;
  fetch('admin-sidebar.html')
    .then(function(r){return r.text();})
    .then(function(html){
      slot.innerHTML = html;
      slot.querySelectorAll('script').forEach(function(old){
        var s = document.createElement('script');
        s.textContent = old.textContent;
        document.body.appendChild(s);
      });
    })
    .catch(function(){
      slot.innerHTML = '<aside style="position:fixed;top:0;left:0;height:100vh;width:248px;background:#04031A;border-right:1px solid rgba(139,126,255,0.18);display:flex;align-items:center;justify-content:center;font-family:Orbitron,monospace;font-size:0.55rem;color:rgba(139,126,255,0.40);letter-spacing:0.12em;text-align:center;padding:20px;">SIDEBAR<br/>COMPONENT<br/><span style="margin-top:8px;display:block;font-size:0.44rem">admin-sidebar.html</span></aside>';
    });
})();
document.addEventListener('prc-sidebar-toggle', function(e){
  document.getElementById('adminShell').classList.toggle('sb-collapsed', e.detail.collapsed);
});
if (localStorage.getItem('prc_sidebar_collapsed') === '1') {
  document.getElementById('adminShell').classList.add('sb-collapsed');
}
</script>

<script>
/* ══════════════════════════════════
   CURSOR
══════════════════════════════════ */
(function(){
  var dot = document.getElementById('cursorDot'), ring = document.getElementById('cursorRing');
  if (!dot || !ring) return;
  var mx=0,my=0,rx=0,ry=0;
  document.addEventListener('mousemove', function(e){ mx=e.clientX; my=e.clientY; dot.style.left=mx+'px'; dot.style.top=my+'px'; });
  (function l(){ rx+=(mx-rx)*.12; ry+=(my-ry)*.12; ring.style.left=rx+'px'; ring.style.top=ry+'px'; requestAnimationFrame(l); })();
  document.addEventListener('mouseover', function(e){ if(e.target.closest('a,button,input,select')) ring.classList.add('hovered'); });
  document.addEventListener('mouseout', function(e){ if(e.target.closest('a,button,input,select')) ring.classList.remove('hovered'); });
})();

/* ══════════════════════════════════
   TOPBAR DATE
══════════════════════════════════ */
(function(){
  var el = document.getElementById('topbar-date-display');
  var M = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
  function upd(){
    var d=new Date(), h=d.getHours(), m=d.getMinutes(), ap=h>=12?'PM':'AM';
    h=h%12||12;
    el.innerHTML = M[d.getMonth()]+' '+String(d.getDate()).padStart(2,'0')+', '+d.getFullYear()+'&nbsp;<span>'+String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+' '+ap+'</span>';
  }
  upd(); setInterval(upd, 30000);
})();

/* ══════════════════════════════════
   FILTER & SEARCH STATE
══════════════════════════════════ */
var activeStatus = 'all';
var activePay    = 'all';
var activeSearch = '';
var activeSortCol = 'date';
var activeSortDir = 'desc';
var currentPage  = 1;
var perPage      = 25;

var allRows = Array.from(document.querySelectorAll('#orders-tbody tr[data-order-id]'));

function setStatusFilter(val, btn) {
  activeStatus = val;
  document.querySelectorAll('[data-status]').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  currentPage = 1;
  applyFilters();
}

function setPayFilter(val, btn) {
  activePay = val;
  document.querySelectorAll('[data-pay]').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  currentPage = 1;
  applyFilters();
}

function searchTable(val) {
  activeSearch = val.toLowerCase().trim();
  currentPage = 1;
  applyFilters();
}

function applySort(val) {
  var parts = val.split('-');
  activeSortCol = parts[0]; activeSortDir = parts[1] || 'desc';
  applyFilters();
}

function setPerPage(n) { perPage = n; currentPage = 1; applyFilters(); }

function sortBy(col) {
  if (activeSortCol === col) { activeSortDir = activeSortDir === 'asc' ? 'desc' : 'asc'; }
  else { activeSortCol = col; activeSortDir = 'asc'; }
  document.querySelectorAll('.data-table th').forEach(function(th){
    th.classList.remove('sorted');
    var a = th.querySelector('.sort-arr'); if (a) a.textContent = '↕';
  });
  var th = document.querySelector('.data-table th[onclick*="sortBy(\''+col+'\')"]');
  if (th) { th.classList.add('sorted'); var a = th.querySelector('.sort-arr'); if (a) a.textContent = activeSortDir === 'asc' ? '↑' : '↓'; }
  applyFilters();
}

function applyFilters() {
  // 1. Filter
  var visible = allRows.filter(function(row){
    var statusOk = activeStatus === 'all' || row.dataset.status === activeStatus;
    var payOk    = activePay   === 'all' || row.dataset.pay   === activePay;
    var searchOk = !activeSearch || row.dataset.search.includes(activeSearch);
    return statusOk && payOk && searchOk;
  });

  // 2. Sort
  visible.sort(function(a, b){
    var av, bv;
    switch(activeSortCol){
      case 'amount': av = parseFloat(a.dataset.amount); bv = parseFloat(b.dataset.amount); break;
      case 'date':   av = parseInt(a.dataset.date); bv = parseInt(b.dataset.date); break;
      case 'ref':    av = a.dataset.ref; bv = b.dataset.ref; break;
      case 'status': av = a.dataset.status; bv = b.dataset.status; break;
      case 'payment':av = a.dataset.pay; bv = b.dataset.pay; break;
      default:       av = a.dataset.search; bv = b.dataset.search;
    }
    if (av < bv) return activeSortDir === 'asc' ? -1 : 1;
    if (av > bv) return activeSortDir === 'asc' ?  1 : -1;
    return 0;
  });

  // 3. Paginate
  var totalPages = Math.max(1, Math.ceil(visible.length / perPage));
  if (currentPage > totalPages) currentPage = totalPages;
  var start = (currentPage - 1) * perPage;
  var pageRows = visible.slice(start, start + perPage);

  // 4. Show/hide rows
  allRows.forEach(function(row){ row.style.display = 'none'; });
  pageRows.forEach(function(row){ row.style.display = ''; });

  // 5. Empty state
  var emptyFilter = document.getElementById('empty-state-filter');
  emptyFilter.style.display = visible.length === 0 ? 'block' : 'none';

  // 6. Counts
  setText('showing-count', visible.length);
  setText('showing-count-2', visible.length);
  setText('page-current', currentPage);
  setText('page-total', totalPages);

  // 7. Pagination buttons
  renderPagination(totalPages);
}

function renderPagination(totalPages) {
  var c = document.getElementById('page-btns'), html = '';
  html += '<button class="page-btn" onclick="goPage('+Math.max(1,currentPage-1)+')">&lsaquo;</button>';
  var s = Math.max(1, currentPage-2), e = Math.min(totalPages, s+4);
  for (var p=s;p<=e;p++) html += '<button class="page-btn'+(p===currentPage?' active':'')+'" onclick="goPage('+p+')">'+p+'</button>';
  html += '<button class="page-btn" onclick="goPage('+Math.min(totalPages,currentPage+1)+')">&rsaquo;</button>';
  c.innerHTML = html;
}

function goPage(p) { currentPage = p; applyFilters(); document.getElementById('orders-table').scrollIntoView({behavior:'smooth'}); }

/* ══════════════════════════════════
   SELECTION
══════════════════════════════════ */
function toggleSelectAll(cb) {
  document.querySelectorAll('.row-cb').forEach(function(c){
    var row = c.closest('tr');
    if (row.style.display !== 'none') { c.checked = cb.checked; row.classList.toggle('selected-row', cb.checked); }
  });
  updateBulkBar();
}

function onRowCheck() {
  document.getElementById('select-all').checked = false;
  document.querySelectorAll('.row-cb').forEach(function(c){ c.closest('tr').classList.toggle('selected-row', c.checked); });
  updateBulkBar();
}

function updateBulkBar() {
  var checked = Array.from(document.querySelectorAll('.row-cb:checked'));
  var bar = document.getElementById('bulk-bar');
  bar.classList.toggle('visible', checked.length > 0);
  setText('bulk-count-num', checked.length);
}

function clearSelection() {
  document.querySelectorAll('.row-cb, #select-all').forEach(function(c){ c.checked = false; });
  document.querySelectorAll('.selected-row').forEach(function(r){ r.classList.remove('selected-row'); });
  updateBulkBar();
}

function applyBulk() {
  var status = document.getElementById('bulk-status-select').value;
  if (!status) { showToast('Please select a status first.', 'error'); return; }
  var ids = Array.from(document.querySelectorAll('.row-cb:checked')).map(function(c){ return c.dataset.id; });
  if (!ids.length) { showToast('No orders selected.', 'error'); return; }
  document.getElementById('bulk-ids-input').value = JSON.stringify(ids.map(Number));
  document.getElementById('bulk-status-input').value = status;
  document.getElementById('bulk-form').submit();
}

/* ══════════════════════════════════
   MODALS
══════════════════════════════════ */
function openModal(id) { document.getElementById(id).classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow = ''; }
function closeModalOutside(e, id) { if (e.target === document.getElementById(id)) closeModal(id); }
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(function(m){ closeModal(m.id); }); });

/* DETAIL MODAL */
function openDetail(o) {
  document.getElementById('detail-ref').textContent = o.order_ref;
  var statusCls = o.order_status;
  var payBadge = o.payment_method === 'GCash' ? '<span class="pay-badge pay-gcash">GCash</span>' : '<span class="pay-badge pay-bank">Bank Transfer</span>';
  var statusBadge = '<span class="pill '+statusCls+'"><span class="pill-dot"></span>'+capitalize(o.order_status)+'</span>';

  var itemsHTML = '';
  if (o.items && o.items.length) {
    itemsHTML += '<div class="detail-section-lbl">Items Ordered</div>';
    itemsHTML += '<table class="items-table"><thead><tr><th>Product</th><th>Unit Price</th><th>Qty</th><th>Subtotal</th></tr></thead><tbody>';
    o.items.forEach(function(item){
      itemsHTML += '<tr><td style="color:var(--text-high);font-weight:600">'+htmlesc(item.product_name||'—')+'</td>'+
        '<td>&#8369;'+fmtNum(item.unit_price)+'</td>'+
        '<td style="font-family:var(--font-hud);font-size:.62rem;font-weight:700;color:var(--creo-amber)">'+item.quantity+'</td>'+
        '<td>&#8369;'+fmtNum(item.subtotal)+'</td></tr>';
    });
    itemsHTML += '</tbody></table>';
    itemsHTML += '<div class="items-total"><span class="items-total-lbl">Order Total</span><span class="items-total-val">&#8369;'+fmtNum(o.total_amount)+'</span></div>';
  }

  var proofHTML = o.has_proof ?
    '<div class="detail-section-lbl">Proof of Payment</div><div style="margin-bottom:16px"><button class="btn btn-ghost btn-sm" onclick="viewProof('+o.order_id+')"><i class="fi fi-rr-file-check"></i> View Proof</button></div>' : '';

  var noteHTML = o.admin_note ?
    '<div class="detail-section-lbl">Admin Note</div><div style="font-size:.875rem;color:var(--text-mid);padding:12px 14px;background:rgba(255,160,48,0.05);border:1px solid rgba(255,160,48,0.18);margin-bottom:20px">'+htmlesc(o.admin_note)+'</div>' : '';

  document.getElementById('detail-body').innerHTML =
    '<div class="detail-section-lbl">Order Info</div>'+
    '<div class="detail-grid">'+
      df('Reference', '<span class="ref-badge">'+htmlesc(o.order_ref)+'</span>', false, true)+
      df('Date', htmlesc(o.created_at))+
      df('Status', statusBadge, false, true)+
      df('Payment', payBadge, false, true)+
    '</div>'+
    '<div class="detail-section-lbl">Customer Info</div>'+
    '<div class="detail-grid">'+
      df('Full Name', htmlesc(o.customer_name))+
      df('Email', '<a href="mailto:'+htmlesc(o.customer_email)+'" style="color:var(--prc-violet)">'+htmlesc(o.customer_email)+'</a>', false, true)+
      df('Contact No.', htmlesc(o.customer_phone))+
      df('School / Org', htmlesc(o.school_org))+
      (o.notes ? df('Notes / Address', htmlesc(o.notes), true) : '')+
    '</div>'+
    itemsHTML +
    proofHTML +
    noteHTML +
    '<div style="display:flex;gap:10px;flex-wrap:wrap;padding-top:16px;border-top:1px solid rgba(139,126,255,0.12)">'+
      '<button class="btn btn-amber btn-sm" onclick="closeModal(\'modal-detail\');openEdit('+o.order_id+',\''+htmlesc(o.order_ref)+'\',\''+o.order_status+'\',\''+htmlesc(o.admin_note||'')+'\')"><i class="fi fi-rr-edit"></i> Edit Status</button>'+
      '<button class="btn btn-ghost btn-sm" onclick="closeModal(\'modal-detail\')">Close</button>'+
    '</div>';

  openModal('modal-detail');
}

function df(label, value, full, raw) {
  return '<div class="detail-field'+(full?' full':'')+'">'+
    '<div class="detail-field-lbl">'+label+'</div>'+
    '<div class="detail-field-val">'+(raw ? value : (value||'—'))+'</div>'+
  '</div>';
}

/* EDIT STATUS MODAL */
function openEdit(oid, ref, status, note) {
  document.getElementById('edit-id').value = oid;
  document.getElementById('edit-ref').textContent = ref;
  document.getElementById('edit-status').value = status;
  document.getElementById('edit-note').value = note;
  openModal('modal-edit');
}

/* CONFIRM DELETE */
function openConfirm(oid, ref) {
  document.getElementById('confirm-oid').value = oid;
  document.getElementById('confirm-title').textContent = 'Delete Order ' + ref + '?';
  openModal('modal-confirm');
}

/* QUICK CONFIRM */
function quickConfirm(oid, ref) {
  document.getElementById('qc-oid').value = oid;
  document.getElementById('qc-title').textContent = 'Confirm order ' + ref + '?';
  openModal('modal-quick-confirm');
}

/* PROOF MODAL */
function viewProof(oid) {
  var url = 'admin-orders.php?proof=' + oid;
  var wrap = document.getElementById('proof-img-wrap');
  wrap.innerHTML = '<img src="'+url+'" alt="Proof of payment" onerror="this.parentElement.innerHTML=\'<div class=\\\'proof-pdf-link\\\'><i class=\\\"fi fi-rr-file\\\"></i> Could not preview — <a href=\\\"'+url+'\\\" target=\\\"_blank\\\" style=\\\"color:var(--prc-violet)\\\">open in new tab</a></div>\'"/>';
  document.getElementById('proof-download-link').href = url;
  openModal('modal-proof');
}

/* ══════════════════════════════════
   EXPORT CSV
══════════════════════════════════ */
function exportCSV() {
  var rows = allRows.filter(function(r){ return r.style.display !== 'none'; });
  if (!rows.length) { showToast('No orders to export.', 'error'); return; }
  var headers = ['Ref No.','Customer','Email','Phone','School/Org','Items','Payment Method','Total Amount','Status','Date'];

  // We'd need to get full data — for now export what's visible in the dataset
  var lines = [headers.join(',')];
  rows.forEach(function(row){
    var cells = row.querySelectorAll('td');
    var ref  = row.dataset.ref || '';
    var name = row.querySelector('.td-name') ? row.querySelector('.td-name').textContent : '';
    var school = row.querySelector('.td-sub') ? row.querySelector('.td-sub').textContent : '';
    var amount = row.dataset.amount || '';
    var status = row.dataset.status || '';
    var date = cells[8] ? cells[8].textContent.trim() : '';
    lines.push([ref, name, '', '', school, '', row.dataset.pay, amount, status, date].map(function(v){ return '"'+(v||'').replace(/"/g,'""')+'"'; }).join(','));
  });

  var blob = new Blob([lines.join('\n')], {type:'text/csv'});
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'PRC-Shop-Orders-' + new Date().toISOString().slice(0,10) + '.csv';
  a.click();
  showToast('Exported ' + rows.length + ' orders.', 'success');
}

/* ══════════════════════════════════
   UTILITIES
══════════════════════════════════ */
function setText(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; }
function capitalize(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function fmtNum(n) { return parseFloat(n||0).toLocaleString(); }
function htmlesc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function showToast(msg, type) {
  var wrap = document.getElementById('toast-wrap');
  var t = document.createElement('div');
  t.className = 'toast ' + (type||'');
  t.innerHTML = '<i class="fi fi-rr-'+(type==='success'?'check':'exclamation')+'"></i> ' + msg;
  wrap.appendChild(t);
  setTimeout(function(){ t.style.opacity='0'; t.style.transition='opacity .4s'; setTimeout(function(){ t.remove(); }, 400); }, 3500);
}

/* ══════════════════════════════════
   INIT
══════════════════════════════════ */
applyFilters();
</script>
</body>
</html>