<?php
// PRC-WebApp/admin-gallery.php
session_start();

mysqli_report(MYSQLI_REPORT_OFF);

$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'prc_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die('<p style="color:#ff6b6b;padding:40px;font-family:monospace;">DB error: ' . htmlspecialchars($conn->connect_error) . '</p>');

define('GALLERY_UPLOAD_DIR', __DIR__ . '/assets/gallery/');
define('GALLERY_UPLOAD_URL', 'assets/gallery/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024);
define('ALLOWED_IMG', ['image/jpeg','image/png','image/gif','image/webp']);
if (!is_dir(GALLERY_UPLOAD_DIR)) mkdir(GALLERY_UPLOAD_DIR, 0755, true);

function set_flash($t, $m){ $_SESSION['gflash'] = ['type'=>$t,'msg'=>$m]; }
function get_flash(){ if(!empty($_SESSION['gflash'])){ $f=$_SESSION['gflash']; unset($_SESSION['gflash']); return $f; } return null; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_folder') {
        $name  = trim($_POST['folder_name']  ?? '');
        $label = trim($_POST['folder_label'] ?? '');
        $sort  = (int)($_POST['folder_sort'] ?? 0);
        if ($name === '') { set_flash('error','Folder name is required.'); }
        else {
            $s = $conn->prepare("INSERT INTO prc_gallery_folders (folder_name,folder_label,folder_sort) VALUES (?,?,?)");
            $s->bind_param('ssi',$name,$label,$sort);
            $s->execute() ? set_flash('success','Folder "'.$name.'" created.') : set_flash('error','Folder name may already exist.');
            $s->close();
        }
    }

    if ($action === 'edit_folder') {
        $fid   = (int)($_POST['folder_id']    ?? 0);
        $name  = trim($_POST['folder_name']   ?? '');
        $label = trim($_POST['folder_label']  ?? '');
        $sort  = (int)($_POST['folder_sort']  ?? 0);
        if ($fid && $name !== '') {
            $s = $conn->prepare("UPDATE prc_gallery_folders SET folder_name=?,folder_label=?,folder_sort=? WHERE folder_id=?");
            $s->bind_param('ssii',$name,$label,$sort,$fid);
            if ($s->execute()) set_flash('success','Folder updated.');
            else set_flash('error','That folder name already exists. Choose a different name.');
            $s->close();
        } else set_flash('error','Invalid folder data.');
    }

    if ($action === 'delete_folder') {
        $fid = (int)($_POST['folder_id'] ?? 0);
        if ($fid) {
            $pr = $conn->query("SELECT photo_file FROM prc_gallery_photos WHERE folder_id=$fid");
            if ($pr) while($row=$pr->fetch_assoc()){ $fp=__DIR__.'/'.ltrim($row['photo_file'],'/'); if(file_exists($fp)) @unlink($fp); }
            $conn->query("DELETE FROM prc_gallery_folders WHERE folder_id=$fid");
            set_flash('success','Folder and all its photos deleted.');
        }
    }

    if ($action === 'add_photos') {
        $fid  = (int)($_POST['folder_id'] ?? 0);
        $cat  = trim($_POST['photo_category'] ?? '');
        $sort = (int)($_POST['photo_sort']    ?? 0);
        if (!$fid) { set_flash('error','Select a folder first.'); }
        elseif (empty($_FILES['photo_files']['name'][0])) { set_flash('error','No files selected.'); }
        else {
            $files   = $_FILES['photo_files'];
            $count   = count($files['name']);
            $uploaded = 0;
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                if ($files['size'][$i] > MAX_FILE_SIZE) continue;
                $mime = mime_content_type($files['tmp_name'][$i]);
                if (!in_array($mime, ALLOWED_IMG)) continue;
                $ext  = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                $safe = 'prc_gal_' . $fid . '_' . time() . '_' . $i . '.' . strtolower($ext);
                $dest = GALLERY_UPLOAD_DIR . $safe;
                if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                    $path    = GALLERY_UPLOAD_URL . $safe;
                    $caption = trim($_POST['photo_captions'][$i] ?? '');
                    $s = $conn->prepare("INSERT INTO prc_gallery_photos (folder_id,photo_file,photo_caption,photo_category,photo_sort) VALUES (?,?,?,?,?)");
                    $s->bind_param('isssi',$fid,$path,$caption,$cat,$sort);
                    $s->execute(); $s->close();
                    $uploaded++;
                }
            }
            set_flash('success',$uploaded . ' photo(s) uploaded.');
        }
    }

    if ($action === 'edit_photo') {
        $pid  = (int)($_POST['photo_id']       ?? 0);
        $fid  = (int)($_POST['folder_id']      ?? 0);
        $cap  = trim($_POST['photo_caption']   ?? '');
        $cat  = trim($_POST['photo_category']  ?? '');
        $sort = (int)($_POST['photo_sort']     ?? 0);
        if ($pid) {
            $s = $conn->prepare("UPDATE prc_gallery_photos SET folder_id=?,photo_caption=?,photo_category=?,photo_sort=? WHERE photo_id=?");
            $s->bind_param('issii',$fid,$cap,$cat,$sort,$pid);
            $s->execute(); set_flash('success','Photo updated.'); $s->close();
        }
    }

    if ($action === 'delete_photo') {
        $pid = (int)($_POST['photo_id'] ?? 0);
        if ($pid) {
            $r = $conn->query("SELECT photo_file FROM prc_gallery_photos WHERE photo_id=$pid");
            if ($row = $r->fetch_assoc()){ $fp=__DIR__.'/'.ltrim($row['photo_file'],'/'); if(file_exists($fp)) @unlink($fp); }
            $conn->query("DELETE FROM prc_gallery_photos WHERE photo_id=$pid");
            set_flash('success','Photo deleted.');
        }
    }

    header('Location: admin-gallery.php'); exit;
}

