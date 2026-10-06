#!/usr/bin/env python3
"""Generate the Entire RCM Elementor page data (native widgets + design CSS classes)."""
import json, random, string, os

random.seed(20261006)
def uid():
    return ''.join(random.choice('0123456789abcdef') for _ in range(7))

def pad(v='0'):
    return {"unit": "px", "top": v, "right": v, "bottom": v, "left": v, "isLinked": True}

NO_PAD = {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}

def C(classes='', children=None, *, full=True, eid=None, direction=None, gap=None, extra=None):
    # NOTE: Elementor 4.x names the custom-class control `css_classes` on
    # containers/sections/columns, while widgets still use `_css_classes`.
    s = {"content_width": "full" if full else "boxed", "padding": NO_PAD,
         "margin": NO_PAD, "flex_direction": direction or "column"}
    if classes: s["css_classes"] = classes
    if eid: s["_element_id"] = eid
    if gap: s["gap"] = gap
    if extra: s.update(extra)
    return {"id": uid(), "elType": "container", "settings": s, "elements": children or [], "isInner": False}

def W(wtype, settings, classes=''):
    s = dict(settings)
    if classes: s["_css_classes"] = classes
    return {"id": uid(), "elType": "widget", "widgetType": wtype, "settings": s, "elements": [], "isInner": False}

def H(tag, text, classes, extra=None):
    st = {"title": text, "header_size": tag}
    if extra: st.update(extra)
    return W("heading", st, classes)

def P(html, classes=''):
    return W("text-editor", {"editor": html}, classes)

def BTN(text, href, classes, *, align=None, extra=None):
    st = {"text": text, "link": {"url": href, "is_external": "", "nofollow": ""},
          "size": "sm", "selected_icon": {"value": "", "library": ""}}
    if extra: st.update(extra)
    return W("button", st, classes)

def IMG(src, alt, classes, width=None):
    st = {"image": {"url": src, "id": "", "alt": alt, "source": "library"}, "image_size": "full",
          "align": "left"}
    if width: st["width"] = width
    return W("image", st, classes)

def HTML(html, classes=''):
    return W("html", {"html": html}, classes)

def SHORTCODE(sc, classes=''):
    return W("shortcode", {"shortcode": sc}, classes)

def ICON(name, classes='', extra=None):
    st = {"selected_icon": {"value": "fas fa-check", "library": "fa-solid"}, "view": "default"}
    if extra: st.update(extra)
    return W("icon", st, classes)

def ICONLIST(items, classes='', icon="fas fa-check"):
    st = {"icon_list": [{"text": t, "_id": uid(), "selected_icon": {"value": icon, "library": "fa-solid"}} for t in items],
          "space_between": {"unit": "px", "size": 10}, "icon_color": "#4750c7", "text_color": "#45464f"}
    return W("icon-list", st, classes)

def ACCORDION(pairs, classes=''):
    st = {"tabs": [{"tab_title": q, "tab_content": f"<p>{a}</p>", "_id": uid()} for q, a in pairs],
          "title_size": "h3", "selected_icon": {"value": "fas fa-plus", "library": "fa-solid"},
          "selected_active_icon": {"value": "fas fa-minus", "library": "fa-solid"}}
    return W("accordion", st, classes)

# ---------------------------------------------------------------- helpers ---
def p_text(t, cls='er-p'):        return P(f'<p class="{cls}">{t}</p>')
def list_html(items, cls='er-plist', ico=None, icls='er-ico er-ico--md'):
    out = []
    for it in items:
        icon = ico or it.get('i', 'check')
        out.append(f'<li><span class="{icls} er-ico--i-{icon}">{icon}</span><div>'
                   f'<div class="er-plist__t">{it["t"]}</div>'
                   f'<div class="er-plist__d">{it["d"]}</div></div></li>')
    return f'<ul class="{cls}">' + ''.join(out) + '</ul>'

EHR = "{{LOGO}}"
TEL = "tel:+18884207261"

# =========================================================================
PARTS = []

# ---- 0. HEADER -----------------------------------------------------------
announce = C('er-announce', [
    C('er-announce__inner', [
        P('<span class="er-ico er-ico--sm" style="color:#4edea3">bolt</span> '
          '<span>2025 CMS Billing Compliance &amp; AI Claim Scrubbing Engine Live &bull; '
          'Free 30-Day Revenue Cycle Audit ($2,500 Value)</span> '
          '<a href="#schedule-audit">Claim Free Audit &rarr;</a>', 'er-announce__text'),
    ], extra={"flex_direction": "row", "flex_wrap": "wrap"}),
    HTML('<button type="button" class="er-announce__close" aria-label="Dismiss announcement" '
         'onclick="document.getElementById(\'er-announce\').classList.add(\'is-hidden\')">'
         '<span class="er-ico er-ico--sm">close</span></button>', 'er-announce__btnwrap'),
], eid="er-announce")

brand = C('er-brand', [
    IMG(EHR, 'Entire RCM Revenue Cycle Logo', 'er-brand__img'),
    H('h2', 'Entire RCM', 'er-brand__name er-h4'),
], extra={"flex_direction": "row", "align_items": "center"})

menu_items = [("Solutions", "#pillars"), ("Specialties", "#case-studies"), ("Technology", "#technology"),
              ("Case Studies", "#case-studies"), ("Pricing", "#pricing-section"), ("Resources", "#faq")]
menu = C('er-menu', [BTN(t, h, 'er-menulink') for t, h in menu_items],
         extra={"flex_direction": "row", "flex_wrap": "wrap"})

