# Entire RCM — WordPress site bootstrap

Source of truth for the **Entire RCM** marketing site (medical billing / RCM).
The live site runs WordPress + Elementor on Hostinger; this repository is
deployed straight into `wp-content/mu-plugins/`, so everything the site needs is
versioned here.

## What is in here

| Path | Purpose |
| --- | --- |
| `entire-rcm-boot.php` | Must-use plugin entry point. Enqueues the fonts, the design system (`design.css`) and the interaction layer (`theme.js`). Deliberately does **not** load the installer, so provisioning code can never take the front end down. |
| `entire-rcm/inc/installer.php` | Idempotent provisioner: media, the three Contact Form 7 forms, the Elementor landing page, legal pages and site options. |
| `entire-rcm/payload/css/design.css` | The whole "Clinical Precision" design system — tokens, layout, typography, components and the Elementor reconciliation layer. Fluid `clamp()` type and spacing throughout. |
| `entire-rcm/payload/js/theme.js` | Drawer, ROI calculator, quick-yield estimate, case-study filter, pricing scale toggle, smooth scroll and CF7 confirmation. |
| `entire-rcm/payload/elementor/page.json` | The landing page as an Elementor document (native widgets — headings, text, buttons, images, accordion, shortcodes). |
| `entire-rcm/payload/img/` | The supplied company logo. |
| `tools/build_page.py` | Generator that produced `page.json`. Edit this, not the JSON, when restructuring the page. |

## Editing the site

Everything a marketer needs to change is editable in the WordPress UI:

* **Page copy, headings, buttons, images, FAQ** — Elementor editor on the *Home* page (Pages → Home → Edit with Elementor).
* **Forms and recipients** — Contact Form 7 (*Contact → Contact Forms*). Submissions are archived by Flamingo under *Contact → Flamingo*.
* **Global styling** — `entire-rcm/payload/css/design.css`, or Appearance → Customize → Additional CSS for one-off overrides (that sheet loads last and wins).

## Provisioning

Deploy this repository into `wp-content/mu-plugins` on the Hostinger site, then
visit `/wp-admin/?er_rcm_build=1` as an administrator. The installer is
idempotent: it re-uses the existing page, forms and attachment on re-runs.

Diagnostics:

* `/wp-admin/?er_rcm_log=1` — JSON log of the last run.
* `/wp-admin/?er_rcm_fatal=1` — last recorded PHP fatal (if any).

Bump `ER_RCM_VERSION` in `entire-rcm-boot.php` to make the installer re-run
automatically on the next admin request.
