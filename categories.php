<?php
// PRC-WebApp/categories.php

$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'prc_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die('<p style="color:#ff6b6b;padding:40px;font-family:monospace;">DB error: '.htmlspecialchars($conn->connect_error).'</p>');

// ── Meta ──────────────────────────────────────────────────────
$meta = [];
$mr = $conn->query("SELECT meta_key, meta_value FROM prc_categories_meta");
if ($mr) while ($row = $mr->fetch_assoc()) $meta[$row['meta_key']] = $row['meta_value'];
$hero_desc = $meta['hero_desc'] ?? 'Three major tracks, eleven categories — each designed to challenge Filipino students at every level and open doors to the national and international stage.';

// ── Tracks ────────────────────────────────────────────────────
$tracks = [];
$tr = $conn->query("SELECT * FROM prc_categories_tracks WHERE is_active=1 ORDER BY track_sort ASC");
if ($tr) while ($row = $tr->fetch_assoc()) $tracks[] = $row;

// ── Subs keyed by track_id ────────────────────────────────────
$subs_by_track = [];
$sr = $conn->query("SELECT * FROM prc_categories_subs WHERE is_active=1 ORDER BY sub_sort ASC");
if ($sr) while ($row = $sr->fetch_assoc()) $subs_by_track[$row['track_id']][] = $row;

// ── Tags keyed by sub_id ──────────────────────────────────────
$tags_by_sub = [];
$tgr = $conn->query("SELECT * FROM prc_categories_tags ORDER BY tag_sort ASC");
if ($tgr) while ($row = $tgr->fetch_assoc()) $tags_by_sub[$row['sub_id']][] = $row['tag_text'];

// ── Tabs keyed by sub_id ──────────────────────────────────────
$tabs_by_sub = [];
$tabr = $conn->query("SELECT * FROM prc_categories_tabs WHERE is_active=1 ORDER BY tab_sort ASC");
if ($tabr) while ($row = $tabr->fetch_assoc()) $tabs_by_sub[$row['sub_id']][] = $row;

// ── Sections keyed by tab_id ──────────────────────────────────
$sections_by_tab = [];
$secr = $conn->query("SELECT * FROM prc_categories_sections ORDER BY section_sort ASC");
if ($secr) while ($row = $secr->fetch_assoc()) $sections_by_tab[$row['tab_id']][] = $row;

// ── Items keyed by section_id ─────────────────────────────────
$items_by_section = [];
$ir = $conn->query("SELECT * FROM prc_categories_items ORDER BY item_sort ASC");
if ($ir) while ($row = $ir->fetch_assoc()) $items_by_section[$row['section_id']][] = $row['item_text'];

$conn->close();