nav_actions = C('er-nav__actions', [
    BTN('<span class="er-ico er-ico--md er-ico--sec">call</span> +1 (888) 420-RCM1', TEL, 'er-nav__phone'),
    BTN('Client Portal', '#client-portal', 'er-nav__portal'),
    BTN('Book Free Audit', '#schedule-audit', 'er-nav__cta'),
    HTML('<button type="button" class="er-burger" aria-label="Open menu" aria-expanded="false" '
         'onclick="erToggleDrawer(true)"><span class="er-ico er-ico--lg">menu</span></button>', 'er-burger-wrap'),
], extra={"flex_direction": "row", "align_items": "center"})

nav = C('er-nav', [C('er-nav__inner', [brand, menu, nav_actions],
                     extra={"flex_direction": "row", "justify_content": "space-between", "align_items": "center"})])

drawer = C('er-drawer', [
    C('er-drawer__head', [H('h2', 'Entire RCM', 'er-h4'),
                          HTML('<button type="button" class="er-burger" aria-label="Close menu" '
                               'onclick="erToggleDrawer(false)"><span class="er-ico er-ico--lg">close</span></button>')],
      extra={"flex_direction": "row", "justify_content": "space-between", "align_items": "center"}),
    C('er-drawer__nav', [BTN(t, h, 'er-menulink er-menulink--drawer') for t, h in menu_items]),
    C('er-drawer__actions', [
        BTN('<span class="er-ico er-ico--md er-ico--sec">call</span> +1 (888) 420-RCM1', TEL, 'er-nav__phone'),
        BTN('Client Portal', '#client-portal', 'er-nav__portal'),
        BTN('Book Free Audit', '#schedule-audit', 'er-nav__cta'),
    ]),
], eid="er-drawer")

PARTS.append(C('er-header', [announce, nav, drawer]))

# ---- 1. HERO -------------------------------------------------------------
stats = [("14 Days", "AR Days (US Avg: 42)"), ("99.2%", "Clean Claims First-Pass"),
         ("$180M+", "Annual Recovery Yield"), ("100%", "HIPAA &amp; SOC-2 Type II")]
hero_stats = C('er-stats', [C('er-stat', [P(f'<p class="er-stat__v">{v}</p>'),
                                         P(f'<p class="er-stat__l">{l}</p>')]) for v, l in stats],
               extra={"flex_direction": "row", "flex_wrap": "wrap"})

hero_left = C('er-hero__copy', [
    P('<span class="er-dot"></span><span>Next-Gen Healthcare Revenue Engine &bull; 99.2% Clean Claims Rate</span>',
      'er-hero__badge'),
    H('h1', 'Maximized Collections. Zero Billing Friction. <span class="er-accent">End-to-End Medical RCM.</span>', 'er-h1'),
    P('<p class="er-p er-hero__lede">We empower medical practices, health systems, and specialty clinics to reduce '
      'claim denials by up to 98%, accelerate cash flow within 21 days, and recover uncollected revenue with '
      'certified AAPC coders and AI-driven claim scrubbing.</p>'),
    C('er-hero__cta', [
        BTN('Claim Your Free Practice Audit <span class="er-ico er-ico--md">arrow_forward</span>', '#quick-audit-card', 'er-btn er-btn--primary'),
        BTN('<span class="er-ico er-ico--md">calculate</span> Calculate Lost Revenue', '#interactive-calculator-section', 'er-btn er-btn--light'),
    ], extra={"flex_direction": "row", "flex_wrap": "wrap"}),
    hero_stats,
])

hero_right = C('er-quickcard', [
    C('er-quickcard__head', [
        P('<span class="er-ico er-ico--md er-ico--sec">analytics</span>', 'er-quickcard__eye'),
        H('h3', 'Instant Practice Yield Estimate', 'er-h3'),
    ], extra={"flex_direction": "row", "align_items": "center"}),
    SHORTCODE('[contact-form-7 id="{{CF7_QUICK}}" title="Instant Practice Yield Estimate"]', 'er-quick-form'),
], eid="quick-audit-card")

hero = C('er-hero', [
    HTML('<div class="er-hero__glow" aria-hidden="true"></div>', 'er-hero__glowwrap'),
    C('er-wrap', [C('er-hero__grid', [hero_left, hero_right], extra={"flex_direction": "row", "flex_wrap": "wrap"})]),
], eid="hero")

# ---- 2. TRUST STRIP ------------------------------------------------------
trust = C('er-sec er-sec--sm er-bg-low', [C('er-wrap', [
    P('<p class="er-label-sm er-eyebrow">Trusted By 450+ Medical Practices, Health Systems, and Surgery Centers '
      'Nationwide &bull; Native EHR Connectivity</p>'),
    P('<div class="er-logos"><span>Epic</span><span>Cerner</span><span>athenahealth</span><span>eClinicalWorks</span>'
      '<span>AdvancedMD</span><span>Kareo / Tebra</span><span>Veradigm</span><span>NextGen</span></div>'),
    P('<div class="er-certs">'
      '<span class="er-cert"><span class="er-ico er-ico--sm er-ico--sec">verified</span> AAPC Certified Master Coders</span>'
      '<span class="er-cert"><span class="er-ico er-ico--sm er-ico--sec">health_and_safety</span> AHIMA Accredited Protocol</span>'
      '<span class="er-cert"><span class="er-ico er-ico--sm er-ico--sec">account_balance</span> HBMA Active Member</span>'
      '<span class="er-cert"><span class="er-ico er-ico--sm er-ico--sec">shield</span> SOC-2 Type II Attested</span>'
      '</div>'),
])])

