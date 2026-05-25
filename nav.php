<?php
/**
 * nav.php — Unified Navigation Component
 * Philippine Robotics Cup 2026 · By Creotec Philippines
 *
 * USAGE: <?php include 'nav.php'; ?>
 *
 * Active state is set automatically based on the current filename.
 * Override by defining $activePage before including:
 *   $activePage = 'rankings'; // optional override
 *   include 'nav.php';
 */

// ── Auto-detect current page ──────────────────────────────────────────────────
if (!isset($activePage)) {
    $currentFile = basename($_SERVER['PHP_SELF'], '.php');
    // Normalize common aliases
    $pageAliases = [
        'index'      => 'home',
        'default'    => 'home',
        'whats-new'  => 'whatsnew',
        'whats_new'  => 'whatsnew',
        'whatsnew'   => 'whatsnew',
        'about'      => 'about',
    ];
    $activePage = $pageAliases[$currentFile] ?? $currentFile;
}

// Pages that belong to "About Us" dropdown
$aboutPages    = ['about', 'faq', 'categories'];
// Pages that belong to "What's New" dropdown
$whatsnewPages = ['whatsnew'];

// ── Helper: emit "active" / "active-page" classes ────────────────────────────
function navClass(string $page, string $activePage, bool $mobile = false): string {
    if ($page === $activePage) {
        return $mobile ? ' class="active"' : ' class="active-page"';
    }
    return '';
}
?>

<!-- ===== DESKTOP NAV ===== -->
<nav id="main-nav" role="navigation" aria-label="Main navigation">
  <div class="nav-inner">

    <!-- Logo -->
    <a href="index.php" class="nav-logo" aria-label="PRC Home">
      <img src="assets/PRC White Logo.png" alt="Philippine Robotics Cup Logo" />
      <div class="nav-brand">
        Philippine Robotics Cup
        <span>By Creotec Philippines</span>
      </div>
    </a>

    <!-- Desktop links -->
    <ul class="nav-links" role="list">

      <!-- Home -->
      <li>
        <a href="index.php" data-nav="home"<?= navClass('home', $activePage) ?>>Home</a>
      </li>

      <!-- About Us (dropdown) -->
      <li class="nav-dropdown<?= in_array($activePage, $aboutPages) ? ' is-active-group' : '' ?>">
        <button
          class="nav-dropdown-trigger<?= in_array($activePage, $aboutPages) ? ' active-page' : '' ?>"
          aria-haspopup="true"
          aria-expanded="false"
          type="button">
          About Us
          <svg class="nav-dropdown-caret" width="10" height="6" viewBox="0 0 10 6" fill="none" aria-hidden="true">
            <path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <ul class="nav-dropdown-menu" role="menu" aria-label="About Us submenu">
          <li role="none">
            <a href="about.php" data-nav="about" role="menuitem"<?= navClass('about', $activePage) ?>>
              <i class="fi fi-rr-info"></i>
              <span>
                About Us
                <small>Our story &amp; mission</small>
              </span>
            </a>
          </li>
          <li role="none">
            <a href="faq.php" data-nav="faq" role="menuitem"<?= navClass('faq', $activePage) ?>>
              <i class="fi fi-rr-question"></i>
              <span>
                FAQ
                <small>Common questions answered</small>
              </span>
            </a>
          </li>
          <li role="none">
            <a href="categories.php" data-nav="categories" role="menuitem"<?= navClass('categories', $activePage) ?>>
              <i class="fi fi-rr-trophy"></i>
              <span>
                Categories
                <small>Competition divisions</small>
              </span>
            </a>
          </li>
        </ul>
      </li>

      <!-- What's New (dropdown) -->
      <li class="nav-dropdown<?= in_array($activePage, $whatsnewPages) ? ' is-active-group' : '' ?>">
        <button
          class="nav-dropdown-trigger<?= in_array($activePage, $whatsnewPages) ? ' active-page' : '' ?>"
          aria-haspopup="true"
          aria-expanded="false"
          type="button">
          What's New
          <svg class="nav-dropdown-caret" width="10" height="6" viewBox="0 0 10 6" fill="none" aria-hidden="true">
            <path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <ul class="nav-dropdown-menu" role="menu" aria-label="What's New submenu">
          <li role="none">
            <a href="whats-new.php" data-nav="whatsnew-all" role="menuitem">
              <i class="fi fi-rr-bells"></i>
              <span>
                All
                <small>Everything in one place</small>
              </span>
            </a>
          </li>
          <li role="none">
            <a href="whats-new.php" data-nav="whatsnew-announcements" role="menuitem">
              <i class="fi fi-rr-megaphone"></i>
              <span>
                Announcements
                <small>Official updates</small>
              </span>
            </a>
          </li>
          <li role="none">
            <a href="whats-new.php" data-nav="whatsnew-news" role="menuitem">
              <i class="fi fi-rr-newspaper"></i>
              <span>
                News
                <small>Latest coverage &amp; stories</small>
              </span>
            </a>
          </li>
        </ul>
      </li>

      <!-- Rankings -->
      <li>
        <a href="rankings.php" data-nav="rankings"<?= navClass('rankings', $activePage) ?>>Rankings</a>
      </li>

      <!-- Gallery -->
      <li>
        <a href="gallery.php" data-nav="gallery"<?= navClass('gallery', $activePage) ?>>Gallery</a>
      </li>

      <!-- Shop -->
      <li>
        <a href="shop.php" data-nav="shop"<?= navClass('shop', $activePage) ?>>Shop</a>
      </li>

      <!-- Contact Us -->
      <li>
        <a href="contact.php" data-nav="contact"<?= navClass('contact', $activePage) ?>>Contact Us</a>
      </li>

      <!-- Register CTA -->
      <li>
        <a href="register.php" class="nav-cta<?= ($activePage === 'register') ? ' active-page' : '' ?>" data-nav="register">Register Now</a>
      </li>

    </ul>

    <!-- Hamburger (mobile) -->
    <button
      class="nav-hamburger"
      id="prc-hamburger"
      type="button"
      aria-label="Open menu"
      aria-expanded="false"
      aria-controls="prc-mobile-menu">
      <span></span><span></span><span></span>
    </button>

  </div>
