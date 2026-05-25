<!DOCTYPE html>
<!-- PRC-WebApp/index.php -->
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#8B7EFF" />
  <meta name="description" content="Philippine Robotics Cup — The premier national robotics competition for Filipino students." />
  <title>Philippine Robotics Cup 2026</title>

  <!-- FAVICON -->
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
      --neon-cyan:    var(--prc-ice);
      --neon-magenta: #CC55FF;
      --neon-volt:    var(--creo-volt);
      --neon-orange:  var(--creo-amber);
      --neon-primary: var(--prc-violet);
      --neon-sky:     var(--creo-sky);
      --bg-void:      #03020D;
      --bg-deep:      #06051A;
      --border-neon:  rgba(139,126,255,0.22);
      --border-hot:   rgba(139,126,255,0.55);
      --glow-cyan:    0 0 18px rgba(196,238,255,0.55), 0 0 55px rgba(196,238,255,0.18);
      --glow-magenta: 0 0 18px rgba(204,85,255,0.55),  0 0 55px rgba(204,85,255,0.18);
      --glow-volt:    0 0 18px rgba(255,233,48,0.60),  0 0 55px rgba(255,233,48,0.20);
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
      --section-pad:  110px 0;
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
      content: '';
      position: fixed; inset: 0; z-index: 9998; pointer-events: none;
      background: repeating-linear-gradient(
        to bottom, transparent, transparent 2px,
        rgba(0,0,0,0.04) 2px, rgba(0,0,0,0.04) 4px
      );
    }

    /* ===== HEX GRID BACKGROUND ===== */
    .hex-grid {
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background-image:
        linear-gradient(rgba(139,126,255,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(139,126,255,0.04) 1px, transparent 1px);
      background-size: 50px 50px;
    }
    .hex-grid::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(ellipse 80% 60% at 50% 0%,  rgba(119,51,255,0.14) 0%, transparent 70%),
                  radial-gradient(ellipse 60% 50% at 100% 100%, rgba(204,85,255,0.07) 0%, transparent 60%),
                  radial-gradient(ellipse 50% 50% at 0% 80%,  rgba(139,126,255,0.09) 0%, transparent 60%);
    }

    /* ===== ANIMATIONS ===== */
    @keyframes glitch1 {
      0%,94%,100% { clip-path: inset(0 0 100% 0); transform: translate(0); }
      95% { clip-path: inset(20% 0 60% 0); transform: translate(-4px, 1px); }
      97% { clip-path: inset(60% 0 10% 0); transform: translate(4px, -1px); }
      99% { clip-path: inset(40% 0 40% 0); transform: translate(-2px, 2px); }
    }
    @keyframes glitch2 {
      0%,96%,100% { clip-path: inset(0 0 100% 0); transform: translate(0); }
      97% { clip-path: inset(10% 0 80% 0); transform: translate(5px, -2px); }
      99% { clip-path: inset(70% 0 5%  0); transform: translate(-5px, 1px); }
    }
    @keyframes flicker {
      0%,100% { opacity:1; } 92% { opacity:1; } 93% { opacity:0.4; } 94% { opacity:1; } 96% { opacity:0.7; } 97% { opacity:1; }
    }
    @keyframes scanDown {
      from { transform: translateY(-100%); }
      to   { transform: translateY(100vh); }
    }
    @keyframes neonPulse {
      0%,100% { opacity:1; } 50% { opacity:0.7; }
    }
    @keyframes fadeInUp {
      from { opacity:0; transform:translateY(28px); }
      to   { opacity:1; transform:translateY(0); }
    }
    @keyframes fadeIn {
      from { opacity:0; } to { opacity:1; }
    }
    @keyframes videoGlowPulse {
      0%,100% { box-shadow: 0 0 30px rgba(139,126,255,0.30), 0 0 80px rgba(119,51,255,0.12); }
      50%      { box-shadow: 0 0 55px rgba(139,126,255,0.55), 0 0 120px rgba(119,51,255,0.22); }
    }
    @keyframes cornerBlink {
      0%,100% { opacity:1; } 50% { opacity:0.35; }
    }
    @keyframes bounce {
      0%,100% { transform: translateX(-50%) translateY(0); }
      50% { transform: translateX(-50%) translateY(10px); }
    }

    .page-wrapper { position: relative; z-index: 1; }

    /* ===== NAV ===== */
    #main-nav {
      position: fixed; top: 0; left: 0; right: 0;
      height: var(--nav-height); z-index: 1000;
      transition: background 0.4s, border-color 0.4s;
      background: transparent;
    }
    #main-nav.scrolled {
      background: rgba(3,2,13,0.94);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border-neon);
      box-shadow: 0 0 30px rgba(139,126,255,0.10);
    }
    .nav-inner {
      max-width: 1340px; margin: 0 auto;
      height: 100%; padding: 0 36px;
      display: flex; align-items: center;
      justify-content: space-between; gap: 16px;
    }
    .nav-logo { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
    .nav-logo img { height: 38px; width: auto; transition: filter 0.3s; }
    .nav-logo:hover img { filter: drop-shadow(0 0 14px rgba(139,126,255,0.75)); }
    .nav-brand { font-family: var(--font-hud); font-weight: 700; font-size: 0.72rem; letter-spacing: 0.06em; line-height: 1.3; color: var(--prc-violet); text-shadow: 0 0 12px rgba(139,126,255,0.65); }
    .nav-brand span { color: var(--text-soft); display: block; font-size: 0.58rem; font-weight: 400; letter-spacing: 0.10em; text-transform: uppercase; margin-top: 1px; }
    .nav-links { display: flex; align-items: center; gap: 2px; }
    .nav-links a {
      font-family: var(--font-hud); font-size: 0.65rem; font-weight: 600;
      color: var(--text-mid); padding: 8px 14px;
      letter-spacing: 0.08em; text-transform: uppercase;
      border-radius: 4px; transition: all 0.2s;
      position: relative; white-space: nowrap;
    }
    .nav-links a:hover { color: var(--prc-violet); text-shadow: 0 0 12px rgba(139,126,255,0.85); }
    .nav-cta {
      background: transparent !important;
      border: 1px solid var(--prc-violet) !important;
      color: var(--prc-violet) !important;
      padding: 8px 20px !important;
      border-radius: 3px !important;
      box-shadow: 0 0 15px rgba(139,126,255,0.28), inset 0 0 15px rgba(139,126,255,0.06) !important;
      transition: all 0.25s !important;
      margin-left: 8px;
      clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
    }
    .nav-cta:hover {
      background: rgba(139,126,255,0.12) !important;
      box-shadow: 0 0 30px rgba(139,126,255,0.52), inset 0 0 20px rgba(139,126,255,0.10) !important;
      color: #fff !important;
    }
    .nav-hamburger {
      display: none; flex-direction: column; justify-content: center; align-items: center;
      gap: 5px; width: 44px; height: 44px; padding: 0; cursor: none;
      background: rgba(139,126,255,0.06); border: 1px solid var(--border-neon);
      border-radius: 4px; flex-shrink: 0; z-index: 1002;
      -webkit-tap-highlight-color: transparent; touch-action: manipulation;
      transition: all 0.2s;
    }
    .nav-hamburger:hover { background: rgba(139,126,255,0.14); box-shadow: 0 0 14px rgba(139,126,255,0.28); }
    .nav-hamburger span { width: 20px; height: 1.5px; background: var(--prc-violet); border-radius: 2px; transition: transform 0.28s, opacity 0.28s; display: block; pointer-events: none; }
    .nav-hamburger.open span:nth-child(1) { transform: rotate(45deg) translate(5px,5px); }
    .nav-hamburger.open span:nth-child(2) { opacity: 0; }
    .nav-hamburger.open span:nth-child(3) { transform: rotate(-45deg) translate(5px,-5px); }
    .nav-mobile {
      display: none; position: fixed; top: var(--nav-height); left: 0; right: 0;
      background: rgba(3,2,13,0.98); backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border-neon);
      padding: 12px 18px 24px; z-index: 1000;
      flex-direction: column; gap: 2px;
      box-shadow: 0 20px 60px rgba(139,126,255,0.09);
    }
    .nav-mobile.open { display: flex; }
    .nav-mobile a {
      font-family: var(--font-hud); font-size: 0.70rem; font-weight: 600;
      color: var(--text-mid); padding: 13px 14px; border-radius: 3px;
      letter-spacing: 0.08em; text-transform: uppercase;
      transition: all 0.2s; display: flex; align-items: center; gap: 12px;
    }
    .nav-mobile a i { font-size: 1rem; color: var(--prc-violet); }
    .nav-mobile a:hover { color: var(--prc-violet); background: rgba(139,126,255,0.07); text-shadow: 0 0 10px rgba(139,126,255,0.55); }
    .nav-mobile .nav-cta { border: 1px solid var(--prc-violet) !important; color: var(--prc-violet) !important; margin-top: 10px; justify-content: center; border-radius: 3px !important; clip-path: none !important; }

    /* ===== HERO ===== */
    #hero {
      position: relative; width: 100%;
      min-height: 100vh; min-height: 100dvh;
      display: flex; align-items: center; justify-content: center; overflow: hidden;
      padding-top: var(--nav-height);
    }
    .hero-bg {
      position: absolute; inset: 0;
      background-image: url('assets/hero-background2.jpg');
      background-size: cover; background-position: center;
      filter: brightness(0.20) saturate(0.35);
    }
    .hero-overlay {
      position: absolute; inset: 0;
      background:
        radial-gradient(ellipse 65% 70% at 25% 50%, rgba(119,51,255,0.18) 0%, transparent 70%),
        radial-gradient(ellipse 50% 50% at 80% 50%, rgba(68,217,255,0.07) 0%, transparent 60%),
        radial-gradient(ellipse 40% 40% at 10% 85%, rgba(139,126,255,0.14) 0%, transparent 60%),
        linear-gradient(to bottom, rgba(3,2,13,0.28) 0%, transparent 35%, rgba(3,2,13,0.62) 100%);
    }
    .hero-scan { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
    .hero-scan::after {
      content: '';
      position: absolute; left: 0; right: 0; height: 2px;
      background: linear-gradient(90deg, transparent, var(--prc-violet), var(--prc-ice), transparent);
      animation: scanDown 6s linear infinite;
      box-shadow: 0 0 18px rgba(139,126,255,0.55);
    }

    /* ===== TWO-COLUMN HERO ===== */
    .hero-content {
      position: relative; z-index: 3;
      width: 100%; max-width: 1340px;
      padding: 36px 36px 64px;
      display: grid;
      grid-template-columns: 5fr 7fr;
      gap: 56px;
      align-items: center;
    }

    /* LEFT */
    .hero-left { display: flex; flex-direction: column; align-items: flex-start; }
    .hero-sys-label {
      display: inline-flex; align-items: center; gap: 10px;
      font-family: var(--font-hud); font-size: 0.60rem; font-weight: 600;
      letter-spacing: 0.18em; text-transform: uppercase;
      color: var(--prc-ice); margin-bottom: 22px;
      animation: fadeIn 1s ease 0.1s both;
    }
    .sys-blink { width: 6px; height: 6px; background: var(--prc-ice); border-radius: 50%; box-shadow: var(--glow-cyan); animation: neonPulse 1.2s ease-in-out infinite; }
    .hero-title {
      font-family: var(--font-hud);
      text-align: left; line-height: 1;
      margin-bottom: 20px; position: relative;
      animation: fadeInUp 1s ease 0.2s both;
    }
    .ht-philippine {
      display: block; font-size: clamp(0.72rem, 1.6vw, 1.05rem);
      font-weight: 700; letter-spacing: 0.30em; text-transform: uppercase;
      color: var(--creo-volt);
      text-shadow: 0 0 18px rgba(255,233,48,0.85), 0 0 55px rgba(255,233,48,0.28);
      animation: flicker 8s ease infinite; margin-bottom: 4px;
    }
    .ht-robotics {
      display: block; font-size: clamp(2.6rem, 6.2vw, 5.2rem);
      font-weight: 900; letter-spacing: -0.01em;
      text-transform: uppercase; line-height: 0.88; color: #fff;
      text-shadow: 0 0 40px rgba(139,126,255,0.45), 0 0 80px rgba(139,126,255,0.15), 0 4px 0 rgba(0,0,0,0.90);
      position: relative;
    }
    .ht-robotics::before, .ht-robotics::after {
      content: attr(data-text); position: absolute; inset: 0;
      color: #fff; font-size: inherit; font-weight: inherit; letter-spacing: inherit;
    }
    .ht-robotics::before { color: var(--prc-ice);    animation: glitch1 7s ease-in-out infinite; mix-blend-mode: screen; }
    .ht-robotics::after  { color: var(--creo-purple); animation: glitch2 7s ease-in-out infinite 0.5s; mix-blend-mode: screen; }
    .ht-cup {
      display: block; font-size: clamp(2.6rem, 6.2vw, 5.2rem);
      font-weight: 900; letter-spacing: 0.14em; text-transform: uppercase; line-height: 0.88;
      background: linear-gradient(90deg, var(--prc-violet) 0%, var(--creo-sky) 100%);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text; filter: drop-shadow(0 0 18px rgba(139,126,255,0.55));
    }
    .ht-divider { display: flex; align-items: center; gap: 12px; margin: 10px 0 8px; }
    .ht-divider-line { flex: 1; max-width: 80px; height: 1px; background: linear-gradient(90deg, transparent, var(--prc-ice)); }
    .ht-divider-line.right { background: linear-gradient(90deg, var(--prc-ice), transparent); }
    .ht-divider-diamond { width: 8px; height: 8px; background: var(--prc-ice); transform: rotate(45deg); box-shadow: var(--glow-cyan); }
    .ht-year {
      display: block; font-size: clamp(1.5rem, 4vw, 3.2rem);
      font-weight: 900; letter-spacing: 0.20em; color: var(--creo-volt);
      text-shadow: 0 0 28px rgba(255,233,48,0.80), 0 0 80px rgba(255,233,48,0.25);
      line-height: 1; animation: flicker 12s ease-in-out infinite 2s;
    }
    .hero-tagline {
      font-family: var(--font-body); font-size: clamp(0.84rem, 1.4vw, 0.96rem);
      color: var(--text-mid); max-width: 460px;
      margin: 0 0 26px; line-height: 1.78;
      animation: fadeInUp 1s ease 0.35s both;
    }
    .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 34px; animation: fadeInUp 1s ease 0.48s both; }

    /* COUNTDOWN */
    .hero-countdown { animation: fadeInUp 1s ease 0.60s both; width: 100%; }
    .countdown-label {
      font-family: var(--font-hud); font-size: 0.54rem; letter-spacing: 0.18em;
      color: var(--text-soft); margin-bottom: 10px; text-transform: uppercase;
      display: flex; align-items: center; gap: 10px;
    }
    .countdown-label::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, rgba(139,126,255,0.48), transparent); }
    .countdown-grid { display: flex; gap: 8px; flex-wrap: wrap; }
    .countdown-item {
      background: rgba(139,126,255,0.05); border: 1px solid rgba(139,126,255,0.22);
      padding: 11px 15px; min-width: 68px; text-align: center;
      position: relative; overflow: hidden;
      clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
      transition: border-color 0.3s, background 0.3s;
    }
    .countdown-item:hover { border-color: var(--prc-violet); background: rgba(139,126,255,0.10); box-shadow: 0 0 20px rgba(139,126,255,0.22); }
    .countdown-item::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, var(--prc-violet), transparent); }
    .countdown-num { font-family: var(--font-hud); font-size: 1.75rem; font-weight: 700; line-height: 1; color: var(--prc-ice); display: block; text-shadow: 0 0 20px rgba(139,126,255,0.7); }
    .countdown-unit { font-family: var(--font-hud); font-size: 0.46rem; text-transform: uppercase; letter-spacing: 0.14em; color: var(--text-soft); margin-top: 4px; display: block; }
    .hero-event-date {
      display: inline-flex; align-items: center; gap: 9px;
      margin-top: 13px; font-family: var(--font-hud); font-size: 0.57rem;
      letter-spacing: 0.10em; text-transform: uppercase; color: var(--text-mid);
      border: 1px solid rgba(255,160,48,0.28); padding: 7px 15px;
      clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
      background: rgba(255,160,48,0.06);
    }
    .hero-event-date i { color: var(--creo-amber); }

    /* RIGHT — VIDEO */
    .hero-right { position: relative; animation: fadeInUp 1s ease 0.30s both; }

    .hero-video-frame {
      position: relative; padding: 2px;
      background: linear-gradient(135deg, rgba(139,126,255,0.85), rgba(68,217,255,0.55), rgba(119,51,255,0.75));
      animation: videoGlowPulse 3s ease-in-out infinite;
      clip-path: polygon(14px 0%, 100% 0%, calc(100% - 14px) 100%, 0% 100%);
    }
    .hero-video-frame::before, .hero-video-frame::after {
      content: ''; position: absolute; width: 22px; height: 22px;
      border-color: var(--prc-ice); border-style: solid; z-index: 5; pointer-events: none;
      animation: cornerBlink 2.5s ease-in-out infinite;
    }
    .hero-video-frame::before { top: -1px; left: -1px; border-width: 2px 0 0 2px; }
    .hero-video-frame::after  { bottom: -1px; right: -1px; border-width: 0 2px 2px 0; }

    .hero-video-inner-wrap {
      position: relative; overflow: hidden; background: #000;
      clip-path: polygon(13px 0%, 100% 0%, calc(100% - 13px) 100%, 0% 100%);
    }
    .hero-video-inner-wrap::before, .hero-video-inner-wrap::after {
      content: ''; position: absolute; width: 20px; height: 20px;
      border-color: var(--creo-amber); border-style: solid; z-index: 5; pointer-events: none;
      animation: cornerBlink 2.5s ease-in-out infinite 1.25s;
    }
    .hero-video-inner-wrap::before { top: 9px; right: 9px; border-width: 2px 2px 0 0; }
    .hero-video-inner-wrap::after  { bottom: 9px; left: 9px; border-width: 0 0 2px 2px; }

    .hero-video-el {
      width: 100%; display: block; aspect-ratio: 16/9; object-fit: cover;
      filter: brightness(0.92) saturate(1.10) contrast(1.04); transition: filter 0.4s;
    }
    .hero-video-frame:hover .hero-video-el { filter: brightness(1.0) saturate(1.2) contrast(1.06); }

    /* HUD overlay — pointer-events: none so the mute button below it still works */
    .hero-video-hud {
      position: absolute; inset: 0; z-index: 4; pointer-events: none;
      background:
        linear-gradient(to bottom, rgba(3,2,13,0.38) 0%, transparent 22%, transparent 72%, rgba(3,2,13,0.58) 100%),
        linear-gradient(to right,  rgba(3,2,13,0.18) 0%, transparent 14%, transparent 86%, rgba(3,2,13,0.18) 100%);
    }
    .video-hud-label {
      position: absolute; top: 13px; left: 17px;
      font-family: var(--font-hud); font-size: 0.50rem; font-weight: 700;
      letter-spacing: 0.16em; text-transform: uppercase;
      color: var(--prc-ice); opacity: 0.88;
      display: flex; align-items: center; gap: 7px;
    }
    .video-hud-label .rec-dot {
      width: 6px; height: 6px; background: #FF4444; border-radius: 50%;
      box-shadow: 0 0 8px rgba(255,68,68,0.80);
      animation: neonPulse 1s ease-in-out infinite;
    }
    .video-hud-title-row {
      position: absolute; bottom: 10px; left: 17px;
      display: flex; flex-direction: column; gap: 1px;
    }
    .video-hud-title {
      font-family: var(--font-hud); font-size: 0.56rem; font-weight: 700;
      color: var(--text-high); letter-spacing: 0.08em;
      text-shadow: 0 0 10px rgba(139,126,255,0.55);
    }
    .video-hud-year {
      font-family: var(--font-hud); font-size: 0.52rem;
      color: var(--creo-volt); letter-spacing: 0.12em;
      text-shadow: 0 0 8px rgba(255,233,48,0.60);
    }

    /* ── MUTE TOGGLE ──
       Lives outside .hero-video-hud so pointer-events work.
       z-index: 6 puts it above the HUD gradient. */
    .video-mute-btn {
      position: absolute;
      bottom: 12px;
      right: 14px;
      z-index: 6;
      width: 34px; height: 34px;
      background: rgba(3,2,13,0.68);
      border: 1px solid rgba(139,126,255,0.50) !important;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer !important;
      transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
      padding: 0;
      flex-shrink: 0;
    }
    .video-mute-btn:hover {
      background: rgba(139,126,255,0.25);
      border-color: var(--prc-violet) !important;
      box-shadow: 0 0 16px rgba(139,126,255,0.50);
    }
    /* SVG icons inside the button */
    .video-mute-btn svg {
      width: 16px; height: 16px;
      fill: none;
      stroke: var(--prc-ice);
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
      display: block;
      pointer-events: none;
      flex-shrink: 0;
    }

    .hero-scroll {
      position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%);
      display: flex; flex-direction: column; align-items: center; gap: 6px;
      animation: bounce 2.4s ease-in-out infinite;
      font-family: var(--font-hud); color: var(--text-dim);
      font-size: 0.52rem; letter-spacing: 0.16em; text-transform: uppercase; z-index: 4;
    }
    .hero-scroll i { font-size: 1rem; }

    /* BUTTONS */
    .btn-neon-primary {
      display: inline-flex; align-items: center; gap: 10px;
      background: transparent; color: var(--prc-violet);
      padding: 12px 28px; font-family: var(--font-hud); font-size: 0.66rem;
      font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
      border: 1px solid var(--prc-violet);
      clip-path: polygon(10px 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%);
      box-shadow: 0 0 18px rgba(139,126,255,0.32), inset 0 0 18px rgba(139,126,255,0.07);
      transition: all 0.25s; position: relative; overflow: hidden;
    }
    .btn-neon-primary::before {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(90deg, transparent, rgba(139,126,255,0.18), transparent);
      transform: translateX(-100%); transition: transform 0.5s;
    }
    .btn-neon-primary:hover {
      background: rgba(139,126,255,0.12);
      box-shadow: 0 0 38px rgba(139,126,255,0.60), inset 0 0 28px rgba(139,126,255,0.12);
      color: #fff; transform: translateY(-2px);
    }
    .btn-neon-primary:hover::before { transform: translateX(100%); }
    .btn-neon-secondary {
      display: inline-flex; align-items: center; gap: 10px;
      background: transparent; color: var(--creo-sky);
      padding: 12px 28px; font-family: var(--font-hud); font-size: 0.66rem;
      font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
      border: 1px solid var(--creo-sky);
      clip-path: polygon(10px 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%);
      box-shadow: 0 0 18px rgba(68,217,255,0.28), inset 0 0 18px rgba(68,217,255,0.06);
      transition: all 0.25s; position: relative; overflow: hidden;
    }
    .btn-neon-secondary:hover {
      background: rgba(68,217,255,0.10);
      box-shadow: 0 0 38px rgba(68,217,255,0.55), inset 0 0 28px rgba(68,217,255,0.10);
      color: #fff; transform: translateY(-2px);
    }

    /* ===== STATS STRIP ===== */
    .stats-strip {
      border-top: 1px solid var(--border-neon); border-bottom: 1px solid var(--border-neon);
      padding: 48px 0; position: relative; overflow: hidden; background: rgba(139,126,255,0.02);
    }
    .stats-strip::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(90deg, transparent 0%, rgba(139,126,255,0.04) 50%, transparent 100%); }
    .stats-inner { max-width: 1340px; margin: 0 auto; padding: 0 36px; display: grid; grid-template-columns: repeat(4,1fr); }
    .stat-item { text-align: center; padding: 14px 20px; border-right: 1px solid var(--border-neon); }
    .stat-item:last-child { border-right: none; }
    .stat-num { font-family: var(--font-hud); font-size: 2.4rem; font-weight: 800; color: var(--prc-violet); display: block; line-height: 1; text-shadow: 0 0 20px rgba(139,126,255,0.70); }
    .stat-label { font-family: var(--font-hud); font-size: 0.58rem; color: var(--text-soft); margin-top: 7px; text-transform: uppercase; letter-spacing: 0.10em; }
    .stat-icon { color: rgba(139,126,255,0.60); font-size: 1.1rem; margin-bottom: 8px; }

    /* ===== SECTION BASE ===== */
    section { padding: var(--section-pad); }
    .section-inner { max-width: 1340px; margin: 0 auto; padding: 0 36px; }
    .section-eyebrow { display: inline-flex; align-items: center; gap: 8px; font-family: var(--font-hud); color: var(--prc-ice); font-size: 0.60rem; font-weight: 700; letter-spacing: 0.20em; text-transform: uppercase; margin-bottom: 16px; }
    .section-eyebrow::before { content: '//'; color: rgba(139,126,255,0.40); font-size: 0.70rem; }
    .section-title { font-family: var(--font-hud); font-size: clamp(1.8rem, 3.8vw, 2.8rem); font-weight: 800; letter-spacing: -0.01em; line-height: 1.08; margin-bottom: 14px; color: #fff; text-shadow: 0 0 40px rgba(139,126,255,0.14); }
    .section-title .accent { color: var(--prc-violet); text-shadow: 0 0 18px rgba(139,126,255,0.65); }
    .section-desc { font-size: 1rem; color: var(--text-mid); max-width: 500px; line-height: 1.78; }

    /* ===== ABOUT ===== */
    #about .section-inner { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; }
    .about-feature-list { display: flex; flex-direction: column; gap: 12px; margin-top: 36px; }
    .about-feature { display: flex; align-items: flex-start; gap: 16px; padding: 16px 20px; background: rgba(139,126,255,0.04); border: 1px solid rgba(139,126,255,0.12); border-left: 2px solid var(--prc-violet); transition: all 0.3s; }
    .about-feature:hover { background: rgba(139,126,255,0.09); border-color: rgba(139,126,255,0.30); border-left-color: var(--creo-volt); box-shadow: 0 0 22px rgba(139,126,255,0.12); transform: translateX(4px); }
    .about-feature-icon { width: 40px; height: 40px; flex-shrink: 0; background: rgba(139,126,255,0.10); border: 1px solid rgba(139,126,255,0.24); display: flex; align-items: center; justify-content: center; color: var(--prc-violet); font-size: 1rem; }
    .about-feature-text h4 { font-family: var(--font-hud); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; color: var(--text-high); margin-bottom: 4px; }
    .about-feature-text p { font-size: 0.875rem; color: var(--text-mid); line-height: 1.60; }
    .about-card { background: rgba(139,126,255,0.04); border: 1px solid var(--border-neon); position: relative; overflow: hidden; box-shadow: 0 0 40px rgba(139,126,255,0.08), inset 0 0 40px rgba(139,126,255,0.02); }
    .about-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, var(--prc-violet), transparent); }
    .about-card-header { background: rgba(139,126,255,0.07); padding: 26px 30px; display: flex; align-items: center; gap: 16px; border-bottom: 1px solid var(--border-neon); }
    .about-card-header img { height: 44px; width: auto; }
    .about-card-header-text h3 { font-family: var(--font-hud); font-size: 0.80rem; font-weight: 700; letter-spacing: 0.06em; color: var(--prc-violet); text-shadow: 0 0 10px rgba(139,126,255,0.55); }
    .about-card-header-text p { font-size: 0.80rem; color: var(--text-soft); margin-top: 3px; }
    .about-card-body { padding: 26px 30px; }
    .about-card-body p { font-size: 0.888rem; color: var(--text-mid); line-height: 1.82; margin-bottom: 22px; }
    .partner-strip { padding-top: 18px; border-top: 1px solid rgba(139,126,255,0.14); }
    .partner-strip-label { font-family: var(--font-hud); font-size: 0.55rem; text-transform: uppercase; letter-spacing: 0.14em; color: var(--text-soft); margin-bottom: 12px; }
    .partner-logos { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .partner-badge { background: rgba(139,126,255,0.08); border: 1px solid rgba(139,126,255,0.24); padding: 4px 12px; font-family: var(--font-hud); font-size: 0.60rem; font-weight: 700; color: var(--prc-violet); letter-spacing: 0.08em; }
    .partner-logos img { height: 20px; width: auto; }
    .about-visual { position: relative; }
    .floating-badge { position: absolute; bottom: -16px; right: -16px; background: transparent; border: 1px solid var(--creo-volt); color: var(--creo-volt); padding: 16px 24px; font-family: var(--font-hud); font-weight: 700; font-size: 0.72rem; text-shadow: 0 0 10px rgba(255,233,48,0.6); box-shadow: 0 0 30px rgba(255,233,48,0.25), inset 0 0 20px rgba(255,233,48,0.06); clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%); letter-spacing: 0.06em; }
    .floating-badge span { display: block; font-size: 1.2rem; font-weight: 900; margin-bottom: 2px; }

    /* ===== CATEGORIES ===== */
    #categories { background: rgba(0,0,8,0.5); }
    .categories-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 52px; flex-wrap: wrap; gap: 24px; }
    .categories-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; }
    .cat-card { background: rgba(0,0,8,0.6); border: 1px solid rgba(255,255,255,0.07); overflow: hidden; position: relative; display: flex; flex-direction: column; transition: all 0.35s cubic-bezier(0.23,1,0.32,1); cursor: none; }
    .cat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; opacity: 0; transition: opacity 0.35s; }
    .cat-card.prc::before   { background: linear-gradient(90deg, transparent, var(--prc-violet), transparent); }
    .cat-card.makex::before { background: linear-gradient(90deg, transparent, var(--creo-sky), transparent); }
    .cat-card.drone::before { background: linear-gradient(90deg, transparent, var(--creo-amber), transparent); }
    .cat-card::after { content: ''; position: absolute; inset: 0; opacity: 0; transition: opacity 0.35s; pointer-events: none; }
    .cat-card.prc::after   { box-shadow: inset 0 0 40px rgba(139,126,255,0.06); }
    .cat-card.makex::after { box-shadow: inset 0 0 40px rgba(204,85,255,0.06); }
    .cat-card.drone::after { box-shadow: inset 0 0 40px rgba(255,160,48,0.06); }
    .cat-card:hover::before, .cat-card:hover::after { opacity: 1; }
    .cat-card.prc:hover   { border-color: rgba(139,126,255,0.35); box-shadow: 0 0 40px rgba(139,126,255,0.12); transform: translateY(-6px); }
    .cat-card.makex:hover { border-color: rgba(204,85,255,0.35); box-shadow: 0 0 40px rgba(204,85,255,0.12); transform: translateY(-6px); }
    .cat-card.drone:hover { border-color: rgba(255,160,48,0.35); box-shadow: 0 0 40px rgba(255,160,48,0.12); transform: translateY(-6px); }
    .cat-img-wrap { position: relative; width: 100%; height: 188px; overflow: hidden; }
    .cat-img-wrap img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.50s; filter: brightness(0.5) saturate(0.6); }
    .cat-card:hover .cat-img-wrap img { transform: scale(1.07); filter: brightness(0.6) saturate(0.8); }
    .cat-img-overlay { position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 30%, var(--bg-void) 100%); }
    .cat-img-badge { position: absolute; bottom: 12px; left: 12px; font-family: var(--font-hud); font-size: 0.58rem; font-weight: 700; letter-spacing: 0.10em; text-transform: uppercase; padding: 4px 12px; background: rgba(3,3,8,0.80); border: 1px solid; clip-path: polygon(4px 0%, 100% 0%, calc(100% - 4px) 100%, 0% 100%); }
    .cat-card.prc   .cat-img-badge { color: var(--prc-violet);  border-color: rgba(139,126,255,0.40); }
    .cat-card.makex .cat-img-badge { color: var(--creo-sky);    border-color: rgba(68,217,255,0.40); }
    .cat-card.drone .cat-img-badge { color: var(--creo-amber);  border-color: rgba(255,160,48,0.40); }
    .cat-body { padding: 22px 26px 26px; display: flex; flex-direction: column; flex: 1; }
    .cat-logo-row { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    .cat-logo { height: 30px; width: auto; }
    .cat-title { font-family: var(--font-hud); font-size: 1rem; font-weight: 700; letter-spacing: 0.04em; margin-bottom: 9px; color: var(--text-high); }
    .cat-card.prc   .cat-title { text-shadow: 0 0 14px rgba(139,126,255,0.45); }
    .cat-card.makex .cat-title { text-shadow: 0 0 14px rgba(68,217,255,0.45); }
    .cat-card.drone .cat-title { text-shadow: 0 0 14px rgba(255,160,48,0.45); }
    .cat-desc { font-size: 0.862rem; color: var(--text-mid); line-height: 1.68; margin-bottom: 16px; flex: 1; }
    .cat-subcats { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 18px; }
    .cat-tag { border: 1px solid; padding: 3px 10px; font-family: var(--font-hud); font-size: 0.56rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; }
    .cat-card.prc   .cat-tag { color: var(--prc-violet);  border-color: rgba(139,126,255,0.20); background: rgba(139,126,255,0.04); }
    .cat-card.makex .cat-tag { color: var(--creo-sky);    border-color: rgba(68,217,255,0.20);  background: rgba(68,217,255,0.04); }
    .cat-card.drone .cat-tag { color: var(--creo-amber);  border-color: rgba(255,160,48,0.20);  background: rgba(255,160,48,0.04); }
    .cat-link { display: inline-flex; align-items: center; gap: 7px; margin-top: auto; font-family: var(--font-hud); font-size: 0.62rem; font-weight: 700; letter-spacing: 0.10em; text-transform: uppercase; padding: 9px 18px; border: 1px solid; align-self: flex-start; clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%); transition: all 0.25s; }
    .cat-card.prc   .cat-link { color: var(--prc-violet);  border-color: rgba(139,126,255,0.34); }
    .cat-card.makex .cat-link { color: var(--creo-sky);    border-color: rgba(68,217,255,0.34); }
    .cat-card.drone .cat-link { color: var(--creo-amber);  border-color: rgba(255,160,48,0.34); }
    .cat-card.prc:hover   .cat-link { background: rgba(139,126,255,0.10); box-shadow: 0 0 14px rgba(139,126,255,0.24); }
    .cat-card.makex:hover .cat-link { background: rgba(68,217,255,0.10);  box-shadow: 0 0 14px rgba(68,217,255,0.24); }
    .cat-card.drone:hover .cat-link { background: rgba(255,160,48,0.10);  box-shadow: 0 0 14px rgba(255,160,48,0.24); }

    /* ===== VIDEO SECTION ===== */
    #video { padding: var(--section-pad); }
    .video-inner { max-width: 1340px; margin: 0 auto; padding: 0 36px; }
    .video-header { text-align: center; margin-bottom: 50px; }
    .video-header .section-eyebrow { display: inline-flex; }
    .video-header .section-desc { margin: 0 auto; }
    .video-outer-wrap { position: relative; max-width: 980px; margin: 0 auto; padding: 1px; background: linear-gradient(135deg, var(--prc-ice), var(--creo-purple), var(--creo-amber)); box-shadow: 0 0 60px rgba(139,126,255,0.20), 0 0 120px rgba(204,85,255,0.10); }
    .video-outer-wrap::before { content: ''; position: absolute; inset: -2px; background: linear-gradient(135deg, var(--prc-violet), var(--creo-sky), var(--creo-amber)); filter: blur(18px); opacity: 0.35; z-index: -1; }
    .video-inner-wrap { overflow: hidden; background: #000; }
    .video-ratio { position: relative; padding-bottom: 56.25%; }
    .video-ratio iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: none; }

    /* ===== HIGHLIGHTS ===== */
    #highlights { background: rgba(0,0,8,0.5); }
    .highlights-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 40px; flex-wrap: wrap; gap: 20px; }
    .highlights-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; grid-template-rows: auto auto; gap: 12px; }
    .highlight-item { position: relative; background: rgba(0,0,8,0.8); border: 1px solid rgba(139,126,255,0.12); overflow: hidden; cursor: none; transition: border-color 0.35s, box-shadow 0.35s, transform 0.35s; }
    .highlight-item:nth-child(1) { grid-row: 1/3; }
    .highlight-img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.45s; filter: brightness(0.55) saturate(0.5); }
    .highlight-item:nth-child(1) .highlight-img { min-height: 410px; }
    .highlight-item:nth-child(n+2) .highlight-img { height: 196px; }
    .highlight-item:hover { border-color: rgba(139,126,255,0.48); box-shadow: 0 0 28px rgba(139,126,255,0.18); transform: scale(1.015); }
    .highlight-item:hover .highlight-img { transform: scale(1.06); filter: brightness(0.70) saturate(0.8); }
    .highlight-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(3,3,8,0.95) 0%, rgba(3,3,8,0.30) 50%, transparent 100%); opacity: 0; transition: opacity 0.35s; display: flex; align-items: flex-end; padding: 18px; }
    .highlight-item:hover .highlight-overlay { opacity: 1; }
    .highlight-caption { font-family: var(--font-hud); font-size: 0.65rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--prc-violet); text-shadow: 0 0 10px rgba(139,126,255,0.70); transform: translateY(6px); transition: transform 0.3s; }
    .highlight-item:hover .highlight-caption { transform: translateY(0); }
    .highlight-num { position: absolute; top: 10px; right: 10px; z-index: 2; font-family: var(--font-hud); font-size: 0.58rem; font-weight: 700; color: var(--text-soft); letter-spacing: 0.05em; }
    .highlight-item::before, .highlight-item::after { content: ''; position: absolute; width: 16px; height: 16px; z-index: 3; border-color: var(--prc-ice); border-style: solid; opacity: 0; transition: opacity 0.3s; }
    .highlight-item::before { top: 8px; left: 8px; border-width: 1.5px 0 0 1.5px; }
    .highlight-item::after  { bottom: 8px; right: 8px; border-width: 0 1.5px 1.5px 0; }
    .highlight-item:hover::before, .highlight-item:hover::after { opacity: 1; }

    /* ===== ORGANIZERS ===== */
    #organizers { padding: 90px 0; }
    .organizers-inner { max-width: 1340px; margin: 0 auto; padding: 0 36px; }
    .organizers-card { background: rgba(139,126,255,0.025); border: 1px solid var(--border-neon); padding: 60px 52px; text-align: center; position: relative; overflow: hidden; box-shadow: 0 0 60px rgba(139,126,255,0.07), inset 0 0 60px rgba(139,126,255,0.02); }
    .organizers-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, var(--prc-violet), transparent); }
    .organizers-card::after  { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(139,126,255,0.42), transparent); }
    .organizers-heading { font-family: var(--font-hud); font-size: 1rem; font-weight: 700; color: var(--prc-violet); letter-spacing: 0.08em; text-shadow: 0 0 18px rgba(139,126,255,0.60); margin-bottom: 8px; }
    .organizers-sub { font-size: 0.900rem; color: var(--text-mid); margin-bottom: 46px; }
    .organizers-row { display: flex; align-items: stretch; justify-content: center; flex-wrap: wrap; }
    .org-item { display: flex; flex-direction: column; align-items: center; gap: 14px; padding: 22px 44px; border-right: 1px solid var(--border-neon); transition: all 0.25s; cursor: none; }
    .org-item:last-child { border-right: none; }
    .org-item:hover { background: rgba(139,126,255,0.06); box-shadow: 0 0 20px rgba(139,126,255,0.10); }
    .org-logo-img { height: 60px; width: auto; object-fit: contain; transition: transform 0.25s, filter 0.25s; }
    .org-item:hover .org-logo-img { transform: scale(1.06); filter: drop-shadow(0 0 8px rgba(139,126,255,0.55)); }
    .org-name { font-family: var(--font-hud); font-size: 0.65rem; font-weight: 600; color: var(--text-mid); letter-spacing: 0.05em; text-align: center; line-height: 1.4; }
    .org-role { font-size: 0.62rem; color: var(--prc-violet); font-weight: 500; font-family: var(--font-hud); opacity: 0.85; }

    /* ===== CTA ===== */
    #cta { padding: var(--section-pad); }
    .cta-card { max-width: 960px; margin: 0 auto; background: rgba(139,126,255,0.04); border: 1px solid var(--prc-violet); padding: 88px 64px; text-align: center; position: relative; overflow: hidden; box-shadow: 0 0 80px rgba(139,126,255,0.18), inset 0 0 80px rgba(139,126,255,0.03); }
    .cta-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--prc-ice), transparent); box-shadow: 0 0 20px rgba(139,126,255,0.8); }
    .cta-card::after  { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--creo-purple), transparent); box-shadow: 0 0 20px rgba(204,85,255,0.8); }
    .cta-corner { position: absolute; width: 24px; height: 24px; border-color: var(--prc-violet); border-style: solid; }
    .cta-corner.tl { top: 12px; left: 12px; border-width: 1.5px 0 0 1.5px; }
    .cta-corner.tr { top: 12px; right: 12px; border-width: 1.5px 1.5px 0 0; }
    .cta-corner.bl { bottom: 12px; left: 12px; border-width: 0 0 1.5px 1.5px; }
    .cta-corner.br { bottom: 12px; right: 12px; border-width: 0 1.5px 1.5px 0; }
    .cta-bg-grid { position: absolute; inset: 0; pointer-events: none; background-image: linear-gradient(rgba(139,126,255,0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(139,126,255,0.05) 1px, transparent 1px); background-size: 40px 40px; mask-image: radial-gradient(ellipse 70% 70% at 50% 50%, black 0%, transparent 100%); }
    .cta-tag { display: inline-flex; align-items: center; gap: 8px; border: 1px solid rgba(255,233,48,0.38); padding: 6px 18px; font-family: var(--font-hud); font-size: 0.60rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--creo-volt); margin-bottom: 22px; position: relative; z-index: 1; clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%); text-shadow: 0 0 10px rgba(255,233,48,0.60); background: rgba(255,233,48,0.05); }
    .cta-title { font-family: var(--font-hud); font-size: clamp(1.8rem, 3.8vw, 2.8rem); font-weight: 800; letter-spacing: -0.01em; line-height: 1.08; margin-bottom: 16px; position: relative; z-index: 1; color: #fff; }
    .cta-title .accent { color: var(--prc-violet); text-shadow: 0 0 18px rgba(139,126,255,0.70); }
    .cta-desc { font-size: 1rem; color: var(--text-mid); max-width: 460px; margin: 0 auto 38px; line-height: 1.78; position: relative; z-index: 1; }
    .cta-actions { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; position: relative; z-index: 1; }

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
    .reveal { opacity:0; transform:translateY(30px); transition: opacity 0.65s ease, transform 0.65s ease; }
    .reveal.visible { opacity:1; transform:translateY(0); }
    .reveal-left { opacity:0; transform:translateX(-24px); transition: opacity 0.65s ease, transform 0.65s ease; }
    .reveal-left.visible { opacity:1; transform:translateX(0); }
    .reveal-delay-1 { transition-delay:0.10s; }
    .reveal-delay-2 { transition-delay:0.20s; }
    .reveal-delay-3 { transition-delay:0.30s; }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 1100px) {
      .hero-content { grid-template-columns: 1fr; gap: 40px; padding-bottom: 80px; }
      .hero-left { align-items: center; }
      .hero-title { text-align: center; }
      .hero-tagline { text-align: center; }
      .hero-actions { justify-content: center; }
      .countdown-label::after { max-width: 60px; }
      .countdown-grid { justify-content: center; }
      .hero-right { max-width: 620px; width: 100%; margin: 0 auto; }
    }
    @media (max-width: 1024px) {
      .stats-inner { grid-template-columns: repeat(2,1fr); }
      .stat-item:nth-child(2) { border-right:none; }
      .stat-item:nth-child(1),.stat-item:nth-child(2) { border-bottom:1px solid var(--border-neon); padding-bottom:24px; }
      .stat-item:nth-child(3),.stat-item:nth-child(4) { padding-top:24px; }
      #about .section-inner { grid-template-columns:1fr; gap:44px; }
      .about-visual { order:-1; } .floating-badge { display:none; }
      .categories-grid { grid-template-columns:1fr 1fr; }
      .categories-grid .cat-card:last-child { grid-column:1/-1; }
      .highlights-grid { grid-template-columns:1fr 1fr; }
      .highlight-item:nth-child(1) { grid-row:auto; grid-column:1/-1; }
      .highlight-item:nth-child(1) .highlight-img { min-height:270px; }
      .footer-top { grid-template-columns:1fr 1fr; gap:36px; }
      .organizers-card { padding:40px 28px; }
      .org-item { padding:18px 28px; }
      .cta-card { padding:64px 36px; }
    }
    @media (max-width: 768px) {
      :root { --section-pad:70px 0; --nav-height:62px; }
      body { cursor: auto; } button { cursor: pointer; }
      .cursor-dot, .cursor-ring { display: none; }
      .nav-links { display:none; } .nav-hamburger { display:flex; }
      .categories-grid { grid-template-columns:1fr; }
      .highlights-grid { grid-template-columns:1fr; }
      .highlight-item:nth-child(1) { grid-column:auto; }
      .highlight-item:nth-child(n+2) .highlight-img { height:200px; }
      .cta-card { padding:48px 24px; }
      .footer-top { grid-template-columns:1fr; gap:32px; }
      .footer-bottom { flex-direction:column; text-align:center; }
      .organizers-row { flex-direction:column; }
      .org-item { border-right:none; border-bottom:1px solid var(--border-neon); width:100%; }
      .org-item:last-child { border-bottom:none; }
    }
    @media (max-width: 520px) {
      :root { --nav-height:58px; }
      .nav-inner { padding:0 14px; }
      .section-inner,.video-inner,.organizers-inner,.footer-inner,.stats-inner { padding:0 16px; }
      .hero-content { padding:16px 16px 68px; }
      .nav-brand span { display:none; }
      .countdown-item { min-width:60px; padding:10px 10px; }
      .countdown-num { font-size:1.45rem; }
      .hero-actions { flex-direction:column; align-items:stretch; }
      .hero-actions .btn-neon-primary, .hero-actions .btn-neon-secondary { justify-content:center; clip-path:none; }
      .btn-neon-primary,.btn-neon-secondary { clip-path:none !important; }
    }
    /* ===== CONTACT BANNER ===== */
    #contact-banner { padding: 0 0 110px; }
    .contact-banner-card {
      display: grid; grid-template-columns: 1fr 1fr; gap: 0;
      border: 1px solid var(--border-neon); position: relative; overflow: hidden;
      background: rgba(139,126,255,0.03);
      box-shadow: 0 0 60px rgba(139,126,255,0.08), inset 0 0 60px rgba(139,126,255,0.02);
    }
    .contact-banner-card::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
      background: linear-gradient(90deg, transparent, var(--creo-sky), var(--prc-violet), transparent);
    }
    .contact-banner-bg-grid {
      position: absolute; inset: 0; pointer-events: none;
      background-image: linear-gradient(rgba(139,126,255,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(139,126,255,0.04) 1px, transparent 1px);
      background-size: 40px 40px;
      mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 0%, transparent 100%);
    }
    .contact-banner-left {
      padding: 56px 52px; border-right: 1px solid var(--border-neon);
      position: relative; z-index: 1;
    }
    .contact-banner-title {
      font-family: var(--font-hud); font-size: clamp(1.5rem, 2.8vw, 2.2rem);
      font-weight: 800; letter-spacing: -0.01em; line-height: 1.1;
      margin-bottom: 14px; color: #fff;
    }
    .contact-banner-title .accent { color: var(--creo-sky); text-shadow: 0 0 18px rgba(68,217,255,0.65); }
    .contact-banner-desc { font-size: 0.92rem; color: var(--text-mid); line-height: 1.78; margin-bottom: 36px; max-width: 400px; }
    .contact-banner-items { display: flex; flex-direction: column; gap: 18px; }
    .contact-banner-item { display: flex; align-items: center; gap: 16px; }
    .contact-banner-icon {
      width: 42px; height: 42px; flex-shrink: 0;
      background: rgba(68,217,255,0.08); border: 1px solid rgba(68,217,255,0.24);
      display: flex; align-items: center; justify-content: center;
      color: var(--creo-sky); font-size: 1rem;
    }
    .contact-banner-item-label { font-family: var(--font-hud); font-size: 0.52rem; letter-spacing: 0.14em; text-transform: uppercase; color: var(--text-soft); margin-bottom: 3px; }
    .contact-banner-item-val { font-size: 0.88rem; color: var(--text-mid); transition: color 0.2s; text-decoration: none; }
    .contact-banner-item-val:hover { color: var(--creo-sky); text-shadow: 0 0 8px rgba(68,217,255,0.50); }

    .contact-banner-right {
      padding: 56px 52px; position: relative; z-index: 1;
      display: flex; align-items: center; justify-content: center;
    }
    .contact-banner-cta-box {
      display: flex; flex-direction: column; align-items: flex-start; gap: 16px;
      max-width: 380px; width: 100%;
    }
    .contact-banner-cta-icon {
      width: 56px; height: 56px;
      background: rgba(68,217,255,0.08); border: 1px solid rgba(68,217,255,0.30);
      display: flex; align-items: center; justify-content: center;
      color: var(--creo-sky);
    }
    .contact-banner-cta-icon svg { width: 28px; height: 28px; color: var(--creo-sky); }
    .contact-banner-cta-title {
      font-family: var(--font-hud); font-size: 1.1rem; font-weight: 700;
      letter-spacing: 0.04em; color: var(--text-high);
      text-shadow: 0 0 14px rgba(68,217,255,0.30);
    }
    .contact-banner-cta-desc { font-size: 0.88rem; color: var(--text-mid); line-height: 1.72; }
    .contact-banner-response-note {
      display: flex; align-items: center; gap: 8px;
      font-family: var(--font-hud); font-size: 0.54rem; letter-spacing: 0.10em;
      text-transform: uppercase; color: var(--text-soft);
      margin-top: 4px;
    }
    .contact-banner-dot {
      width: 7px; height: 7px; border-radius: 50%; background: #44FF88; flex-shrink: 0;
      box-shadow: 0 0 8px rgba(68,255,136,0.70);
      animation: neonPulse 1.8s ease-in-out infinite;
    }
    @media (max-width: 900px) {
      .contact-banner-card { grid-template-columns: 1fr; }
      .contact-banner-left { border-right: none; border-bottom: 1px solid var(--border-neon); padding: 40px 32px; }
      .contact-banner-right { padding: 40px 32px; }
    }
    @media (max-width: 520px) {
      .contact-banner-left, .contact-banner-right { padding: 28px 20px; }
    }

    ::-webkit-scrollbar { width: 4px; }
    ::-webkit-scrollbar-track { background: var(--bg-void); }
    ::-webkit-scrollbar-thumb { background: var(--prc-violet); box-shadow: 0 0 8px rgba(139,126,255,0.70); border-radius: 2px; }

    /* ── ABOUT v2 ─────────────────────────────────────────────── */
  #about { padding: 110px 0; }
 
  .about-wrap {
    max-width: 1340px;
    margin: 0 auto;
    padding: 0 36px;
  }
 
  /* ── INTRO HEADER ── */
  .about-intro {
    max-width: 760px;
    margin: 0 auto 80px;
    text-align: center;
  }
  .about-intro .section-eyebrow {
    justify-content: center;
    margin-bottom: 16px;
  }
  .about-intro-title {
    font-family: var(--font-hud);
    font-size: clamp(1.7rem, 3.5vw, 2.6rem);
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.01em;
    line-height: 1.1;
    margin-bottom: 18px;
    text-shadow: 0 0 40px rgba(139,126,255,0.14);
  }
  .about-intro-title .accent { color: var(--prc-violet); text-shadow: 0 0 18px rgba(139,126,255,0.65); }
  .about-intro-divider {
    display: flex;
    align-items: center;
    gap: 14px;
    margin: 0 auto 22px;
    max-width: 320px;
  }
  .about-intro-divider-line {
    flex: 1; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(139,126,255,0.45));
  }
  .about-intro-divider-line.r {
    background: linear-gradient(90deg, rgba(139,126,255,0.45), transparent);
  }
  .about-intro-divider-diamond {
    width: 7px; height: 7px;
    background: var(--prc-violet);
    transform: rotate(45deg);
    box-shadow: 0 0 10px rgba(139,126,255,0.70);
    flex-shrink: 0;
  }
  .about-intro-text {
    font-size: 1.02rem;
    color: var(--text-mid);
    line-height: 1.80;
  }
 
  /* ── BLOCKS ── */
  .about-blocks { display: flex; flex-direction: column; gap: 0; }
 
  .about-block {
    display: grid;
    grid-template-columns: 1fr 1fr;
    min-height: 420px;
    border: 1px solid rgba(139,126,255,0.13);
    border-bottom: none;
    overflow: hidden;
    position: relative;
    transition: border-color 0.3s;
  }
  .about-block:first-child { border-radius: 2px 2px 0 0; }
  .about-block:last-child  { border-bottom: 1px solid rgba(139,126,255,0.13); border-radius: 0 0 2px 2px; }
  .about-block:hover { border-color: rgba(139,126,255,0.28); }
 
  /* reverse layout for block 2 */
  .about-block.reverse .about-block-text  { order: -1; }
  .about-block.reverse .about-block-carousel { order: 1; border-left: 1px solid rgba(139,126,255,0.13); border-right: none; }
  .about-block:not(.reverse) .about-block-carousel { border-right: 1px solid rgba(139,126,255,0.13); }
 
  /* ── CAROUSEL SIDE ── */
  .about-block-carousel {
    position: relative;
    overflow: hidden;
    background: #000;
    min-height: 420px;
  }
 
  .carousel-track {
    display: flex;
    width: 300%;
    height: 100%;
    transition: transform 0.65s cubic-bezier(0.77,0,0.175,1);
  }
  .carousel-slide {
    width: calc(100% / 3);
    height: 100%;
    flex-shrink: 0;
    position: relative;
  }
  .carousel-slide img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
    filter: brightness(0.55) saturate(0.65);
    transition: filter 0.5s;
  }
  .about-block-carousel:hover .carousel-slide img { filter: brightness(0.68) saturate(0.82); }
 
  /* gradient fade on the text side */
  .about-block:not(.reverse) .about-block-carousel::after {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(to right, transparent 60%, var(--bg-void) 100%),
                linear-gradient(to bottom, rgba(3,2,13,0.18) 0%, transparent 30%, rgba(3,2,13,0.40) 100%);
    pointer-events: none;
  }
  .about-block.reverse .about-block-carousel::after {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(to left, transparent 60%, var(--bg-void) 100%),
                linear-gradient(to bottom, rgba(3,2,13,0.18) 0%, transparent 30%, rgba(3,2,13,0.40) 100%);
    pointer-events: none;
  }
 
  /* carousel controls */
  .carousel-controls {
    position: absolute;
    bottom: 18px;
    left: 0; right: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
  }
  .carousel-btn {
    width: 32px; height: 32px;
    background: rgba(3,2,13,0.70);
    border: 1px solid rgba(139,126,255,0.40) !important;
    color: var(--prc-ice);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem;
    transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
    cursor: pointer !important;
    flex-shrink: 0;
  }
  .carousel-btn:hover {
    background: rgba(139,126,255,0.28);
    border-color: var(--prc-violet) !important;
    box-shadow: 0 0 14px rgba(139,126,255,0.45);
  }
  .carousel-dots {
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .carousel-dot {
    width: 5px; height: 5px;
    border-radius: 50%;
    background: rgba(196,238,255,0.28);
    transition: background 0.25s, transform 0.25s, box-shadow 0.25s;
    cursor: pointer !important;
    border: none !important;
    flex-shrink: 0;
  }
  .carousel-dot.active {
    background: var(--prc-ice);
    transform: scale(1.4);
    box-shadow: 0 0 8px rgba(196,238,255,0.75);
  }
 
  /* block number badge on image */
  .carousel-block-num {
    position: absolute;
    top: 16px; left: 16px;
    z-index: 5;
    font-family: var(--font-hud);
    font-size: 0.52rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--prc-ice);
    background: rgba(3,2,13,0.65);
    border: 1px solid rgba(196,238,255,0.22);
    padding: 5px 12px;
    clip-path: polygon(4px 0%, 100% 0%, calc(100% - 4px) 100%, 0% 100%);
  }
  .about-block.reverse .carousel-block-num { left: auto; right: 16px; }
 
  /* ── TEXT SIDE ── */
  .about-block-text {
    padding: 52px 52px 52px 56px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background: rgba(139,126,255,0.018);
    position: relative;
  }
  .about-block.reverse .about-block-text { padding: 52px 56px 52px 52px; }
 
  /* thin top accent line */
  .about-block-text::before {
    content: '';
    position: absolute; top: 0; left: 56px; right: 52px;
    height: 1px;
    background: linear-gradient(90deg, var(--prc-violet), transparent);
    opacity: 0.35;
  }
  .about-block.reverse .about-block-text::before {
    left: 52px; right: 56px;
    background: linear-gradient(90deg, transparent, var(--prc-violet));
  }
 
  .about-block-label {
    font-family: var(--font-hud);
    font-size: 0.55rem;
    font-weight: 700;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--prc-violet);
    text-shadow: 0 0 10px rgba(139,126,255,0.50);
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .about-block-label::before {
    content: '';
    width: 22px; height: 1px;
    background: var(--prc-violet);
    box-shadow: 0 0 6px rgba(139,126,255,0.60);
    flex-shrink: 0;
  }
 
  .about-block-title {
    font-family: var(--font-hud);
    font-size: clamp(1.3rem, 2.4vw, 1.9rem);
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.01em;
    line-height: 1.12;
    margin-bottom: 20px;
    text-shadow: 0 0 30px rgba(139,126,255,0.10);
  }
  .about-block-title .hl {
    color: var(--prc-violet);
    text-shadow: 0 0 14px rgba(139,126,255,0.55);
  }
 
  .about-block-body {
    font-size: 0.955rem;
    color: var(--text-mid);
    line-height: 1.82;
    margin-bottom: 28px;
  }
 
  /* tag chips */
  .about-block-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: auto;
  }
  .about-block-tag {
    font-family: var(--font-hud);
    font-size: 0.53rem;
    font-weight: 600;
    letter-spacing: 0.10em;
    text-transform: uppercase;
    padding: 4px 12px;
    border: 1px solid rgba(139,126,255,0.22);
    background: rgba(139,126,255,0.05);
    color: var(--text-soft);
    clip-path: polygon(4px 0%, 100% 0%, calc(100% - 4px) 100%, 0% 100%);
    transition: border-color 0.2s, color 0.2s;
  }
  .about-block:hover .about-block-tag {
    border-color: rgba(139,126,255,0.35);
    color: var(--text-mid);
  }
 
  /* ── RESPONSIVE ── */
  @media (max-width: 900px) {
    .about-block {
      grid-template-columns: 1fr;
      min-height: auto;
    }
    .about-block-carousel { min-height: 300px; order: -1 !important; }
    .about-block-text { order: 1 !important; padding: 36px 28px; }
    .about-block-text::before { left: 28px; right: 28px; background: linear-gradient(90deg, var(--prc-violet), transparent); }
    .about-block:not(.reverse) .about-block-carousel { border-right: none; border-bottom: 1px solid rgba(139,126,255,0.13); }
    .about-block.reverse .about-block-carousel { border-left: none; border-bottom: 1px solid rgba(139,126,255,0.13); }
  }
  @media (max-width: 520px) {
    .about-wrap { padding: 0 16px; }
    .about-block-text { padding: 28px 20px; }
    #about { padding: 70px 0; }
  }
  </style>
