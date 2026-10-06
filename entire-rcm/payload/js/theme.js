/* Entire RCM — front-end interactions.
   Ported 1:1 from the Stitch design's interaction layer. */
(function () {
  'use strict';

  /* ---------------------------------------------------------- drawer --- */
  window.erToggleDrawer = function (open) {
    var d = document.getElementById('er-drawer');
    if (!d) return;
    d.classList.toggle('is-open', open);
    document.body.classList.toggle('er-drawer-open', open);
    var b = document.querySelector('.er-burger');
    if (b && b.getAttribute('aria-expanded') !== null) b.setAttribute('aria-expanded', open ? 'true' : 'false');
  };
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') window.erToggleDrawer(false);
  });

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  /* -------------------------------------------------------- calculator -- */
  function money(n) { return '$' + Math.round(n).toLocaleString('en-US'); }

  function updateROICalculator() {
    var vol = document.getElementById('calc-volume-slider');
    var den = document.getElementById('calc-denial-slider');
    var ar = document.getElementById('calc-ar-slider');
    if (!vol) return;

    var v = parseFloat(vol.value), d = parseFloat(den.value), a = parseFloat(ar.value);
    document.getElementById('calc-volume-text').textContent = money(v);
    document.getElementById('calc-denial-text').textContent = d + '%';
    document.getElementById('calc-ar-text').textContent = a + ' Days';

    var annualVol = v * 12;
    var lost = annualVol * (d / 100);
    var recoverable = Math.round(lost * 0.72);
    document.getElementById('calc-recovered-annual').textContent = money(recoverable);
    document.getElementById('calc-three-year').textContent = '+' + money(recoverable * 3);
    document.getElementById('calc-ar-reduction').textContent = Math.max(12, Math.round(a - 14)) + ' Days Faster';
  }

  /* ------------------------------------------------------ quick audit --- */
  function runQuickAudit(show) {
    var sel = document.getElementById('audit-specialty');
    var rng = document.getElementById('audit-monthly-volume');
    if (!sel || !rng) return;
    var annual = parseFloat(rng.value) * 12;
    var recapture = Math.round(annual * 0.148);
    var out = document.getElementById('quick-audit-result-banner');
    document.getElementById('quick-result-lift').textContent = '$' + recapture.toLocaleString('en-US') + ' / yr';
    document.getElementById('quick-result-summary').textContent =
      'Based on average ' + sel.value + ' claim denial leakages, Entire RCM recovers up to 14.8% in ' +
      'previously write-off-prone revenue within 60 days.';
    var label = document.getElementById('audit-volume-label');
    if (label) label.textContent = money(parseFloat(rng.value)) + ' / mo';
    if (show && out) {
      out.classList.add('is-visible');
      out.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

  /* ------------------------------------------------------------ tabs ---- */
  /* Elementor puts our custom classes on the widget WRAPPER; the href lives
     on the inner <a class="elementor-button">. Always resolve through both. */
  function hrefOf(el) {
    var a = el.matches('a[href]') ? el : el.querySelector('a[href]');
    return a ? a.getAttribute('href') : '';
  }
  function keyOf(el, prefix) {
    var raw = hrefOf(el).replace('#', '');
    return (prefix && raw.indexOf(prefix) === 0) ? raw.slice(prefix.length) : raw;
  }

  function bindFilter(selector, attr, allKey, prefix) {
    var btns = document.querySelectorAll(selector);
    if (!btns.length) return;
    btns.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var key = keyOf(btn, prefix);
        btns.forEach(function (b) {
          b.classList.toggle('is-active', b === btn);
        });
        document.querySelectorAll('[' + attr + ']').forEach(function (card) {
          var mine = card.getAttribute(attr);
          card.style.display = (key === allKey || mine === key) ? '' : 'none';
        });
      });
    });
  }

  /* ------------------------------------------------------- pricing ------ */
  function setPricingScale(scale) {
    var rates = {
      standard: ['4.2%', '4.8%', 'Custom %'],
      growth: ['3.8%', '4.4%', '2.9% - 3.5%']
    }[scale];
    if (!rates) return;
    document.querySelectorAll('.er-price__v[data-rate]').forEach(function (el, i) {
      if (rates[i] !== undefined) el.textContent = rates[i];
    });
  }

  /* ----------------------------------------------------------- wire up -- */
  /* Keep the hero clear of the fixed header at every breakpoint. */
  function syncHeaderHeight() {
    var h = document.querySelector('.er-header');
    if (!h) return;
    document.documentElement.style.setProperty('--er-header-h', h.offsetHeight + 'px');
  }

  ready(function () {
    syncHeaderHeight();
    window.addEventListener('resize', syncHeaderHeight);
    window.addEventListener('load', syncHeaderHeight);
    if (window.ResizeObserver) {
      var h = document.querySelector('.er-header');
      if (h) new ResizeObserver(syncHeaderHeight).observe(h);
    }
    ['calc-volume-slider', 'calc-denial-slider', 'calc-ar-slider'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('input', updateROICalculator);
    });
    updateROICalculator();

    var qs = document.getElementById('audit-specialty');
    var qr = document.getElementById('audit-monthly-volume');
    if (qs) qs.addEventListener('change', function () { runQuickAudit(true); });
    if (qr) qr.addEventListener('input', function () { runQuickAudit(true); });
    runQuickAudit(false);
    var qbtn = document.getElementById('audit-quick-btn');
    if (qbtn) qbtn.addEventListener('click', function () { runQuickAudit(true); });

    bindFilter('.er-cases__tabs .er-tab', 'data-specialty', 'all', 'cs-');

    document.querySelectorAll('.er-price__toggle .er-tab').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelectorAll('.er-price__toggle .er-tab').forEach(function (b) {
          b.classList.toggle('is-active', b === btn);
        });
        setPricingScale(keyOf(btn, 'scale-').indexOf('growth') > -1 ? 'growth' : 'standard');
      });
    });

    // Case-study cards need their filter key from their own class suffix.
    document.querySelectorAll('.er-case[class*="er-case--"]').forEach(function (card) {
      var m = card.className.match(/er-case--(\w+)/);
      if (m) card.setAttribute('data-specialty', m[1]);
    });

    // Smooth-scroll every in-page anchor (Elementor buttons and plain links).
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
      var id = a.getAttribute('href').slice(1);
      if (!id || /^(cs-|scale-)/.test(id)) return;
      a.addEventListener('click', function (e) {
        var t = document.getElementById(id);
        if (!t) return;
        e.preventDefault();
        t.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    // Confirm the audit lead form on success (CF7 fires wpcf7mailsent).
    document.addEventListener('wpcf7mailsent', function (ev) {
      var form = ev.target.closest('.wpcf7');
      if (!form) return;
      var c = form.parentElement.querySelector('.er-confirm');
      if (c) c.classList.add('is-visible');
    });
  });

  window.erRunQuickAudit = runQuickAudit;
})();
