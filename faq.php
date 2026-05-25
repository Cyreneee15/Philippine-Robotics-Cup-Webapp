<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>FAQ — Philippine Robotics Cup 2026</title>
  <link rel="icon" type="image/png" href="assets/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-brands/css/uicons-brands.css">
  <style>
    :root{
      --prc-violet:#8B7EFF;--prc-ice:#C4EEFF;--creo-purple:#7733FF;
      --creo-amber:#D4AF37;--creo-volt:#D4AF37;--creo-sky:#44D9FF;
      --neon-primary:var(--prc-violet);
      --bg-void:#03020D;--bg-deep:#06051A;--bg-card:#0e0d24;
      --border-neon:rgba(139,126,255,0.22);
      --glow-primary:0 0 18px rgba(139,126,255,0.60),0 0 55px rgba(139,126,255,0.20);
      --text-high:#F2EEFF;--text-mid:#C8C0F0;--text-soft:#9A90CC;--text-dim:#7068A8;
      --nav-height:72px;--font-hud:'Orbitron',monospace;--font-body:'Exo 2',sans-serif;
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
    .hex-grid::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(119,51,255,0.14) 0%,transparent 70%),radial-gradient(ellipse 50% 50% at 100% 100%,rgba(68,217,255,0.06) 0%,transparent 60%);}

    @keyframes scanDown{from{transform:translateY(-100%);}to{transform:translateY(100vh);}}
    @keyframes neonPulse{0%,100%{opacity:1;}50%{opacity:0.7;}}
    @keyframes fadeInUp{from{opacity:0;transform:translateY(28px);}to{opacity:1;transform:translateY(0);}}
    @keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
    @keyframes flicker{0%,100%{opacity:1;}92%{opacity:1;}93%{opacity:0.4;}94%{opacity:1;}96%{opacity:0.7;}97%{opacity:1;}}

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
    .nav-links a.active-page{color:var(--creo-volt);text-shadow:0 0 12px rgba(212,175,55,0.60);}
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
    .breadcrumb-current{color:var(--creo-volt);}

    /* PAGE HERO */
    .page-hero{position:relative;overflow:hidden;padding:80px 0 70px;}
    .page-hero-scan{position:absolute;inset:0;pointer-events:none;overflow:hidden;}
    .page-hero-scan::after{content:'';position:absolute;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--prc-violet),var(--prc-ice),transparent);animation:scanDown 6s linear infinite;box-shadow:0 0 18px rgba(139,126,255,0.55);}
    .page-hero-inner{max-width:1000px;margin:0 auto;padding:0 36px;text-align:center;}
    .hero-eyebrow{font-family:var(--font-hud);font-size:0.60rem;font-weight:700;letter-spacing:0.22em;text-transform:uppercase;color:var(--prc-ice);margin-bottom:18px;display:inline-flex;align-items:center;gap:10px;animation:fadeIn 0.9s ease both;}
    .hero-blink{width:6px;height:6px;background:var(--prc-ice);border-radius:50%;box-shadow:0 0 10px rgba(196,238,255,0.80);animation:neonPulse 1.2s ease-in-out infinite;}
    .hero-title{font-family:var(--font-hud);font-size:clamp(2.4rem,6vw,4.2rem);font-weight:900;line-height:1.0;margin-bottom:16px;animation:fadeInUp 0.9s ease 0.1s both;}
    .hero-title .word-got{color:#ffffff;text-shadow:0 2px 24px rgba(255,255,255,0.18);}
    .hero-title .word-questions{color:#4B0082;text-shadow:0 0 32px rgba(75,0,130,0.80),0 0 80px rgba(75,0,130,0.35);}
    .hero-desc{font-size:1rem;color:var(--text-mid);max-width:560px;margin:0 auto 32px;line-height:1.80;animation:fadeInUp 0.9s ease 0.2s both;}

    /* SEARCH */
    .faq-search-wrap{max-width:520px;margin:0 auto;animation:fadeInUp 0.9s ease 0.3s both;}
    .faq-search-inner{position:relative;}
    .faq-search-input{width:100%;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.30);color:var(--text-high);font-family:var(--font-body);font-size:0.92rem;padding:13px 46px 13px 18px;outline:none;transition:border-color 0.22s,box-shadow 0.22s;clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);}
    .faq-search-input::placeholder{color:var(--text-dim);}
    .faq-search-input:focus{border-color:var(--prc-violet);box-shadow:0 0 22px rgba(139,126,255,0.22);}
    .faq-search-icon{position:absolute;right:16px;top:50%;transform:translateY(-50%);color:rgba(139,126,255,0.50);font-size:1rem;pointer-events:none;}

    /* MAIN CONTENT */
    .faq-wrap{max-width:1000px;margin:0 auto;padding:60px 36px 100px;position:relative;z-index:1;}

    /* CATEGORY FILTER */
    .faq-cats{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:48px;justify-content:center;}
    .faq-cat-btn{font-family:var(--font-hud);font-size:0.56rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:8px 18px;border:1px solid rgba(139,126,255,0.24);color:var(--text-soft);background:transparent;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);cursor:pointer;transition:all 0.2s;}
    .faq-cat-btn:hover{border-color:var(--prc-violet);color:var(--prc-violet);background:rgba(139,126,255,0.07);}
    .faq-cat-btn.active{border-color:var(--creo-volt);color:var(--creo-volt);background:rgba(212,175,55,0.07);box-shadow:0 0 14px rgba(212,175,55,0.18);}

    /* FAQ SECTION */
    .faq-section{margin-bottom:52px;}
    .faq-section-title{font-family:var(--font-hud);font-size:0.60rem;font-weight:700;letter-spacing:0.20em;text-transform:uppercase;color:var(--prc-ice);margin-bottom:20px;display:flex;align-items:center;gap:12px;}
    .faq-section-title::before{content:'//';color:rgba(139,126,255,0.35);}
    .faq-section-title::after{content:'';flex:1;height:1px;background:linear-gradient(90deg,var(--border-neon),transparent);}

    /* FAQ ITEM */
    .faq-item{background:rgba(0,0,8,0.55);border:1px solid rgba(139,126,255,0.13);margin-bottom:10px;position:relative;overflow:hidden;transition:border-color 0.3s;}
    .faq-item::before{content:'';position:absolute;top:0;left:0;bottom:0;width:2px;background:var(--prc-violet);opacity:0;transition:opacity 0.3s;}
    .faq-item.open{border-color:rgba(139,126,255,0.32);}
    .faq-item.open::before{opacity:1;}
    .faq-item.hidden{display:none;}
    .faq-q{width:100%;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 22px;cursor:pointer;text-align:left;background:transparent;border:none;transition:background 0.2s;}
    .faq-q:hover{background:rgba(139,126,255,0.04);}
    .faq-q-text{font-family:var(--font-hud);font-size:0.72rem;font-weight:700;color:var(--text-high);letter-spacing:0.04em;line-height:1.4;flex:1;}
    .faq-item.open .faq-q-text{color:var(--prc-ice);}
    .faq-q-icon{width:28px;height:28px;border:1px solid rgba(139,126,255,0.28);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--prc-violet);font-size:0.70rem;transition:transform 0.3s,background 0.2s,border-color 0.2s;}
    .faq-item.open .faq-q-icon{transform:rotate(45deg);background:rgba(139,126,255,0.12);border-color:var(--prc-violet);}
    .faq-a{max-height:0;overflow:hidden;transition:max-height 0.42s cubic-bezier(0.23,1,0.32,1);}
    .faq-a-inner{padding:0 22px 20px;font-size:0.90rem;color:var(--text-mid);line-height:1.85;border-top:1px solid rgba(139,126,255,0.10);}
    .faq-a-inner p{margin-bottom:10px;}
    .faq-a-inner p:last-child{margin-bottom:0;}
    .faq-a-inner strong{color:var(--text-high);}
    .faq-a-inner a{color:var(--prc-violet);transition:color 0.2s;}
    .faq-a-inner a:hover{color:var(--prc-ice);}
    .faq-a-inner ul{margin:8px 0 8px 18px;display:flex;flex-direction:column;gap:5px;}
    .faq-a-inner ul li{list-style:disc;color:var(--text-mid);}
    .faq-tag{display:inline-block;font-family:var(--font-hud);font-size:0.44rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:2px 8px;border:1px solid;margin-right:6px;margin-bottom:6px;}
    .faq-tag.violet{color:var(--prc-violet);border-color:rgba(139,126,255,0.30);background:rgba(139,126,255,0.06);}
    .faq-tag.amber{color:var(--creo-amber);border-color:rgba(212,175,55,0.28);background:rgba(212,175,55,0.05);}
    .faq-tag.sky{color:var(--creo-sky);border-color:rgba(68,217,255,0.28);background:rgba(68,217,255,0.05);}

    /* NO RESULTS */
    .faq-no-results{text-align:center;padding:60px 24px;display:none;}
    .faq-no-results i{font-size:2rem;color:rgba(139,126,255,0.18);display:block;margin-bottom:14px;}
    .faq-no-results p{font-family:var(--font-hud);font-size:0.58rem;color:var(--text-dim);letter-spacing:0.08em;}

    /* STILL HAVE QUESTIONS CTA */
    .faq-cta{background:rgba(139,126,255,0.04);border:1px solid var(--prc-violet);padding:52px 48px;text-align:center;position:relative;overflow:hidden;margin-top:60px;}
    .faq-cta::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--prc-ice),transparent);}
    .faq-cta::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--creo-purple),transparent);}
    .faq-cta-title{font-family:var(--font-hud);font-size:1.3rem;font-weight:800;color:#fff;margin-bottom:10px;}
    .faq-cta-title span{color:var(--prc-violet);}
    .faq-cta-desc{font-size:0.90rem;color:var(--text-mid);margin-bottom:28px;line-height:1.75;max-width:440px;margin-left:auto;margin-right:auto;}
    .faq-cta-btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
    .btn-neon{display:inline-flex;align-items:center;gap:9px;background:transparent;padding:12px 28px;font-family:var(--font-hud);font-size:0.64rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;border:1px solid;clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);transition:all 0.25s;}
    .btn-neon.violet{color:var(--prc-violet);border-color:var(--prc-violet);box-shadow:0 0 14px rgba(139,126,255,0.22);}
    .btn-neon.violet:hover{background:rgba(139,126,255,0.12);box-shadow:0 0 30px rgba(139,126,255,0.45);color:#fff;}
    .btn-neon.amber{color:var(--creo-amber);border-color:rgba(212,175,55,0.60);box-shadow:0 0 14px rgba(212,175,55,0.18);}
    .btn-neon.amber:hover{background:rgba(212,175,55,0.10);box-shadow:0 0 30px rgba(212,175,55,0.38);color:#fff;}

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

    /* REVEAL */
    .reveal{opacity:0;transform:translateY(24px);transition:opacity 0.55s ease,transform 0.55s ease;}
    .reveal.visible{opacity:1;transform:translateY(0);}

    @media(max-width:768px){body{cursor:auto;}button{cursor:pointer;}.cursor-dot,.cursor-ring{display:none;}.nav-links{display:none;}.nav-hamburger{display:flex;}.faq-wrap{padding:40px 16px 80px;}.footer-top{grid-template-columns:1fr;gap:32px;}.footer-bottom{flex-direction:column;text-align:center;}.page-hero-inner,.breadcrumb-inner{padding:0 16px;}.faq-cta{padding:36px 22px;}}
    @media(max-width:520px){.nav-inner{padding:0 14px;}.footer-inner{padding:0 16px;}.faq-cta-btns{flex-direction:column;align-items:stretch;}.btn-neon{justify-content:center;clip-path:none;}}
    ::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-track{background:var(--bg-void);}::-webkit-scrollbar-thumb{background:var(--prc-violet);border-radius:2px;}
  </style>
</head>
<body>
  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>
  <div class="hex-grid" aria-hidden="true"></div>

  <?php $activePage = 'faq'; include 'nav.php'; ?>

  <div class="breadcrumb-bar">
    <div class="breadcrumb-inner">
      <a href="index.html">Home</a>
      <span class="breadcrumb-sep">&#8250;</span>
      <span class="breadcrumb-current">FAQ</span>
    </div>
  </div>

  <!-- HERO -->
  <div class="page-hero">
    <div class="page-hero-scan"></div>
    <div class="page-hero-inner">
      <div class="hero-eyebrow"><span class="hero-blink"></span> Frequently Asked Questions</div>
      <h1 class="hero-title">
        <span class="word-got">Got</span> <span class="word-questions">Questions?</span>
      </h1>
      <p class="hero-desc">Everything you need to know about PRC 2026 — registration, categories, payment, and more. Can't find your answer? Contact us directly.</p>
      <div class="faq-search-wrap">
        <div class="faq-search-inner">
          <input class="faq-search-input" id="faq-search" type="text" placeholder="Search questions..." oninput="searchFAQ(this.value)"/>
          <i class="fi fi-rr-search faq-search-icon"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="faq-wrap">

    <!-- CATEGORY FILTER -->
    <div class="faq-cats">
      <button class="faq-cat-btn active" onclick="filterCat('all',this)">All</button>
      <button class="faq-cat-btn" onclick="filterCat('registration',this)">Registration</button>
      <button class="faq-cat-btn" onclick="filterCat('categories',this)">Categories</button>
      <button class="faq-cat-btn" onclick="filterCat('payment',this)">Payment</button>
      <button class="faq-cat-btn" onclick="filterCat('event',this)">Event Details</button>
      <button class="faq-cat-btn" onclick="filterCat('shop',this)">Shop</button>
    </div>

    <!-- REGISTRATION -->
    <div class="faq-section reveal" data-cat="registration" id="sec-registration">
      <div class="faq-section-title">Registration</div>

      <div class="faq-item" data-cat="registration">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">Who can join the Philippine Robotics Cup 2026?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>PRC 2026 is open to <strong>all students who have a passion for robotics</strong> — regardless of grade level, school, or experience. Whether you're a beginner or a seasoned builder, there's a category for you.</p>
          <p>Teams must be accompanied by a registered teacher or coach, and each team must be represented by their school or organization.</p>
        </div></div>
      </div>

      <div class="faq-item" data-cat="registration">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">How do I register my team?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>Registration is done online through our <a href="register.html">registration page</a>. The process has 4 steps:</p>
          <ul>
            <li><strong>Step 1:</strong> Select your registration package (Starter, Competitor, or Champion)</li>
            <li><strong>Step 2:</strong> Fill in your team and school details</li>
            <li><strong>Step 3:</strong> Upload your proof of payment (GCash or bank transfer)</li>
            <li><strong>Step 4:</strong> Receive your confirmation and reference number</li>
          </ul>
          <p>Your slot is confirmed once payment is verified — usually within 24 hours.</p>
        </div></div>
      </div>

      <div class="faq-item" data-cat="registration">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">How many members can be in a team?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>Team size varies by category:</p>
          <ul>
            <li><strong>RoboVenture categories:</strong> 1–3 members per team</li>
            <li><strong>MakeX Starter / Explorer:</strong> 2–3 members per team</li>
            <li><strong>Drone Soccer:</strong> 2–4 members per team</li>
          </ul>
          <p>Each registration covers one team entry for one category. If your school wants to join multiple categories, a separate registration is required for each.</p>
        </div></div>
      </div>

      <div class="faq-item" data-cat="registration">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">Can one school register multiple teams?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>Yes! Schools can register multiple teams across different categories or even in the same category (subject to slot availability). Each team requires a separate registration and payment.</p>
          <p>Use a different team name for each entry to avoid confusion during the event.</p>
        </div></div>
      </div>

      <div class="faq-item" data-cat="registration">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">What is the registration deadline?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>Registration is open until slots are filled. We strongly recommend registering early as slots fill up quickly — especially for popular categories like MakeX Starter and Drone Soccer.</p>
          <p>Late registrations may not be accommodated, so don't wait!</p>
        </div></div>
      </div>

      <div class="faq-item" data-cat="registration">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">Can I change my team details after registering?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>Minor changes (like correcting a name or school) can be requested by emailing us at <a href="mailto:philippineroboticscup@gmail.com">philippineroboticscup@gmail.com</a> with your reference number.</p>
          <p>Category changes after payment are generally not allowed. If you need to switch categories, please contact us as soon as possible — we'll do our best to accommodate you.</p>
        </div></div>
      </div>
    </div>

    <!-- CATEGORIES -->
    <div class="faq-section reveal" data-cat="categories" id="sec-categories">
      <div class="faq-section-title">Categories</div>

      <div class="faq-item" data-cat="categories">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">What categories are available at PRC 2026?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>PRC 2026 features three main competition tracks:</p>
          <p><span class="faq-tag violet">RoboVenture</span> Emerging Innovators, Aspiring Makers, Line Tracing, Innovation Builders, Sumobot, Robot Soccer, Navigation (Autonomous &amp; Manual)</p>
          <p><span class="faq-tag sky">MakeX</span> MakeX Starter, MakeX Explorer</p>
          <p><span class="faq-tag amber">Drone Soccer</span> Drone Soccer (open division)</p>
        </div></div>
      </div>

      <div class="faq-item" data-cat="categories">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">Which category is right for my team?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <ul>
            <li><strong>Emerging Innovators / Aspiring Makers:</strong> Best for teams new to robotics competitions — focuses on basic robot building and programming</li>
            <li><strong>Line Tracing:</strong> Robots must autonomously follow a track — great for teams with some programming experience</li>
            <li><strong>Sumobot:</strong> Head-to-head robot battles — pushing the opponent out of the ring</li>
            <li><strong>Innovation Builders:</strong> Creative problem-solving with open-ended challenges</li>
            <li><strong>MakeX Starter / Explorer:</strong> Mission-based challenges using Makeblock hardware and mBlock programming</li>
            <li><strong>Drone Soccer:</strong> Teams pilot encaged drones to score goals — exciting and fast-paced</li>
          </ul>
        </div></div>
      </div>

      <div class="faq-item" data-cat="categories">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">Do we need to bring our own robots and equipment?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>Yes, teams are responsible for bringing their own robots and equipment. However, we offer kits and supplies through our <a href="shop.html">Shop</a> if you need materials.</p>
          <p>All robots must comply with the technical specifications for their respective category. Specification documents will be sent to registered teams via email.</p>
        </div></div>
      </div>

      <div class="faq-item" data-cat="categories">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">Is there a separate rule book for each category?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>Yes. Official rule books and technical specifications for each category will be shared with registered teams via email after payment confirmation. You can also message us on <a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank">Facebook</a> to request them in advance.</p>
        </div></div>
      </div>
    </div>

    <!-- PAYMENT -->
    <div class="faq-section reveal" data-cat="payment" id="sec-payment">
      <div class="faq-section-title">Payment</div>

      <div class="faq-item" data-cat="payment">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">What are the registration packages and fees?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>PRC 2026 offers three registration packages:</p>
          <ul>
            <li><strong>Starter Package — ₱2,500:</strong> Basic participation for one category</li>
            <li><strong>Competitor Package — ₱4,500:</strong> Enhanced package with additional benefits</li>
            <li><strong>Champion Package — ₱7,000:</strong> Full package with premium access and perks</li>
          </ul>
          <p>Specific inclusions for each package are listed on the <a href="register.html">registration page</a>.</p>
        </div></div>
      </div>

      <div class="faq-item" data-cat="payment">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">What payment methods are accepted?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>We accept the following payment methods:</p>
          <ul>
            <li><strong>GCash:</strong> +63 917 771 3961 (Creotec Philippines Inc.)</li>
            <li><strong>Bank Transfer (BDO):</strong> Account Name: Creotec Philippines Inc. — send your receipt after transferring</li>
          </ul>
          <p>After paying, upload your proof of payment (screenshot or photo) during Step 3 of the registration process. Your slot will be confirmed within 24 hours.</p>
        </div></div>
      </div>

      <div class="faq-item" data-cat="payment">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">Are registration fees refundable?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>Registration fees are <strong>non-refundable</strong> once confirmed. However, if the event is cancelled or postponed by the organizers, registered teams will be given full credit or refund options.</p>
          <p>If you registered by mistake or need to transfer your slot, please contact us at <a href="mailto:philippineroboticscup@gmail.com">philippineroboticscup@gmail.com</a> as soon as possible.</p>
        </div></div>
      </div>

      <div class="faq-item" data-cat="payment">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">How do I know if my payment was received?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>After you submit your proof of payment, you will receive a confirmation email at the address you provided during registration. The email will include your <strong>reference number</strong> and registration details.</p>
          <p>If you haven't received a confirmation within 24 hours, please check your spam folder or contact us with your reference number.</p>
        </div></div>
      </div>
    </div>

    <!-- EVENT DETAILS -->
    <div class="faq-section reveal" data-cat="event" id="sec-event">
      <div class="faq-section-title">Event Details</div>

      <div class="faq-item" data-cat="event">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">When and where is PRC 2026?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>PRC 2026 is scheduled for <strong>2026</strong>. The exact date and venue will be announced soon. Registered teams will be notified via email once the details are finalized.</p>
          <p>Follow our <a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank">Facebook page</a> for the latest updates.</p>
        </div></div>
      </div>

      <div class="faq-item" data-cat="event">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">Are coaches and parents allowed to attend?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>Yes! Coaches, teachers, parents, and supporters are welcome to attend and cheer for their teams. The event is open to spectators.</p>
          <p>Each registered team is required to have at least <strong>one registered teacher/coach</strong> who will be responsible for the student participants during the event.</p>
        </div></div>
      </div>

      <div class="faq-item" data-cat="event">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">What should teams bring on competition day?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <ul>
            <li>Your <strong>reference number</strong> or confirmation email</li>
            <li>School ID for all student participants</li>
            <li>Your robot(s) and all necessary equipment</li>
            <li>Tools for repairs and adjustments (spare parts recommended)</li>
            <li>Laptop or tablet for programming if required by your category</li>
            <li>Power banks, extension cords, and charging cables</li>
          </ul>
        </div></div>
      </div>

      <div class="faq-item" data-cat="event">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">Will there be awards and prizes?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>Yes! Winners in each category will receive:</p>
          <ul>
            <li><strong>Champion:</strong> Trophy, certificate, and cash prize</li>
            <li><strong>2nd Place:</strong> Trophy and certificate</li>
            <li><strong>3rd Place:</strong> Trophy and certificate</li>
            <li><strong>Finalists:</strong> Medals and certificates</li>
            <li><strong>All participants:</strong> Certificate of participation</li>
          </ul>
          <p>Special awards may also be given for outstanding performance, best design, and sportsmanship.</p>
        </div></div>
      </div>

      <div class="faq-item" data-cat="event">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">Is PRC 2026 DepEd or DOST accredited?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>PRC 2026 is organized by <strong>Creotec Philippines Inc.</strong> in coordination with education and government partners. Certificates of participation are issued to all student participants and their coaches.</p>
          <p>For accreditation inquiries for your school or division, please contact us directly so we can provide the necessary documentation.</p>
        </div></div>
      </div>
    </div>

    <!-- SHOP -->
    <div class="faq-section reveal" data-cat="shop" id="sec-shop">
      <div class="faq-section-title">Shop &amp; Materials</div>

      <div class="faq-item" data-cat="shop">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">Do I need to buy materials from the PRC Shop?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>No, purchasing from the PRC Shop is <strong>optional</strong>. Teams can source their materials from anywhere, as long as their robots comply with the official specifications for their category.</p>
          <p>The shop offers convenient, competition-ready kits verified to meet PRC specifications — ideal if you want a quick and reliable setup.</p>
        </div></div>
      </div>

      <div class="faq-item" data-cat="shop">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">How do I order from the Shop?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>Visit our <a href="shop.html">Shop page</a>, select the items you need, add them to cart, and proceed to checkout. You'll need to provide your contact details and upload proof of payment (GCash or bank transfer).</p>
          <p>Orders are processed within 24–48 hours. For bulk orders or delivery inquiries, contact us at <a href="mailto:philippineroboticscup@gmail.com">philippineroboticscup@gmail.com</a>.</p>
        </div></div>
      </div>

      <div class="faq-item" data-cat="shop">
        <button class="faq-q" onclick="toggleFAQ(this)">
          <span class="faq-q-text">Can I pick up my order at the event venue?</span>
          <span class="faq-q-icon">&#43;</span>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          <p>Yes, event-day pickup is available for orders placed in advance. Please indicate "event pickup" in the notes field when ordering, and bring your order reference on competition day.</p>
          <p>We recommend ordering early as stock is limited — especially for the Drone Soccer Starter Pack and MakeX kits.</p>
        </div></div>
      </div>
    </div>

    <!-- NO RESULTS -->
    <div class="faq-no-results" id="faq-no-results">
      <i class="fi fi-rr-search"></i>
      <p>No questions found for "<span id="no-results-query"></span>"</p>
      <p style="margin-top:8px;font-size:0.80rem;color:var(--text-dim);font-family:var(--font-body);">Try a different keyword or browse by category above.</p>
    </div>

    <!-- STILL HAVE QUESTIONS -->
    <div class="faq-cta reveal">
      <div class="cta-corner tl" style="position:absolute;top:12px;left:12px;width:20px;height:20px;border:1.5px solid var(--prc-violet);border-right:none;border-bottom:none;"></div>
      <div class="cta-corner tr" style="position:absolute;top:12px;right:12px;width:20px;height:20px;border:1.5px solid var(--prc-violet);border-left:none;border-bottom:none;"></div>
      <div class="cta-corner bl" style="position:absolute;bottom:12px;left:12px;width:20px;height:20px;border:1.5px solid var(--prc-violet);border-right:none;border-top:none;"></div>
      <div class="cta-corner br" style="position:absolute;bottom:12px;right:12px;width:20px;height:20px;border:1.5px solid var(--prc-violet);border-left:none;border-top:none;"></div>
      <h2 class="faq-cta-title">Still Have <span>Questions?</span></h2>
      <p class="faq-cta-desc">Our team is happy to help. Reach out via Facebook or email and we'll get back to you as soon as possible.</p>
      <div class="faq-cta-btns">
        <a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank" class="btn-neon violet"><i class="fi fi-brands-facebook"></i> Message on Facebook</a>
        <a href="mailto:philippineroboticscup@gmail.com" class="btn-neon amber"><i class="fi fi-rr-envelope"></i> Send us an Email</a>
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
        <nav class="footer-col"><h4>Competition</h4><ul><li><a href="index.html#categories">Categories</a></li><li><a href="rankings.html">Rankings</a></li><li><a href="faq.html">FAQ</a></li></ul></nav>
        <nav class="footer-col"><h4>Register</h4><ul><li><a href="register.html">Register Now</a></li><li><a href="faq.html#sec-payment">Payment Info</a></li></ul></nav>
        <nav class="footer-col"><h4>Shop</h4><ul><li><a href="shop.html">All Products</a></li><li><a href="faq.html#sec-shop">Shop FAQ</a></li></ul></nav>
      </div>
      <div class="footer-bottom">
        <p>&copy; 2026 Philippine Robotics Cup // Creotec Philippines Inc. All rights reserved.</p>
        <div class="footer-bottom-links"><a href="faq.html">FAQ</a><a href="#">Privacy Policy</a><a href="#">Terms of Use</a></div>
      </div>
    </div>
  </footer>

  <script>
    // CURSOR
    var dot=document.getElementById('cursorDot'),ring=document.getElementById('cursorRing'),mx=0,my=0,rx=0,ry=0;
    document.addEventListener('mousemove',function(e){mx=e.clientX;my=e.clientY;dot.style.left=mx+'px';dot.style.top=my+'px';});
    (function loop(){rx+=(mx-rx)*.12;ry+=(my-ry)*.12;ring.style.left=rx+'px';ring.style.top=ry+'px';requestAnimationFrame(loop);})();
    document.addEventListener('mouseover',function(e){if(e.target.closest('a,button,.faq-item')){ring.classList.add('hovered');dot.style.background='var(--creo-amber)';}});
    document.addEventListener('mouseout', function(e){if(e.target.closest('a,button,.faq-item')){ring.classList.remove('hovered');dot.style.background='var(--neon-primary)';}});

    // FAQ TOGGLE
    function toggleFAQ(btn) {
      var item = btn.closest('.faq-item');
      var answer = item.querySelector('.faq-a');
      var isOpen = item.classList.contains('open');
      document.querySelectorAll('.faq-item.open').forEach(function(i){
        i.classList.remove('open');
        i.querySelector('.faq-a').style.maxHeight = '0';
      });
      if (!isOpen) {
        item.classList.add('open');
        answer.style.maxHeight = answer.scrollHeight + 'px';
      }
    }

    // CATEGORY FILTER
    function filterCat(cat, btn) {
      document.querySelectorAll('.faq-cat-btn').forEach(function(b){b.classList.remove('active');});
      btn.classList.add('active');
      document.getElementById('faq-search').value = '';
      var sections = document.querySelectorAll('.faq-section');
      var items = document.querySelectorAll('.faq-item');
      document.querySelectorAll('.faq-item.open').forEach(function(i){i.classList.remove('open');i.querySelector('.faq-a').style.maxHeight='0';});
      if (cat === 'all') {
        sections.forEach(function(s){s.style.display='';});
        items.forEach(function(i){i.classList.remove('hidden');});
      } else {
        sections.forEach(function(s){
          s.style.display = s.dataset.cat === cat ? '' : 'none';
        });
        items.forEach(function(i){
          i.classList.toggle('hidden', i.dataset.cat !== cat);
        });
      }
      document.getElementById('faq-no-results').style.display = 'none';
    }

    // SEARCH
    function searchFAQ(query) {
      query = query.toLowerCase().trim();
      var items = document.querySelectorAll('.faq-item');
      var sections = document.querySelectorAll('.faq-section');
      document.querySelectorAll('.faq-cat-btn').forEach(function(b){b.classList.remove('active');});
      document.querySelector('.faq-cat-btn').classList.add('active');
      var visCount = 0;
      if (!query) {
        items.forEach(function(i){i.classList.remove('hidden');});
        sections.forEach(function(s){s.style.display='';});
        document.getElementById('faq-no-results').style.display = 'none';
        return;
      }
      var secHasVisible = {};
      items.forEach(function(i){
        var q = i.querySelector('.faq-q-text').textContent.toLowerCase();
        var a = i.querySelector('.faq-a-inner').textContent.toLowerCase();
        var match = q.includes(query) || a.includes(query);
        i.classList.toggle('hidden', !match);
        if (match) { visCount++; var sec = i.closest('.faq-section'); if(sec) secHasVisible[sec.id] = true; }
      });
      sections.forEach(function(s){
        s.style.display = secHasVisible[s.id] ? '' : 'none';
      });
      var noRes = document.getElementById('faq-no-results');
      noRes.style.display = visCount === 0 ? 'block' : 'none';
      document.getElementById('no-results-query').textContent = query;
    }

    // REVEAL ON SCROLL
    var ro = new IntersectionObserver(function(entries){
      entries.forEach(function(e){if(e.isIntersecting){e.target.classList.add('visible');ro.unobserve(e.target);}});
    },{threshold:0.06});
    document.querySelectorAll('.reveal').forEach(function(el){ro.observe(el);});

    // ANCHOR LINKS
    window.addEventListener('DOMContentLoaded', function(){
      var hash = window.location.hash;
      if (hash) {
        var el = document.querySelector(hash);
        if (el) { setTimeout(function(){ el.scrollIntoView({behavior:'smooth',block:'start'}); }, 300); }
      }
    });
  </script>
</body>
</html>