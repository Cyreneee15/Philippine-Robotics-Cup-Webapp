<?php
// PRC-WebApp/contact.php
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'prc_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die('<p style="color:#ff6b6b;padding:40px;font-family:monospace;">DB error: '.htmlspecialchars($conn->connect_error).'</p>');

// ── Pull all contact settings ────────────────────────────────
$cfg = [];
$res = $conn->query("SELECT setting_key, setting_val FROM prc_contact_settings");
if ($res) while ($r = $res->fetch_assoc()) $cfg[$r['setting_key']] = $r['setting_val'];
$conn->close();

// ── Helpers with fallbacks to original values ────────────────
function cs($cfg, $key, $fallback = '') { return htmlspecialchars($cfg[$key] ?? $fallback); }
function cr($cfg, $key, $fallback = '') { return $cfg[$key] ?? $fallback; }

$hero_desc          = cs($cfg, 'hero_desc', 'Got a question about registration, categories, or the competition? Reach out — our team is happy to help you and your school get started.');
$email              = cs($cfg, 'email',           'philippineroboticscup@gmail.com');
$phone              = cs($cfg, 'phone',            '+63 917 771 3961');
$address_line1      = cs($cfg, 'address_line1',   '117 Technology Ave., Laguna Technopark,');
$address_line2      = cs($cfg, 'address_line2',   'Biñan, Laguna 4024, Philippines');
$address_note       = cs($cfg, 'address_note',    'Creotec Philippines Inc. — Primary organizer');
$facebook_url       = cs($cfg, 'facebook_url',    'https://www.facebook.com/profile.php?id=61579706372017');
$facebook_label     = cs($cfg, 'facebook_label',  'Philippine Robotics Cup');
$maps_embed_url     = cs($cfg, 'maps_embed_url',  '');
$maps_directions    = cs($cfg, 'maps_directions_url', '#');
$maps_label         = cs($cfg, 'maps_label',      'Gruppo EMS, Inc.');
$maps_sublabel      = cs($cfg, 'maps_sublabel',   '117 Technology Ave., SEPZ, Biñan, Laguna 4024');
$hours_weekday      = cs($cfg, 'hours_weekday',   '9:00 AM – 5:00 PM');
$hours_saturday     = cs($cfg, 'hours_saturday',  '10:00 AM – 2:00 PM');
$hours_sunday       = cs($cfg, 'hours_sunday',    'Closed');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#8B7EFF" />
  <meta name="description" content="Contact the Philippine Robotics Cup team — reach us via email, phone, or our contact form." />
  <title>Contact Us - Philippine Robotics Cup 2026</title>

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
      --neon-primary: var(--prc-violet);
      --bg-void:      #03020D;
      --bg-deep:      #06051A;
      --border-neon:  rgba(139,126,255,0.22);
      --glow-cyan:    0 0 18px rgba(196,238,255,0.55), 0 0 55px rgba(196,238,255,0.18);
      --glow-orange:  0 0 18px rgba(255,160,48,0.55),  0 0 55px rgba(255,160,48,0.18);
      --glow-primary: 0 0 18px rgba(139,126,255,0.60), 0 0 55px rgba(139,126,255,0.20);
      --glow-sky:     0 0 18px rgba(68,217,255,0.55),  0 0 55px rgba(68,217,255,0.18);
      --text-high:    #F2EEFF;
      --text-mid:     #C8C0F0;
      --text-soft:    #9A90CC;
      --text-dim:     #7068A8;
      --nav-height:   72px;
      --font-hud:     'Orbitron', monospace;
      --font-body:    'Exo 2', sans-serif;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: var(--font-body);
      background: var(--bg-void);
      color: var(--text-high);
      overflow-x: hidden;
      line-height: 1.6;
      cursor: none;
    }
    img { max-width: 100%; display: block; }
    a { text-decoration: none; color: inherit; }
    ul { list-style: none; }
    button { font-family: inherit; cursor: none; border: none; background: none; }
    input, textarea, select { font-family: inherit; }

    /* ===== CUSTOM CURSOR ===== */
    .cursor-dot {
      position: fixed; width: 8px; height: 8px; border-radius: 50%;
      background: var(--neon-primary); pointer-events: none; z-index: 99999;
      transform: translate(-50%,-50%);
      box-shadow: var(--glow-primary);
      transition: transform 0.1s, background 0.2s;
    }
    .cursor-ring {
      position: fixed; width: 36px; height: 36px; border-radius: 50%;
      border: 1px solid rgba(139,126,255,0.65); pointer-events: none; z-index: 99998;
      transform: translate(-50%,-50%);
      transition: width 0.25s, height 0.25s, border-color 0.25s, transform 0.08s;
    }
    .cursor-ring.hovered { width: 56px; height: 56px; border-color: var(--creo-amber); border-width: 1.5px; }

    /* ===== SCANLINES ===== */
    body::after {
      content: ''; position: fixed; inset: 0; z-index: 9998; pointer-events: none;
      background: repeating-linear-gradient(to bottom, transparent, transparent 2px, rgba(0,0,0,0.04) 2px, rgba(0,0,0,0.04) 4px);
    }

    /* ===== HEX GRID ===== */
    .hex-grid {
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background-image: linear-gradient(rgba(139,126,255,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(139,126,255,0.04) 1px, transparent 1px);
      background-size: 50px 50px;
    }
    .hex-grid::before {
      content: ''; position: absolute; inset: 0;
      background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(119,51,255,0.14) 0%, transparent 70%),
                  radial-gradient(ellipse 60% 50% at 100% 100%, rgba(204,85,255,0.07) 0%, transparent 60%),
                  radial-gradient(ellipse 50% 50% at 0% 80%, rgba(139,126,255,0.09) 0%, transparent 60%);
    }

    /* ===== ANIMATIONS ===== */
    @keyframes neonPulse { 0%,100% { opacity:1; } 50% { opacity:0.6; } }
    @keyframes fadeInUp { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }
    @keyframes fadeIn   { from { opacity:0; } to { opacity:1; } }
    @keyframes scanDown { from { transform:translateY(-100%); } to { transform:translateY(100vh); } }
    @keyframes slideInLeft  { from { opacity:0; transform:translateX(-30px); } to { opacity:1; transform:translateX(0); } }
    @keyframes slideInRight { from { opacity:0; transform:translateX(30px);  } to { opacity:1; transform:translateX(0); } }

    .page-wrapper { position: relative; z-index: 1; }

    /* ===== NAV ===== */
    #main-nav {
      position: fixed; top: 0; left: 0; right: 0;
      height: var(--nav-height); z-index: 1000;
      background: rgba(3,2,13,0.94);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border-neon);
      box-shadow: 0 0 30px rgba(139,126,255,0.10);
    }
    .nav-inner {
      max-width: 1340px; margin: 0 auto; height: 100%; padding: 0 36px;
      display: flex; align-items: center; justify-content: space-between; gap: 16px;
    }
    .nav-logo { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
    .nav-logo img { height: 38px; width: auto; transition: filter 0.3s; }
    .nav-logo:hover img { filter: drop-shadow(0 0 14px rgba(139,126,255,0.75)); }
    .nav-brand { font-family: var(--font-hud); font-weight: 700; font-size: 0.72rem; letter-spacing: 0.06em; line-height: 1.3; color: var(--prc-violet); text-shadow: 0 0 12px rgba(139,126,255,0.65); }
    .nav-brand span { color: var(--text-soft); display: block; font-size: 0.58rem; font-weight: 400; letter-spacing: 0.10em; text-transform: uppercase; margin-top: 1px; }
    .nav-links { display: flex; align-items: center; gap: 2px; }
    .nav-links a {
      font-family: var(--font-hud); font-size: 0.65rem; font-weight: 600;
      color: var(--text-mid); padding: 8px 14px; letter-spacing: 0.08em; text-transform: uppercase;
      border-radius: 4px; transition: all 0.2s; position: relative; white-space: nowrap;
    }
    .nav-links a:hover { color: var(--prc-violet); text-shadow: 0 0 12px rgba(139,126,255,0.85); }
    .nav-links a.active { color: var(--prc-violet); text-shadow: 0 0 12px rgba(139,126,255,0.85); }
    .nav-links a.active::after { content: ''; position: absolute; bottom: 4px; left: 14px; right: 14px; height: 1px; background: var(--prc-violet); box-shadow: 0 0 6px rgba(139,126,255,0.80); }
    .nav-cta {
      background: transparent !important; border: 1px solid var(--prc-violet) !important;
      color: var(--prc-violet) !important; padding: 8px 20px !important; border-radius: 3px !important;
      box-shadow: 0 0 15px rgba(139,126,255,0.28), inset 0 0 15px rgba(139,126,255,0.06) !important;
      transition: all 0.25s !important; margin-left: 8px;
      clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
    }
    .nav-cta:hover { background: rgba(139,126,255,0.12) !important; box-shadow: 0 0 30px rgba(139,126,255,0.52), inset 0 0 20px rgba(139,126,255,0.10) !important; color: #fff !important; }
    .nav-hamburger {
      display: none; flex-direction: column; justify-content: center; align-items: center;
      gap: 5px; width: 44px; height: 44px; padding: 0; cursor: none;
      background: rgba(139,126,255,0.06); border: 1px solid var(--border-neon);
      border-radius: 4px; flex-shrink: 0; z-index: 1002;
      -webkit-tap-highlight-color: transparent; touch-action: manipulation; transition: all 0.2s;
    }
    .nav-hamburger:hover { background: rgba(139,126,255,0.14); box-shadow: 0 0 14px rgba(139,126,255,0.28); }
    .nav-hamburger span { width: 20px; height: 1.5px; background: var(--prc-violet); border-radius: 2px; transition: transform 0.28s, opacity 0.28s; display: block; pointer-events: none; }
    .nav-hamburger.open span:nth-child(1) { transform: rotate(45deg) translate(5px,5px); }
    .nav-hamburger.open span:nth-child(2) { opacity: 0; }
    .nav-hamburger.open span:nth-child(3) { transform: rotate(-45deg) translate(5px,-5px); }
    .nav-mobile {
      display: none; position: fixed; top: var(--nav-height); left: 0; right: 0;
      background: rgba(3,2,13,0.98); backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border-neon); padding: 12px 18px 24px; z-index: 1000;
      flex-direction: column; gap: 2px; box-shadow: 0 20px 60px rgba(139,126,255,0.09);
    }
    .nav-mobile.open { display: flex; }
    .nav-mobile a {
      font-family: var(--font-hud); font-size: 0.70rem; font-weight: 600;
      color: var(--text-mid); padding: 13px 14px; border-radius: 3px;
      letter-spacing: 0.08em; text-transform: uppercase; transition: all 0.2s;
      display: flex; align-items: center; gap: 12px;
    }
    .nav-mobile a i { font-size: 1rem; color: var(--prc-violet); }
    .nav-mobile a:hover, .nav-mobile a.active { color: var(--prc-violet); background: rgba(139,126,255,0.07); text-shadow: 0 0 10px rgba(139,126,255,0.55); }
    .nav-mobile .nav-cta { border: 1px solid var(--prc-violet) !important; color: var(--prc-violet) !important; margin-top: 10px; justify-content: center; border-radius: 3px !important; clip-path: none !important; }

    /* ===== PAGE HERO ===== */
    .page-hero {
      position: relative; padding: calc(var(--nav-height) + 72px) 0 72px;
      overflow: hidden; text-align: center;
    }
    .page-hero::before {
      content: ''; position: absolute; inset: 0;
      background: radial-gradient(ellipse 70% 70% at 50% 0%, rgba(68,217,255,0.10) 0%, transparent 70%),
                  linear-gradient(to bottom, rgba(3,2,13,0) 60%, var(--bg-void) 100%);
    }
    .page-hero-scan { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
    .page-hero-scan::after {
      content: ''; position: absolute; left: 0; right: 0; height: 1px;
      background: linear-gradient(90deg, transparent, var(--creo-sky), transparent);
      animation: scanDown 8s linear infinite;
      box-shadow: 0 0 14px rgba(68,217,255,0.55);
    }
    .page-hero-inner { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; padding: 0 36px; }
    .page-hero-eyebrow {
      display: inline-flex; align-items: center; gap: 10px;
      font-family: var(--font-hud); font-size: 0.60rem; font-weight: 600;
      letter-spacing: 0.20em; text-transform: uppercase; color: var(--creo-sky);
      margin-bottom: 18px; animation: fadeIn 0.8s ease both;
    }
    .page-hero-eyebrow::before { content: '//'; color: rgba(68,217,255,0.40); font-size: 0.70rem; }
    .page-hero-blink { width: 6px; height: 6px; background: var(--creo-sky); border-radius: 50%; box-shadow: var(--glow-sky); animation: neonPulse 1.2s ease-in-out infinite; }
    .page-hero-title {
      font-family: var(--font-hud); font-size: clamp(2.2rem, 6vw, 4rem);
      font-weight: 900; letter-spacing: -0.01em; line-height: 1.0;
      color: #fff; margin-bottom: 18px;
      text-shadow: 0 0 40px rgba(68,217,255,0.20);
      animation: fadeInUp 0.8s ease 0.1s both;
    }
    .page-hero-title .accent-sky { color: var(--creo-sky); text-shadow: 0 0 22px rgba(68,217,255,0.65); }
    .page-hero-desc {
      font-size: 1rem; color: var(--text-mid); line-height: 1.78; max-width: 520px; margin: 0 auto;
      animation: fadeInUp 0.8s ease 0.2s both;
    }
    .page-hero-divider {
      display: flex; align-items: center; justify-content: center; gap: 14px;
      margin-top: 36px; animation: fadeIn 0.8s ease 0.3s both;
    }
    .page-hero-divider-line { width: 80px; height: 1px; background: linear-gradient(90deg, transparent, rgba(68,217,255,0.40)); }
    .page-hero-divider-line.right { background: linear-gradient(90deg, rgba(68,217,255,0.40), transparent); }
    .page-hero-divider-diamond { width: 8px; height: 8px; background: var(--creo-sky); transform: rotate(45deg); box-shadow: var(--glow-sky); }

    /* ===== MAIN CONTENT ===== */
    .contact-main {
      max-width: 1340px; margin: 0 auto; padding: 0 36px 0;
      display: grid; grid-template-columns: 1fr 1.35fr; gap: 40px; align-items: start;
    }
    .contact-map-section { max-width: 1340px; margin: 0 auto; padding: 0 36px 100px; }

    /* ===== LEFT: CONTACT INFO ===== */
    .contact-info { animation: slideInLeft 0.8s ease 0.2s both; }
    .contact-info-header { margin-bottom: 36px; }
    .contact-info-header h2 { font-family: var(--font-hud); font-size: 1.05rem; font-weight: 700; letter-spacing: 0.06em; color: var(--text-high); margin-bottom: 10px; }
    .contact-info-header p { font-size: 0.90rem; color: var(--text-mid); line-height: 1.72; }

    .contact-cards { display: flex; flex-direction: column; gap: 14px; margin-bottom: 40px; }
    .contact-card {
      display: flex; align-items: flex-start; gap: 18px;
      padding: 22px 24px;
      background: rgba(139,126,255,0.04); border: 1px solid rgba(139,126,255,0.14);
      border-left: 2px solid var(--prc-violet);
      transition: all 0.3s; position: relative; overflow: hidden;
    }
    .contact-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, var(--prc-violet), transparent); opacity: 0; transition: opacity 0.3s; }
    .contact-card:hover { background: rgba(139,126,255,0.08); border-color: rgba(139,126,255,0.30); border-left-color: var(--creo-sky); transform: translateX(4px); box-shadow: 0 0 20px rgba(139,126,255,0.10); }
    .contact-card:hover::before { opacity: 1; }
    .contact-card-icon {
      width: 46px; height: 46px; flex-shrink: 0;
      background: rgba(139,126,255,0.10); border: 1px solid rgba(139,126,255,0.28);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem; color: var(--prc-violet); transition: all 0.3s;
    }
    .contact-card:hover .contact-card-icon { background: rgba(68,217,255,0.10); border-color: rgba(68,217,255,0.40); color: var(--creo-sky); box-shadow: var(--glow-sky); }
    .contact-card-body { flex: 1; }
    .contact-card-label { font-family: var(--font-hud); font-size: 0.52rem; text-transform: uppercase; letter-spacing: 0.16em; color: var(--text-soft); margin-bottom: 5px; }
    .contact-card-value { font-size: 0.95rem; color: var(--text-high); font-weight: 500; line-height: 1.5; word-break: break-word; }
    .contact-card-value a { color: var(--text-high); transition: color 0.2s; }
    .contact-card-value a:hover { color: var(--creo-sky); text-shadow: 0 0 8px rgba(68,217,255,0.50); }
    .contact-card-note { font-size: 0.78rem; color: var(--text-soft); margin-top: 4px; }

    .contact-social-row { margin-bottom: 40px; }
    .contact-social-label { font-family: var(--font-hud); font-size: 0.54rem; text-transform: uppercase; letter-spacing: 0.14em; color: var(--text-soft); margin-bottom: 14px; }
    .contact-social-links { display: flex; gap: 10px; }
    .contact-social-link {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 18px;
      background: rgba(139,126,255,0.05); border: 1px solid rgba(139,126,255,0.20);
      font-family: var(--font-hud); font-size: 0.58rem; font-weight: 600;
      letter-spacing: 0.08em; text-transform: uppercase;
      color: var(--text-mid); transition: all 0.25s;
    }
    .contact-social-link i { font-size: 1rem; color: var(--prc-violet); }
    .contact-social-link:hover { background: rgba(139,126,255,0.12); border-color: var(--prc-violet); color: var(--prc-violet); box-shadow: 0 0 14px rgba(139,126,255,0.30); }

    .contact-hours-card {
      background: rgba(68,217,255,0.04); border: 1px solid rgba(68,217,255,0.18);
      padding: 22px 24px; position: relative; overflow: hidden;
    }
    .contact-hours-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, var(--creo-sky), transparent); }
    .contact-hours-title {
      font-family: var(--font-hud); font-size: 0.60rem; font-weight: 700;
      letter-spacing: 0.14em; text-transform: uppercase;
      color: var(--creo-sky); margin-bottom: 14px;
      display: flex; align-items: center; gap: 8px;
    }
    .contact-hours-title .status-dot { width: 7px; height: 7px; border-radius: 50%; background: #44FF88; box-shadow: 0 0 8px rgba(68,255,136,0.70); animation: neonPulse 1.8s ease-in-out infinite; }
    .contact-hours-grid { display: flex; flex-direction: column; gap: 8px; }
    .contact-hours-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.84rem; }
    .contact-hours-day { color: var(--text-soft); }
    .contact-hours-time { color: var(--text-mid); font-family: var(--font-hud); font-size: 0.68rem; letter-spacing: 0.06em; }
    .contact-hours-time.available { color: var(--creo-sky); }

    /* ===== RIGHT: FORM ===== */
    .contact-form-wrap { animation: slideInRight 0.8s ease 0.3s both; }
    .contact-form-card {
      background: rgba(139,126,255,0.03); border: 1px solid var(--border-neon);
      position: relative; overflow: hidden;
      box-shadow: 0 0 50px rgba(139,126,255,0.08), inset 0 0 50px rgba(139,126,255,0.02);
    }
    .contact-form-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--creo-sky), var(--prc-violet), transparent); }
    .contact-form-card::after { content: ''; position: absolute; bottom: 0; right: 0; width: 24px; height: 24px; border-right: 1px solid rgba(139,126,255,0.40); border-bottom: 1px solid rgba(139,126,255,0.40); }
    .form-card-header {
      padding: 28px 36px 24px; border-bottom: 1px solid var(--border-neon);
      background: rgba(139,126,255,0.05);
      display: flex; align-items: center; justify-content: space-between;
    }
    .form-card-header-left h3 { font-family: var(--font-hud); font-size: 0.82rem; font-weight: 700; letter-spacing: 0.06em; color: var(--prc-violet); text-shadow: 0 0 12px rgba(139,126,255,0.55); }
    .form-card-header-left p { font-size: 0.80rem; color: var(--text-soft); margin-top: 3px; }

    .contact-form { padding: 32px 36px 36px; display: flex; flex-direction: column; gap: 22px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-group { display: flex; flex-direction: column; gap: 8px; }
    .form-label { font-family: var(--font-hud); font-size: 0.54rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--text-soft); display: flex; align-items: center; gap: 6px; }
    .form-label .required { color: #FF6B6B; }

    .form-input, .form-textarea, .form-select {
      width: 100%; background: rgba(139,126,255,0.05);
      border: 1px solid rgba(139,126,255,0.22);
      color: var(--text-high); font-family: var(--font-body); font-size: 0.90rem;
      padding: 12px 16px; outline: none;
      transition: border-color 0.25s, background 0.25s, box-shadow 0.25s;
      appearance: none; -webkit-appearance: none;
    }
    .form-input::placeholder, .form-textarea::placeholder { color: var(--text-dim); }
    .form-input:focus, .form-textarea:focus, .form-select:focus { border-color: var(--prc-violet); background: rgba(139,126,255,0.09); box-shadow: 0 0 0 1px rgba(139,126,255,0.35), 0 0 20px rgba(139,126,255,0.12); }
    .form-input:hover, .form-textarea:hover, .form-select:hover { border-color: rgba(139,126,255,0.40); }
    .form-textarea { resize: vertical; min-height: 130px; line-height: 1.65; }

    .form-select-wrap { position: relative; }
    .form-select-wrap::after { content: ''; position: absolute; right: 14px; top: 50%; transform: translateY(-50%); width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-top: 6px solid var(--text-soft); pointer-events: none; }
    .form-select option { background: #0D0C1E; color: var(--text-high); }

    .form-count { font-family: var(--font-hud); font-size: 0.50rem; color: var(--text-dim); text-align: right; letter-spacing: 0.08em; margin-top: -4px; }
    .form-count.warn { color: var(--creo-amber); }

    .form-consent { display: flex; align-items: flex-start; gap: 12px; }
    .form-checkbox { width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px; background: rgba(139,126,255,0.08); border: 1px solid rgba(139,126,255,0.35); appearance: none; -webkit-appearance: none; cursor: pointer; position: relative; transition: all 0.2s; }
    .form-checkbox:checked { background: var(--prc-violet); border-color: var(--prc-violet); }
    .form-checkbox:checked::after { content: ''; position: absolute; top: 2px; left: 5px; width: 5px; height: 9px; border-right: 2px solid #fff; border-bottom: 2px solid #fff; transform: rotate(45deg); }
    .form-checkbox:focus { box-shadow: 0 0 0 2px rgba(139,126,255,0.40); }
    .form-consent-text { font-size: 0.82rem; color: var(--text-soft); line-height: 1.60; }
    .form-consent-text a { color: var(--prc-violet); text-decoration: underline; text-underline-offset: 3px; }
    .form-consent-text a:hover { color: var(--prc-ice); }

    .form-submit-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; }
    .btn-submit {
      display: inline-flex; align-items: center; gap: 10px;
      background: transparent; color: var(--prc-violet);
      padding: 14px 36px; font-family: var(--font-hud); font-size: 0.70rem;
      font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
      border: 1px solid var(--prc-violet); cursor: pointer;
      clip-path: polygon(10px 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%);
      box-shadow: 0 0 18px rgba(139,126,255,0.32), inset 0 0 18px rgba(139,126,255,0.07);
      transition: all 0.25s; position: relative; overflow: hidden;
    }
    .btn-submit::before { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(139,126,255,0.18), transparent); transform: translateX(-100%); transition: transform 0.5s; }
    .btn-submit:hover { background: rgba(139,126,255,0.12); box-shadow: 0 0 38px rgba(139,126,255,0.60), inset 0 0 28px rgba(139,126,255,0.12); color: #fff; transform: translateY(-2px); }
    .btn-submit:hover::before { transform: translateX(100%); }
    .btn-submit:disabled { opacity: 0.55; cursor: not-allowed; transform: none; box-shadow: 0 0 10px rgba(139,126,255,0.15); }
    .form-submit-note { font-family: var(--font-hud); font-size: 0.52rem; color: var(--text-dim); letter-spacing: 0.08em; display: flex; align-items: center; gap: 7px; }
    .form-submit-note::before { content: '//'; color: rgba(139,126,255,0.30); }

    /* ===== FORM TOAST ===== */
    .form-toast { display: none; padding: 14px 20px; border: 1px solid; font-family: var(--font-hud); font-size: 0.65rem; letter-spacing: 0.06em; line-height: 1.6; position: relative; overflow: hidden; }
    .form-toast.show { display: flex; align-items: flex-start; gap: 12px; }
    .form-toast.success { background: rgba(68,255,136,0.06); border-color: rgba(68,255,136,0.35); color: #44FF88; }
    .form-toast.error { background: rgba(255,80,80,0.06); border-color: rgba(255,80,80,0.35); color: #FF6B6B; }
    .form-toast i { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }

    /* ===== FAQ ===== */
    .contact-faq { max-width: 1340px; margin: 0 auto; padding: 0 36px 100px; }
    .contact-faq-header { margin-bottom: 32px; }
    .contact-faq-header h2 { font-family: var(--font-hud); font-size: clamp(1.4rem, 2.5vw, 1.9rem); font-weight: 800; color: #fff; }
    .contact-faq-header h2 .accent { color: var(--prc-violet); text-shadow: 0 0 16px rgba(139,126,255,0.60); }
    .faq-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .faq-item { background: rgba(139,126,255,0.03); border: 1px solid rgba(139,126,255,0.14); padding: 22px 24px; transition: all 0.3s; }
    .faq-item:hover { background: rgba(139,126,255,0.07); border-color: rgba(139,126,255,0.30); box-shadow: 0 0 18px rgba(139,126,255,0.08); }
    .faq-q { font-family: var(--font-hud); font-size: 0.70rem; font-weight: 700; letter-spacing: 0.04em; color: var(--prc-ice); margin-bottom: 9px; display: flex; align-items: flex-start; gap: 10px; }
    .faq-q::before { content: 'Q//'; color: var(--prc-violet); font-size: 0.62rem; flex-shrink: 0; margin-top: 1px; }
    .faq-a { font-size: 0.875rem; color: var(--text-mid); line-height: 1.72; padding-left: 30px; }

    /* ===== MAP ===== */
    .contact-map-section { margin-top: 40px; }
    .contact-map-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 10px; }
    .contact-map-label { font-family: var(--font-hud); font-size: 0.58rem; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: var(--text-soft); display: flex; align-items: center; gap: 8px; }
    .contact-map-label i { color: var(--prc-violet); font-size: 0.85rem; }
    .contact-map-directions { display: inline-flex; align-items: center; gap: 7px; font-family: var(--font-hud); font-size: 0.56rem; font-weight: 700; letter-spacing: 0.10em; text-transform: uppercase; color: var(--creo-sky); border: 1px solid rgba(68,217,255,0.32); padding: 7px 18px; transition: all 0.2s; background: rgba(68,217,255,0.04); }
    .contact-map-directions:hover { background: rgba(68,217,255,0.12); border-color: var(--creo-sky); box-shadow: 0 0 16px rgba(68,217,255,0.30); color: #fff; }
    .contact-map-outer { padding: 2px; background: linear-gradient(135deg, rgba(139,126,255,0.70), rgba(68,217,255,0.45), rgba(119,51,255,0.60)); box-shadow: 0 0 50px rgba(139,126,255,0.18), 0 0 100px rgba(68,217,255,0.08); position: relative; }
    .contact-map-outer::before { content: ''; position: absolute; inset: -3px; background: linear-gradient(135deg, var(--prc-violet), var(--creo-sky), var(--creo-purple)); filter: blur(14px); opacity: 0.25; z-index: -1; }
    .contact-map-frame { position: relative; width: 100%; height: 440px; overflow: hidden; background: #050412; }
    .contact-map-frame iframe { width: 100%; height: 100%; display: block; filter: invert(0.9) hue-rotate(180deg) saturate(0.65) brightness(0.82); transition: filter 0.4s; border: 0; }
    .contact-map-frame:hover iframe { filter: invert(0.85) hue-rotate(180deg) saturate(0.85) brightness(0.90); }
    .map-corner { position: absolute; width: 22px; height: 22px; border-color: var(--prc-ice); border-style: solid; pointer-events: none; z-index: 3; }
    .map-corner.tl { top: 8px; left: 8px;    border-width: 2px 0 0 2px; }
    .map-corner.tr { top: 8px; right: 8px;   border-width: 2px 2px 0 0; }
    .map-corner.bl { bottom: 8px; left: 8px;  border-width: 0 0 2px 2px; }
    .map-corner.br { bottom: 8px; right: 8px; border-width: 0 2px 2px 0; }
    .map-scan { position: absolute; inset: 0; pointer-events: none; overflow: hidden; z-index: 3; }
    .map-scan::after { content: ''; position: absolute; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(139,126,255,0.40), rgba(68,217,255,0.55), transparent); animation: mapScan 8s linear infinite; }
    @keyframes mapScan { from { transform: translateY(-10px); } to { transform: translateY(440px); } }
    .map-address-bar { position: absolute; bottom: 0; left: 0; right: 0; z-index: 4; padding: 12px 20px; background: linear-gradient(to top, rgba(3,2,13,0.92), transparent); display: flex; align-items: center; gap: 12px; pointer-events: none; }
    .map-address-icon { width: 32px; height: 32px; flex-shrink: 0; background: rgba(139,126,255,0.15); border: 1px solid rgba(139,126,255,0.45); display: flex; align-items: center; justify-content: center; color: var(--prc-violet); font-size: 0.85rem; }
    .map-address-text { display: flex; flex-direction: column; gap: 1px; }
    .map-address-name { font-family: var(--font-hud); font-size: 0.60rem; font-weight: 700; color: var(--text-high); letter-spacing: 0.06em; text-shadow: 0 0 10px rgba(139,126,255,0.50); }
    .map-address-sub { font-size: 0.75rem; color: var(--text-soft); }

    /* ===== FOOTER ===== */
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

    /* ===== REVEAL ===== */
    .reveal { opacity:0; transform:translateY(28px); transition: opacity 0.60s ease, transform 0.60s ease; }
    .reveal.visible { opacity:1; transform:translateY(0); }
    .reveal-delay-1 { transition-delay: 0.10s; }
    .reveal-delay-2 { transition-delay: 0.20s; }
    .reveal-delay-3 { transition-delay: 0.30s; }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 1024px) {
      .contact-main { grid-template-columns: 1fr; gap: 32px; }
      .faq-grid { grid-template-columns: 1fr; }
      .footer-top { grid-template-columns: 1fr 1fr; gap: 36px; }
      .contact-map-frame { height: 340px; }
    }
    @media (max-width: 768px) {
      :root { --nav-height: 62px; }
      body { cursor: auto; } button { cursor: pointer; }
      .cursor-dot, .cursor-ring { display: none; }
      .nav-links { display: none; } .nav-hamburger { display: flex; }
      .form-row { grid-template-columns: 1fr; }
      .contact-form { padding: 24px 22px 28px; }
      .form-card-header { padding: 22px 22px 18px; }
      .footer-top { grid-template-columns: 1fr; gap: 32px; }
      .footer-bottom { flex-direction: column; text-align: center; }
      .contact-map-frame { height: 280px; }
    }
    @media (max-width: 520px) {
      :root { --nav-height: 58px; }
      .nav-inner { padding: 0 14px; }
      .contact-main, .contact-faq, .contact-map-section { padding-left: 16px; padding-right: 16px; }
      .page-hero-inner { padding: 0 16px; }
      .footer-inner { padding: 0 16px; }
      .nav-brand span { display: none; }
      .btn-submit { clip-path: none; }
      .contact-map-frame { height: 240px; }
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

    <!-- NAV -->
    <?php $activePage = 'contact'; include 'nav.php'; ?>

    <!-- PAGE HERO -->
    <header class="page-hero">
      <div class="page-hero-scan"></div>
      <div class="page-hero-inner">
        <div class="page-hero-eyebrow">
          <div class="page-hero-blink"></div>
          PRC 2026 &mdash; Support &amp; Inquiries
        </div>
        <h1 class="page-hero-title">Contact <span class="accent-sky">Us</span></h1>
        <p class="page-hero-desc"><?= $hero_desc ?></p>
        <div class="page-hero-divider" aria-hidden="true">
          <div class="page-hero-divider-line"></div>
          <div class="page-hero-divider-diamond"></div>
          <div class="page-hero-divider-line right"></div>
        </div>
      </div>
    </header>

    <!-- MAIN: INFO + FORM -->
    <main>
      <div class="contact-main">

        <!-- LEFT: Contact Info -->
        <div class="contact-info">
          <div class="contact-info-header">
            <h2>Reach Out Directly</h2>
            <p>Whether you prefer email, phone, or social media — we're available across multiple channels. Choose what's most convenient for you.</p>
          </div>

          <div class="contact-cards">
            <div class="contact-card">
              <div class="contact-card-icon"><i class="fi fi-rr-envelope"></i></div>
              <div class="contact-card-body">
                <div class="contact-card-label">Email Address</div>
                <div class="contact-card-value">
                  <a href="mailto:<?= $email ?>"><?= $email ?></a>
                </div>
                <div class="contact-card-note">Best for detailed questions and documents</div>
              </div>
            </div>

            <div class="contact-card">
              <div class="contact-card-icon"><i class="fi fi-rr-phone-call"></i></div>
              <div class="contact-card-body">
                <div class="contact-card-label">Phone &amp; Viber</div>
                <div class="contact-card-value">
                  <a href="tel:<?= preg_replace('/\s+/', '', $cfg['phone'] ?? '') ?>"><?= $phone ?></a>
                </div>
                <div class="contact-card-note">Available Mon – Fri, 9 AM – 5 PM</div>
              </div>
            </div>

            <div class="contact-card">
              <div class="contact-card-icon"><i class="fi fi-rr-map-marker"></i></div>
              <div class="contact-card-body">
                <div class="contact-card-label">Office Address</div>
                <div class="contact-card-value"><?= $address_line1 ?><br/><?= $address_line2 ?></div>
                <div class="contact-card-note"><?= $address_note ?></div>
              </div>
            </div>
          </div>

          <div class="contact-social-row">
            <div class="contact-social-label">Follow Us</div>
            <div class="contact-social-links">
              <a href="<?= $facebook_url ?>" target="_blank" rel="noopener" class="contact-social-link">
                <i class="fi fi-brands-facebook"></i> <?= $facebook_label ?>
              </a>
            </div>
          </div>

          <div class="contact-hours-card">
            <div class="contact-hours-title">
              <span class="status-dot"></span>
              Response Hours
            </div>
            <div class="contact-hours-grid">
              <div class="contact-hours-row">
                <span class="contact-hours-day">Monday – Friday</span>
                <span class="contact-hours-time<?= ($hours_weekday !== 'Closed') ? ' available' : '' ?>"><?= $hours_weekday ?></span>
              </div>
              <div class="contact-hours-row">
                <span class="contact-hours-day">Saturday</span>
                <span class="contact-hours-time<?= ($hours_saturday !== 'Closed') ? ' available' : '' ?>"><?= $hours_saturday ?></span>
              </div>
              <div class="contact-hours-row">
                <span class="contact-hours-day">Sunday &amp; Holidays</span>
                <span class="contact-hours-time"><?= $hours_sunday ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT: Contact Form -->
        <div class="contact-form-wrap">
          <div class="contact-form-card">
            <div class="form-card-header">
              <div class="form-card-header-left">
                <h3>Send Us a Message</h3>
                <p>Fill out the form below and we'll respond promptly.</p>
              </div>
            </div>

            <div class="form-toast" id="formToast" role="alert" aria-live="polite">
              <i class="fi" id="toastIcon"></i>
              <span id="toastMsg"></span>
            </div>

            <form class="contact-form" id="contactForm" novalidate>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="firstName">First Name <span class="required">*</span></label>
                  <input class="form-input" type="text" id="firstName" name="firstName" placeholder="e.g. Maria" autocomplete="given-name" required />
                </div>
                <div class="form-group">
                  <label class="form-label" for="lastName">Last Name <span class="required">*</span></label>
                  <input class="form-input" type="text" id="lastName" name="lastName" placeholder="e.g. Santos" autocomplete="family-name" required />
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                  <input class="form-input" type="email" id="email" name="email" placeholder="you@example.com" autocomplete="email" required />
                </div>
                <div class="form-group">
                  <label class="form-label" for="phone">Phone / Viber</label>
                  <input class="form-input" type="tel" id="phone" name="phone" placeholder="+63 9XX XXX XXXX" autocomplete="tel" />
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="school">School / Organization</label>
                  <input class="form-input" type="text" id="school" name="school" placeholder="School name (optional)" />
                </div>
                <div class="form-group">
                  <label class="form-label" for="role">Your Role <span class="required">*</span></label>
                  <div class="form-select-wrap">
                    <select class="form-select" id="role" name="role" required>
                      <option value="" disabled selected>Select a role…</option>
                      <option value="student">Student</option>
                      <option value="teacher">Teacher / Coach</option>
                      <option value="coordinator">School Coordinator</option>
                      <option value="parent">Parent / Guardian</option>
                      <option value="sponsor">Sponsor / Partner</option>
                      <option value="media">Media / Press</option>
                      <option value="other">Other</option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="subject">Subject <span class="required">*</span></label>
                <div class="form-select-wrap">
                  <select class="form-select" id="subject" name="subject" required>
                    <option value="" disabled selected>What is your inquiry about?</option>
                    <option value="registration">Registration &amp; Team Sign-Up</option>
                    <option value="categories">Competition Categories &amp; Rules</option>
                    <option value="materials">Ordering Materials &amp; Kits</option>
                    <option value="schedule">Schedule &amp; Venue</option>
                    <option value="makex">MakeX International Qualifier</option>
                    <option value="sponsorship">Sponsorship &amp; Partnership</option>
                    <option value="media">Media &amp; Press Inquiry</option>
                    <option value="other">Other / General Question</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="message">Message <span class="required">*</span></label>
                <textarea class="form-textarea" id="message" name="message" placeholder="Tell us how we can help you…" maxlength="1000" required></textarea>
                <div class="form-count" id="charCount">0 / 1000</div>
              </div>

              <div class="form-consent">
                <input type="checkbox" class="form-checkbox" id="consent" name="consent" required />
                <label for="consent" class="form-consent-text">
                  I agree that my submitted data will be used to respond to my inquiry. I have read and accept the <a href="#">Privacy Policy</a>.
                </label>
              </div>

              <div class="form-submit-row">
                <button type="submit" class="btn-submit" id="submitBtn">
                  <i class="fi fi-rr-paper-plane" id="submitIcon"></i>
                  <span id="submitLabel">Send Message</span>
                </button>
                <span class="form-submit-note" id="submitNote">All fields marked * are required</span>
              </div>
            </form>
          </div>
        </div>

      </div><!-- /contact-main -->

      <!-- FULL-WIDTH MAP -->
      <div class="contact-map-section reveal">
        <div class="contact-map-header">
          <span class="contact-map-label">
            <i class="fi fi-rr-map-marker"></i>
            Find Us — <?= $maps_label ?>
          </span>
          <a href="<?= $maps_directions ?>" target="_blank" rel="noopener" class="contact-map-directions">
            <i class="fi fi-rr-navigation"></i> Get Directions
          </a>
        </div>
        <div class="contact-map-outer">
          <div class="contact-map-frame">
            <?php if (!empty($cfg['maps_embed_url'])): ?>
            <iframe
              src="<?= $maps_embed_url ?>"
              width="100%" height="100%"
              allowfullscreen="" loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
              title="<?= $maps_label ?> location"
            ></iframe>
            <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:rgba(139,126,255,0.04);">
              <span style="font-family:var(--font-hud);font-size:0.60rem;color:var(--text-dim);letter-spacing:0.12em;">MAP EMBED URL NOT SET</span>
            </div>
            <?php endif; ?>
            <div class="map-scan" aria-hidden="true"></div>
            <div class="map-address-bar" aria-hidden="true">
              <div class="map-address-icon"><i class="fi fi-rr-building"></i></div>
              <div class="map-address-text">
                <span class="map-address-name"><?= $maps_label ?></span>
                <span class="map-address-sub"><?= $maps_sublabel ?></span>
              </div>
            </div>
            <span class="map-corner tl"></span>
            <span class="map-corner tr"></span>
            <span class="map-corner bl"></span>
            <span class="map-corner br"></span>
          </div>
        </div>
      </div>

      <!-- FAQ -->
      <div class="contact-faq" style="padding-top:80px;" id="faq">
        <div class="contact-faq-header reveal">
          <div style="font-family:var(--font-hud);font-size:0.58rem;letter-spacing:0.18em;text-transform:uppercase;color:var(--prc-ice);margin-bottom:10px;">// Frequently Asked</div>
          <h2>Common <span class="accent">Questions</span></h2>
        </div>
        <div class="faq-grid">
          <div class="faq-item reveal">
            <div class="faq-q">How do I register my team for PRC 2026?</div>
            <div class="faq-a">Registration is done online through our official registration form. Visit the Register page or contact us directly for assisted sign-up. Early registration is encouraged as slots are limited per category.</div>
          </div>
          <div class="faq-item reveal reveal-delay-1">
            <div class="faq-q">What age groups or grade levels can join?</div>
            <div class="faq-a">The PRC is open to elementary and high school students from both public and private schools nationwide. Specific age brackets vary per category — check the Categories page for details.</div>
          </div>
          <div class="faq-item reveal reveal-delay-1">
            <div class="faq-q">Do we need to purchase a kit to compete?</div>
            <div class="faq-a">Most categories require specific robotics kits available through Creotec Philippines. Some categories allow open hardware. Contact us to confirm which kit is required for your chosen event.</div>
          </div>
          <div class="faq-item reveal reveal-delay-2">
            <div class="faq-q">What is the MakeX track and how does qualifying work?</div>
            <div class="faq-a">MakeX is an internationally recognized robotics competition. The PRC serves as the official Philippine qualifier — top-performing teams in the MakeX category earn the chance to represent the country at the MakeX World Championships in China.</div>
          </div>
          <div class="faq-item reveal reveal-delay-2">
            <div class="faq-q">Can one school enter multiple teams or categories?</div>
            <div class="faq-a">Yes! Schools are encouraged to enter multiple teams across different categories. Each category has its own team size and requirements, so please review the rules for each event you plan to join.</div>
          </div>
          <div class="faq-item reveal reveal-delay-3">
            <div class="faq-q">How long does it take to receive a response?</div>
            <div class="faq-a">We aim to reply within one business day for email and form inquiries. Urgent questions about registration deadlines or materials are handled as priority — feel free to call or message us on Viber for faster assistance.</div>
          </div>
        </div>
      </div>

    </main>

    <!-- FOOTER -->
    <footer role="contentinfo">
      <div class="footer-inner">
        <div class="footer-top">
          <div class="footer-brand">
            <img src="assets/PRC White Logo.png" alt="Philippine Robotics Cup" />
            <p>The Philippine Robotics Cup is a premier national robotics competition promoting STEM education and preparing Filipino students for the future of technology.</p>
            <div class="footer-contact-list">
              <div class="footer-contact-item"><i class="fi fi-brands-facebook"></i><a href="<?= $facebook_url ?>" target="_blank" rel="noopener"><?= $facebook_label ?></a></div>
              <div class="footer-contact-item"><i class="fi fi-rr-phone-call"></i><a href="tel:<?= preg_replace('/\s+/', '', $cfg['phone'] ?? '') ?>"><?= $phone ?></a></div>
              <div class="footer-contact-item"><i class="fi fi-rr-envelope"></i><a href="mailto:<?= $email ?>"><?= $email ?></a></div>
            </div>
            <div class="social-links"><a href="<?= $facebook_url ?>" target="_blank" rel="noopener" class="social-link" aria-label="Facebook"><i class="fi fi-brands-facebook"></i></a></div>
          </div>
          <nav class="footer-col" aria-label="Competition"><h4>Competition</h4><ul><li><a href="index.php#categories"><i class="fi fi-rr-angle-right"></i>Categories</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Rules &amp; Guidelines</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Schedule</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Past Events</a></li></ul></nav>
          <nav class="footer-col" aria-label="Participate"><h4>Participate</h4><ul><li><a href="#"><i class="fi fi-rr-angle-right"></i>Register Now</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Order Materials</a></li><li><a href="contact.php"><i class="fi fi-rr-angle-right"></i>Contact Us</a></li><li><a href="contact.php#faq"><i class="fi fi-rr-angle-right"></i>FAQ</a></li></ul></nav>
          <nav class="footer-col" aria-label="Resources"><h4>Resources</h4><ul><li><a href="#"><i class="fi fi-rr-angle-right"></i>News &amp; Updates</a></li><li><a href="index.php#highlights"><i class="fi fi-rr-angle-right"></i>Gallery</a></li><li><a href="index.php#video"><i class="fi fi-rr-angle-right"></i>Videos</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Creotec Philippines</a></li></ul></nav>
        </div>
        <div class="footer-bottom">
          <p>&copy; 2026 Philippine Robotics Cup // Creotec Philippines Inc. All rights reserved.</p>
          <div class="footer-bottom-links"><a href="#">Privacy Policy</a><a href="#">Terms of Use</a></div>
        </div>
      </div>
    </footer>

  </div><!-- /page-wrapper -->

  <script src="nav-loader.js" defer></script>
  <script>
    var APPS_SCRIPT_ENDPOINT = 'https://script.google.com/macros/s/AKfycbx2tEY3ooRHGYAKTCWhMgPl7fkh19zC66Mj3a4E7mewpNrZ-wQ0_jZJ3gPRi76LiRIpLA/exec';
    var subjectMap = { registration:'Registration & Team Sign-Up', categories:'Competition Categories & Rules', materials:'Ordering Materials & Kits', schedule:'Schedule & Venue', makex:'MakeX International Qualifier', sponsorship:'Sponsorship & Partnership', media:'Media & Press Inquiry', other:'Other / General Question' };

    // ── CUSTOM CURSOR ──
    var dot = document.getElementById('cursorDot'), ring = document.getElementById('cursorRing');
    var mx = 0, my = 0, rx = 0, ry = 0;
    document.addEventListener('mousemove', function(e){ mx = e.clientX; my = e.clientY; dot.style.left = mx+'px'; dot.style.top = my+'px'; });
    (function animRing(){ rx += (mx-rx)*0.12; ry += (my-ry)*0.12; ring.style.left = rx+'px'; ring.style.top = ry+'px'; requestAnimationFrame(animRing); })();
    document.querySelectorAll('a,button,.contact-card,.faq-item,.contact-social-link').forEach(function(el){
      el.addEventListener('mouseenter', function(){ ring.classList.add('hovered'); dot.style.background='var(--creo-amber)'; dot.style.boxShadow='var(--glow-orange)'; });
      el.addEventListener('mouseleave', function(){ ring.classList.remove('hovered'); dot.style.background='var(--prc-violet)'; dot.style.boxShadow='var(--glow-primary)'; });
    });

    // ── CHAR COUNTER ──
    var msgField = document.getElementById('message'), charCount = document.getElementById('charCount');
    msgField.addEventListener('input', function(){ var l=msgField.value.length; charCount.textContent=l+' / 1000'; charCount.classList.toggle('warn',l>900); });

    // ── SCROLL REVEAL ──
    var revEls = document.querySelectorAll('.reveal');
    var ro = new IntersectionObserver(function(entries){ entries.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('visible'); ro.unobserve(e.target); } }); }, { threshold: 0.08, rootMargin: '0px 0px -24px 0px' });
    revEls.forEach(function(el){ ro.observe(el); });

    // ── CONTACT FORM ──
    var form=document.getElementById('contactForm'), submitBtn=document.getElementById('submitBtn');
    var submitIcon=document.getElementById('submitIcon'), submitLbl=document.getElementById('submitLabel');
    var submitNote=document.getElementById('submitNote'), toast=document.getElementById('formToast');
    var toastIcon=document.getElementById('toastIcon'), toastMsg=document.getElementById('toastMsg');

    function showToast(type, msg){ toast.className='form-toast show '+type; toastIcon.className='fi '+(type==='success'?'fi-rr-check-circle':'fi-rr-exclamation'); toastMsg.textContent=msg; toast.scrollIntoView({behavior:'smooth',block:'nearest'}); }
    function setSubmitting(l){ submitBtn.disabled=l; submitIcon.className=l?'fi fi-rr-loading':'fi fi-rr-paper-plane'; submitLbl.textContent=l?'Sending…':'Send Message'; submitNote.textContent=l?'Please wait…':'All fields marked * are required'; }

    form.addEventListener('submit', function(e){
      e.preventDefault();
      toast.className='form-toast';
      var required=['firstName','lastName','email','role','subject','message'];
      for(var i=0;i<required.length;i++){ var el=form.elements[required[i]]; if(!el||!el.value.trim()){ showToast('error','Please fill in all required fields before submitting.'); el&&el.focus(); return; } }
      var emailEl=form.elements['email'];
      if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value.trim())){ showToast('error','Please enter a valid email address.'); emailEl.focus(); return; }
      if(!form.elements['consent'].checked){ showToast('error','Please accept the Privacy Policy to continue.'); return; }
      setSubmitting(true);
      var sv=form.elements['subject'].value;
      var payload={ firstName:form.elements['firstName'].value.trim(), lastName:form.elements['lastName'].value.trim(), email:form.elements['email'].value.trim(), phone:form.elements['phone'].value.trim(), school:form.elements['school'].value.trim(), role:form.elements['role'].value, subject:sv, subjectLabel:subjectMap[sv]||sv, message:form.elements['message'].value.trim() };
      fetch(APPS_SCRIPT_ENDPOINT,{ method:'POST', mode:'no-cors', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'data='+encodeURIComponent(JSON.stringify(payload)) })
      .then(function(){ setSubmitting(false); showToast('success','Message sent! A confirmation copy has been sent to '+payload.email+'. We\'ll get back to you within one business day.'); form.reset(); charCount.textContent='0 / 1000'; })
      .catch(function(){ setSubmitting(false); showToast('error','Network error — please check your connection and try again.'); });
    });
  </script>

</body>
</html>