$folders = [];
$fr = $conn->query("SELECT * FROM prc_gallery_folders ORDER BY folder_sort ASC, folder_name DESC");
if ($fr) while($r=$fr->fetch_assoc()) $folders[] = $r;

$photos_by_folder = [];
$pr = $conn->query("SELECT * FROM prc_gallery_photos ORDER BY folder_id, photo_sort ASC, photo_id DESC");
if ($pr) while($r=$pr->fetch_assoc()) $photos_by_folder[$r['folder_id']][] = $r;

$total_photos = array_sum(array_map('count', $photos_by_folder));
$flash = get_flash();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Gallery — PRC Admin</title>
  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css'>
  <style>
    /* ═══════════════════════════════════════════
       SHARED ADMIN SHELL VARS (matches dashboard)
    ═══════════════════════════════════════════ */
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
      --admin-red:      #FF4D6A;
      --admin-green:    #44FF88;
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

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: var(--font-body);
      background: var(--bg-void);
      color: var(--text-high);
      overflow-x: hidden;
      line-height: 1.6;
      min-height: 100vh;
      cursor: none;
    }
    img { max-width: 100%; display: block; }
    a { text-decoration: none; color: inherit; }
    ul { list-style: none; }
    button { font-family: inherit; border: none; background: none; cursor: none; }

    /* Grid bg */
    body::before {
      content: '';
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background-image:
        linear-gradient(rgba(139,126,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(139,126,255,0.03) 1px, transparent 1px);
      background-size: 44px 44px;
    }
    body::after {
      content: '';
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background: repeating-linear-gradient(to bottom, transparent, transparent 2px, rgba(0,0,0,0.025) 2px, rgba(0,0,0,0.025) 4px);
    }

    /* ── CUSTOM CURSOR ── */
    .cursor-dot  { position:fixed;width:8px;height:8px;border-radius:50%;background:var(--prc-violet);pointer-events:none;z-index:99999;transform:translate(-50%,-50%);box-shadow:0 0 18px rgba(139,126,255,.80);transition:transform .1s }
    .cursor-ring { position:fixed;width:36px;height:36px;border-radius:50%;border:1px solid rgba(139,126,255,.60);pointer-events:none;z-index:99998;transform:translate(-50%,-50%);transition:width .25s,height .25s }
    .cursor-ring.hovered { width:52px;height:52px;border-color:var(--creo-amber) }

    /* ═══ LAYOUT SHELL ═══ */
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
    .topbar-breadcrumb i { font-size: 0.55rem; }
    .topbar-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
    .topbar-icon-btn {
      width: 36px; height: 36px;
      background: rgba(139,126,255,0.05);
      border: 1px solid var(--border-neon) !important;
      border-radius: var(--radius);
      display: flex; align-items: center; justify-content: center;
      color: var(--text-soft);
      transition: var(--transition);
      cursor: none;
    }
    .topbar-icon-btn i { font-size: 0.90rem; }
    .topbar-icon-btn:hover { background: rgba(139,126,255,0.14); color: var(--prc-violet); border-color: var(--prc-violet) !important; }
    .topbar-date {
      font-family: var(--font-hud); font-size: 0.52rem;
      color: var(--text-dim); letter-spacing: 0.10em; white-space: nowrap;
    }
    .topbar-date span { color: var(--creo-volt); }
    .topbar-public-btn {
      display: inline-flex; align-items: center; gap: 7px;
      font-family: var(--font-hud); font-size: 0.56rem; font-weight: 700;
      letter-spacing: 0.10em; text-transform: uppercase;
      color: var(--prc-violet); padding: 7px 14px;
      border: 1px solid rgba(139,126,255,0.35) !important;
      background: rgba(139,126,255,0.06);
      clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
      transition: var(--transition);
    }
    .topbar-public-btn:hover { background: rgba(139,126,255,0.16); color: #fff; }

    /* ═══ MAIN CONTENT AREA ═══ */
    .admin-main {
      grid-column: 2; grid-row: 2;
      padding: 28px 28px 60px;
      overflow-y: auto;
      min-height: calc(100vh - var(--topbar-h));
    }

    /* ═══ PAGE HEADER ═══ */
    .page-header {
      display: flex; align-items: flex-start; justify-content: space-between;
      gap: 20px; flex-wrap: wrap; margin-bottom: 24px;
    }
    .page-eyebrow {
      font-family: var(--font-hud); font-size: 0.52rem;
      letter-spacing: 0.20em; text-transform: uppercase;
      color: var(--admin-red); margin-bottom: 6px;
      display: flex; align-items: center; gap: 8px;
    }
    .dot-live {
      width: 7px; height: 7px; background: var(--admin-red); border-radius: 50%;
      box-shadow: 0 0 8px rgba(255,77,106,0.90);
      animation: neonPulse 1s ease-in-out infinite;
    }
    .page-title {
      font-family: var(--font-hud); font-size: clamp(1.3rem, 3vw, 2rem);
      font-weight: 900; color: #fff; letter-spacing: -0.01em;
    }
    .page-title .accent { color: var(--admin-red); text-shadow: 0 0 22px rgba(255,77,106,0.70); }
    .page-stats { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
    .stat-chip {
      background: rgba(255,77,106,0.06); border: 1px solid rgba(255,77,106,0.20);
      padding: 10px 18px; text-align: center;
      clip-path: polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
    }
    .stat-chip-num { font-family: var(--font-hud); font-size: 1.4rem; font-weight: 800; color: var(--admin-red); display: block; line-height: 1; text-shadow: 0 0 14px rgba(255,77,106,0.70); }
    .stat-chip-lbl { font-family: var(--font-hud); font-size: 0.48rem; color: var(--text-soft); text-transform: uppercase; letter-spacing: 0.12em; display: block; margin-top: 3px; }

    /* ═══ FLASH MESSAGE ═══ */
    .flash-wrap { margin-bottom: 20px; animation: slideDown 0.35s ease; }
    .flash-inner {
      padding: 13px 20px; display: flex; align-items: center; gap: 10px;
      font-family: var(--font-hud); font-size: 0.63rem; font-weight: 600;
      letter-spacing: 0.08em; border: 1px solid;
    }
    .flash-inner.success { color: var(--admin-green); border-color: rgba(68,255,136,0.35); background: rgba(68,255,136,0.06); }
    .flash-inner.error   { color: var(--admin-red);   border-color: rgba(255,77,106,0.35); background: rgba(255,77,106,0.06); }

    /* ═══ GALLERY LAYOUT ═══ */
    .gallery-layout {
      display: grid;
      grid-template-columns: 300px 1fr;
      gap: 24px;
      align-items: start;
    }

    /* ── SIDEBAR PANEL ── */
    .gal-sidebar { display: flex; flex-direction: column; gap: 18px; position: sticky; top: calc(var(--topbar-h) + 20px); }

    .panel-card {
      background: var(--bg-card);
      border: 1px solid rgba(139,126,255,0.18);
      position: relative;
    }
    .panel-card::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
      background: linear-gradient(90deg, transparent, var(--prc-violet), transparent);
    }
    .panel-card.red-card { border-color: rgba(255,77,106,0.22); }
    .panel-card.red-card::before { background: linear-gradient(90deg, transparent, var(--admin-red), transparent); }

    .panel-hdr {
      background: rgba(139,126,255,0.05);
      padding: 14px 18px;
      border-bottom: 1px solid rgba(139,126,255,0.12);
      display: flex; align-items: center; gap: 10px;
    }
    .panel-hdr.red { background: rgba(255,77,106,0.05); border-color: rgba(255,77,106,0.15); }
    .panel-hdr i { color: var(--prc-violet); font-size: 0.95rem; }
    .panel-hdr.red i { color: var(--admin-red); }
    .panel-hdr-text h3 { font-family: var(--font-hud); font-size: 0.70rem; font-weight: 700; letter-spacing: 0.06em; color: var(--text-high); }
    .panel-hdr-text p { font-size: 0.76rem; color: var(--text-soft); margin-top: 2px; }
    .panel-body { padding: 18px 20px; }

    /* FORM FIELDS */
    .field { margin-bottom: 14px; }
    .field:last-of-type { margin-bottom: 0; }
    .field-label {
      font-family: var(--font-hud); font-size: 0.50rem; font-weight: 700;
      letter-spacing: 0.14em; text-transform: uppercase; color: var(--text-soft);
      display: flex; align-items: center; gap: 6px; margin-bottom: 7px;
    }
    .field-label .req { color: var(--admin-red); }
    .field-input, .field-select, .field-textarea {
      width: 100%; padding: 10px 13px;
      background: rgba(139,126,255,0.04);
      border: 1px solid rgba(139,126,255,0.22);
      color: var(--text-high); font-family: var(--font-body); font-size: 0.88rem;
      outline: none; transition: border-color 0.22s, box-shadow 0.22s; appearance: none;
    }
    .field-input::placeholder, .field-textarea::placeholder { color: var(--text-dim); }
    .field-input:focus, .field-select:focus, .field-textarea:focus {
      border-color: var(--prc-violet); box-shadow: 0 0 0 2px rgba(139,126,255,0.14);
    }
    .field-textarea { resize: vertical; min-height: 65px; }
    .field-hint { font-size: 0.74rem; color: var(--text-dim); margin-top: 5px; }

    /* Upload zone */
    .upload-zone {
      border: 1.5px dashed rgba(139,126,255,0.28);
      background: rgba(139,126,255,0.03);
      padding: 20px; text-align: center;
      cursor: pointer !important; transition: all 0.25s; position: relative; overflow: hidden;
    }
    .upload-zone:hover, .upload-zone.dragover {
      border-color: var(--prc-violet); background: rgba(139,126,255,0.08);
    }
    .upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer !important; width: 100%; height: 100%; }
    .upload-zone i { font-size: 1.4rem; color: rgba(139,126,255,0.40); display: block; margin-bottom: 7px; }
    .upload-zone-label { font-family: var(--font-hud); font-size: 0.58rem; font-weight: 700; color: var(--text-soft); letter-spacing: 0.08em; display: block; margin-bottom: 3px; }
    .upload-zone-sub { font-size: 0.74rem; color: var(--text-dim); }
    .upload-preview { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .upload-preview-thumb { width: 56px; height: 56px; overflow: hidden; border: 1px solid rgba(139,126,255,0.25); }
    .upload-preview-thumb img { width: 100%; height: 100%; object-fit: cover; }

    /* BUTTONS */
    .btn-primary {
      display: inline-flex; align-items: center; justify-content: center; gap: 8px;
      width: 100%; padding: 11px;
      font-family: var(--font-hud); font-size: 0.60rem; font-weight: 700;
      letter-spacing: 0.12em; text-transform: uppercase;
      color: var(--prc-violet); border: 1px solid var(--prc-violet) !important;
      box-shadow: 0 0 14px rgba(139,126,255,0.22);
      cursor: pointer !important; transition: all 0.25s; background: transparent;
      clip-path: polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);
    }
    .btn-primary:hover { background: rgba(139,126,255,0.12); box-shadow: 0 0 28px rgba(139,126,255,0.48); color: #fff; transform: translateY(-1px); }
    .btn-red { color: var(--admin-red); border-color: rgba(255,77,106,0.40) !important; box-shadow: 0 0 14px rgba(255,77,106,0.18); }
    .btn-red:hover { background: rgba(255,77,106,0.12); box-shadow: 0 0 28px rgba(255,77,106,0.45); }

    /* ── RIGHT PANEL ── */
    .gal-right { display: flex; flex-direction: column; gap: 0; }
    .gal-right-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 18px; flex-wrap: wrap; gap: 10px;
    }
    .gal-right-title {
      font-family: var(--font-hud); font-size: 0.78rem; font-weight: 700;
      letter-spacing: 0.06em; color: var(--text-high); display: flex; align-items: center; gap: 10px;
    }
    .count-pill {
      font-family: var(--font-hud); font-size: 0.48rem; font-weight: 700;
      padding: 3px 10px; border: 1px solid rgba(255,77,106,0.30);
      background: rgba(255,77,106,0.07); color: var(--admin-red); letter-spacing: 0.10em;
    }

    /* FOLDER BLOCK */
    .folder-block { border: 1px solid rgba(139,126,255,0.16); margin-bottom: 20px; overflow: visible; }
    .folder-block-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 13px 18px; background: rgba(139,126,255,0.05);
      border-bottom: 1px solid rgba(139,126,255,0.12);
      gap: 12px; flex-wrap: wrap;
    }
    .folder-block-title {
      font-family: var(--font-hud); font-size: 0.78rem; font-weight: 800;
      color: var(--text-high); letter-spacing: 0.04em;
      display: flex; align-items: center; gap: 10px;
    }
    .folder-block-title i { color: var(--prc-violet); font-size: 0.86rem; }
    .folder-photo-count {
      font-family: var(--font-hud); font-size: 0.48rem; color: var(--text-dim);
      letter-spacing: 0.10em; text-transform: uppercase;
      padding: 2px 8px; border: 1px solid rgba(139,126,255,0.20); background: rgba(139,126,255,0.05);
    }
    .folder-actions { display: flex; align-items: center; gap: 4px; }

    /* ICON BUTTONS */
    .icon-btn {
      display: inline-flex; align-items: center; justify-content: center;
      width: 32px; height: 32px; border: 1px solid; border-radius: 2px;
      cursor: pointer !important; transition: all 0.20s; background: transparent;
      font-size: 0.82rem; position: relative;
    }
    .icon-btn::after {
      content: attr(data-tip);
      position: absolute; bottom: calc(100% + 8px); left: 50%; transform: translateX(-50%);
      white-space: nowrap; font-family: var(--font-hud); font-size: 0.46rem; font-weight: 700;
      letter-spacing: 0.10em; text-transform: uppercase; padding: 5px 10px;
      background: rgba(6,5,26,0.96); border: 1px solid rgba(139,126,255,0.30); color: var(--text-mid);
      pointer-events: none; opacity: 0; transition: opacity 0.2s; z-index: 200;
    }
    .icon-btn::before {
      content: ''; position: absolute; bottom: calc(100% + 2px); left: 50%; transform: translateX(-50%);
      border: 5px solid transparent; border-top-color: rgba(139,126,255,0.30);
      pointer-events: none; opacity: 0; transition: opacity 0.2s; z-index: 200;
    }
    .icon-btn:hover::after, .icon-btn:hover::before { opacity: 1; }

    .icon-btn.edit  { color: var(--creo-sky);   border-color: rgba(68,217,255,0.30);  background: rgba(68,217,255,0.04); }
    .icon-btn.edit:hover  { background: rgba(68,217,255,0.14);  box-shadow: 0 0 12px rgba(68,217,255,0.28); }
    .icon-btn.add   { color: var(--admin-green); border-color: rgba(68,255,136,0.30); background: rgba(68,255,136,0.04); }
    .icon-btn.add:hover   { background: rgba(68,255,136,0.14); box-shadow: 0 0 12px rgba(68,255,136,0.28); }
    .icon-btn.del   { color: var(--admin-red);   border-color: rgba(255,77,106,0.30);  background: rgba(255,77,106,0.04); }
    .icon-btn.del:hover   { background: rgba(255,77,106,0.14);  box-shadow: 0 0 12px rgba(255,77,106,0.28); }

    /* PHOTO GRID */
    .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 8px; padding: 14px 18px; }
    .photo-item {
      position: relative; aspect-ratio: 4/3; overflow: visible;
      border: 1px solid rgba(139,126,255,0.12); background: rgba(0,0,8,0.60);
    }
    .photo-item > img { width: 100%; height: 100%; object-fit: cover; display: block; filter: brightness(0.80) saturate(0.70); transition: filter 0.3s; overflow: hidden; }
    .photo-item:hover > img { filter: brightness(0.95) saturate(1); }
    .photo-item-overlay {
      position: absolute; inset: 0; display: flex; flex-direction: column;
      justify-content: flex-end; padding: 7px;
      background: linear-gradient(to top, rgba(3,2,13,0.90) 0%, transparent 60%);
      opacity: 0; transition: opacity 0.3s;
    }
    .photo-item:hover .photo-item-overlay { opacity: 1; }
    .photo-item-cap {
      font-family: var(--font-hud); font-size: 0.42rem; color: var(--text-mid);
      letter-spacing: 0.06em; margin-bottom: 5px;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .photo-item-actions { display: flex; gap: 4px; }
    .no-photos {
      padding: 28px; text-align: center;
      font-family: var(--font-hud); font-size: 0.58rem; color: var(--text-dim); letter-spacing: 0.10em;
    }

    /* ═══ MODALS ═══ */
    .modal-overlay {
      display: none; position: fixed; inset: 0; z-index: 9000;
      background: rgba(3,2,13,0.88); backdrop-filter: blur(10px);
      align-items: center; justify-content: center; padding: 20px;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: #0A0918; border: 1px solid var(--border-neon);
      max-width: 480px; width: 100%; position: relative; max-height: 90vh; overflow-y: auto;
    }
    .modal-box::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
      background: linear-gradient(90deg, transparent, var(--prc-violet), transparent);
    }
    .modal-hdr {
      padding: 16px 20px 13px; border-bottom: 1px solid rgba(139,126,255,0.14);
      background: rgba(139,126,255,0.06); display: flex; align-items: center;
      justify-content: space-between; gap: 12px;
    }
    .modal-hdr h3 { font-family: var(--font-hud); font-size: 0.73rem; font-weight: 700; letter-spacing: 0.06em; color: var(--prc-violet); }
    .modal-close {
      width: 28px; height: 28px; background: rgba(139,126,255,0.06);
      border: 1px solid rgba(139,126,255,0.20) !important; color: var(--text-soft);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer !important; font-size: 0.72rem; transition: all 0.2s;
    }
    .modal-close:hover { background: rgba(139,126,255,0.14); color: var(--prc-violet); }
    .modal-body { padding: 20px; }
    .modal-footer { padding: 13px 20px; border-top: 1px solid rgba(139,126,255,0.12); display: flex; gap: 10px; justify-content: flex-end; }

    /* DELETE CONFIRM */
    .confirm-icon { font-size: 2rem; color: var(--admin-red); display: block; margin-bottom: 12px; }
    .confirm-title { font-family: var(--font-hud); font-size: 0.92rem; font-weight: 800; color: #fff; margin-bottom: 8px; }
    .confirm-text { font-size: 0.88rem; color: var(--text-mid); line-height: 1.70; }

    /* EMPTY STATE */
    .empty-state {
      text-align: center; padding: 60px 20px;
      border: 1px dashed rgba(139,126,255,0.18); background: rgba(139,126,255,0.02);
    }
    .empty-state i { font-size: 2rem; color: rgba(255,77,106,0.30); display: block; margin-bottom: 14px; }
    .empty-state p { font-family: var(--font-hud); font-size: 0.60rem; color: var(--text-dim); }

    /* ── KEYFRAMES ── */
    @keyframes neonPulse { 0%,100%{opacity:1} 50%{opacity:.7} }
    @keyframes slideDown { from{opacity:0;transform:translateY(-12px)} to{opacity:1;transform:translateY(0)} }

    /* ── SCROLLBAR ── */
    ::-webkit-scrollbar { width: 4px; }
    ::-webkit-scrollbar-track { background: var(--bg-void); }
    ::-webkit-scrollbar-thumb { background: var(--prc-violet); border-radius: 2px; }

    /* ── RESPONSIVE ── */
    @media (max-width: 1100px) { .gallery-layout { grid-template-columns: 280px 1fr; } }
    @media (max-width: 900px) {
      .admin-shell { grid-template-columns: 0 1fr; }
      .admin-sidebar-slot { display: none; }
      .admin-main { padding: 18px 16px 48px; }
      body { cursor: auto; }
      .cursor-dot, .cursor-ring { display: none; }
    }
    @media (max-width: 768px) {
      .gallery-layout { grid-template-columns: 1fr; }
      .gal-sidebar { position: static; }
      .photo-grid { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); }
    }
  </style>