# ---- 3. PROBLEM / AGITATION ---------------------------------------------
bad = [{"t": "18% &ndash; 25% Average Denial Rate", "d": "Claims rejected due to outdated payer edits, missing prior-auth attachments, and unverified patient coverage."},
       {"t": "45+ Days Dragged in Aging A/R", "d": "Cash receivables get trapped in aging buckets past 90 and 120 days until timely filing limits expire."},
       {"t": "High Staff Turnover &amp; Chronic Training Costs", "d": "Constant medical biller departures cause missed claims batches, unworked denials, and heavy payroll burdens."},
       {"t": "Zero Algorithmic Pre-Scrubbing", "d": "Billing staff manually inputs codes without continuous automated CMS fee schedule verification."}]
good = [{"t": "99.2% First-Pass Clean Claim Yield", "d": "Proprietary 3-tier validation scrubs against 1.2M+ payer-specific rules before clearinghouse transit."},
        {"t": "Guaranteed Under 25-Day AR Velocity", "d": "Automated payment posting, daily denial work queues, and digital patient collections fast-track liquidity."},
        {"t": "Dedicated AAPC Certified Specialty Coders", "d": "No generalists. Your claims are touched exclusively by credentialed coders matching your subspecialty."},
        {"t": "99.9% Denial Appeal Win-Rate", "d": "Every legitimate denial is aggressively mobilized with clinical records and legal appeals within 48 hours."}]

versus = C('er-versus', [
    C('er-versus__card er-versus__card--bad', [
        P('<p class="er-versus__tag"><span class="er-ico er-ico--sm">trending_down</span> Traditional / In-House Approach</p>'),
        H('h3', 'The Revenue Leak Trap', 'er-h3'),
        P(list_html(bad, ico='cancel', icls='er-ico er-ico--md er-ico--bad'), 'er-plist-wrap'),
        C('er-versus__foot', [P('<span>Average Practice Loss / Year:</span>'),
                              P('<strong>-$142,000</strong>')],
          extra={"flex_direction": "row", "justify_content": "space-between", "align_items": "baseline"}),
    ]),
    C('er-versus__card er-versus__card--good', [
        P('<p class="er-versus__tag"><span class="er-ico er-ico--sm">rocket_launch</span> The Entire RCM Engine</p>'),
        H('h3', 'Flawless Institutional Precision', 'er-h3'),
        P(list_html(good, ico='check_circle', icls='er-ico er-ico--md er-ico--good'), 'er-plist-wrap'),
        C('er-versus__foot', [P('<span>Net Practice Collections Uplift:</span>'),
                              P('<strong>+18.4% ARR</strong>')],
          extra={"flex_direction": "row", "justify_content": "space-between", "align_items": "baseline"}),
    ]),
], extra={"flex_direction": "row", "flex_wrap": "wrap"})

problem = C('er-sec er-bg-surface', [C('er-wrap', [
    C('er-maxw-3xl', [
        P('<span class="er-eyebrow">Practice Profitability Diagnosis</span>'),
        H('h2', 'Why Traditional Medical Billing Is Bleeding Your Practice Dry', 'er-h2'),
        P('<p class="er-p er-mt-md">Between complex clearinghouse rejections, evolving payer fee schedules, and '
          'overburdened staff, average US clinics lose 14% to 22% of deserved revenue each billing cycle.</p>'),
    ], extra={"flex_direction": "column", "align_items": "flex-start"}),
    versus,
])], eid="solutions")

# ---- 4. PILLARS ----------------------------------------------------------
pillars = [
    ("Pillar 01", "rule", "AI Claims Scrubbing Engine",
     "Every claim passes through 3-tier validation: patient eligibility checks, NCCI edit scrubbing, and LCD/NCD coverage policies before electronic clearinghouse dispatch.",
     "99.2% First-Pass Clean"),
    ("Pillar 02", "verified", "AAPC &amp; AHIMA Certified Coders",
     "Double-board certified medical coders ensure exact ICD-10, CPT, and HCPCS modifier allocation&mdash;protecting your revenue yield while shielding against federal CMS audit risks.",
     "Zero Under-Coding Risk"),
    ("Pillar 03", "gavel", "Relentless Appeals &amp; Denial AR",
     "Denied claims are categorized immediately by CARC/RARC codes and appealed with medical necessity packets within 48 hours. Zero aging balances written off passively.",
     "48-Hour Appeal Dispatch"),
    ("Pillar 04", "contactless", "Frictionless Patient Billing",
     "Clear, transparent digital statements, SMS Text-to-Pay, secure patient portal payments, and empathetic US-based patient financial billing support that preserves patient goodwill.",
     "3.8x Faster Patient Collections"),
]
pillar_cards = [C('er-pillar', [
    C('er-pillar__top', [P(f'<div class="er-pillar__icon"><span class="er-ico er-ico--lg">{ic}</span></div>'),
                         P(f'<p class="er-pillar__no">{no}</p>')],
      extra={"flex_direction": "column", "align_items": "flex-start"}),
    H('h3', title, 'er-h3'),
    P(f'<p class="er-p-sm er-mt-sm">{body}</p>'),
    P(f'<p class="er-pillar__metric"><span class="er-ico er-ico--sm">verified</span> {metric}</p>'),
]) for no, ic, title, body, metric in pillars]

pillars_sec = C('er-sec er-bg-lowest', [C('er-wrap', [
    C('er-center er-maxw-3xl er-mx', [
        P('<span class="er-eyebrow">Our Operating Architecture</span>'),
        H('h2', 'Four Pillars of Autonomous Revenue Capture', 'er-h2'),
        P('<p class="er-p er-mt-sm">Engineered to eradicate human error, bypass payer road-blocks, and collect every '
          'cent your physicians earned.</p>'),
    ], extra={"flex_direction": "column", "align_items": "center"}),
    C('er-grid er-grid--4', pillar_cards, extra={"flex_direction": "row", "flex_wrap": "wrap"}),
])], eid="pillars")

