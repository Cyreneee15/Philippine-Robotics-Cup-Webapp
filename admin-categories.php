<?php
// PRC-WebApp/admin-categories.php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);

$db_host='localhost'; $db_user='root'; $db_pass=''; $db_name='prc_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die('<p style="color:#ff6b6b;padding:40px;font-family:monospace;">DB error: '.htmlspecialchars($conn->connect_error).'</p>');

define('CAT_UPLOAD_DIR', __DIR__.'/assets/categories/admin/');
define('CAT_UPLOAD_URL', 'assets/categories/admin/');
define('MAX_FILE_SIZE', 10*1024*1024);
define('ALLOWED_IMG', ['image/jpeg','image/png','image/gif','image/webp']);
if (!is_dir(CAT_UPLOAD_DIR)) mkdir(CAT_UPLOAD_DIR, 0755, true);

function set_flash($t, $m) { $_SESSION['cflash'] = ['type'=>$t,'msg'=>$m]; }
function get_flash() { if (!empty($_SESSION['cflash'])) { $f=$_SESSION['cflash']; unset($_SESSION['cflash']); return $f; } return null; }

function upload_img($key, $prefix='img') {
    if (empty($_FILES[$key]['name']) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) return null;
    if ($_FILES[$key]['size'] > MAX_FILE_SIZE) return null;
    $mime = mime_content_type($_FILES[$key]['tmp_name']);
    if (!in_array($mime, ALLOWED_IMG)) return null;
    $ext  = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    $name = $prefix.'_'.time().'_'.rand(1000,9999).'.'.$ext;
    $dest = CAT_UPLOAD_DIR.$name;
    if (move_uploaded_file($_FILES[$key]['tmp_name'], $dest)) return CAT_UPLOAD_URL.$name;
    return null;
}

// ═══════════════════════════════════════════════════════
//  POST HANDLER
// ═══════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── META ─────────────────────────────────────────────
    if ($action === 'save_meta') {
        $desc = trim($_POST['hero_desc'] ?? '');
        $s = $conn->prepare("INSERT INTO prc_categories_meta(meta_key,meta_value) VALUES('hero_desc',?) ON DUPLICATE KEY UPDATE meta_value=?");
        $s->bind_param('ss', $desc, $desc);
        $s->execute() ? set_flash('success','Hero description saved.') : set_flash('error','Save failed.');
        $s->close();
    }

    // ── TRACKS: add ──────────────────────────────────────
    if ($action === 'add_track') {
        $slug  = trim($_POST['track_slug'] ?? '');
        $name  = trim($_POST['track_name'] ?? '');
        $color = trim($_POST['track_color'] ?? 'rv');
        $sort  = (int)($_POST['track_sort'] ?? 0);
        $logo  = upload_img('track_logo','tlogo') ?? trim($_POST['track_logo_url'] ?? '');
        if (!$slug || !$name) { set_flash('error','Slug and name are required.'); }
        else {
            $s = $conn->prepare("INSERT INTO prc_categories_tracks(track_slug,track_name,track_logo,track_color,track_sort) VALUES(?,?,?,?,?)");
            $s->bind_param('ssssi', $slug, $name, $logo, $color, $sort);
            $s->execute() ? set_flash('success',"Track \"$name\" added.") : set_flash('error','Slug already exists.');
            $s->close();
        }
    }

    // ── TRACKS: edit ─────────────────────────────────────
    if ($action === 'edit_track') {
        $tid   = (int)($_POST['track_id'] ?? 0);
        $slug  = trim($_POST['track_slug'] ?? '');
        $name  = trim($_POST['track_name'] ?? '');
        $color = trim($_POST['track_color'] ?? 'rv');
        $sort  = (int)($_POST['track_sort'] ?? 0);
        $active= (int)($_POST['is_active'] ?? 1);
        $logo  = upload_img('track_logo','tlogo');
        if (!$logo) $logo = trim($_POST['track_logo_url'] ?? '');
        if ($tid && $slug && $name) {
            $s = $conn->prepare("UPDATE prc_categories_tracks SET track_slug=?,track_name=?,track_logo=?,track_color=?,track_sort=?,is_active=? WHERE track_id=?");
            $s->bind_param('ssssiiii', $slug, $name, $logo, $color, $sort, $active, $tid);
            $s->execute() ? set_flash('success','Track updated.') : set_flash('error','Update failed.');
            $s->close();
        }
    }

    // ── TRACKS: delete ───────────────────────────────────
    if ($action === 'delete_track') {
        $tid = (int)($_POST['track_id'] ?? 0);
        if ($tid) { $conn->query("DELETE FROM prc_categories_tracks WHERE track_id=$tid"); set_flash('success','Track deleted.'); }
    }

    // ── SUBS: add ────────────────────────────────────────
    if ($action === 'add_sub') {
        $tid   = (int)($_POST['track_id'] ?? 0);
        $slug  = trim($_POST['sub_slug'] ?? '');
        $name  = trim($_POST['sub_name'] ?? '');
        $icon  = trim($_POST['sub_icon'] ?? 'fi-rr-settings');
        $eyebrow = trim($_POST['sub_eyebrow'] ?? '');
        $title = trim($_POST['sub_title'] ?? '');
        $desc  = trim($_POST['sub_desc'] ?? '');
        $dpct  = min(100, max(0, (int)($_POST['difficulty_pct'] ?? 50)));
        $dlbl  = trim($_POST['difficulty_label'] ?? 'Intermediate');
        $sort  = (int)($_POST['sub_sort'] ?? 0);
        if (!$tid || !$slug || !$name) { set_flash('error','Track, slug, and name are required.'); }
        else {
            $s = $conn->prepare("INSERT INTO prc_categories_subs(track_id,sub_slug,sub_name,sub_icon,sub_eyebrow,sub_title,sub_desc,difficulty_pct,difficulty_label,sub_sort) VALUES(?,?,?,?,?,?,?,?,?,?)");
            $s->bind_param('issssssiis', $tid, $slug, $name, $icon, $eyebrow, $title, $desc, $dpct, $dlbl, $sort);
            if ($s->execute()) {
                set_flash('success',"Sub-category \"$name\" added.");
            } else { set_flash('error','Slug already exists in this track.'); }
            $s->close();
        }
    }

    // ── SUBS: edit ───────────────────────────────────────
    if ($action === 'edit_sub') {
        $sid   = (int)($_POST['sub_id'] ?? 0);
        $tid   = (int)($_POST['track_id'] ?? 0);
        $slug  = trim($_POST['sub_slug'] ?? '');
        $name  = trim($_POST['sub_name'] ?? '');
        $icon  = trim($_POST['sub_icon'] ?? 'fi-rr-settings');
        $eyebrow = trim($_POST['sub_eyebrow'] ?? '');
        $title = trim($_POST['sub_title'] ?? '');
        $desc  = trim($_POST['sub_desc'] ?? '');
        $dpct  = min(100, max(0, (int)($_POST['difficulty_pct'] ?? 50)));
        $dlbl  = trim($_POST['difficulty_label'] ?? 'Intermediate');
        $sort  = (int)($_POST['sub_sort'] ?? 0);
        $active= (int)($_POST['is_active'] ?? 1);
        if ($sid && $slug && $name) {
            $s = $conn->prepare("UPDATE prc_categories_subs SET track_id=?,sub_slug=?,sub_name=?,sub_icon=?,sub_eyebrow=?,sub_title=?,sub_desc=?,difficulty_pct=?,difficulty_label=?,sub_sort=?,is_active=? WHERE sub_id=?");
            $s->bind_param('issssssiisii', $tid, $slug, $name, $icon, $eyebrow, $title, $desc, $dpct, $dlbl, $sort, $active, $sid);
            $s->execute() ? set_flash('success','Sub-category updated.') : set_flash('error','Update failed.');
            $s->close();
        }
    }

    // ── SUBS: delete ─────────────────────────────────────
    if ($action === 'delete_sub') {
        $sid = (int)($_POST['sub_id'] ?? 0);
        if ($sid) { $conn->query("DELETE FROM prc_categories_subs WHERE sub_id=$sid"); set_flash('success','Sub-category deleted.'); }
    }

    // ── TAGS: add ────────────────────────────────────────
    if ($action === 'add_tag') {
        $sid  = (int)($_POST['sub_id'] ?? 0);
        $text = trim($_POST['tag_text'] ?? '');
        $sort = (int)($_POST['tag_sort'] ?? 0);
        if ($sid && $text) {
            $s = $conn->prepare("INSERT INTO prc_categories_tags(sub_id,tag_text,tag_sort) VALUES(?,?,?)");
            $s->bind_param('isi', $sid, $text, $sort);
            $s->execute() ? set_flash('success','Tag added.') : set_flash('error','Failed.');
            $s->close();
        }
    }

    // ── TAGS: delete ─────────────────────────────────────
    if ($action === 'delete_tag') {
        $tid = (int)($_POST['tag_id'] ?? 0);
        if ($tid) { $conn->query("DELETE FROM prc_categories_tags WHERE tag_id=$tid"); set_flash('success','Tag deleted.'); }
    }

    // ── TABS: add ────────────────────────────────────────
    if ($action === 'add_tab') {
        $sid  = (int)($_POST['sub_id'] ?? 0);
        $slug = trim($_POST['tab_slug'] ?? '');
        $lbl  = trim($_POST['tab_label'] ?? '');
        $sort = (int)($_POST['tab_sort'] ?? 0);
        if ($sid && $slug && $lbl) {
            $s = $conn->prepare("INSERT INTO prc_categories_tabs(sub_id,tab_slug,tab_label,tab_sort) VALUES(?,?,?,?)");
            $s->bind_param('issi', $sid, $slug, $lbl, $sort);
            $s->execute() ? set_flash('success','Tab added.') : set_flash('error','Slug already exists.');
            $s->close();
        }
    }

    // ── TABS: edit ───────────────────────────────────────
    if ($action === 'edit_tab') {
        $tabid = (int)($_POST['tab_id'] ?? 0);
        $slug  = trim($_POST['tab_slug'] ?? '');
        $lbl   = trim($_POST['tab_label'] ?? '');
        $sort  = (int)($_POST['tab_sort'] ?? 0);
        $active= (int)($_POST['is_active'] ?? 1);
        if ($tabid && $slug && $lbl) {
            $s = $conn->prepare("UPDATE prc_categories_tabs SET tab_slug=?,tab_label=?,tab_sort=?,is_active=? WHERE tab_id=?");
            $s->bind_param('sssii', $slug, $lbl, $sort, $active, $tabid);  // fixed: was ssiii -> sssii, bind needs 5 params
            $s->execute() ? set_flash('success','Tab updated.') : set_flash('error','Update failed.');
            $s->close();
        }
    }

    // ── TABS: delete ─────────────────────────────────────
    if ($action === 'delete_tab') {
        $tabid = (int)($_POST['tab_id'] ?? 0);
        if ($tabid) { $conn->query("DELETE FROM prc_categories_tabs WHERE tab_id=$tabid"); set_flash('success','Tab deleted.'); }
    }

    // ── SECTIONS: add ────────────────────────────────────
    if ($action === 'add_section') {
        $tabid = (int)($_POST['tab_id'] ?? 0);
        $icon  = trim($_POST['section_icon'] ?? 'fi-rr-info');
        $lbl   = trim($_POST['section_label'] ?? '');
        $sort  = (int)($_POST['section_sort'] ?? 0);
        if ($tabid && $lbl) {
            $s = $conn->prepare("INSERT INTO prc_categories_sections(tab_id,section_icon,section_label,section_sort) VALUES(?,?,?,?)");
            $s->bind_param('issi', $tabid, $icon, $lbl, $sort);
            $s->execute() ? set_flash('success','Section card added.') : set_flash('error','Failed.');
            $s->close();
        }
    }

    // ── SECTIONS: edit ───────────────────────────────────
    if ($action === 'edit_section') {
        $secid = (int)($_POST['section_id'] ?? 0);
        $icon  = trim($_POST['section_icon'] ?? 'fi-rr-info');
        $lbl   = trim($_POST['section_label'] ?? '');
        $sort  = (int)($_POST['section_sort'] ?? 0);
        if ($secid && $lbl) {
            $s = $conn->prepare("UPDATE prc_categories_sections SET section_icon=?,section_label=?,section_sort=? WHERE section_id=?");
            $s->bind_param('ssii', $icon, $lbl, $sort, $secid);
            $s->execute() ? set_flash('success','Section updated.') : set_flash('error','Update failed.');
            $s->close();
        }
    }

    // ── SECTIONS: delete ─────────────────────────────────
    if ($action === 'delete_section') {
        $secid = (int)($_POST['section_id'] ?? 0);
        if ($secid) { $conn->query("DELETE FROM prc_categories_sections WHERE section_id=$secid"); set_flash('success','Section deleted.'); }
    }

    // ── ITEMS: add ───────────────────────────────────────
    if ($action === 'add_item') {
        $secid = (int)($_POST['section_id'] ?? 0);
        $text  = trim($_POST['item_text'] ?? '');
        $sort  = (int)($_POST['item_sort'] ?? 0);
        if ($secid && $text) {
            $s = $conn->prepare("INSERT INTO prc_categories_items(section_id,item_text,item_sort) VALUES(?,?,?)");
            $s->bind_param('isi', $secid, $text, $sort);
            $s->execute() ? set_flash('success','Bullet item added.') : set_flash('error','Failed.');
            $s->close();
        }
    }

    // ── ITEMS: edit ──────────────────────────────────────
    if ($action === 'edit_item') {
        $iid  = (int)($_POST['item_id'] ?? 0);
        $text = trim($_POST['item_text'] ?? '');
        $sort = (int)($_POST['item_sort'] ?? 0);
        if ($iid && $text) {
            $s = $conn->prepare("UPDATE prc_categories_items SET item_text=?,item_sort=? WHERE item_id=?");
            $s->bind_param('sii', $text, $sort, $iid);
            $s->execute() ? set_flash('success','Item updated.') : set_flash('error','Update failed.');
            $s->close();
        }
    }

    // ── ITEMS: delete ────────────────────────────────────
    if ($action === 'delete_item') {
        $iid = (int)($_POST['item_id'] ?? 0);
        if ($iid) { $conn->query("DELETE FROM prc_categories_items WHERE item_id=$iid"); set_flash('success','Item deleted.'); }
    }

    header('Location: admin-categories.php'); exit;
}