</head>
<body>

  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>
  <div class="hex-grid" aria-hidden="true"></div>

  <div class="page-wrapper">

    <!-- NAV -->
    <?php $activePage = 'home'; include 'nav.php'; ?>

    <!-- HERO -->
    <section id="hero" aria-label="Hero">
      <div class="hero-bg"></div>
      <div class="hero-overlay"></div>
      <div class="hero-scan"></div>

      <div class="hero-content">

        <!-- LEFT -->
        <div class="hero-left">
          <div class="hero-sys-label">
            <div class="sys-blink"></div>
            SYS://PRC_2026 &mdash; COMPETITION_ACTIVE
          </div>
          <h1 class="hero-title">
            <span class="ht-philippine">Philippine</span>
            <span class="ht-robotics" data-text="Robotics">Robotics</span>
            <span class="ht-cup">Cup</span>
            <div class="ht-divider" aria-hidden="true">
              <div class="ht-divider-line"></div>
              <div class="ht-divider-diamond"></div>
              <div class="ht-divider-line right"></div>
            </div>
            <span class="ht-year">2026</span>
          </h1>
          <p class="hero-tagline">Where young innovators build, code, and compete — shaping the next generation of Filipino STEM leaders on the world stage.</p>
          <div class="hero-actions">
            <a href="#" class="btn-neon-primary"><i class="fi fi-rr-pen-field"></i> Register Your Team</a>
            <a href="#video" class="btn-neon-secondary"><i class="fi fi-rr-play-alt"></i> Watch Highlights</a>
          </div>
          <div class="hero-countdown" aria-label="Countdown to PRC 2026">
            <div class="countdown-label">Competition begins in</div>
            <div class="countdown-grid">
              <div class="countdown-item"><span class="countdown-num" id="cd-days">000</span><span class="countdown-unit">Days</span></div>
              <div class="countdown-item"><span class="countdown-num" id="cd-hours">00</span><span class="countdown-unit">Hours</span></div>
              <div class="countdown-item"><span class="countdown-num" id="cd-minutes">00</span><span class="countdown-unit">Minutes</span></div>
              <div class="countdown-item"><span class="countdown-num" id="cd-seconds">00</span><span class="countdown-unit">Seconds</span></div>
            </div>
            <p class="hero-event-date">
              <i class="fi fi-rr-calendar"></i>
              October 2026 
            </p>
          </div>
        </div>

        <!-- RIGHT: Video -->
        <div class="hero-right">
          <div class="hero-video-frame">
            <div class="hero-video-inner-wrap">

              <!-- autoplay + loop + muted (required for autoplay) -->
              <video
                id="heroVideo"
                class="hero-video-el"
                src="assets/hero-video.mp4"
                autoplay
                muted
                loop
                playsinline
                preload="auto"
                aria-label="Philippine Robotics Cup 2025 highlights"
              ></video>

              <!-- HUD overlay (pointer-events:none — clicks pass through to mute btn) -->
              <div class="hero-video-hud" aria-hidden="true">
                <div class="video-hud-label">
                  <span class="rec-dot"></span>
                  PRC 2025 &nbsp;//&nbsp; COMPETITION HIGHLIGHTS
                </div>
                <div class="video-hud-title-row">
                  <span class="video-hud-title">Philippine Robotics Cup</span>
                  <span class="video-hud-year">&#9654; 2025 SEASON</span>
                </div>
              </div>

              <!--
                Mute toggle button.
                - Placed OUTSIDE .hero-video-hud so it receives click events.
                - Uses inline SVG icons (no icon-font dependency = always renders).
                - #svgMuted shown when muted, #svgSound shown when unmuted.
              -->
              <button
                class="video-mute-btn"
                id="muteBtn"
                type="button"
                aria-label="Unmute video"
                title="Toggle sound"
              >
                <!-- Speaker-with-X (muted state) -->
                <svg id="svgMuted" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                  <line x1="23" y1="9" x2="17" y2="15"/>
                  <line x1="17" y1="9" x2="23" y2="15"/>
                </svg>
                <!-- Speaker-with-waves (sound on) — hidden initially -->
                <svg id="svgSound" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="display:none;">
                  <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                  <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
                  <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
                </svg>
              </button>

            </div>
          </div>
        </div>

      </div><!-- /hero-content -->

      <div class="hero-scroll" aria-hidden="true"><i class="fi fi-rr-angle-double-down"></i> Scroll Down</div>
    </section>

    <!-- STATS -->
    <div class="stats-strip">
      <div class="stats-inner">
        <div class="stat-item reveal"><div class="stat-icon"><i class="fi fi-rr-layers"></i></div><span class="stat-num" data-target="3">0</span><span class="stat-label">Competition Tracks</span></div>
        <div class="stat-item reveal reveal-delay-1"><div class="stat-icon"><i class="fi fi-rr-trophy"></i></div><span class="stat-num" data-target="11">0</span><span class="stat-label">Event Categories</span></div>
        <div class="stat-item reveal reveal-delay-2"><div class="stat-icon"><i class="fi fi-rr-calendar-check"></i></div><span class="stat-num">2025</span><span class="stat-label">Active Since</span></div>
        <div class="stat-item reveal reveal-delay-3"><div class="stat-icon"><i class="fi fi-rr-globe"></i></div><span class="stat-num">1st</span><span class="stat-label">Int&rsquo;l Qualifier (MakeX)</span></div>
      </div>
    </div>

    <!-- ABOUT -->
    <section id="about" aria-labelledby="about-title">
      <div class="about-wrap">
    
        <!-- ── INTRO ── -->
        <div class="about-intro reveal">
          <div class="section-eyebrow">About the Event</div>
          <h2 class="about-intro-title" id="about-title">
            The <span class="accent">Philippine Robotics Cup</span>
          </h2>
          <div class="about-intro-divider">
            <div class="about-intro-divider-line"></div>
            <div class="about-intro-divider-diamond"></div>
            <div class="about-intro-divider-line r"></div>
          </div>
          <p class="about-intro-text">
            A premier national robotics competition where Filipino students design, build, and
            compete with robots — developing real STEM skills and earning their place on the
            international stage. Organized by Creotec Philippines and supported by DepEd,
            DOST, and DICT.
          </p>
        </div>
    
        <!-- ── BLOCKS ── -->
        <div class="about-blocks">
    
          <!-- BLOCK 1: Image Left, Text Right -->
          <div class="about-block reveal"
              data-carousel-id="c1"
              data-images='["assets/highlights/highlight-1.jpg","assets/highlights/highlight-2.jpg","assets/highlights/highlight-roboventure.jpg"]'>
    
            <div class="about-block-carousel">
              <div class="carousel-block-num">PRC 2026 // 01</div>
              <div class="carousel-track" id="c1-track">
                <div class="carousel-slide"><img src="assets/highlights/highlight-1.jpg" alt="Competition floor" /></div>
                <div class="carousel-slide"><img src="assets/highlights/highlight-2.jpg" alt="Robot assembly" /></div>
                <div class="carousel-slide"><img src="assets/highlights/highlight-roboventure.jpg" alt="RoboVenture challenge" /></div>
              </div>
              <div class="carousel-controls">
                <button class="carousel-btn" data-carousel="c1" data-dir="-1" aria-label="Previous image">
                  <i class="fi fi-rr-angle-left"></i>
                </button>
                <div class="carousel-dots" id="c1-dots">
                  <button class="carousel-dot active" data-carousel="c1" data-index="0" aria-label="Image 1"></button>
                  <button class="carousel-dot"        data-carousel="c1" data-index="1" aria-label="Image 2"></button>
                  <button class="carousel-dot"        data-carousel="c1" data-index="2" aria-label="Image 3"></button>
                </div>
                <button class="carousel-btn" data-carousel="c1" data-dir="1" aria-label="Next image">
                  <i class="fi fi-rr-angle-right"></i>
                </button>
              </div>
            </div>
    
            <div class="about-block-text">
              <div class="about-block-label">What It Is</div>
              <h3 class="about-block-title">What is the<br/><span class="hl">Philippine Robotics Cup?</span></h3>
              <p class="about-block-body">
                The Philippine Robotics Cup (PRC) is an outcome-based national competition
                that brings together students from across the Philippines to test their skills
                in robotics, automation, and creative engineering. It is more than a contest —
                it is a platform for discovery, collaboration, and growth in STEM education.
                Teams face real challenges that mirror the demands of tomorrow's technology
                landscape, preparing them for careers in engineering, programming, and design.
              </p>
              <div class="about-block-tags">
                <span class="about-block-tag">Robotics</span>
                <span class="about-block-tag">Automation</span>
                <span class="about-block-tag">STEM Education</span>
                <span class="about-block-tag">National Competition</span>
              </div>
            </div>
    
          </div><!-- /block 1 -->
    
          <!-- BLOCK 2: Text Left, Image Right -->
          <div class="about-block reverse reveal reveal-delay-1"
              data-carousel-id="c2">
    
            <div class="about-block-text">
              <div class="about-block-label">Our Purpose</div>
              <h3 class="about-block-title">Our Mission<br/><span class="hl">and Vision</span></h3>
              <p class="about-block-body">
                We believe every Filipino student deserves the chance to explore technology
                hands-on. The PRC exists to spark curiosity, reward perseverance, and nurture
                the next generation of innovators — no matter where they come from. Our vision
                is a Philippines where young minds lead the world in science and engineering,
                one robot at a time. Through teamwork and creative problem-solving, students
                learn that the future is something they can build with their own hands.
              </p>
              <div class="about-block-tags">
                <span class="about-block-tag">Innovation</span>
                <span class="about-block-tag">Teamwork</span>
                <span class="about-block-tag">Public & Private Schools</span>
                <span class="about-block-tag">Future-Ready</span>
              </div>
            </div>
    
            <div class="about-block-carousel">
              <div class="carousel-block-num">PRC 2026 // 02</div>
              <div class="carousel-track" id="c2-track">
                <div class="carousel-slide"><img src="assets/highlights/highlight-3.jpg" alt="Drone soccer" /></div>
                <div class="carousel-slide"><img src="assets/highlights/highlight-4.jpg" alt="Awarding ceremony" /></div>
                <div class="carousel-slide"><img src="assets/highlights/highlight-makex.jpg" alt="MakeX competition" /></div>
              </div>
              <div class="carousel-controls">
                <button class="carousel-btn" data-carousel="c2" data-dir="-1" aria-label="Previous image">
                  <i class="fi fi-rr-angle-left"></i>
                </button>
                <div class="carousel-dots" id="c2-dots">
                  <button class="carousel-dot active" data-carousel="c2" data-index="0" aria-label="Image 1"></button>
                  <button class="carousel-dot"        data-carousel="c2" data-index="1" aria-label="Image 2"></button>
                  <button class="carousel-dot"        data-carousel="c2" data-index="2" aria-label="Image 3"></button>
                </div>
                <button class="carousel-btn" data-carousel="c2" data-dir="1" aria-label="Next image">
                  <i class="fi fi-rr-angle-right"></i>
                </button>
              </div>
            </div>
    
          </div><!-- /block 2 -->
    
          <!-- BLOCK 3: Image Left, Text Right -->
          <div class="about-block reveal reveal-delay-2"
              data-carousel-id="c3">
    
            <div class="about-block-carousel">
              <div class="carousel-block-num">PRC 2026 // 03</div>
              <div class="carousel-track" id="c3-track">
                <div class="carousel-slide"><img src="assets/highlights/highlight-5.jpg" alt="MakeX build phase" /></div>
                <div class="carousel-slide"><img src="assets/highlights/highlight-drone.jpg" alt="Drone event" /></div>
                <div class="carousel-slide"><img src="assets/highlights/highlight-1.jpg" alt="Finals competition" /></div>
              </div>
              <div class="carousel-controls">
                <button class="carousel-btn" data-carousel="c3" data-dir="-1" aria-label="Previous image">
                  <i class="fi fi-rr-angle-left"></i>
                </button>
                <div class="carousel-dots" id="c3-dots">
                  <button class="carousel-dot active" data-carousel="c3" data-index="0" aria-label="Image 1"></button>
                  <button class="carousel-dot"        data-carousel="c3" data-index="1" aria-label="Image 2"></button>
                  <button class="carousel-dot"        data-carousel="c3" data-index="2" aria-label="Image 3"></button>
                </div>
                <button class="carousel-btn" data-carousel="c3" data-dir="1" aria-label="Next image">
                  <i class="fi fi-rr-angle-right"></i>
                </button>
              </div>
            </div>
    
            <div class="about-block-text">
              <div class="about-block-label">The Experience</div>
              <h3 class="about-block-title">What Participants<br/><span class="hl">Experience</span></h3>
              <p class="about-block-body">
                Participants don't just compete — they grow. From designing and programming
                their first robot to navigating high-pressure competition rounds, students
                develop real-world engineering thinking, resilience under pressure, and
                the confidence to present their ideas. Each category offers a unique challenge:
                robot navigation, drone sports, international MakeX rounds, and creative
                build events that push every team to its limit — and beyond.
              </p>
              <div class="about-block-tags">
                <span class="about-block-tag">Robot Design</span>
                <span class="about-block-tag">Programming</span>
                <span class="about-block-tag">Drone Sports</span>
                <span class="about-block-tag">International Qualifier</span>
              </div>
            </div>
    
          </div><!-- /block 3 -->
    
        </div><!-- /about-blocks -->
    
      </div>
    </section>


    <!-- CATEGORIES -->
    <section id="categories" aria-labelledby="categories-title">
      <div class="section-inner">
        <div class="categories-header">
          <div>
            <div class="section-eyebrow reveal-left">Competition Tracks</div>
            <h2 class="section-title reveal" id="categories-title">Find Your<br/><span class="accent">Competition</span></h2>
            <p class="section-desc reveal">Three major tracks, eleven categories — each designed to challenge students at every level.</p>
          </div>
          <a href="categories.php" class="btn-neon-secondary reveal" style="white-space:nowrap;flex-shrink:0;align-self:flex-end;"><i class="fi fi-rr-apps"></i> All Categories</a>
        </div>
        <div class="categories-grid">
          <div class="cat-card prc reveal">
            <div class="cat-img-wrap"><img src="assets/highlights/highlight-roboventure.jpg" alt="RoboVenture" loading="lazy" /><div class="cat-img-overlay"></div><span class="cat-img-badge">8 Sub-categories</span></div>
            <div class="cat-body"><div class="cat-logo-row"><img src="assets/Roboventure Logo.png" alt="RoboVenture" class="cat-logo" /></div><h3 class="cat-title">RoboVenture</h3><p class="cat-desc">The flagship multi-category track featuring eight unique events — from robot navigation to creative innovation challenges.</p><div class="cat-subcats"><span class="cat-tag">Aspiring Makers</span><span class="cat-tag">Robot Soccer</span><span class="cat-tag">Emerging Innovators</span><span class="cat-tag">Navigation - Auto</span><span class="cat-tag">Line Tracing</span><span class="cat-tag">Sumobot</span></div><a href="#" class="cat-link">Explore <i class="fi fi-rr-arrow-right"></i></a></div>
          </div>
          <div class="cat-card makex reveal reveal-delay-1">
            <div class="cat-img-wrap"><img src="assets/highlights/highlight-makex.jpg" alt="MakeX" loading="lazy" /><div class="cat-img-overlay"></div><span class="cat-img-badge">2 Sub-categories</span></div>
            <div class="cat-body"><div class="cat-logo-row"><img src="assets/Makex logo.png" alt="MakeX" class="cat-logo" /></div><h3 class="cat-title">MakeX</h3><p class="cat-desc">An internationally recognized competition. Winners represent the Philippines at the MakeX World Championships in China.</p><div class="cat-subcats"><span class="cat-tag">MakeX Starter</span><span class="cat-tag">MakeX Explorer</span></div><a href="#" class="cat-link">Explore <i class="fi fi-rr-arrow-right"></i></a></div>
          </div>
          <div class="cat-card drone reveal reveal-delay-2">
            <div class="cat-img-wrap"><img src="assets/highlights/highlight-drone.jpg" alt="Drone Soccer" loading="lazy" /><div class="cat-img-overlay"></div><span class="cat-img-badge">1 Category</span></div>
            <div class="cat-body"><div class="cat-logo-row"><img src="assets/Drone Soccer Logo.png" alt="Drone Soccer" class="cat-logo" /></div><h3 class="cat-title">Drone Soccer</h3><p class="cat-desc">A thrilling aerial sport combining drone piloting skills with team strategy in a high-energy futuristic competition.</p><div class="cat-subcats"><span class="cat-tag">Drone Soccer</span></div><a href="#" class="cat-link">Explore <i class="fi fi-rr-arrow-right"></i></a></div>
          </div>
        </div>
      </div>
    </section>

    <!-- VIDEO SECTION -->
    <section id="video" aria-labelledby="video-title">
      <div class="video-inner">
        <div class="video-header reveal">
          <div class="section-eyebrow" style="display:inline-flex;">Watch the Action</div>
          <h2 class="section-title" id="video-title" style="margin-top:14px;">See It In <span class="accent">Action</span></h2>
          <p class="section-desc">Experience the excitement, energy, and innovation of the Philippine Robotics Cup.</p>
        </div>
        <div class="video-outer-wrap reveal">
          <div class="video-inner-wrap">
            <div class="video-ratio">
              <iframe src="https://www.youtube.com/embed/fis54XtM3NM?rel=0&modestbranding=1&color=white" title="Philippine Robotics Cup Highlights" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- HIGHLIGHTS -->
    <section id="highlights" aria-labelledby="highlights-title">
      <div class="section-inner">
        <div class="highlights-header">
          <div><div class="section-eyebrow reveal-left">Photo Gallery</div><h2 class="section-title reveal" id="highlights-title">Competition<br/><span class="accent">Highlights</span></h2></div>
          <a href="#" class="btn-neon-primary reveal"><i class="fi fi-rr-images"></i> Full Gallery</a>
        </div>
        <div class="highlights-grid">
          <div class="highlight-item reveal"><span class="highlight-num">01</span><img src="assets/highlights/highlight-1.jpg" alt="National Finals floor" class="highlight-img" loading="lazy" /><div class="highlight-overlay"><span class="highlight-caption">Competition Floor — National Finals</span></div></div>
          <div class="highlight-item reveal reveal-delay-1"><span class="highlight-num">02</span><img src="assets/highlights/highlight-2.jpg" alt="Robot assembly" class="highlight-img" loading="lazy" /><div class="highlight-overlay"><span class="highlight-caption">Robot Assembly Challenge</span></div></div>
          <div class="highlight-item reveal reveal-delay-2"><span class="highlight-num">03</span><img src="assets/highlights/highlight-3.jpg" alt="Drone soccer" class="highlight-img" loading="lazy" /><div class="highlight-overlay"><span class="highlight-caption">Drone Soccer — Aerial Battle</span></div></div>
          <div class="highlight-item reveal reveal-delay-1"><span class="highlight-num">04</span><img src="assets/highlights/highlight-4.jpg" alt="Awarding ceremony" class="highlight-img" loading="lazy" /><div class="highlight-overlay"><span class="highlight-caption">Awarding Ceremony — National Champions</span></div></div>
          <div class="highlight-item reveal reveal-delay-2"><span class="highlight-num">05</span><img src="assets/highlights/highlight-5.jpg" alt="MakeX build phase" class="highlight-img" loading="lazy" /><div class="highlight-overlay"><span class="highlight-caption">MakeX Explorer — Build Phase</span></div></div>
        </div>
      </div>
    </section>

    <!-- ORGANIZERS -->
    <section id="organizers" aria-label="Organizers and supporters">
      <div class="organizers-inner">
        <div class="organizers-card reveal">
          <div class="organizers-heading">// Organized &amp; Supported By</div>
          <p class="organizers-sub">The Philippine Robotics Cup is proudly backed by these organizations and government agencies.</p>
          <div class="organizers-row">
            <div class="org-item"><img src="assets/CreoLogo.png" alt="Creotec Philippines" class="org-logo-img" /><div class="org-name">Creotec Philippines Inc.<br/><span class="org-role">Primary Organizer</span></div></div>
            <div class="org-item"><img src="assets/DepED-Logo.png" alt="Department of Education" class="org-logo-img" /><div class="org-name">Dept. of Education<br/><span class="org-role">DepEd</span></div></div>
            <div class="org-item"><img src="assets/DOST-Logo.png" alt="Department of Science and Technology" class="org-logo-img" /><div class="org-name">Dept. of Science &amp; Technology<br/><span class="org-role">DOST</span></div></div>
            <div class="org-item"><img src="assets/DICT-Logo.png" alt="Dept. of ICT" class="org-logo-img" /><div class="org-name">Dept. of ICT<br/><span class="org-role">DICT</span></div></div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section id="cta" aria-label="Call to action">
      <div class="section-inner">
        <div class="cta-card reveal">
          <div class="cta-bg-grid"></div>
          <div class="cta-corner tl"></div><div class="cta-corner tr"></div>
          <div class="cta-corner bl"></div><div class="cta-corner br"></div>
          <div class="cta-tag"><i class="fi fi-rr-calendar"></i> Registration Open // PRC 2026</div>
          <h2 class="cta-title">Ready to Compete<br/>on the <span class="accent">National Stage?</span></h2>
          <p class="cta-desc">Join thousands of Filipino students in the country's most exciting robotics competition. Register your team today.</p>
          <div class="cta-actions">
            <a href="#" class="btn-neon-primary"><i class="fi fi-rr-pen-field"></i> Register Your Team</a>
            <a href="#" class="btn-neon-secondary"><i class="fi fi-rr-shopping-cart"></i> Order Materials</a>
          </div>
        </div>
      </div>
    </section>

    <!-- CONTACT BANNER -->
    <section id="contact-banner" aria-label="Contact us">
      <div class="section-inner">
        <div class="contact-banner-card reveal">
          <div class="contact-banner-bg-grid"></div>
          <!-- Left: text -->
          <div class="contact-banner-left">
            <div class="section-eyebrow" style="margin-bottom:12px;">Get In Touch</div>
            <h2 class="contact-banner-title">Have Questions?<br/>We're <span class="accent">Here to Help.</span></h2>
            <p class="contact-banner-desc">Whether you're a student, teacher, or school coordinator — our team is ready to answer your questions about registration, categories, rules, and more.</p>
            <div class="contact-banner-items">
              <div class="contact-banner-item">
                <div class="contact-banner-icon"><i class="fi fi-rr-envelope"></i></div>
                <div>
                  <div class="contact-banner-item-label">Email Us</div>
                  <a href="mailto:philippineroboticscup@gmail.com" class="contact-banner-item-val">philippineroboticscup@gmail.com</a>
                </div>
              </div>
              <div class="contact-banner-item">
                <div class="contact-banner-icon"><i class="fi fi-rr-phone-call"></i></div>
                <div>
                  <div class="contact-banner-item-label">Call / Viber</div>
                  <a href="tel:+639177713961" class="contact-banner-item-val">+63 917 771 3961</a>
                </div>
              </div>
              <div class="contact-banner-item">
                <div class="contact-banner-icon"><i class="fi fi-brands-facebook"></i></div>
                <div>
                  <div class="contact-banner-item-label">Facebook</div>
                  <a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank" rel="noopener" class="contact-banner-item-val">Philippine Robotics Cup</a>
                </div>
              </div>
            </div>
          </div>
          <!-- Right: CTA to contact page -->
          <div class="contact-banner-right">
            <div class="contact-banner-cta-box">
              <div class="contact-banner-cta-icon">
                <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <rect x="4" y="10" width="40" height="28" rx="3" stroke="currentColor" stroke-width="2"/>
                  <path d="M4 14l20 13 20-13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
              </div>
              <h3 class="contact-banner-cta-title">Send Us a Message</h3>
              <p class="contact-banner-cta-desc">Fill out our contact form and we'll get back to you as soon as possible — usually within one business day.</p>
              <a href="contact.php" class="btn-neon-primary" style="clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%);align-self:flex-start;">
                <i class="fi fi-rr-paper-plane"></i> Go to Contact Page
              </a>
              <div class="contact-banner-response-note">
                <span class="contact-banner-dot"></span>
                Typical response time: within 24 hours
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

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
              <div class="footer-contact-item"><i class="fi fi-rr-envelope"></i><a href="mailto:philippineroboticscup@gmail.com" class="contact-banner-item-val">philippineroboticscup@gmail.com</a></div>
            </div>
            <div class="social-links"><a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank" rel="noopener" class="social-link" aria-label="Facebook"><i class="fi fi-brands-facebook"></i></a></div>
          </div>
          <nav class="footer-col" aria-label="Competition"><h4>Competition</h4><ul><li><a href="#categories"><i class="fi fi-rr-angle-right"></i>Categories</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Rules &amp; Guidelines</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Schedule</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Past Events</a></li></ul></nav>
          <nav class="footer-col" aria-label="Participate"><h4>Participate</h4><ul><li><a href="#"><i class="fi fi-rr-angle-right"></i>Register Now</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Order Materials</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>FAQ</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Contact Us</a></li></ul></nav>
          <nav class="footer-col" aria-label="Resources"><h4>Resources</h4><ul><li><a href="#"><i class="fi fi-rr-angle-right"></i>News &amp; Updates</a></li><li><a href="#highlights"><i class="fi fi-rr-angle-right"></i>Gallery</a></li><li><a href="#video"><i class="fi fi-rr-angle-right"></i>Videos</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>Creotec Philippines</a></li></ul></nav>
        </div>
        <div class="footer-bottom">
          <p>&copy; 2026 Philippine Robotics Cup // Creotec Philippines Inc. All rights reserved.</p>
          <div class="footer-bottom-links"><a href="#">Privacy Policy</a><a href="#">Terms of Use</a></div>
        </div>
      </div>
    </footer>

  </div><!-- /page-wrapper -->

  <script src="nav-loader.js" defer></script>
  <script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script><script>
    // ── CUSTOM CURSOR ──
    var dot = document.getElementById('cursorDot');
    var ring = document.getElementById('cursorRing');
    var mx = 0, my = 0, rx = 0, ry = 0;
    document.addEventListener('mousemove', function(e) {
      mx = e.clientX; my = e.clientY;
      dot.style.left = mx + 'px'; dot.style.top = my + 'px';
    });
    (function animRing() {
      rx += (mx - rx) * 0.12; ry += (my - ry) * 0.12;
      ring.style.left = rx + 'px'; ring.style.top = ry + 'px';
      requestAnimationFrame(animRing);
    })();
    document.querySelectorAll('a, button, .cat-card, .highlight-item, .org-item, .about-feature, .hero-video-frame').forEach(function(el) {
      el.addEventListener('mouseenter', function() { ring.classList.add('hovered'); dot.style.background = 'var(--creo-amber)'; dot.style.boxShadow = 'var(--glow-orange)'; });
      el.addEventListener('mouseleave', function() { ring.classList.remove('hovered'); dot.style.background = 'var(--prc-violet)'; dot.style.boxShadow = 'var(--glow-primary)'; });
    });

    // ── HERO VIDEO: infinite loop + mute toggle ──
    (function() {
      var vid       = document.getElementById('heroVideo');
      var muteBtn   = document.getElementById('muteBtn');
      var svgMuted  = document.getElementById('svgMuted');   // speaker-X icon
      var svgSound  = document.getElementById('svgSound');   // speaker-waves icon

      if (!vid || !muteBtn) return;

      // Must start muted — browsers block autoplay with sound
      vid.muted = true;
      vid.play().catch(function() {});

      // Keep icons in sync with the actual muted state
      function syncIcon() {
        if (vid.muted) {
          svgMuted.style.display = 'block';
          svgSound.style.display = 'none';
          muteBtn.setAttribute('aria-label', 'Unmute video');
        } else {
          svgMuted.style.display = 'none';
          svgSound.style.display = 'block';
          muteBtn.setAttribute('aria-label', 'Mute video');
        }
      }

      muteBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        vid.muted = !vid.muted;
        syncIcon();
      });

      muteBtn.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); vid.muted = !vid.muted; syncIcon(); }
      });

      syncIcon(); // set correct icon on load
    })();

    // ── COUNTDOWN ──
    var TARGET = new Date('2026-10-01T08:00:00+08:00');
    function tick() {
      var diff = TARGET - Date.now(); if (diff < 0) diff = 0;
      var d = Math.floor(diff/86400000), h = Math.floor((diff%86400000)/3600000);
      var m = Math.floor((diff%3600000)/60000),  s = Math.floor((diff%60000)/1000);
      document.getElementById('cd-days').textContent    = String(d).padStart(3,'0');
      document.getElementById('cd-hours').textContent   = String(h).padStart(2,'0');
      document.getElementById('cd-minutes').textContent = String(m).padStart(2,'0');
      document.getElementById('cd-seconds').textContent = String(s).padStart(2,'0');
    }
    tick(); setInterval(tick, 1000);

    // ── SCROLL REVEAL ──
    var revEls = document.querySelectorAll('.reveal, .reveal-left');
    var ro = new IntersectionObserver(function(entries) {
      entries.forEach(function(e) { if (e.isIntersecting) { e.target.classList.add('visible'); ro.unobserve(e.target); } });
    }, { threshold: 0.08, rootMargin: '0px 0px -28px 0px' });
    revEls.forEach(function(el) { ro.observe(el); });

    // ── STAT COUNTER ──
    var counters = document.querySelectorAll('.stat-num[data-target]');
    var co = new IntersectionObserver(function(entries) {
      entries.forEach(function(e) {
        if (!e.isIntersecting || e.target._done) return;
        e.target._done = true;
        var target = parseInt(e.target.getAttribute('data-target'));
        var t0 = Date.now(), dur = 1400;
        (function loop() {
          var p = Math.min((Date.now()-t0)/dur, 1);
          e.target.textContent = Math.floor((1-Math.pow(1-p,3))*target) + '+';
          if (p < 1) requestAnimationFrame(loop); else e.target.textContent = target + '+';
        })();
      });
    }, { threshold: 0.5 });
    counters.forEach(function(el) { co.observe(el); });

    (function() {
      var carousels = {};
    
      function initCarousel(id) {
        var track = document.getElementById(id + '-track');
        var dotsEl = document.getElementById(id + '-dots');
        if (!track || !dotsEl) return;
    
        var state = { current: 0, total: 3, timer: null, paused: false };
        carousels[id] = state;
    
        function goTo(idx) {
          if (idx < 0) idx = state.total - 1;
          if (idx >= state.total) idx = 0;
          state.current = idx;
          track.style.transform = 'translateX(-' + (idx * (100 / 3)) + '%)';
          dotsEl.querySelectorAll('.carousel-dot').forEach(function(d, i) {
            d.classList.toggle('active', i === idx);
          });
        }
    
        function startAuto() {
          clearInterval(state.timer);
          state.timer = setInterval(function() {
            if (!state.paused) goTo(state.current + 1);
          }, 5000);
        }
    
        // pause on hover
        var wrap = track.closest('.about-block-carousel');
        if (wrap) {
          wrap.addEventListener('mouseenter', function() { state.paused = true; });
          wrap.addEventListener('mouseleave', function() { state.paused = false; });
        }
    
        // arrow buttons
        document.querySelectorAll('[data-carousel="' + id + '"][data-dir]').forEach(function(btn) {
          btn.addEventListener('click', function() {
            goTo(state.current + parseInt(btn.getAttribute('data-dir')));
            clearInterval(state.timer);
            startAuto();
          });
        });
    
        // dot buttons
        dotsEl.querySelectorAll('.carousel-dot').forEach(function(dot) {
          dot.addEventListener('click', function() {
            goTo(parseInt(dot.getAttribute('data-index')));
            clearInterval(state.timer);
            startAuto();
          });
        });
    
        startAuto();
      }
    
      // stagger init so autoplay timers don't fire at the same time
      setTimeout(function() { initCarousel('c1'); }, 0);
      setTimeout(function() { initCarousel('c2'); }, 1700);
      setTimeout(function() { initCarousel('c3'); }, 3400);
    })();
  </script>

</body>
</html>