# ---- 5. BENTO / TECHNOLOGY ----------------------------------------------
tiles = [("First-Pass Yield", "99.4%", "&uarr; +4.2% vs Q3"),
         ("Avg Days in AR", "14.6 d", "&darr; -21.4 days faster"),
         ("Monthly Collections", "$682,410", "&uarr; 98.2% Collected"),
         ("Appeals Win Rate", "99.1%", "48-Hr Mobilization")]
CHART = (
    '<div class="er-chart" role="img" aria-label="Collections velocity trend, last 6 months: '
    'historic baseline versus Entire RCM">'
    '<svg viewBox="0 0 320 90" preserveAspectRatio="none">'
    '<polyline points="0,72 64,66 128,58 192,44 256,30 320,14" fill="none" stroke="#4edea3" stroke-width="3" stroke-linecap="round"/>'
    '<polyline points="0,78 64,76 128,74 192,72 256,70 320,69" fill="none" stroke="rgba(255,255,255,.35)" '
    'stroke-width="2" stroke-dasharray="5 5" stroke-linecap="round"/></svg>'
    '<div class="er-chart__head"><p class="er-chart__title">Collections Velocity Trend (Last 6 Months)</p>'
    '<span class="er-chart__cmp">Entire RCM vs Historic Baseline</span></div></div>')

bento_main = C('er-bento__main', [
    P('<span class="er-eyebrow er-eyebrow--onPrimary">Live BI Portal</span>'),
    H('h3', 'Real-Time Revenue Analytics &amp; KPI Executive Dashboard', 'er-h3'),
    P('<p class="er-p-sm er-mt-sm">Granular oversight into gross collections, payer denial velocities, and aging '
      'accounts without waiting for month-end reports.</p>'),
    C('er-bento__grid2', [C('er-tile', [P(f'<p class="er-tile__l">{l}</p><p class="er-tile__v">{v}</p>'
                                         f'<p class="er-tile__d">{d}</p>')]) for l, v, d in tiles],
      extra={"flex_direction": "row", "flex_wrap": "wrap"}),
    HTML(CHART, 'er-chart-wrap'),
])

tech_feats = [
    ("assignment_turned_in", "Prior Authorization Engine",
     "Prevent surgery cancellations and claim rejections. Our team automates prior authorizations across commercial and government payers with automated clinical record packaging.",
     "Approval Turnaround", "&lt; 24 Hours"),
    ("find_in_page", "Underpayment &amp; Contract Audit",
     "Payers quietly underpay contracted fee schedules. Our algorithm compares every remittance advice against your negotiated payer contracts to recapture silent underpayments.",
     "Contract Variance Recapture", "+$3.2K / Provider / Mo"),
    ("medical_services", "35+ Clinical Subspecialty Rules",
     "Deep clinical logic calibrated for orthopedic implants, bilateral cardiology cath procedures, behavioral billing limits, pathology units, and ASC facility fee carve-outs.",
     "Preconfigured Rulesets", "1,200,000+ Edits"),
    ("devices_wearables", "Telehealth &amp; RPM / CCM Billing",
     "Capture lucrative Remote Patient Monitoring (99453, 99454, 99457) and Chronic Care Management revenues compliant with multi-state telemedicine regulations and CMS time thresholds.",
     "RPM Time Compliance Audit", "100% Audit-Proof"),
]
bento_sec = C('er-sec er-bg-surface', [C('er-wrap', [
    C('er-maxw-2xl', [
        P('<span class="er-eyebrow">Enterprise Infrastructure</span>'),
        H('h2', 'Sophisticated Revenue Intelligence Built for High-Volume Practices', 'er-h2'),
        P('<p class="er-p er-mt-sm">From multi-state prior authorizations to deep fee schedule contract audits, '
          'explore our full technology footprint.</p>'),
    ], extra={"flex_direction": "column", "align_items": "flex-start"}),
    C('er-bento', [bento_main] + [C('er-feat', [
        P(f'<div class="er-feat__icon"><span class="er-ico er-ico--md">{ic}</span></div>'),
        H('h3', title, 'er-h4'),
        P(f'<p class="er-p-sm er-mt-sm">{body}</p>'),
        P(f'<p class="er-feat__metric">{ml}: {mv}</p>'),
    ]) for ic, title, body, ml, mv in tech_feats],
      extra={"flex_direction": "row", "flex_wrap": "wrap"}),
])], eid="technology")

# ---- 6. CALCULATOR -------------------------------------------------------
CALC = '''
<div class="er-calc__controls">
  <div class="er-slider">
    <div class="er-slider__row"><span class="er-label-sm er-on-dark">Monthly Billed Charges:</span>
      <span class="er-slider__val" id="calc-volume-text">$350,000</span></div>
    <input type="range" id="calc-volume-slider" min="50000" max="2000000" step="10000" value="350000"
      aria-label="Monthly billed charges">
    <div class="er-range-row"><span>$50k</span><span>$1M</span><span>$2M+</span></div>
  </div>
  <div class="er-slider">
    <div class="er-slider__row"><span class="er-label-sm er-on-dark">Current Estimated Denial Rate:</span>
      <span class="er-slider__val" id="calc-denial-text">18%</span></div>
    <input type="range" id="calc-denial-slider" min="5" max="35" step="1" value="18" aria-label="Current estimated denial rate">
    <div class="er-range-row"><span>5% (Optimized)</span><span>18% (Avg US)</span><span>35% (Severe)</span></div>
  </div>
  <div class="er-slider">
    <div class="er-slider__row"><span class="er-label-sm er-on-dark">Average Days in A/R:</span>
      <span class="er-slider__val" id="calc-ar-text">48 Days</span></div>
    <input type="range" id="calc-ar-slider" min="20" max="90" step="1" value="48" aria-label="Average days in A/R">
    <div class="er-range-row"><span>20 Days</span><span>45 Days (National Avg)</span><span>90 Days</span></div>
  </div>
</div>
<div class="er-calc__out">
  <p class="er-tile__l">Recoverable Annual Cash</p>
  <p class="er-tile__v" id="calc-recovered-annual">$71,820</p>
  <p class="er-tile__d" id="calc-ar-reduction">34 Days Faster</p>
  <div class="er-calc__3yr">
    <p class="er-tile__l">Estimated 3-Year Practice Impact:</p>
    <p class="er-tile__v" id="calc-three-year">+$215,460</p>
  </div>
</div>'''

