<?php
// PRC-WebApp/admin-contacts.php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);

$db_host='localhost'; $db_user='root'; $db_pass=''; $db_name='prc_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die('<p style="color:#ff6b6b;padding:40px;font-family:monospace;">DB error: '.htmlspecialchars($conn->connect_error).'</p>');

function set_flash($t,$m){ $_SESSION['cflash']=['type'=>$t,'msg'=>$m]; }
function get_flash(){ if(!empty($_SESSION['cflash'])){ $f=$_SESSION['cflash']; unset($_SESSION['cflash']); return $f; } return null; }

// ── SAVE (upsert) a single setting ──────────────────────────
function save_setting($conn, $key, $val){
    $s = $conn->prepare("INSERT INTO prc_contact_settings(setting_key,setting_val) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_val=?");
    $s->bind_param('sss', $key, $val, $val);
    return $s->execute();
}

// ── HANDLE POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Save Hero Description ──
    if ($action === 'save_hero') {
        $val = trim($_POST['hero_desc'] ?? '');
        save_setting($conn, 'hero_desc', $val)
            ? set_flash('success', 'Hero description updated.')
            : set_flash('error', 'Failed to update hero description.');
    }

    // ── Save Contact Details ──
    if ($action === 'save_contact') {
        $fields = ['email', 'phone', 'address_line1', 'address_line2', 'address_note'];
        $ok = true;
        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '');
            if (!save_setting($conn, $f, $val)) $ok = false;
        }
        $ok ? set_flash('success', 'Contact details updated.') : set_flash('error', 'Some fields failed to save.');
    }

    // ── Save Social Links ──
    if ($action === 'save_social') {
        $fields = ['facebook_url', 'facebook_label'];
        $ok = true;
        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '');
            if (!save_setting($conn, $f, $val)) $ok = false;
        }
        $ok ? set_flash('success', 'Social links updated.') : set_flash('error', 'Some fields failed to save.');
    }

    // ── Save Response Hours ──
    if ($action === 'save_hours') {
        $fields = ['hours_weekday', 'hours_saturday', 'hours_sunday'];
        $ok = true;
        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '');
            if (!save_setting($conn, $f, $val)) $ok = false;
        }
        $ok ? set_flash('success', 'Response hours updated.') : set_flash('error', 'Some fields failed to save.');
    }

    // ── Save Map Settings ──
    if ($action === 'save_map') {
        $fields = ['maps_embed_url', 'maps_directions_url', 'maps_label', 'maps_sublabel'];
        $ok = true;
        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '');
            if (!save_setting($conn, $f, $val)) $ok = false;
        }
        $ok ? set_flash('success', 'Map settings updated.') : set_flash('error', 'Some fields failed to save.');
    }

    // ── Reset a single field to original default ──
    if ($action === 'reset_field') {
        $key = $_POST['field_key'] ?? '';
        $defaults = [
            'hero_desc'           => 'Got a question about registration, categories, or the competition? Reach out — our team is happy to help you and your school get started.',
            'email'               => 'philippineroboticscup@gmail.com',
            'phone'               => '+63 917 771 3961',
            'address_line1'       => '117 Technology Ave., Laguna Technopark,',
            'address_line2'       => 'Biñan, Laguna 4024, Philippines',
            'address_note'        => 'Creotec Philippines Inc. — Primary organizer',
            'facebook_url'        => 'https://www.facebook.com/profile.php?id=61579706372017',
            'facebook_label'      => 'Philippine Robotics Cup',
            'maps_embed_url'      => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d500!2d121.0603888!3d14.274057899999999!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397d7e7290006651%3A0x2881cf0867301e8e!2sGruppo%20EMS%2C%20Inc.!5e0!3m2!1sen!2sph!4v1',
            'maps_directions_url' => 'https://maps.google.com/?q=CREOTEC+Philippines+117+Technology+Ave+Laguna+Technopark+Binan+Laguna',
            'maps_label'          => 'Gruppo EMS, Inc.',
            'maps_sublabel'       => '117 Technology Ave., SEPZ, Biñan, Laguna 4024',
            'hours_weekday'       => '9:00 AM – 5:00 PM',
            'hours_saturday'      => '10:00 AM – 2:00 PM',
            'hours_sunday'        => 'Closed',
        ];
        if (array_key_exists($key, $defaults)) {
            save_setting($conn, $key, $defaults[$key])
                ? set_flash('success', '"'.$key.'" reset to original default.')
                : set_flash('error', 'Reset failed.');
        } else {
            set_flash('error', 'Unknown field key.');
        }
    }

    header('Location: admin-contacts.php'); exit;
}