</head>
<body>

<!-- Custom cursor -->
<div class="cursor-dot"  id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<div class="admin-shell" id="adminShell">

  <!-- ── SIDEBAR SLOT ── -->
  <div class="admin-sidebar-slot" id="sidebarSlot"></div>

  <!-- ── TOP BAR ── -->
  <header class="admin-topbar">
    <div class="topbar-breadcrumb">
      PRC Admin
      <i class="fi fi-rr-angle-right"></i>
      <span>Gallery</span>
    </div>
    <div class="topbar-right">
      <button class="topbar-icon-btn" title="Refresh page" onclick="location.reload()">
        <i class="fi fi-rr-refresh"></i>
      </button>
      <a href="gallery.php" target="_blank" class="topbar-public-btn">
        <i class="fi fi-rr-eye"></i> Public View
      </a>
      <div class="topbar-date"><span id="topbar-date-display"></span></div>
    </div>
  </header>

  <!-- ── MAIN ── -->
  <main class="admin-main">

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div>
        <div class="page-eyebrow"><span class="dot-live"></span> Content Management // Gallery</div>
        <h1 class="page-title"><span class="accent">Gallery</span> Manager</h1>
      </div>
      <div class="page-stats">
        <div class="stat-chip">
          <span class="stat-chip-num"><?= count($folders) ?></span>
          <span class="stat-chip-lbl">Folders</span>
        </div>
        <div class="stat-chip">
          <span class="stat-chip-num"><?= $total_photos ?></span>
          <span class="stat-chip-lbl">Photos</span>
        </div>
      </div>
    </div>

    <!-- FLASH -->
    <?php if ($flash): ?>
    <div class="flash-wrap">
      <div class="flash-inner <?= $flash['type'] ?>">
        <i class="fi fi-<?= $flash['type']==='success' ? 'rr-check' : 'rr-cross' ?>"></i>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- GALLERY LAYOUT -->
    <div class="gallery-layout">

      <!-- ── LEFT: FORMS ── -->
      <aside class="gal-sidebar">

        <!-- ADD FOLDER -->
        <div class="panel-card">
          <div class="panel-hdr">
            <i class="fi fi-rr-folder-add"></i>
            <div class="panel-hdr-text"><h3>New Folder</h3><p>Create a year / event folder</p></div>
          </div>
          <div class="panel-body">
            <form method="POST" action="admin-gallery.php" id="form-add-folder">
              <input type="hidden" name="action" value="add_folder" />
              <div class="field">
                <label class="field-label">Folder Name <span class="req">*</span></label>
                <input class="field-input" type="text" name="folder_name" placeholder="e.g. 2026" maxlength="100" required />
                <div class="field-hint">Short name used in IDs and filters.</div>
              </div>
              <div class="field">
                <label class="field-label">Display Label</label>
                <input class="field-input" type="text" name="folder_label" placeholder="e.g. 2026 Competition" maxlength="150" />
              </div>
              <div class="field" style="margin-bottom:16px">
                <label class="field-label">Sort Order</label>
                <input class="field-input" type="number" name="folder_sort" value="0" min="0" />
                <div class="field-hint">Lower = shown first.</div>
              </div>
              <button type="submit" class="btn-primary"><i class="fi fi-rr-plus"></i> Create Folder</button>
            </form>
          </div>
        </div>

        <!-- UPLOAD PHOTOS -->
        <div class="panel-card red-card">
          <div class="panel-hdr red">
            <i class="fi fi-rr-cloud-upload"></i>
            <div class="panel-hdr-text"><h3>Upload Photos</h3><p>Add images to a folder</p></div>
          </div>
          <div class="panel-body">
            <form method="POST" action="admin-gallery.php" enctype="multipart/form-data" id="form-upload">
              <input type="hidden" name="action" value="add_photos" />
              <div class="field">
                <label class="field-label">Target Folder <span class="req">*</span></label>
                <select class="field-select" name="folder_id" required>
                  <option value="" disabled selected>— Select folder —</option>
                  <?php foreach ($folders as $f): ?>
                  <option value="<?= $f['folder_id'] ?>"><?= htmlspecialchars($f['folder_label'] ?: $f['folder_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label class="field-label">Category</label>
                <input class="field-input" type="text" name="photo_category" placeholder="e.g. Drone Soccer, MakeX" maxlength="100" />
              </div>
              <div class="field">
                <label class="field-label">Sort Order</label>
                <input class="field-input" type="number" name="photo_sort" value="0" min="0" />
              </div>
              <div class="field">
                <label class="field-label">Images <span class="req">*</span></label>
                <div class="upload-zone" id="upload-zone">
                  <input type="file" name="photo_files[]" id="photo_files" multiple accept="image/jpeg,image/png,image/gif,image/webp" />
                  <i class="fi fi-rr-picture"></i>
                  <span class="upload-zone-label">Click or drag images here</span>
                  <span class="upload-zone-sub">JPG, PNG, GIF, WEBP — max 50 MB</span>
                </div>
                <div class="upload-preview" id="upload-preview"></div>
              </div>
              <button type="submit" class="btn-primary btn-red"><i class="fi fi-rr-upload"></i> Upload Photos</button>
            </form>
          </div>
        </div>

      </aside>

      <!-- ── RIGHT: FOLDER LIST ── -->
      <section class="gal-right">
        <div class="gal-right-header">
          <div class="gal-right-title">
            All Folders &amp; Photos
            <span class="count-pill"><?= count($folders) ?> folders · <?= $total_photos ?> photos</span>
          </div>
        </div>

        <?php if (empty($folders)): ?>
        <div class="empty-state">
          <i class="fi fi-rr-folder"></i>
          <p>No folders yet. Create your first one using the panel on the left.</p>
        </div>
        <?php else: ?>

        <?php foreach ($folders as $f):
          $fid    = $f['folder_id'];
          $photos = $photos_by_folder[$fid] ?? [];
          $label  = $f['folder_label'] ?: $f['folder_name'];
        ?>
        <div class="folder-block" id="folder-<?= $fid ?>">
          <div class="folder-block-header">
            <div class="folder-block-title">
              <i class="fi fi-rr-folder"></i>
              <?= htmlspecialchars($label) ?>
              <span class="folder-photo-count"><?= count($photos) ?> photos</span>
            </div>
            <div class="folder-actions">
              <button class="icon-btn add" data-tip="Upload to this folder"
                onclick="setUploadFolder(<?= $fid ?>, '<?= htmlspecialchars($label, ENT_QUOTES) ?>')"
                type="button" aria-label="Upload to this folder">
                <i class="fi fi-rr-cloud-upload"></i>
              </button>
              <button class="icon-btn edit" data-tip="Edit folder"
                onclick="openEditFolder(<?= $fid ?>,
                  '<?= htmlspecialchars($f['folder_name'], ENT_QUOTES) ?>',
                  '<?= htmlspecialchars($f['folder_label'] ?? '', ENT_QUOTES) ?>',
                  <?= (int)$f['folder_sort'] ?>)"
                type="button" aria-label="Edit folder">
                <i class="fi fi-rr-edit"></i>
              </button>
              <button class="icon-btn del" data-tip="Delete folder &amp; all photos"
                onclick="openConfirm('folder', <?= $fid ?>, '<?= htmlspecialchars($label, ENT_QUOTES) ?>', '<?= count($photos) ?> photo(s) inside')"
                type="button" aria-label="Delete folder">
                <i class="fi fi-rr-trash"></i>
              </button>
            </div>
          </div>

          <?php if (empty($photos)): ?>
          <div class="no-photos">No photos yet. Use the upload panel or click <i class="fi fi-rr-cloud-upload"></i> above.</div>
          <?php else: ?>
          <div class="photo-grid">
            <?php foreach ($photos as $p): ?>
            <div class="photo-item">
              <img src="<?= htmlspecialchars(ltrim($p['photo_file'], '/')) ?>" alt="<?= htmlspecialchars($p['photo_caption']??'') ?>" loading="lazy" />
              <div class="photo-item-overlay">
                <div class="photo-item-cap"><?= htmlspecialchars($p['photo_caption'] ?: '—') ?></div>
                <div class="photo-item-actions">
                  <button class="icon-btn edit" data-tip="Edit photo"
                    style="width:26px;height:26px;font-size:.72rem"
                    onclick="openEditPhoto(<?= $p['photo_id'] ?>, <?= $fid ?>,
                      '<?= htmlspecialchars($p['photo_caption'] ?? '', ENT_QUOTES) ?>',
                      '<?= htmlspecialchars($p['photo_category'] ?? '', ENT_QUOTES) ?>',
                      <?= (int)$p['photo_sort'] ?>)"
                    type="button" aria-label="Edit photo">
                    <i class="fi fi-rr-edit"></i>
                  </button>
                  <button class="icon-btn del" data-tip="Delete photo"
                    style="width:26px;height:26px;font-size:.72rem"
                    onclick="openConfirm('photo', <?= $p['photo_id'] ?>, '<?= htmlspecialchars($p['photo_caption'] ?: 'this photo', ENT_QUOTES) ?>', '')"
                    type="button" aria-label="Delete photo">
                    <i class="fi fi-rr-trash"></i>
                  </button>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

      </section>

    </div><!-- /gallery-layout -->

  </main><!-- /admin-main -->