calc_sec = C('er-sec er-bg-primary', [C('er-wrap', [
    C('er-calc__grid', [
        C('er-calc__left', [
            P('<span class="er-eyebrow er-eyebrow--onPrimary">Practice Yield Calculator</span>'),
            H('h2', 'See Exactly How Much Revenue You Are Leaving on the Table', 'er-h2'),
            P('<p class="er-p er-on-dark er-mt-sm">Adjust the sliders to match your clinic\'s billing parameters. Our '
              'calculator computes recoverable write-offs, accelerated cash flow, and overall net practice yield.</p>'),
            P('<ul class="er-calc__checks">'
              '<li><span class="er-ico er-ico--md er-ico--ter">check</span> Real-time calculations based on 2024&ndash;2025 HFMA benchmark data</li>'
              '<li><span class="er-ico er-ico--md er-ico--ter">check</span> Factor in staff overhead savings &amp; eliminated billing software costs</li>'
              '<li><span class="er-ico er-ico--md er-ico--ter">check</span> Immediate itemized breakdown available for board review</li></ul>'),
            C('er-hero__cta', [BTN('Lock In Guarantee', '#schedule-audit', 'er-btn er-btn--secondary')],
              extra={"flex_direction": "row"}),
        ]),
        HTML(CALC, 'er-calc__right'),
    ], extra={"flex_direction": "row", "flex_wrap": "wrap"}),
])], eid="interactive-calculator-section")

# ---- 7. SECURITY ---------------------------------------------------------
seccards = [("lock", "256-Bit TLS 1.3", "Encrypted clearinghouse file transfer protocols (SFTP / EDI 837 &amp; 835)."),
            ("policy", "HIPAA HITECH Audited", "Full adherence to Protected Health Information (PHI) physical and digital safeguards."),
            ("security", "SOC-2 Type II Certified", "Independently certified data center infrastructure and operational segregation."),
            ("map", "All 50 US States MACs", "Direct EDI integration across Noridian, Novitas, Palmetto, NGS, WPS, and state Medicaid.")]
payers = [("Medicare Part A/B &amp; Railroad Medicare", "Noridian, Novitas, Palmetto, NGS, WPS"),
          ("National Commercial Payers", "BCBS (All States), UHC, Aetna, Cigna, Humana"),
          ("State Medicaid &amp; Managed Medicaid (MCO)", "Centene, Molina, CareSource, WellCare"),
          ("Workers' Comp &amp; No-Fault Auto PIP", "State-specific fee schedule compliance")]

payer_tbl = C('er-payers', [
    P('<div class="er-payers__head"><span>50-State Payer Interoperability Directory</span>'
      '<span class="er-payers__badge">Active Clearinghouse Links</span></div>'),
] + [P(f'<div class="er-payers__row"><strong>{a}</strong><span>{b}</span></div>') for a, b in payers] + [
    P('<div class="er-payers__foot"><strong>EDI Transaction Standards:</strong> ANSI X12 837P, 837I, 835, '
      '270/271, 276/277</div>'),
], extra={"flex_direction": "column"})

security = C('er-sec er-bg-low', [C('er-wrap', [
    C('er-security__head', [
        C('er-maxw-2xl', [
            P('<span class="er-eyebrow">Institutional Security &amp; Jurisdiction</span>'),
            H('h2', 'Bank-Grade Financial Security &amp; Full 50-State Payer Coverage', 'er-h2'),
            P('<p class="er-p er-mt-md">Entire RCM operates with the rigorous compliance posture mandated by major '
              'healthcare networks. We execute comprehensive Business Associate Agreements (BAA) with every medical '
              'client before onboarding.</p>'),
        ], extra={"flex_direction": "column", "align_items": "flex-start"}),
    ], extra={"flex_direction": "row", "flex_wrap": "wrap"}),
    C('er-security__cards', [C('er-seccard', [
        P(f'<div class="er-pillar__icon"><span class="er-ico er-ico--lg">{ic}</span></div>'),
        H('h3', t, 'er-h4'),
        P(f'<p class="er-p-sm er-mt-sm">{d}</p>'),
    ]) for ic, t, d in seccards], extra={"flex_direction": "row", "flex_wrap": "wrap"}),
    payer_tbl,
])])

