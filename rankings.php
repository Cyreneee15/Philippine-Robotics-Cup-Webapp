<?php
// PRC-WebApp/rankings.php

$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'prc_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die('<p style="color:#ff6b6b;padding:40px;font-family:monospace;">DB error: '.htmlspecialchars($conn->connect_error).'</p>');

// ── Meta ─────────────────────────────────────────────────────
$meta = [];
$mr = $conn->query("SELECT meta_key, meta_value FROM prc_rankings_meta");
if ($mr) while ($row = $mr->fetch_assoc()) $meta[$row['meta_key']] = $row['meta_value'];
$hero_desc = $meta['hero_desc'] ?? 'Official standings from Philippine Robotics Cup National Finals.';

// ── Years ────────────────────────────────────────────────────
$years = [];
$yr = $conn->query("SELECT * FROM prc_rankings_years WHERE is_active=1 ORDER BY year_sort ASC, year_label DESC");
if ($yr) while ($row = $yr->fetch_assoc()) $years[] = $row;

// ── Categories ───────────────────────────────────────────────
$cats = [];
$cr = $conn->query("SELECT * FROM prc_rankings_categories WHERE is_active=1 ORDER BY cat_sort ASC");
if ($cr) while ($row = $cr->fetch_assoc()) $cats[] = $row;

// ── Subcategories keyed by cat_id ────────────────────────────
$subs_by_cat = [];
$sr = $conn->query("SELECT s.*, c.cat_slug FROM prc_rankings_subcategories s JOIN prc_rankings_categories c ON s.cat_id=c.cat_id WHERE s.is_active=1 ORDER BY s.sub_sort ASC");
if ($sr) while ($row = $sr->fetch_assoc()) $subs_by_cat[$row['cat_id']][] = $row;

// ── Entries keyed by year_id > sub_id ────────────────────────
$entries = []; // $entries[year_id][sub_id] = [ ...rows ]
$er = $conn->query(
    "SELECT e.*, ae.award_detail, ae.award_photo
     FROM prc_rankings_entries e
     LEFT JOIN prc_rankings_award_extras ae ON ae.entry_id = e.entry_id
     WHERE e.is_active=1
     ORDER BY e.year_id ASC, e.sub_id ASC, e.rank_pos ASC, e.entry_sort ASC"
);
if ($er) while ($row = $er->fetch_assoc()) $entries[$row['year_id']][$row['sub_id']][] = $row;

$conn->close();

// ── Helper: badge class for cat_color ────────────────────────
function cat_badge_class($color) {
    return match($color) {
        'mx'    => 'badge-mx',
        'drone' => 'badge-drone',
        'award' => 'badge-award',
        default => 'badge-rv',
    };
}

// ── Helper: status label from rank_pos ───────────────────────
function default_status($pos) {
    return match((int)$pos) {
        1 => 'Champion',
        2 => '1st Runner-up',
        3 => '2nd Runner-up',
        default => 'Finalist',
    };
}

