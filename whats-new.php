<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>What's New — Philippine Robotics Cup 2026</title>
  <link rel="icon" type="image/png" href="assets/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-brands/css/uicons-brands.css">
  <style>
    :root{
      --prc-violet:#8B7EFF;--prc-ice:#C4EEFF;--creo-purple:#7733FF;
      --creo-amber:#FFA030;--creo-volt:#FFE930;--creo-sky:#44D9FF;
      --creo-red:#FF4466;
      --neon-primary:var(--prc-violet);
      --bg-void:#03020D;--bg-deep:#06051A;--bg-card:#0e0d24;
      --border-neon:rgba(139,126,255,0.22);
      --glow-primary:0 0 18px rgba(139,126,255,0.60),0 0 55px rgba(139,126,255,0.20);
      --text-high:#F2EEFF;--text-mid:#C8C0F0;--text-soft:#9A90CC;--text-dim:#7068A8;
      --nav-height:72px;--font-hud:'Orbitron',monospace;--font-body:'Exo 2',sans-serif;
      --accent:#7733FF;
      --accent-light:#9B6AFF;
      --accent-glow:rgba(119,51,255,0.70);
      --accent-bg:rgba(119,51,255,0.08);
      --accent-border:rgba(119,51,255,0.35);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    html{scroll-behavior:smooth;}
    body{font-family:var(--font-body);background:var(--bg-void);color:var(--text-high);overflow-x:hidden;line-height:1.6;cursor:none;}
    img{max-width:100%;display:block;}a{text-decoration:none;color:inherit;}ul{list-style:none;}
    button{font-family:inherit;cursor:none;border:none;background:none;}

    .cursor-dot{position:fixed;width:8px;height:8px;border-radius:50%;background:var(--neon-primary);pointer-events:none;z-index:99999;transform:translate(-50%,-50%);box-shadow:var(--glow-primary);transition:transform 0.1s,background 0.2s;}
    .cursor-ring{position:fixed;width:36px;height:36px;border-radius:50%;border:1px solid rgba(139,126,255,0.65);pointer-events:none;z-index:99998;transform:translate(-50%,-50%);transition:width 0.25s,height 0.25s,border-color 0.25s;}
    .cursor-ring.hovered{width:56px;height:56px;border-color:var(--creo-amber);border-width:1.5px;}
    body::after{content:'';position:fixed;inset:0;z-index:9998;pointer-events:none;background:repeating-linear-gradient(to bottom,transparent,transparent 2px,rgba(0,0,0,0.04) 2px,rgba(0,0,0,0.04) 4px);}
    .hex-grid{position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(rgba(139,126,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(139,126,255,0.04) 1px,transparent 1px);background-size:50px 50px;}
    .hex-grid::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(119,51,255,0.18) 0%,transparent 70%);}

    @keyframes scanDown{from{transform:translateY(-100%);}to{transform:translateY(100vh);}}
    @keyframes neonPulse{0%,100%{opacity:1;}50%{opacity:0.65;}}
    @keyframes fadeInUp{from{opacity:0;transform:translateY(28px);}to{opacity:1;transform:translateY(0);}}
    @keyframes fadeIn{from{opacity:0;}to{opacity:1;}}

    /* NAV */
    #main-nav{position:fixed;top:0;left:0;right:0;height:var(--nav-height);z-index:1000;background:rgba(3,2,13,0.94);backdrop-filter:blur(20px);border-bottom:1px solid var(--border-neon);box-shadow:0 0 30px rgba(139,126,255,0.10);}
    .nav-inner{max-width:1340px;margin:0 auto;height:100%;padding:0 36px;display:flex;align-items:center;justify-content:space-between;gap:16px;}
    .nav-logo{display:flex;align-items:center;gap:12px;flex-shrink:0;}
    .nav-logo img{height:38px;width:auto;}
    .nav-logo:hover img{filter:drop-shadow(0 0 14px rgba(139,126,255,0.75));}
    .nav-brand{font-family:var(--font-hud);font-weight:700;font-size:0.72rem;letter-spacing:0.06em;line-height:1.3;color:var(--prc-violet);}
    .nav-brand span{color:var(--text-soft);display:block;font-size:0.58rem;font-weight:400;letter-spacing:0.10em;text-transform:uppercase;margin-top:1px;}
    .nav-links{display:flex;align-items:center;gap:2px;}
    .nav-links a{font-family:var(--font-hud);font-size:0.65rem;font-weight:600;color:var(--text-mid);padding:8px 14px;letter-spacing:0.08em;text-transform:uppercase;transition:all 0.2s;white-space:nowrap;}
    .nav-links a:hover{color:var(--prc-violet);}
    .nav-links a.active-page{color:var(--accent-light);text-shadow:0 0 12px var(--accent-glow);}
    .nav-cta{background:transparent!important;border:1px solid var(--prc-violet)!important;color:var(--prc-violet)!important;padding:8px 20px!important;border-radius:3px!important;box-shadow:0 0 15px rgba(139,126,255,0.28)!important;transition:all 0.25s!important;margin-left:8px;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);}
    .nav-cta:hover{background:rgba(139,126,255,0.12)!important;color:#fff!important;}
    .nav-hamburger{display:none;flex-direction:column;justify-content:center;align-items:center;gap:5px;width:44px;height:44px;padding:0;background:rgba(139,126,255,0.06);border:1px solid var(--border-neon);border-radius:4px;flex-shrink:0;z-index:1002;-webkit-tap-highlight-color:transparent;}
    .nav-hamburger span{width:20px;height:1.5px;background:var(--prc-violet);border-radius:2px;transition:transform 0.28s,opacity 0.28s;display:block;pointer-events:none;}
    .nav-hamburger.open span:nth-child(1){transform:rotate(45deg) translate(5px,5px);}
    .nav-hamburger.open span:nth-child(2){opacity:0;}
    .nav-hamburger.open span:nth-child(3){transform:rotate(-45deg) translate(5px,-5px);}
    .nav-mobile{display:none;position:fixed;top:var(--nav-height);left:0;right:0;background:rgba(3,2,13,0.98);backdrop-filter:blur(20px);border-bottom:1px solid var(--border-neon);padding:12px 18px 24px;z-index:1000;flex-direction:column;gap:2px;}
    .nav-mobile.open{display:flex;}
    .nav-mobile a{font-family:var(--font-hud);font-size:0.70rem;font-weight:600;color:var(--text-mid);padding:13px 14px;letter-spacing:0.08em;text-transform:uppercase;transition:all 0.2s;display:flex;align-items:center;gap:12px;}
    .nav-mobile a i{font-size:1rem;color:var(--prc-violet);}
    .nav-mobile a:hover{color:var(--prc-violet);background:rgba(139,126,255,0.07);}
    .nav-mobile .nav-cta{border:1px solid var(--prc-violet)!important;color:var(--prc-violet)!important;margin-top:10px;justify-content:center;clip-path:none!important;}

    /* BREADCRUMB */
    .breadcrumb-bar{margin-top:var(--nav-height);padding:14px 0;border-bottom:1px solid var(--border-neon);background:rgba(139,126,255,0.02);}
    .breadcrumb-inner{max-width:1340px;margin:0 auto;padding:0 36px;display:flex;align-items:center;gap:10px;font-family:var(--font-hud);font-size:0.58rem;letter-spacing:0.10em;text-transform:uppercase;}
    .breadcrumb-inner a{color:var(--text-dim);transition:color 0.2s;}
    .breadcrumb-inner a:hover{color:var(--prc-violet);}
    .breadcrumb-sep{color:var(--text-dim);font-size:0.52rem;}
    .breadcrumb-current{color:var(--accent-light);}

    /* HERO */
    .page-hero{position:relative;overflow:hidden;padding:80px 0 70px;}
    .page-hero-scan{position:absolute;inset:0;pointer-events:none;overflow:hidden;}
    .page-hero-scan::after{content:'';position:absolute;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--accent),var(--prc-ice),transparent);animation:scanDown 6s linear infinite;box-shadow:0 0 18px var(--accent-glow);}
    .page-hero-inner{max-width:1000px;margin:0 auto;padding:0 36px;text-align:center;}
    .hero-eyebrow{font-family:var(--font-hud);font-size:0.60rem;font-weight:700;letter-spacing:0.22em;text-transform:uppercase;color:var(--accent-light);margin-bottom:18px;display:inline-flex;align-items:center;gap:10px;animation:fadeIn 0.9s ease both;}
    .hero-live{width:8px;height:8px;background:var(--accent);border-radius:50%;box-shadow:0 0 12px var(--accent-glow);animation:neonPulse 1s ease-in-out infinite;flex-shrink:0;}
    .hero-title{font-family:var(--font-hud);font-size:clamp(2.2rem,5.5vw,4rem);font-weight:900;color:#fff;line-height:1.0;margin-bottom:16px;animation:fadeInUp 0.9s ease 0.1s both;}
    .hero-title .accent{color:var(--accent-light);text-shadow:0 0 28px var(--accent-glow),0 0 70px rgba(119,51,255,0.25);}
    .hero-desc{font-size:1rem;color:var(--text-mid);max-width:560px;margin:0 auto;line-height:1.80;animation:fadeInUp 0.9s ease 0.2s both;}

    /* FILTER BAR */
    .filter-bar{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-bottom:52px;}
    .filter-btn{font-family:var(--font-hud);font-size:0.56rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:8px 18px;border:1px solid rgba(139,126,255,0.24);color:var(--text-soft);background:transparent;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);cursor:pointer;transition:all 0.2s;}
    .filter-btn:hover{border-color:var(--prc-violet);color:var(--prc-violet);background:rgba(139,126,255,0.07);}
    .filter-btn.active{border-color:var(--accent);color:var(--accent-light);background:var(--accent-bg);box-shadow:0 0 14px rgba(119,51,255,0.22);}

    /* MAIN LAYOUT */
    .page-wrap{max-width:1000px;margin:0 auto;padding:60px 36px 100px;position:relative;z-index:1;}

    /* PINNED BANNER */
    .pinned-banner{background:var(--accent-bg);border:1px solid var(--accent-border);padding:22px 28px;margin-bottom:48px;position:relative;overflow:hidden;display:flex;align-items:center;gap:20px;flex-wrap:wrap;}
    .pinned-banner::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--accent),transparent);}
    .pinned-icon{font-size:1.6rem;flex-shrink:0;}
    .pinned-content{flex:1;}
    .pinned-label{font-family:var(--font-hud);font-size:0.48rem;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:var(--accent-light);margin-bottom:5px;display:flex;align-items:center;gap:8px;}
    .pinned-dot{width:6px;height:6px;background:var(--accent);border-radius:50%;animation:neonPulse 1s ease-in-out infinite;}
    .pinned-title{font-family:var(--font-hud);font-size:0.88rem;font-weight:800;color:var(--text-high);margin-bottom:4px;}
    .pinned-desc{font-size:0.875rem;color:var(--text-mid);line-height:1.70;}
    .pinned-cta{font-family:var(--font-hud);font-size:0.54rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;padding:9px 20px;border:1px solid var(--accent);color:var(--accent-light);background:transparent;display:inline-flex;align-items:center;gap:7px;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);transition:all 0.22s;flex-shrink:0;}
    .pinned-cta:hover{background:var(--accent-bg);box-shadow:0 0 18px rgba(119,51,255,0.35);color:#fff;}

    /* TIMELINE */
    .timeline{position:relative;}
    .timeline::before{content:'';position:absolute;left:28px;top:0;bottom:0;width:1px;background:linear-gradient(to bottom,var(--accent),var(--prc-violet),rgba(139,126,255,0.10));}

    /* UPDATE CARD */
    .update-card{display:grid;grid-template-columns:56px 1fr;gap:0;margin-bottom:28px;position:relative;opacity:0;transform:translateX(-14px);transition:opacity 0.5s ease,transform 0.5s ease;}
    .update-card.visible{opacity:1;transform:translateX(0);}
    .update-card.hidden{display:none;}

    /* TIMELINE DOT */
    .timeline-dot{display:flex;flex-direction:column;align-items:center;padding-top:20px;position:relative;z-index:1;}
    .dot{width:12px;height:12px;border-radius:50%;border:2px solid;flex-shrink:0;}
    .dot.new{border-color:var(--accent);background:var(--accent);box-shadow:0 0 14px var(--accent-glow);}
    .dot.update{border-color:var(--prc-violet);background:var(--prc-violet);box-shadow:0 0 14px rgba(139,126,255,0.70);}
    .dot.announcement{border-color:var(--creo-volt);background:var(--creo-volt);box-shadow:0 0 14px rgba(255,233,48,0.70);}
    .dot.feature{border-color:var(--creo-sky);background:var(--creo-sky);box-shadow:0 0 14px rgba(68,217,255,0.70);}
    .dot.reminder{border-color:var(--creo-amber);background:var(--creo-amber);box-shadow:0 0 14px rgba(255,160,48,0.70);}

    /* CARD BODY */
    .card-body{background:rgba(0,0,8,0.55);border:1px solid rgba(139,126,255,0.13);padding:22px 26px;margin-left:16px;position:relative;transition:border-color 0.3s,box-shadow 0.3s;}
    .card-body::before{content:'';position:absolute;top:22px;left:-7px;width:12px;height:12px;background:rgba(0,0,8,0.55);border-left:1px solid rgba(139,126,255,0.13);border-bottom:1px solid rgba(139,126,255,0.13);transform:rotate(45deg);}
    .card-body:hover{border-color:rgba(139,126,255,0.28);box-shadow:0 0 24px rgba(139,126,255,0.08);}
    .update-card.is-new .card-body{border-color:var(--accent-border);}
    .update-card.is-new .card-body::after{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--accent),transparent);}
    .update-card.is-new .card-body:hover{border-color:rgba(119,51,255,0.50);box-shadow:0 0 24px rgba(119,51,255,0.12);}

    .card-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;}
    .type-badge{font-family:var(--font-hud);font-size:0.44rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;padding:3px 10px;border:1px solid;display:inline-flex;align-items:center;gap:5px;}
    .type-badge.new{color:var(--accent-light);border-color:var(--accent-border);background:var(--accent-bg);}
    .type-badge.update{color:var(--prc-violet);border-color:rgba(139,126,255,0.30);background:rgba(139,126,255,0.06);}
    .type-badge.announcement{color:var(--creo-volt);border-color:rgba(255,233,48,0.30);background:rgba(255,233,48,0.06);}
    .type-badge.feature{color:var(--creo-sky);border-color:rgba(68,217,255,0.28);background:rgba(68,217,255,0.05);}
    .type-badge.reminder{color:var(--creo-amber);border-color:rgba(255,160,48,0.28);background:rgba(255,160,48,0.05);}
    .badge-dot{width:4px;height:4px;border-radius:50%;background:currentColor;flex-shrink:0;}
    .new-pill{font-family:var(--font-hud);font-size:0.42rem;font-weight:900;letter-spacing:0.14em;text-transform:uppercase;padding:2px 8px;background:var(--accent);color:#fff;animation:neonPulse 1.2s ease-in-out infinite;}
    .card-date{font-family:var(--font-hud);font-size:0.48rem;color:var(--text-dim);letter-spacing:0.10em;}
    .card-title{font-family:var(--font-hud);font-size:0.84rem;font-weight:800;color:var(--text-high);margin-bottom:8px;line-height:1.3;}
    .card-body-text{font-size:0.875rem;color:var(--text-mid);line-height:1.78;}
    .card-body-text p{margin-bottom:8px;}
    .card-body-text p:last-child{margin-bottom:0;}
    .card-body-text strong{color:var(--text-high);}
    .card-body-text ul{margin:8px 0 8px 18px;display:flex;flex-direction:column;gap:4px;}
    .card-body-text ul li{list-style:disc;color:var(--text-mid);}
    .card-link{display:inline-flex;align-items:center;gap:7px;margin-top:14px;font-family:var(--font-hud);font-size:0.52rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:7px 16px;border:1px solid rgba(139,126,255,0.26);color:var(--prc-violet);clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%);transition:all 0.22s;}
    .card-link:hover{background:rgba(139,126,255,0.10);box-shadow:0 0 14px rgba(139,126,255,0.24);color:#fff;}
    .card-link.purple{border-color:var(--accent-border);color:var(--accent-light);}
    .card-link.purple:hover{background:var(--accent-bg);box-shadow:0 0 14px rgba(119,51,255,0.30);}
    .card-link.amber{border-color:rgba(255,160,48,0.30);color:var(--creo-amber);}
    .card-link.amber:hover{background:rgba(255,160,48,0.10);box-shadow:0 0 14px rgba(255,160,48,0.24);}

    /* MONTH DIVIDER */
    .month-divider{display:flex;align-items:center;gap:14px;margin:36px 0 24px;padding-left:56px;}
    .month-divider-line{flex:1;height:1px;background:linear-gradient(90deg,var(--border-neon),transparent);}
    .month-divider-lbl{font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:var(--text-dim);white-space:nowrap;}

    /* EMPTY */
    .empty-state{text-align:center;padding:60px 24px;}
    .empty-state i{font-size:2rem;color:rgba(139,126,255,0.18);display:block;margin-bottom:14px;}
    .empty-state p{font-family:var(--font-hud);font-size:0.58rem;color:var(--text-dim);letter-spacing:0.08em;}

    /* CTA */
    .page-cta{background:var(--accent-bg);border:1px solid var(--accent-border);padding:52px 48px;text-align:center;position:relative;overflow:hidden;margin-top:64px;}
    .page-cta::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--prc-ice),transparent);}
    .page-cta::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--accent),transparent);}
    .cta-corner{position:absolute;width:20px;height:20px;border-color:var(--accent);border-style:solid;}
    .cta-corner.tl{top:12px;left:12px;border-width:1.5px 0 0 1.5px;}
    .cta-corner.tr{top:12px;right:12px;border-width:1.5px 1.5px 0 0;}
    .cta-corner.bl{bottom:12px;left:12px;border-width:0 0 1.5px 1.5px;}
    .cta-corner.br{bottom:12px;right:12px;border-width:0 1.5px 1.5px 0;}
    .page-cta-title{font-family:var(--font-hud);font-size:1.3rem;font-weight:800;color:#fff;margin-bottom:10px;}
    .page-cta-title span{color:var(--accent-light);}
    .page-cta-desc{font-size:0.90rem;color:var(--text-mid);margin-bottom:28px;line-height:1.75;max-width:420px;margin-left:auto;margin-right:auto;}
    .cta-btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
    .btn-neon{display:inline-flex;align-items:center;gap:9px;background:transparent;padding:12px 28px;font-family:var(--font-hud);font-size:0.64rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;border:1px solid;clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);transition:all 0.25s;}
    .btn-neon.violet{color:var(--prc-violet);border-color:var(--prc-violet);box-shadow:0 0 14px rgba(139,126,255,0.22);}
    .btn-neon.violet:hover{background:rgba(139,126,255,0.12);box-shadow:0 0 30px rgba(139,126,255,0.45);color:#fff;}
    .btn-neon.purple{color:var(--accent-light);border-color:var(--accent-border);box-shadow:0 0 14px rgba(119,51,255,0.22);}
    .btn-neon.purple:hover{background:var(--accent-bg);box-shadow:0 0 30px rgba(119,51,255,0.45);color:#fff;}

    /* FOOTER */
    footer{background:rgba(0,0,6,0.95);border-top:1px solid var(--border-neon);padding:70px 0 32px;position:relative;z-index:1;}
    .footer-inner{max-width:1340px;margin:0 auto;padding:0 36px;}
    .footer-top{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:52px;margin-bottom:52px;}
    .footer-brand img{height:36px;width:auto;margin-bottom:16px;}
    .footer-brand p{font-size:0.875rem;color:var(--text-mid);line-height:1.80;margin-bottom:22px;}
    .footer-contact-list{display:flex;flex-direction:column;gap:11px;}
    .footer-contact-item{display:flex;align-items:center;gap:11px;font-size:0.875rem;color:var(--text-mid);}
    .footer-contact-item i{color:var(--prc-violet);font-size:0.95rem;flex-shrink:0;}
    .footer-contact-item a{color:var(--text-mid);transition:color 0.2s;}
    .footer-contact-item a:hover{color:var(--prc-violet);}
    .footer-col h4{font-family:var(--font-hud);font-size:0.65rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;margin-bottom:20px;color:var(--prc-ice);}
    .footer-col ul{display:flex;flex-direction:column;gap:10px;}
    .footer-col ul li a{font-size:0.875rem;color:var(--text-mid);transition:all 0.2s;display:flex;align-items:center;gap:8px;}
    .footer-col ul li a:hover{color:var(--prc-violet);padding-left:4px;}
    .footer-bottom{padding-top:24px;border-top:1px solid rgba(139,126,255,0.12);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;}
    .footer-bottom p{font-family:var(--font-hud);font-size:0.58rem;color:var(--text-soft);letter-spacing:0.06em;}
    .footer-bottom-links{display:flex;gap:22px;}
    .footer-bottom-links a{font-family:var(--font-hud);font-size:0.58rem;color:var(--text-soft);transition:color 0.2s;}
    .footer-bottom-links a:hover{color:var(--prc-violet);}

    @media(max-width:768px){body{cursor:auto;}button{cursor:pointer;}.cursor-dot,.cursor-ring{display:none;}.nav-links{display:none;}.nav-hamburger{display:flex;}.page-wrap{padding:40px 16px 80px;}.timeline::before{left:20px;}.update-card{grid-template-columns:42px 1fr;}.footer-top{grid-template-columns:1fr;gap:32px;}.footer-bottom{flex-direction:column;text-align:center;}.page-hero-inner,.breadcrumb-inner,.footer-inner{padding:0 16px;}.page-cta{padding:36px 22px;}.month-divider{padding-left:42px;}}
    @media(max-width:520px){.nav-inner{padding:0 14px;}.cta-btns{flex-direction:column;align-items:stretch;}.btn-neon{justify-content:center;clip-path:none;}}
    ::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-track{background:var(--bg-void);}::-webkit-scrollbar-thumb{background:var(--prc-violet);border-radius:2px;}
  </style>
</head>
<body>
  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>
  <div class="hex-grid" aria-hidden="true"></div>

  <?php $activePage = 'whatsnew'; include 'nav.php'; ?>

  <div class="breadcrumb-bar">
    <div class="breadcrumb-inner">
      <a href="index.php">Home</a>
      <span class="breadcrumb-sep">&#8250;</span>
      <span class="breadcrumb-current">What's New</span>
    </div>
  </div>

  <div class="page-hero">
    <div class="page-hero-scan"></div>
    <div class="page-hero-inner">
      <div class="hero-eyebrow"><span class="hero-live"></span> Live Competition Updates</div>
      <h1 class="hero-title">What's <span class="accent">New</span></h1>
      <p class="hero-desc">Stay up to date with the latest announcements, reminders, and news about Philippine Robotics Cup 2026.</p>
    </div>
  </div>

  <div class="page-wrap">

    <div class="filter-bar">
      <button class="filter-btn active" onclick="filterUpdates('all',this)">All</button>
      <button class="filter-btn" onclick="filterUpdates('announcement',this)">Announcements</button>
      <button class="filter-btn" onclick="filterUpdates('new',this)">New</button>
      <button class="filter-btn" onclick="filterUpdates('reminder',this)">Reminders</button>
    </div>

    <!-- PINNED -->
    <div class="pinned-banner">
      <div class="pinned-icon">📌</div>
      <div class="pinned-content">
        <div class="pinned-label"><span class="pinned-dot"></span> Pinned Announcement</div>
        <div class="pinned-title">Registration is Now Open for PRC 2026!</div>
        <div class="pinned-desc">Slots are limited — secure your team's spot today before your preferred category closes. The competition is happening this <strong>October 2026</strong>.</div>
      </div>
      <a href="register.php" class="pinned-cta"><i class="fi fi-rr-pen-field"></i> Register Now</a>
    </div>

    <!-- TIMELINE -->
    <div class="timeline" id="updates-container">

      <div class="month-divider"><div class="month-divider-lbl">April 2026</div><div class="month-divider-line"></div></div>

      <div class="update-card is-new" data-type="new">
        <div class="timeline-dot"><div class="dot new"></div></div>
        <div class="card-body">
          <div class="card-meta">
            <span class="type-badge new"><span class="badge-dot"></span>New</span>
            <span class="new-pill">NEW</span>
            <span class="card-date">April 2026</span>
          </div>
          <div class="card-title">Online Registration Now Open</div>
          <div class="card-body-text">
            <p>Registration for Philippine Robotics Cup 2026 is officially open. Teams can now register online and submit their proof of payment directly through the website.</p>
            <p>All categories are currently accepting registrations. Slots are limited, so early registration is strongly encouraged.</p>
          </div>
          <a href="register.php" class="card-link purple"><i class="fi fi-rr-pen-field"></i> Register Now</a>
        </div>
      </div>

      <div class="update-card is-new" data-type="announcement">
        <div class="timeline-dot"><div class="dot announcement"></div></div>
        <div class="card-body">
          <div class="card-meta">
            <span class="type-badge announcement"><span class="badge-dot"></span>Announcement</span>
            <span class="new-pill">NEW</span>
            <span class="card-date">April 2026</span>
          </div>
          <div class="card-title">PRC 2026 is Happening This October</div>
          <div class="card-body-text">
            <p>Philippine Robotics Cup 2026 is officially scheduled for <strong>October 2026</strong>. The exact date and venue will be announced soon — follow our Facebook page and check back here for updates.</p>
            <p>Categories open for this year include RoboVenture, MakeX Starter, MakeX Explorer, and Drone Soccer.</p>
          </div>
          <a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank" class="card-link"><i class="fi fi-brands-facebook"></i> Follow on Facebook</a>
        </div>
      </div>

      <div class="update-card is-new" data-type="reminder">
        <div class="timeline-dot"><div class="dot reminder"></div></div>
        <div class="card-body">
          <div class="card-meta">
            <span class="type-badge reminder"><span class="badge-dot"></span>Reminder</span>
            <span class="new-pill">NEW</span>
            <span class="card-date">April 2026</span>
          </div>
          <div class="card-title">Slots Are Limited — Register Early</div>
          <div class="card-body-text">
            <p>Each category has a limited number of available slots. Once a category fills up, registration for that category will close. We strongly encourage all interested teams to complete their registration as early as possible.</p>
            <p>Payment can be made via <strong>GCash</strong> or <strong>bank transfer</strong>. Upload your proof of payment directly through the registration form.</p>
          </div>
          <a href="register.php" class="card-link amber"><i class="fi fi-rr-pen-field"></i> Register Now</a>
        </div>
      </div>

      <div class="empty-state" id="empty-state" style="display:none;">
        <i class="fi fi-rr-bells"></i>
        <p>No updates found for this filter.</p>
      </div>

    </div>

    <!-- CTA -->
    <div class="page-cta">
      <div class="cta-corner tl"></div><div class="cta-corner tr"></div>
      <div class="cta-corner bl"></div><div class="cta-corner br"></div>
      <h2 class="page-cta-title">Ready to <span>Compete?</span></h2>
      <p class="page-cta-desc">The competition is this October 2026. Don't miss your slot — register your team now.</p>
      <div class="cta-btns">
        <a href="register.php" class="btn-neon purple"><i class="fi fi-rr-pen-field"></i> Register Now</a>
        <a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank" class="btn-neon violet"><i class="fi fi-brands-facebook"></i> Follow on Facebook</a>
      </div>
    </div>

  </div>

  <footer>
    <div class="footer-inner">
      <div class="footer-top">
        <div class="footer-brand">
          <img src="assets/PRC White Logo.png" alt="Philippine Robotics Cup"/>
          <p>The Philippine Robotics Cup is a premier national robotics competition promoting STEM education and innovation.</p>
          <div class="footer-contact-list">
            <div class="footer-contact-item"><i class="fi fi-brands-facebook"></i><a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank">Philippine Robotics Cup</a></div>
            <div class="footer-contact-item"><i class="fi fi-rr-phone-call"></i><a href="tel:+639177713961">+63 917 771 3961</a></div>
            <div class="footer-contact-item"><i class="fi fi-rr-envelope"></i><a href="mailto:philippineroboticscup@gmail.com">philippineroboticscup@gmail.com</a></div>
          </div>
        </div>
        <nav class="footer-col"><h4>Competition</h4><ul><li><a href="index.php#categories">Categories</a></li><li><a href="rankings.php">Rankings</a></li><li><a href="faq.php">FAQ</a></li></ul></nav>
        <nav class="footer-col"><h4>Register</h4><ul><li><a href="register.php">Register Now</a></li><li><a href="faq.php#sec-payment">Payment Info</a></li></ul></nav>
        <nav class="footer-col"><h4>Updates</h4><ul><li><a href="whats-new.php">What's New</a></li><li><a href="shop.php">Shop</a></li></ul></nav>
      </div>
      <div class="footer-bottom">
        <p>&copy; 2026 Philippine Robotics Cup // Creotec Philippines Inc. All rights reserved.</p>
        <div class="footer-bottom-links"><a href="faq.php">FAQ</a><a href="whats-new.php">What's New</a><a href="#">Privacy Policy</a></div>
      </div>
    </div>
  </footer>

  <script>
    var dot=document.getElementById('cursorDot'),ring=document.getElementById('cursorRing'),mx=0,my=0,rx=0,ry=0;
    document.addEventListener('mousemove',function(e){mx=e.clientX;my=e.clientY;dot.style.left=mx+'px';dot.style.top=my+'px';});
    (function loop(){rx+=(mx-rx)*.12;ry+=(my-ry)*.12;ring.style.left=rx+'px';ring.style.top=ry+'px';requestAnimationFrame(loop);})();
    document.addEventListener('mouseover',function(e){if(e.target.closest('a,button,.update-card')){ring.classList.add('hovered');dot.style.background='var(--creo-amber)';}});
    document.addEventListener('mouseout',function(e){if(e.target.closest('a,button,.update-card')){ring.classList.remove('hovered');dot.style.background='var(--neon-primary)';}});

    function filterUpdates(type, btn) {
      document.querySelectorAll('.filter-btn').forEach(function(b){b.classList.remove('active');});
      btn.classList.add('active');
      var cards = document.querySelectorAll('.update-card');
      var dividers = document.querySelectorAll('.month-divider');
      var visCount = 0;
      cards.forEach(function(c){
        var match = type === 'all' || c.dataset.type === type;
        c.classList.toggle('hidden', !match);
        if (match) visCount++;
      });
      dividers.forEach(function(d){
        var next = d.nextElementSibling;
        var hasVisible = false;
        while (next && !next.classList.contains('month-divider') && next.id !== 'empty-state') {
          if (!next.classList.contains('hidden') && next.classList.contains('update-card')) hasVisible = true;
          next = next.nextElementSibling;
        }
        d.style.display = hasVisible ? '' : 'none';
      });
      document.getElementById('empty-state').style.display = visCount === 0 ? 'block' : 'none';
    }

    var ro = new IntersectionObserver(function(entries){
      entries.forEach(function(e){if(e.isIntersecting){e.target.classList.add('visible');ro.unobserve(e.target);}});
    },{threshold:0.08});
    document.querySelectorAll('.update-card').forEach(function(el){ro.observe(el);});
  </script>
</body>
</html>