# ---- 8. CASE STUDIES -----------------------------------------------------
cases = [
    ("cardiology", "Cardiology &bull; Atlanta, GA", "8 Providers", "Multi-Provider Cardiology Specialists",
     "Struggled with complex modifier coding on coronary angiograms and 52-day AR backlogs resulting in $300k+ trapped in payer audits.",
     ("Collections Lift", "+24.8%", "AR Velocity", "52d &rarr; 21d"), "MK", "Dr. Marcus Vance, MD", "Managing Partner"),
    ("orthopedics", "Orthopedics &amp; ASC &bull; Dallas, TX", "Surgery Center", "Lone Star Orthopedic &amp; Spine Center",
     "Implant hardware carve-outs were systematically denied by commercial plans. Previous billing company wrote them off as uncollectible.",
     ("Aging Claims Recaptured", "$420,000", "Final Denial Rate", "1.8%"), "RH", "Rachel Hayes, FACMPE", "Executive Practice Administrator"),
    ("primary", "Primary Care &bull; Chicago, IL", "12 Clinic Network", "Midwest Family Health Partners",
     "Suffered from 32% staff turnover in the in-house billing department, unbilled copays, and severe coding backlogs across 12 locations.",
     ("Overhead Elimination", "$110,000 / yr", "First Pass Clean", "99.6%"), "JL", "Dr. Julian Lee, MD", "Chief Medical Officer"),
]
case_cards = [C(f'er-case er-case--{key}', [
    P(f'<div class="er-case__meta">{meta}<span class="er-case__badge">{badge}</span></div>'),
    H('h3', title, 'er-h3'),
    P(f'<p class="er-p-sm er-mt-sm">{body}</p>'),
    C('er-case__metrics', [C('er-case__m', [P(f'<p class="er-case__v">{m[1]}</p>'), P(f'<p class="er-case__l">{m[0]}</p>')]),
                           C('er-case__m', [P(f'<p class="er-case__v">{m[3]}</p>'), P(f'<p class="er-case__l">{m[2]}</p>')])],
      extra={"flex_direction": "row"}),
    C('er-case__who', [P(f'<div class="er-case__avatar">{ini}</div>'),
                       C('er-case__who-txt', [P(f'<p class="er-plist__t">{name}</p>'), P(f'<p class="er-case__l">{role}</p>')])],
      extra={"flex_direction": "row", "align_items": "center"}),
]) for key, meta, badge, title, body, m, ini, name, role in cases]

nav_tabs = C('er-cases__tabs', [
    BTN('All Specialties', '#cs-all', 'er-tab is-active'),
    BTN('Cardiology', '#cs-cardiology', 'er-tab'),
    BTN('Orthopedics', '#cs-orthopedics', 'er-tab'),
    BTN('Primary Care', '#cs-primary', 'er-tab'),
], extra={"flex_direction": "row", "flex_wrap": "wrap"})

quote = C('er-quote', [
    P('<div class="er-stars">' + '<span class="er-ico er-ico--md">star</span>' * 5 +
      '<span class="er-label-sm er-ml">5.0 Star Practice Rating</span></div>', 'er-stars-wrap'),
    P('<p class="er-quote__text">&ldquo;Switching our multi-site clinic to Entire RCM took exactly 12 days. Our first '
      'month\'s collections surged by $64,000 without a single disrupted patient visit. Their appeal team recovers '
      'dollars our old billing staff labeled as write-offs.&rdquo;</p>'),
    C('er-case__who', [P('<div class="er-case__avatar">AC</div>'),
                       C('er-case__who-txt', [P('<p class="er-plist__t">Dr. Allison Cooper, MD</p>'),
                                              P('<p class="er-case__l">Medical Director, Premier Neurological &amp; Pain Institute</p>')])],
      extra={"flex_direction": "row", "align_items": "center"}),
])

cases_sec = C('er-sec er-bg-surface', [C('er-wrap', [
    C('er-maxw-2xl', [
        P('<span class="er-eyebrow">Verifiable Practice Transformations</span>'),
        H('h2', 'Documented Client Case Studies', 'er-h2'),
        P('<p class="er-p er-mt-sm">Real balance sheet outcomes achieved within 90 days of Entire RCM implementation.</p>'),
    ], extra={"flex_direction": "column", "align_items": "flex-start"}),
    nav_tabs,
    C('er-grid er-grid--3', case_cards, extra={"flex_direction": "row", "flex_wrap": "wrap"}),
    quote,
])], eid="case-studies")

# ---- 9. PRICING ----------------------------------------------------------
def plan(tier, name, providers, blurb, rate, unit, note, feats, cta, cta_cls, featured=False):
    cls = 'er-price__card' + (' er-price__card--featured' if featured else '')
    kids = []
    if featured:
        kids.append(P('<div class="er-price__ribbon">Most Popular &bull; Maximum Yield</div>'))
    kids += [
        P(f'<p class="er-price__tier">{tier}</p>'),
        H('h3', name, 'er-h3--lg'),
        P(f'<p class="er-label-sm er-mt-sm">{providers}</p>'),
        P(f'<p class="er-p-sm er-mt-sm">{blurb}</p>'),
        P(f'<p class="er-price__v er-mt-md" data-rate="{rate}">{rate}</p><p class="er-price__unit">{unit}</p>'
          f'<span class="er-price__note">{note}</span>'),
        P('<ul class="er-price__feats">' + ''.join(
            f'<li><span class="er-ico er-ico--sm">check</span> {f}</li>' for f in feats) + '</ul>'),
        BTN(cta, '#schedule-audit', cta_cls),
    ]
    return C(cls, kids)