// ── Helper: placeholder initials from school name ────────────
function school_initials($name) {
    $words = preg_split('/\s+/', $name);
    $init = '';
    foreach (array_slice($words, 0, 3) as $w) {
        if (ctype_upper($w[0] ?? '')) $init .= $w[0];
    }
    return $init ?: strtoupper(substr($name, 0, 3));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#8B7EFF" />
  <meta name="description" content="Philippine Robotics Cup — Official Rankings and Results." />
  <title>Rankings - Philippine Robotics Cup</title>
  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link rel="shortcut icon" href="assets/favicon.png" />
  <link rel="apple-touch-icon" href="assets/favicon.png" />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-brands/css/uicons-brands.css'>
  <style>
    :root {
      --prc-violet:#8B7EFF;--prc-ice:#C4EEFF;--creo-purple:#7733FF;--creo-amber:#FFA030;
      --creo-volt:#FFE930;--creo-sky:#44D9FF;--neon-primary:var(--prc-violet);
      --bg-void:#03020D;--border-neon:rgba(139,126,255,0.22);
      --glow-primary:0 0 18px rgba(139,126,255,0.60),0 0 55px rgba(139,126,255,0.20);
      --glow-orange:0 0 18px rgba(255,160,48,0.55),0 0 55px rgba(255,160,48,0.18);
      --text-high:#F2EEFF;--text-mid:#C8C0F0;--text-soft:#9A90CC;--text-dim:#7068A8;
      --nav-height:72px;--font-hud:'Orbitron',monospace;--font-body:'Exo 2',sans-serif;
      --sidebar-w:300px;--award-gold:#FFD700;--award-gold-dim:rgba(255,215,0,0.22);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html{scroll-behavior:smooth}
    body{font-family:var(--font-body);background:var(--bg-void);color:var(--text-high);overflow-x:hidden;line-height:1.6;cursor:none}
    img{max-width:100%;display:block} a{text-decoration:none;color:inherit} ul{list-style:none}
    button{font-family:inherit;cursor:none;border:none;background:none}
    /* CURSOR */
    .cursor-dot{position:fixed;width:8px;height:8px;border-radius:50%;background:var(--neon-primary);pointer-events:none;z-index:99999;transform:translate(-50%,-50%);box-shadow:var(--glow-primary);transition:transform .1s,background .2s}
    .cursor-ring{position:fixed;width:36px;height:36px;border-radius:50%;border:1px solid rgba(139,126,255,0.65);pointer-events:none;z-index:99998;transform:translate(-50%,-50%);transition:width .25s,height .25s,border-color .25s}
    .cursor-ring.hovered{width:56px;height:56px;border-color:var(--creo-amber);border-width:1.5px}
    body::after{content:'';position:fixed;inset:0;z-index:9998;pointer-events:none;background:repeating-linear-gradient(to bottom,transparent,transparent 2px,rgba(0,0,0,0.04) 2px,rgba(0,0,0,0.04) 4px)}
    .hex-grid{position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(rgba(139,126,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(139,126,255,0.04) 1px,transparent 1px);background-size:50px 50px}
    .hex-grid::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(119,51,255,0.14) 0%,transparent 70%),radial-gradient(ellipse 60% 50% at 100% 100%,rgba(204,85,255,0.07) 0%,transparent 60%)}
    @keyframes neonPulse{0%,100%{opacity:1}50%{opacity:0.6}}
    @keyframes fadeInUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
    @keyframes fadeIn{from{opacity:0}to{opacity:1}}
    @keyframes scanDown{from{transform:translateY(-100%)}to{transform:translateY(100vh)}}
    @keyframes panelSlide{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
    @keyframes borderPulse{0%,100%{box-shadow:0 0 20px rgba(255,233,48,0.28),inset 0 0 20px rgba(255,233,48,0.04)}50%{box-shadow:0 0 45px rgba(255,233,48,0.52),inset 0 0 35px rgba(255,233,48,0.08)}}
    @keyframes borderPulse2{0%,100%{box-shadow:0 0 20px rgba(196,238,255,0.20),inset 0 0 20px rgba(196,238,255,0.04)}50%{box-shadow:0 0 45px rgba(196,238,255,0.40),inset 0 0 35px rgba(196,238,255,0.08)}}
    @keyframes borderPulse3{0%,100%{box-shadow:0 0 20px rgba(255,160,48,0.20),inset 0 0 20px rgba(255,160,48,0.04)}50%{box-shadow:0 0 45px rgba(255,160,48,0.40),inset 0 0 35px rgba(255,160,48,0.08)}}
    @keyframes crownFloat{0%,100%{transform:translateX(-50%) translateY(0) rotate(-3deg)}50%{transform:translateX(-50%) translateY(-6px) rotate(3deg)}}
    @keyframes scanLine{0%{top:0%;opacity:.7}100%{top:100%;opacity:0}}
    @keyframes logoGlow{0%,100%{filter:drop-shadow(0 0 6px rgba(255,233,48,0.50))}50%{filter:drop-shadow(0 0 14px rgba(255,233,48,0.90))}}
    .page-wrapper{position:relative;z-index:1}
    /* NAV */
    #main-nav{position:fixed;top:0;left:0;right:0;height:var(--nav-height);z-index:1000;background:rgba(3,2,13,0.94);backdrop-filter:blur(20px);border-bottom:1px solid var(--border-neon);box-shadow:0 0 30px rgba(139,126,255,0.10)}
    .nav-inner{max-width:1340px;margin:0 auto;height:100%;padding:0 36px;display:flex;align-items:center;justify-content:space-between;gap:16px}
    .nav-logo{display:flex;align-items:center;gap:12px;flex-shrink:0}
    .nav-logo img{height:38px;width:auto;transition:filter .3s}
    .nav-logo:hover img{filter:drop-shadow(0 0 14px rgba(139,126,255,0.75))}
    .nav-brand{font-family:var(--font-hud);font-weight:700;font-size:0.72rem;letter-spacing:0.06em;line-height:1.3;color:var(--prc-violet);text-shadow:0 0 12px rgba(139,126,255,0.65)}
    .nav-brand span{color:var(--text-soft);display:block;font-size:0.58rem;font-weight:400;letter-spacing:0.10em;text-transform:uppercase;margin-top:1px}
    .nav-links{display:flex;align-items:center;gap:2px}
    .nav-links a{font-family:var(--font-hud);font-size:0.65rem;font-weight:600;color:var(--text-mid);padding:8px 14px;letter-spacing:0.08em;text-transform:uppercase;border-radius:4px;transition:all .2s;white-space:nowrap;position:relative}
    .nav-links a:hover{color:var(--prc-violet);text-shadow:0 0 12px rgba(139,126,255,0.85)}
    .nav-links a.active{color:var(--prc-violet);text-shadow:0 0 12px rgba(139,126,255,0.85)}
    .nav-links a.active::after{content:'';position:absolute;bottom:4px;left:14px;right:14px;height:1px;background:var(--prc-violet);box-shadow:0 0 6px rgba(139,126,255,0.80)}
    .nav-cta{background:transparent!important;border:1px solid var(--prc-violet)!important;color:var(--prc-violet)!important;padding:8px 20px!important;border-radius:3px!important;box-shadow:0 0 15px rgba(139,126,255,0.28),inset 0 0 15px rgba(139,126,255,0.06)!important;transition:all .25s!important;margin-left:8px;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
    .nav-cta:hover{background:rgba(139,126,255,0.12)!important;box-shadow:0 0 30px rgba(139,126,255,0.52),inset 0 0 20px rgba(139,126,255,0.10)!important;color:#fff!important}
    .nav-hamburger{display:none;flex-direction:column;justify-content:center;align-items:center;gap:5px;width:44px;height:44px;padding:0;cursor:none;background:rgba(139,126,255,0.06);border:1px solid var(--border-neon);border-radius:4px;flex-shrink:0;z-index:1002;-webkit-tap-highlight-color:transparent;touch-action:manipulation;transition:all .2s}
    .nav-hamburger:hover{background:rgba(139,126,255,0.14);box-shadow:0 0 14px rgba(139,126,255,0.28)}
    .nav-hamburger span{width:20px;height:1.5px;background:var(--prc-violet);border-radius:2px;transition:transform .28s,opacity .28s;display:block;pointer-events:none}
    .nav-hamburger.open span:nth-child(1){transform:rotate(45deg) translate(5px,5px)}
    .nav-hamburger.open span:nth-child(2){opacity:0}
    .nav-hamburger.open span:nth-child(3){transform:rotate(-45deg) translate(5px,-5px)}
    .nav-mobile{display:none;position:fixed;top:var(--nav-height);left:0;right:0;background:rgba(3,2,13,0.98);backdrop-filter:blur(20px);border-bottom:1px solid var(--border-neon);padding:12px 18px 24px;z-index:1000;flex-direction:column;gap:2px;box-shadow:0 20px 60px rgba(139,126,255,0.09)}
    .nav-mobile.open{display:flex}
    .nav-mobile a{font-family:var(--font-hud);font-size:0.70rem;font-weight:600;color:var(--text-mid);padding:13px 14px;border-radius:3px;letter-spacing:0.08em;text-transform:uppercase;transition:all .2s;display:flex;align-items:center;gap:12px}
    .nav-mobile a i{font-size:1rem;color:var(--prc-violet)}
    .nav-mobile a:hover,.nav-mobile a.active{color:var(--prc-violet);background:rgba(139,126,255,0.07);text-shadow:0 0 10px rgba(139,126,255,0.55)}
    .nav-mobile .nav-cta{border:1px solid var(--prc-violet)!important;color:var(--prc-violet)!important;margin-top:10px;justify-content:center;border-radius:3px!important;clip-path:none!important}
    /* PAGE HERO */
    .page-hero{position:relative;padding:calc(var(--nav-height) + 72px) 0 72px;overflow:hidden;text-align:center}
    .page-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 70% 70% at 50% 0%,rgba(139,126,255,0.10) 0%,transparent 70%),linear-gradient(to bottom,rgba(3,2,13,0) 60%,var(--bg-void) 100%)}
    .page-hero-scan{position:absolute;inset:0;pointer-events:none;overflow:hidden}
    .page-hero-scan::after{content:'';position:absolute;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),var(--prc-ice),transparent);animation:scanDown 8s linear infinite;box-shadow:0 0 14px rgba(139,126,255,0.55)}
    .page-hero-inner{position:relative;z-index:2;max-width:700px;margin:0 auto;padding:0 36px}
    .page-hero-eyebrow{display:inline-flex;align-items:center;gap:10px;font-family:var(--font-hud);font-size:0.60rem;font-weight:600;letter-spacing:0.20em;text-transform:uppercase;color:var(--prc-ice);margin-bottom:18px;animation:fadeIn .8s ease both}
    .page-hero-eyebrow::before{content:'//';color:rgba(139,126,255,0.40);font-size:0.70rem}
    .page-hero-blink{width:6px;height:6px;background:var(--prc-violet);border-radius:50%;box-shadow:var(--glow-primary);animation:neonPulse 1.2s ease-in-out infinite}
    .page-hero-title{font-family:var(--font-hud);font-size:clamp(2.2rem,6vw,4rem);font-weight:900;letter-spacing:-0.01em;line-height:1.0;color:#fff;margin-bottom:18px;text-shadow:0 0 40px rgba(139,126,255,0.20);animation:fadeInUp .8s ease .1s both}
    .page-hero-title .accent{color:var(--prc-violet);text-shadow:0 0 22px rgba(139,126,255,0.65)}
    .page-hero-desc{font-size:1rem;color:var(--text-mid);line-height:1.78;max-width:520px;margin:0 auto;animation:fadeInUp .8s ease .2s both}
    .page-hero-divider{display:flex;align-items:center;justify-content:center;gap:14px;margin-top:36px;animation:fadeIn .8s ease .3s both}
    .page-hero-divider-line{width:80px;height:1px;background:linear-gradient(90deg,transparent,rgba(139,126,255,0.40))}
    .page-hero-divider-line.right{background:linear-gradient(90deg,rgba(139,126,255,0.40),transparent)}
    .page-hero-divider-diamond{width:8px;height:8px;background:var(--prc-violet);transform:rotate(45deg);box-shadow:var(--glow-primary)}
    /* MAIN SHELL */
    .rankings-shell{max-width:1340px;margin:0 auto;padding:40px 36px 100px;display:grid;grid-template-columns:var(--sidebar-w) 1fr;gap:28px;align-items:start}
    /* EVENT SWITCHER */
    .event-switcher-bar{position:relative;overflow:hidden;border-top:1px solid rgba(139,126,255,0.18);border-bottom:1px solid rgba(139,126,255,0.18);background:rgba(139,126,255,0.04);backdrop-filter:blur(12px)}
    .event-switcher-bar::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--prc-violet),var(--prc-ice),transparent);box-shadow:0 0 24px rgba(139,126,255,0.45)}
    .event-switcher-inner{max-width:1340px;margin:0 auto;padding:36px 36px 40px;position:relative;z-index:2;display:grid;grid-template-columns:auto 1fr;gap:48px;align-items:center}
    .event-switcher-label{font-family:var(--font-hud);font-size:0.60rem;font-weight:700;letter-spacing:0.20em;text-transform:uppercase;color:var(--text-soft);margin-bottom:16px;display:flex;align-items:center;gap:8px}
    .event-switcher-label::before{content:'//';color:rgba(139,126,255,0.40)}
    .event-year-row{display:flex;gap:10px;flex-wrap:wrap}
    .event-year-btn{font-family:var(--font-hud);font-size:1.6rem;font-weight:900;letter-spacing:-0.01em;padding:10px 28px;border:1px solid rgba(139,126,255,0.22);color:var(--text-dim);background:transparent;cursor:pointer;transition:all .22s;clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);line-height:1.1}
    .event-year-btn:hover{border-color:var(--prc-violet);color:var(--text-high);background:rgba(139,126,255,0.08);text-shadow:0 0 20px rgba(139,126,255,0.40)}
    .event-year-btn.active{border-color:var(--prc-violet);color:var(--prc-violet);background:rgba(139,126,255,0.10);box-shadow:0 0 22px rgba(139,126,255,0.28),inset 0 0 22px rgba(139,126,255,0.06);text-shadow:0 0 24px rgba(139,126,255,0.70)}
    .event-info-block{display:flex;flex-direction:column;gap:10px}
    .event-info-name{font-family:var(--font-hud);font-size:clamp(1.3rem,2.4vw,1.9rem);font-weight:800;color:#fff;letter-spacing:0.02em;line-height:1.2}
    .event-info-name .accent{color:var(--prc-violet);text-shadow:0 0 18px rgba(139,126,255,0.60)}
    .event-info-meta{display:flex;flex-direction:column;gap:6px}
    .event-info-row{display:flex;align-items:center;gap:10px;font-family:var(--font-hud);font-size:0.72rem;color:var(--text-mid);letter-spacing:0.06em}
    .event-info-row i{font-size:0.85rem;color:var(--prc-violet);flex-shrink:0}
    /* SIDEBAR */
    .rankings-sidebar{position:sticky;top:calc(var(--nav-height) + 20px);display:flex;flex-direction:column;gap:6px}
    .sidebar-section{margin-bottom:4px}
    .sidebar-cat-btn{width:100%;display:flex;align-items:center;gap:14px;padding:15px 16px;background:rgba(139,126,255,0.04);border:1px solid rgba(139,126,255,0.18);color:var(--text-mid);cursor:pointer;transition:all .25s;position:relative;overflow:hidden;text-align:left}
    .sidebar-cat-btn::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(139,126,255,0.40),transparent);opacity:0;transition:opacity .25s}
    .sidebar-cat-btn:hover{border-color:rgba(139,126,255,0.38);background:rgba(139,126,255,0.08)}
    .sidebar-cat-btn:hover::before{opacity:1}
    .sidebar-cat-btn.active{border-color:var(--prc-violet);background:rgba(139,126,255,0.10);box-shadow:0 0 22px rgba(139,126,255,0.14),inset 0 0 22px rgba(139,126,255,0.05)}
    .sidebar-cat-btn.active::before{opacity:1;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent)}
    .sidebar-cat-btn.active-mx{border-color:var(--creo-sky);background:rgba(68,217,255,0.08);box-shadow:0 0 22px rgba(68,217,255,0.12)}
    .sidebar-cat-btn.active-mx::before{opacity:1;background:linear-gradient(90deg,transparent,var(--creo-sky),transparent)}
    .sidebar-cat-btn.active-drone{border-color:var(--creo-amber);background:rgba(255,160,48,0.08);box-shadow:0 0 22px rgba(255,160,48,0.12)}
    .sidebar-cat-btn.active-drone::before{opacity:1;background:linear-gradient(90deg,transparent,var(--creo-amber),transparent)}
    .sidebar-cat-btn.active-award{border-color:var(--award-gold);background:rgba(255,215,0,0.08);box-shadow:0 0 22px rgba(255,215,0,0.20)}
    .sidebar-cat-btn.active-award::before{opacity:1;background:linear-gradient(90deg,transparent,var(--award-gold),transparent)}
    .sidebar-cat-logo{flex-shrink:0;width:72px;height:32px;display:flex;align-items:center;justify-content:center}
    .sidebar-cat-logo img{max-width:72px;max-height:30px;width:auto;height:auto;object-fit:contain;filter:saturate(0.5) brightness(0.70);transition:filter .25s}
    .sidebar-cat-btn:hover .sidebar-cat-logo img,.sidebar-cat-btn.active .sidebar-cat-logo img{filter:saturate(1) brightness(1.1) drop-shadow(0 0 6px rgba(139,126,255,0.45))}
    .sidebar-cat-btn.active-mx .sidebar-cat-logo img{filter:saturate(1) brightness(1.1) drop-shadow(0 0 6px rgba(68,217,255,0.55))}
    .sidebar-cat-btn.active-drone .sidebar-cat-logo img{filter:saturate(1) brightness(1.1) drop-shadow(0 0 6px rgba(255,160,48,0.55))}
    .sidebar-cat-text{flex:1;min-width:0}
    .sidebar-cat-name{display:block;font-family:var(--font-hud);font-size:0.72rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:var(--text-high);line-height:1.2;margin-bottom:2px}
    .sidebar-cat-count{display:block;font-family:var(--font-body);font-size:0.76rem;color:var(--text-dim);font-weight:400}
    .sidebar-cat-chevron{font-size:0.70rem;color:var(--text-dim);transition:transform .25s,color .25s;flex-shrink:0}
    .sidebar-cat-btn.active .sidebar-cat-chevron,.sidebar-cat-btn.active-mx .sidebar-cat-chevron,.sidebar-cat-btn.active-drone .sidebar-cat-chevron,.sidebar-cat-btn.active-award .sidebar-cat-chevron{transform:rotate(90deg)}
    .sidebar-cat-btn.active .sidebar-cat-chevron{color:var(--prc-violet)}
    .sidebar-cat-btn.active-mx .sidebar-cat-chevron{color:var(--creo-sky)}
    .sidebar-cat-btn.active-drone .sidebar-cat-chevron{color:var(--creo-amber)}
    .sidebar-cat-btn.active-award .sidebar-cat-chevron{color:var(--award-gold)}
    .sidebar-subs{overflow:hidden;max-height:0;transition:max-height .38s cubic-bezier(0.23,1,0.32,1);display:flex;flex-direction:column;gap:3px;margin-top:3px}
    .sidebar-subs.open{max-height:600px}
    .sidebar-sub-btn{width:100%;display:flex;align-items:center;gap:10px;padding:11px 16px 11px 18px;font-family:var(--font-body);font-size:0.88rem;font-weight:500;letter-spacing:0.01em;background:rgba(139,126,255,0.02);border:1px solid rgba(139,126,255,0.10);border-left:3px solid transparent;color:var(--text-soft);cursor:pointer;transition:all .20s;text-align:left}
    .sidebar-sub-btn:hover{color:var(--text-high);border-color:rgba(139,126,255,0.20);border-left-color:rgba(139,126,255,0.45);background:rgba(139,126,255,0.06)}
    .sidebar-sub-btn.active{color:var(--text-high);font-weight:600;border-color:rgba(139,126,255,0.25);border-left:3px solid var(--prc-violet);background:rgba(139,126,255,0.08)}
    .sidebar-sub-btn.active-mx{color:var(--text-high);font-weight:600;border-color:rgba(68,217,255,0.22);border-left:3px solid var(--creo-sky);background:rgba(68,217,255,0.06)}
    .sidebar-sub-btn.active-drone{color:var(--text-high);font-weight:600;border-color:rgba(255,160,48,0.22);border-left:3px solid var(--creo-amber);background:rgba(255,160,48,0.06)}
    .sidebar-sub-btn.active-award{color:var(--award-gold);font-weight:600;border-left:3px solid var(--award-gold);background:rgba(255,215,0,0.10)}
    .sub-num{width:22px;height:22px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-hud);font-size:0.48rem;font-weight:700;background:rgba(139,126,255,0.08);border:1px solid rgba(139,126,255,0.20);color:var(--text-dim);transition:all .20s}
    .sidebar-sub-btn.active .sub-num{background:rgba(139,126,255,0.18);color:var(--prc-violet);border-color:var(--prc-violet);box-shadow:0 0 8px rgba(139,126,255,0.40)}
    .sidebar-cta{margin-top:16px;display:block;text-align:center;padding:12px 20px;font-family:var(--font-hud);font-size:0.60rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--prc-violet);border:1px solid var(--prc-violet);background:rgba(139,126,255,0.05);box-shadow:0 0 14px rgba(139,126,255,0.18);transition:all .25s;clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
    .sidebar-cta:hover{background:rgba(139,126,255,0.12);box-shadow:0 0 28px rgba(139,126,255,0.40);color:#fff}
    /* RIGHT PANEL */
    .rankings-panel{min-height:600px}
    .rank-panel{display:none;animation:panelSlide .30s ease both}
    .rank-panel.active{display:block}
    .panel-header-strip{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 20px;margin-bottom:24px;border:1px solid var(--border-neon);background:rgba(139,126,255,0.04);position:relative;overflow:hidden}
    .panel-header-strip::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent)}
    .panel-header-strip.strip-mx::before{background:linear-gradient(90deg,transparent,var(--creo-sky),transparent)}
    .panel-header-strip.strip-drone::before{background:linear-gradient(90deg,transparent,var(--creo-amber),transparent)}
    .panel-header-strip.strip-award::before{background:linear-gradient(90deg,transparent,var(--award-gold),transparent)}
    .panel-header-left{display:flex;align-items:center;gap:10px}
    .panel-header-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;animation:neonPulse 1.8s ease-in-out infinite}
    .panel-header-dot.rv{background:var(--prc-violet);box-shadow:0 0 8px rgba(139,126,255,0.70)}
    .panel-header-dot.mx{background:var(--creo-sky);box-shadow:0 0 8px rgba(68,217,255,0.70)}
    .panel-header-dot.drone{background:var(--creo-amber);box-shadow:0 0 8px rgba(255,160,48,0.70)}
    .panel-header-dot.award{background:var(--award-gold);box-shadow:0 0 8px rgba(255,215,0,0.80)}
    .panel-header-title{font-family:var(--font-hud);font-size:0.60rem;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:var(--text-mid)}
    .panel-header-tag{font-family:var(--font-hud);font-size:0.52rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:4px 12px;border:1px solid}
    .panel-header-tag.rv{color:var(--prc-violet);border-color:rgba(139,126,255,0.30);background:rgba(139,126,255,0.06)}
    .panel-header-tag.mx{color:var(--creo-sky);border-color:rgba(68,217,255,0.28);background:rgba(68,217,255,0.05)}
    .panel-header-tag.drone{color:var(--creo-amber);border-color:rgba(255,160,48,0.28);background:rgba(255,160,48,0.05)}
    .panel-header-tag.award{color:var(--award-gold);border-color:rgba(255,215,0,0.35);background:rgba(255,215,0,0.06)}
    /* PODIUM */
    .podium-section{margin-bottom:36px}
    .podium-label{font-family:var(--font-hud);font-size:0.54rem;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:var(--text-dim);text-align:center;margin-bottom:26px}
    .podium-label span{color:var(--creo-volt);text-shadow:0 0 10px rgba(255,233,48,0.50)}
    .podium-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0 14px;align-items:end}
    .pod-2{grid-column:1}.pod-1{grid-column:2}.pod-3{grid-column:3}
    .podium-card{position:relative;border:1px solid;overflow:hidden;transition:transform .3s;display:flex;flex-direction:column}
    .podium-card:hover{transform:translateY(-4px)}
    .pod-1 .podium-card{border-color:rgba(255,233,48,0.38);background:linear-gradient(160deg,rgba(255,233,48,0.05) 0%,rgba(3,2,13,0.95) 60%);animation:borderPulse 3s ease-in-out infinite}
    .pod-2 .podium-card{border-color:rgba(196,238,255,0.28);background:linear-gradient(160deg,rgba(196,238,255,0.04) 0%,rgba(3,2,13,0.95) 60%);animation:borderPulse2 3.5s ease-in-out infinite}
    .pod-3 .podium-card{border-color:rgba(255,160,48,0.28);background:linear-gradient(160deg,rgba(255,160,48,0.04) 0%,rgba(3,2,13,0.95) 60%);animation:borderPulse3 4s ease-in-out infinite}
    .podium-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px}
    .pod-1 .podium-card::before{background:linear-gradient(90deg,transparent,var(--creo-volt),transparent);box-shadow:0 0 12px rgba(255,233,48,0.70)}
    .pod-2 .podium-card::before{background:linear-gradient(90deg,transparent,var(--prc-ice),transparent);box-shadow:0 0 12px rgba(196,238,255,0.50)}
    .pod-3 .podium-card::before{background:linear-gradient(90deg,transparent,var(--creo-amber),transparent);box-shadow:0 0 12px rgba(255,160,48,0.50)}
    .podium-card-scan{position:absolute;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.06),transparent);animation:scanLine 4s linear infinite;pointer-events:none;z-index:5}
    .podium-rank-badge{position:absolute;top:12px;right:12px;z-index:10;display:flex;flex-direction:column;align-items:center;gap:1px}
    .podium-rank-label{font-family:var(--font-hud);font-size:0.44rem;font-weight:900;letter-spacing:0.06em;line-height:1}
    .pod-1 .podium-rank-label{color:var(--creo-volt);text-shadow:0 0 8px rgba(255,233,48,0.80)}
    .pod-2 .podium-rank-label{color:var(--prc-ice);text-shadow:0 0 8px rgba(196,238,255,0.80)}
    .pod-3 .podium-rank-label{color:var(--creo-amber);text-shadow:0 0 8px rgba(255,160,48,0.80)}
    .podium-rank-icon{font-size:1.1rem;line-height:1;color:var(--creo-volt)}
    .pod-2 .podium-rank-icon{color:var(--prc-ice)}.pod-3 .podium-rank-icon{color:var(--creo-amber)}
    .crown-float{position:absolute;top:-4px;left:50%;transform:translateX(-50%);font-size:1.2rem;z-index:10;animation:crownFloat 3s ease-in-out infinite;color:var(--creo-volt);filter:drop-shadow(0 0 8px rgba(255,233,48,0.80))}
    .podium-photo-wrap{position:relative;width:100%;overflow:hidden}
    .pod-1 .podium-photo-wrap{height:195px}.pod-2 .podium-photo-wrap,.pod-3 .podium-photo-wrap{height:155px}
    .podium-photo{width:100%;height:100%;object-fit:cover;object-position:center;display:block;transition:transform .45s,filter .45s;filter:brightness(1.0) saturate(1.15) contrast(1.02)}
    .podium-card:hover .podium-photo{transform:scale(1.08);filter:brightness(1.08) saturate(1.25) contrast(1.04)}
    .podium-photo-fade{position:absolute;bottom:0;left:0;right:0;height:45%;background:linear-gradient(to top,rgba(3,2,13,0.95) 0%,rgba(3,2,13,0.45) 55%,transparent 100%)}
    .podium-info{padding:14px 14px 16px;display:flex;flex-direction:column;gap:6px;position:relative;z-index:2}
    .podium-status-tag{display:inline-flex;align-items:center;gap:5px;font-family:var(--font-hud);font-size:0.46rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;padding:3px 9px;border:1px solid;clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%);width:fit-content;margin-bottom:2px}
    .pod-1 .podium-status-tag{color:var(--creo-volt);border-color:rgba(255,233,48,0.38);background:rgba(255,233,48,0.07);text-shadow:0 0 8px rgba(255,233,48,0.60)}
    .pod-2 .podium-status-tag{color:var(--prc-ice);border-color:rgba(196,238,255,0.28);background:rgba(196,238,255,0.05)}
    .pod-3 .podium-status-tag{color:var(--creo-amber);border-color:rgba(255,160,48,0.28);background:rgba(255,160,48,0.05)}
    .podium-school-name{font-family:var(--font-hud);font-weight:800;letter-spacing:0.02em;color:#fff;line-height:1.2}
    .pod-1 .podium-school-name{font-size:0.85rem}.pod-2 .podium-school-name,.pod-3 .podium-school-name{font-size:0.70rem}
    .podium-team-name{font-family:var(--font-hud);font-size:0.48rem;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-soft)}
    .podium-meta-row{display:flex;align-items:center;gap:10px;margin-top:4px;padding-top:10px;border-top:1px solid rgba(139,126,255,0.12);flex-wrap:wrap}
    .podium-meta-item{display:flex;align-items:center;gap:4px;font-family:var(--font-hud);font-size:0.46rem;color:var(--text-dim);letter-spacing:0.06em;text-transform:uppercase}
    .podium-meta-item i{font-size:0.58rem}
    .podium-step-base{height:6px;border-left:1px solid;border-right:1px solid;border-bottom:1px solid}
    .pod-1 .podium-step-base{background:linear-gradient(180deg,rgba(255,233,48,0.12) 0%,rgba(255,233,48,0.03) 100%);border-color:rgba(255,233,48,0.22)}
    .pod-2 .podium-step-base{background:linear-gradient(180deg,rgba(196,238,255,0.08) 0%,rgba(196,238,255,0.02) 100%);border-color:rgba(196,238,255,0.18)}
    .pod-3 .podium-step-base{background:linear-gradient(180deg,rgba(255,160,48,0.08) 0%,rgba(255,160,48,0.02) 100%);border-color:rgba(255,160,48,0.18)}
    /* STANDINGS TABLE */
    .standings-section{margin-top:32px}
    .standings-section-label{display:flex;align-items:center;gap:10px;padding:10px 20px 9px;font-family:var(--font-hud);font-size:0.54rem;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;background:rgba(0,0,0,0.30);border-bottom:1px solid rgba(139,126,255,0.12)}
    .standings-section-label .label-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
    .rankings-table-wrap{position:relative;overflow:hidden;border:1px solid var(--border-neon)}
    .rankings-table-wrap::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent)}
    .rankings-header-row{display:grid;grid-template-columns:50px 50px 1fr 158px 76px;padding:11px 20px;background:rgba(139,126,255,0.08);border-bottom:1px solid var(--border-neon);font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-soft);align-items:center}
    .rankings-row{display:grid;grid-template-columns:50px 50px 1fr 158px 76px;padding:13px 20px;border-bottom:1px solid rgba(139,126,255,0.08);align-items:center;transition:background .2s;position:relative}
    .rankings-row:last-child{border-bottom:none}
    .rankings-row:hover{background:rgba(139,126,255,0.06)}
    .rankings-row.rank-1::before,.rankings-row.rank-2::before,.rankings-row.rank-3::before{content:'';position:absolute;left:0;top:0;bottom:0;width:2px}
    .rankings-row.rank-1::before{background:var(--creo-volt);box-shadow:0 0 8px rgba(255,233,48,0.60)}
    .rankings-row.rank-2::before{background:var(--prc-ice);box-shadow:0 0 8px rgba(196,238,255,0.50)}
    .rankings-row.rank-3::before{background:var(--creo-amber);box-shadow:0 0 8px rgba(255,160,48,0.50)}
    .rankings-row.rank-1{background:rgba(255,233,48,0.03)}.rankings-row.rank-2{background:rgba(196,238,255,0.02)}.rankings-row.rank-3{background:rgba(255,160,48,0.02)}
    .rank-num-cell{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px}
    .rank-num{font-family:var(--font-hud);font-size:0.95rem;font-weight:900;line-height:1;text-align:center}
    .rank-1 .rank-num{color:var(--creo-volt);text-shadow:0 0 12px rgba(255,233,48,0.70)}
    .rank-2 .rank-num{color:var(--prc-ice);text-shadow:0 0 12px rgba(196,238,255,0.70)}
    .rank-3 .rank-num{color:var(--creo-amber);text-shadow:0 0 12px rgba(255,160,48,0.70)}
    .rank-num.other{color:var(--text-dim);font-size:0.80rem}
    .rank-medal-icon{font-size:0.80rem;display:block;text-align:center;line-height:1;color:var(--creo-volt)}
    .rank-2 .rank-medal-icon{color:var(--prc-ice)}.rank-3 .rank-medal-icon{color:var(--creo-amber)}
    .row-logo-cell{display:flex;align-items:center;justify-content:center}
    .row-logo{width:34px;height:34px;border-radius:50%;object-fit:contain;border:1px solid rgba(139,126,255,0.20);background:rgba(139,126,255,0.06);padding:3px}
    .row-logo-placeholder{width:34px;height:34px;border-radius:50%;border:1px dashed rgba(139,126,255,0.20);background:rgba(139,126,255,0.04);display:flex;align-items:center;justify-content:center;font-family:var(--font-hud);font-size:0.44rem;color:var(--text-dim);font-weight:700;text-align:center;line-height:1.1}
    .rank-1 .row-logo{border-color:rgba(255,233,48,0.32);box-shadow:0 0 8px rgba(255,233,48,0.18)}
    .rank-school{display:flex;flex-direction:column;gap:2px}
    .rank-school-name{font-family:var(--font-hud);font-size:0.64rem;font-weight:700;color:var(--text-high);letter-spacing:0.03em}
    .rank-team-name{font-family:var(--font-hud);font-size:0.50rem;color:var(--text-soft);letter-spacing:0.08em;text-transform:uppercase}
    .rank-category-badge{display:inline-flex;align-items:center;font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:3px 9px;border:1px solid;clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%)}
    .badge-rv{color:var(--prc-violet);border-color:rgba(139,126,255,0.30);background:rgba(139,126,255,0.06)}
    .badge-mx{color:var(--creo-sky);border-color:rgba(68,217,255,0.30);background:rgba(68,217,255,0.06)}
    .badge-drone{color:var(--creo-amber);border-color:rgba(255,160,48,0.30);background:rgba(255,160,48,0.06)}
    .badge-award{color:var(--award-gold);border-color:rgba(255,215,0,0.30);background:rgba(255,215,0,0.06)}
    .rank-status{font-family:var(--font-hud);font-size:0.48rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;text-align:center}
    .rank-status.champion{color:var(--creo-volt);text-shadow:0 0 8px rgba(255,233,48,0.55)}
    .rank-status.runner{color:var(--prc-violet)}.rank-status.finalist{color:var(--text-soft)}
    /* SPECIAL AWARDS */
    .award-section{padding:28px 0 20px}
    .award-section-header{margin-bottom:32px}
    .award-section-eyebrow{display:flex;align-items:center;gap:10px;font-family:var(--font-hud);font-size:0.58rem;font-weight:700;letter-spacing:0.22em;text-transform:uppercase;color:var(--text-dim);margin-bottom:10px}
    .award-section-eyebrow::before{content:'//';color:rgba(255,215,0,0.30)}
    .award-section-eyebrow .label-dot{width:6px;height:6px;border-radius:50%;background:var(--award-gold);box-shadow:0 0 8px rgba(255,215,0,0.80);flex-shrink:0;animation:neonPulse 2s ease-in-out infinite}
    .award-section-title{font-family:var(--font-hud);font-size:clamp(1.4rem,2.6vw,2rem);font-weight:900;letter-spacing:0.02em;color:var(--award-gold);text-shadow:0 0 28px rgba(255,215,0,0.40),0 0 60px rgba(255,215,0,0.15);line-height:1.15}
    .award-section-divider{display:flex;align-items:center;gap:14px;margin-top:14px}
    .award-section-divider-line{flex:1;height:1px;background:linear-gradient(90deg,rgba(255,215,0,0.35),transparent)}
    .award-section-divider-diamond{width:7px;height:7px;background:var(--award-gold);transform:rotate(45deg);box-shadow:0 0 10px rgba(255,215,0,0.70);flex-shrink:0}
    .award-photo-grid{display:grid;gap:22px;grid-template-columns:repeat(2,1fr)}
    .award-photo-grid.cols-3{grid-template-columns:repeat(3,1fr)}
    .award-photo-grid.cols-1{grid-template-columns:repeat(1,1fr);max-width:480px}
    .award-photo-card{position:relative;border:1px solid rgba(255,215,0,0.22);background:rgba(255,215,0,0.03);overflow:hidden;aspect-ratio:1/1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;transition:box-shadow .25s,border-color .25s;cursor:pointer}
    .award-photo-card:hover{border-color:rgba(255,215,0,0.55);box-shadow:0 0 28px rgba(255,215,0,0.22)}
    .award-photo-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--award-gold),transparent);box-shadow:0 0 10px rgba(255,215,0,0.55)}
    .award-photo-placeholder{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;background:linear-gradient(160deg,rgba(255,215,0,0.05) 0%,rgba(3,2,13,0.60) 100%)}
    .award-photo-placeholder i{font-size:2.5rem;color:rgba(255,215,0,0.30)}
    .award-photo-placeholder span{font-family:var(--font-hud);font-size:0.65rem;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,215,0,0.25)}
    .award-photo-caption{position:relative;z-index:2;width:100%;padding:16px 16px 18px;background:linear-gradient(to top,rgba(3,2,13,0.97) 55%,transparent 100%);font-family:var(--font-hud);text-align:center;display:flex;flex-direction:column;gap:6px}
    .award-caption-category{font-size:0.62rem;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:var(--creo-amber);text-shadow:0 0 8px rgba(255,160,48,0.60)}
    .award-caption-name{font-size:0.90rem;font-weight:800;letter-spacing:0.03em;text-transform:uppercase;color:#fff;line-height:1.25;text-shadow:0 0 16px rgba(255,255,255,0.25)}
    .award-caption-school{font-size:0.70rem;font-weight:500;letter-spacing:0.04em;text-transform:uppercase;color:var(--prc-ice);line-height:1.4}
    .award-caption-detail{font-size:0.60rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--creo-amber);opacity:.85;margin-top:2px;line-height:1.4}
    .award-photo-card.deep-gradient::after{content:'';position:absolute;left:0;right:0;bottom:0;height:60%;z-index:1;pointer-events:none;background:linear-gradient(to top,rgba(3,2,13,1.00) 0%,rgba(3,2,13,0.98) 35%,rgba(3,2,13,0.85) 60%,transparent 100%)}
    .award-photo-card.deep-gradient .award-photo-caption{z-index:2;background:none}
    /* EMPTY STATE */
    .empty-panel{text-align:center;padding:60px 20px;border:1px dashed rgba(139,126,255,0.18);background:rgba(139,126,255,0.02)}
    .empty-panel p{font-family:var(--font-hud);font-size:0.60rem;color:var(--text-dim);letter-spacing:0.10em}
    /* LIGHTBOX */
    .award-lb-overlay{position:fixed;inset:0;z-index:9500;background:rgba(3,2,13,0.96);backdrop-filter:blur(18px);display:none;align-items:center;justify-content:center;padding:20px}
    .award-lb-overlay.open{display:flex}
    .award-lb-box{position:relative;max-width:860px;width:100%;animation:panelSlide .28s ease both;display:flex;flex-direction:column}
    .award-lb-topbar{position:relative;display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:rgba(255,215,0,0.06);border:1px solid rgba(255,215,0,0.28);border-bottom:none}
    .award-lb-topbar::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--award-gold),transparent);box-shadow:0 0 14px rgba(255,215,0,0.55)}
    .award-lb-id{font-family:var(--font-hud);font-size:0.54rem;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:var(--award-gold);text-shadow:0 0 12px rgba(255,215,0,0.55)}
    .award-lb-close{display:flex;align-items:center;gap:7px;font-family:var(--font-hud);font-size:0.54rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-soft);border:1px solid rgba(139,126,255,0.22);background:transparent;padding:7px 14px;cursor:pointer;transition:all .20s}
    .award-lb-close:hover{color:#fff;border-color:rgba(255,80,80,0.50);background:rgba(255,80,80,0.10);box-shadow:0 0 14px rgba(255,80,80,0.20)}
    .award-lb-img-wrap{position:relative;overflow:hidden;border:1px solid rgba(255,215,0,0.22);border-top:none;border-bottom:none;background:#000;max-height:74vh;display:flex;align-items:center;justify-content:center}
    #awardLbImg{max-width:100%;max-height:74vh;object-fit:contain;display:block}
    .award-lb-nav{position:absolute;top:50%;transform:translateY(-50%);z-index:5;display:flex;align-items:center;justify-content:center;width:44px;height:44px;background:rgba(3,2,13,0.75);border:1px solid rgba(255,215,0,0.30);color:var(--award-gold);font-size:1rem;cursor:pointer;transition:all .20s;backdrop-filter:blur(6px)}
    .award-lb-nav:hover{background:rgba(255,215,0,0.14);border-color:var(--award-gold);box-shadow:0 0 14px rgba(255,215,0,0.35)}
    .award-lb-nav-prev{left:14px}.award-lb-nav-next{right:14px}
    .award-lb-caption{padding:14px 18px;background:rgba(255,215,0,0.04);border:1px solid rgba(255,215,0,0.22);border-top:none;display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
    .award-lb-caption-left{display:flex;flex-direction:column;gap:4px}
    .award-lb-caption-category{font-family:var(--font-hud);font-size:0.52rem;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:var(--creo-amber);text-shadow:0 0 8px rgba(255,160,48,0.55)}
    .award-lb-caption-name{font-family:var(--font-hud);font-size:0.84rem;font-weight:800;color:#fff;letter-spacing:0.03em;line-height:1.2}
    .award-lb-caption-school{font-family:var(--font-hud);font-size:0.60rem;color:var(--prc-ice);letter-spacing:0.04em;line-height:1.4}
    .award-lb-caption-detail{font-family:var(--font-hud);font-size:0.54rem;color:var(--text-soft);letter-spacing:0.04em;line-height:1.4}
    .award-lb-counter{font-family:var(--font-hud);font-size:0.56rem;color:var(--text-dim);letter-spacing:0.10em;white-space:nowrap;align-self:center}
    /* FOOTER */
    footer{background:rgba(0,0,6,0.95);border-top:1px solid var(--border-neon);padding:70px 0 32px}
    .footer-inner{max-width:1340px;margin:0 auto;padding:0 36px}
    .footer-top{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:52px;margin-bottom:52px}
    .footer-brand img{height:36px;width:auto;margin-bottom:16px}
    .footer-brand p{font-size:0.875rem;color:var(--text-mid);line-height:1.80;margin-bottom:22px}
    .footer-contact-list{display:flex;flex-direction:column;gap:11px;margin-bottom:24px}
    .footer-contact-item{display:flex;align-items:center;gap:11px;font-size:0.875rem;color:var(--text-mid)}
    .footer-contact-item i{color:var(--prc-violet);font-size:0.95rem;flex-shrink:0}
    .footer-contact-item a{color:var(--text-mid);transition:color .2s}
    .footer-contact-item a:hover{color:var(--prc-violet)}
    .social-links{display:flex;gap:8px}
    .social-link{width:40px;height:40px;background:rgba(139,126,255,0.04);border:1px solid rgba(139,126,255,0.18);display:flex;align-items:center;justify-content:center;font-size:1rem;color:rgba(139,126,255,0.50);transition:all .25s}
    .social-link:hover{background:rgba(139,126,255,0.12);color:var(--prc-violet);box-shadow:0 0 14px rgba(139,126,255,0.35);border-color:var(--prc-violet)}
    .footer-col h4{font-family:var(--font-hud);font-size:0.65rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;margin-bottom:20px;color:var(--prc-ice)}
    .footer-col ul{display:flex;flex-direction:column;gap:10px}
    .footer-col ul li a{font-size:0.875rem;color:var(--text-mid);transition:all .2s;display:flex;align-items:center;gap:8px}
    .footer-col ul li a i{font-size:0.65rem;color:rgba(139,126,255,0.38);transition:color .2s}
    .footer-col ul li a:hover{color:var(--prc-violet);padding-left:4px}
    .footer-col ul li a:hover i{color:var(--prc-violet)}
    .footer-bottom{padding-top:24px;border-top:1px solid rgba(139,126,255,0.12);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px}
    .footer-bottom p{font-family:var(--font-hud);font-size:0.58rem;color:var(--text-soft);letter-spacing:0.06em}
    .footer-bottom-links{display:flex;gap:22px}
    .footer-bottom-links a{font-family:var(--font-hud);font-size:0.58rem;color:var(--text-soft);transition:color .2s;letter-spacing:0.06em}
    .footer-bottom-links a:hover{color:var(--prc-violet)}
    .reveal{opacity:0;transform:translateY(24px);transition:opacity .55s ease,transform .55s ease}
    .reveal.visible{opacity:1;transform:translateY(0)}
    @media(max-width:1200px){:root{--sidebar-w:270px}}
    @media(max-width:1024px){:root{--sidebar-w:240px}.footer-top{grid-template-columns:1fr 1fr;gap:36px}.event-switcher-inner{gap:32px}.event-year-btn{font-size:1.3rem;padding:8px 22px}}
    @media(max-width:900px){.rankings-shell{grid-template-columns:1fr}.rankings-sidebar{position:static}.sidebar-subs{max-height:none!important}.event-switcher-inner{grid-template-columns:1fr;gap:24px}}
    @media(max-width:768px){:root{--nav-height:62px}body{cursor:auto}button{cursor:pointer}.cursor-dot,.cursor-ring{display:none}.nav-links{display:none}.nav-hamburger{display:flex}.event-switcher-inner{padding:28px 20px 32px}.podium-grid{grid-template-columns:1fr;grid-template-rows:auto auto auto;gap:14px 0}.pod-1{grid-column:1;grid-row:1}.pod-2{grid-column:1;grid-row:2}.pod-3{grid-column:1;grid-row:3}.pod-1 .podium-photo-wrap,.pod-2 .podium-photo-wrap,.pod-3 .podium-photo-wrap{height:185px}.rankings-header-row,.rankings-row{grid-template-columns:42px 42px 1fr 76px}.col-cat{display:none}.footer-top{grid-template-columns:1fr;gap:32px}.footer-bottom{flex-direction:column;text-align:center}.rankings-shell{padding:32px 16px 80px}.award-photo-grid{grid-template-columns:repeat(1,1fr)}.award-photo-grid.cols-3{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:520px){:root{--nav-height:58px}.nav-inner{padding:0 14px}.footer-inner{padding-left:16px;padding-right:16px}.page-hero-inner{padding:0 16px}.nav-brand span{display:none}}
    ::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:var(--bg-void)}::-webkit-scrollbar-thumb{background:var(--prc-violet);box-shadow:0 0 8px rgba(139,126,255,0.70);border-radius:2px}
  </style>
</head>
<body>
  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>
  <div class="hex-grid" aria-hidden="true"></div>
  <div class="page-wrapper">

    <!-- NAV -->
    <?php $activePage = 'rankings'; include 'nav.php'; ?>

    <!-- PAGE HERO -->
    <header class="page-hero">
      <div class="page-hero-scan"></div>
      <div class="page-hero-inner">
        <div class="page-hero-eyebrow"><div class="page-hero-blink"></div>Official Competition Results</div>
        <h1 class="page-hero-title">Rankings &amp; <span class="accent">Results</span></h1>
        <p class="page-hero-desc"><?= htmlspecialchars($hero_desc) ?></p>
        <div class="page-hero-divider" aria-hidden="true">
          <div class="page-hero-divider-line"></div>
          <div class="page-hero-divider-diamond"></div>
          <div class="page-hero-divider-line right"></div>
        </div>
      </div>
    </header>

    <!-- EVENT SWITCHER BAR -->
    <div class="event-switcher-bar">
      <div class="event-switcher-inner">
        <div class="event-switcher-left">
          <div class="event-switcher-label">Select Competition Year</div>
          <div class="event-year-row">
            <?php foreach ($years as $i => $y): ?>
            <button class="event-year-btn<?= $i===0?' active':'' ?>"
              data-year-id="<?= $y['year_id'] ?>"
              data-edition="<?= htmlspecialchars($y['edition']) ?>"
              data-date="<?= htmlspecialchars($y['event_date']) ?>"
              data-venue="<?= htmlspecialchars($y['venue']) ?>"
              onclick="switchYear(this)">
              <?= htmlspecialchars($y['year_label']) ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="event-info-block" id="event-info">
          <?php if (!empty($years)): $fy = $years[0]; ?>
          <div class="event-info-name" id="ev-name">Philippine Robotics Cup <span class="accent"><?= htmlspecialchars($fy['year_label']) ?></span></div>
          <div class="event-info-meta">
            <div class="event-info-row"><i class="fi fi-rr-trophy"></i><span id="ev-edition"><?= htmlspecialchars($fy['edition']) ?></span></div>
            <div class="event-info-row"><i class="fi fi-rr-calendar"></i><span id="ev-date"><?= htmlspecialchars($fy['event_date']) ?></span></div>
            <div class="event-info-row"><i class="fi fi-rr-map-marker"></i><span id="ev-venue"><?= htmlspecialchars($fy['venue']) ?></span></div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- MAIN -->
    <main>
      <div class="rankings-shell">

        <!-- SIDEBAR -->
        <aside class="rankings-sidebar reveal" aria-label="Rankings navigation">
          <?php foreach ($cats as $ci => $cat):
            $cat_subs = $subs_by_cat[$cat['cat_id']] ?? [];
            $color = $cat['cat_color'];
            $isFirstCat = ($ci === 0);
            $firstSubSlug = $cat_subs[0]['sub_slug'] ?? '';
            $activeClass = $isFirstCat ? ('rv' === $color ? 'active' : 'active-'.$color) : '';
          ?>
          <div class="sidebar-section">
            <button class="sidebar-cat-btn <?= $activeClass ?>"
              id="btn-<?= htmlspecialchars($cat['cat_slug']) ?>"
              onclick="selectCat('<?= htmlspecialchars($cat['cat_slug']) ?>')"
              aria-expanded="<?= $isFirstCat ? 'true':'false' ?>">
              <span class="sidebar-cat-logo">
                <?php if ($cat['cat_logo']): ?>
                <img src="<?= htmlspecialchars($cat['cat_logo']) ?>" alt="<?= htmlspecialchars($cat['cat_name']) ?>" />
                <?php else: ?>
                <i class="fi fi-rr-star" style="font-size:1.4rem;color:var(--award-gold);"></i>
                <?php endif; ?>
              </span>
              <span class="sidebar-cat-text">
                <span class="sidebar-cat-name"><?= htmlspecialchars($cat['cat_name']) ?></span>
                <span class="sidebar-cat-count"><?= count($cat_subs) ?> <?= count($cat_subs)===1?'category':'categories' ?></span>
              </span>
              <i class="fi fi-rr-angle-right sidebar-cat-chevron"></i>
            </button>
            <div class="sidebar-subs <?= $isFirstCat?'open':'' ?>" id="subs-<?= htmlspecialchars($cat['cat_slug']) ?>">
              <?php foreach ($cat_subs as $si => $sub): ?>
              <button class="sidebar-sub-btn <?= ($isFirstCat&&$si===0)?'active':'' ?>"
                id="sub-<?= htmlspecialchars($cat['cat_slug']) ?>-<?= htmlspecialchars($sub['sub_slug']) ?>"
                onclick="selectSub('<?= htmlspecialchars($cat['cat_slug']) ?>','<?= htmlspecialchars($sub['sub_slug']) ?>')">
                <span class="sub-num"><?= str_pad($si+1,2,'0',STR_PAD_LEFT) ?></span>
                <?= htmlspecialchars($sub['sub_name']) ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
          <a href="#" class="sidebar-cta"><i class="fi fi-rr-pen-field"></i> &nbsp;Register for PRC 2026</a>
        </aside>

        <!-- PANEL AREA -->
        <section class="rankings-panel" aria-live="polite">
          <?php
          // Build panel ID map: panel-{cat_slug}-{sub_slug}
          $firstPanel = true;
          foreach ($cats as $ci => $cat):
            $cat_subs = $subs_by_cat[$cat['cat_id']] ?? [];
            $color = $cat['cat_color'];
            $isAward = ($color === 'award');
            $stripClass = match($color){
              'mx'=>'strip-mx','drone'=>'strip-drone','award'=>'strip-award',default=>''
            };
            foreach ($cat_subs as $si => $sub):
              $panelId = 'panel-'.$cat['cat_slug'].'-'.$sub['sub_slug'];
              $isActive = $firstPanel;
              $firstPanel = false;

              // Gather entries for ALL years (JS switching handles which year to show)
              // We build data- attributes with year_id keyed arrays
              // For now render all year data; JS hides by year
              // Build podium+table per year
          ?>
          <div class="rank-panel <?= $isActive?'active':'' ?>" id="<?= $panelId ?>">
            <div class="panel-header-strip <?= $stripClass ?>">
              <div class="panel-header-left">
                <span class="panel-header-dot <?= $color ?>"></span>
                <span class="panel-header-title"><?= htmlspecialchars($cat['cat_name']) ?> — <?= htmlspecialchars($sub['sub_name']) ?></span>
              </div>
              <span class="panel-header-tag <?= $color ?>" id="tag-<?= $panelId ?>">PRC <?= htmlspecialchars($years[0]['year_label'] ?? '2025') ?> Results</span>
            </div>

            <?php if ($isAward): ?>
            <!-- SPECIAL AWARD PANEL -->
            <?php
            // Collect all entries across years, group by year for JS
            // For initial display show first year
            $firstYear = $years[0] ?? null;
            $awardEntriesByYear = [];
            foreach ($years as $y) {
                $yEntries = $entries[$y['year_id']][$sub['sub_id']] ?? [];
                $awardEntriesByYear[$y['year_id']] = $yEntries;
            }
            ?>
            <div class="award-section">
              <div class="award-section-header">
                <div class="award-section-eyebrow"><span class="label-dot"></span>Special Award</div>
                <div class="award-section-title"><?= htmlspecialchars($sub['sub_name']) ?></div>
                <div class="award-section-divider"><div class="award-section-divider-line"></div><div class="award-section-divider-diamond"></div></div>
              </div>
              <?php foreach ($years as $y):
                $aEntries = $awardEntriesByYear[$y['year_id']] ?? [];
                $gridCols = count($aEntries) === 1 ? 'cols-1' : (count($aEntries) === 3 ? 'cols-3' : '');
              ?>
              <div class="award-year-block" data-year-block="<?= $y['year_id'] ?>" style="<?= (array_key_first($awardEntriesByYear)===$y['year_id'])?'':'display:none' ?>">
                <?php if (empty($aEntries)): ?>
                <div class="empty-panel"><p>No award entries for <?= htmlspecialchars($y['year_label']) ?> yet.</p></div>
                <?php else: ?>
                <div class="award-photo-grid <?= $gridCols ?>">
                  <?php foreach ($aEntries as $ae): ?>
                  <div class="award-photo-card deep-gradient"
                    data-cat="<?= htmlspecialchars($ae['members'] ?? '') ?>"
                    data-name="<?= htmlspecialchars($ae['school_name']) ?>"
                    data-school="<?= htmlspecialchars($ae['team_name'] ?? '') ?>"
                    data-detail="<?= htmlspecialchars($ae['award_detail'] ?? '') ?>">
                    <?php $aPhoto = $ae['award_photo'] ?? $ae['podium_photo'] ?? ''; ?>
                    <?php if ($aPhoto): ?>
                    <img src="<?= htmlspecialchars($aPhoto) ?>" class="podium-photo"
                      style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;"
                      alt="<?= htmlspecialchars($ae['school_name']) ?>"
                      onerror="this.style.display='none';this.nextElementSibling.style.display='flex';" />
                    <div class="award-photo-placeholder" style="display:none;"><i class="fi fi-rr-star"></i><span>Photo</span></div>
                    <?php else: ?>
                    <div class="award-photo-placeholder"><i class="fi fi-rr-star"></i><span>Photo</span></div>
                    <?php endif; ?>
                    <div class="award-photo-caption">
                      <span class="award-caption-category"><?= htmlspecialchars($ae['members'] ?? '') ?></span>
                      <span class="award-caption-name"><?= htmlspecialchars($ae['school_name']) ?></span>
                      <span class="award-caption-school"><?= htmlspecialchars($ae['team_name'] ?? '') ?></span>
                      <?php if (!empty($ae['award_detail'])): ?>
                      <span class="award-caption-detail"><?= htmlspecialchars($ae['award_detail']) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>

            <?php else: ?>
            <!-- STANDARD RANKING PANEL (podium + table) -->
            <?php foreach ($years as $yi => $y):
              $yEntries = $entries[$y['year_id']][$sub['sub_id']] ?? [];
              $champion  = null; $runner1 = null; $runner2 = null; $rest = [];
              foreach ($yEntries as $e) {
                if ((int)$e['rank_pos']===1 && !$champion)       $champion = $e;
                elseif ((int)$e['rank_pos']===2 && !$runner1)    $runner1  = $e;
                elseif ((int)$e['rank_pos']===3 && !$runner2)    $runner2  = $e;
                else                                               $rest[]   = $e;
              }
            ?>
            <div class="year-block" data-year-block="<?= $y['year_id'] ?>" style="<?= $yi===0?'':'display:none' ?>">
            <?php if (empty($yEntries)): ?>
            <div class="empty-panel"><p>No entries for <?= htmlspecialchars($y['year_label']) ?> yet.</p></div>
            <?php else: ?>

            <!-- PODIUM -->
            <div class="podium-section">
              <p class="podium-label">// <span>Top 3</span> — National Finals <?= htmlspecialchars($y['year_label']) ?></p>
              <div class="podium-grid">
                <!-- 2nd -->
                <div class="pod-2">
                  <div class="podium-card">
                    <div class="podium-card-scan"></div>
                    <div class="podium-rank-badge"><span class="podium-rank-label">2ND</span><i class="fi fi-sr-medal podium-rank-icon"></i></div>
                    <?php if ($runner1): ?>
                    <div class="podium-photo-wrap">
                      <?php if ($runner1['podium_photo']): ?><img src="<?= htmlspecialchars($runner1['podium_photo']) ?>" class="podium-photo" alt="1st Runner-up" /><?php else: ?><div style="width:100%;height:100%;background:rgba(139,126,255,0.06);display:flex;align-items:center;justify-content:center;"><i class="fi fi-rr-image-slash" style="font-size:2rem;color:var(--text-dim);opacity:.4"></i></div><?php endif; ?>
                      <div class="podium-photo-fade"></div>
                    </div>
                    <div class="podium-info">
                      <span class="podium-status-tag"><i class="fi fi-rr-medal"></i> <?= htmlspecialchars($runner1['status_label'] ?? '1st Runner-up') ?></span>
                      <span class="podium-school-name"><?= htmlspecialchars($runner1['school_name']) ?></span>
                      <?php if ($runner1['team_name']): ?><span class="podium-team-name"><?= htmlspecialchars($runner1['team_name']) ?></span><?php endif; ?>
                      <?php if ($runner1['members']): ?><div class="podium-meta-row"><span class="podium-meta-item"><i class="fi fi-rr-user"></i> <?= htmlspecialchars($runner1['members']) ?></span></div><?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="podium-info" style="padding:60px 14px;text-align:center;"><span style="font-family:var(--font-hud);font-size:0.55rem;color:var(--text-dim);letter-spacing:0.10em;">No Data</span></div>
                    <?php endif; ?>
                  </div><div class="podium-step-base"></div>
                </div>
                <!-- 1st -->
                <div class="pod-1">
                  <div class="podium-card">
                    <i class="fi fi-sr-crown crown-float" aria-hidden="true"></i>
                    <div class="podium-card-scan"></div>
                    <div class="podium-rank-badge"><span class="podium-rank-label">1ST</span><i class="fi fi-sr-trophy podium-rank-icon"></i></div>
                    <?php if ($champion): ?>
                    <div class="podium-photo-wrap">
                      <?php if ($champion['podium_photo']): ?><img src="<?= htmlspecialchars($champion['podium_photo']) ?>" class="podium-photo" alt="Champion" /><?php else: ?><div style="width:100%;height:100%;background:rgba(255,233,48,0.06);display:flex;align-items:center;justify-content:center;"><i class="fi fi-rr-image-slash" style="font-size:2rem;color:rgba(255,233,48,0.30)"></i></div><?php endif; ?>
                      <div class="podium-photo-fade"></div>
                    </div>
                    <div class="podium-info">
                      <span class="podium-status-tag"><i class="fi fi-rr-trophy"></i> Champion</span>
                      <span class="podium-school-name"><?= htmlspecialchars($champion['school_name']) ?></span>
                      <?php if ($champion['team_name']): ?><span class="podium-team-name"><?= htmlspecialchars($champion['team_name']) ?></span><?php endif; ?>
                      <?php if ($champion['members']): ?><div class="podium-meta-row"><span class="podium-meta-item"><i class="fi fi-rr-user"></i> <?= htmlspecialchars($champion['members']) ?></span></div><?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="podium-info" style="padding:60px 14px;text-align:center;"><span style="font-family:var(--font-hud);font-size:0.55rem;color:var(--text-dim);letter-spacing:0.10em;">No Data</span></div>
                    <?php endif; ?>
                  </div><div class="podium-step-base"></div>
                </div>
                <!-- 3rd -->
                <div class="pod-3">
                  <div class="podium-card">
                    <div class="podium-card-scan"></div>
                    <div class="podium-rank-badge"><span class="podium-rank-label">3RD</span><i class="fi fi-sr-medal podium-rank-icon"></i></div>
                    <?php if ($runner2): ?>
                    <div class="podium-photo-wrap">
                      <?php if ($runner2['podium_photo']): ?><img src="<?= htmlspecialchars($runner2['podium_photo']) ?>" class="podium-photo" alt="2nd Runner-up" /><?php else: ?><div style="width:100%;height:100%;background:rgba(255,160,48,0.06);display:flex;align-items:center;justify-content:center;"><i class="fi fi-rr-image-slash" style="font-size:2rem;color:rgba(255,160,48,0.30)"></i></div><?php endif; ?>
                      <div class="podium-photo-fade"></div>
                    </div>
                    <div class="podium-info">
                      <span class="podium-status-tag"><i class="fi fi-rr-medal"></i> <?= htmlspecialchars($runner2['status_label'] ?? '2nd Runner-up') ?></span>
                      <span class="podium-school-name"><?= htmlspecialchars($runner2['school_name']) ?></span>
                      <?php if ($runner2['team_name']): ?><span class="podium-team-name"><?= htmlspecialchars($runner2['team_name']) ?></span><?php endif; ?>
                      <?php if ($runner2['members']): ?><div class="podium-meta-row"><span class="podium-meta-item"><i class="fi fi-rr-user"></i> <?= htmlspecialchars($runner2['members']) ?></span></div><?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="podium-info" style="padding:60px 14px;text-align:center;"><span style="font-family:var(--font-hud);font-size:0.55rem;color:var(--text-dim);letter-spacing:0.10em;">No Data</span></div>
                    <?php endif; ?>
                  </div><div class="podium-step-base"></div>
                </div>
              </div>
            </div>

            <!-- STANDINGS TABLE -->
            <div class="standings-section">
              <div class="rankings-table-wrap">
                <div class="standings-section-label" style="color:var(--<?= $color==='rv'?'prc-violet':($color==='mx'?'creo-sky':($color==='drone'?'creo-amber':'award-gold')) ?>);">
                  <span class="label-dot" style="background:var(--<?= $color==='rv'?'prc-violet':($color==='mx'?'creo-sky':($color==='drone'?'creo-amber':'award-gold')) ?>);"></span>
                  Full Standings — <?= htmlspecialchars($sub['sub_name']) ?>
                </div>
                <div class="rankings-header-row">
                  <div>Rank</div><div>Logo</div><div>School / Team</div>
                  <div class="col-cat">Category</div><div style="text-align:center">Status</div>
                </div>
                <?php foreach ($yEntries as $e):
                  $rpos = (int)$e['rank_pos'];
                  $rowCls = $rpos===1?'rank-1':($rpos===2?'rank-2':($rpos===3?'rank-3':''));
                  $statusLbl = $e['status_label'] ?? default_status($rpos);
                  $statusCls = $rpos===1?'champion':($rpos<=3?'runner':'finalist');
                  $initials = school_initials($e['school_name']);
                  $badge = cat_badge_class($color);
                ?>
                <div class="rankings-row <?= $rowCls ?>">
                  <div class="rank-num-cell">
                    <span class="rank-num<?= $rpos>3?' other':'' ?>"><?= $rpos ?></span>
                    <?php if ($rpos===1): ?><i class="fi fi-sr-trophy rank-medal-icon"></i>
                    <?php elseif ($rpos<=3): ?><i class="fi fi-sr-medal rank-medal-icon"></i><?php endif; ?>
                  </div>
                  <div class="row-logo-cell">
                    <?php if ($e['school_logo']): ?>
                    <img src="<?= htmlspecialchars($e['school_logo']) ?>" class="row-logo" alt="<?= htmlspecialchars($e['school_name']) ?>" />
                    <?php else: ?>
                    <div class="row-logo-placeholder"><?= htmlspecialchars($initials) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="rank-school">
                    <span class="rank-school-name"><?= htmlspecialchars($e['school_name']) ?></span>
                    <?php if ($e['team_name']): ?><span class="rank-team-name"><?= htmlspecialchars($e['team_name']) ?></span><?php endif; ?>
                  </div>
                  <div class="col-cat"><span class="rank-category-badge <?= $badge ?>"><?= htmlspecialchars($sub['sub_name']) ?></span></div>
                  <div class="rank-status <?= $statusCls ?>"><?= htmlspecialchars($statusLbl) ?></div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
            </div><!-- /year-block -->
            <?php endforeach; ?>
            <?php endif; // isAward ?>
          </div><!-- /rank-panel -->
          <?php
            endforeach; // subs
          endforeach; // cats
          ?>
        </section>

      </div><!-- /rankings-shell -->
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
              <div class="footer-contact-item"><i class="fi fi-rr-envelope"></i><a href="mailto:info@prc.com">info@prc.com</a></div>
            </div>
            <div class="social-links"><a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank" rel="noopener" class="social-link" aria-label="Facebook"><i class="fi fi-brands-facebook"></i></a></div>
          </div>
          <nav class="footer-col" aria-label="Competition"><h4>Competition</h4><ul><li><a href="categories.php"><i class="fi fi-rr-angle-right"></i>Categories</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Rules &amp; Guidelines</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Schedule</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Past Events</a></li></ul></nav>
          <nav class="footer-col" aria-label="Participate"><h4>Participate</h4><ul><li><a href="#"><i class="fi fi-rr-angle-right"></i>Register Now</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Order Materials</a></li><li><a href="contact.php"><i class="fi fi-rr-angle-right"></i>Contact Us</a></li><li><a href="contact.php"><i class="fi fi-rr-angle-right"></i>FAQ</a></li></ul></nav>
          <nav class="footer-col" aria-label="Resources"><h4>Resources</h4><ul><li><a href="#"><i class="fi fi-rr-angle-right"></i>News &amp; Updates</a></li><li><a href="gallery.php"><i class="fi fi-rr-angle-right"></i>Gallery</a></li><li><a href="index.php#video"><i class="fi fi-rr-angle-right"></i>Videos</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Creotec Philippines</a></li></ul></nav>
        </div>
        <div class="footer-bottom">
          <p>&copy; 2026 Philippine Robotics Cup // Creotec Philippines Inc. All rights reserved.</p>
          <div class="footer-bottom-links"><a href="#">Privacy Policy</a><a href="#">Terms of Use</a></div>
        </div>
      </div>
    </footer>

  </div><!-- /page-wrapper -->

  <!-- AWARD PHOTO LIGHTBOX -->
  <div class="award-lb-overlay" id="awardLbOverlay" role="dialog" aria-modal="true" aria-label="Award photo viewer">
    <div class="award-lb-box">
      <div class="award-lb-topbar">
        <span class="award-lb-id">SPECIAL AWARD — PRC</span>
        <button class="award-lb-close" id="awardLbClose"><i class="fi fi-rr-cross-small"></i> Close</button>
      </div>
      <div class="award-lb-img-wrap">
        <img src="" alt="" id="awardLbImg" />
        <button class="award-lb-nav award-lb-nav-prev" id="awardLbPrev" aria-label="Previous"><i class="fi fi-rr-angle-left"></i></button>
        <button class="award-lb-nav award-lb-nav-next" id="awardLbNext" aria-label="Next"><i class="fi fi-rr-angle-right"></i></button>
      </div>
      <div class="award-lb-caption">
        <div class="award-lb-caption-left">
          <div class="award-lb-caption-category" id="awardLbCategory"></div>
          <div class="award-lb-caption-name" id="awardLbName"></div>
          <div class="award-lb-caption-school" id="awardLbSchool"></div>
          <div class="award-lb-caption-detail" id="awardLbDetail"></div>
        </div>
        <div class="award-lb-counter" id="awardLbCounter">1 / 1</div>
      </div>
    </div>
  </div>

  <script>
  // ── YEAR DATA (PHP → JS) ──────────────────────────────────
  var YEARS = <?php
    $jsYears = [];
    foreach ($years as $y) $jsYears[] = [
      'id'      => (int)$y['year_id'],
      'label'   => $y['year_label'],
      'edition' => $y['edition'],
      'date'    => $y['event_date'],
      'venue'   => $y['venue'],
    ];
    echo json_encode($jsYears);
  ?>;

  var currentYearId = YEARS.length ? YEARS[0].id : 0;
  var currentCat    = '<?= htmlspecialchars($cats[0]['cat_slug'] ?? 'rv') ?>';
  var currentSub    = {};
  <?php foreach ($cats as $ci => $cat):
    $firstSubSlug = ($subs_by_cat[$cat['cat_id']][0]['sub_slug'] ?? '');
  ?>
  currentSub['<?= $cat['cat_slug'] ?>'] = '<?= $firstSubSlug ?>';
  <?php endforeach; ?>

  // ── CURSOR ───────────────────────────────────────────────
  var dot=document.getElementById('cursorDot'),ring=document.getElementById('cursorRing');
  var mx=0,my=0,rx=0,ry=0;
  document.addEventListener('mousemove',function(e){mx=e.clientX;my=e.clientY;dot.style.left=mx+'px';dot.style.top=my+'px';});
  (function animRing(){rx+=(mx-rx)*.12;ry+=(my-ry)*.12;ring.style.left=rx+'px';ring.style.top=ry+'px';requestAnimationFrame(animRing);})();
  document.querySelectorAll('a,button,.podium-card,.award-photo-card').forEach(function(el){
    el.addEventListener('mouseenter',function(){ring.classList.add('hovered');dot.style.background='var(--creo-amber)';dot.style.boxShadow='var(--glow-orange)';});
    el.addEventListener('mouseleave',function(){ring.classList.remove('hovered');dot.style.background='var(--prc-violet)';dot.style.boxShadow='var(--glow-primary)';});
  });

  // ── YEAR SWITCHER ────────────────────────────────────────
  function switchYear(btn) {
    document.querySelectorAll('.event-year-btn').forEach(function(b){b.classList.remove('active');});
    btn.classList.add('active');
    var yr = YEARS.find(function(y){return y.id===parseInt(btn.dataset.yearId);});
    if (!yr) return;
    currentYearId = yr.id;
    document.getElementById('ev-name').innerHTML = 'Philippine Robotics Cup <span class="accent">'+yr.label+'</span>';
    document.getElementById('ev-edition').textContent = yr.edition;
    document.getElementById('ev-date').textContent    = yr.date;
    document.getElementById('ev-venue').textContent   = yr.venue;
    // Update result tags
    document.querySelectorAll('[id^="tag-panel-"]').forEach(function(t){t.textContent='PRC '+yr.label+' Results';});
    // Show correct year-block inside every active panel
    showYearBlocks(yr.id);
  }

  function showYearBlocks(yid) {
    document.querySelectorAll('.year-block,.award-year-block').forEach(function(b){
      b.style.display = parseInt(b.dataset.yearBlock)===yid ? '' : 'none';
    });
  }

  // ── SIDEBAR NAVIGATION ───────────────────────────────────
  var catColors = <?php
    $cc=[];
    foreach($cats as $c) $cc[$c['cat_slug']]=$c['cat_color'];
    echo json_encode($cc);
  ?>;

  function activeClassForColor(color){
    return color==='rv'?'active':('active-'+color);
  }

  function selectCat(cat) {
    var wasActive = (currentCat === cat);
    // collapse all
    <?php foreach ($cats as $c): ?>
    document.getElementById('btn-<?= $c['cat_slug'] ?>').className='sidebar-cat-btn';
    document.getElementById('btn-<?= $c['cat_slug'] ?>').setAttribute('aria-expanded','false');
    document.getElementById('subs-<?= $c['cat_slug'] ?>').classList.remove('open');
    <?php endforeach; ?>
    document.querySelectorAll('.sidebar-sub-btn').forEach(function(b){b.classList.remove('active','active-mx','active-drone','active-award');});
    if (!wasActive) {
      currentCat = cat;
      var catBtn = document.getElementById('btn-'+cat);
      catBtn.classList.add(activeClassForColor(catColors[cat]||'rv'));
      catBtn.setAttribute('aria-expanded','true');
      document.getElementById('subs-'+cat).classList.add('open');
      selectSub(cat, currentSub[cat]||'', true);
    }
  }

  function selectSub(cat, sub, skipCatToggle) {
    if (!skipCatToggle && currentCat !== cat) selectCat(cat);
    currentSub[cat] = sub;
    document.querySelectorAll('.sidebar-sub-btn').forEach(function(b){b.classList.remove('active','active-mx','active-drone','active-award');});
    var subBtn = document.getElementById('sub-'+cat+'-'+sub);
    if (subBtn) subBtn.classList.add(activeClassForColor(catColors[cat]||'rv'));
    document.querySelectorAll('.rank-panel').forEach(function(p){p.classList.remove('active');});
    var panel = document.getElementById('panel-'+cat+'-'+sub);
    if (panel) { panel.classList.add('active'); showYearBlocks(currentYearId); }
  }

  // ── SCROLL REVEAL ────────────────────────────────────────
  var ro=new IntersectionObserver(function(entries){entries.forEach(function(e){if(e.isIntersecting){e.target.classList.add('visible');ro.unobserve(e.target);}});},{threshold:0.06,rootMargin:'0px 0px -20px 0px'});
  document.querySelectorAll('.reveal').forEach(function(el){ro.observe(el);});

  // ── AWARD LIGHTBOX ───────────────────────────────────────
  (function(){
    var overlay=document.getElementById('awardLbOverlay'),lbImg=document.getElementById('awardLbImg');
    var lbCat=document.getElementById('awardLbCategory'),lbName=document.getElementById('awardLbName');
    var lbSchool=document.getElementById('awardLbSchool'),lbDetail=document.getElementById('awardLbDetail');
    var lbCounter=document.getElementById('awardLbCounter');
    var currentSet=[],currentIdx=0;
    function getCardData(card){
      var img=card.querySelector('img');
      return {src:img?img.src:'',alt:img?img.alt:'',cat:card.dataset.cat||'',name:card.dataset.name||'',school:card.dataset.school||'',detail:card.dataset.detail||''};
    }
    function show(idx){
      currentIdx=idx;var d=currentSet[idx];
      lbImg.src=d.src;lbImg.alt=d.alt;lbCat.textContent=d.cat;lbName.textContent=d.name;
      lbSchool.textContent=d.school;lbDetail.textContent=d.detail;
      lbCounter.textContent=(idx+1)+' / '+currentSet.length;
    }
    function open(cards,idx){currentSet=Array.from(cards).map(getCardData);show(idx);overlay.classList.add('open');document.body.style.overflow='hidden';}
    function close(){overlay.classList.remove('open');document.body.style.overflow='';}
    function nav(dir){var n=currentIdx+dir;if(n<0)n=currentSet.length-1;if(n>=currentSet.length)n=0;show(n);}
    document.getElementById('awardLbClose').addEventListener('click',close);
    overlay.addEventListener('click',function(e){if(e.target===overlay)close();});
    document.getElementById('awardLbPrev').addEventListener('click',function(){nav(-1);});
    document.getElementById('awardLbNext').addEventListener('click',function(){nav(1);});
    document.addEventListener('keydown',function(e){if(!overlay.classList.contains('open'))return;if(e.key==='Escape')close();if(e.key==='ArrowLeft')nav(-1);if(e.key==='ArrowRight')nav(1);});
    function bindCards(){
      document.querySelectorAll('.award-photo-card').forEach(function(card){
        if(card._lbBound)return;card._lbBound=true;
        card.addEventListener('click',function(){
          var grid=card.closest('.award-photo-grid');
          var cards=grid?grid.querySelectorAll('.award-photo-card'):[card];
          var idx=Array.from(cards).indexOf(card);
          open(cards,idx<0?0:idx);
        });
      });
    }
    bindCards();
    var _orig=window.selectSub;
    window.selectSub=function(){_orig.apply(this,arguments);setTimeout(bindCards,50);};
  })();
  </script>
</body>
</html>