/* Entire RCM — front-end layer.
 *
 * The page markup is the Stitch export's own, so the design's interaction
 * handlers (toggleFAQ, filterCaseStudies, setPricingScale, updateROICalculator,
 * runQuickAuditCompute) are reused verbatim below. Only two things had to be
 * added: restoring the `data-*` attributes that Elementor containers cannot
 * carry, and wiring Contact Form 7 into the design's confirmation flow.
 */
(function () {
  'use strict';

  /* ---------------------------------------------------------- [1/3] ----
   * Elementor containers cannot hold arbitrary HTML attributes, so the
   * generator encodes `data-specialty` (and friends) as `erdata-<name>-<value>`
   * inside the container's class list. Restore the real attributes before the
   * design's handlers run.
   * -------------------------------------------------------------------- */
  function restoreDataAttributes() {
    document.querySelectorAll('[class*="erdata-"]').forEach(function (el) {
      (el.className || '').split(/\s+/).forEach(function (c) {
        var m = c.match(/^erdata-([a-z0-9]+)-(.+)$/i);
        if (!m) return;
        var name = 'data-' + m[1];
        if (el.hasAttribute(name)) return;
        el.setAttribute(name, m[2].replace(/_/g, ' '));
      });
    });
  }

  /* ---------------------------------------------------------- [2/3] ----
   * The design's interaction layer, unchanged.
   * -------------------------------------------------------------------- */
  window.runQuickAuditCompute = function () {
    var specialtyEl = document.getElementById('audit-specialty');
    var volumeEl = document.getElementById('audit-monthly-volume');
    var banner = document.getElementById('quick-audit-result-banner');
    var lift = document.getElementById('quick-result-lift');
    var summary = document.getElementById('quick-result-summary');
    if (!specialtyEl || !volumeEl || !banner || !lift || !summary) return;

    var specialty = specialtyEl.value;
    var monthlyVol = parseFloat(volumeEl.value) || 0;
    var annualVolume = monthlyVol * 12;
    var estimatedRecapture = Math.round(annualVolume * 0.148);

    lift.innerText = '$' + estimatedRecapture.toLocaleString() + ' / yr';
    summary.innerText = 'Based on average ' + specialty + ' claim denial leakages, Entire RCM ' +
      'recovers up to 14.8% in previously write-off-prone revenue within 60 days.';

    banner.classList.remove('hidden');
    banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };

  window.updateROICalculator = function () {
    var v = document.getElementById('calc-volume-slider');
    var d = document.getElementById('calc-denial-slider');
    var a = document.getElementById('calc-ar-slider');
    if (!v || !d || !a) return;

    var vol = parseFloat(v.value);
    var denialPct = parseFloat(d.value);
    var arDays = parseFloat(a.value);

    document.getElementById('calc-volume-text').innerText = '$' + vol.toLocaleString();
    document.getElementById('calc-denial-text').innerText = denialPct + '%';
    document.getElementById('calc-ar-text').innerText = arDays + ' Days';

    var annualVol = vol * 12;
    var lostToDenials = annualVol * (denialPct / 100);
    var recoverableAnnual = Math.round(lostToDenials * 0.72);
    var threeYearImpact = Math.round(recoverableAnnual * 3);
    var arDaysFaster = Math.max(12, Math.round(arDays - 14));

    document.getElementById('calc-recovered-annual').innerText = '$' + recoverableAnnual.toLocaleString();
    document.getElementById('calc-three-year').innerText = '+$' + threeYearImpact.toLocaleString();
    document.getElementById('calc-ar-reduction').innerText = arDaysFaster + ' Days Faster';
  };

  window.filterCaseStudies = function (specialty) {
    var cards = document.querySelectorAll('.case-study-card');
    var buttons = document.querySelectorAll('.case-study-tab-btn');

    buttons.forEach(function (btn) {
      if (btn.getAttribute('data-category') === specialty) {
        btn.classList.add('bg-surface-container-lowest', 'text-primary', 'font-bold', 'shadow-sm');
        btn.classList.remove('text-on-surface-variant');
      } else {
        btn.classList.remove('bg-surface-container-lowest', 'text-primary', 'font-bold', 'shadow-sm');
        btn.classList.add('text-on-surface-variant');
      }
    });

    cards.forEach(function (card) {
      var cardSpec = card.getAttribute('data-specialty');
      card.style.display = (specialty === 'all' || cardSpec === specialty) ? 'flex' : 'none';
    });
  };

  window.setPricingScale = function (scale) {
    var btnStd = document.getElementById('btn-scale-standard');
    var btnGro = document.getElementById('btn-scale-growth');
    var r1 = document.getElementById('tier-1-rate');
    var r2 = document.getElementById('tier-2-rate');
    var r3 = document.getElementById('tier-3-rate');
    if (!btnStd || !btnGro || !r1 || !r2 || !r3) return;

    if (scale === 'growth') {
      btnGro.classList.add('bg-surface-container-lowest', 'text-primary', 'font-bold', 'shadow-sm');
      btnGro.classList.remove('text-on-surface-variant');
      btnStd.classList.remove('bg-surface-container-lowest', 'text-primary', 'font-bold', 'shadow-sm');
      btnStd.classList.add('text-on-surface-variant');
      r1.innerText = '3.8%'; r2.innerText = '4.4%'; r3.innerText = '2.9% - 3.5%';
    } else {
      btnStd.classList.add('bg-surface-container-lowest', 'text-primary', 'font-bold', 'shadow-sm');
      btnStd.classList.remove('text-on-surface-variant');
      btnGro.classList.remove('bg-surface-container-lowest', 'text-primary', 'font-bold', 'shadow-sm');
      btnGro.classList.add('text-on-surface-variant');
      r1.innerText = '4.2%'; r2.innerText = '4.8%'; r3.innerText = 'Custom %';
    }
  };

  window.toggleFAQ = function (btn) {
    var item = btn.closest('.faq-item');
    if (!item) return;
    var content = item.querySelector('.faq-content');
    var icon = btn.querySelector('.faq-icon');
    var wasHidden = content.classList.contains('hidden');

    document.querySelectorAll('.faq-item .faq-content').forEach(function (c) { c.classList.add('hidden'); });
    document.querySelectorAll('.faq-item .faq-icon').forEach(function (ic) {
      ic.innerText = 'add';
      ic.classList.remove('rotate-45');
    });

    if (wasHidden) {
      content.classList.remove('hidden');
      if (icon) icon.innerText = 'remove';
    }
  };

  window.submitComprehensiveAuditForm = function () {
    var submitBtn = document.getElementById('lead-submit-button');
    var confirmation = document.getElementById('booking-confirmation-alert');
    if (submitBtn) submitBtn.classList.add('hidden');
    if (confirmation) confirmation.classList.remove('hidden');
  };

  /* ---------------------------------------------------------- [3/3] ----
   * Contact Form 7 wiring: the design's forms are real CF7 forms now, so the
   * confirmation behaviour is driven off CF7's own events, and the submit
   * button is restored on failure so a visitor can correct and retry.
   * -------------------------------------------------------------------- */
  function wireForms() {
    document.addEventListener('wpcf7mailsent', function (ev) {
      var wrap = ev.target.closest ? ev.target.closest('.wpcf7') : null;
      var form = wrap ? wrap.querySelector('form.wpcf7-form') : null;
      if (form && form.querySelector('#lead-submit-button')) {
        window.submitComprehensiveAuditForm();
      }
      if (form && document.getElementById('audit-monthly-volume')) {
        window.runQuickAuditCompute();
      }
    });

    document.addEventListener('wpcf7submit', function (ev) {
      var wrap = ev.target.closest ? ev.target.closest('.wpcf7') : null;
      var btn = wrap ? wrap.querySelector('#lead-submit-button, #audit-quick-btn') : null;
      if (btn) { btn.disabled = false; btn.classList.remove('opacity-70', 'cursor-wait'); }
    });

    document.addEventListener('wpcf7invalid', function (ev) {
      var wrap = ev.target.closest ? ev.target.closest('.wpcf7') : null;
      var btn = wrap ? wrap.querySelector('#lead-submit-button, #audit-quick-btn') : null;
      if (btn) { btn.disabled = false; btn.classList.remove('opacity-70', 'cursor-wait'); }
    });

    var heroRange = document.getElementById('audit-monthly-volume');
    if (heroRange) heroRange.addEventListener('input', window.runQuickAuditCompute);
    var heroSelect = document.getElementById('audit-specialty');
    if (heroSelect) heroSelect.addEventListener('change', window.runQuickAuditCompute);
  }

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    restoreDataAttributes();
    window.updateROICalculator();
    wireForms();
  });
})();