</nav>

<!-- ===== MOBILE NAV ===== -->
<nav
  class="nav-mobile"
  id="prc-mobile-menu"
  aria-label="Mobile navigation"
  aria-hidden="true">

  <a href="index.php" data-nav="home"<?= navClass('home', $activePage, true) ?>>
    <i class="fi fi-rr-home"></i>Home
  </a>

  <!-- About Us accordion -->
  <div class="nav-mobile-accordion<?= in_array($activePage, $aboutPages) ? ' is-open' : '' ?>">
    <button class="nav-mobile-accordion-trigger" type="button" aria-expanded="<?= in_array($activePage, $aboutPages) ? 'true' : 'false' ?>">
      <span><i class="fi fi-rr-info"></i>About Us</span>
      <svg class="nav-dropdown-caret" width="10" height="6" viewBox="0 0 10 6" fill="none" aria-hidden="true">
        <path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
    <div class="nav-mobile-accordion-body">
      <a href="about.php" data-nav="about"<?= navClass('about', $activePage, true) ?>><i class="fi fi-rr-info"></i>About Us</a>
      <a href="faq.php" data-nav="faq"<?= navClass('faq', $activePage, true) ?>><i class="fi fi-rr-question"></i>FAQ</a>
      <a href="categories.php" data-nav="categories"<?= navClass('categories', $activePage, true) ?>><i class="fi fi-rr-trophy"></i>Categories</a>
    </div>
  </div>

  <!-- What's New accordion -->
  <div class="nav-mobile-accordion<?= in_array($activePage, $whatsnewPages) ? ' is-open' : '' ?>">
    <button class="nav-mobile-accordion-trigger" type="button" aria-expanded="<?= in_array($activePage, $whatsnewPages) ? 'true' : 'false' ?>">
      <span><i class="fi fi-rr-bells"></i>What's New</span>
      <svg class="nav-dropdown-caret" width="10" height="6" viewBox="0 0 10 6" fill="none" aria-hidden="true">
        <path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
    <div class="nav-mobile-accordion-body">
      <a href="whats-new.php" data-nav="whatsnew-all"<?= navClass('whatsnew', $activePage, true) ?>><i class="fi fi-rr-bells"></i>All</a>
      <a href="whats-new.php" data-nav="whatsnew-announcements"><i class="fi fi-rr-megaphone"></i>Announcements</a>
      <a href="whats-new.php" data-nav="whatsnew-news"><i class="fi fi-rr-newspaper"></i>News</a>
    </div>
  </div>

  <a href="rankings.php" data-nav="rankings"<?= navClass('rankings', $activePage, true) ?>>
    <i class="fi fi-rr-list-check"></i>Rankings
  </a>
  <a href="gallery.php" data-nav="gallery"<?= navClass('gallery', $activePage, true) ?>>
    <i class="fi fi-rr-picture"></i>Gallery
  </a>
  <a href="shop.php" data-nav="shop"<?= navClass('shop', $activePage, true) ?>>
    <i class="fi fi-rr-shopping-cart"></i>Shop
  </a>
  <a href="contact.php" data-nav="contact"<?= navClass('contact', $activePage, true) ?>>
    <i class="fi fi-rr-envelope"></i>Contact Us
  </a>
  <a href="register.php" class="nav-cta<?= ($activePage === 'register') ? ' active' : '' ?>" data-nav="register">
    <i class="fi fi-rr-pen-field"></i>Register Now
  </a>