plans = C('er-price__plane', [
    plan("Essential", "Standard Collections", "1-3 Providers",
         "Complete core medical billing and claim processing for solo clinics and private practices.",
         "4.2%", "of net collections", "Zero software charges",
         ["Full Cycle Billing &amp; 3-Tier Scrubbing", "Payment Posting &amp; ERA Reconciliation",
          "Primary &amp; Secondary Payer Submissions", "Standard CARC/RARC Denial Resolution",
          "Monthly Financial Executive Summary"],
         "Get Started with Standard", "er-btn er-btn--light er-btn--block"),
    plan("Comprehensive", "Growth Accelerator", "4-15 Providers",
         "Complete end-to-end RCM plus prior authorizations, aggressive 48-hr appeals, and digital patient billing.",
         "4.8%", "of net collections", "Includes Dedicated Account Director",
         ["Everything in Standard Collections", "Prior Authorization Verification Engine",
          "Aggressive 48-Hour Clinical Denial Appeals", "Digital Patient Statements &amp; SMS Text-to-Pay",
          "US-Based Inbound Patient Billing Support", "Bi-Weekly Revenue Diagnostic Reviews"],
         "Choose Growth Accelerator", "er-btn er-btn--secondary er-btn--block", featured=True),
    plan("Custom Scale", "Enterprise &amp; ASC", "15+ Providers / Hospitals",
         "Engineered for surgical centers, hospital networks, and national healthcare organizations.",
         "Custom %", "or Hybrid Model", "Dedicated Onshore Pods",
         ["All Growth Accelerator Capabilities", "Custom Direct EHR Integration &amp; HL7 Bridges",
          "Facility Fee &amp; Implant Carve-Out Specialists", "Custom Snowflake / PowerBI Analytics Feed",
          "SLA-Backed 14-Day AR Velocity Guarantee"],
         "Request Enterprise Scope", "er-btn er-btn--light er-btn--block"),
], extra={"flex_direction": "row", "flex_wrap": "wrap"})

pricing = C('er-sec er-bg-lowest', [C('er-wrap', [
    C('er-center er-maxw-3xl er-mx', [
        P('<span class="er-eyebrow">Transparent &amp; Incentive-Aligned</span>'),
        H('h2', 'We Only Get Paid When You Get Paid', 'er-h2'),
        P('<p class="er-p er-mt-sm">Zero hidden onboarding fees. Zero billing software licensing charges. Pure '
          'percentage-of-collections pricing.</p>'),
        C('er-price__toggle', [
            BTN('Solo &amp; Small Practice (&lt;$100k/mo)', '#scale-standard', 'er-tab is-active'),
            BTN('High-Volume / Enterprise ($100k - $2M+)', '#scale-growth', 'er-tab'),
        ], extra={"flex_direction": "row", "flex_wrap": "wrap"}),
    ], extra={"flex_direction": "column", "align_items": "center"}),
    plans,
])], eid="pricing-section")

# ---- 10. GUARANTEES ------------------------------------------------------
guars = [("verified_user", "30-Day Zero-Risk Trial",
          "Experience our full coding, scrubbing, and denial appeals pipeline for 30 days without long-term contractual lock-in. Cancel anytime if we do not outperform your expectations."),
         ("trending_up", "Guaranteed 10%+ Revenue Lift",
          "If Entire RCM does not increase your net collections by at least 10% within 90 days of onboarding, you may terminate immediately with zero penalty."),
         ("sync_saved_locally", "Zero Setup or Migration Fee",
          "Our specialized healthcare IT engineers handle 100% of the EHR mapping, EDI enrollment, and clearinghouse bridge setup without charging a dime in setup costs.")]

guar_sec = C('er-sec er-bg-low', [C('er-wrap', [
    C('er-grid er-grid--3', [C('er-guar', [
        P(f'<div class="er-guar__icon"><span class="er-ico er-ico--lg">{ic}</span></div>'),
        C('er-guar__txt', [H('h3', t, 'er-h3'), P(f'<p class="er-p-sm er-mt-sm">{d}</p>')]),
    ], extra={"flex_direction": "row"}) for ic, t, d in guars],
      extra={"flex_direction": "row", "flex_wrap": "wrap"}),
])])

# ---- 11. FAQ -------------------------------------------------------------
faqs = [
    ("How quickly can Entire RCM integrate with our existing EHR / Billing software?",
     "Full onboarding typically completes within 7 to 14 business days. We connect directly into your current EHR (Epic, athenahealth, eClinicalWorks, Kareo, NextGen, etc.) via secure credentials or HL7/API connections. Your front-office workflow does not change, and patient care continues uninterrupted."),
    ("How do you handle claims denials and payer appeals?",
     "We operate a strict 48-hour denial turnaround protocol. As soon as an ERA/835 remittance displays an adverse adjustment, our certified coding team analyzes the CARC/RARC codes, pulls required operative notes or prior-authorizations from your chart, and submits a substantiated medical necessity appeal. We win 99.1% of eligible appeals."),
    ("What clinical specialties do you support?",
     "We support over 35 distinct clinical specialties. Our strongest domain volumes are in Cardiology, Orthopedic Surgery, Ambulatory Surgery Centers (ASCs), Behavioral Health, Internal Medicine, Neurology, Pain Management, and Pathology. We pair each practice with billers certified specifically in their discipline."),
    ("Will my practice lose visibility or control over our financial data?",
     "No. In fact, you gain unprecedented transparency. You retain master admin access to your EHR. Additionally, Entire RCM gives practice principals 24/7 access to our cloud Business Intelligence dashboard, displaying real-time claims velocity, collections by provider, aging buckets, and payer payment speeds down to the dollar."),
    ("How are patient inquiries and patient balances handled?",
     "We provide clean, easy-to-understand electronic and mailed patient statements equipped with QR codes and text-to-pay links. We staff a dedicated, courteous, US-based patient financial support line that handles patient billing inquiries calmly, protecting your clinic's patient satisfaction scores."),
    ("What makes Entire RCM distinct from traditional medical billing companies?",
     "Traditional billers simply enter codes and passively wait for payments, writing off difficult denials. Entire RCM merges proprietary pre-submission AI rule engines with master AAPC coders to prevent denials upfront, and fights every legitimate underpayment down to the penny. We treat your practice balance sheet like our own."),
]
faq_sec = C('er-sec er-bg-surface', [C('er-wrap er-wrap--tight', [
    C('er-center', [
        P('<span class="er-eyebrow">Frequently Answered Questions</span>'),
        H('h2', 'Clear Answers for Healthcare Executives', 'er-h2'),
        P('<p class="er-p er-mt-sm">Everything you need to know about switching your medical revenue cycle '
          'management to Entire RCM.</p>'),
    ], extra={"flex_direction": "column", "align_items": "center"}),
    ACCORDION(faqs, 'er-faq'),
])], eid="faq")