</div><!-- /admin-shell -->

<!-- ══════ EDIT FOLDER MODAL ══════ -->
<div class="modal-overlay" id="modal-edit-folder">
  <div class="modal-box">
    <div class="modal-hdr">
      <h3>Edit Folder</h3>
      <button class="modal-close" onclick="closeModal('modal-edit-folder')"><i class="fi fi-rr-cross"></i></button>
    </div>
    <form method="POST" action="admin-gallery.php">
      <input type="hidden" name="action" value="edit_folder" />
      <input type="hidden" name="folder_id" id="ef-id" />
      <div class="modal-body">
        <div class="field">
          <label class="field-label">Folder Name <span class="req">*</span></label>
          <input class="field-input" type="text" name="folder_name" id="ef-name" maxlength="100" required />
        </div>
        <div class="field">
          <label class="field-label">Display Label</label>
          <input class="field-input" type="text" name="folder_label" id="ef-label" maxlength="150" />
        </div>
        <div class="field">
          <label class="field-label">Sort Order</label>
          <input class="field-input" type="number" name="folder_sort" id="ef-sort" min="0" />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary" style="width:auto;padding:9px 22px;clip-path:none" onclick="closeModal('modal-edit-folder')">Cancel</button>
        <button type="submit" class="btn-primary" style="width:auto;padding:9px 22px;clip-path:none"><i class="fi fi-rr-check"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════ EDIT PHOTO MODAL ══════ -->