</nav>

<!-- ===== NAV STYLES ===== -->
<style>
  /* ── Active state: desktop standard links ── */
  .nav-links a {
    position: relative;
  }
  .nav-links a.active-page:not(.nav-cta) {
    color: var(--accent-light);
    text-shadow: 0 0 12px var(--accent-glow);
  }
  .nav-links a.active-page:not(.nav-cta)::after {
    content: '';
    position: absolute;
    bottom: 4px;
    left: 14px;
    right: 14px;
    height: 1px;
    background: var(--prc-violet);
    box-shadow: 0 0 6px rgba(139,126,255,0.80);
  }

  /* ── Active state: mobile links ── */
  .nav-mobile a.active:not(.nav-cta) {
    color: var(--accent-light);
    background: rgba(139,126,255,0.07);
    text-shadow: 0 0 10px rgba(139,126,255,0.55);
  }

  /* ════════════════════════════════════════
     DESKTOP DROPDOWN
  ════════════════════════════════════════ */
  .nav-links .nav-dropdown {
    position: relative;
    list-style: none;
  }

  /* Trigger button looks like the other nav links */
  .nav-links .nav-dropdown-trigger {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: none;
    border: none;
    padding: 0 14px;
    font: inherit;
    font-size: inherit;
    color: var(--text-mid, #b0aac8);
    cursor: pointer;
    letter-spacing: inherit;
    text-transform: inherit;
    transition: color 0.2s;
    white-space: nowrap;
  }
  .nav-links .nav-dropdown-trigger:hover,
  .nav-links .nav-dropdown[aria-expanded="true"] .nav-dropdown-trigger {
    color: var(--accent-light, #d4cfff);
  }
  .nav-links .nav-dropdown-trigger.active-page {
    color: var(--accent-light);
    text-shadow: 0 0 12px var(--accent-glow);
  }

  /* Caret rotation */
  .nav-dropdown-caret {
    transition: transform 0.22s ease;
    flex-shrink: 0;
  }
  .nav-dropdown.is-open > .nav-dropdown-trigger .nav-dropdown-caret,
  .nav-dropdown[aria-expanded="true"] > .nav-dropdown-trigger .nav-dropdown-caret {
    transform: rotate(180deg);
  }

  /* The dropdown panel */
  .nav-links .nav-dropdown-menu {
    display: none;
    position: absolute;
    top: calc(100% + 14px);
    left: 50%;
    transform: translateX(-50%);
    min-width: 230px;
    list-style: none;
    margin: 0;
    padding: 8px;
    background: var(--nav-bg, rgba(14,12,28,0.97));
    border: 1px solid rgba(139,126,255,0.18);
    border-radius: 10px;
    box-shadow: 0 16px 48px rgba(0,0,0,0.55), 0 0 0 1px rgba(139,126,255,0.06);
    backdrop-filter: blur(18px);
    z-index: 9999;
    /* Entry animation */
    opacity: 0;
    transform: translateX(-50%) translateY(-6px);
    transition: opacity 0.18s ease, transform 0.18s ease;
    pointer-events: none;
  }
  /* Small top arrow */
  .nav-links .nav-dropdown-menu::before {
    content: '';
    position: absolute;
    top: -6px;
    left: 50%;
    transform: translateX(-50%);
    width: 12px;
    height: 6px;
    background: var(--nav-bg, rgba(14,12,28,0.97));
    clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
    border-left: 1px solid rgba(139,126,255,0.18);
    border-right: 1px solid rgba(139,126,255,0.18);
  }
  .nav-links .nav-dropdown.is-open .nav-dropdown-menu {
    display: block;
    opacity: 1;
    transform: translateX(-50%) translateY(0);
    pointer-events: auto;
  }

  /* Dropdown items */
  .nav-links .nav-dropdown-menu li {
    list-style: none;
  }
  .nav-links .nav-dropdown-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 7px;
    color: var(--text-mid, #b0aac8);
    text-decoration: none;
    font-size: 0.875rem;
    font-family: inherit;
    font-weight: inherit;
    letter-spacing: inherit;
    transition: background 0.16s, color 0.16s;
  }
  .nav-links .nav-dropdown-menu a:hover,
  .nav-links .nav-dropdown-menu a.active-page {
    background: rgba(139,126,255,0.10);
    color: var(--accent-light, #d4cfff);
  }
  .nav-links .nav-dropdown-menu a.active-page {
    text-shadow: 0 0 10px var(--accent-glow, rgba(139,126,255,0.6));
  }
  /* Remove the underline pseudo from dropdown items */
  .nav-links .nav-dropdown-menu a.active-page::after {
    display: none;
  }
  .nav-links .nav-dropdown-menu a i {
    font-size: 1rem;
    width: 18px;
    text-align: center;
    flex-shrink: 0;
    color: var(--prc-violet, #8b7eff);
    opacity: 0.85;
  }
  .nav-links .nav-dropdown-menu a span {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
  }
  .nav-links .nav-dropdown-menu a span small {
    font-size: 0.72rem;
    color: var(--text-dim, #6e687e);
    font-weight: 400;
    margin-top: 2px;
  }
  /* Dividers between items */
  .nav-links .nav-dropdown-menu li + li {
    border-top: 1px solid rgba(139,126,255,0.07);
  }

  /* Active group: trigger underline indicator */
  .nav-links .nav-dropdown.is-active-group > .nav-dropdown-trigger {
    position: relative;
  }
  .nav-links .nav-dropdown.is-active-group > .nav-dropdown-trigger::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 14px;
    right: 14px;
    height: 1px;
    background: var(--prc-violet, #8b7eff);
    box-shadow: 0 0 6px rgba(139,126,255,0.80);
  }

  /* ════════════════════════════════════════
     MOBILE ACCORDION
  ════════════════════════════════════════ */
  .nav-mobile-accordion {
    display: flex;
    flex-direction: column;
    width: 100%;
  }
  .nav-mobile-accordion-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    background: none;
    border: none;
    padding: 13px 20px;
    font: inherit;
    font-size: 0.92rem;
    color: var(--text-mid, #b0aac8);
    cursor: pointer;
    text-align: left;
    letter-spacing: 0.02em;
    transition: color 0.2s, background 0.2s;
  }
  .nav-mobile-accordion-trigger span {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .nav-mobile-accordion-trigger span i {
    font-size: 1rem;
    width: 20px;
    text-align: center;
  }
  .nav-mobile-accordion-trigger:hover {
    color: var(--accent-light, #d4cfff);
    background: rgba(139,126,255,0.05);
  }
  .nav-mobile-accordion.is-open .nav-mobile-accordion-trigger {
    color: var(--accent-light, #d4cfff);
    background: rgba(139,126,255,0.06);
  }
  .nav-mobile-accordion.is-open .nav-dropdown-caret {
    transform: rotate(180deg);
  }

  /* Accordion body */
  .nav-mobile-accordion-body {
    display: none;
    flex-direction: column;
    background: rgba(139,126,255,0.04);
    border-top: 1px solid rgba(139,126,255,0.08);
    border-bottom: 1px solid rgba(139,126,255,0.08);
  }
  .nav-mobile-accordion.is-open .nav-mobile-accordion-body {
    display: flex;
  }
  .nav-mobile-accordion-body a {
    padding-left: 48px !important; /* indent under parent icon */
    font-size: 0.875rem !important;
    color: var(--text-mid, #b0aac8);
    opacity: 0.9;
  }
  .nav-mobile-accordion-body a:hover,
  .nav-mobile-accordion-body a.active {
    background: rgba(139,126,255,0.09) !important;
    color: var(--accent-light, #d4cfff) !important;
    opacity: 1;
  }
</style>

<!-- ===== NAV SCRIPTS ===== -->
<script>
(function () {
  // ── Mobile hamburger ──
  var hbtn  = document.getElementById('prc-hamburger');
  var hmenu = document.getElementById('prc-mobile-menu');

  if (hbtn && hmenu) {
    hbtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = hmenu.classList.toggle('open');
      hbtn.classList.toggle('open', isOpen);
      hbtn.setAttribute('aria-expanded', String(isOpen));
      hmenu.setAttribute('aria-hidden', String(!isOpen));
      document.body.style.overflow = isOpen ? 'hidden' : '';
    });

    document.addEventListener('click', function (e) {
      if (hmenu.classList.contains('open') &&
          !hbtn.contains(e.target) &&
          !hmenu.contains(e.target)) {
        hmenu.classList.remove('open');
        hbtn.classList.remove('open');
        hbtn.setAttribute('aria-expanded', 'false');
        hmenu.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
      }
    });
  }

  // ── Desktop dropdowns ──
  var dropdowns = document.querySelectorAll('#main-nav .nav-dropdown');

  function openDropdown(dd) {
    var trigger = dd.querySelector('.nav-dropdown-trigger');
    dd.classList.add('is-open');
    if (trigger) trigger.setAttribute('aria-expanded', 'true');
    dropdowns.forEach(function (other) {
      if (other !== dd) closeDropdown(other);
    });
  }

  function closeDropdown(dd) {
    var trigger = dd.querySelector('.nav-dropdown-trigger');
    dd.classList.remove('is-open');
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
  }

  dropdowns.forEach(function (dd) {
    var trigger = dd.querySelector('.nav-dropdown-trigger');
    var menu    = dd.querySelector('.nav-dropdown-menu');
    if (!trigger || !menu) return;

    var closeTimer = null;

    // ── Hover ──
    dd.addEventListener('mouseenter', function () {
      clearTimeout(closeTimer);
      openDropdown(dd);
    });
    dd.addEventListener('mouseleave', function () {
      closeTimer = setTimeout(function () { closeDropdown(dd); }, 120);
    });

    // ── Click (toggle) ──
    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      if (dd.classList.contains('is-open')) {
        closeDropdown(dd);
      } else {
        openDropdown(dd);
      }
    });

    // ── Keyboard: Escape to close ──
    dd.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeDropdown(dd);
        trigger.focus();
      }
    });
  });

  // Close all dropdowns on outside click
  document.addEventListener('click', function (e) {
    dropdowns.forEach(function (dd) {
      if (!dd.contains(e.target)) closeDropdown(dd);
    });
  });

  // ── Mobile accordions ──
  var accordions = document.querySelectorAll('#prc-mobile-menu .nav-mobile-accordion');

  accordions.forEach(function (acc) {
    var btn = acc.querySelector('.nav-mobile-accordion-trigger');
    if (!btn) return;

    btn.addEventListener('click', function () {
      var isOpen = acc.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', String(isOpen));
    });
  });
})();
</script>