// ── FETCH ALL SETTINGS ───────────────────────────────────────
$cfg = [];
$res = $conn->query("SELECT setting_key, setting_val FROM prc_contact_settings");
if ($res) while ($r = $res->fetch_assoc()) $cfg[$r['setting_key']] = $r['setting_val'];
$flash = get_flash();
$conn->close();

function cv($cfg, $key, $fallback='') { return htmlspecialchars($cfg[$key] ?? $fallback); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Contact Settings — PRC Admin</title>
  <link rel="icon" type="image/png" href="assets/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css'>
  <style>
    :root{--sb-width:248px;--sb-collapsed:68px;--topbar-h:60px;--bg-void:#03020D;--bg-card:rgba(10,8,30,0.80);--prc-violet:#8B7EFF;--prc-ice:#B8ADFF;--creo-amber:#FFA030;--creo-volt:#FFE930;--creo-sky:#8B7EFF;--admin-red:#FF4D6A;--admin-green:#44FF88;--admin-blue:#8B7EFF;--border-neon:rgba(139,126,255,0.18);--text-high:#F2EEFF;--text-mid:#C8C0F0;--text-soft:#9A90CC;--text-dim:#6058A0;--font-hud:'Orbitron',monospace;--font-body:'Exo 2',sans-serif}
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
    .admin-shell{display:grid;grid-template-columns:var(--sb-width) 1fr;grid-template-rows:var(--topbar-h) 1fr;min-height:100vh;position:relative;z-index:1;transition:grid-template-columns .30s cubic-bezier(.77,0,.175,1)}
    .admin-shell.sb-collapsed{grid-template-columns:var(--sb-collapsed) 1fr}
    .admin-sidebar-slot{grid-row:1/-1;grid-column:1}
    /* TOPBAR */
    .admin-topbar{grid-column:2;grid-row:1;height:var(--topbar-h);background:rgba(3,2,13,0.92);backdrop-filter:blur(20px);border-bottom:1px solid var(--border-neon);display:flex;align-items:center;padding:0 28px;gap:16px;position:sticky;top:0;z-index:800}
    .topbar-breadcrumb{display:flex;align-items:center;gap:8px;font-family:var(--font-hud);font-size:0.60rem;color:var(--text-dim);letter-spacing:0.10em;text-transform:uppercase}
    .topbar-breadcrumb span{color:var(--admin-blue)}
    .topbar-right{margin-left:auto;display:flex;align-items:center;gap:10px}
    .topbar-icon-btn{width:36px;height:36px;background:rgba(139,126,255,0.05);border:1px solid var(--border-neon)!important;border-radius:3px;display:flex;align-items:center;justify-content:center;color:var(--text-soft);transition:all .25s;cursor:none}
    .topbar-icon-btn:hover{background:rgba(139,126,255,0.14);color:var(--prc-violet)}
    .topbar-public-btn{display:inline-flex;align-items:center;gap:7px;font-family:var(--font-hud);font-size:0.56rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--admin-blue);padding:7px 14px;border:1px solid rgba(139,126,255,0.35)!important;background:rgba(139,126,255,0.06);clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);transition:all .25s}
    .topbar-public-btn:hover{background:rgba(139,126,255,0.16);color:#fff}
    .topbar-date{font-family:var(--font-hud);font-size:0.52rem;color:var(--text-dim);letter-spacing:0.10em;white-space:nowrap}
    .topbar-date span{color:var(--creo-volt)}
    /* MAIN */
    .admin-main{grid-column:2;grid-row:2;padding:28px 28px 60px;overflow-y:auto;min-height:calc(100vh - var(--topbar-h))}
    /* PAGE HEADER */
    .page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:24px}
    .page-eyebrow{font-family:var(--font-hud);font-size:0.52rem;letter-spacing:0.20em;text-transform:uppercase;color:var(--admin-blue);margin-bottom:6px;display:flex;align-items:center;gap:8px}
    .dot-live{width:7px;height:7px;background:var(--admin-blue);border-radius:50%;box-shadow:0 0 8px rgba(139,126,255,0.90);animation:pulse 1s ease-in-out infinite}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:.7}}
    .page-title{font-family:var(--font-hud);font-size:clamp(1.3rem,3vw,2rem);font-weight:900;color:#fff;letter-spacing:-0.01em}
    .page-title .accent{color:var(--admin-blue);text-shadow:0 0 22px rgba(139,126,255,0.70)}
    /* GRID */
    .settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
    .settings-grid.full{grid-template-columns:1fr}
    /* FLASH */
    .flash-wrap{margin-bottom:20px}
    .flash-inner{padding:13px 20px;display:flex;align-items:center;gap:10px;font-family:var(--font-hud);font-size:0.63rem;font-weight:600;letter-spacing:0.08em;border:1px solid}
    .flash-inner.success{color:var(--admin-green);border-color:rgba(68,255,136,0.35);background:rgba(68,255,136,0.06)}
    .flash-inner.error{color:var(--admin-red);border-color:rgba(255,77,106,0.35);background:rgba(255,77,106,0.06)}
    /* PANEL */
    .panel-card{background:var(--bg-card);border:1px solid rgba(139,126,255,0.18);position:relative;margin-bottom:0}
    .panel-card::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent)}
    .panel-card.blue-card::before{background:linear-gradient(90deg,transparent,var(--admin-blue),transparent)}
    .panel-card.sky-card::before{background:linear-gradient(90deg,transparent,var(--creo-sky),transparent)}
    .panel-card.green-card::before{background:linear-gradient(90deg,transparent,var(--admin-green),transparent)}
    .panel-card.amber-card::before{background:linear-gradient(90deg,transparent,var(--creo-amber),transparent)}
    .panel-hdr{background:rgba(139,126,255,0.05);padding:14px 18px;border-bottom:1px solid rgba(139,126,255,0.12);display:flex;align-items:center;justify-content:space-between;gap:10px}
    .panel-hdr-left{display:flex;align-items:center;gap:10px}
    .panel-hdr i{color:var(--prc-violet);font-size:0.95rem}
    .panel-hdr.blue i{color:var(--admin-blue)}
    .panel-hdr.green i{color:var(--admin-green)}
    .panel-hdr.amber i{color:var(--creo-amber)}
    .panel-hdr-text h3{font-family:var(--font-hud);font-size:0.70rem;font-weight:700;letter-spacing:0.06em;color:var(--text-high)}
    .panel-hdr-text p{font-size:0.76rem;color:var(--text-soft);margin-top:2px}
    .panel-body{padding:18px 20px}
    /* FORM */
    .field{margin-bottom:16px}
    .field:last-of-type{margin-bottom:0}
    .field-label{font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-soft);display:flex;align-items:center;justify-content:space-between;margin-bottom:7px}
    .field-label .req{color:var(--admin-red)}
    .field-input,.field-textarea{width:100%;padding:10px 13px;background:rgba(139,126,255,0.04);border:1px solid rgba(139,126,255,0.22);color:var(--text-high);font-family:var(--font-body);font-size:0.88rem;outline:none;transition:border-color .22s,box-shadow .22s;appearance:none}
    .field-input::placeholder,.field-textarea::placeholder{color:var(--text-dim)}
    .field-input:focus,.field-textarea:focus{border-color:var(--prc-violet);box-shadow:0 0 0 2px rgba(139,126,255,0.14)}
    .field-textarea{resize:vertical;min-height:90px;line-height:1.6}
    .field-hint{font-size:0.74rem;color:var(--text-dim);margin-top:5px}
    .field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    /* PREVIEW BOX */
    .preview-box{background:rgba(139,126,255,0.04);border:1px solid rgba(139,126,255,0.14);padding:12px 14px;margin-top:8px;font-size:0.84rem;color:var(--text-mid);line-height:1.65;word-break:break-all}
    .preview-label{font-family:var(--font-hud);font-size:0.46rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-dim);margin-bottom:6px}
    /* MAP PREVIEW */
    .map-preview-wrap{position:relative;margin-top:12px;border:1px solid rgba(139,126,255,0.22);overflow:hidden;height:200px}
    .map-preview-wrap iframe{width:100%;height:100%;display:block;filter:invert(0.88) hue-rotate(180deg) saturate(0.60) brightness(0.80);border:0}
    .map-preview-placeholder{height:200px;background:rgba(139,126,255,0.04);border:1px dashed rgba(139,126,255,0.20);display:flex;align-items:center;justify-content:center;flex-direction:column;gap:8px;margin-top:12px}
    .map-preview-placeholder i{font-size:2rem;color:rgba(139,126,255,0.25)}
    .map-preview-placeholder span{font-family:var(--font-hud);font-size:0.55rem;color:var(--text-dim);letter-spacing:0.12em;text-transform:uppercase}
    /* BUTTONS */
    .btn-save{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 24px;font-family:var(--font-hud);font-size:0.60rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--prc-violet);border:1px solid var(--prc-violet)!important;box-shadow:0 0 14px rgba(139,126,255,0.22);cursor:pointer!important;transition:all .25s;background:transparent;clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
    .btn-save:hover{background:rgba(139,126,255,0.12);box-shadow:0 0 28px rgba(139,126,255,0.48);color:#fff;transform:translateY(-1px)}
    .btn-save.blue{color:var(--admin-blue);border-color:rgba(139,126,255,0.50)!important;box-shadow:0 0 14px rgba(139,126,255,0.18)}
    .btn-save.blue:hover{background:rgba(139,126,255,0.12);box-shadow:0 0 28px rgba(139,126,255,0.45)}
    .btn-save.green{color:var(--admin-green);border-color:rgba(68,255,136,0.45)!important;box-shadow:0 0 14px rgba(68,255,136,0.18)}
    .btn-save.green:hover{background:rgba(68,255,136,0.12);box-shadow:0 0 28px rgba(68,255,136,0.40)}
    .btn-save.amber{color:var(--creo-amber);border-color:rgba(255,160,48,0.45)!important;box-shadow:0 0 14px rgba(255,160,48,0.18)}
    .btn-save.amber:hover{background:rgba(255,160,48,0.12);box-shadow:0 0 28px rgba(255,160,48,0.40)}
    .btn-save.full{width:100%}
    /* RESET BUTTON */
    .btn-reset{display:inline-flex;align-items:center;gap:5px;font-family:var(--font-hud);font-size:0.46rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-dim);border:1px solid rgba(139,126,255,0.14)!important;padding:3px 9px;cursor:pointer!important;background:transparent;transition:all .20s}
    .btn-reset:hover{color:var(--creo-amber);border-color:rgba(255,160,48,0.35)!important;background:rgba(255,160,48,0.06)}
    /* SECTION LABEL */
    .section-label{font-family:var(--font-hud);font-size:0.64rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-mid);margin:0 0 16px;display:flex;align-items:center;gap:10px;padding-bottom:8px;border-bottom:1px solid rgba(139,126,255,0.14)}
    .section-label::before{content:'//';color:rgba(139,126,255,0.35);font-size:0.70rem}
    /* HOURS TABLE */
    .hours-table{width:100%;border-collapse:collapse;margin-top:4px}
    .hours-table td{padding:8px 0;vertical-align:middle}
    .hours-table td:first-child{font-family:var(--font-hud);font-size:0.58rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-soft);width:160px;padding-right:12px}
    /* LIVE STATUS */
    .live-badge{display:inline-flex;align-items:center;gap:6px;font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--admin-green);padding:4px 10px;border:1px solid rgba(68,255,136,0.30);background:rgba(68,255,136,0.05)}
    .live-dot{width:6px;height:6px;border-radius:50%;background:var(--admin-green);box-shadow:0 0 8px rgba(68,255,136,0.80);animation:pulse 1.8s ease-in-out infinite}
    /* SCROLL */
    ::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:var(--bg-void)}::-webkit-scrollbar-thumb{background:var(--prc-violet);border-radius:2px}
    /* RESPONSIVE */
    @media(max-width:900px){.admin-shell{grid-template-columns:0 1fr}.admin-sidebar-slot{display:none}.admin-main{padding:18px 16px 48px}body{cursor:auto}button{cursor:pointer}.cursor-dot,.cursor-ring{display:none}}
    @media(max-width:768px){.settings-grid{grid-template-columns:1fr}.field-row{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="cursor-dot" id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<div class="admin-shell" id="adminShell">
  <div class="admin-sidebar-slot" id="sidebarSlot"></div>

  <!-- TOPBAR -->
  <header class="admin-topbar">
    <div class="topbar-breadcrumb">PRC Admin <i class="fi fi-rr-angle-right" style="font-size:.55rem"></i> <span>Contact Settings</span></div>
    <div class="topbar-right">
      <button class="topbar-icon-btn" onclick="location.reload()" title="Refresh"><i class="fi fi-rr-refresh"></i></button>
      <a href="contact.php" target="_blank" class="topbar-public-btn"><i class="fi fi-rr-eye"></i> View Contact Page</a>
      <div class="topbar-date"><span id="topbar-date-display"></span></div>
    </div>
  </header>

  <!-- MAIN -->
  <main class="admin-main">

    <div class="page-header">
      <div>
        <div class="page-eyebrow"><span class="dot-live"></span> Content Management // Contact Page</div>
        <h1 class="page-title"><span class="accent">Contact</span> Settings</h1>
      </div>
      <div class="live-badge"><span class="live-dot"></span> Changes go live instantly</div>
    </div>

    <?php if ($flash): ?>
    <div class="flash-wrap">
      <div class="flash-inner <?= $flash['type'] ?>">
        <i class="fi fi-<?= $flash['type']==='success' ? 'rr-check' : 'rr-cross' ?>"></i>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ══ SECTION 1: Hero Description ══ -->
    <div class="section-label" style="margin-bottom:16px">Hero Section</div>
    <div class="settings-grid full" style="margin-bottom:24px">
      <div class="panel-card blue-card">
        <div class="panel-hdr blue">
          <div class="panel-hdr-left"><i class="fi fi-rr-text"></i><div class="panel-hdr-text"><h3>Page Hero Description</h3><p>Subtitle shown under "Contact Us" on the public page</p></div></div>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="reset_field"/>
            <input type="hidden" name="field_key" value="hero_desc"/>
            <button type="submit" class="btn-reset" title="Reset to original"><i class="fi fi-rr-refresh"></i> Reset</button>
          </form>
        </div>
        <div class="panel-body">
          <form method="POST" action="admin-contacts.php">
            <input type="hidden" name="action" value="save_hero"/>
            <div class="field">
              <div class="field-label"><span>Description Text <span class="req">*</span></span></div>
              <textarea class="field-textarea" name="hero_desc" rows="3" required><?= cv($cfg,'hero_desc') ?></textarea>
              <div class="field-hint">Appears directly below the "Contact Us" heading in the page hero.</div>
            </div>
            <button type="submit" class="btn-save blue full"><i class="fi fi-rr-check"></i> Save Hero Description</button>
          </form>
        </div>
      </div>
    </div>

    <!-- ══ SECTION 2: Contact Details + Social ══ -->
    <div class="section-label" style="margin-bottom:16px">Contact Information</div>
    <div class="settings-grid" style="margin-bottom:24px">

      <!-- Contact Details -->
      <div class="panel-card green-card">
        <div class="panel-hdr green">
          <div class="panel-hdr-left"><i class="fi fi-rr-address-book"></i><div class="panel-hdr-text"><h3>Direct Contact Details</h3><p>Email, phone, and office address</p></div></div>
        </div>
        <div class="panel-body">
          <form method="POST" action="admin-contacts.php">
            <input type="hidden" name="action" value="save_contact"/>
            <div class="field">
              <div class="field-label">
                <span>Email Address <span class="req">*</span></span>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="reset_field"/><input type="hidden" name="field_key" value="email"/><button type="submit" class="btn-reset"><i class="fi fi-rr-refresh"></i> Reset</button></form>
              </div>
              <input class="field-input" type="email" name="email" value="<?= cv($cfg,'email') ?>" placeholder="contact@example.com" required/>
            </div>
            <div class="field">
              <div class="field-label">
                <span>Phone / Viber <span class="req">*</span></span>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="reset_field"/><input type="hidden" name="field_key" value="phone"/><button type="submit" class="btn-reset"><i class="fi fi-rr-refresh"></i> Reset</button></form>
              </div>
              <input class="field-input" type="text" name="phone" value="<?= cv($cfg,'phone') ?>" placeholder="+63 9XX XXX XXXX" required/>
            </div>
            <div class="field">
              <div class="field-label">
                <span>Address Line 1</span>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="reset_field"/><input type="hidden" name="field_key" value="address_line1"/><button type="submit" class="btn-reset"><i class="fi fi-rr-refresh"></i> Reset</button></form>
              </div>
              <input class="field-input" type="text" name="address_line1" value="<?= cv($cfg,'address_line1') ?>" placeholder="Street, district,"/>
            </div>
            <div class="field">
              <div class="field-label">
                <span>Address Line 2</span>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="reset_field"/><input type="hidden" name="field_key" value="address_line2"/><button type="submit" class="btn-reset"><i class="fi fi-rr-refresh"></i> Reset</button></form>
              </div>
              <input class="field-input" type="text" name="address_line2" value="<?= cv($cfg,'address_line2') ?>" placeholder="City, Province ZIP, Country"/>
            </div>
            <div class="field">
              <div class="field-label">
                <span>Address Note / Sub-label</span>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="reset_field"/><input type="hidden" name="field_key" value="address_note"/><button type="submit" class="btn-reset"><i class="fi fi-rr-refresh"></i> Reset</button></form>
              </div>
              <input class="field-input" type="text" name="address_note" value="<?= cv($cfg,'address_note') ?>" placeholder="e.g. Creotec Philippines Inc. — Primary organizer"/>
              <div class="field-hint">Small grey text shown below the address.</div>
            </div>
            <button type="submit" class="btn-save green full"><i class="fi fi-rr-check"></i> Save Contact Details</button>
          </form>
        </div>
      </div>

      <!-- Social + Hours -->
      <div style="display:flex;flex-direction:column;gap:20px">

        <!-- Facebook -->
        <div class="panel-card">
          <div class="panel-hdr">
            <div class="panel-hdr-left"><i class="fi fi-rr-share"></i><div class="panel-hdr-text"><h3>Social Links</h3><p>Facebook page URL and label</p></div></div>
          </div>
          <div class="panel-body">
            <form method="POST" action="admin-contacts.php">
              <input type="hidden" name="action" value="save_social"/>
              <div class="field">
                <div class="field-label">
                  <span>Facebook Page URL</span>
                  <form method="POST" style="display:inline"><input type="hidden" name="action" value="reset_field"/><input type="hidden" name="field_key" value="facebook_url"/><button type="submit" class="btn-reset"><i class="fi fi-rr-refresh"></i> Reset</button></form>
                </div>
                <input class="field-input" type="url" name="facebook_url" value="<?= cv($cfg,'facebook_url') ?>" placeholder="https://www.facebook.com/..."/>
              </div>
              <div class="field">
                <div class="field-label">
                  <span>Facebook Display Label</span>
                  <form method="POST" style="display:inline"><input type="hidden" name="action" value="reset_field"/><input type="hidden" name="field_key" value="facebook_label"/><button type="submit" class="btn-reset"><i class="fi fi-rr-refresh"></i> Reset</button></form>
                </div>
                <input class="field-input" type="text" name="facebook_label" value="<?= cv($cfg,'facebook_label') ?>" placeholder="Philippine Robotics Cup"/>
                <div class="field-hint">Text shown next to the Facebook icon button.</div>
              </div>
              <button type="submit" class="btn-save full"><i class="fi fi-rr-check"></i> Save Social Links</button>
            </form>
          </div>
        </div>

        <!-- Response Hours -->
        <div class="panel-card amber-card">
          <div class="panel-hdr amber">
            <div class="panel-hdr-left"><i class="fi fi-rr-clock"></i><div class="panel-hdr-text"><h3>Response Hours</h3><p>Operating schedule shown on contact page</p></div></div>
          </div>
          <div class="panel-body">
            <form method="POST" action="admin-contacts.php">
              <input type="hidden" name="action" value="save_hours"/>
              <table class="hours-table">
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                      <span>Mon – Fri</span>
                      <form method="POST" style="display:inline"><input type="hidden" name="action" value="reset_field"/><input type="hidden" name="field_key" value="hours_weekday"/><button type="submit" class="btn-reset"><i class="fi fi-rr-refresh"></i></button></form>
                    </div>
                  </td>
                  <td><input class="field-input" type="text" name="hours_weekday" value="<?= cv($cfg,'hours_weekday') ?>" placeholder="9:00 AM – 5:00 PM"/></td>
                </tr>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                      <span>Saturday</span>
                      <form method="POST" style="display:inline"><input type="hidden" name="action" value="reset_field"/><input type="hidden" name="field_key" value="hours_saturday"/><button type="submit" class="btn-reset"><i class="fi fi-rr-refresh"></i></button></form>
                    </div>
                  </td>
                  <td><input class="field-input" type="text" name="hours_saturday" value="<?= cv($cfg,'hours_saturday') ?>" placeholder="10:00 AM – 2:00 PM"/></td>
                </tr>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                      <span>Sunday / Holidays</span>
                      <form method="POST" style="display:inline"><input type="hidden" name="action" value="reset_field"/><input type="hidden" name="field_key" value="hours_sunday"/><button type="submit" class="btn-reset"><i class="fi fi-rr-refresh"></i></button></form>
                    </div>
                  </td>
                  <td><input class="field-input" type="text" name="hours_sunday" value="<?= cv($cfg,'hours_sunday') ?>" placeholder="Closed"/></td>
                </tr>
              </table>
              <div class="field-hint" style="margin-top:10px">Use "Closed" for days with no office hours. Times show in cyan when not "Closed".</div>
              <button type="submit" class="btn-save amber full" style="margin-top:16px"><i class="fi fi-rr-check"></i> Save Hours</button>
            </form>
          </div>
        </div>

      </div><!-- /right column -->
    </div>

    <!-- ══ SECTION 3: Map ══ -->
    <div class="section-label" style="margin-bottom:16px">Map &amp; Location</div>
    <div class="settings-grid full">
      <div class="panel-card sky-card">
        <div class="panel-hdr blue">
          <div class="panel-hdr-left"><i class="fi fi-rr-map-marker"></i><div class="panel-hdr-text"><h3>Map Settings</h3><p>Google Maps embed and directions link</p></div></div>
        </div>
        <div class="panel-body">
          <form method="POST" action="admin-contacts.php" id="mapForm">
            <input type="hidden" name="action" value="save_map"/>
            <div class="field-row">
              <div class="field">
                <div class="field-label">
                  <span>Venue Display Name</span>
                  <form method="POST" style="display:inline"><input type="hidden" name="action" value="reset_field"/><input type="hidden" name="field_key" value="maps_label"/><button type="submit" class="btn-reset"><i class="fi fi-rr-refresh"></i> Reset</button></form>
                </div>
                <input class="field-input" type="text" name="maps_label" value="<?= cv($cfg,'maps_label') ?>" placeholder="Venue / building name"/>
                <div class="field-hint">Large text in the map address bar overlay.</div>
              </div>
              <div class="field">
                <div class="field-label">
                  <span>Venue Sub-label / Address</span>
                  <form method="POST" style="display:inline"><input type="hidden" name="action" value="reset_field"/><input type="hidden" name="field_key" value="maps_sublabel"/><button type="submit" class="btn-reset"><i class="fi fi-rr-refresh"></i> Reset</button></form>
                </div>
                <input class="field-input" type="text" name="maps_sublabel" value="<?= cv($cfg,'maps_sublabel') ?>" placeholder="Street address shown below name"/>
              </div>
            </div>
            <div class="field">
              <div class="field-label">
                <span>Get Directions URL</span>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="reset_field"/><input type="hidden" name="field_key" value="maps_directions_url"/><button type="submit" class="btn-reset"><i class="fi fi-rr-refresh"></i> Reset</button></form>
              </div>
              <input class="field-input" type="url" name="maps_directions_url" value="<?= cv($cfg,'maps_directions_url') ?>" placeholder="https://maps.google.com/?q=..."/>
              <div class="field-hint">URL opened when the "Get Directions" button is clicked. Use Google Maps search URL format.</div>
            </div>
            <div class="field">
              <div class="field-label">
                <span>Google Maps Embed URL / src</span>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="reset_field"/><input type="hidden" name="field_key" value="maps_embed_url"/><button type="submit" class="btn-reset"><i class="fi fi-rr-refresh"></i> Reset</button></form>
              </div>
              <textarea class="field-textarea" name="maps_embed_url" rows="3" id="embedUrlInput" oninput="previewMap()"><?= cv($cfg,'maps_embed_url') ?></textarea>
              <div class="field-hint">Paste the <code style="background:rgba(139,126,255,0.12);padding:1px 5px;font-size:.82em">src</code> attribute value from a Google Maps embed &lt;iframe&gt;. To get it: Google Maps → Share → Embed a map → copy the <strong>src="..."</strong> value only.</div>
            </div>

            <!-- MAP PREVIEW -->
            <div style="margin-bottom:14px">
              <div class="preview-label">Live Preview</div>
              <?php if (!empty($cfg['maps_embed_url'])): ?>
              <div class="map-preview-wrap" id="mapPreviewWrap">
                <iframe src="<?= cv($cfg,'maps_embed_url') ?>" id="mapPreviewIframe" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map preview"></iframe>
              </div>
              <?php else: ?>
              <div class="map-preview-placeholder" id="mapPreviewPlaceholder">
                <i class="fi fi-rr-map"></i>
                <span>Paste an embed URL above to preview</span>
              </div>
              <div class="map-preview-wrap" id="mapPreviewWrap" style="display:none">
                <iframe src="" id="mapPreviewIframe" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map preview"></iframe>
              </div>
              <?php endif; ?>
            </div>

            <button type="submit" class="btn-save blue full"><i class="fi fi-rr-check"></i> Save Map Settings</button>
          </form>
        </div>
      </div>
    </div>

    <!-- ══ CURRENT VALUES REFERENCE ══ -->
    <div class="section-label" style="margin-top:28px;margin-bottom:16px">Current Live Values</div>
    <div class="panel-card" style="margin-bottom:0">
      <div class="panel-hdr">
        <div class="panel-hdr-left"><i class="fi fi-rr-database"></i><div class="panel-hdr-text"><h3>All Settings in DB</h3><p>Read-only reference — what's currently stored in prc_contact_settings</p></div></div>
      </div>
      <div class="panel-body">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr>
              <th style="font-family:var(--font-hud);font-size:0.48rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-soft);padding:8px 14px;background:rgba(139,126,255,0.07);border-bottom:1px solid rgba(139,126,255,0.16);text-align:left;width:200px">Key</th>
              <th style="font-family:var(--font-hud);font-size:0.48rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-soft);padding:8px 14px;background:rgba(139,126,255,0.07);border-bottom:1px solid rgba(139,126,255,0.16);text-align:left">Value</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cfg as $k => $v): ?>
            <tr style="border-bottom:1px solid rgba(139,126,255,0.07)">
              <td style="padding:9px 14px;font-family:var(--font-hud);font-size:0.58rem;color:var(--prc-violet);vertical-align:top"><?= htmlspecialchars($k) ?></td>
              <td style="padding:9px 14px;font-size:0.84rem;color:var(--text-mid);word-break:break-all;vertical-align:top"><?= htmlspecialchars(mb_strimwidth($v ?? '', 0, 120, '…')) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($cfg)): ?>
            <tr><td colspan="2" style="padding:28px;text-align:center;font-family:var(--font-hud);font-size:0.58rem;color:var(--text-dim);letter-spacing:0.10em">No settings found. Run prc_contact.sql to seed the table.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<!-- ══ SIDEBAR ══ -->