// ── Helpers ───────────────────────────────────────────────────
function track_active_class($color) {
    return match($color) {
        'mx'    => 'active-mx',
        'drone' => 'active-drone',
        default => 'active',
    };
}
function sub_active_class($color) {
    return match($color) {
        'mx'    => 'active-mx',
        'drone' => 'active-drone',
        default => 'active',
    };
}
function tag_class($color) {
    return match($color) { 'mx' => 'mx', 'drone' => 'drone', default => 'rv' };
}
function tc_color_class($color) {
    return match($color) { 'mx' => 'mx-color', 'drone' => 'drone-color', default => 'rv-color' };
}
function icon_class($color) {
    return match($color) { 'mx' => 'mx', 'drone' => 'drone', default => '' };
}
function detail_card_class($color) {
    return match($color) { 'mx' => 'mx', 'drone' => 'drone', default => '' };
}
function dot_class($color) {
    return match($color) { 'mx' => 'mx', 'drone' => 'drone', default => 'rv' };
}
function level_fill_class($color) {
    return match($color) { 'mx' => 'mx', 'drone' => 'drone', default => '' };
}
function btn_class($color) {
    return match($color) { 'mx' => 'mx', 'drone' => 'drone', default => '' };
}
function actions_class($color) {
    return match($color) { 'mx' => 'mx', 'drone' => 'drone', default => '' };
}
function panel_box_class($color) {
    return match($color) { 'mx' => 'box-mx', 'drone' => 'box-drone', default => '' };
}
function tab_class($color) {
    return match($color) { 'mx' => 'tab-mx', 'drone' => 'tab-drone', default => '' };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#8B7EFF" />
  <meta name="description" content="Philippine Robotics Cup 2026 — All Competition Categories including RoboVenture, MakeX, and Drone Soccer." />
  <title>Categories - Philippine Robotics Cup 2026</title>

  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link rel="shortcut icon" href="assets/favicon.png" />
  <link rel="apple-touch-icon" href="assets/favicon.png" />

  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-brands/css/uicons-brands.css'>

  <style>
    :root {
      --prc-violet:   #8B7EFF;
      --prc-ice:      #C4EEFF;
      --creo-purple:  #7733FF;
      --creo-amber:   #FFA030;
      --creo-volt:    #FFE930;
      --creo-sky:     #44D9FF;
      --neon-magenta: #CC55FF;
      --neon-primary: var(--prc-violet);
      --bg-void:      #03020D;
      --bg-deep:      #06051A;
      --border-neon:  rgba(139,126,255,0.22);
      --glow-primary: 0 0 18px rgba(139,126,255,0.60), 0 0 55px rgba(139,126,255,0.20);
      --glow-orange:  0 0 18px rgba(255,160,48,0.55),  0 0 55px rgba(255,160,48,0.18);
      --glow-sky:     0 0 18px rgba(68,217,255,0.55),  0 0 55px rgba(68,217,255,0.18);
      --glow-cyan:    0 0 18px rgba(196,238,255,0.55), 0 0 55px rgba(196,238,255,0.18);
      --glow-amber:   0 0 18px rgba(255,160,48,0.55),  0 0 55px rgba(255,160,48,0.18);
      --text-high:    #F2EEFF;
      --text-mid:     #C8C0F0;
      --text-soft:    #9A90CC;
      --text-dim:     #7068A8;
      --nav-height:   72px;
      --font-hud:     'Orbitron', monospace;
      --font-body:    'Exo 2', sans-serif;
      --sidebar-w:    320px;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body { font-family: var(--font-body); background: var(--bg-void); color: var(--text-high); overflow-x: hidden; line-height: 1.6; cursor: none; }
    img { max-width: 100%; display: block; }
    a { text-decoration: none; color: inherit; }
    ul { list-style: none; }
    button { font-family: inherit; cursor: none; border: none; background: none; }

    .cursor-dot  { position: fixed; width: 8px; height: 8px; border-radius: 50%; background: var(--neon-primary); pointer-events: none; z-index: 99999; transform: translate(-50%,-50%); box-shadow: var(--glow-primary); transition: transform 0.1s, background 0.2s; }
    .cursor-ring { position: fixed; width: 36px; height: 36px; border-radius: 50%; border: 1px solid rgba(139,126,255,0.65); pointer-events: none; z-index: 99998; transform: translate(-50%,-50%); transition: width 0.25s, height 0.25s, border-color 0.25s; }
    .cursor-ring.hovered { width: 56px; height: 56px; border-color: var(--creo-amber); border-width: 1.5px; }

    body::after { content: ''; position: fixed; inset: 0; z-index: 9998; pointer-events: none; background: repeating-linear-gradient(to bottom, transparent, transparent 2px, rgba(0,0,0,0.04) 2px, rgba(0,0,0,0.04) 4px); }

    .hex-grid { position: fixed; inset: 0; z-index: 0; pointer-events: none; background-image: linear-gradient(rgba(139,126,255,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(139,126,255,0.04) 1px, transparent 1px); background-size: 50px 50px; }
    .hex-grid::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(119,51,255,0.14) 0%, transparent 70%), radial-gradient(ellipse 60% 50% at 100% 100%, rgba(204,85,255,0.07) 0%, transparent 60%); }

    @keyframes neonPulse  { 0%,100%{opacity:1;} 50%{opacity:0.6;} }
    @keyframes fadeInUp   { from{opacity:0;transform:translateY(24px);} to{opacity:1;transform:translateY(0);} }
    @keyframes fadeIn     { from{opacity:0;} to{opacity:1;} }
    @keyframes scanDown   { from{transform:translateY(-100%);} to{transform:translateY(100vh);} }
    @keyframes panelSlide { from{opacity:0;transform:translateX(18px);} to{opacity:1;transform:translateX(0);} }

    .page-wrapper { position: relative; z-index: 1; }

    /* NAV */
    #main-nav { position: fixed; top: 0; left: 0; right: 0; height: var(--nav-height); z-index: 1000; background: rgba(3,2,13,0.94); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-neon); box-shadow: 0 0 30px rgba(139,126,255,0.10); }
    .nav-inner { max-width: 1340px; margin: 0 auto; height: 100%; padding: 0 36px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .nav-logo { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
    .nav-logo img { height: 38px; width: auto; transition: filter 0.3s; }
    .nav-logo:hover img { filter: drop-shadow(0 0 14px rgba(139,126,255,0.75)); }
    .nav-brand { font-family: var(--font-hud); font-weight: 700; font-size: 0.72rem; letter-spacing: 0.06em; line-height: 1.3; color: var(--prc-violet); text-shadow: 0 0 12px rgba(139,126,255,0.65); }
    .nav-brand span { color: var(--text-soft); display: block; font-size: 0.58rem; font-weight: 400; letter-spacing: 0.10em; text-transform: uppercase; margin-top: 1px; }
    .nav-links { display: flex; align-items: center; gap: 2px; }
    .nav-links a { font-family: var(--font-hud); font-size: 0.65rem; font-weight: 600; color: var(--text-mid); padding: 8px 14px; letter-spacing: 0.08em; text-transform: uppercase; border-radius: 4px; transition: all 0.2s; position: relative; white-space: nowrap; }
    .nav-links a:hover { color: var(--prc-violet); text-shadow: 0 0 12px rgba(139,126,255,0.85); }
    .nav-links a.active { color: var(--prc-violet); text-shadow: 0 0 12px rgba(139,126,255,0.85); }
    .nav-links a.active::after { content: ''; position: absolute; bottom: 4px; left: 14px; right: 14px; height: 1px; background: var(--prc-violet); box-shadow: 0 0 6px rgba(139,126,255,0.80); }
    .nav-cta { background: transparent !important; border: 1px solid var(--prc-violet) !important; color: var(--prc-violet) !important; padding: 8px 20px !important; border-radius: 3px !important; box-shadow: 0 0 15px rgba(139,126,255,0.28), inset 0 0 15px rgba(139,126,255,0.06) !important; transition: all 0.25s !important; margin-left: 8px; clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%); }
    .nav-cta:hover { background: rgba(139,126,255,0.12) !important; box-shadow: 0 0 30px rgba(139,126,255,0.52), inset 0 0 20px rgba(139,126,255,0.10) !important; color: #fff !important; }
    .nav-hamburger { display: none; flex-direction: column; justify-content: center; align-items: center; gap: 5px; width: 44px; height: 44px; padding: 0; cursor: none; background: rgba(139,126,255,0.06); border: 1px solid var(--border-neon); border-radius: 4px; flex-shrink: 0; z-index: 1002; -webkit-tap-highlight-color: transparent; touch-action: manipulation; transition: all 0.2s; }
    .nav-hamburger:hover { background: rgba(139,126,255,0.14); box-shadow: 0 0 14px rgba(139,126,255,0.28); }
    .nav-hamburger span { width: 20px; height: 1.5px; background: var(--prc-violet); border-radius: 2px; transition: transform 0.28s, opacity 0.28s; display: block; pointer-events: none; }
    .nav-hamburger.open span:nth-child(1) { transform: rotate(45deg) translate(5px,5px); }
    .nav-hamburger.open span:nth-child(2) { opacity: 0; }
    .nav-hamburger.open span:nth-child(3) { transform: rotate(-45deg) translate(5px,-5px); }
    .nav-mobile { display: none; position: fixed; top: var(--nav-height); left: 0; right: 0; background: rgba(3,2,13,0.98); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-neon); padding: 12px 18px 24px; z-index: 1000; flex-direction: column; gap: 2px; box-shadow: 0 20px 60px rgba(139,126,255,0.09); }
    .nav-mobile.open { display: flex; }
    .nav-mobile a { font-family: var(--font-hud); font-size: 0.70rem; font-weight: 600; color: var(--text-mid); padding: 13px 14px; border-radius: 3px; letter-spacing: 0.08em; text-transform: uppercase; transition: all 0.2s; display: flex; align-items: center; gap: 12px; }
    .nav-mobile a i { font-size: 1rem; color: var(--prc-violet); }
    .nav-mobile a:hover, .nav-mobile a.active { color: var(--prc-violet); background: rgba(139,126,255,0.07); text-shadow: 0 0 10px rgba(139,126,255,0.55); }
    .nav-mobile .nav-cta { border: 1px solid var(--prc-violet) !important; color: var(--prc-violet) !important; margin-top: 10px; justify-content: center; border-radius: 3px !important; clip-path: none !important; }

    /* PAGE HERO */
    .page-hero { position: relative; padding: calc(var(--nav-height) + 72px) 0 72px; overflow: hidden; text-align: center; }
    .page-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse 70% 70% at 50% 0%, rgba(68,217,255,0.10) 0%, transparent 70%), linear-gradient(to bottom, rgba(3,2,13,0) 60%, var(--bg-void) 100%); }
    .page-hero-scan { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
    .page-hero-scan::after { content: ''; position: absolute; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, var(--creo-sky), transparent); animation: scanDown 8s linear infinite; box-shadow: 0 0 14px rgba(68,217,255,0.55); }
    .page-hero-inner { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; padding: 0 36px; }
    .page-hero-eyebrow { display: inline-flex; align-items: center; gap: 10px; font-family: var(--font-hud); font-size: 0.60rem; font-weight: 600; letter-spacing: 0.20em; text-transform: uppercase; color: var(--creo-sky); margin-bottom: 18px; animation: fadeIn 0.8s ease both; }
    .page-hero-eyebrow::before { content: '//'; color: rgba(68,217,255,0.40); font-size: 0.70rem; }
    .page-hero-blink { width: 6px; height: 6px; background: var(--creo-sky); border-radius: 50%; box-shadow: var(--glow-sky); animation: neonPulse 1.2s ease-in-out infinite; }
    .page-hero-title { font-family: var(--font-hud); font-size: clamp(2.2rem, 6vw, 4rem); font-weight: 900; letter-spacing: -0.01em; line-height: 1.0; color: #fff; margin-bottom: 18px; text-shadow: 0 0 40px rgba(68,217,255,0.20); animation: fadeInUp 0.8s ease 0.1s both; }
    .page-hero-title .accent-sky { color: var(--creo-sky); text-shadow: 0 0 22px rgba(68,217,255,0.65); }
    .page-hero-desc { font-size: 1rem; color: var(--text-mid); line-height: 1.78; max-width: 520px; margin: 0 auto; animation: fadeInUp 0.8s ease 0.2s both; }
    .page-hero-divider { display: flex; align-items: center; justify-content: center; gap: 14px; margin-top: 36px; animation: fadeIn 0.8s ease 0.3s both; }
    .page-hero-divider-line { width: 80px; height: 1px; background: linear-gradient(90deg, transparent, rgba(68,217,255,0.40)); }
    .page-hero-divider-line.right { background: linear-gradient(90deg, rgba(68,217,255,0.40), transparent); }
    .page-hero-divider-diamond { width: 8px; height: 8px; background: var(--creo-sky); transform: rotate(45deg); box-shadow: var(--glow-sky); }

    /* CATEGORIES LAYOUT */
    .cats-shell { max-width: 1340px; margin: 0 auto; padding: 60px 36px 100px; display: grid; grid-template-columns: var(--sidebar-w) 1fr; gap: 32px; align-items: start; }

    /* SIDEBAR */
    .cats-sidebar { position: sticky; top: calc(var(--nav-height) + 20px); }
    .sidebar-section { margin-bottom: 10px; }
    .sidebar-cat-btn { width: 100%; display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: rgba(139,126,255,0.04); border: 1px solid rgba(139,126,255,0.20); color: var(--text-mid); cursor: pointer; transition: all 0.25s; position: relative; overflow: hidden; text-align: left; }
    .sidebar-cat-btn::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, rgba(139,126,255,0.40), transparent); opacity: 0; transition: opacity 0.25s; }
    .sidebar-cat-btn:hover { border-color: rgba(139,126,255,0.40); background: rgba(139,126,255,0.09); }
    .sidebar-cat-btn:hover::before { opacity: 1; }
    .sidebar-cat-btn.active       { border-color: var(--prc-violet); background: rgba(139,126,255,0.12); box-shadow: 0 0 22px rgba(139,126,255,0.16), inset 0 0 22px rgba(139,126,255,0.06); }
    .sidebar-cat-btn.active::before { opacity: 1; background: linear-gradient(90deg, transparent, var(--prc-violet), transparent); }
    .sidebar-cat-btn.active-mx    { border-color: var(--creo-sky);   background: rgba(68,217,255,0.08);  box-shadow: 0 0 22px rgba(68,217,255,0.14); }
    .sidebar-cat-btn.active-mx::before  { opacity: 1; background: linear-gradient(90deg, transparent, var(--creo-sky), transparent); }
    .sidebar-cat-btn.active-drone { border-color: var(--creo-amber); background: rgba(255,160,48,0.09); box-shadow: 0 0 22px rgba(255,160,48,0.14); }
    .sidebar-cat-btn.active-drone::before { opacity: 1; background: linear-gradient(90deg, transparent, var(--creo-amber), transparent); }
    .sidebar-cat-logo { flex-shrink: 0; width: 80px; height: 36px; display: flex; align-items: center; justify-content: center; }
    .sidebar-cat-logo img { max-width: 80px; max-height: 34px; width: auto; height: auto; object-fit: contain; filter: saturate(0.6) brightness(0.75); transition: filter 0.25s; }
    .sidebar-cat-btn:hover .sidebar-cat-logo img, .sidebar-cat-btn.active .sidebar-cat-logo img, .sidebar-cat-btn.active-mx .sidebar-cat-logo img, .sidebar-cat-btn.active-drone .sidebar-cat-logo img { filter: saturate(1) brightness(1.1) drop-shadow(0 0 6px rgba(139,126,255,0.45)); }
    .sidebar-cat-btn.active-mx .sidebar-cat-logo img { filter: saturate(1) brightness(1.1) drop-shadow(0 0 6px rgba(68,217,255,0.55)); }
    .sidebar-cat-btn.active-drone .sidebar-cat-logo img { filter: saturate(1) brightness(1.1) drop-shadow(0 0 6px rgba(255,160,48,0.55)); }
    .sidebar-cat-text { flex: 1; min-width: 0; }
    .sidebar-cat-name { display: block; font-family: var(--font-hud); font-size: 0.75rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-high); line-height: 1.2; margin-bottom: 3px; }
    .sidebar-cat-count { display: block; font-family: var(--font-body); font-size: 0.78rem; color: var(--text-dim); font-weight: 400; letter-spacing: 0.02em; }
    .sidebar-cat-chevron { font-size: 0.72rem; color: var(--text-dim); transition: transform 0.25s, color 0.25s; flex-shrink: 0; }
    .sidebar-cat-btn.active .sidebar-cat-chevron       { transform: rotate(90deg); color: var(--prc-violet); }
    .sidebar-cat-btn.active-mx .sidebar-cat-chevron    { transform: rotate(90deg); color: var(--creo-sky); }
    .sidebar-cat-btn.active-drone .sidebar-cat-chevron { transform: rotate(90deg); color: var(--creo-amber); }
    .sidebar-subs { overflow: hidden; max-height: 0; transition: max-height 0.38s cubic-bezier(0.23,1,0.32,1); padding-left: 0; margin-top: 4px; display: flex; flex-direction: column; gap: 3px; }
    .sidebar-subs.open { max-height: 700px; }
    .sidebar-sub-btn { width: 100%; display: flex; align-items: center; gap: 12px; padding: 13px 18px 13px 20px; font-family: var(--font-body); font-size: 0.93rem; font-weight: 500; letter-spacing: 0.01em; background: rgba(139,126,255,0.02); border: 1px solid rgba(139,126,255,0.10); border-left: 3px solid transparent; color: var(--text-soft); cursor: pointer; transition: all 0.20s; text-align: left; }
    .sidebar-sub-btn:hover { color: var(--text-high); border-color: rgba(139,126,255,0.22); border-left-color: rgba(139,126,255,0.50); background: rgba(139,126,255,0.06); }
    .sidebar-sub-btn.active { color: var(--text-high); font-weight: 600; border-color: rgba(139,126,255,0.28); border-left: 3px solid var(--prc-violet); background: rgba(139,126,255,0.09); box-shadow: inset 0 0 14px rgba(139,126,255,0.04); }
    .sidebar-sub-btn.active-mx { color: var(--text-high); font-weight: 600; border-color: rgba(68,217,255,0.25); border-left: 3px solid var(--creo-sky); background: rgba(68,217,255,0.06); }
    .sidebar-sub-btn.active-drone { color: var(--text-high); font-weight: 600; border-color: rgba(255,160,48,0.25); border-left: 3px solid var(--creo-amber); background: rgba(255,160,48,0.06); }
    .sub-num { width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-family: var(--font-hud); font-size: 0.50rem; font-weight: 700; background: rgba(139,126,255,0.08); border: 1px solid rgba(139,126,255,0.22); color: var(--text-dim); transition: all 0.20s; }
    .sidebar-sub-btn:hover .sub-num { background: rgba(139,126,255,0.14); color: var(--prc-violet); border-color: rgba(139,126,255,0.40); }
    .sidebar-sub-btn.active .sub-num { background: rgba(139,126,255,0.18); color: var(--prc-violet); border-color: var(--prc-violet); box-shadow: 0 0 8px rgba(139,126,255,0.40); }
    .sidebar-sub-btn.active-mx .sub-num { background: rgba(68,217,255,0.12); color: var(--creo-sky); border-color: var(--creo-sky); box-shadow: 0 0 8px rgba(68,217,255,0.35); }
    .sidebar-sub-btn.active-drone .sub-num { background: rgba(255,160,48,0.12); color: var(--creo-amber); border-color: var(--creo-amber); box-shadow: 0 0 8px rgba(255,160,48,0.35); }

    /* RIGHT CONTENT PANEL */
    .cats-panel { min-height: 600px; }
    .sub-panel { display: none; animation: panelSlide 0.30s ease both; }
    .sub-panel.active { display: block; }
    .panel-tabs { display: flex; gap: 6px; margin-bottom: 0; flex-wrap: wrap; }
    .panel-tab { font-family: var(--font-hud); font-size: 0.64rem; font-weight: 700; letter-spacing: 0.10em; text-transform: uppercase; padding: 11px 22px; border: 1px solid rgba(139,126,255,0.22); color: var(--text-soft); background: rgba(139,126,255,0.03); cursor: pointer; transition: all 0.20s; clip-path: polygon(5px 0%, 100% 0%, calc(100% - 5px) 100%, 0% 100%); }
    .panel-tab:hover { color: var(--prc-violet); border-color: rgba(139,126,255,0.40); background: rgba(139,126,255,0.08); }
    .panel-tab.active { color: var(--prc-violet); border-color: var(--prc-violet); background: rgba(139,126,255,0.10); box-shadow: 0 0 12px rgba(139,126,255,0.18); }
    .panel-tab.tab-mx.active    { color: var(--creo-sky);   border-color: var(--creo-sky);   background: rgba(68,217,255,0.08);  box-shadow: 0 0 12px rgba(68,217,255,0.18); }
    .panel-tab.tab-drone.active { color: var(--creo-amber); border-color: var(--creo-amber); background: rgba(255,160,48,0.08); box-shadow: 0 0 12px rgba(255,160,48,0.18); }
    .panel-box { border: 1px solid var(--border-neon); position: relative; overflow: hidden; background: rgba(139,126,255,0.02); }
    .panel-box::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--prc-violet), transparent); }
    .panel-box.box-mx::before    { background: linear-gradient(90deg, transparent, var(--creo-sky),   transparent); }
    .panel-box.box-drone::before { background: linear-gradient(90deg, transparent, var(--creo-amber), transparent); }
    .tab-content { display: none; padding: 40px; }
    .tab-content.active { display: block; animation: panelSlide 0.25s ease both; }
    .tc-header { display: flex; align-items: flex-start; gap: 22px; margin-bottom: 30px; }
    .tc-icon { width: 60px; height: 60px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; background: rgba(139,126,255,0.10); border: 1px solid rgba(139,126,255,0.28); color: var(--prc-violet); }
    .tc-icon.mx    { background: rgba(68,217,255,0.10);  border-color: rgba(68,217,255,0.28);  color: var(--creo-sky); }
    .tc-icon.drone { background: rgba(255,160,48,0.10);  border-color: rgba(255,160,48,0.28);  color: var(--creo-amber); }
    .tc-eyebrow { font-family: var(--font-hud); font-size: 0.55rem; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: var(--text-dim); margin-bottom: 7px; display: flex; align-items: center; gap: 6px; }
    .tc-eyebrow::before { content: '//'; color: rgba(139,126,255,0.35); }
    .tc-title { font-family: var(--font-hud); font-size: clamp(1.2rem, 2.4vw, 1.65rem); font-weight: 800; letter-spacing: 0.03em; color: var(--text-high); line-height: 1.15; margin-bottom: 10px; }
    .tc-title.rv-color    { text-shadow: 0 0 20px rgba(139,126,255,0.45); }
    .tc-title.mx-color    { text-shadow: 0 0 20px rgba(68,217,255,0.45); }
    .tc-title.drone-color { text-shadow: 0 0 20px rgba(255,160,48,0.45); }
    .tc-tags { display: flex; gap: 6px; flex-wrap: wrap; }
    .tc-tag { font-family: var(--font-hud); font-size: 0.57rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; padding: 4px 12px; border: 1px solid; }
    .tc-tag.rv    { color: var(--prc-violet); border-color: rgba(139,126,255,0.30); background: rgba(139,126,255,0.06); }
    .tc-tag.mx    { color: var(--creo-sky);   border-color: rgba(68,217,255,0.30);  background: rgba(68,217,255,0.06); }
    .tc-tag.drone { color: var(--creo-amber); border-color: rgba(255,160,48,0.30);  background: rgba(255,160,48,0.06); }
    .tc-desc { font-size: 1rem; color: var(--text-mid); line-height: 1.82; margin-bottom: 32px; }
    .tc-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 32px; }
    .tc-detail-card { background: rgba(139,126,255,0.04); border: 1px solid rgba(139,126,255,0.12); padding: 20px 22px; }
    .tc-detail-card.mx    { border-color: rgba(68,217,255,0.12);  background: rgba(68,217,255,0.03); }
    .tc-detail-card.drone { border-color: rgba(255,160,48,0.12); background: rgba(255,160,48,0.03); }
    .tc-detail-label { font-family: var(--font-hud); font-size: 0.56rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--text-soft); margin-bottom: 12px; display: flex; align-items: center; gap: 7px; }
    .tc-detail-label i { font-size: 0.85rem; }
    .tc-detail-label i.rv    { color: var(--prc-violet); }
    .tc-detail-label i.mx    { color: var(--creo-sky); }
    .tc-detail-label i.drone { color: var(--creo-amber); }
    .tc-detail-list { display: flex; flex-direction: column; gap: 8px; }
    .tc-detail-item { display: flex; align-items: flex-start; gap: 10px; font-size: 0.925rem; color: var(--text-mid); line-height: 1.55; }
    .tc-detail-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; margin-top: 8px; }
    .tc-detail-dot.rv    { background: var(--prc-violet); box-shadow: 0 0 5px rgba(139,126,255,0.55); }
    .tc-detail-dot.mx    { background: var(--creo-sky);   box-shadow: 0 0 5px rgba(68,217,255,0.55); }
    .tc-detail-dot.drone { background: var(--creo-amber); box-shadow: 0 0 5px rgba(255,160,48,0.55); }
    .tc-level { display: flex; align-items: center; gap: 16px; margin-bottom: 32px; }
    .tc-level-label { font-family: var(--font-hud); font-size: 0.56rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-dim); white-space: nowrap; }
    .tc-level-track { flex: 1; height: 5px; background: rgba(139,126,255,0.10); border-radius: 10px; }
    .tc-level-fill { height: 5px; border-radius: 10px; background: linear-gradient(90deg, rgba(139,126,255,0.50), var(--prc-violet)); box-shadow: 0 0 10px rgba(139,126,255,0.55); transition: width 1.0s cubic-bezier(0.23,1,0.32,1); }
    .tc-level-fill.mx    { background: linear-gradient(90deg, rgba(68,217,255,0.50),  var(--creo-sky));   box-shadow: 0 0 10px rgba(68,217,255,0.55); }
    .tc-level-fill.drone { background: linear-gradient(90deg, rgba(255,160,48,0.50),  var(--creo-amber)); box-shadow: 0 0 10px rgba(255,160,48,0.55); }
    .tc-level-pct { font-family: var(--font-hud); font-size: 0.56rem; letter-spacing: 0.08em; color: var(--text-soft); white-space: nowrap; }
    .tc-actions { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; padding-top: 10px; border-top: 1px solid rgba(139,126,255,0.14); }
    .tc-actions.mx    { border-color: rgba(68,217,255,0.14); }
    .tc-actions.drone { border-color: rgba(255,160,48,0.14); }
    .btn-register { display: inline-flex; align-items: center; gap: 10px; padding: 12px 28px; font-family: var(--font-hud); font-size: 0.66rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; background: transparent; color: var(--prc-violet); border: 1px solid var(--prc-violet); clip-path: polygon(10px 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%); box-shadow: 0 0 18px rgba(139,126,255,0.32), inset 0 0 18px rgba(139,126,255,0.07); transition: all 0.25s; cursor: pointer; position: relative; overflow: hidden; }
    .btn-register::before { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(139,126,255,0.18), transparent); transform: translateX(-100%); transition: transform 0.5s; }
    .btn-register:hover { background: rgba(139,126,255,0.12); box-shadow: 0 0 38px rgba(139,126,255,0.60), inset 0 0 28px rgba(139,126,255,0.12); color: #fff; transform: translateY(-2px); }
    .btn-register:hover::before { transform: translateX(100%); }
    .btn-register.mx    { color: var(--creo-sky);   border-color: var(--creo-sky);   box-shadow: 0 0 18px rgba(68,217,255,0.28); }
    .btn-register.mx:hover    { background: rgba(68,217,255,0.12); box-shadow: 0 0 38px rgba(68,217,255,0.55); }
    .btn-register.drone { color: var(--creo-amber); border-color: var(--creo-amber); box-shadow: 0 0 18px rgba(255,160,48,0.28); }
    .btn-register.drone:hover { background: rgba(255,160,48,0.12); box-shadow: 0 0 38px rgba(255,160,48,0.55); }
    .btn-secondary-link { display: inline-flex; align-items: center; gap: 8px; font-family: var(--font-hud); font-size: 0.60rem; font-weight: 600; letter-spacing: 0.10em; text-transform: uppercase; color: var(--text-soft); padding: 8px 0; transition: color 0.2s; cursor: pointer; }
    .btn-secondary-link:hover { color: var(--text-mid); }
    .panel-box-corner { position: absolute; width: 18px; height: 18px; border-color: rgba(139,126,255,0.30); border-style: solid; pointer-events: none; }
    .panel-box-corner.tl { top: 8px; left: 8px;   border-width: 1.5px 0 0 1.5px; }
    .panel-box-corner.br { bottom: 8px; right: 8px; border-width: 0 1.5px 1.5px 0; }

    /* EMPTY PANEL */
    .empty-panel { padding: 60px 40px; text-align: center; border: 1px dashed rgba(139,126,255,0.18); }
    .empty-panel p { font-family: var(--font-hud); font-size: 0.60rem; color: var(--text-dim); letter-spacing: 0.10em; }

    /* FOOTER */
    footer { background: rgba(0,0,6,0.95); border-top: 1px solid var(--border-neon); padding: 70px 0 32px; }
    .footer-inner { max-width: 1340px; margin: 0 auto; padding: 0 36px; }
    .footer-top { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 52px; margin-bottom: 52px; }
    .footer-brand img { height: 36px; width: auto; margin-bottom: 16px; }
    .footer-brand p { font-size: 0.875rem; color: var(--text-mid); line-height: 1.80; margin-bottom: 22px; }
    .footer-contact-list { display: flex; flex-direction: column; gap: 11px; margin-bottom: 24px; }
    .footer-contact-item { display: flex; align-items: center; gap: 11px; font-size: 0.875rem; color: var(--text-mid); }
    .footer-contact-item i { color: var(--prc-violet); font-size: 0.95rem; flex-shrink: 0; }
    .footer-contact-item a { color: var(--text-mid); transition: color 0.2s; }
    .footer-contact-item a:hover { color: var(--prc-violet); text-shadow: 0 0 8px rgba(139,126,255,0.60); }
    .social-links { display: flex; gap: 8px; }
    .social-link { width: 40px; height: 40px; background: rgba(139,126,255,0.04); border: 1px solid rgba(139,126,255,0.18); display: flex; align-items: center; justify-content: center; font-size: 1rem; color: rgba(139,126,255,0.50); transition: all 0.25s; }
    .social-link:hover { background: rgba(139,126,255,0.12); color: var(--prc-violet); box-shadow: 0 0 14px rgba(139,126,255,0.35); border-color: var(--prc-violet); }
    .footer-col h4 { font-family: var(--font-hud); font-size: 0.65rem; font-weight: 700; letter-spacing: 0.10em; text-transform: uppercase; margin-bottom: 20px; color: var(--prc-ice); text-shadow: 0 0 10px rgba(196,238,255,0.45); }
    .footer-col ul { display: flex; flex-direction: column; gap: 10px; }
    .footer-col ul li a { font-size: 0.875rem; color: var(--text-mid); transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
    .footer-col ul li a i { font-size: 0.65rem; color: rgba(139,126,255,0.38); transition: color 0.2s; }
    .footer-col ul li a:hover { color: var(--prc-violet); padding-left: 4px; }
    .footer-col ul li a:hover i { color: var(--prc-violet); }
    .footer-bottom { padding-top: 24px; border-top: 1px solid rgba(139,126,255,0.12); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; }
    .footer-bottom p { font-family: var(--font-hud); font-size: 0.58rem; color: var(--text-soft); letter-spacing: 0.06em; }
    .footer-bottom-links { display: flex; gap: 22px; }
    .footer-bottom-links a { font-family: var(--font-hud); font-size: 0.58rem; color: var(--text-soft); transition: color 0.2s; letter-spacing: 0.06em; }
    .footer-bottom-links a:hover { color: var(--prc-violet); }

    .reveal { opacity:0; transform:translateY(24px); transition: opacity 0.55s ease, transform 0.55s ease; }
    .reveal.visible { opacity:1; transform:translateY(0); }

    @media (max-width: 1200px) { :root { --sidebar-w: 280px; } }
    @media (max-width: 1024px) { :root { --sidebar-w: 250px; } }
    @media (max-width: 900px) { .cats-shell { grid-template-columns: 1fr; } .cats-sidebar { position: static; } .sidebar-subs { max-height: none !important; } }
    @media (max-width: 768px) { :root { --nav-height: 62px; } body { cursor: auto; } button { cursor: pointer; } .cursor-dot, .cursor-ring { display: none; } .nav-links { display: none; } .nav-hamburger { display: flex; } .cats-shell { padding: 40px 16px 80px; } .footer-top { grid-template-columns: 1fr 1fr; gap: 36px; } .tc-detail-grid { grid-template-columns: 1fr; } }
    @media (max-width: 520px) { :root { --nav-height: 58px; } .nav-inner { padding: 0 14px; } .nav-brand span { display: none; } .footer-top { grid-template-columns: 1fr; } .footer-bottom { flex-direction: column; text-align: center; } .page-hero-inner { padding: 0 16px; } }
    ::-webkit-scrollbar { width: 4px; } ::-webkit-scrollbar-track { background: var(--bg-void); } ::-webkit-scrollbar-thumb { background: var(--prc-violet); box-shadow: 0 0 8px rgba(139,126,255,0.70); border-radius: 2px; }
  </style>
</head>
<body>

  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>
  <div class="hex-grid" aria-hidden="true"></div>

  <div class="page-wrapper">

    <!-- NAV -->
    <?php $activePage = 'categories'; include 'nav.php'; ?>

    <!-- PAGE HERO -->
    <header class="page-hero">
      <div class="page-hero-scan"></div>
      <div class="page-hero-inner">
        <div class="page-hero-eyebrow">
          <div class="page-hero-blink"></div>
          PRC 2026 &mdash; Competition Tracks
        </div>
        <h1 class="page-hero-title">All <span class="accent-sky">Categories</span></h1>
        <p class="page-hero-desc"><?= htmlspecialchars($hero_desc) ?></p>
        <div class="page-hero-divider" aria-hidden="true">
          <div class="page-hero-divider-line"></div>
          <div class="page-hero-divider-diamond"></div>
          <div class="page-hero-divider-line right"></div>
        </div>
      </div>
    </header>

    <!-- MAIN -->
    <main>
      <div class="cats-shell">

        <!-- SIDEBAR -->
        <aside class="cats-sidebar reveal" aria-label="Category navigation">
          <?php foreach ($tracks as $ti => $track):
            $color   = $track['track_color'];
            $slug    = $track['track_slug'];
            $tsubs   = $subs_by_track[$track['track_id']] ?? [];
            $isFirst = ($ti === 0);
            $activeClass = $isFirst ? track_active_class($color) : '';
          ?>
          <div class="sidebar-section">
            <button class="sidebar-cat-btn <?= $activeClass ?>"
              id="btn-<?= htmlspecialchars($slug) ?>"
              onclick="selectCat('<?= htmlspecialchars($slug) ?>')"
              aria-expanded="<?= $isFirst ? 'true' : 'false' ?>">
              <span class="sidebar-cat-logo">
                <?php if ($track['track_logo']): ?>
                  <img src="<?= htmlspecialchars($track['track_logo']) ?>" alt="<?= htmlspecialchars($track['track_name']) ?>" />
                <?php else: ?>
                  <i class="fi fi-rr-trophy" style="font-size:1.4rem;color:var(--prc-violet);"></i>
                <?php endif; ?>
              </span>
              <span class="sidebar-cat-text">
                <span class="sidebar-cat-name"><?= htmlspecialchars($track['track_name']) ?></span>
                <span class="sidebar-cat-count"><?= count($tsubs) ?> Sub-categor<?= count($tsubs) === 1 ? 'y' : 'ies' ?></span>
              </span>
              <i class="fi fi-rr-angle-right sidebar-cat-chevron"></i>
            </button>
            <div class="sidebar-subs <?= $isFirst ? 'open' : '' ?>" id="subs-<?= htmlspecialchars($slug) ?>">
              <?php foreach ($tsubs as $si => $sub): ?>
              <button class="sidebar-sub-btn <?= ($isFirst && $si === 0) ? sub_active_class($color) : '' ?>"
                id="sub-<?= htmlspecialchars($slug) ?>-<?= htmlspecialchars($sub['sub_slug']) ?>"
                onclick="selectSub('<?= htmlspecialchars($slug) ?>','<?= htmlspecialchars($sub['sub_slug']) ?>')">
                <span class="sub-num"><?= str_pad($si + 1, 2, '0', STR_PAD_LEFT) ?></span>
                <?= htmlspecialchars($sub['sub_name']) ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </aside>

        <!-- RIGHT PANEL -->
        <section class="cats-panel" aria-live="polite">
          <?php
          $firstPanel = true;
          foreach ($tracks as $track):
            $color  = $track['track_color'];
            $slug   = $track['track_slug'];
            $tsubs  = $subs_by_track[$track['track_id']] ?? [];
            foreach ($tsubs as $si => $sub):
              $panelId  = 'panel-' . $slug . '-' . $sub['sub_slug'];
              $isActive = $firstPanel;
              $firstPanel = false;
              $subTabs  = $tabs_by_sub[$sub['sub_id']] ?? [];
              $subTags  = $tags_by_sub[$sub['sub_id']] ?? [];
              $tabClass = tab_class($color);
              $boxClass = panel_box_class($color);
          ?>
          <div class="sub-panel <?= $isActive ? 'active' : '' ?>" id="<?= $panelId ?>">
            <!-- Tab buttons -->
            <div class="panel-tabs">
              <?php foreach ($subTabs as $tabi => $tab): ?>
              <button class="panel-tab <?= $tabClass ?> <?= $tabi === 0 ? 'active' : '' ?>"
                onclick="switchTab('<?= $slug ?>','<?= htmlspecialchars($sub['sub_slug']) ?>','<?= htmlspecialchars($tab['tab_slug']) ?>')">
                <?= htmlspecialchars($tab['tab_label']) ?>
              </button>
              <?php endforeach; ?>
            </div>

            <div class="panel-box <?= $boxClass ?>">
              <span class="panel-box-corner tl"></span>
              <span class="panel-box-corner br"></span>

              <?php foreach ($subTabs as $tabi => $tab):
                $tabSections = $sections_by_tab[$tab['tab_id']] ?? [];
                $tcId = 'tc-' . $slug . '-' . $sub['sub_slug'] . '-' . $tab['tab_slug'];
                $isOverview = ($tab['tab_slug'] === 'overview');
              ?>
              <div class="tab-content <?= $tabi === 0 ? 'active' : '' ?>" id="<?= $tcId ?>">

                <?php if ($isOverview): ?>
                <!-- Overview header (icon + title + tags) -->
                <div class="tc-header">
                  <div class="tc-icon <?= icon_class($color) ?>">
                    <i class="<?= htmlspecialchars($sub['sub_icon'] ?? 'fi fi-rr-settings') ?>"></i>
                  </div>
                  <div>
                    <div class="tc-eyebrow"><?= htmlspecialchars($sub['sub_eyebrow'] ?? '') ?></div>
                    <h2 class="tc-title <?= tc_color_class($color) ?>"><?= htmlspecialchars($sub['sub_title'] ?? $sub['sub_name']) ?></h2>
                    <?php if ($subTags): ?>
                    <div class="tc-tags">
                      <?php foreach ($subTags as $tag): ?>
                      <span class="tc-tag <?= tag_class($color) ?>"><?= htmlspecialchars($tag) ?></span>
                      <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Description -->
                <?php if ($sub['sub_desc']): ?>
                <p class="tc-desc"><?= nl2br(htmlspecialchars($sub['sub_desc'])) ?></p>
                <?php endif; ?>

                <?php endif; // isOverview ?>

                <!-- Non-overview: eyebrow heading -->
                <?php if (!$isOverview && !$isOverview): ?>
                <div class="tc-eyebrow" style="margin-bottom:20px;"><?= htmlspecialchars($tab['tab_label']) ?></div>
                <?php endif; ?>

                <?php if (!$isOverview): ?>
                <div class="tc-eyebrow" style="margin-bottom:20px;"><?= htmlspecialchars($tab['tab_label']) ?></div>
                <?php endif; ?>

                <!-- Detail cards grid -->
                <?php if (!empty($tabSections)): ?>
                <div class="tc-detail-grid">
                  <?php foreach ($tabSections as $sec):
                    $secItems = $items_by_section[$sec['section_id']] ?? [];
                  ?>
                  <div class="tc-detail-card <?= detail_card_class($color) ?>">
                    <div class="tc-detail-label">
                      <i class="<?= htmlspecialchars($sec['section_icon']) ?> <?= dot_class($color) ?>"></i>
                      <?= htmlspecialchars($sec['section_label']) ?>
                    </div>
                    <?php if ($secItems): ?>
                    <div class="tc-detail-list">
                      <?php foreach ($secItems as $item): ?>
                      <div class="tc-detail-item">
                        <span class="tc-detail-dot <?= dot_class($color) ?>"></span>
                        <?= htmlspecialchars($item) ?>
                      </div>
                      <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="tc-detail-list">
                      <div class="tc-detail-item" style="color:var(--text-dim);font-style:italic;font-size:.82rem;">No items added yet.</div>
                    </div>
                    <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Difficulty bar (overview only) -->
                <?php if ($isOverview): ?>
                <div class="tc-level">
                  <span class="tc-level-label">Difficulty</span>
                  <div class="tc-level-track">
                    <div class="tc-level-fill <?= level_fill_class($color) ?>" data-width="<?= (int)$sub['difficulty_pct'] ?>%"></div>
                  </div>
                  <span class="tc-level-pct"><?= htmlspecialchars($sub['difficulty_label']) ?></span>
                </div>
                <?php endif; ?>

                <!-- Action buttons -->
                <div class="tc-actions <?= actions_class($color) ?>">
                  <a href="#" class="btn-register <?= btn_class($color) ?>">
                    <i class="fi fi-rr-pen-field"></i> Register for this Category
                  </a>
                  <?php if (!$isOverview): ?>
                  <a href="contact.php" class="btn-secondary-link">
                    <i class="fi fi-rr-envelope"></i> Inquire about this category
                  </a>
                  <?php endif; ?>
                </div>

              </div>
              <?php endforeach; // tabs ?>
            </div><!-- /panel-box -->
          </div><!-- /sub-panel -->
          <?php
            endforeach; // subs
          endforeach; // tracks
          ?>

          <?php if (empty($tracks)): ?>
          <div class="empty-panel">
            <p>No categories configured yet. Visit the admin panel to set up tracks and sub-categories.</p>
          </div>
          <?php endif; ?>
        </section>
      </div><!-- /cats-shell -->
    </main>

    <!-- FOOTER -->
    <footer role="contentinfo">
      <div class="footer-inner">
        <div class="footer-top">
          <div class="footer-brand">
            <img src="assets/PRC White Logo.png" alt="Philippine Robotics Cup" />
            <p>The Philippine Robotics Cup is a premier national robotics competition promoting STEM education and preparing Filipino students for the future of technology.</p>
            <div class="footer-contact-list">
              <div class="footer-contact-item"><i class="fi fi-brands-facebook"></i><a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank" rel="noopener">Philippine Robotics Cup</a></div>
              <div class="footer-contact-item"><i class="fi fi-rr-phone-call"></i><a href="tel:+639177713961">+63 917 771 3961</a></div>
              <div class="footer-contact-item"><i class="fi fi-rr-envelope"></i><a href="mailto:philippineroboticscup@gmail.com">philippineroboticscup@gmail.com</a></div>
            </div>
            <div class="social-links"><a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank" rel="noopener" class="social-link" aria-label="Facebook"><i class="fi fi-brands-facebook"></i></a></div>
          </div>
          <nav class="footer-col" aria-label="Competition"><h4>Competition</h4><ul>
            <li><a href="categories.php"><i class="fi fi-rr-angle-right"></i>Categories</a></li>
            <li><a href="#"><i class="fi fi-rr-angle-right"></i>Rules &amp; Guidelines</a></li>
            <li><a href="#"><i class="fi fi-rr-angle-right"></i>Schedule</a></li>
            <li><a href="#"><i class="fi fi-rr-angle-right"></i>Past Events</a></li>
          </ul></nav>
          <nav class="footer-col" aria-label="Participate"><h4>Participate</h4><ul>
            <li><a href="#"><i class="fi fi-rr-angle-right"></i>Register Now</a></li>
            <li><a href="#"><i class="fi fi-rr-angle-right"></i>Order Materials</a></li>
            <li><a href="contact.php"><i class="fi fi-rr-angle-right"></i>Contact Us</a></li>
            <li><a href="contact.php"><i class="fi fi-rr-angle-right"></i>FAQ</a></li>
          </ul></nav>
          <nav class="footer-col" aria-label="Resources"><h4>Resources</h4><ul>
            <li><a href="#"><i class="fi fi-rr-angle-right"></i>News &amp; Updates</a></li>
            <li><a href="index.php#highlights"><i class="fi fi-rr-angle-right"></i>Gallery</a></li>
            <li><a href="index.php#video"><i class="fi fi-rr-angle-right"></i>Videos</a></li>
            <li><a href="#"><i class="fi fi-rr-angle-right"></i>Creotec Philippines</a></li>
          </ul></nav>
        </div>
        <div class="footer-bottom">
          <p>&copy; 2026 Philippine Robotics Cup // Creotec Philippines Inc. All rights reserved.</p>
          <div class="footer-bottom-links"><a href="#">Privacy Policy</a><a href="#">Terms of Use</a></div>
        </div>
      </div>
    </footer>

  </div><!-- /page-wrapper -->

  <script>
    // ── CURSOR ──
    var dot = document.getElementById('cursorDot');
    var ring = document.getElementById('cursorRing');
    var mx = 0, my = 0, rx = 0, ry = 0;
    document.addEventListener('mousemove', function(e) { mx = e.clientX; my = e.clientY; dot.style.left = mx+'px'; dot.style.top = my+'px'; });
    (function animRing() { rx += (mx-rx)*0.12; ry += (my-ry)*0.12; ring.style.left = rx+'px'; ring.style.top = ry+'px'; requestAnimationFrame(animRing); })();
    document.querySelectorAll('a, button').forEach(function(el) {
      el.addEventListener('mouseenter', function() { ring.classList.add('hovered'); dot.style.background = 'var(--creo-amber)'; dot.style.boxShadow = 'var(--glow-amber)'; });
      el.addEventListener('mouseleave', function() { ring.classList.remove('hovered'); dot.style.background = 'var(--prc-violet)'; dot.style.boxShadow = 'var(--glow-primary)'; });
    });

    // ── Track color map from PHP ──
    var trackColors = <?php
      $map = [];
      foreach ($tracks as $t) $map[$t['track_slug']] = $t['track_color'];
      echo json_encode($map);
    ?>;

    function activeClassForColor(color) {
      return color === 'mx' ? 'active-mx' : (color === 'drone' ? 'active-drone' : 'active');
    }

    var currentCat = <?php echo json_encode($tracks[0]['track_slug'] ?? 'rv'); ?>;
    var currentSub = {};
    <?php foreach ($tracks as $track):
      $tsubs = $subs_by_track[$track['track_id']] ?? [];
      $firstSlug = $tsubs[0]['sub_slug'] ?? '';
    ?>
    currentSub[<?= json_encode($track['track_slug']) ?>] = <?= json_encode($firstSlug) ?>;
    <?php endforeach; ?>

    function selectCat(cat) {
      var wasActive = (currentCat === cat);
      // Deactivate all
      <?php foreach ($tracks as $t): ?>
      (function() {
        var b = document.getElementById('btn-<?= $t['track_slug'] ?>');
        if (b) { b.className = 'sidebar-cat-btn'; b.setAttribute('aria-expanded','false'); }
        var s = document.getElementById('subs-<?= $t['track_slug'] ?>');
        if (s) s.classList.remove('open');
      })();
      <?php endforeach; ?>
      document.querySelectorAll('.sidebar-sub-btn').forEach(function(b) { b.classList.remove('active','active-mx','active-drone'); });
      if (!wasActive) {
        currentCat = cat;
        var catBtn = document.getElementById('btn-' + cat);
        if (catBtn) { catBtn.classList.add(activeClassForColor(trackColors[cat] || 'rv')); catBtn.setAttribute('aria-expanded','true'); }
        var subsEl = document.getElementById('subs-' + cat);
        if (subsEl) subsEl.classList.add('open');
        selectSub(cat, currentSub[cat] || '', true);
      }
    }

    function selectSub(cat, sub, skipCatToggle) {
      if (!skipCatToggle && currentCat !== cat) selectCat(cat);
      currentSub[cat] = sub;
      document.querySelectorAll('.sidebar-sub-btn').forEach(function(b) { b.classList.remove('active','active-mx','active-drone'); });
      var subBtn = document.getElementById('sub-' + cat + '-' + sub);
      if (subBtn) subBtn.classList.add(activeClassForColor(trackColors[cat] || 'rv'));
      document.querySelectorAll('.sub-panel').forEach(function(p) { p.classList.remove('active'); });
      var panel = document.getElementById('panel-' + cat + '-' + sub);
      if (panel) {
        panel.classList.add('active');
        setTimeout(function() { animateLevelFills(panel); }, 80);
      }
    }

    function switchTab(cat, sub, tab) {
      var panelId = 'panel-' + cat + '-' + sub;
      var panel = document.getElementById(panelId);
      if (!panel) return;
      panel.querySelectorAll('.panel-tab').forEach(function(t) { t.classList.remove('active'); });
      panel.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
      // Find the tab button that calls this tab slug and activate it
      panel.querySelectorAll('.panel-tab').forEach(function(t) {
        if (t.getAttribute('onclick') && t.getAttribute('onclick').indexOf("'" + tab + "'") > -1) t.classList.add('active');
      });
      var content = document.getElementById('tc-' + cat + '-' + sub + '-' + tab);
      if (content) { content.classList.add('active'); setTimeout(function() { animateLevelFills(content); }, 60); }
    }

    function animateLevelFills(container) {
      container.querySelectorAll('.tc-level-fill').forEach(function(fill) {
        var w = fill.getAttribute('data-width') || '0%';
        fill.style.width = '0%';
        setTimeout(function() { fill.style.transition = 'width 1.0s cubic-bezier(0.23,1,0.32,1)'; fill.style.width = w; }, 30);
      });
    }

    // ── SCROLL REVEAL ──
    var revEls = document.querySelectorAll('.reveal');
    var ro = new IntersectionObserver(function(entries) {
      entries.forEach(function(e) { if (e.isIntersecting) { e.target.classList.add('visible'); ro.unobserve(e.target); } });
    }, { threshold: 0.08 });
    revEls.forEach(function(el) { ro.observe(el); });

    window.addEventListener('load', function() {
      var firstPanel = document.querySelector('.sub-panel.active');
      if (firstPanel) setTimeout(function() { animateLevelFills(firstPanel); }, 400);
    });
  </script>

</body>
</html>