// ═══════════════════════════════════════════════════════
//  FETCH DATA
// ═══════════════════════════════════════════════════════
$meta_row = $conn->query("SELECT meta_value FROM prc_categories_meta WHERE meta_key='hero_desc'")->fetch_row();
$hero_desc = $meta_row[0] ?? '';

$tracks = [];
$tr = $conn->query("SELECT * FROM prc_categories_tracks ORDER BY track_sort ASC");
if ($tr) while ($r = $tr->fetch_assoc()) $tracks[] = $r;

$all_subs = [];
$sr = $conn->query("SELECT s.*, t.track_name, t.track_slug, t.track_color FROM prc_categories_subs s JOIN prc_categories_tracks t ON s.track_id=t.track_id ORDER BY t.track_sort ASC, s.sub_sort ASC");
if ($sr) while ($r = $sr->fetch_assoc()) $all_subs[] = $r;

$subs_by_track = [];
foreach ($all_subs as $s) $subs_by_track[$s['track_id']][] = $s;

// Tags keyed by sub_id
$all_tags = [];
$tgr = $conn->query("SELECT * FROM prc_categories_tags ORDER BY sub_id ASC, tag_sort ASC");
if ($tgr) while ($r = $tgr->fetch_assoc()) $all_tags[$r['sub_id']][] = $r;

// Tabs keyed by sub_id
$all_tabs = [];
$tabr = $conn->query("SELECT * FROM prc_categories_tabs ORDER BY sub_id ASC, tab_sort ASC");
if ($tabr) while ($r = $tabr->fetch_assoc()) $all_tabs[$r['sub_id']][] = $r;

// Sections keyed by tab_id
$all_sections = [];
$secr = $conn->query("SELECT * FROM prc_categories_sections ORDER BY tab_id ASC, section_sort ASC");
if ($secr) while ($r = $secr->fetch_assoc()) $all_sections[$r['tab_id']][] = $r;

// Items keyed by section_id
$all_items = [];
$ir = $conn->query("SELECT * FROM prc_categories_items ORDER BY section_id ASC, item_sort ASC");
if ($ir) while ($r = $ir->fetch_assoc()) $all_items[$r['section_id']][] = $r;

// Counts
$total_subs   = count($all_subs);
$total_tabs   = array_sum(array_map('count', $all_tabs));
$total_sections = array_sum(array_map('count', $all_sections));
$total_items  = array_sum(array_map('count', $all_items));

$flash = get_flash();
$conn->close();