<script>
(function(){
  var slot=document.getElementById('sidebarSlot');
  if(!slot)return;
  fetch('admin-sidebar.html').then(function(r){return r.text();}).then(function(html){
    slot.innerHTML=html;
    slot.querySelectorAll('script').forEach(function(old){var s=document.createElement('script');s.textContent=old.textContent;document.body.appendChild(s);});
  }).catch(function(){
    slot.innerHTML='<aside style="position:fixed;top:0;left:0;height:100vh;width:248px;background:#04031A;border-right:1px solid rgba(139,126,255,0.18);display:flex;align-items:center;justify-content:center;font-family:Orbitron,monospace;font-size:0.55rem;color:rgba(139,126,255,0.40);letter-spacing:0.12em;text-align:center;padding:20px;">SIDEBAR<br/>COMPONENT</aside>';
  });
})();
document.addEventListener('prc-sidebar-toggle',function(e){document.getElementById('adminShell').classList.toggle('sb-collapsed',e.detail.collapsed);});
(function(){if(localStorage.getItem('prc_sidebar_collapsed')==='1')document.getElementById('adminShell').classList.add('sb-collapsed');})();
</script>

<script>
// ── TOPBAR DATE ──
(function(){
  var el=document.getElementById('topbar-date-display');
  function upd(){var d=new Date(),M=['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'],h=d.getHours(),m=d.getMinutes(),ap=h>=12?'PM':'AM';h=h%12||12;el.innerHTML=M[d.getMonth()]+' '+String(d.getDate()).padStart(2,'0')+', '+d.getFullYear()+'&nbsp;<span>'+String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+' '+ap+'</span>';}
  upd();setInterval(upd,30000);
})();

