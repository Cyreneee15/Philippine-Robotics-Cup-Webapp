<?php
// PRC-WebApp/gallery.php
// ── DB ──────────────────────────────────────────────────────────
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'prc_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die('<p style="color:#ff6b6b;padding:40px;font-family:monospace;">DB error: ' . htmlspecialchars($conn->connect_error) . '</p>');
}

// ── Fetch all folders ordered by sort ──────────────────────────
$folders = [];
$fr = $conn->query("SELECT * FROM prc_gallery_folders ORDER BY folder_sort ASC, folder_name DESC");
if ($fr) while ($row = $fr->fetch_assoc()) $folders[] = $row;

// ── Fetch all photos keyed by folder_id ───────────────────────
$photos_by_folder = [];
$pr = $conn->query(
    "SELECT p.*, f.folder_name, f.folder_label
     FROM prc_gallery_photos p
     JOIN prc_gallery_folders f ON p.folder_id = f.folder_id
     ORDER BY p.photo_sort ASC"
);
if ($pr) {
    while ($row = $pr->fetch_assoc()) {
        $photos_by_folder[$row['folder_id']][] = $row;
    }
}

$total_photos = 0;
foreach ($photos_by_folder as $arr) $total_photos += count($arr);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#8B7EFF" />
  <meta name="description" content="Philippine Robotics Cup Gallery — Explore highlights from past PRC competitions." />
  <title>Gallery - Philippine Robotics Cup</title>

  <!-- PWA -->
  <link rel="manifest" href="manifest.json" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
  <meta name="apple-mobile-web-app-title" content="PRC Gallery" />

  <!-- OG / Social -->
  <meta property="og:title" content="Gallery — Philippine Robotics Cup" />
  <meta property="og:description" content="Explore highlights from past PRC competitions." />
  <meta property="og:type" content="website" />

  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link rel="shortcut icon" href="assets/favicon.png" />
  <link rel="apple-touch-icon" href="assets/favicon.png" />

  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-brands/css/uicons-brands.css'>

  <style>
    /* ===================== TOKENS ===================== */
    :root {
      --prc-violet:   #8B7EFF;
      --prc-ice:      #C4EEFF;
      --creo-purple:  #7733FF;
      --creo-amber:   #FFA030;
      --creo-volt:    #FFE930;
      --creo-sky:     #44D9FF;
      --neon-primary: var(--prc-violet);
      --bg-void:      #03020D;
      --border-neon:  rgba(139,126,255,0.22);
      --glow-primary: 0 0 18px rgba(139,126,255,0.60), 0 0 55px rgba(139,126,255,0.20);
      --glow-orange:  0 0 18px rgba(255,160,48,0.55),  0 0 55px rgba(255,160,48,0.18);
      --glow-volt:    0 0 18px rgba(255,233,48,0.60),  0 0 55px rgba(255,233,48,0.20);
      --text-high:    #F2EEFF;
      --text-mid:     #C8C0F0;
      --text-soft:    #9A90CC;
      --text-dim:     #7068A8;
      --nav-height:   72px;
      --font-hud:     'Orbitron', monospace;
      --font-body:    'Exo 2', sans-serif;
    }

    /* ===================== RESET ===================== */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body { font-family: var(--font-body); background: var(--bg-void); color: var(--text-high); overflow-x: hidden; line-height: 1.6; cursor: none; }
    img { max-width: 100%; display: block; }
    a { text-decoration: none; color: inherit; }
    ul { list-style: none; }
    button { font-family: inherit; cursor: none; border: none; background: none; }

    /* ===================== CURSOR ===================== */
    .cursor-dot  { position: fixed; width: 8px; height: 8px; border-radius: 50%; background: var(--neon-primary); pointer-events: none; z-index: 99999; transform: translate(-50%,-50%); box-shadow: var(--glow-primary); transition: transform 0.1s, background 0.2s; }
    .cursor-ring { position: fixed; width: 36px; height: 36px; border-radius: 50%; border: 1px solid rgba(139,126,255,0.65); pointer-events: none; z-index: 99998; transform: translate(-50%,-50%); transition: width 0.25s, height 0.25s, border-color 0.25s; }
    .cursor-ring.hovered { width: 56px; height: 56px; border-color: var(--creo-amber); border-width: 1.5px; }

    /* ===================== SCANLINES ===================== */
    body::after { content: ''; position: fixed; inset: 0; z-index: 9998; pointer-events: none; background: repeating-linear-gradient(to bottom, transparent, transparent 2px, rgba(0,0,0,0.04) 2px, rgba(0,0,0,0.04) 4px); }

    /* ===================== HEX GRID ===================== */
    .hex-grid { position: fixed; inset: 0; z-index: 0; pointer-events: none; background-image: linear-gradient(rgba(139,126,255,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(139,126,255,0.04) 1px, transparent 1px); background-size: 50px 50px; }
    .hex-grid::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(119,51,255,0.14) 0%, transparent 70%), radial-gradient(ellipse 60% 50% at 100% 100%, rgba(204,85,255,0.07) 0%, transparent 60%), radial-gradient(ellipse 50% 50% at 0% 80%, rgba(139,126,255,0.09) 0%, transparent 60%); }

    /* ===================== ANIMATIONS ===================== */
    @keyframes neonPulse  { 0%,100%{opacity:1;} 50%{opacity:0.6;} }
    @keyframes fadeInUp   { from{opacity:0;transform:translateY(24px);} to{opacity:1;transform:translateY(0);} }
    @keyframes fadeIn     { from{opacity:0;} to{opacity:1;} }
    @keyframes scanDown   { from{transform:translateY(-100%);} to{transform:translateY(100vh);} }
    @keyframes lightboxIn { from{opacity:0;transform:scale(0.96);} to{opacity:1;transform:scale(1);} }
    @keyframes imgReveal  { from{opacity:0;transform:translateY(18px);} to{opacity:1;transform:translateY(0);} }

    .page-wrapper { position: relative; z-index: 1; }

    /* ===================== NAV ===================== */
    #main-nav { position: fixed; top: 0; left: 0; right: 0; height: var(--nav-height); z-index: 1000; background: rgba(3,2,13,0.94); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-neon); box-shadow: 0 0 30px rgba(139,126,255,0.10); }
    .nav-inner { max-width: 1340px; margin: 0 auto; height: 100%; padding: 0 36px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .nav-logo { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
    .nav-logo img { height: 38px; width: auto; transition: filter 0.3s; }
    .nav-logo:hover img { filter: drop-shadow(0 0 14px rgba(139,126,255,0.75)); }
    .nav-brand { font-family: var(--font-hud); font-weight: 700; font-size: 0.72rem; letter-spacing: 0.06em; line-height: 1.3; color: var(--prc-violet); text-shadow: 0 0 12px rgba(139,126,255,0.65); }
    .nav-brand span { color: var(--text-soft); display: block; font-size: 0.58rem; font-weight: 400; letter-spacing: 0.10em; text-transform: uppercase; margin-top: 1px; }
    .nav-links { display: flex; align-items: center; gap: 2px; }
    .nav-links a { font-family: var(--font-hud); font-size: 0.65rem; font-weight: 600; color: var(--text-mid); padding: 8px 14px; letter-spacing: 0.08em; text-transform: uppercase; border-radius: 4px; transition: all 0.2s; white-space: nowrap; position: relative; }
    .nav-links a:hover { color: var(--prc-violet); text-shadow: 0 0 12px rgba(139,126,255,0.85); }
    .nav-links a.active { color: var(--prc-violet); text-shadow: 0 0 12px rgba(139,126,255,0.85); }
    .nav-links a.active::after { content: ''; position: absolute; bottom: 4px; left: 14px; right: 14px; height: 1px; background: var(--prc-violet); box-shadow: 0 0 6px rgba(139,126,255,0.80); }
    .nav-cta { background: transparent !important; border: 1px solid var(--prc-violet) !important; color: var(--prc-violet) !important; padding: 8px 20px !important; border-radius: 3px !important; box-shadow: 0 0 15px rgba(139,126,255,0.28), inset 0 0 15px rgba(139,126,255,0.06) !important; transition: all 0.25s !important; margin-left: 8px; clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%); }
    .nav-cta:hover { background: rgba(139,126,255,0.12) !important; box-shadow: 0 0 30px rgba(139,126,255,0.52), inset 0 0 20px rgba(139,126,255,0.10) !important; color: #fff !important; }
    /* Shop icon — small square icon button */
    .nav-shop-icon { display: inline-flex !important; align-items: center; justify-content: center; width: 36px; height: 36px; padding: 0 !important; border: 1px solid rgba(139,126,255,0.30) !important; background: rgba(139,126,255,0.04) !important; border-radius: 3px !important; color: var(--text-mid) !important; font-size: 1rem; transition: all 0.22s !important; margin: 0 4px; box-shadow: none !important; clip-path: none !important; }
    .nav-shop-icon:hover { color: var(--creo-amber) !important; border-color: rgba(255,160,48,0.60) !important; background: rgba(255,160,48,0.08) !important; box-shadow: 0 0 14px rgba(255,160,48,0.28) !important; text-shadow: none !important; }
    .nav-shop-icon.active { color: var(--creo-amber) !important; border-color: rgba(255,160,48,0.60) !important; background: rgba(255,160,48,0.08) !important; }
    .nav-shop-icon.active::after { display: none !important; }
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

    /* ===================== PAGE HERO ===================== */
    .page-hero { position: relative; padding: calc(var(--nav-height) + 72px) 0 72px; overflow: hidden; text-align: center; }
    .page-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse 70% 70% at 50% 0%, rgba(139,126,255,0.10) 0%, transparent 70%), linear-gradient(to bottom, rgba(3,2,13,0) 60%, var(--bg-void) 100%); }
    .page-hero-scan { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
    .page-hero-scan::after { content: ''; position: absolute; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, var(--prc-violet), var(--prc-ice), transparent); animation: scanDown 8s linear infinite; box-shadow: 0 0 14px rgba(139,126,255,0.55); }
    .page-hero-inner { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; padding: 0 36px; }
    .page-hero-eyebrow { display: inline-flex; align-items: center; gap: 10px; font-family: var(--font-hud); font-size: 0.60rem; font-weight: 600; letter-spacing: 0.20em; text-transform: uppercase; color: var(--prc-ice); margin-bottom: 18px; animation: fadeIn 0.8s ease both; }
    .page-hero-eyebrow::before { content: '//'; color: rgba(139,126,255,0.40); font-size: 0.70rem; }
    .page-hero-blink { width: 6px; height: 6px; background: var(--prc-violet); border-radius: 50%; box-shadow: var(--glow-primary); animation: neonPulse 1.2s ease-in-out infinite; }
    .page-hero-title { font-family: var(--font-hud); font-size: clamp(2.2rem, 6vw, 4rem); font-weight: 900; letter-spacing: -0.01em; line-height: 1.0; color: #fff; margin-bottom: 18px; text-shadow: 0 0 40px rgba(139,126,255,0.20); animation: fadeInUp 0.8s ease 0.1s both; }
    .page-hero-title .accent { color: var(--prc-violet); text-shadow: 0 0 22px rgba(139,126,255,0.65); }
    .page-hero-desc { font-size: 1rem; color: var(--text-mid); line-height: 1.78; max-width: 520px; margin: 0 auto; animation: fadeInUp 0.8s ease 0.2s both; }
    .page-hero-divider { display: flex; align-items: center; justify-content: center; gap: 14px; margin-top: 36px; animation: fadeIn 0.8s ease 0.3s both; }
    .page-hero-divider-line { width: 80px; height: 1px; background: linear-gradient(90deg, transparent, rgba(139,126,255,0.40)); }
    .page-hero-divider-line.right { background: linear-gradient(90deg, rgba(139,126,255,0.40), transparent); }
    .page-hero-divider-diamond { width: 8px; height: 8px; background: var(--prc-violet); transform: rotate(45deg); box-shadow: var(--glow-primary); }

    /* ===================== GALLERY MAIN ===================== */
    .gallery-main { max-width: 1340px; margin: 0 auto; padding: 48px 36px 100px; }

    /* ===================== FILTER BAR ===================== */
    .gallery-filter-bar {
      display: flex; align-items: center; justify-content: space-between;
      gap: 20px; flex-wrap: wrap; margin-bottom: 52px;
      padding: 24px 28px;
      border: 1px solid rgba(255,160,48,0.28);
      background: linear-gradient(135deg, rgba(255,160,48,0.04) 0%, rgba(139,126,255,0.06) 100%);
      position: relative; overflow: hidden;
      box-shadow: 0 0 40px rgba(255,160,48,0.06), inset 0 0 40px rgba(139,126,255,0.03);
    }
    .gallery-filter-bar::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, transparent, var(--creo-amber), var(--prc-violet), transparent); box-shadow: 0 0 16px rgba(255,160,48,0.45); }
    .gallery-filter-bar::after  { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(255,160,48,0.20), transparent); }
    .filter-left { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .filter-label { font-family: var(--font-hud); font-size: 0.78rem; font-weight: 900; letter-spacing: 0.18em; text-transform: uppercase; color: var(--creo-amber); text-shadow: 0 0 14px rgba(255,160,48,0.60); white-space: nowrap; display: flex; align-items: center; gap: 8px; }
    .filter-label::before { content: '//'; color: rgba(255,160,48,0.45); font-size: 0.85rem; }
    .filter-divider { width: 1px; height: 36px; background: linear-gradient(to bottom, transparent, rgba(255,160,48,0.35), transparent); flex-shrink: 0; }
    .gallery-year-select { font-family: var(--font-hud); font-size: 0.80rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-high); background: rgba(255,160,48,0.08); border: 1px solid rgba(255,160,48,0.40); padding: 13px 44px 13px 18px; appearance: none; -webkit-appearance: none; cursor: pointer; outline: none; transition: all 0.2s; clip-path: polygon(7px 0%, 100% 0%, calc(100% - 7px) 100%, 0% 100%); min-width: 200px; }
    .gallery-year-select:hover { background: rgba(255,160,48,0.14); border-color: var(--creo-amber); box-shadow: 0 0 18px rgba(255,160,48,0.28); color: #fff; }
    .gallery-year-select:focus { border-color: var(--creo-amber); box-shadow: 0 0 18px rgba(255,160,48,0.28); }
    .gallery-year-select option { background: #0A0818; color: var(--text-high); }
    .select-wrap { position: relative; display: inline-block; }
    .select-wrap::after { content: '▾'; position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: var(--creo-amber); font-size: 0.90rem; pointer-events: none; text-shadow: 0 0 8px rgba(255,160,48,0.60); }
    .filter-pills { display: flex; gap: 8px; flex-wrap: wrap; }
    .filter-pill { font-family: var(--font-hud); font-size: 0.74rem; font-weight: 800; letter-spacing: 0.10em; text-transform: uppercase; padding: 11px 22px; border: 1px solid rgba(139,126,255,0.28); color: var(--text-soft); background: rgba(139,126,255,0.04); cursor: pointer; transition: all 0.22s; clip-path: polygon(5px 0%, 100% 0%, calc(100% - 5px) 100%, 0% 100%); position: relative; }
    .filter-pill:hover { border-color: var(--creo-amber); color: var(--creo-amber); background: rgba(255,160,48,0.08); box-shadow: 0 0 14px rgba(255,160,48,0.22); }
    .filter-pill[data-year="all"].active { border-color: var(--prc-violet); color: var(--prc-violet); background: rgba(139,126,255,0.14); box-shadow: 0 0 16px rgba(139,126,255,0.28); text-shadow: 0 0 10px rgba(139,126,255,0.55); }
    .filter-pill[data-year]:not([data-year="all"]).active { border-color: var(--creo-amber); color: var(--creo-amber); background: rgba(255,160,48,0.12); box-shadow: 0 0 18px rgba(255,160,48,0.30); text-shadow: 0 0 12px rgba(255,160,48,0.65); }
    .filter-right { display: flex; align-items: center; gap: 10px; }
    .photo-count-badge { font-family: var(--font-hud); font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-mid); padding: 10px 20px; border: 1px solid rgba(255,233,48,0.22); background: rgba(255,233,48,0.04); white-space: nowrap; }
    .photo-count-badge span { color: var(--creo-volt); font-size: 1.1rem; font-weight: 900; text-shadow: 0 0 14px rgba(255,233,48,0.65); margin-right: 4px; }

    /* ===================== OVERVIEW MODE ===================== */
    .gallery-overview { display: flex; flex-direction: column; gap: 64px; }
    .year-section-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px; }
    .year-section-left { display: flex; align-items: center; gap: 14px; }
    .year-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--prc-violet); box-shadow: 0 0 10px rgba(139,126,255,0.70); flex-shrink: 0; animation: neonPulse 2s ease-in-out infinite; }
    .year-title { font-family: var(--font-hud); font-size: 1.1rem; font-weight: 800; color: #fff; letter-spacing: 0.04em; }
    .year-title-line { flex: 1; height: 1px; background: linear-gradient(90deg, rgba(139,126,255,0.35), transparent); max-width: 200px; }
    .year-count { font-family: var(--font-hud); font-size: 0.52rem; color: var(--text-dim); letter-spacing: 0.10em; text-transform: uppercase; }
    .see-all-btn { display: inline-flex; align-items: center; gap: 8px; font-family: var(--font-hud); font-size: 0.58rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--prc-violet); border: 1px solid rgba(139,126,255,0.35); background: rgba(139,126,255,0.06); padding: 8px 18px; cursor: pointer; transition: all 0.22s; clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%); flex-shrink: 0; }
    .see-all-btn:hover { background: rgba(139,126,255,0.14); border-color: var(--prc-violet); box-shadow: 0 0 16px rgba(139,126,255,0.28); color: #fff; }
    .preview-strip { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }

    /* ===================== ALL-YEAR MODE ===================== */
    .gallery-all { display: none; }
    .gallery-all.active { display: block; }
    .gallery-all-header { display: flex; align-items: center; gap: 16px; margin-bottom: 32px; }
    .back-btn { display: inline-flex; align-items: center; gap: 8px; font-family: var(--font-hud); font-size: 0.60rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-soft); border: 1px solid rgba(139,126,255,0.22); background: transparent; padding: 9px 18px; cursor: pointer; transition: all 0.22s; clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%); }
    .back-btn:hover { color: var(--prc-violet); border-color: rgba(139,126,255,0.45); background: rgba(139,126,255,0.08); }
    .all-year-label { font-family: var(--font-hud); font-size: 1rem; font-weight: 800; color: #fff; letter-spacing: 0.04em; }
    .gallery-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }

    /* ===================== SHARED PHOTO CARD ===================== */
    .photo-card { position: relative; overflow: hidden; border: 1px solid rgba(139,126,255,0.10); background: rgba(0,0,8,0.80); cursor: pointer; transition: border-color 0.30s, box-shadow 0.30s, transform 0.30s; aspect-ratio: 4/3; }
    .photo-card:hover { border-color: rgba(139,126,255,0.42); box-shadow: 0 0 24px rgba(139,126,255,0.18); transform: translateY(-3px); }
    .photo-card img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.45s, filter 0.35s; filter: brightness(0.78) saturate(0.7); }
    .photo-card:hover img { transform: scale(1.06); filter: brightness(0.90) saturate(1.0); }
    .photo-card::before, .photo-card::after { content: ''; position: absolute; width: 14px; height: 14px; z-index: 3; border-color: var(--prc-ice); border-style: solid; opacity: 0; transition: opacity 0.25s; pointer-events: none; }
    .photo-card::before { top: 8px; left: 8px; border-width: 1.5px 0 0 1.5px; }
    .photo-card::after  { bottom: 8px; right: 8px; border-width: 0 1.5px 1.5px 0; }
    .photo-card:hover::before, .photo-card:hover::after { opacity: 1; }
    .photo-card-accent { position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--prc-violet), transparent); opacity: 0; transition: opacity 0.25s; z-index: 3; }
    .photo-card:hover .photo-card-accent { opacity: 1; }
    .photo-card-overlay { position: absolute; inset: 0; z-index: 2; background: linear-gradient(to top, rgba(3,2,13,0.92) 0%, rgba(3,2,13,0.30) 45%, transparent 100%); opacity: 0; transition: opacity 0.30s; display: flex; flex-direction: column; justify-content: flex-end; padding: 14px; }
    .photo-card:hover .photo-card-overlay { opacity: 1; }
    .photo-card-caption { font-family: var(--font-hud); font-size: 0.60rem; font-weight: 700; color: var(--text-high); letter-spacing: 0.06em; margin-bottom: 4px; line-height: 1.3; }
    .photo-card-meta { font-family: var(--font-hud); font-size: 0.50rem; color: var(--prc-violet); letter-spacing: 0.10em; text-transform: uppercase; }
    .photo-id-badge { position: absolute; top: 10px; left: 10px; z-index: 4; font-family: var(--font-hud); font-size: 0.46rem; font-weight: 700; letter-spacing: 0.10em; color: var(--text-soft); background: rgba(3,2,13,0.70); border: 1px solid rgba(139,126,255,0.22); padding: 3px 9px; backdrop-filter: blur(4px); }
    .photo-expand-icon { position: absolute; top: 10px; right: 10px; z-index: 4; width: 30px; height: 30px; background: rgba(3,2,13,0.70); border: 1px solid rgba(139,126,255,0.35); display: flex; align-items: center; justify-content: center; color: var(--prc-ice); font-size: 0.80rem; opacity: 0; transition: opacity 0.22s, background 0.22s; backdrop-filter: blur(4px); }
    .photo-card:hover .photo-expand-icon { opacity: 1; }
    .photo-card.card-in { animation: imgReveal 0.40s ease both; }

    /* ===================== LIGHTBOX ===================== */
    .lightbox-overlay { position: fixed; inset: 0; z-index: 9000; background: rgba(3,2,13,0.96); backdrop-filter: blur(16px); display: none; align-items: center; justify-content: center; padding: 20px; }
    .lightbox-overlay.open { display: flex; }
    .lightbox-box { position: relative; max-width: 1100px; width: 100%; animation: lightboxIn 0.30s ease both; display: flex; flex-direction: column; gap: 0; }
    .lightbox-topbar { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(139,126,255,0.06); border: 1px solid rgba(139,126,255,0.22); border-bottom: none; }
    .lightbox-topbar-left { display: flex; align-items: center; gap: 10px; }
    .lightbox-id { font-family: var(--font-hud); font-size: 0.52rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--prc-violet); }
    .lightbox-year-tag { font-family: var(--font-hud); font-size: 0.48rem; font-weight: 700; letter-spacing: 0.10em; padding: 3px 10px; border: 1px solid rgba(255,233,48,0.30); color: var(--creo-volt); background: rgba(255,233,48,0.06); text-shadow: 0 0 8px rgba(255,233,48,0.50); }
    .lightbox-topbar-right { display: flex; align-items: center; gap: 8px; }
    .lb-btn { display: flex; align-items: center; gap: 7px; font-family: var(--font-hud); font-size: 0.56rem; font-weight: 700; letter-spacing: 0.10em; text-transform: uppercase; padding: 8px 16px; border: 1px solid; cursor: pointer; transition: all 0.20s; background: transparent; white-space: nowrap; }
    .lb-btn-download { color: var(--creo-volt); border-color: rgba(255,233,48,0.35); background: rgba(255,233,48,0.05); }
    .lb-btn-download:hover { background: rgba(255,233,48,0.14); box-shadow: 0 0 16px rgba(255,233,48,0.30); color: #fff; border-color: var(--creo-volt); }
    .lb-btn-close { color: var(--text-soft); border-color: rgba(139,126,255,0.22); }
    .lb-btn-close:hover { color: #fff; border-color: rgba(255,100,100,0.50); background: rgba(255,80,80,0.10); box-shadow: 0 0 14px rgba(255,80,80,0.20); }
    .lightbox-img-wrap { position: relative; overflow: hidden; border: 1px solid rgba(139,126,255,0.22); border-top: none; border-bottom: none; background: #000; max-height: 72vh; display: flex; align-items: center; justify-content: center; }
    .lightbox-img-wrap::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--prc-violet), var(--prc-ice), transparent); z-index: 2; box-shadow: 0 0 14px rgba(139,126,255,0.55); }
    #lightboxImg { max-width: 100%; max-height: 72vh; object-fit: contain; display: block; filter: brightness(0.95); }
    .lb-nav { position: absolute; top: 50%; transform: translateY(-50%); z-index: 5; display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; background: rgba(3,2,13,0.75); border: 1px solid rgba(139,126,255,0.30); color: var(--prc-ice); font-size: 1rem; cursor: pointer; transition: all 0.20s; backdrop-filter: blur(6px); }
    .lb-nav:hover { background: rgba(139,126,255,0.25); border-color: var(--prc-violet); box-shadow: 0 0 14px rgba(139,126,255,0.40); }
    .lb-nav-prev { left: 14px; }
    .lb-nav-next { right: 14px; }
    .lightbox-caption-bar { padding: 14px 18px; background: rgba(139,126,255,0.04); border: 1px solid rgba(139,126,255,0.22); border-top: none; display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; }
    .lb-caption-text { display: flex; flex-direction: column; gap: 4px; }
    .lb-caption-main { font-family: var(--font-hud); font-size: 0.70rem; font-weight: 700; color: var(--text-high); letter-spacing: 0.04em; }
    .lb-caption-sub { font-family: var(--font-hud); font-size: 0.52rem; color: var(--text-soft); letter-spacing: 0.08em; text-transform: uppercase; }
    .lb-nav-counter { font-family: var(--font-hud); font-size: 0.56rem; color: var(--text-dim); letter-spacing: 0.10em; white-space: nowrap; align-self: center; }
    .lb-kbd-hint { font-family: var(--font-hud); font-size: 0.46rem; color: var(--text-dim); letter-spacing: 0.08em; display: flex; gap: 10px; align-items: center; align-self: center; white-space: nowrap; }
    .lb-key { display: inline-block; padding: 2px 7px; border: 1px solid rgba(139,126,255,0.22); color: var(--text-soft); background: rgba(139,126,255,0.06); font-size: 0.50rem; }

    /* ===================== EMPTY STATE ===================== */
    .gallery-empty { text-align: center; padding: 72px 36px; border: 1px solid var(--border-neon); background: rgba(139,126,255,0.02); }
    .gallery-empty i { font-size: 2.5rem; color: var(--text-dim); opacity: 0.4; margin-bottom: 16px; }
    .gallery-empty h3 { font-family: var(--font-hud); font-size: 1rem; font-weight: 700; color: var(--text-soft); margin-bottom: 10px; }
    .gallery-empty p { font-size: 0.875rem; color: var(--text-dim); max-width: 380px; margin: 0 auto; line-height: 1.70; }

    /* ===================== FOOTER ===================== */
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
    .footer-col ul li a:hover { color: var(--prc-violet); padding-left: 4px; text-shadow: 0 0 8px rgba(139,126,255,0.50); }
    .footer-col ul li a:hover i { color: var(--prc-violet); }
    .footer-bottom { padding-top: 24px; border-top: 1px solid rgba(139,126,255,0.12); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; }
    .footer-bottom p { font-family: var(--font-hud); font-size: 0.58rem; color: var(--text-soft); letter-spacing: 0.06em; }
    .footer-bottom-links { display: flex; gap: 22px; }
    .footer-bottom-links a { font-family: var(--font-hud); font-size: 0.58rem; color: var(--text-soft); transition: color 0.2s; letter-spacing: 0.06em; }
    .footer-bottom-links a:hover { color: var(--prc-violet); }

    /* ===================== REVEAL ===================== */
    .reveal { opacity:0; transform:translateY(24px); transition: opacity 0.55s ease, transform 0.55s ease; }
    .reveal.visible { opacity:1; transform:translateY(0); }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 1100px) { .gallery-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 900px)  { .gallery-grid { grid-template-columns: repeat(2, 1fr); } .preview-strip { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 768px) {
      :root { --nav-height: 62px; }
      body { cursor: auto; } button { cursor: pointer; }
      .cursor-dot, .cursor-ring { display: none; }
      .nav-links { display: none; } .nav-hamburger { display: flex; }
      .gallery-grid { grid-template-columns: repeat(2, 1fr); }
      .preview-strip { grid-template-columns: repeat(2, 1fr); }
      .footer-top { grid-template-columns: 1fr 1fr; gap: 36px; }
      .footer-bottom { flex-direction: column; text-align: center; }
      .lb-kbd-hint { display: none; }
      .lightbox-caption-bar { flex-direction: column; gap: 10px; }
      .lightbox-box { max-width: 100%; }
      .gallery-filter-bar { padding: 18px 18px; gap: 14px; }
      .gallery-year-select { min-width: 160px; font-size: 0.72rem; padding: 11px 38px 11px 14px; }
      .filter-pill { font-size: 0.66rem; padding: 9px 16px; }
      .filter-label { font-size: 0.68rem; }
      .filter-divider { display: none; }
    }
    @media (max-width: 520px) {
      :root { --nav-height: 58px; }
      .nav-inner { padding: 0 14px; }
      .gallery-main { padding-left: 16px; padding-right: 16px; }
      .footer-inner { padding-left: 16px; padding-right: 16px; }
      .page-hero-inner { padding: 0 16px; }
      .nav-brand span { display: none; }
      .gallery-grid { grid-template-columns: 1fr; }
      .preview-strip { grid-template-columns: repeat(2, 1fr); }
      .lightbox-overlay { padding: 10px; }
      .lb-btn span { display: none; }
      .gallery-filter-bar { flex-direction: column; align-items: flex-start; padding: 16px; }
      .filter-right { width: 100%; justify-content: flex-end; }
      .filter-pills { gap: 6px; }
      .filter-pill { padding: 8px 14px; font-size: 0.62rem; }
      .gallery-year-select { min-width: 140px; }
    }

    ::-webkit-scrollbar { width: 4px; }
    ::-webkit-scrollbar-track { background: var(--bg-void); }
    ::-webkit-scrollbar-thumb { background: var(--prc-violet); box-shadow: 0 0 8px rgba(139,126,255,0.70); border-radius: 2px; }
  </style>
</head>
<body>

  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>
  <div class="hex-grid" aria-hidden="true"></div>

  <div class="page-wrapper">

    <!-- ===================== NAV ===================== -->
    <?php $activePage = 'gallery'; include 'nav.php'; ?>

    <!-- ===================== PAGE HERO ===================== -->
    <header class="page-hero">
      <div class="page-hero-scan"></div>
      <div class="page-hero-inner">
        <div class="page-hero-eyebrow">
          <div class="page-hero-blink"></div>
          PRC — Competition Archive
        </div>
        <h1 class="page-hero-title">Photo <span class="accent">Gallery</span></h1>
        <p class="page-hero-desc">Explore highlights from past Philippine Robotics Cup competitions — the energy, the robots, and the champions.</p>
        <div class="page-hero-divider" aria-hidden="true">
          <div class="page-hero-divider-line"></div>
          <div class="page-hero-divider-diamond"></div>
          <div class="page-hero-divider-line right"></div>
        </div>
      </div>
    </header>

    <!-- ===================== GALLERY MAIN ===================== -->
    <main class="gallery-main">

      <!-- FILTER BAR -->
      <div class="gallery-filter-bar reveal">
        <div class="filter-left">
          <span class="filter-label">Gallery</span>
          <div class="filter-divider"></div>
          <div class="select-wrap">
            <select class="gallery-year-select" id="yearSelect" aria-label="Filter by year">
              <option value="all">All Years</option>
              <?php foreach ($folders as $f): ?>
              <option value="<?= (int)$f['folder_id'] ?>"><?= htmlspecialchars($f['folder_label'] ?: $f['folder_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filter-pills">
            <button class="filter-pill active" data-year="all">All</button>
            <?php foreach ($folders as $f): ?>
            <button class="filter-pill" data-year="<?= (int)$f['folder_id'] ?>"><?= htmlspecialchars($f['folder_name']) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="filter-right">
          <div class="photo-count-badge"><span id="photoCountDisplay"><?= $total_photos ?></span> Photos</div>
        </div>
      </div>

      <!-- OVERVIEW MODE (default) -->
      <div class="gallery-overview" id="galleryOverview"></div>

      <!-- ALL-YEAR MODE (expanded) -->
      <div class="gallery-all" id="galleryAll">
        <div class="gallery-all-header">
          <button class="back-btn" id="backBtn"><i class="fi fi-rr-angle-left"></i> Back to Overview</button>
          <span class="all-year-label" id="allYearLabel"></span>
        </div>
        <div class="gallery-grid" id="galleryGrid"></div>
      </div>

    </main><!-- /gallery-main -->

    <!-- ===================== FOOTER ===================== -->
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
            <div class="social-links">
              <a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank" rel="noopener" class="social-link" aria-label="Facebook"><i class="fi fi-brands-facebook"></i></a>
            </div>
          </div>
          <nav class="footer-col" aria-label="Competition"><h4>Competition</h4><ul><li><a href="categories.php"><i class="fi fi-rr-angle-right"></i>Categories</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Rules &amp; Guidelines</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Schedule</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Past Events</a></li></ul></nav>
          <nav class="footer-col" aria-label="Participate"><h4>Participate</h4><ul><li><a href="register.php"><i class="fi fi-rr-angle-right"></i>Register Now</a></li><li><a href="shop.php"><i class="fi fi-rr-angle-right"></i>Order Materials</a></li><li><a href="contact.php"><i class="fi fi-rr-angle-right"></i>Contact Us</a></li><li><a href="contact.php"><i class="fi fi-rr-angle-right"></i>FAQ</a></li></ul></nav>
          <nav class="footer-col" aria-label="Resources"><h4>Resources</h4><ul><li><a href="#"><i class="fi fi-rr-angle-right"></i>News &amp; Updates</a></li><li><a href="gallery.php"><i class="fi fi-rr-angle-right"></i>Gallery</a></li><li><a href="index.php#video"><i class="fi fi-rr-angle-right"></i>Videos</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Creotec Philippines</a></li></ul></nav>
        </div>
        <div class="footer-bottom">
          <p>&copy; 2026 Philippine Robotics Cup // Creotec Philippines Inc. All rights reserved.</p>
          <div class="footer-bottom-links"><a href="#">Privacy Policy</a><a href="#">Terms of Use</a></div>
        </div>
      </div>
    </footer>

  </div><!-- /page-wrapper -->

  <!-- ===================== LIGHTBOX ===================== -->
  <div class="lightbox-overlay" id="lightboxOverlay" role="dialog" aria-modal="true" aria-label="Photo viewer">
    <div class="lightbox-box" id="lightboxBox">
      <div class="lightbox-topbar">
        <div class="lightbox-topbar-left">
          <span class="lightbox-id" id="lbId">PRC_2025_001</span>
          <span class="lightbox-year-tag" id="lbYear">2025</span>
        </div>
        <div class="lightbox-topbar-right">
          <button class="lb-btn lb-btn-download" id="lbDownload" title="Download photo">
            <i class="fi fi-rr-download"></i><span>Download</span>
          </button>
          <button class="lb-btn lb-btn-close" id="lbClose" title="Close (Esc)">
            <i class="fi fi-rr-cross-small"></i><span>Close</span>
          </button>
        </div>
      </div>
      <div class="lightbox-img-wrap">
        <img src="" alt="" id="lightboxImg" />
        <button class="lb-nav lb-nav-prev" id="lbPrev" aria-label="Previous photo"><i class="fi fi-rr-angle-left"></i></button>
        <button class="lb-nav lb-nav-next" id="lbNext" aria-label="Next photo"><i class="fi fi-rr-angle-right"></i></button>
      </div>
      <div class="lightbox-caption-bar">
        <div class="lb-caption-text">
          <div class="lb-caption-main" id="lbCaption">—</div>
          <div class="lb-caption-sub"  id="lbMeta">—</div>
        </div>
        <div style="display:flex;align-items:center;gap:24px;">
          <div class="lb-kbd-hint">
            <span class="lb-key">←</span><span class="lb-key">→</span> Navigate
            &nbsp;·&nbsp;
            <span class="lb-key">Esc</span> Close
          </div>
          <div class="lb-nav-counter" id="lbCounter">1 / 1</div>
        </div>
      </div>
    </div>
  </div>

  <script>
    /* ================================================================
       PHP → JS DATA
    ================================================================ */
    var FOLDERS = <?php
      $js_folders = [];
      foreach ($folders as $f) {
          $js_folders[] = [
              'id'    => (int)$f['folder_id'],
              'name'  => $f['folder_name'],
              'label' => $f['folder_label'] ?: $f['folder_name'],
          ];
      }
      echo json_encode($js_folders, JSON_UNESCAPED_UNICODE);
    ?>;

    var PHOTOS_BY_FOLDER = <?php
      $js_photos = [];
      foreach ($photos_by_folder as $fid => $photos) {
          $js_photos[(int)$fid] = array_map(function($p) {
              return [
                  'photo_id'       => (int)$p['photo_id'],
                  'photo_file'     => $p['photo_file'],
                  'photo_caption'  => $p['photo_caption'] ?? '',
                  'photo_category' => $p['photo_category'] ?? '',
                  'folder_name'    => $p['folder_name'],
                  'upload_date'    => '',
              ];
          }, $photos);
      }
      echo json_encode($js_photos, JSON_UNESCAPED_UNICODE);
    ?>;

    /* ================================================================
       STATE
    ================================================================ */
    var state = {
      activeYear: 'all',
      mode: 'overview',
      lightboxIndex: -1,
      currentPhotoSet: [],
    };

    /* ================================================================
       UTILITIES
    ================================================================ */
    function photosForYear(year) {
      if (year === 'all') {
        var all = [];
        FOLDERS.forEach(function(f) {
          if (PHOTOS_BY_FOLDER[f.id]) all = all.concat(PHOTOS_BY_FOLDER[f.id]);
        });
        return all;
      }
      return PHOTOS_BY_FOLDER[year] || [];
    }

    function updateCountBadge() {
      document.getElementById('photoCountDisplay').textContent = photosForYear(state.activeYear).length;
    }

    /* ================================================================
       RENDER — Photo Card
    ================================================================ */
    function buildPhotoCard(photo, index, photoSet, delay) {
      var card = document.createElement('div');
      card.className = 'photo-card card-in';
      card.style.animationDelay = (delay * 0.06) + 's';
      card.setAttribute('role', 'button');
      card.setAttribute('tabindex', '0');
      card.setAttribute('aria-label', photo.photo_caption || 'Gallery photo');

      card.innerHTML =
        '<div class="photo-card-accent"></div>' +
        '<span class="photo-id-badge">PRC_' + photo.folder_name + '_' + String(photo.photo_id).padStart(3,'0') + '</span>' +
        '<span class="photo-expand-icon"><i class="fi fi-rr-expand"></i></span>' +
        '<img src="' + photo.photo_file + '" alt="' + (photo.photo_caption||'') + '" loading="lazy" />' +
        '<div class="photo-card-overlay">' +
          '<div class="photo-card-caption">' + (photo.photo_caption || '—') + '</div>' +
          '<div class="photo-card-meta">' + (photo.photo_category || '') + (photo.photo_category && photo.folder_name ? ' · ' : '') + photo.folder_name + '</div>' +
        '</div>';

      function open() { state.currentPhotoSet = photoSet; openLightbox(index); }
      card.addEventListener('click', open);
      card.addEventListener('keydown', function(e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
      return card;
    }

    /* ================================================================
       RENDER — Overview Mode
    ================================================================ */
    function renderOverview() {
      var overview = document.getElementById('galleryOverview');
      overview.innerHTML = '';
      document.getElementById('galleryAll').classList.remove('active');
      overview.style.display = '';

      var foldersToShow = state.activeYear === 'all'
        ? FOLDERS
        : FOLDERS.filter(function(f) { return String(f.id) === String(state.activeYear); });

      foldersToShow.forEach(function(folder) {
        var photos = PHOTOS_BY_FOLDER[folder.id] || [];
        if (!photos.length) return;
        var preview = photos.slice(0, 5);

        var section = document.createElement('div');
        section.className = 'year-section reveal';

        var hdr = document.createElement('div');
        hdr.className = 'year-section-header';

        var left = document.createElement('div');
        left.className = 'year-section-left';
        left.innerHTML =
          '<span class="year-dot"></span>' +
          '<h2 class="year-title">' + folder.label + '</h2>' +
          '<div class="year-title-line"></div>' +
          '<span class="year-count">' + photos.length + ' photos</span>';

        var seeAll = document.createElement('button');
        seeAll.className = 'see-all-btn';
        seeAll.innerHTML = 'See All <i class="fi fi-rr-arrow-right"></i>';
        (function(fid) { seeAll.addEventListener('click', function() { renderAllYear(fid); }); })(folder.id);

        hdr.appendChild(left);
        hdr.appendChild(seeAll);
        section.appendChild(hdr);

        var strip = document.createElement('div');
        strip.className = 'preview-strip';
        preview.forEach(function(photo, i) { strip.appendChild(buildPhotoCard(photo, i, preview, i)); });
        section.appendChild(strip);
        overview.appendChild(section);
      });

      if (!overview.children.length) {
        overview.innerHTML = '<div class="gallery-empty"><i class="fi fi-rr-images"></i><h3>No Photos Found</h3><p>No photos are available for the selected year.</p></div>';
      }
      observeReveal();
      updateCountBadge();
    }

    /* ================================================================
       RENDER — All-Year Mode
    ================================================================ */
    function renderAllYear(folderId) {
      state.mode = 'all';
      document.getElementById('galleryOverview').style.display = 'none';
      var allDiv = document.getElementById('galleryAll');
      allDiv.classList.add('active');

      var folder = FOLDERS.find(function(f) { return f.id === folderId; });
      document.getElementById('allYearLabel').textContent = (folder ? folder.label : '') + ' — All Photos';

      var photos = PHOTOS_BY_FOLDER[folderId] || [];
      state.currentPhotoSet = photos;

      var grid = document.getElementById('galleryGrid');
      grid.innerHTML = '';
      photos.forEach(function(photo, i) { grid.appendChild(buildPhotoCard(photo, i, photos, i)); });
      window.scrollTo({ top: 0, behavior: 'smooth' });
      updateCountBadge();
    }

    /* ================================================================
       BACK BUTTON
    ================================================================ */
    document.getElementById('backBtn').addEventListener('click', function() {
      state.mode = 'overview';
      document.getElementById('galleryAll').classList.remove('active');
      document.getElementById('galleryOverview').style.display = '';
      updateCountBadge();
    });

    /* ================================================================
       FILTER — year select + pills kept in sync
    ================================================================ */
    function applyFilter(year) {
      state.activeYear = year;
      state.mode = 'overview';
      document.getElementById('yearSelect').value = year;
      document.querySelectorAll('.filter-pill').forEach(function(p) {
        p.classList.toggle('active', String(p.dataset.year) === String(year));
      });
      document.getElementById('galleryAll').classList.remove('active');
      document.getElementById('galleryOverview').style.display = '';
      renderOverview();
    }

    document.getElementById('yearSelect').addEventListener('change', function() { applyFilter(this.value); });
    document.querySelectorAll('.filter-pill').forEach(function(pill) {
      pill.addEventListener('click', function() { applyFilter(pill.dataset.year); });
    });

    /* ================================================================
       LIGHTBOX
    ================================================================ */
    var lbOverlay = document.getElementById('lightboxOverlay');
    var lbImg     = document.getElementById('lightboxImg');
    var lbId      = document.getElementById('lbId');
    var lbYear    = document.getElementById('lbYear');
    var lbCaption = document.getElementById('lbCaption');
    var lbMeta    = document.getElementById('lbMeta');
    var lbCounter = document.getElementById('lbCounter');

    function openLightbox(index) {
      state.lightboxIndex = index;
      showLightboxPhoto(index);
      lbOverlay.classList.add('open');
      document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
      lbOverlay.classList.remove('open');
      document.body.style.overflow = '';
    }
    function showLightboxPhoto(index) {
      var set   = state.currentPhotoSet;
      var photo = set[index];
      if (!photo) return;
      lbImg.src             = photo.photo_file;
      lbImg.alt             = photo.photo_caption || '';
      lbId.textContent      = 'PRC_' + photo.folder_name + '_' + String(photo.photo_id).padStart(3,'0');
      lbYear.textContent    = photo.folder_name;
      lbCaption.textContent = photo.photo_caption || '—';
      lbMeta.textContent    = (photo.photo_category || '') + (photo.photo_category ? '  ·  ' : '') + 'Uploaded ' + photo.upload_date;
      lbCounter.textContent = (index + 1) + ' / ' + set.length;
      state.lightboxIndex   = index;
    }
    function lbNavigate(dir) {
      var set  = state.currentPhotoSet;
      var next = state.lightboxIndex + dir;
      if (next < 0)           next = set.length - 1;
      if (next >= set.length) next = 0;
      showLightboxPhoto(next);
    }

    document.getElementById('lbClose').addEventListener('click', closeLightbox);
    document.getElementById('lbPrev').addEventListener('click', function() { lbNavigate(-1); });
    document.getElementById('lbNext').addEventListener('click', function() { lbNavigate(1); });
    lbOverlay.addEventListener('click', function(e) { if (e.target === lbOverlay) closeLightbox(); });
    document.addEventListener('keydown', function(e) {
      if (!lbOverlay.classList.contains('open')) return;
      if (e.key === 'Escape')     closeLightbox();
      if (e.key === 'ArrowLeft')  lbNavigate(-1);
      if (e.key === 'ArrowRight') lbNavigate(1);
    });

    document.getElementById('lbDownload').addEventListener('click', function() {
      var set   = state.currentPhotoSet;
      var photo = set[state.lightboxIndex];
      if (!photo) return;
      var ext  = photo.photo_file.split('.').pop().split('?')[0] || 'jpg';
      var name = 'PRC_' + photo.folder_name + '_' + photo.photo_id + '.' + ext;
      var a = document.createElement('a');
      a.href = photo.photo_file; a.download = name;
      a.style.display = 'none'; document.body.appendChild(a); a.click(); document.body.removeChild(a);
    });

    /* ================================================================
       CURSOR
    ================================================================ */
    var dot  = document.getElementById('cursorDot');
    var ring = document.getElementById('cursorRing');
    var mx = 0, my = 0, rx = 0, ry = 0;
    document.addEventListener('mousemove', function(e) { mx = e.clientX; my = e.clientY; dot.style.left = mx+'px'; dot.style.top = my+'px'; });
    (function animRing() { rx += (mx-rx)*0.12; ry += (my-ry)*0.12; ring.style.left = rx+'px'; ring.style.top = ry+'px'; requestAnimationFrame(animRing); })();
    function onHover() { ring.classList.add('hovered'); dot.style.background='var(--creo-amber)'; dot.style.boxShadow='var(--glow-orange)'; }
    function onLeave() { ring.classList.remove('hovered'); dot.style.background='var(--prc-violet)'; dot.style.boxShadow='var(--glow-primary)'; }

    /* ================================================================
       REVEAL OBSERVER
    ================================================================ */
    var ro = new IntersectionObserver(function(entries) {
      entries.forEach(function(e) {
        if (e.isIntersecting) { e.target.classList.add('visible'); ro.unobserve(e.target); }
      });
    }, { threshold: 0.06, rootMargin: '0px 0px -20px 0px' });

    function observeReveal() {
      document.querySelectorAll('.reveal:not(.visible)').forEach(function(el) { ro.observe(el); });
      document.querySelectorAll('.photo-card, .see-all-btn, .back-btn, a, button').forEach(function(el) {
        if (!el._prcHover) {
          el._prcHover = true;
          el.addEventListener('mouseenter', onHover);
          el.addEventListener('mouseleave', onLeave);
        }
      });
    }

    /* ================================================================
       PWA — Service Worker
    ================================================================ */
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function() {
        navigator.serviceWorker.register('sw.js').catch(function() {});
      });
    }

    /* ================================================================
       INIT
    ================================================================ */
    renderOverview();
    observeReveal();
  </script>

</body>
</html>