# ---- 12. FINAL CTA / AUDIT FORM -----------------------------------------
cta = C('er-sec er-bg-low', [C('er-wrap er-wrap--narrow', [
    C('er-cta__card', [
        C('er-cta__grid', [
            C('er-cta__copy', [
                P('<span class="er-eyebrow">$2,500 Value &bull; 100% Complimentary</span>'),
                H('h2', 'Claim Your 30-Day Revenue Cycle Health Audit', 'er-h2'),
                P('<p class="er-p er-mt-md">Our Senior RCM Directors will review your last 90 days of 835 ERA files '
                  'to detect lost revenue, systemic coding mismatches, and recoverable uncollected balances.</p>'),
                P(list_html([
                    {"t": "No obligation, no contract", "d": "A written diagnostic report delivered within 48 hours."},
                    {"t": "BAA signed before any data moves", "d": "HIPAA-compliant secure transfer of your 835 ERA files."},
                    {"t": "Reviewed by a senior RCM director", "d": "Not a sales rep &mdash; an operator who has run billing floors."},
                ], ico='check_circle', icls='er-ico er-ico--md er-ico--good'), 'er-plist-wrap er-mt-lg'),
            ]),
            C('er-cta__form', [
                SHORTCODE('[contact-form-7 id="{{CF7_AUDIT}}" title="30-Day Revenue Cycle Health Audit"]', 'er-audit-form'),
            ]),
        ], extra={"flex_direction": "row", "flex_wrap": "wrap"}),
    ]),
])], eid="schedule-audit")

# ---- 13. FOOTER ----------------------------------------------------------
foot_cta = C('er-footer__cta', [
    C('er-footer__cta-copy', [
        P('<span class="er-eyebrow er-eyebrow--onPrimary">Institutional Yield Guaranteed</span>'),
        H('h2', 'Ready to eliminate 98% of claim denials and unlock 15-25% higher collections?', 'er-h3--lg'),
        P('<p class="er-p-sm er-on-dark er-mt-sm">Deploy proprietary algorithmic scrubbing, real-time clearinghouse '
          'diagnostics, and automated appeal workflows in under 48 hours.</p>'),
    ], extra={"flex_direction": "column", "align_items": "flex-start"}),
    SHORTCODE('[contact-form-7 id="{{CF7_FOOTER}}" title="Footer Book Audit"]', 'er-footer__form'),
])

FOOT_COLS = [
    ("Solutions", ["Denial Management", "Prior Authorization", "Coding & Auditing", "A/R Recovery Engine", "Patient Financial Care"]),
    ("Specialties", ["Cardiology Systems", "Orthopedic Surgery", "Ambulatory Surgery Centers", "Pathology & Labs", "Behavioral Health"]),
    ("Enterprise &amp; Compliance", ["HIPAA Business Associate", "SOC-2 Type II Attestation", "ISO 27001 Infrastructure", "OIG / CMS Safe Harbors", "Data Encryption Standard"]),
    ("Company", ["About Us", "Client Results", "Revenue Blog", "Contact Executives", "Client Portal"]),
]
foot_cols = [C('er-footer__col', [H('h4', t, 'er-h4'),
                                  P('<ul class="er-footer__links">' + ''.join(
                                      f'<li><a href="#schedule-audit">{x}</a></li>' for x in items) + '</ul>')])
             for t, items in FOOT_COLS]

footer = C('er-footer', [C('er-wrap', [
    foot_cta,
    C('er-footer__grid', [
        C('er-footer__brand', [
            IMG(EHR, 'Entire RCM Revenue Cycle Logo', 'er-brand__img'),
            P('<p>Comprehensive institutional billing architecture, algorithmic claims clearance, and end-to-end '
              'recovery pipelines for leading US medical practices.</p>'),
            P('<span class="er-footer__badge"><span class="er-ico er-ico--sm">verified_user</span> '
              'SOC-2 Type II Certified &bull; HIPAA Compliant</span>'),
        ]),
    ] + foot_cols, extra={"flex_direction": "row", "flex_wrap": "wrap"}),
    C('er-footer__bottom', [
        P('<span>&copy; 2025 Entire RCM Inc. All rights reserved. Registered Healthcare BPO.</span>'),
        C('er-footer__legal', [
            P('<a href="#privacy-policy">Privacy Policy</a> &bull; <a href="#terms-of-service">Terms of Service</a>'),
            P('<span class="er-footer__badge"><span class="er-ico er-ico--sm">lock</span> End-to-End TLS 1.3 256-Bit Financial Encryption</span>'),
        ], extra={"flex_direction": "row", "flex_wrap": "wrap"}),
    ], extra={"flex_direction": "row", "justify_content": "space-between", "align_items": "center"}),
])])

PARTS.append(problem)
PARTS.insert(1, trust)
PARTS.insert(1, hero)
PARTS.append(pillars_sec)
PARTS.append(bento_sec)
PARTS.append(calc_sec)
PARTS.append(security)
PARTS.append(cases_sec)
PARTS.append(pricing)
PARTS.append(guar_sec)
PARTS.append(faq_sec)
PARTS.append(cta)
PARTS.append(footer)

out = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'page.json')
json.dump(PARTS, open(out, 'w', encoding='utf-8'), ensure_ascii=False)
n_w = sum(1 for p in PARTS for _ in json.dumps(p).split('"elType": "widget"')) - len(PARTS)
print("containers/widgets written ->", out)
print("top-level:", len(PARTS))
