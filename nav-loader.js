/**
 * nav-loader.js  —  Philippine Robotics Cup 2026
 *
 * Drop ONE tag into every page's <head>:
 *   <script src="nav-loader.js" defer></script>
 *
 * Then place ONE mount point anywhere in <body>
 * (ideally the very first element inside <body>):
 *   <div id="prc-nav-mount"></div>
 *
 * The script will:
 *  1. Fetch nav.html and inject it at #prc-nav-mount
 *  2. Auto-highlight the correct nav link based on the
 *     current page filename  (e.g. "gallery.html" → data-nav="gallery")
 *  3. Wire the hamburger toggle
 *  4. Wire the scroll-shadow on the nav bar
 *
 * ─────────────────────────────────────────────────
 * To mark a link active on a page that doesn't match
 * the filename automatically, add this BEFORE the
 * loader script tag:
 *
 *   <meta name="prc-nav-active" content="gallery">
 *
 * Valid content values (match the data-nav attributes):
 *   home | categories | rankings | gallery | contact
 *   shop | register
 * ─────────────────────────────────────────────────
 */

(function () {
  'use strict';

  // ── 1. Locate mount point ──────────────────────────────────────
  var mount = document.getElementById('prc-nav-mount');
  if (!mount) {
    console.warn('[nav-loader] No #prc-nav-mount element found. Add <div id="prc-nav-mount"></div> to your <body>.');
    return;
  }

  // ── 2. Resolve nav.html path relative to this script ──────────
  var scriptSrc = (document.currentScript && document.currentScript.src) || '';
  var base = scriptSrc.substring(0, scriptSrc.lastIndexOf('/') + 1) || '';
  var navUrl = base + 'nav.html';

  // ── 3. Fetch + inject ──────────────────────────────────────────
  fetch(navUrl)
    .then(function (res) {
      if (!res.ok) throw new Error('nav.html fetch failed: ' + res.status);
      return res.text();
    })
    .then(function (html) {
      mount.innerHTML = html;
      afterInject();
    })
    .catch(function (err) {
      console.error('[nav-loader]', err);
    });

  // ── 4. Post-inject setup ───────────────────────────────────────
  function afterInject() {

    // ── 4a. Active link detection ──────────────────────────────
    // Priority 1: <meta name="prc-nav-active" content="...">
    var metaActive = (document.querySelector('meta[name="prc-nav-active"]') || {}).content || '';

    // Priority 2: match current filename to known page map
    var filename = window.location.pathname.split('/').pop().replace(/^$/, 'index.html');
    var fileMap = {
      'index.html':      'home',
      '':                'home',
      'categories.html': 'categories',
      'rankings.html':   'rankings',
      'gallery.html':    'gallery',
      'contact.html':    'contact',
      'shop.html':       'shop',
      'register.html':   'register',
    };
    var activeKey = metaActive || fileMap[filename] || '';

    if (activeKey) {
      document.querySelectorAll('[data-nav="' + activeKey + '"]').forEach(function (el) {
        el.classList.add('active');
      });
    }

    // ── 4b. Hamburger toggle ───────────────────────────────────
    var hamburger  = document.getElementById('prc-hamburger');
    var mobileMenu = document.getElementById('prc-mobile-menu');

    function toggleMenu(open) {
      mobileMenu.classList.toggle('open', open);
      hamburger.classList.toggle('open', open);
      hamburger.setAttribute('aria-expanded', open ? 'true' : 'false');
      mobileMenu.setAttribute('aria-hidden',  open ? 'false' : 'true');
      document.body.style.overflow = open ? 'hidden' : '';
    }

    if (hamburger && mobileMenu) {
      hamburger.addEventListener('click', function (e) {
        e.stopPropagation();
        toggleMenu(!mobileMenu.classList.contains('open'));
      });

      // Close on mobile link click
      mobileMenu.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () { toggleMenu(false); });
      });

      // Close on outside click
      document.addEventListener('click', function (e) {
        if (
          mobileMenu.classList.contains('open') &&
          !hamburger.contains(e.target) &&
          !mobileMenu.contains(e.target)
        ) {
          toggleMenu(false);
        }
      });
    }

    // ── 4c. Scroll shadow on nav bar ──────────────────────────
    var nav = document.getElementById('main-nav');
    if (nav) {
      function syncNav() {
        nav.classList.toggle('scrolled', window.scrollY > 40);
      }
      window.addEventListener('scroll', syncNav, { passive: true });
      syncNav();
    }

    // ── 4d. Custom-cursor hover (if cursor elements exist) ─────
    var cursorDot  = document.getElementById('cursorDot');
    var cursorRing = document.getElementById('cursorRing');
    if (cursorDot && cursorRing) {
      document.querySelectorAll('#main-nav a, #main-nav button, #prc-mobile-menu a').forEach(function (el) {
        if (el._navCursorBound) return;
        el._navCursorBound = true;
        el.addEventListener('mouseenter', function () {
          cursorRing.classList.add('hovered');
          cursorDot.style.background   = 'var(--creo-amber)';
          cursorDot.style.boxShadow    = 'var(--glow-orange)';
        });
        el.addEventListener('mouseleave', function () {
          cursorRing.classList.remove('hovered');
          cursorDot.style.background   = 'var(--prc-violet)';
          cursorDot.style.boxShadow    = 'var(--glow-primary)';
        });
      });
    }
  }

})();