<div class="modal-overlay" id="modal-edit-photo">
  <div class="modal-box">
    <div class="modal-hdr">
      <h3>Edit Photo</h3>
      <button class="modal-close" onclick="closeModal('modal-edit-photo')"><i class="fi fi-rr-cross"></i></button>
    </div>
    <form method="POST" action="admin-gallery.php">
      <input type="hidden" name="action" value="edit_photo" />
      <input type="hidden" name="photo_id" id="ep-id" />
      <div class="modal-body">
        <div class="field">
          <label class="field-label">Move to Folder</label>
          <select class="field-select" name="folder_id" id="ep-folder">
            <?php foreach ($folders as $f): ?>
            <option value="<?= $f['folder_id'] ?>"><?= htmlspecialchars($f['folder_label'] ?: $f['folder_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label class="field-label">Caption</label>
          <textarea class="field-textarea" name="photo_caption" id="ep-caption" rows="3" placeholder="e.g. Competition Floor — National Finals"></textarea>
        </div>
        <div class="field">
          <label class="field-label">Category</label>
          <input class="field-input" type="text" name="photo_category" id="ep-category" placeholder="e.g. Drone Soccer" maxlength="100" />
        </div>
        <div class="field">
          <label class="field-label">Sort Order</label>
          <input class="field-input" type="number" name="photo_sort" id="ep-sort" min="0" />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary" style="width:auto;padding:9px 22px;clip-path:none" onclick="closeModal('modal-edit-photo')">Cancel</button>
        <button type="submit" class="btn-primary" style="width:auto;padding:9px 22px;clip-path:none"><i class="fi fi-rr-check"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════ DELETE CONFIRM MODAL ══════ -->
<div class="modal-overlay" id="modal-confirm">
  <div class="modal-box" style="max-width:400px;text-align:center">
    <div class="modal-hdr" style="justify-content:center;border-color:rgba(255,77,106,0.25);background:rgba(255,77,106,0.05)">
      <h3 style="color:var(--admin-red)">Confirm Delete</h3>
    </div>
    <div class="modal-body">
      <i class="fi fi-rr-trash confirm-icon"></i>
      <div class="confirm-title" id="confirm-title">Delete?</div>
      <div class="confirm-text" id="confirm-text">This action cannot be undone.</div>
    </div>
    <div class="modal-footer" style="justify-content:center">
      <button type="button" class="btn-primary" style="width:auto;padding:9px 22px;clip-path:none" onclick="closeModal('modal-confirm')">Cancel</button>
      <form method="POST" action="admin-gallery.php" style="display:inline">
        <input type="hidden" name="action"    id="confirm-action" />
        <input type="hidden" name="folder_id" id="confirm-folder-id" />
        <input type="hidden" name="photo_id"  id="confirm-photo-id" />
        <button type="submit" class="btn-primary btn-red" style="width:auto;padding:9px 22px;clip-path:none"><i class="fi fi-rr-trash"></i> Yes, Delete</button>
      </form>
    </div>
  </div>
</div>

<!-- ══════ SIDEBAR INJECTION (same pattern as dashboard) ══════ -->
<script>
(function() {
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
      slot.innerHTML = '<aside style="position:fixed;top:0;left:0;height:100vh;width:248px;background:#04031A;border-right:1px solid rgba(139,126,255,0.18);display:flex;align-items:center;justify-content:center;font-family:Orbitron,monospace;font-size:0.55rem;color:rgba(139,126,255,0.40);letter-spacing:0.12em;text-align:center;padding:20px;">SIDEBAR<br/>COMPONENT<br/><span style="margin-top:8px;display:block;font-size:0.44rem;color:rgba(139,126,255,0.25)">admin-sidebar.html</span></aside>';
    });
})();
</script>