// ── CURSOR ──
(function(){
  var dot=document.getElementById('cursorDot'),ring=document.getElementById('cursorRing');
  if(!dot||!ring)return;
  var mx=0,my=0,rx=0,ry=0;
  document.addEventListener('mousemove',function(e){mx=e.clientX;my=e.clientY;dot.style.left=mx+'px';dot.style.top=my+'px';});
  (function l(){rx+=(mx-rx)*.12;ry+=(my-ry)*.12;ring.style.left=rx+'px';ring.style.top=ry+'px';requestAnimationFrame(l);})();
  document.querySelectorAll('a,button').forEach(function(el){
    el.addEventListener('mouseenter',function(){ring.classList.add('hovered');});
    el.addEventListener('mouseleave',function(){ring.classList.remove('hovered');});
  });
})();

// ── MAP PREVIEW ──
function previewMap() {
  var url = document.getElementById('embedUrlInput').value.trim();
  var wrap = document.getElementById('mapPreviewWrap');
  var placeholder = document.getElementById('mapPreviewPlaceholder');
  var iframe = document.getElementById('mapPreviewIframe');
  if (url.startsWith('http')) {
    iframe.src = url;
    if (wrap) wrap.style.display = '';
    if (placeholder) placeholder.style.display = 'none';
  } else {
    if (wrap) wrap.style.display = 'none';
    if (placeholder) placeholder.style.display = '';
  }
}
</script>
</body>
</html>