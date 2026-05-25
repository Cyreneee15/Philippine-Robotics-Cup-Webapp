<?php
// PRC-WebApp/admin-faq.php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);

$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'prc_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die('<p style="color:#ff6b6b;padding:40px;font-family:monospace;">DB error: ' . htmlspecialchars($conn->connect_error) . '</p>');

/* ══════════════════════════════════════════════════
   AUTO-CREATE TABLES (run once, safe to re-run)
══════════════════════════════════════════════════ */
$conn->query("
  CREATE TABLE IF NOT EXISTS `prc_faq_categories` (
    `cat_id`    INT(11)      NOT NULL AUTO_INCREMENT,
    `cat_slug`  VARCHAR(60)  NOT NULL,
    `cat_label` VARCHAR(150) NOT NULL,
    `cat_sort`  INT(11)      NOT NULL DEFAULT 0,
    `is_active` TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (`cat_id`),
    UNIQUE KEY `uq_cat_slug` (`cat_slug`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$conn->query("
  CREATE TABLE IF NOT EXISTS `prc_faq_items` (
    `faq_id`     INT(11)      NOT NULL AUTO_INCREMENT,
    `cat_id`     INT(11)      NOT NULL,
    `faq_question` TEXT       NOT NULL,
    `faq_answer`   LONGTEXT   NOT NULL,
    `faq_sort`   INT(11)      NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`faq_id`),
    KEY `idx_cat` (`cat_id`),
    CONSTRAINT `fk_faq_cat` FOREIGN KEY (`cat_id`) REFERENCES `prc_faq_categories` (`cat_id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* Seed default categories if table is empty */
$check = $conn->query("SELECT COUNT(*) AS n FROM prc_faq_categories");
if ($check && $check->fetch_assoc()['n'] == 0) {
    $conn->query("INSERT INTO prc_faq_categories (cat_slug,cat_label,cat_sort) VALUES
        ('registration','Registration',0),
        ('categories','Categories',1),
        ('payment','Payment',2),
        ('event','Event Details',3),
        ('shop','Shop & Materials',4)
    ");
}

/* ══════════════════════════════════════════════════
   FLASH HELPERS
══════════════════════════════════════════════════ */
function set_flash($t,$m){ $_SESSION['faq_flash']=['type'=>$t,'msg'=>$m]; }
function get_flash(){ if(!empty($_SESSION['faq_flash'])){ $f=$_SESSION['faq_flash']; unset($_SESSION['faq_flash']); return $f; } return null; }

/* ══════════════════════════════════════════════════
   POST HANDLERS
══════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── CATEGORY ACTIONS ── */
    if ($action === 'add_cat') {
        $slug  = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['cat_slug'] ?? '')));
        $label = trim($_POST['cat_label'] ?? '');
        $sort  = (int)($_POST['cat_sort'] ?? 0);
        if ($slug === '' || $label === '') { set_flash('error','Slug and label are required.'); }
        else {
            $s = $conn->prepare("INSERT INTO prc_faq_categories (cat_slug,cat_label,cat_sort) VALUES (?,?,?)");
            $s->bind_param('ssi',$slug,$label,$sort);
            $s->execute() ? set_flash('success','Category "'.$label.'" created.') : set_flash('error','Slug already exists — choose a different one.');
            $s->close();
        }
    }

    if ($action === 'edit_cat') {
        $cid   = (int)($_POST['cat_id']    ?? 0);
        $slug  = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['cat_slug'] ?? '')));
        $label = trim($_POST['cat_label']  ?? '');
        $sort  = (int)($_POST['cat_sort']  ?? 0);
        $act   = (int)($_POST['cat_active']?? 1);
        if ($cid && $slug !== '' && $label !== '') {
            $s = $conn->prepare("UPDATE prc_faq_categories SET cat_slug=?,cat_label=?,cat_sort=?,is_active=? WHERE cat_id=?");
            $s->bind_param('ssiii',$slug,$label,$sort,$act,$cid);
            $s->execute() ? set_flash('success','Category updated.') : set_flash('error','Could not update — slug may already exist.');
            $s->close();
        } else set_flash('error','Invalid category data.');
    }

    if ($action === 'delete_cat') {
        $cid = (int)($_POST['cat_id'] ?? 0);
        if ($cid) {
            $conn->query("DELETE FROM prc_faq_categories WHERE cat_id=$cid");
            set_flash('success','Category and all its questions deleted.');
        }
    }

    /* ── FAQ ITEM ACTIONS ── */
    if ($action === 'add_faq') {
        $cid  = (int)($_POST['cat_id']     ?? 0);
        $q    = trim($_POST['faq_question'] ?? '');
        $a    = trim($_POST['faq_answer']   ?? '');
        $sort = (int)($_POST['faq_sort']    ?? 0);
        if (!$cid || $q === '' || $a === '') { set_flash('error','Category, question, and answer are all required.'); }
        else {
            $s = $conn->prepare("INSERT INTO prc_faq_items (cat_id,faq_question,faq_answer,faq_sort) VALUES (?,?,?,?)");
            $s->bind_param('issi',$cid,$q,$a,$sort);
            $s->execute() ? set_flash('success','FAQ item added.') : set_flash('error','Could not add FAQ item.');
            $s->close();
        }
    }

    if ($action === 'edit_faq') {
        $fid  = (int)($_POST['faq_id']      ?? 0);
        $cid  = (int)($_POST['cat_id']      ?? 0);
        $q    = trim($_POST['faq_question'] ?? '');
        $a    = trim($_POST['faq_answer']   ?? '');
        $sort = (int)($_POST['faq_sort']    ?? 0);
        $act  = (int)($_POST['faq_active']  ?? 1);
        if ($fid && $cid && $q !== '' && $a !== '') {
            $s = $conn->prepare("UPDATE prc_faq_items SET cat_id=?,faq_question=?,faq_answer=?,faq_sort=?,is_active=? WHERE faq_id=?");
            $s->bind_param('issiii',$cid,$q,$a,$sort,$act,$fid);
            $s->execute() ? set_flash('success','FAQ item updated.') : set_flash('error','Could not update FAQ item.');
            $s->close();
        } else set_flash('error','Invalid FAQ data.');
    }

    if ($action === 'delete_faq') {
        $fid = (int)($_POST['faq_id'] ?? 0);
        if ($fid) {
            $conn->query("DELETE FROM prc_faq_items WHERE faq_id=$fid");
            set_flash('success','FAQ item deleted.');
        }
    }

    if ($action === 'toggle_faq') {
        $fid = (int)($_POST['faq_id'] ?? 0);
        $val = (int)($_POST['is_active'] ?? 0);
        if ($fid) {
            $conn->query("UPDATE prc_faq_items SET is_active=$val WHERE faq_id=$fid");
            set_flash('success', $val ? 'FAQ item published.' : 'FAQ item hidden.');
        }
    }

    header('Location: admin-faq.php'); exit;
}

/* ══════════════════════════════════════════════════
   READ DATA
══════════════════════════════════════════════════ */
$categories = [];
$cr = $conn->query("SELECT * FROM prc_faq_categories ORDER BY cat_sort ASC, cat_label ASC");
if ($cr) while($r=$cr->fetch_assoc()) $categories[] = $r;

$faqs_by_cat = [];
$fr = $conn->query("SELECT f.*, c.cat_label FROM prc_faq_items f JOIN prc_faq_categories c ON c.cat_id=f.cat_id ORDER BY f.cat_id, f.faq_sort ASC, f.faq_id ASC");
if ($fr) while($r=$fr->fetch_assoc()) $faqs_by_cat[$r['cat_id']][] = $r;

$total_faqs    = array_sum(array_map('count', $faqs_by_cat));
$active_faqs   = 0;
foreach ($faqs_by_cat as $items) foreach ($items as $i) if ($i['is_active']) $active_faqs++;
$flash = get_flash();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>FAQ Manager — PRC Admin</title>
  <link rel="icon" type="image/png" href="assets/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css"/>
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css"/>
  <style>
    /* ═══════════════════════════════════════════
       SHARED ADMIN SHELL VARS
    ═══════════════════════════════════════════ */
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
      --creo-sky:      #44D9FF;
      --admin-red:     #FF4D6A;
      --admin-green:   #44FF88;
      --admin-blue:    #44D9FF;
      --border-neon:   rgba(139,126,255,0.18);
      --text-high:     #F2EEFF;
      --text-mid:      #C8C0F0;
      --text-soft:     #9A90CC;
      --text-dim:      #6058A0;
      --font-hud:      'Orbitron',monospace;
      --font-body:     'Exo 2',sans-serif;
      --radius:        3px;
      --tr:            all 0.25s ease;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    html{scroll-behavior:smooth;}
    body{font-family:var(--font-body);background:var(--bg-void);color:var(--text-high);overflow-x:hidden;line-height:1.6;min-height:100vh;cursor:none;}
    img{max-width:100%;display:block;}a{text-decoration:none;color:inherit;}ul{list-style:none;}
    button{font-family:inherit;border:none;background:none;cursor:none;}
    textarea{font-family:var(--font-body);}

    body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(rgba(139,126,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(139,126,255,0.03) 1px,transparent 1px);background-size:44px 44px;}
    body::after{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;background:repeating-linear-gradient(to bottom,transparent,transparent 2px,rgba(0,0,0,0.025) 2px,rgba(0,0,0,0.025) 4px);}

    /* CURSOR */
    .cursor-dot{position:fixed;width:8px;height:8px;border-radius:50%;background:var(--prc-violet);pointer-events:none;z-index:99999;transform:translate(-50%,-50%);box-shadow:0 0 18px rgba(139,126,255,.80);transition:transform .1s;}
    .cursor-ring{position:fixed;width:36px;height:36px;border-radius:50%;border:1px solid rgba(139,126,255,.60);pointer-events:none;z-index:99998;transform:translate(-50%,-50%);transition:width .25s,height .25s;}
    .cursor-ring.hovered{width:52px;height:52px;border-color:var(--creo-amber);}

    /* LAYOUT */
    .admin-shell{display:grid;grid-template-columns:var(--sb-width) 1fr;grid-template-rows:var(--topbar-h) 1fr;min-height:100vh;position:relative;z-index:1;transition:grid-template-columns 0.30s cubic-bezier(0.77,0,0.175,1);}
    .admin-shell.sb-collapsed{grid-template-columns:var(--sb-collapsed) 1fr;}
    .admin-sidebar-slot{grid-row:1/-1;grid-column:1;}

    /* TOPBAR */
    .admin-topbar{grid-column:2;grid-row:1;height:var(--topbar-h);background:rgba(3,2,13,0.92);backdrop-filter:blur(20px);border-bottom:1px solid var(--border-neon);display:flex;align-items:center;padding:0 28px;gap:16px;position:sticky;top:0;z-index:800;box-shadow:0 1px 30px rgba(139,126,255,0.07);}
    .topbar-breadcrumb{display:flex;align-items:center;gap:8px;font-family:var(--font-hud);font-size:0.60rem;color:var(--text-dim);letter-spacing:0.10em;text-transform:uppercase;}
    .topbar-breadcrumb span{color:var(--prc-violet);}
    .topbar-right{margin-left:auto;display:flex;align-items:center;gap:10px;}
    .topbar-icon-btn{width:36px;height:36px;background:rgba(139,126,255,0.05);border:1px solid var(--border-neon)!important;border-radius:var(--radius);display:flex;align-items:center;justify-content:center;color:var(--text-soft);transition:var(--tr);cursor:none;}
    .topbar-icon-btn i{font-size:0.90rem;}
    .topbar-icon-btn:hover{background:rgba(139,126,255,0.14);color:var(--prc-violet);border-color:var(--prc-violet)!important;}
    .topbar-date{font-family:var(--font-hud);font-size:0.52rem;color:var(--text-dim);letter-spacing:0.10em;white-space:nowrap;}
    .topbar-date span{color:var(--creo-volt);}
    .topbar-public-btn{display:inline-flex;align-items:center;gap:7px;font-family:var(--font-hud);font-size:0.56rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--prc-violet);padding:7px 14px;border:1px solid rgba(139,126,255,0.35)!important;background:rgba(139,126,255,0.06);clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);transition:var(--tr);}
    .topbar-public-btn:hover{background:rgba(139,126,255,0.16);color:#fff;}

    /* MAIN */
    .admin-main{grid-column:2;grid-row:2;padding:28px 28px 60px;overflow-y:auto;min-height:calc(100vh - var(--topbar-h));}

    /* PAGE HEADER */
    .page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:24px;}
    .page-eyebrow{font-family:var(--font-hud);font-size:0.52rem;letter-spacing:0.20em;text-transform:uppercase;color:var(--prc-violet);margin-bottom:6px;display:flex;align-items:center;gap:8px;}
    .dot-live{width:7px;height:7px;background:var(--prc-violet);border-radius:50%;box-shadow:0 0 8px rgba(139,126,255,0.90);animation:neonPulse 1s ease-in-out infinite;}
    .page-title{font-family:var(--font-hud);font-size:clamp(1.3rem,3vw,2rem);font-weight:900;color:#fff;letter-spacing:-0.01em;}
    .page-title .accent{color:var(--prc-violet);text-shadow:0 0 22px rgba(139,126,255,0.70);}
    .page-stats{display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
    .stat-chip{background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.20);padding:10px 18px;text-align:center;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);}
    .stat-chip-num{font-family:var(--font-hud);font-size:1.4rem;font-weight:800;color:var(--prc-violet);display:block;line-height:1;text-shadow:0 0 14px rgba(139,126,255,0.70);}
    .stat-chip-lbl{font-family:var(--font-hud);font-size:0.48rem;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.12em;display:block;margin-top:3px;}
    .stat-chip.green .stat-chip-num{color:var(--admin-green);text-shadow:0 0 14px rgba(68,255,136,0.60);}
    .stat-chip.green{background:rgba(68,255,136,0.05);border-color:rgba(68,255,136,0.18);}

    /* FLASH */
    .flash-wrap{margin-bottom:20px;animation:slideDown 0.35s ease;}
    .flash-inner{padding:13px 20px;display:flex;align-items:center;gap:10px;font-family:var(--font-hud);font-size:0.63rem;font-weight:600;letter-spacing:0.08em;border:1px solid;}
    .flash-inner.success{color:var(--admin-green);border-color:rgba(68,255,136,0.35);background:rgba(68,255,136,0.06);}
    .flash-inner.error{color:var(--admin-red);border-color:rgba(255,77,106,0.35);background:rgba(255,77,106,0.06);}

    /* ══ LAYOUT ══ */
    .faq-layout{display:grid;grid-template-columns:310px 1fr;gap:24px;align-items:start;}

    /* SIDEBAR / PANEL CARDS */
    .faq-sidebar{display:flex;flex-direction:column;gap:18px;position:sticky;top:calc(var(--topbar-h) + 20px);}
    .panel-card{background:var(--bg-card);border:1px solid rgba(139,126,255,0.18);position:relative;}
    .panel-card::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent);}
    .panel-card.amber-card{border-color:rgba(255,160,48,0.22);}
    .panel-card.amber-card::before{background:linear-gradient(90deg,transparent,var(--creo-amber),transparent);}
    .panel-hdr{background:rgba(139,126,255,0.05);padding:14px 18px;border-bottom:1px solid rgba(139,126,255,0.12);display:flex;align-items:center;gap:10px;}
    .panel-hdr.amber{background:rgba(255,160,48,0.05);border-color:rgba(255,160,48,0.15);}
    .panel-hdr i{color:var(--prc-violet);font-size:0.95rem;}
    .panel-hdr.amber i{color:var(--creo-amber);}
    .panel-hdr-text h3{font-family:var(--font-hud);font-size:0.70rem;font-weight:700;letter-spacing:0.06em;color:var(--text-high);}
    .panel-hdr-text p{font-size:0.76rem;color:var(--text-soft);margin-top:2px;}
    .panel-body{padding:18px 20px;}

    /* FORM FIELDS */
    .field{margin-bottom:14px;}
    .field:last-of-type{margin-bottom:0;}
    .field-label{font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-soft);display:flex;align-items:center;gap:6px;margin-bottom:7px;}
    .field-label .req{color:var(--admin-red);}
    .field-input,.field-select,.field-textarea{width:100%;padding:10px 13px;background:rgba(139,126,255,0.04);border:1px solid rgba(139,126,255,0.22);color:var(--text-high);font-family:var(--font-body);font-size:0.88rem;outline:none;transition:border-color 0.22s,box-shadow 0.22s;appearance:none;}
    .field-input::placeholder,.field-textarea::placeholder{color:var(--text-dim);}
    .field-input:focus,.field-select:focus,.field-textarea:focus{border-color:var(--prc-violet);box-shadow:0 0 0 2px rgba(139,126,255,0.14);}
    .field-textarea{resize:vertical;min-height:90px;}
    .field-hint{font-size:0.74rem;color:var(--text-dim);margin-top:5px;}
    .field-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;}

    /* TOGGLE */
    .field-toggle{display:flex;align-items:center;gap:10px;cursor:pointer;}
    .toggle-track{width:38px;height:20px;background:rgba(139,126,255,0.10);border:1px solid rgba(139,126,255,0.28);border-radius:20px;position:relative;transition:background 0.2s,border-color 0.2s;flex-shrink:0;}
    .toggle-track::after{content:'';position:absolute;top:3px;left:3px;width:12px;height:12px;border-radius:50%;background:var(--text-dim);transition:transform 0.2s,background 0.2s;}
    input[type="checkbox"]:checked + .toggle-track{background:rgba(139,126,255,0.22);border-color:var(--prc-violet);}
    input[type="checkbox"]:checked + .toggle-track::after{transform:translateX(18px);background:var(--prc-violet);}
    input[type="checkbox"]{display:none;}
    .toggle-label{font-family:var(--font-hud);font-size:0.54rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-soft);}

    /* BUTTONS */
    .btn-primary{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:11px;font-family:var(--font-hud);font-size:0.60rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--prc-violet);border:1px solid var(--prc-violet)!important;box-shadow:0 0 14px rgba(139,126,255,0.22);cursor:pointer!important;transition:all 0.25s;background:transparent;clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);}
    .btn-primary:hover{background:rgba(139,126,255,0.12);box-shadow:0 0 28px rgba(139,126,255,0.48);color:#fff;transform:translateY(-1px);}
    .btn-amber{color:var(--creo-amber);border-color:rgba(255,160,48,0.45)!important;box-shadow:0 0 14px rgba(255,160,48,0.16);}
    .btn-amber:hover{background:rgba(255,160,48,0.10);box-shadow:0 0 28px rgba(255,160,48,0.40);}
    .btn-sm{padding:7px 14px;font-size:0.52rem;width:auto;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);}

    /* RIGHT PANEL */
    .faq-right{display:flex;flex-direction:column;gap:20px;}
    .faq-right-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:4px;}
    .faq-right-title{font-family:var(--font-hud);font-size:0.78rem;font-weight:700;letter-spacing:0.06em;color:var(--text-high);display:flex;align-items:center;gap:10px;}
    .count-pill{font-family:var(--font-hud);font-size:0.48rem;font-weight:700;padding:3px 10px;border:1px solid rgba(139,126,255,0.30);background:rgba(139,126,255,0.07);color:var(--prc-violet);letter-spacing:0.10em;}

    /* CATEGORY GROUP */
    .cat-group{border:1px solid rgba(139,126,255,0.15);overflow:hidden;position:relative;}
    .cat-group::before{content:'';position:absolute;top:0;left:0;bottom:0;width:2px;background:var(--prc-violet);opacity:0.45;}
    .cat-group-header{display:flex;align-items:center;justify-content:space-between;padding:13px 18px 13px 22px;background:rgba(139,126,255,0.04);border-bottom:1px solid rgba(139,126,255,0.12);flex-wrap:wrap;gap:10px;}
    .cat-group-title{font-family:var(--font-hud);font-size:0.76rem;font-weight:800;color:var(--text-high);letter-spacing:0.04em;display:flex;align-items:center;gap:10px;}
    .cat-group-title i{color:var(--prc-violet);font-size:0.82rem;}
    .cat-slug-badge{font-family:var(--font-hud);font-size:0.44rem;letter-spacing:0.12em;text-transform:uppercase;padding:2px 8px;border:1px solid rgba(139,126,255,0.22);background:rgba(139,126,255,0.06);color:var(--text-dim);}
    .cat-inactive-badge{font-family:var(--font-hud);font-size:0.44rem;padding:2px 8px;border:1px solid rgba(255,77,106,0.30);background:rgba(255,77,106,0.06);color:var(--admin-red);letter-spacing:0.10em;}
    .cat-group-actions{display:flex;align-items:center;gap:4px;}

    /* ICON BUTTONS */
    .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid;border-radius:2px;cursor:pointer!important;transition:all 0.20s;background:transparent;font-size:0.82rem;position:relative;}
    .icon-btn::after{content:attr(data-tip);position:absolute;bottom:calc(100% + 8px);left:50%;transform:translateX(-50%);white-space:nowrap;font-family:var(--font-hud);font-size:0.46rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:5px 10px;background:rgba(6,5,26,0.96);border:1px solid rgba(139,126,255,0.30);color:var(--text-mid);pointer-events:none;opacity:0;transition:opacity 0.2s;z-index:200;}
    .icon-btn::before{content:'';position:absolute;bottom:calc(100% + 2px);left:50%;transform:translateX(-50%);border:5px solid transparent;border-top-color:rgba(139,126,255,0.30);pointer-events:none;opacity:0;transition:opacity 0.2s;z-index:200;}
    .icon-btn:hover::after,.icon-btn:hover::before{opacity:1;}
    .icon-btn.edit{color:var(--creo-sky);border-color:rgba(68,217,255,0.30);background:rgba(68,217,255,0.04);}
    .icon-btn.edit:hover{background:rgba(68,217,255,0.14);box-shadow:0 0 12px rgba(68,217,255,0.28);}
    .icon-btn.add{color:var(--admin-green);border-color:rgba(68,255,136,0.30);background:rgba(68,255,136,0.04);}
    .icon-btn.add:hover{background:rgba(68,255,136,0.14);box-shadow:0 0 12px rgba(68,255,136,0.28);}
    .icon-btn.del{color:var(--admin-red);border-color:rgba(255,77,106,0.30);background:rgba(255,77,106,0.04);}
    .icon-btn.del:hover{background:rgba(255,77,106,0.14);box-shadow:0 0 12px rgba(255,77,106,0.28);}
    .icon-btn.toggle-on{color:var(--admin-green);border-color:rgba(68,255,136,0.30);background:rgba(68,255,136,0.04);}
    .icon-btn.toggle-on:hover{background:rgba(68,255,136,0.14);}
    .icon-btn.toggle-off{color:var(--text-dim);border-color:rgba(96,88,160,0.25);background:rgba(96,88,160,0.04);}
    .icon-btn.toggle-off:hover{background:rgba(255,77,106,0.08);color:var(--admin-red);}

    /* FAQ ITEM TABLE */
    .faq-table-wrap{overflow-x:auto;}
    .faq-table{width:100%;border-collapse:collapse;}
    .faq-table th{font-family:var(--font-hud);font-size:0.48rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-dim);padding:10px 16px;text-align:left;background:rgba(0,0,8,0.40);border-bottom:1px solid rgba(139,126,255,0.10);white-space:nowrap;}
    .faq-table td{padding:12px 16px;border-bottom:1px solid rgba(139,126,255,0.07);vertical-align:middle;font-size:0.88rem;color:var(--text-mid);}
    .faq-table tr:last-child td{border-bottom:none;}
    .faq-table tr:hover td{background:rgba(139,126,255,0.03);}
    .faq-q-cell{max-width:340px;}
    .faq-q-text{font-family:var(--font-hud);font-size:0.65rem;font-weight:700;color:var(--text-high);letter-spacing:0.03em;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
    .faq-a-preview{font-size:0.78rem;color:var(--text-dim);margin-top:4px;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;}
    .sort-badge{font-family:var(--font-hud);font-size:0.52rem;color:var(--text-dim);padding:2px 8px;border:1px solid rgba(139,126,255,0.15);background:rgba(139,126,255,0.04);text-align:center;display:inline-block;}
    .status-active{color:var(--admin-green);font-family:var(--font-hud);font-size:0.48rem;letter-spacing:0.10em;text-transform:uppercase;}
    .status-hidden{color:var(--text-dim);font-family:var(--font-hud);font-size:0.48rem;letter-spacing:0.10em;text-transform:uppercase;}
    .faq-actions-cell{display:flex;gap:4px;align-items:center;}

    /* EMPTY */
    .empty-cat{padding:22px 18px;text-align:center;font-family:var(--font-hud);font-size:0.56rem;color:var(--text-dim);letter-spacing:0.10em;display:flex;align-items:center;justify-content:center;gap:10px;}
    .empty-cat i{font-size:1rem;color:rgba(139,126,255,0.20);}
    .empty-state{text-align:center;padding:60px 20px;border:1px dashed rgba(139,126,255,0.18);background:rgba(139,126,255,0.02);}
    .empty-state i{font-size:2rem;color:rgba(139,126,255,0.20);display:block;margin-bottom:14px;}
    .empty-state p{font-family:var(--font-hud);font-size:0.60rem;color:var(--text-dim);letter-spacing:0.08em;}

    /* MODALS */
    .modal-overlay{display:none;position:fixed;inset:0;z-index:9000;background:rgba(3,2,13,0.88);backdrop-filter:blur(10px);align-items:center;justify-content:center;padding:20px;}
    .modal-overlay.open{display:flex;}
    .modal-box{background:#0A0918;border:1px solid var(--border-neon);max-width:560px;width:100%;position:relative;max-height:90vh;overflow-y:auto;}
    .modal-box::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent);}
    .modal-box.wide{max-width:680px;}
    .modal-hdr{padding:16px 20px 13px;border-bottom:1px solid rgba(139,126,255,0.14);background:rgba(139,126,255,0.06);display:flex;align-items:center;justify-content:space-between;gap:12px;}
    .modal-hdr h3{font-family:var(--font-hud);font-size:0.73rem;font-weight:700;letter-spacing:0.06em;color:var(--prc-violet);}
    .modal-close{width:28px;height:28px;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.20)!important;color:var(--text-soft);display:flex;align-items:center;justify-content:center;cursor:pointer!important;font-size:0.72rem;transition:all 0.2s;}
    .modal-close:hover{background:rgba(139,126,255,0.14);color:var(--prc-violet);}
    .modal-body{padding:20px;}
    .modal-footer{padding:13px 20px;border-top:1px solid rgba(139,126,255,0.12);display:flex;gap:10px;justify-content:flex-end;}
    .modal-hint{font-size:0.80rem;color:var(--text-dim);margin-bottom:16px;padding:10px 14px;border:1px solid rgba(139,126,255,0.14);background:rgba(139,126,255,0.03);}
    .modal-hint i{color:var(--prc-violet);margin-right:6px;}

    /* CONFIRM */
    .confirm-icon{font-size:2rem;color:var(--admin-red);display:block;margin-bottom:12px;}
    .confirm-title{font-family:var(--font-hud);font-size:0.92rem;font-weight:800;color:#fff;margin-bottom:8px;}
    .confirm-text{font-size:0.88rem;color:var(--text-mid);line-height:1.70;}

    /* KEYFRAMES */
    @keyframes neonPulse{0%,100%{opacity:1}50%{opacity:.7}}
    @keyframes slideDown{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:translateY(0)}}

    /* SCROLLBAR */
    ::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-track{background:var(--bg-void);}::-webkit-scrollbar-thumb{background:var(--prc-violet);border-radius:2px;}

    /* RESPONSIVE */
    @media(max-width:1100px){.faq-layout{grid-template-columns:280px 1fr;}}
    @media(max-width:900px){.admin-shell{grid-template-columns:0 1fr;}.admin-sidebar-slot{display:none;}.admin-main{padding:18px 16px 48px;}body{cursor:auto;}button{cursor:pointer;}.cursor-dot,.cursor-ring{display:none;}}
    @media(max-width:768px){.faq-layout{grid-template-columns:1fr;}.faq-sidebar{position:static;}.field-row{grid-template-columns:1fr;}}
    @media(max-width:520px){.modal-footer{flex-direction:column;}}
  </style>
</head>
<body>

<div class="cursor-dot"  id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<div class="admin-shell" id="adminShell">

  <!-- SIDEBAR SLOT -->
  <div class="admin-sidebar-slot" id="sidebarSlot"></div>

  <!-- TOPBAR -->
  <header class="admin-topbar">
    <div class="topbar-breadcrumb">
      PRC Admin
      <i class="fi fi-rr-angle-right"></i>
      <span>FAQ Manager</span>
    </div>
    <div class="topbar-right">
      <button class="topbar-icon-btn" title="Refresh" onclick="location.reload()">
        <i class="fi fi-rr-refresh"></i>
      </button>
      <a href="faq.html" target="_blank" class="topbar-public-btn">
        <i class="fi fi-rr-eye"></i> Public FAQ
      </a>
      <div class="topbar-date"><span id="topbar-date-display"></span></div>
    </div>
  </header>

  <!-- MAIN -->
  <main class="admin-main">

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div>
        <div class="page-eyebrow"><span class="dot-live"></span> Content Management // FAQ</div>
        <h1 class="page-title"><span class="accent">FAQ</span> Manager</h1>
      </div>
      <div class="page-stats">
        <div class="stat-chip">
          <span class="stat-chip-num"><?= count($categories) ?></span>
          <span class="stat-chip-lbl">Categories</span>
        </div>
        <div class="stat-chip">
          <span class="stat-chip-num"><?= $total_faqs ?></span>
          <span class="stat-chip-lbl">Total FAQs</span>
        </div>
        <div class="stat-chip green">
          <span class="stat-chip-num"><?= $active_faqs ?></span>
          <span class="stat-chip-lbl">Published</span>
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

    <!-- LAYOUT -->
    <div class="faq-layout">

      <!-- ── LEFT SIDEBAR ── -->
      <aside class="faq-sidebar">

        <!-- ADD FAQ ITEM -->
        <div class="panel-card">
          <div class="panel-hdr">
            <i class="fi fi-rr-comment-alt-plus"></i>
            <div class="panel-hdr-text"><h3>New FAQ Item</h3><p>Add a question &amp; answer</p></div>
          </div>
          <div class="panel-body">
            <form method="POST" action="admin-faq.php" id="form-add-faq">
              <input type="hidden" name="action" value="add_faq"/>
              <div class="field">
                <label class="field-label">Category <span class="req">*</span></label>
                <select class="field-select" name="cat_id" id="add-faq-cat" required>
                  <option value="" disabled selected>— Select category —</option>
                  <?php foreach ($categories as $c): ?>
                  <option value="<?= $c['cat_id'] ?>"><?= htmlspecialchars($c['cat_label']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label class="field-label">Question <span class="req">*</span></label>
                <input class="field-input" type="text" name="faq_question" placeholder="e.g. Who can join PRC 2026?" maxlength="500" required/>
              </div>
              <div class="field">
                <label class="field-label">Answer <span class="req">*</span></label>
                <textarea class="field-textarea" name="faq_answer" rows="5" placeholder="Type the full answer here. You can use basic HTML like &lt;strong&gt;, &lt;a&gt;, &lt;ul&gt;, &lt;li&gt;…" required></textarea>
                <div class="field-hint">HTML is rendered on the public page.</div>
              </div>
              <div class="field" style="margin-bottom:16px">
                <label class="field-label">Sort Order</label>
                <input class="field-input" type="number" name="faq_sort" value="0" min="0"/>
                <div class="field-hint">Lower = shown first within category.</div>
              </div>
              <button type="submit" class="btn-primary"><i class="fi fi-rr-plus"></i> Add FAQ Item</button>
            </form>
          </div>
        </div>

        <!-- ADD CATEGORY -->
        <div class="panel-card amber-card">
          <div class="panel-hdr amber">
            <i class="fi fi-rr-folder-add"></i>
            <div class="panel-hdr-text"><h3>New Category</h3><p>Add a FAQ section</p></div>
          </div>
          <div class="panel-body">
            <form method="POST" action="admin-faq.php" id="form-add-cat">
              <input type="hidden" name="action" value="add_cat"/>
              <div class="field">
                <label class="field-label">Slug <span class="req">*</span></label>
                <input class="field-input" type="text" name="cat_slug" placeholder="e.g. registration" maxlength="60" required id="cat-slug-input"/>
                <div class="field-hint">Lowercase, no spaces. Used internally &amp; in filters.</div>
              </div>
              <div class="field">
                <label class="field-label">Display Label <span class="req">*</span></label>
                <input class="field-input" type="text" name="cat_label" placeholder="e.g. Registration" maxlength="150" required id="cat-label-input"/>
              </div>
              <div class="field" style="margin-bottom:16px">
                <label class="field-label">Sort Order</label>
                <input class="field-input" type="number" name="cat_sort" value="0" min="0"/>
              </div>
              <button type="submit" class="btn-primary btn-amber"><i class="fi fi-rr-folder-add"></i> Create Category</button>
            </form>
          </div>
        </div>

      </aside>

      <!-- ── RIGHT: CATEGORIES & FAQ LIST ── -->
      <section class="faq-right">
        <div class="faq-right-header">
          <div class="faq-right-title">
            Categories &amp; Questions
            <span class="count-pill"><?= count($categories) ?> cats · <?= $total_faqs ?> items</span>
          </div>
        </div>

        <?php if (empty($categories)): ?>
        <div class="empty-state">
          <i class="fi fi-rr-comment-question"></i>
          <p>No categories yet. Create your first one using the panel on the left.</p>
        </div>
        <?php else: ?>

        <?php foreach ($categories as $c):
          $cid   = $c['cat_id'];
          $items = $faqs_by_cat[$cid] ?? [];
          $inactive_class = !$c['is_active'] ? 'opacity:0.55' : '';
        ?>
        <div class="cat-group" id="cat-<?= $cid ?>" style="<?= $inactive_class ?>">
          <div class="cat-group-header">
            <div class="cat-group-title">
              <i class="fi fi-rr-comment-question"></i>
              <?= htmlspecialchars($c['cat_label']) ?>
              <span class="cat-slug-badge"><?= htmlspecialchars($c['cat_slug']) ?></span>
              <?php if (!$c['is_active']): ?>
              <span class="cat-inactive-badge">Hidden</span>
              <?php endif; ?>
              <span style="font-family:var(--font-hud);font-size:0.44rem;color:var(--text-dim);letter-spacing:0.10em;"><?= count($items) ?> items</span>
            </div>
            <div class="cat-group-actions">
              <button class="icon-btn add" data-tip="Add FAQ to this category" type="button"
                onclick="openAddFAQInCat(<?= $cid ?>,'<?= htmlspecialchars($c['cat_label'],ENT_QUOTES) ?>')"
                aria-label="Add FAQ item">
                <i class="fi fi-rr-comment-alt-plus"></i>
              </button>
              <button class="icon-btn edit" data-tip="Edit category" type="button"
                onclick="openEditCat(<?= $cid ?>,'<?= htmlspecialchars($c['cat_slug'],ENT_QUOTES) ?>','<?= htmlspecialchars($c['cat_label'],ENT_QUOTES) ?>',<?= (int)$c['cat_sort'] ?>,<?= (int)$c['is_active'] ?>)"
                aria-label="Edit category">
                <i class="fi fi-rr-edit"></i>
              </button>
              <button class="icon-btn del" data-tip="Delete category &amp; all FAQs" type="button"
                onclick="openConfirm('cat',<?= $cid ?>,'<?= htmlspecialchars($c['cat_label'],ENT_QUOTES) ?>','<?= count($items) ?> FAQ item(s)')"
                aria-label="Delete category">
                <i class="fi fi-rr-trash"></i>
              </button>
            </div>
          </div>

          <?php if (empty($items)): ?>
          <div class="empty-cat">
            <i class="fi fi-rr-comment-alt"></i>
            No FAQ items yet — click <i class="fi fi-rr-comment-alt-plus" style="margin:0 4px;color:var(--admin-green);"></i> above to add one.
          </div>
          <?php else: ?>
          <div class="faq-table-wrap">
            <table class="faq-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th style="width:100%">Question / Answer Preview</th>
                  <th>Sort</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $idx => $faq): ?>
                <tr>
                  <td style="color:var(--text-dim);font-family:var(--font-hud);font-size:0.50rem;"><?= $faq['faq_id'] ?></td>
                  <td class="faq-q-cell">
                    <div class="faq-q-text"><?= htmlspecialchars($faq['faq_question']) ?></div>
                    <div class="faq-a-preview"><?= htmlspecialchars(strip_tags($faq['faq_answer'])) ?></div>
                  </td>
                  <td><span class="sort-badge"><?= $faq['faq_sort'] ?></span></td>
                  <td>
                    <?php if ($faq['is_active']): ?>
                    <span class="status-active">● Published</span>
                    <?php else: ?>
                    <span class="status-hidden">○ Hidden</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="faq-actions-cell">
                      <!-- Toggle visibility -->
                      <form method="POST" action="admin-faq.php" style="display:inline">
                        <input type="hidden" name="action" value="toggle_faq"/>
                        <input type="hidden" name="faq_id" value="<?= $faq['faq_id'] ?>"/>
                        <input type="hidden" name="is_active" value="<?= $faq['is_active'] ? 0 : 1 ?>"/>
                        <button type="submit"
                          class="icon-btn <?= $faq['is_active'] ? 'toggle-on' : 'toggle-off' ?>"
                          data-tip="<?= $faq['is_active'] ? 'Hide' : 'Publish' ?>"
                          aria-label="Toggle visibility">
                          <i class="fi fi-rr-<?= $faq['is_active'] ? 'eye' : 'eye-crossed' ?>"></i>
                        </button>
                      </form>
                      <!-- Edit -->
                      <button class="icon-btn edit" data-tip="Edit FAQ" type="button"
                        onclick="openEditFAQ(
                          <?= $faq['faq_id'] ?>,
                          <?= $faq['cat_id'] ?>,
                          <?= htmlspecialchars(json_encode($faq['faq_question']), ENT_QUOTES) ?>,
                          <?= htmlspecialchars(json_encode($faq['faq_answer']), ENT_QUOTES) ?>,
                          <?= (int)$faq['faq_sort'] ?>,
                          <?= (int)$faq['is_active'] ?>
                        )"
                        aria-label="Edit FAQ">
                        <i class="fi fi-rr-edit"></i>
                      </button>
                      <!-- Delete -->
                      <button class="icon-btn del" data-tip="Delete FAQ" type="button"
                        onclick="openConfirm('faq',<?= $faq['faq_id'] ?>,'<?= htmlspecialchars(mb_strimwidth($faq['faq_question'],0,50,'…'),ENT_QUOTES) ?>','')"
                        aria-label="Delete FAQ">
                        <i class="fi fi-rr-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

      </section>
    </div><!-- /faq-layout -->

  </main>
</div><!-- /admin-shell -->

<!-- ══════ EDIT CATEGORY MODAL ══════ -->
<div class="modal-overlay" id="modal-edit-cat">
  <div class="modal-box">
    <div class="modal-hdr">
      <h3>Edit Category</h3>
      <button class="modal-close" onclick="closeModal('modal-edit-cat')"><i class="fi fi-rr-cross"></i></button>
    </div>
    <form method="POST" action="admin-faq.php">
      <input type="hidden" name="action" value="edit_cat"/>
      <input type="hidden" name="cat_id" id="ec-id"/>
      <div class="modal-body">
        <div class="field">
          <label class="field-label">Slug <span class="req">*</span></label>
          <input class="field-input" type="text" name="cat_slug" id="ec-slug" maxlength="60" required/>
          <div class="field-hint">Lowercase letters, numbers, underscores only.</div>
        </div>
        <div class="field">
          <label class="field-label">Display Label <span class="req">*</span></label>
          <input class="field-input" type="text" name="cat_label" id="ec-label" maxlength="150" required/>
        </div>
        <div class="field-row">
          <div class="field">
            <label class="field-label">Sort Order</label>
            <input class="field-input" type="number" name="cat_sort" id="ec-sort" min="0"/>
          </div>
          <div class="field" style="display:flex;flex-direction:column;justify-content:flex-end;padding-bottom:2px;">
            <label class="field-toggle" style="cursor:pointer">
              <input type="checkbox" name="cat_active" id="ec-active" value="1"/>
              <span class="toggle-track"></span>
              <span class="toggle-label">Visible on public FAQ</span>
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary btn-sm" style="clip-path:none" onclick="closeModal('modal-edit-cat')">Cancel</button>
        <button type="submit" class="btn-primary btn-sm" style="clip-path:none"><i class="fi fi-rr-check"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════ EDIT FAQ MODAL ══════ -->
<div class="modal-overlay" id="modal-edit-faq">
  <div class="modal-box wide">
    <div class="modal-hdr">
      <h3>Edit FAQ Item</h3>
      <button class="modal-close" onclick="closeModal('modal-edit-faq')"><i class="fi fi-rr-cross"></i></button>
    </div>
    <form method="POST" action="admin-faq.php">
      <input type="hidden" name="action" value="edit_faq"/>
      <input type="hidden" name="faq_id" id="ef-id"/>
      <div class="modal-body">
        <div class="modal-hint"><i class="fi fi-rr-info"></i>HTML is supported in the answer field — use <code>&lt;strong&gt;</code>, <code>&lt;a href=""&gt;</code>, <code>&lt;ul&gt;&lt;li&gt;</code> etc.</div>
        <div class="field">
          <label class="field-label">Category <span class="req">*</span></label>
          <select class="field-select" name="cat_id" id="ef-cat" required>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['cat_id'] ?>"><?= htmlspecialchars($c['cat_label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label class="field-label">Question <span class="req">*</span></label>
          <input class="field-input" type="text" name="faq_question" id="ef-question" maxlength="500" required/>
        </div>
        <div class="field">
          <label class="field-label">Answer <span class="req">*</span></label>
          <textarea class="field-textarea" name="faq_answer" id="ef-answer" rows="8" required></textarea>
        </div>
        <div class="field-row">
          <div class="field">
            <label class="field-label">Sort Order</label>
            <input class="field-input" type="number" name="faq_sort" id="ef-sort" min="0"/>
          </div>
          <div class="field" style="display:flex;flex-direction:column;justify-content:flex-end;padding-bottom:2px;">
            <label class="field-toggle" style="cursor:pointer">
              <input type="checkbox" name="faq_active" id="ef-active" value="1"/>
              <span class="toggle-track"></span>
              <span class="toggle-label">Published</span>
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary btn-sm" style="clip-path:none" onclick="closeModal('modal-edit-faq')">Cancel</button>
        <button type="submit" class="btn-primary btn-sm" style="clip-path:none"><i class="fi fi-rr-check"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════ DELETE CONFIRM MODAL ══════ -->
<div class="modal-overlay" id="modal-confirm">
  <div class="modal-box" style="max-width:420px;text-align:center">
    <div class="modal-hdr" style="justify-content:center;border-color:rgba(255,77,106,0.25);background:rgba(255,77,106,0.05)">
      <h3 style="color:var(--admin-red)">Confirm Delete</h3>
    </div>
    <div class="modal-body">
      <i class="fi fi-rr-trash confirm-icon"></i>
      <div class="confirm-title" id="confirm-title">Delete?</div>
      <div class="confirm-text" id="confirm-text">This action cannot be undone.</div>
    </div>
    <div class="modal-footer" style="justify-content:center">
      <button type="button" class="btn-primary btn-sm" style="clip-path:none" onclick="closeModal('modal-confirm')">Cancel</button>
      <form method="POST" action="admin-faq.php" style="display:inline">
        <input type="hidden" name="action"  id="confirm-action"/>
        <input type="hidden" name="cat_id"  id="confirm-cat-id"/>
        <input type="hidden" name="faq_id"  id="confirm-faq-id"/>
        <button type="submit" class="btn-primary btn-sm" style="clip-path:none;color:var(--admin-red);border-color:rgba(255,77,106,0.45)!important;"><i class="fi fi-rr-trash"></i> Yes, Delete</button>
      </form>
    </div>
  </div>
</div>

<!-- SIDEBAR INJECTION -->
<script>
(function(){
  var slot=document.getElementById('sidebarSlot');
  if(!slot) return;
  fetch('admin-sidebar.html')
    .then(function(r){return r.text();})
    .then(function(html){
      slot.innerHTML=html;
      slot.querySelectorAll('script').forEach(function(old){
        var s=document.createElement('script');
        s.textContent=old.textContent;
        document.body.appendChild(s);
      });
    })
    .catch(function(){
      slot.innerHTML='<aside style="position:fixed;top:0;left:0;height:100vh;width:248px;background:#04031A;border-right:1px solid rgba(139,126,255,0.18);display:flex;align-items:center;justify-content:center;font-family:Orbitron,monospace;font-size:0.55rem;color:rgba(139,126,255,0.40);letter-spacing:0.12em;text-align:center;padding:20px;">SIDEBAR<br/>COMPONENT<br/><span style="margin-top:8px;display:block;font-size:0.44rem;color:rgba(139,126,255,0.25)">admin-sidebar.html</span></aside>';
    });
})();
</script>

<script>
/* ── SIDEBAR COLLAPSE SYNC ── */
document.addEventListener('prc-sidebar-toggle',function(e){
  document.getElementById('adminShell').classList.toggle('sb-collapsed',e.detail.collapsed);
});
(function(){
  if(localStorage.getItem('prc_sidebar_collapsed')==='1')
    document.getElementById('adminShell').classList.add('sb-collapsed');
})();

/* ── TOPBAR DATE ── */
(function(){
  var el=document.getElementById('topbar-date-display');
  function update(){
    var d=new Date(),M=['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
    var h=d.getHours(),m=d.getMinutes(),ap=h>=12?'PM':'AM';
    h=h%12||12;
    el.innerHTML=M[d.getMonth()]+' '+String(d.getDate()).padStart(2,'0')+', '+d.getFullYear()
      +' &nbsp;<span>'+String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+' '+ap+'</span>';
  }
  update(); setInterval(update,30000);
})();

/* ── CUSTOM CURSOR ── */
(function(){
  var dot=document.getElementById('cursorDot'),ring=document.getElementById('cursorRing');
  if(!dot||!ring)return;
  var mx=0,my=0,rx=0,ry=0;
  document.addEventListener('mousemove',function(e){mx=e.clientX;my=e.clientY;dot.style.left=mx+'px';dot.style.top=my+'px';});
  (function l(){rx+=(mx-rx)*.12;ry+=(my-ry)*.12;ring.style.left=rx+'px';ring.style.top=ry+'px';requestAnimationFrame(l);})();
  document.querySelectorAll('a,button,.cat-group,.faq-table tr').forEach(function(el){
    el.addEventListener('mouseenter',function(){ring.classList.add('hovered');});
    el.addEventListener('mouseleave',function(){ring.classList.remove('hovered');});
  });
})();

/* ── MODAL HELPERS ── */
function openModal(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id).classList.remove('open');document.body.style.overflow='';}
document.querySelectorAll('.modal-overlay').forEach(function(el){
  el.addEventListener('click',function(e){if(e.target===el)closeModal(el.id);});
});
document.addEventListener('keydown',function(e){
  if(e.key==='Escape') document.querySelectorAll('.modal-overlay.open').forEach(function(m){closeModal(m.id);});
});

/* ── EDIT CATEGORY ── */
function openEditCat(id,slug,label,sort,active){
  document.getElementById('ec-id').value    = id;
  document.getElementById('ec-slug').value  = slug;
  document.getElementById('ec-label').value = label;
  document.getElementById('ec-sort').value  = sort;
  document.getElementById('ec-active').checked = !!active;
  openModal('modal-edit-cat');
}

/* ── ADD FAQ SCOPED TO A CATEGORY ── */
function openAddFAQInCat(catId, catLabel){
  var sel = document.getElementById('add-faq-cat');
  if(sel) sel.value = catId;
  document.getElementById('form-add-faq').scrollIntoView({behavior:'smooth',block:'start'});
  // Briefly highlight the sidebar panel
  var panel = document.getElementById('form-add-faq').closest('.panel-card');
  panel.style.boxShadow = '0 0 0 1.5px var(--prc-violet), 0 0 30px rgba(139,126,255,0.30)';
  setTimeout(function(){ panel.style.boxShadow=''; }, 1500);
}

/* ── EDIT FAQ ── */
function openEditFAQ(id, catId, question, answer, sort, active){
  document.getElementById('ef-id').value       = id;
  document.getElementById('ef-cat').value      = catId;
  document.getElementById('ef-question').value = question;
  document.getElementById('ef-answer').value   = answer;
  document.getElementById('ef-sort').value     = sort;
  document.getElementById('ef-active').checked = !!active;
  openModal('modal-edit-faq');
}

/* ── DELETE CONFIRM ── */
function openConfirm(type, id, name, extra){
  var isCat = type === 'cat';
  document.getElementById('confirm-title').textContent = isCat
    ? 'Delete category "'+name+'"?'
    : 'Delete this FAQ item?';
  document.getElementById('confirm-text').textContent  = isCat
    ? 'This will permanently delete the category and all '+extra+' inside it. Cannot be undone.'
    : 'This will permanently remove "'+name+'" from the public FAQ. Cannot be undone.';
  document.getElementById('confirm-action').value  = isCat ? 'delete_cat' : 'delete_faq';
  document.getElementById('confirm-cat-id').value  = isCat ? id : '';
  document.getElementById('confirm-faq-id').value  = isCat ? '' : id;
  openModal('modal-confirm');
}

/* ── AUTO-SLUG from label in Add Category form ── */
(function(){
  var label = document.getElementById('cat-label-input');
  var slug  = document.getElementById('cat-slug-input');
  if(!label||!slug) return;
  label.addEventListener('input', function(){
    if(slug._touched) return;
    slug.value = label.value.toLowerCase().trim().replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'');
  });
  slug.addEventListener('input', function(){ slug._touched = slug.value !== ''; });
})();
</script>
</body>
</html>