function color_badge($c) {
    return match($c) { 'mx'=>'badge-mx','drone'=>'badge-drone','award'=>'badge-award', default=>'badge-rv' };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Categories — PRC Admin</title>
  <link rel="icon" type="image/png" href="assets/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css'>
  <style>
    :root{--sb-width:248px;--sb-collapsed:68px;--topbar-h:60px;--bg-void:#03020D;--bg-card:rgba(10,8,30,0.80);--prc-violet:#8B7EFF;--prc-ice:#C4EEFF;--creo-amber:#FFA030;--creo-volt:#FFE930;--creo-sky:#44D9FF;--admin-red:#FF4D6A;--admin-green:#44FF88;--border-neon:rgba(139,126,255,0.18);--text-high:#F2EEFF;--text-mid:#C8C0F0;--text-soft:#9A90CC;--text-dim:#6058A0;--font-hud:'Orbitron',monospace;--font-body:'Exo 2',sans-serif}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html{scroll-behavior:smooth}
    body{font-family:var(--font-body);background:var(--bg-void);color:var(--text-high);overflow-x:hidden;line-height:1.6;min-height:100vh;cursor:none}
    img{max-width:100%;display:block} a{text-decoration:none;color:inherit} ul{list-style:none}
    button{font-family:inherit;border:none;background:none;cursor:none}
    body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(rgba(139,126,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(139,126,255,0.03) 1px,transparent 1px);background-size:44px 44px}
    body::after{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;background:repeating-linear-gradient(to bottom,transparent,transparent 2px,rgba(0,0,0,0.025) 2px,rgba(0,0,0,0.025) 4px)}
    .cursor-dot{position:fixed;width:8px;height:8px;border-radius:50%;background:var(--prc-violet);pointer-events:none;z-index:99999;transform:translate(-50%,-50%);box-shadow:0 0 18px rgba(139,126,255,.80)}
    .cursor-ring{position:fixed;width:36px;height:36px;border-radius:50%;border:1px solid rgba(139,126,255,.60);pointer-events:none;z-index:99998;transform:translate(-50%,-50%);transition:width .25s,height .25s}
    .cursor-ring.hovered{width:52px;height:52px;border-color:var(--creo-amber)}
    /* SHELL */
    .admin-shell{display:grid;grid-template-columns:var(--sb-width) 1fr;grid-template-rows:var(--topbar-h) 1fr;min-height:100vh;position:relative;z-index:1;transition:grid-template-columns .30s}
    .admin-shell.sb-collapsed{grid-template-columns:var(--sb-collapsed) 1fr}
    .admin-sidebar-slot{grid-row:1/-1;grid-column:1}
    /* TOPBAR */
    .admin-topbar{grid-column:2;grid-row:1;height:var(--topbar-h);background:rgba(3,2,13,0.92);backdrop-filter:blur(20px);border-bottom:1px solid var(--border-neon);display:flex;align-items:center;padding:0 28px;gap:16px;position:sticky;top:0;z-index:800}
    .topbar-breadcrumb{display:flex;align-items:center;gap:8px;font-family:var(--font-hud);font-size:0.60rem;color:var(--text-dim);letter-spacing:0.10em;text-transform:uppercase}
    .topbar-breadcrumb span{color:var(--prc-violet)}
    .topbar-right{margin-left:auto;display:flex;align-items:center;gap:10px}
    .topbar-icon-btn{width:36px;height:36px;background:rgba(139,126,255,0.05);border:1px solid var(--border-neon)!important;border-radius:3px;display:flex;align-items:center;justify-content:center;color:var(--text-soft);transition:all .25s;cursor:none}
    .topbar-icon-btn:hover{background:rgba(139,126,255,0.14);color:var(--prc-violet)}
    .topbar-public-btn{display:inline-flex;align-items:center;gap:7px;font-family:var(--font-hud);font-size:0.56rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--prc-violet);padding:7px 14px;border:1px solid rgba(139,126,255,0.35)!important;background:rgba(139,126,255,0.06);clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);transition:all .25s}
    .topbar-public-btn:hover{background:rgba(139,126,255,0.16);color:#fff}
    .topbar-date{font-family:var(--font-hud);font-size:0.52rem;color:var(--text-dim);letter-spacing:0.10em;white-space:nowrap}
    .topbar-date span{color:var(--creo-volt)}
    /* MAIN */
    .admin-main{grid-column:2;grid-row:2;padding:28px 28px 60px;overflow-y:auto;min-height:calc(100vh - var(--topbar-h))}
    /* PAGE HEADER */
    .page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:24px}
    .page-eyebrow{font-family:var(--font-hud);font-size:0.52rem;letter-spacing:0.20em;text-transform:uppercase;color:var(--creo-sky);margin-bottom:6px;display:flex;align-items:center;gap:8px}
    .dot-live{width:7px;height:7px;background:var(--creo-sky);border-radius:50%;box-shadow:0 0 8px rgba(68,217,255,0.90);animation:sbPulse 1s ease-in-out infinite}
    @keyframes sbPulse{0%,100%{opacity:1}50%{opacity:.7}}
    .page-title{font-family:var(--font-hud);font-size:clamp(1.3rem,3vw,2rem);font-weight:900;color:#fff;letter-spacing:-0.01em}
    .page-title .accent{color:var(--creo-sky);text-shadow:0 0 22px rgba(68,217,255,0.70)}
    .page-stats{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
    .stat-chip{background:rgba(68,217,255,0.05);border:1px solid rgba(68,217,255,0.18);padding:10px 18px;text-align:center;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
    .stat-chip-num{font-family:var(--font-hud);font-size:1.4rem;font-weight:800;color:var(--creo-sky);display:block;line-height:1;text-shadow:0 0 14px rgba(68,217,255,0.70)}
    .stat-chip-lbl{font-family:var(--font-hud);font-size:0.48rem;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.12em;display:block;margin-top:3px}
    /* TABS */
    .admin-tabs{display:flex;gap:2px;margin-bottom:24px;border-bottom:1px solid var(--border-neon)}
    .admin-tab{font-family:var(--font-hud);font-size:0.60rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;padding:11px 22px;color:var(--text-soft);cursor:pointer!important;border:none;background:transparent;transition:all .22s;border-bottom:2px solid transparent;position:relative;top:1px}
    .admin-tab:hover{color:var(--text-mid)}
    .admin-tab.active{color:var(--prc-violet);border-bottom-color:var(--prc-violet);text-shadow:0 0 10px rgba(139,126,255,0.55)}
    .tab-pane{display:none}.tab-pane.active{display:block}
    /* FLASH */
    .flash-wrap{margin-bottom:20px}
    .flash-inner{padding:13px 20px;display:flex;align-items:center;gap:10px;font-family:var(--font-hud);font-size:0.63rem;font-weight:600;letter-spacing:0.08em;border:1px solid}
    .flash-inner.success{color:var(--admin-green);border-color:rgba(68,255,136,0.35);background:rgba(68,255,136,0.06)}
    .flash-inner.error{color:var(--admin-red);border-color:rgba(255,77,106,0.35);background:rgba(255,77,106,0.06)}
    /* CARDS */
    .panel-card{background:var(--bg-card);border:1px solid rgba(139,126,255,0.18);position:relative;margin-bottom:20px}
    .panel-card::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent)}
    .panel-card.sky-card::before{background:linear-gradient(90deg,transparent,var(--creo-sky),transparent)}
    .panel-hdr{background:rgba(139,126,255,0.05);padding:14px 18px;border-bottom:1px solid rgba(139,126,255,0.12);display:flex;align-items:center;gap:10px}
    .panel-hdr.sky{background:rgba(68,217,255,0.05);border-color:rgba(68,217,255,0.12)}
    .panel-hdr i{color:var(--prc-violet);font-size:0.95rem}
    .panel-hdr.sky i{color:var(--creo-sky)}
    .panel-hdr-text h3{font-family:var(--font-hud);font-size:0.70rem;font-weight:700;letter-spacing:0.06em;color:var(--text-high)}
    .panel-hdr-text p{font-size:0.76rem;color:var(--text-soft);margin-top:2px}
    .panel-body{padding:18px 20px}
    /* GRID */
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px}
    .three-col{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
    /* FORM */
    .field{margin-bottom:14px}
    .field:last-of-type{margin-bottom:0}
    .field-label{font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-soft);display:flex;align-items:center;gap:6px;margin-bottom:7px}
    .field-label .req{color:var(--admin-red)}
    .field-input,.field-select,.field-textarea{width:100%;padding:10px 13px;background:rgba(139,126,255,0.04);border:1px solid rgba(139,126,255,0.22);color:var(--text-high);font-family:var(--font-body);font-size:0.88rem;outline:none;transition:border-color .22s;appearance:none}
    .field-input::placeholder,.field-textarea::placeholder{color:var(--text-dim)}
    .field-input:focus,.field-select:focus,.field-textarea:focus{border-color:var(--prc-violet);box-shadow:0 0 0 2px rgba(139,126,255,0.12)}
    .field-textarea{resize:vertical;min-height:90px}
    .field-hint{font-size:0.74rem;color:var(--text-dim);margin-top:5px}
    .field-row{display:flex;gap:12px}
    .field-row .field{flex:1}
    .upload-zone{border:1.5px dashed rgba(139,126,255,0.28);background:rgba(139,126,255,0.03);padding:14px;text-align:center;cursor:pointer!important;transition:all .25s;position:relative;overflow:hidden}
    .upload-zone:hover{border-color:var(--prc-violet);background:rgba(139,126,255,0.08)}
    .upload-zone input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer!important;width:100%;height:100%}
    .upload-zone-label{font-family:var(--font-hud);font-size:0.54rem;font-weight:700;color:var(--text-soft);letter-spacing:0.08em;display:block;margin-bottom:2px}
    .upload-zone-sub{font-size:0.70rem;color:var(--text-dim)}
    /* BUTTONS */
    .btn-primary{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 22px;font-family:var(--font-hud);font-size:0.60rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--prc-violet);border:1px solid var(--prc-violet)!important;cursor:pointer!important;transition:all .25s;background:transparent;clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
    .btn-primary:hover{background:rgba(139,126,255,0.12);box-shadow:0 0 28px rgba(139,126,255,0.48);color:#fff}
    .btn-sky{color:var(--creo-sky);border-color:rgba(68,217,255,0.50)!important}
    .btn-sky:hover{background:rgba(68,217,255,0.12);box-shadow:0 0 28px rgba(68,217,255,0.40)}
    .btn-red{color:#FF4D6A;border-color:rgba(255,77,106,0.40)!important}
    .btn-red:hover{background:rgba(255,77,106,0.12);box-shadow:0 0 28px rgba(255,77,106,0.45)}
    .btn-sm{padding:7px 14px;font-size:0.52rem;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%)}
    .btn-xs{padding:5px 10px;font-size:0.46rem;clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%)}
    .btn-full{width:100%}
    /* SECTION LABEL */
    .section-label{font-family:var(--font-hud);font-size:0.62rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-mid);margin-bottom:14px;display:flex;align-items:center;gap:10px;padding-bottom:8px;border-bottom:1px solid rgba(139,126,255,0.14)}
    .section-label::before{content:'//';color:rgba(139,126,255,0.35)}
    /* TABLES */
    .data-table-wrap{border:1px solid rgba(139,126,255,0.18);overflow:hidden;overflow-x:auto}
    .data-table-wrap::before{content:'';display:block;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent)}
    .data-table{width:100%;border-collapse:collapse}
    .data-table th{font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-soft);padding:10px 14px;background:rgba(139,126,255,0.07);border-bottom:1px solid rgba(139,126,255,0.18);text-align:left;white-space:nowrap}
    .data-table td{padding:10px 14px;border-bottom:1px solid rgba(139,126,255,0.07);font-size:0.86rem;color:var(--text-mid);vertical-align:middle}
    .data-table tr:last-child td{border-bottom:none}
    .data-table tr:hover td{background:rgba(139,126,255,0.04)}
    /* ICON BUTTONS */
    .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:1px solid;border-radius:2px;cursor:pointer!important;transition:all .20s;background:transparent;font-size:0.78rem}
    .icon-btn.edit{color:var(--creo-sky);border-color:rgba(68,217,255,0.30);background:rgba(68,217,255,0.04)}
    .icon-btn.edit:hover{background:rgba(68,217,255,0.14)}
    .icon-btn.del{color:#FF4D6A;border-color:rgba(255,77,106,0.30);background:rgba(255,77,106,0.04)}
    .icon-btn.del:hover{background:rgba(255,77,106,0.14)}
    /* BADGES */
    .badge{font-family:var(--font-hud);font-size:0.46rem;font-weight:700;padding:2px 8px;border:1px solid;clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%);letter-spacing:0.08em;text-transform:uppercase}
    .badge-rv{color:#8B7EFF;border-color:rgba(139,126,255,0.40);background:rgba(139,126,255,0.08)}
    .badge-mx{color:#44D9FF;border-color:rgba(68,217,255,0.40);background:rgba(68,217,255,0.08)}
    .badge-drone{color:#FFA030;border-color:rgba(255,160,48,0.40);background:rgba(255,160,48,0.08)}
    .badge-award{color:#FFD700;border-color:rgba(255,215,0,0.40);background:rgba(255,215,0,0.08)}
    /* NESTED ACCORDIONS */
    .accordion-section{background:rgba(139,126,255,0.02);border:1px solid rgba(139,126,255,0.12);margin-bottom:8px}
    .accordion-hdr{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;cursor:pointer!important;transition:background .2s;gap:10px}
    .accordion-hdr:hover{background:rgba(139,126,255,0.06)}
    .accordion-hdr-left{display:flex;align-items:center;gap:10px;flex:1;min-width:0}
    .accordion-hdr-title{font-family:var(--font-hud);font-size:0.65rem;font-weight:700;color:var(--text-high);letter-spacing:0.04em}
    .accordion-hdr-meta{font-family:var(--font-hud);font-size:0.48rem;color:var(--text-dim);letter-spacing:0.08em;text-transform:uppercase;white-space:nowrap}
    .accordion-chevron{font-size:0.65rem;color:var(--text-dim);transition:transform .25s;flex-shrink:0}
    .accordion-body{display:none;padding:14px 16px 16px;border-top:1px solid rgba(139,126,255,0.10)}
    .accordion-section.open .accordion-body{display:block}
    .accordion-section.open .accordion-chevron{transform:rotate(90deg);color:var(--prc-violet)}
    /* INLINE ADD FORM */
    .inline-add-form{background:rgba(139,126,255,0.03);border:1px dashed rgba(139,126,255,0.18);padding:14px;margin-top:10px}
    .inline-add-form .form-title{font-family:var(--font-hud);font-size:0.54rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--prc-violet);margin-bottom:12px;display:flex;align-items:center;gap:6px}
    .inline-add-form .form-title::before{content:'+ ';color:rgba(139,126,255,0.50)}
    /* ITEM ROWS */
    .item-row{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid rgba(139,126,255,0.07)}
    .item-row:last-of-type{border-bottom:none}
    .item-row-text{flex:1;font-size:0.85rem;color:var(--text-mid)}
    .item-row-num{font-family:var(--font-hud);font-size:0.44rem;color:var(--text-dim);background:rgba(139,126,255,0.08);border:1px solid rgba(139,126,255,0.18);padding:2px 7px;border-radius:2px;white-space:nowrap}
    /* TAG CHIPS */
    .tag-chip{display:inline-flex;align-items:center;gap:5px;font-family:var(--font-hud);font-size:0.52rem;font-weight:700;letter-spacing:0.06em;padding:3px 10px;border:1px solid rgba(139,126,255,0.28);background:rgba(139,126,255,0.06);color:var(--prc-violet);margin:2px}
    .tag-chip button{color:rgba(255,77,106,0.60);cursor:pointer!important;font-size:0.65rem;padding:0;line-height:1;background:none;border:none;transition:color .2s}
    .tag-chip button:hover{color:#FF4D6A}
    /* DIFFICULTY PREVIEW BAR */
    .diff-preview{display:flex;align-items:center;gap:10px;margin-top:8px}
    .diff-bar{flex:1;height:4px;background:rgba(139,126,255,0.10);border-radius:4px;overflow:hidden}
    .diff-bar-fill{height:4px;border-radius:4px;background:linear-gradient(90deg,rgba(139,126,255,0.50),var(--prc-violet));transition:width .4s}
    .diff-lbl{font-family:var(--font-hud);font-size:0.48rem;color:var(--text-soft);white-space:nowrap}
    /* EMPTY ROW */
    .empty-row td{text-align:center;padding:28px;font-family:var(--font-hud);font-size:0.56rem;color:var(--text-dim);letter-spacing:0.10em}
    /* MODALS */
    .modal-overlay{display:none;position:fixed;inset:0;z-index:9000;background:rgba(3,2,13,0.88);backdrop-filter:blur(10px);align-items:center;justify-content:center;padding:20px;overflow-y:auto}
    .modal-overlay.open{display:flex}
    .modal-box{background:#0A0918;border:1px solid var(--border-neon);max-width:560px;width:100%;position:relative;max-height:90vh;overflow-y:auto;margin:auto}
    .modal-box.wide{max-width:740px}
    .modal-box::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent)}
    .modal-hdr{padding:16px 20px 13px;border-bottom:1px solid rgba(139,126,255,0.14);background:rgba(139,126,255,0.06);display:flex;align-items:center;justify-content:space-between;gap:12px}
    .modal-hdr h3{font-family:var(--font-hud);font-size:0.70rem;font-weight:700;letter-spacing:0.06em;color:var(--prc-violet)}
    .modal-close{width:28px;height:28px;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.20)!important;color:var(--text-soft);display:flex;align-items:center;justify-content:center;cursor:pointer!important;font-size:0.70rem;transition:all .2s}
    .modal-close:hover{background:rgba(139,126,255,0.14);color:var(--prc-violet)}
    .modal-body{padding:20px}
    .modal-footer{padding:12px 20px;border-top:1px solid rgba(139,126,255,0.12);display:flex;gap:10px;justify-content:flex-end}
    .confirm-icon{font-size:2rem;color:#FF4D6A;display:block;margin-bottom:12px}
    .confirm-title{font-family:var(--font-hud);font-size:0.90rem;font-weight:800;color:#fff;margin-bottom:8px}
    .confirm-text{font-size:0.88rem;color:var(--text-mid);line-height:1.70}
    /* SCROLL */
    ::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:var(--bg-void)}::-webkit-scrollbar-thumb{background:var(--prc-violet);border-radius:2px}
    /* RESPONSIVE */
    @media(max-width:900px){.admin-shell{grid-template-columns:0 1fr}.admin-sidebar-slot{display:none}.admin-main{padding:18px 16px 48px}body{cursor:auto}button{cursor:pointer}.cursor-dot,.cursor-ring{display:none}}
    @media(max-width:768px){.two-col,.three-col{grid-template-columns:1fr}.field-row{flex-direction:column}}
  </style>
</head>
<body>
<div class="cursor-dot" id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<div class="admin-shell" id="adminShell">
  <div class="admin-sidebar-slot" id="sidebarSlot"></div>

  <!-- TOPBAR -->
  <header class="admin-topbar">
    <div class="topbar-breadcrumb">PRC Admin <i class="fi fi-rr-angle-right" style="font-size:.55rem"></i> <span>Categories</span></div>
    <div class="topbar-right">
      <button class="topbar-icon-btn" onclick="location.reload()" title="Refresh"><i class="fi fi-rr-refresh"></i></button>
      <a href="categories.php" target="_blank" class="topbar-public-btn"><i class="fi fi-rr-eye"></i> Public View</a>
      <div class="topbar-date"><span id="topbar-date-display"></span></div>
    </div>
  </header>

  <!-- MAIN CONTENT -->
  <main class="admin-main">
    <div class="page-header">
      <div>
        <div class="page-eyebrow"><span class="dot-live"></span> Content Management // Categories</div>
        <h1 class="page-title"><span class="accent">Categories</span> Manager</h1>
      </div>
      <div class="page-stats">
        <div class="stat-chip"><span class="stat-chip-num"><?= count($tracks) ?></span><span class="stat-chip-lbl">Tracks</span></div>
        <div class="stat-chip"><span class="stat-chip-num"><?= $total_subs ?></span><span class="stat-chip-lbl">Sub-cats</span></div>
        <div class="stat-chip"><span class="stat-chip-num"><?= $total_tabs ?></span><span class="stat-chip-lbl">Tabs</span></div>
        <div class="stat-chip"><span class="stat-chip-num"><?= $total_items ?></span><span class="stat-chip-lbl">Items</span></div>
      </div>
    </div>

    <?php if ($flash): ?>
    <div class="flash-wrap">
      <div class="flash-inner <?= $flash['type'] ?>">
        <i class="fi fi-<?= $flash['type']==='success' ? 'rr-check' : 'rr-cross' ?>"></i>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="admin-tabs">
      <button class="admin-tab active" onclick="switchTab('meta',this)">Page Settings</button>
      <button class="admin-tab" onclick="switchTab('tracks',this)">Tracks</button>
      <button class="admin-tab" onclick="switchTab('subs',this)">Sub-categories</button>
      <button class="admin-tab" onclick="switchTab('content',this)">Tab Content</button>
    </div>

    <!-- ══════════════════════════════════════
         TAB 1 — PAGE SETTINGS
    ══════════════════════════════════════ -->
    <div class="tab-pane active" id="tab-meta">
      <div class="panel-card">
        <div class="panel-hdr"><i class="fi fi-rr-settings"></i><div class="panel-hdr-text"><h3>Page Settings</h3><p>Hero description shown at the top of the public categories page</p></div></div>
        <div class="panel-body">
          <form method="POST" action="admin-categories.php">
            <input type="hidden" name="action" value="save_meta"/>
            <div class="field">
              <label class="field-label">Hero Description <span class="req">*</span></label>
              <textarea class="field-textarea" name="hero_desc" rows="4" required><?= htmlspecialchars($hero_desc) ?></textarea>
              <div class="field-hint">Shown below "All Categories" on the public page.</div>
            </div>
            <button type="submit" class="btn-primary"><i class="fi fi-rr-check"></i> Save Description</button>
          </form>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════
         TAB 2 — TRACKS
    ══════════════════════════════════════ -->
    <div class="tab-pane" id="tab-tracks">
      <div class="two-col">
        <!-- Add Track -->
        <div class="panel-card sky-card">
          <div class="panel-hdr sky"><i class="fi fi-rr-folder-add"></i><div class="panel-hdr-text"><h3>Add Track</h3><p>Top-level sidebar section (e.g. RoboVenture, MakeX)</p></div></div>
          <div class="panel-body">
            <form method="POST" action="admin-categories.php" enctype="multipart/form-data">
              <input type="hidden" name="action" value="add_track"/>
              <div class="field-row">
                <div class="field"><label class="field-label">Slug <span class="req">*</span></label><input class="field-input" type="text" name="track_slug" placeholder="rv" maxlength="50" required/><div class="field-hint">Lowercase, no spaces. e.g. rv, mx, drone</div></div>
                <div class="field"><label class="field-label">Display Name <span class="req">*</span></label><input class="field-input" type="text" name="track_name" placeholder="RoboVenture" maxlength="100" required/></div>
              </div>
              <div class="field-row">
                <div class="field"><label class="field-label">Color Theme</label>
                  <select class="field-select" name="track_color">
                    <option value="rv">RoboVenture (violet)</option>
                    <option value="mx">MakeX (sky/cyan)</option>
                    <option value="drone">Drone Soccer (amber)</option>
                  </select>
                </div>
                <div class="field"><label class="field-label">Sort Order</label><input class="field-input" type="number" name="track_sort" value="0" min="0"/></div>
              </div>
              <div class="field-row">
                <div class="field"><label class="field-label">Logo File</label><div class="upload-zone"><input type="file" name="track_logo" accept="image/*"/><span class="upload-zone-label">Upload logo</span><span class="upload-zone-sub">PNG preferred</span></div></div>
                <div class="field"><label class="field-label">— or Logo URL/Path</label><input class="field-input" type="text" name="track_logo_url" placeholder="assets/Roboventure Logo.png"/></div>
              </div>
              <button type="submit" class="btn-primary btn-sky btn-full" style="margin-top:6px"><i class="fi fi-rr-plus"></i> Add Track</button>
            </form>
          </div>
        </div>

        <!-- Track list -->
        <div>
          <div class="section-label">All Tracks</div>
          <div class="data-table-wrap">
            <table class="data-table">
              <thead><tr><th>Logo</th><th>Name</th><th>Slug</th><th>Color</th><th>Sort</th><th>Actions</th></tr></thead>
              <tbody>
                <?php if (empty($tracks)): ?>
                <tr class="empty-row"><td colspan="6">No tracks yet.</td></tr>
                <?php else: foreach ($tracks as $t): ?>
                <tr>
                  <td><?php if ($t['track_logo']): ?><img src="<?= htmlspecialchars($t['track_logo']) ?>" style="height:26px;width:auto;opacity:.85"/><?php else: ?>—<?php endif; ?></td>
                  <td><strong style="font-family:var(--font-hud);font-size:.68rem;color:var(--text-high)"><?= htmlspecialchars($t['track_name']) ?></strong><?php if (!$t['is_active']): ?> <span style="font-family:var(--font-hud);font-size:.40rem;color:var(--text-dim)">HIDDEN</span><?php endif; ?></td>
                  <td><code style="font-family:var(--font-hud);font-size:.68rem;color:var(--prc-violet);background:rgba(139,126,255,0.08);padding:2px 6px"><?= htmlspecialchars($t['track_slug']) ?></code></td>
                  <td><span class="badge <?= color_badge($t['track_color']) ?>"><?= htmlspecialchars($t['track_color']) ?></span></td>
                  <td style="color:var(--text-dim);font-size:.80rem"><?= $t['track_sort'] ?></td>
                  <td style="white-space:nowrap">
                    <button class="icon-btn edit" title="Edit" onclick="openEditTrack(<?= $t['track_id'] ?>,'<?= addslashes($t['track_slug']) ?>','<?= addslashes($t['track_name']) ?>','<?= addslashes($t['track_logo']??'') ?>','<?= $t['track_color'] ?>',<?= $t['track_sort'] ?>,<?= $t['is_active'] ?>)"><i class="fi fi-rr-edit"></i></button>
                    <button class="icon-btn del" title="Delete" onclick="openConfirm('track',<?= $t['track_id'] ?>,'<?= addslashes($t['track_name']) ?>')"><i class="fi fi-rr-trash"></i></button>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════
         TAB 3 — SUB-CATEGORIES
    ══════════════════════════════════════ -->
    <div class="tab-pane" id="tab-subs">

      <!-- Add Sub-category -->
      <div class="panel-card sky-card" style="margin-bottom:28px">
        <div class="panel-hdr sky"><i class="fi fi-rr-layer-plus"></i><div class="panel-hdr-text"><h3>Add Sub-category</h3><p>Sidebar sub-items — each gets its own content panel on the public page</p></div></div>
        <div class="panel-body">
          <form method="POST" action="admin-categories.php">
            <input type="hidden" name="action" value="add_sub"/>
            <div class="three-col">
              <div class="field"><label class="field-label">Parent Track <span class="req">*</span></label>
                <select class="field-select" name="track_id" required>
                  <option value="" disabled selected>— Select track —</option>
                  <?php foreach ($tracks as $t): ?><option value="<?= $t['track_id'] ?>"><?= htmlspecialchars($t['track_name']) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="field"><label class="field-label">Slug <span class="req">*</span></label><input class="field-input" type="text" name="sub_slug" placeholder="am" maxlength="50" required/><div class="field-hint">Unique within the track.</div></div>
              <div class="field"><label class="field-label">Name <span class="req">*</span></label><input class="field-input" type="text" name="sub_name" placeholder="Aspiring Makers" maxlength="150" required/></div>
            </div>
            <div class="three-col">
              <div class="field"><label class="field-label">Panel Icon Class</label><input class="field-input" type="text" name="sub_icon" value="fi-rr-settings" maxlength="100"/><div class="field-hint">Flaticon class, e.g. fi-rr-rocket</div></div>
              <div class="field"><label class="field-label">Eyebrow Label</label><input class="field-input" type="text" name="sub_eyebrow" placeholder="RoboVenture — 01" maxlength="150"/></div>
              <div class="field"><label class="field-label">Panel Title</label><input class="field-input" type="text" name="sub_title" placeholder="Aspiring Makers" maxlength="200"/><div class="field-hint">Defaults to sub name if blank.</div></div>
            </div>
            <div class="field"><label class="field-label">Description</label><textarea class="field-textarea" name="sub_desc" rows="3" placeholder="Full description shown on the public category panel…"></textarea></div>
            <div class="three-col">
              <div class="field"><label class="field-label">Difficulty % (0–100)</label><input class="field-input" type="number" name="difficulty_pct" value="50" min="0" max="100" id="addDiffPct" oninput="updateDiffBar('addDiffBar','addDiffLbl',this.value)"/><div class="diff-preview"><div class="diff-bar"><div class="diff-bar-fill" id="addDiffBar" style="width:50%"></div></div><span class="diff-lbl" id="addDiffLbl">50%</span></div></div>
              <div class="field"><label class="field-label">Difficulty Label</label><input class="field-input" type="text" name="difficulty_label" value="Intermediate" maxlength="50" placeholder="Beginner / Intermediate / Advanced / Expert"/></div>
              <div class="field"><label class="field-label">Sort Order</label><input class="field-input" type="number" name="sub_sort" value="0" min="0"/></div>
            </div>
            <button type="submit" class="btn-primary btn-sky"><i class="fi fi-rr-plus"></i> Add Sub-category</button>
          </form>
        </div>
      </div>

      <!-- Sub-categories by track -->
      <?php foreach ($tracks as $track):
        $tsubs = $subs_by_track[$track['track_id']] ?? [];
      ?>
      <div class="panel-card" style="margin-bottom:20px">
        <div class="panel-hdr" style="justify-content:space-between">
          <div style="display:flex;align-items:center;gap:10px">
            <?php if ($track['track_logo']): ?><img src="<?= htmlspecialchars($track['track_logo']) ?>" style="height:24px;width:auto;opacity:.8"/><?php endif; ?>
            <strong style="font-family:var(--font-hud);font-size:.68rem;color:var(--text-high)"><?= htmlspecialchars($track['track_name']) ?></strong>
            <span class="badge <?= color_badge($track['track_color']) ?>"><?= count($tsubs) ?> sub<?= count($tsubs)===1?'':'s' ?></span>
          </div>
        </div>
        <div class="panel-body">
          <?php if (empty($tsubs)): ?>
          <p style="font-family:var(--font-hud);font-size:.55rem;color:var(--text-dim);letter-spacing:.10em">No sub-categories yet. Use the form above.</p>
          <?php else: ?>
          <div class="data-table-wrap">
            <table class="data-table">
              <thead><tr><th>#</th><th>Slug</th><th>Name</th><th>Difficulty</th><th>Sort</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($tsubs as $si => $sub): ?>
                <tr>
                  <td style="font-family:var(--font-hud);font-size:.58rem;color:var(--text-dim)"><?= str_pad($si+1,2,'0',STR_PAD_LEFT) ?></td>
                  <td><code style="font-family:var(--font-hud);font-size:.66rem;color:var(--prc-violet);background:rgba(139,126,255,0.08);padding:2px 6px"><?= htmlspecialchars($sub['sub_slug']) ?></code></td>
                  <td>
                    <div style="font-family:var(--font-hud);font-size:.65rem;font-weight:700;color:var(--text-high)"><?= htmlspecialchars($sub['sub_name']) ?></div>
                    <?php if ($sub['sub_title'] && $sub['sub_title'] !== $sub['sub_name']): ?>
                    <div style="font-size:.74rem;color:var(--text-dim);margin-top:2px"><?= htmlspecialchars($sub['sub_title']) ?></div>
                    <?php endif; ?>
                    <?php if (!$sub['is_active']): ?><span style="font-family:var(--font-hud);font-size:.40rem;color:var(--text-dim)">HIDDEN</span><?php endif; ?>
                  </td>
                  <td>
                    <div class="diff-preview" style="min-width:120px">
                      <div class="diff-bar"><div class="diff-bar-fill" style="width:<?= $sub['difficulty_pct'] ?>%"></div></div>
                      <span class="diff-lbl"><?= htmlspecialchars($sub['difficulty_label']) ?></span>
                    </div>
                  </td>
                  <td style="color:var(--text-dim);font-size:.80rem"><?= $sub['sub_sort'] ?></td>
                  <td style="white-space:nowrap">
                    <button class="icon-btn edit" title="Edit" onclick="openEditSub(<?= htmlspecialchars(json_encode($sub)) ?>)"><i class="fi fi-rr-edit"></i></button>
                    <button class="icon-btn del" title="Delete" onclick="openConfirm('sub',<?= $sub['sub_id'] ?>,'<?= addslashes($sub['sub_name']) ?>')"><i class="fi fi-rr-trash"></i></button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>

          <!-- Tags manager per sub -->
          <div class="section-label" style="margin-top:18px">Tags per Sub-category</div>
          <?php foreach ($tsubs as $sub):
            $stags = $all_tags[$sub['sub_id']] ?? [];
          ?>
          <div class="accordion-section" id="tagsec-<?= $sub['sub_id'] ?>">
            <div class="accordion-hdr" onclick="toggleAccordion('tagsec-<?= $sub['sub_id'] ?>')">
              <div class="accordion-hdr-left">
                <span class="accordion-hdr-title"><?= htmlspecialchars($sub['sub_name']) ?></span>
                <span class="accordion-hdr-meta"><?= count($stags) ?> tag<?= count($stags)===1?'':'s' ?></span>
              </div>
              <i class="fi fi-rr-angle-right accordion-chevron"></i>
            </div>
            <div class="accordion-body">
              <!-- Existing tags -->
              <div style="margin-bottom:10px;display:flex;flex-wrap:wrap;gap:4px">
                <?php if (empty($stags)): ?>
                <span style="font-family:var(--font-hud);font-size:.52rem;color:var(--text-dim)">No tags yet.</span>
                <?php else: foreach ($stags as $tag): ?>
                <span class="tag-chip">
                  <?= htmlspecialchars($tag['tag_text']) ?>
                  <form method="POST" action="admin-categories.php" style="display:inline">
                    <input type="hidden" name="action" value="delete_tag"/>
                    <input type="hidden" name="tag_id" value="<?= $tag['tag_id'] ?>"/>
                    <button type="submit" title="Remove tag">×</button>
                  </form>
                </span>
                <?php endforeach; endif; ?>
              </div>
              <!-- Add tag inline -->
              <form method="POST" action="admin-categories.php" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
                <input type="hidden" name="action" value="add_tag"/>
                <input type="hidden" name="sub_id" value="<?= $sub['sub_id'] ?>"/>
                <div class="field" style="flex:1;margin-bottom:0"><label class="field-label">Tag Text</label><input class="field-input" type="text" name="tag_text" placeholder="e.g. Beginner" maxlength="100" required/></div>
                <div class="field" style="width:80px;margin-bottom:0"><label class="field-label">Sort</label><input class="field-input" type="number" name="tag_sort" value="<?= count($stags) ?>" min="0"/></div>
                <button type="submit" class="btn-primary btn-xs btn-sky" style="margin-bottom:0;align-self:flex-end"><i class="fi fi-rr-plus"></i> Add</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ══════════════════════════════════════
         TAB 4 — TAB CONTENT (Tabs, Sections, Items)
    ══════════════════════════════════════ -->
    <div class="tab-pane" id="tab-content">
      <p style="font-family:var(--font-hud);font-size:.58rem;color:var(--text-soft);letter-spacing:.10em;margin-bottom:20px">
        // Manage tabs, detail-card sections, and bullet-point items per sub-category.
      </p>

      <?php foreach ($tracks as $track):
        $tsubs = $subs_by_track[$track['track_id']] ?? [];
        if (empty($tsubs)) continue;
      ?>
      <div class="panel-card" style="margin-bottom:24px">
        <div class="panel-hdr" style="justify-content:space-between">
          <div style="display:flex;align-items:center;gap:10px">
            <?php if ($track['track_logo']): ?><img src="<?= htmlspecialchars($track['track_logo']) ?>" style="height:24px;width:auto;opacity:.8"/><?php endif; ?>
            <strong style="font-family:var(--font-hud);font-size:.68rem;color:var(--text-high)"><?= htmlspecialchars($track['track_name']) ?></strong>
          </div>
        </div>
        <div class="panel-body">
          <?php foreach ($tsubs as $sub):
            $stabs    = $all_tabs[$sub['sub_id']] ?? [];
          ?>
          <div class="accordion-section" id="subsec-<?= $sub['sub_id'] ?>">
            <div class="accordion-hdr" onclick="toggleAccordion('subsec-<?= $sub['sub_id'] ?>')">
              <div class="accordion-hdr-left">
                <span class="accordion-hdr-title"><?= htmlspecialchars($sub['sub_name']) ?></span>
                <span class="accordion-hdr-meta"><?= count($stabs) ?> tab<?= count($stabs)===1?'':'s' ?></span>
              </div>
              <i class="fi fi-rr-angle-right accordion-chevron"></i>
            </div>
            <div class="accordion-body">

              <!-- ── TABS ───────────────────────────── -->
              <div class="section-label" style="margin-bottom:12px">Tabs</div>
              <?php foreach ($stabs as $tab):
                $tsections = $all_sections[$tab['tab_id']] ?? [];
              ?>
              <div class="accordion-section" id="tabsec-<?= $tab['tab_id'] ?>" style="margin-bottom:6px">
                <div class="accordion-hdr" onclick="toggleAccordion('tabsec-<?= $tab['tab_id'] ?>')">
                  <div class="accordion-hdr-left">
                    <span class="accordion-hdr-title" style="font-size:.60rem"><?= htmlspecialchars($tab['tab_label']) ?></span>
                    <code style="font-family:var(--font-hud);font-size:.46rem;color:var(--creo-sky);background:rgba(68,217,255,0.08);padding:1px 5px"><?= htmlspecialchars($tab['tab_slug']) ?></code>
                    <span class="accordion-hdr-meta"><?= count($tsections) ?> section<?= count($tsections)===1?'':'s' ?></span>
                  </div>
                  <div style="display:flex;align-items:center;gap:6px" onclick="event.stopPropagation()">
                    <button class="icon-btn edit btn-xs" title="Edit tab" onclick="openEditTab(<?= $tab['tab_id'] ?>,'<?= addslashes($tab['tab_slug']) ?>','<?= addslashes($tab['tab_label']) ?>',<?= $tab['tab_sort'] ?>,<?= $tab['is_active'] ?>)"><i class="fi fi-rr-edit"></i></button>
                    <button class="icon-btn del btn-xs" title="Delete tab" onclick="openConfirm('tab',<?= $tab['tab_id'] ?>,'<?= addslashes($tab['tab_label']) ?> tab')"><i class="fi fi-rr-trash"></i></button>
                    <i class="fi fi-rr-angle-right accordion-chevron" style="pointer-events:none"></i>
                  </div>
                </div>
                <div class="accordion-body">

                  <!-- ── SECTIONS ─────────────────────── -->
                  <?php foreach ($tsections as $sec):
                    $sitems = $all_items[$sec['section_id']] ?? [];
                  ?>
                  <div style="background:rgba(139,126,255,0.02);border:1px solid rgba(139,126,255,0.10);padding:12px 14px;margin-bottom:8px">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px">
                      <div style="display:flex;align-items:center;gap:8px">
                        <i class="<?= htmlspecialchars($sec['section_icon']) ?>" style="color:var(--prc-violet);font-size:.80rem"></i>
                        <strong style="font-family:var(--font-hud);font-size:.62rem;color:var(--text-high)"><?= htmlspecialchars($sec['section_label']) ?></strong>
                        <span style="font-family:var(--font-hud);font-size:.44rem;color:var(--text-dim)"><?= count($sitems) ?> item<?= count($sitems)===1?'':'s' ?></span>
                      </div>
                      <div style="display:flex;gap:5px">
                        <button class="icon-btn edit btn-xs" title="Edit section" onclick="openEditSection(<?= $sec['section_id'] ?>,'<?= addslashes($sec['section_icon']) ?>','<?= addslashes($sec['section_label']) ?>',<?= $sec['section_sort'] ?>)"><i class="fi fi-rr-edit"></i></button>
                        <button class="icon-btn del btn-xs" title="Delete section" onclick="openConfirm('section',<?= $sec['section_id'] ?>,'<?= addslashes($sec['section_label']) ?> section')"><i class="fi fi-rr-trash"></i></button>
                      </div>
                    </div>

                    <!-- Items -->
                    <?php foreach ($sitems as $item): ?>
                    <div class="item-row">
                      <span class="item-row-text"><?= htmlspecialchars($item['item_text']) ?></span>
                      <span class="item-row-num">sort: <?= $item['item_sort'] ?></span>
                      <button class="icon-btn edit btn-xs" title="Edit item" onclick="openEditItem(<?= $item['item_id'] ?>,'<?= addslashes($item['item_text']) ?>',<?= $item['item_sort'] ?>)"><i class="fi fi-rr-edit"></i></button>
                      <button class="icon-btn del btn-xs" title="Delete item" onclick="openConfirm('item',<?= $item['item_id'] ?>,'this bullet item')"><i class="fi fi-rr-trash"></i></button>
                    </div>
                    <?php endforeach; ?>

                    <!-- Add item inline -->
                    <form method="POST" action="admin-categories.php" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-top:10px">
                      <input type="hidden" name="action" value="add_item"/>
                      <input type="hidden" name="section_id" value="<?= $sec['section_id'] ?>"/>
                      <div class="field" style="flex:1;margin-bottom:0"><label class="field-label" style="font-size:.44rem">Add Bullet Item</label><input class="field-input" type="text" name="item_text" placeholder="e.g. Grades 4–6 recommended" maxlength="500" required/></div>
                      <div class="field" style="width:70px;margin-bottom:0"><label class="field-label" style="font-size:.44rem">Sort</label><input class="field-input" type="number" name="item_sort" value="<?= count($sitems) ?>" min="0"/></div>
                      <button type="submit" class="btn-primary btn-xs" style="align-self:flex-end"><i class="fi fi-rr-plus"></i> Add</button>
                    </form>
                  </div>
                  <?php endforeach; ?>

                  <!-- Add section form -->
                  <div class="inline-add-form">
                    <div class="form-title">Add Detail Card Section</div>
                    <form method="POST" action="admin-categories.php">
                      <input type="hidden" name="action" value="add_section"/>
                      <input type="hidden" name="tab_id" value="<?= $tab['tab_id'] ?>"/>
                      <div class="field-row">
                        <div class="field"><label class="field-label">Icon Class</label><input class="field-input" type="text" name="section_icon" value="fi-rr-info" maxlength="100"/><div class="field-hint">e.g. fi-rr-users, fi-rr-trophy</div></div>
                        <div class="field"><label class="field-label">Label <span class="req">*</span></label><input class="field-input" type="text" name="section_label" placeholder="Who Can Join" maxlength="150" required/></div>
                        <div class="field"><label class="field-label">Sort</label><input class="field-input" type="number" name="section_sort" value="<?= count($tsections) ?>" min="0"/></div>
                      </div>
                      <button type="submit" class="btn-primary btn-xs"><i class="fi fi-rr-plus"></i> Add Section Card</button>
                    </form>
                  </div>
                </div><!-- /accordion-body tab -->
              </div><!-- /accordion-section tab -->
              <?php endforeach; // tabs ?>

              <!-- Add tab inline -->
              <div class="inline-add-form" style="margin-top:10px">
                <div class="form-title">Add Tab to <?= htmlspecialchars($sub['sub_name']) ?></div>
                <form method="POST" action="admin-categories.php">
                  <input type="hidden" name="action" value="add_tab"/>
                  <input type="hidden" name="sub_id" value="<?= $sub['sub_id'] ?>"/>
                  <div class="field-row">
                    <div class="field"><label class="field-label">Slug <span class="req">*</span></label><input class="field-input" type="text" name="tab_slug" placeholder="overview" maxlength="50" required/></div>
                    <div class="field"><label class="field-label">Label <span class="req">*</span></label><input class="field-input" type="text" name="tab_label" placeholder="Overview" maxlength="100" required/></div>
                    <div class="field"><label class="field-label">Sort</label><input class="field-input" type="number" name="tab_sort" value="<?= count($stabs) ?>" min="0"/></div>
                  </div>
                  <button type="submit" class="btn-primary btn-xs btn-sky"><i class="fi fi-rr-plus"></i> Add Tab</button>
                </form>
              </div>

            </div><!-- /accordion-body sub -->
          </div><!-- /accordion-section sub -->
          <?php endforeach; // subs ?>
        </div>
      </div>
      <?php endforeach; // tracks ?>
    </div>

  </main><!-- /admin-main -->
</div><!-- /admin-shell -->

<!-- ══ EDIT TRACK MODAL ══ -->
<div class="modal-overlay" id="modal-edit-track">
  <div class="modal-box">
    <div class="modal-hdr"><h3>Edit Track</h3><button class="modal-close" onclick="closeModal('modal-edit-track')"><i class="fi fi-rr-cross"></i></button></div>
    <form method="POST" action="admin-categories.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit_track"/>
      <input type="hidden" name="track_id" id="et-id"/>
      <div class="modal-body">
        <div class="field-row">
          <div class="field"><label class="field-label">Slug <span class="req">*</span></label><input class="field-input" type="text" name="track_slug" id="et-slug" maxlength="50" required/></div>
          <div class="field"><label class="field-label">Name <span class="req">*</span></label><input class="field-input" type="text" name="track_name" id="et-name" maxlength="100" required/></div>
        </div>
        <div class="field-row">
          <div class="field"><label class="field-label">Color</label>
            <select class="field-select" name="track_color" id="et-color">
              <option value="rv">RoboVenture (violet)</option><option value="mx">MakeX (sky)</option><option value="drone">Drone Soccer (amber)</option>
            </select>
          </div>
          <div class="field"><label class="field-label">Sort</label><input class="field-input" type="number" name="track_sort" id="et-sort" min="0"/></div>
          <div class="field"><label class="field-label">Visible</label><select class="field-select" name="is_active" id="et-active"><option value="1">Yes</option><option value="0">Hidden</option></select></div>
        </div>
        <div class="field"><label class="field-label">New Logo File</label><div class="upload-zone"><input type="file" name="track_logo" accept="image/*"/><span class="upload-zone-label">Replace logo</span><span class="upload-zone-sub">Leave empty to keep current</span></div></div>
        <div class="field"><label class="field-label">Logo URL / Path</label><input class="field-input" type="text" name="track_logo_url" id="et-logo" placeholder="current path"/></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary btn-sm" onclick="closeModal('modal-edit-track')">Cancel</button>
        <button type="submit" class="btn-primary btn-sm btn-sky"><i class="fi fi-rr-check"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT SUB MODAL ══ -->
<div class="modal-overlay" id="modal-edit-sub">
  <div class="modal-box wide">
    <div class="modal-hdr"><h3>Edit Sub-category</h3><button class="modal-close" onclick="closeModal('modal-edit-sub')"><i class="fi fi-rr-cross"></i></button></div>
    <form method="POST" action="admin-categories.php">
      <input type="hidden" name="action" value="edit_sub"/>
      <input type="hidden" name="sub_id" id="es-id"/>
      <div class="modal-body">
        <div class="three-col">
          <div class="field"><label class="field-label">Parent Track</label>
            <select class="field-select" name="track_id" id="es-track">
              <?php foreach ($tracks as $t): ?><option value="<?= $t['track_id'] ?>"><?= htmlspecialchars($t['track_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label class="field-label">Slug <span class="req">*</span></label><input class="field-input" type="text" name="sub_slug" id="es-slug" maxlength="50" required/></div>
          <div class="field"><label class="field-label">Name <span class="req">*</span></label><input class="field-input" type="text" name="sub_name" id="es-name" maxlength="150" required/></div>
        </div>
        <div class="three-col">
          <div class="field"><label class="field-label">Icon Class</label><input class="field-input" type="text" name="sub_icon" id="es-icon" maxlength="100"/></div>
          <div class="field"><label class="field-label">Eyebrow</label><input class="field-input" type="text" name="sub_eyebrow" id="es-eyebrow" maxlength="150"/></div>
          <div class="field"><label class="field-label">Panel Title</label><input class="field-input" type="text" name="sub_title" id="es-title" maxlength="200"/></div>
        </div>
        <div class="field"><label class="field-label">Description</label><textarea class="field-textarea" name="sub_desc" id="es-desc" rows="4"></textarea></div>
        <div class="three-col">
          <div class="field"><label class="field-label">Difficulty %</label><input class="field-input" type="number" name="difficulty_pct" id="es-dpct" min="0" max="100" oninput="updateDiffBar('editDiffBar','editDiffLbl',this.value)"/><div class="diff-preview"><div class="diff-bar"><div class="diff-bar-fill" id="editDiffBar" style="width:50%"></div></div><span class="diff-lbl" id="editDiffLbl">50%</span></div></div>
          <div class="field"><label class="field-label">Difficulty Label</label><input class="field-input" type="text" name="difficulty_label" id="es-dlbl" maxlength="50"/></div>
          <div class="field-row" style="gap:8px">
            <div class="field"><label class="field-label">Sort</label><input class="field-input" type="number" name="sub_sort" id="es-sort" min="0"/></div>
            <div class="field"><label class="field-label">Visible</label><select class="field-select" name="is_active" id="es-active"><option value="1">Yes</option><option value="0">Hidden</option></select></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary btn-sm" onclick="closeModal('modal-edit-sub')">Cancel</button>
        <button type="submit" class="btn-primary btn-sm btn-sky"><i class="fi fi-rr-check"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT TAB MODAL ══ -->
<div class="modal-overlay" id="modal-edit-tab">
  <div class="modal-box">
    <div class="modal-hdr"><h3>Edit Tab</h3><button class="modal-close" onclick="closeModal('modal-edit-tab')"><i class="fi fi-rr-cross"></i></button></div>
    <form method="POST" action="admin-categories.php">
      <input type="hidden" name="action" value="edit_tab"/>
      <input type="hidden" name="tab_id" id="etab-id"/>
      <div class="modal-body">
        <div class="field-row">
          <div class="field"><label class="field-label">Slug <span class="req">*</span></label><input class="field-input" type="text" name="tab_slug" id="etab-slug" maxlength="50" required/></div>
          <div class="field"><label class="field-label">Label <span class="req">*</span></label><input class="field-input" type="text" name="tab_label" id="etab-label" maxlength="100" required/></div>
        </div>
        <div class="field-row">
          <div class="field"><label class="field-label">Sort</label><input class="field-input" type="number" name="tab_sort" id="etab-sort" min="0"/></div>
          <div class="field"><label class="field-label">Visible</label><select class="field-select" name="is_active" id="etab-active"><option value="1">Yes</option><option value="0">Hidden</option></select></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary btn-sm" onclick="closeModal('modal-edit-tab')">Cancel</button>
        <button type="submit" class="btn-primary btn-sm btn-sky"><i class="fi fi-rr-check"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT SECTION MODAL ══ -->
<div class="modal-overlay" id="modal-edit-section">
  <div class="modal-box">
    <div class="modal-hdr"><h3>Edit Section Card</h3><button class="modal-close" onclick="closeModal('modal-edit-section')"><i class="fi fi-rr-cross"></i></button></div>
    <form method="POST" action="admin-categories.php">
      <input type="hidden" name="action" value="edit_section"/>
      <input type="hidden" name="section_id" id="esec-id"/>
      <div class="modal-body">
        <div class="field-row">
          <div class="field"><label class="field-label">Icon Class</label><input class="field-input" type="text" name="section_icon" id="esec-icon" maxlength="100"/></div>
          <div class="field"><label class="field-label">Label <span class="req">*</span></label><input class="field-input" type="text" name="section_label" id="esec-label" maxlength="150" required/></div>
          <div class="field"><label class="field-label">Sort</label><input class="field-input" type="number" name="section_sort" id="esec-sort" min="0"/></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary btn-sm" onclick="closeModal('modal-edit-section')">Cancel</button>
        <button type="submit" class="btn-primary btn-sm"><i class="fi fi-rr-check"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT ITEM MODAL ══ -->
<div class="modal-overlay" id="modal-edit-item">
  <div class="modal-box">
    <div class="modal-hdr"><h3>Edit Bullet Item</h3><button class="modal-close" onclick="closeModal('modal-edit-item')"><i class="fi fi-rr-cross"></i></button></div>
    <form method="POST" action="admin-categories.php">
      <input type="hidden" name="action" value="edit_item"/>
      <input type="hidden" name="item_id" id="eitem-id"/>
      <div class="modal-body">
        <div class="field-row">
          <div class="field"><label class="field-label">Item Text <span class="req">*</span></label><input class="field-input" type="text" name="item_text" id="eitem-text" maxlength="500" required/></div>
          <div class="field" style="max-width:90px"><label class="field-label">Sort</label><input class="field-input" type="number" name="item_sort" id="eitem-sort" min="0"/></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary btn-sm" onclick="closeModal('modal-edit-item')">Cancel</button>
        <button type="submit" class="btn-primary btn-sm"><i class="fi fi-rr-check"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ CONFIRM DELETE ══ -->
<div class="modal-overlay" id="modal-confirm">
  <div class="modal-box" style="max-width:400px;text-align:center">
    <div class="modal-hdr" style="justify-content:center;border-color:rgba(255,77,106,.25);background:rgba(255,77,106,.05)">
      <h3 style="color:#FF4D6A">Confirm Delete</h3>
    </div>
    <div class="modal-body">
      <i class="fi fi-rr-trash confirm-icon"></i>
      <div class="confirm-title" id="confirm-title">Delete?</div>
      <div class="confirm-text" id="confirm-text">This cannot be undone.</div>
    </div>
    <div class="modal-footer" style="justify-content:center">
      <button type="button" class="btn-primary btn-sm" onclick="closeModal('modal-confirm')">Cancel</button>
      <form method="POST" action="admin-categories.php" style="display:inline">
        <input type="hidden" name="action" id="confirm-action"/>
        <input type="hidden" name="track_id"   id="confirm-track-id"/>
        <input type="hidden" name="sub_id"     id="confirm-sub-id"/>
        <input type="hidden" name="tab_id"     id="confirm-tab-id"/>
        <input type="hidden" name="section_id" id="confirm-section-id"/>
        <input type="hidden" name="item_id"    id="confirm-item-id"/>
        <button type="submit" class="btn-primary btn-sm btn-red"><i class="fi fi-rr-trash"></i> Yes, Delete</button>
      </form>
    </div>
  </div>
</div>

<!-- ══ SIDEBAR ══ -->
<script>
(function(){
  var slot = document.getElementById('sidebarSlot');
  if (!slot) return;
  fetch('admin-sidebar.html').then(function(r){return r.text();}).then(function(html){
    slot.innerHTML = html;
    slot.querySelectorAll('script').forEach(function(old){var s=document.createElement('script');s.textContent=old.textContent;document.body.appendChild(s);});
  }).catch(function(){
    slot.innerHTML = '<aside style="position:fixed;top:0;left:0;height:100vh;width:248px;background:#04031A;border-right:1px solid rgba(139,126,255,0.18);display:flex;align-items:center;justify-content:center;font-family:Orbitron,monospace;font-size:0.55rem;color:rgba(139,126,255,0.40);letter-spacing:0.12em;text-align:center;padding:20px;">SIDEBAR<br/>COMPONENT<br/><span style="margin-top:8px;display:block;font-size:0.44rem">admin-sidebar.html</span></aside>';
  });
})();
document.addEventListener('prc-sidebar-toggle', function(e){ document.getElementById('adminShell').classList.toggle('sb-collapsed', e.detail.collapsed); });
(function(){ if (localStorage.getItem('prc_sidebar_collapsed')==='1') document.getElementById('adminShell').classList.add('sb-collapsed'); })();
</script>

<script>
// ── TOPBAR DATE ──
(function(){
  var el = document.getElementById('topbar-date-display');
  function upd(){ var d=new Date(),M=['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'],h=d.getHours(),m=d.getMinutes(),ap=h>=12?'PM':'AM';h=h%12||12;el.innerHTML=M[d.getMonth()]+' '+String(d.getDate()).padStart(2,'0')+', '+d.getFullYear()+'&nbsp;<span>'+String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+' '+ap+'</span>'; }
  upd(); setInterval(upd, 30000);
})();

// ── CURSOR ──
(function(){
  var dot = document.getElementById('cursorDot'), ring = document.getElementById('cursorRing');
  if (!dot || !ring) return;
  var mx=0,my=0,rx=0,ry=0;
  document.addEventListener('mousemove', function(e){ mx=e.clientX; my=e.clientY; dot.style.left=mx+'px'; dot.style.top=my+'px'; });
  (function l(){ rx+=(mx-rx)*.12; ry+=(my-ry)*.12; ring.style.left=rx+'px'; ring.style.top=ry+'px'; requestAnimationFrame(l); })();
  document.querySelectorAll('a,button').forEach(function(el){ el.addEventListener('mouseenter',function(){ ring.classList.add('hovered'); }); el.addEventListener('mouseleave',function(){ ring.classList.remove('hovered'); }); });
})();

// ── TABS ──
function switchTab(id, btn) {
  document.querySelectorAll('.tab-pane').forEach(function(p){ p.classList.remove('active'); });
  document.querySelectorAll('.admin-tab').forEach(function(b){ b.classList.remove('active'); });
  document.getElementById('tab-'+id).classList.add('active');
  btn.classList.add('active');
}

// ── ACCORDION ──
function toggleAccordion(id) {
  document.getElementById(id).classList.toggle('open');
}

// ── DIFFICULTY BAR PREVIEW ──
function updateDiffBar(barId, lblId, val) {
  val = Math.min(100, Math.max(0, parseInt(val)||0));
  var bar = document.getElementById(barId);
  var lbl = document.getElementById(lblId);
  if (bar) bar.style.width = val + '%';
  if (lbl) lbl.textContent = val + '%';
}

// ── MODALS ──
function openModal(id){ document.getElementById(id).classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id){ document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }
document.querySelectorAll('.modal-overlay').forEach(function(el){
  el.addEventListener('click', function(e){ if (e.target===el) closeModal(el.id); });
});
document.addEventListener('keydown', function(e){ if (e.key==='Escape') document.querySelectorAll('.modal-overlay.open').forEach(function(m){ closeModal(m.id); }); });

// ── OPEN EDIT MODALS ──
function openEditTrack(id, slug, name, logo, color, sort, active) {
  document.getElementById('et-id').value=id;
  document.getElementById('et-slug').value=slug;
  document.getElementById('et-name').value=name;
  document.getElementById('et-logo').value=logo;
  document.getElementById('et-color').value=color;
  document.getElementById('et-sort').value=sort;
  document.getElementById('et-active').value=active;
  openModal('modal-edit-track');
}

function openEditSub(s) {
  document.getElementById('es-id').value=s.sub_id;
  document.getElementById('es-track').value=s.track_id;
  document.getElementById('es-slug').value=s.sub_slug;
  document.getElementById('es-name').value=s.sub_name;
  document.getElementById('es-icon').value=s.sub_icon||'';
  document.getElementById('es-eyebrow').value=s.sub_eyebrow||'';
  document.getElementById('es-title').value=s.sub_title||'';
  document.getElementById('es-desc').value=s.sub_desc||'';
  document.getElementById('es-dpct').value=s.difficulty_pct;
  document.getElementById('es-dlbl').value=s.difficulty_label;
  document.getElementById('es-sort').value=s.sub_sort;
  document.getElementById('es-active').value=s.is_active;
  updateDiffBar('editDiffBar','editDiffLbl',s.difficulty_pct);
  openModal('modal-edit-sub');
}

function openEditTab(id, slug, label, sort, active) {
  document.getElementById('etab-id').value=id;
  document.getElementById('etab-slug').value=slug;
  document.getElementById('etab-label').value=label;
  document.getElementById('etab-sort').value=sort;
  document.getElementById('etab-active').value=active;
  openModal('modal-edit-tab');
}

function openEditSection(id, icon, label, sort) {
  document.getElementById('esec-id').value=id;
  document.getElementById('esec-icon').value=icon;
  document.getElementById('esec-label').value=label;
  document.getElementById('esec-sort').value=sort;
  openModal('modal-edit-section');
}

function openEditItem(id, text, sort) {
  document.getElementById('eitem-id').value=id;
  document.getElementById('eitem-text').value=text;
  document.getElementById('eitem-sort').value=sort;
  openModal('modal-edit-item');
}

// ── CONFIRM DELETE ──
function openConfirm(type, id, name) {
  var actionMap = { track:'delete_track', sub:'delete_sub', tab:'delete_tab', section:'delete_section', item:'delete_item' };
  var idMap     = { track:'track-id', sub:'sub-id', tab:'tab-id', section:'section-id', item:'item-id' };
  var warnMap   = {
    track: 'Deleting a track removes ALL its sub-categories, tabs, sections, and items.',
    sub:   'Deleting a sub-category removes ALL its tabs, sections, and items.',
    tab:   'Deleting a tab removes ALL its sections and bullet items.',
    section: 'Deleting a section removes ALL its bullet items.',
    item: 'The bullet item will be permanently removed.'
  };
  document.getElementById('confirm-title').textContent = 'Delete "' + name + '"?';
  document.getElementById('confirm-text').textContent  = warnMap[type] || 'This cannot be undone.';
  document.getElementById('confirm-action').value = actionMap[type];
  ['track-id','sub-id','tab-id','section-id','item-id'].forEach(function(f){ document.getElementById('confirm-'+f).value=''; });
  document.getElementById('confirm-'+idMap[type]).value = id;
  openModal('modal-confirm');
}
</script>
</body>
</html>