<script>
// ── SIDEBAR COLLAPSE SYNC (same as dashboard) ──
document.addEventListener('prc-sidebar-toggle', function(e){
  document.getElementById('adminShell').classList.toggle('sb-collapsed', e.detail.collapsed);
});
(function(){
  if (localStorage.getItem('prc_sidebar_collapsed') === '1')
    document.getElementById('adminShell').classList.add('sb-collapsed');
})();

// ── TOPBAR DATE ──
(function(){
  var el = document.getElementById('topbar-date-display');
  function update(){
    var d=new Date(), M=['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
    var h=d.getHours(), m=d.getMinutes(), ap=h>=12?'PM':'AM';
    h=h%12||12;
    el.innerHTML = M[d.getMonth()]+' '+String(d.getDate()).padStart(2,'0')+', '+d.getFullYear()
      +' &nbsp;<span>'+String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+' '+ap+'</span>';
  }
  update(); setInterval(update,30000);
})();

// ── CUSTOM CURSOR ──
(function(){
  var dot=document.getElementById('cursorDot'), ring=document.getElementById('cursorRing');
  if(!dot||!ring) return;
  var mx=0,my=0,rx=0,ry=0;
  document.addEventListener('mousemove',function(e){ mx=e.clientX; my=e.clientY; dot.style.left=mx+'px'; dot.style.top=my+'px'; });
  (function l(){ rx+=(mx-rx)*.12; ry+=(my-ry)*.12; ring.style.left=rx+'px'; ring.style.top=ry+'px'; requestAnimationFrame(l); })();
  document.querySelectorAll('a,button,.photo-item,.folder-block').forEach(function(el){
    el.addEventListener('mouseenter',function(){ ring.classList.add('hovered'); });
    el.addEventListener('mouseleave',function(){ ring.classList.remove('hovered'); });
  });
})();

// ── MODAL HELPERS ──
function openModal(id){ document.getElementById(id).classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id){ document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }
document.querySelectorAll('.modal-overlay').forEach(function(el){
  el.addEventListener('click',function(e){ if(e.target===el) closeModal(el.id); });
});
document.addEventListener('keydown',function(e){ if(e.key==='Escape') document.querySelectorAll('.modal-overlay.open').forEach(function(m){ closeModal(m.id); }); });

// ── EDIT FOLDER ──
function openEditFolder(id, name, label, sort){
  document.getElementById('ef-id').value    = id;
  document.getElementById('ef-name').value  = name;
  document.getElementById('ef-label').value = label;
  document.getElementById('ef-sort').value  = sort;
  openModal('modal-edit-folder');
}

// ── EDIT PHOTO ──
function openEditPhoto(id, folderId, caption, category, sort){
  document.getElementById('ep-id').value       = id;
  document.getElementById('ep-folder').value   = folderId;
  document.getElementById('ep-caption').value  = caption;
  document.getElementById('ep-category').value = category;
  document.getElementById('ep-sort').value     = sort;
  openModal('modal-edit-photo');
}

// ── DELETE CONFIRM ──
function openConfirm(type, id, name, extra){
  var title = type==='folder' ? 'Delete folder "'+name+'"?' : 'Delete "'+name+'"?';
  var text  = type==='folder'
    ? 'This will permanently delete the folder and all '+extra+' along with their files. This cannot be undone.'
    : 'This will permanently delete the photo and its file. This cannot be undone.';
  document.getElementById('confirm-title').textContent   = title;
  document.getElementById('confirm-text').textContent    = text;
  document.getElementById('confirm-action').value        = type==='folder' ? 'delete_folder' : 'delete_photo';
  document.getElementById('confirm-folder-id').value     = type==='folder' ? id : '';
  document.getElementById('confirm-photo-id').value      = type==='photo'  ? id : '';
  openModal('modal-confirm');
}

// ── SET UPLOAD FOLDER ──
function setUploadFolder(fid, label){
  var sel = document.querySelector('#form-upload select[name="folder_id"]');
  if(sel) sel.value = fid;
  document.getElementById('form-upload').closest('.panel-card').scrollIntoView({behavior:'smooth',block:'start'});
}

// ── UPLOAD PREVIEW ──
(function(){
  var input   = document.getElementById('photo_files');
  var preview = document.getElementById('upload-preview');
  var zone    = document.getElementById('upload-zone');
  if(!input) return;
  input.addEventListener('change',function(){
    preview.innerHTML='';
    Array.from(this.files).forEach(function(file){
      var wrap=document.createElement('div'); wrap.className='upload-preview-thumb';
      var img=document.createElement('img'); img.src=URL.createObjectURL(file); img.alt=file.name;
      wrap.appendChild(img); preview.appendChild(wrap);
    });
  });
  zone.addEventListener('dragover',function(e){ e.preventDefault(); zone.classList.add('dragover'); });
  zone.addEventListener('dragleave',function(){ zone.classList.remove('dragover'); });
  zone.addEventListener('drop',function(e){ e.preventDefault(); zone.classList.remove('dragover'); });
})();
</script>
</body>
</html>