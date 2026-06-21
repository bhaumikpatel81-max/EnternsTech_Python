# AGENTS.md — Enterns Tech WordPress Theme

## Architecture overview

A **WordPress theme** whose front page serves a self-contained **Design Canvas (DC)**
bundle (`enternstech/static/index.html`). There is no build pipeline for the bundle at
runtime; WordPress serves the compiled HTML as-is via `front-page.php`. Hosting is
**Bluehost**; deploys run over FTP via GitHub Actions (`.github/workflows/deploy.yml`).

### How the bundled runtime works

`static/index.html` ships a proprietary DC reactive micro-framework. It contains:

1. `<script type="__bundler/manifest">` — JSON map of asset UUIDs → base64, gzip-compressed blobs.
2. `<script type="__bundler/template">` — the compiled component template with placeholder tokens.
3. A bootstrap script that uses `DecompressionStream` to unpack blobs into Blob URLs,
   replaces tokens, and mounts the component.

The design-time authoring format is `source/Enterns Tech IT.dc.html` (uses `<x-dc>` and
`support.js`). The bundler converts it into the self-contained `static/index.html`.

### Reactive system tags (design source only)

| Tag | Purpose |
|---|---|
| `<sc-if value="{{ expr }}">` | Conditional render |
| `<sc-for list="{{ expr }}" as="item">` | List render |
| `{{ expr }}` | Text interpolation |
| `style-hover` / `style-focus` | State style bindings |
| `data-reveal` | Scroll-triggered reveal |
| `data-count data-target="N"` | Animated counter |
| `data-tilt3d` / `data-magnetic` / `data-depth="N"` | Pointer effects / parallax |

### State shape (design source)

`loading`, `solid` (navbar on scroll), `route` (`home`/`pricing`/`payment`), `audience`
(`null`/`intl`/`dom`), `plans`, `combos`, `comparison`, `submissions`, `adminAuth`,
`payPlan`, `payAmount`.

### Data persistence (prototype)

`localStorage` keys: `et_store_v1` (plans/combos), `et_comparison_v1` (comparison table),
`et_subs_v1` (form submissions). Migrate to a real backend for production.

## PayPal

`functions.php` registers two REST endpoints:
`POST /wp-json/enternstech/v1/paypal/create` and `.../paypal/capture`. Credentials are
read from `wp-config.php` constants (`ENTERNSTECH_PAYPAL_ENV/CLIENT/SECRET`) so the
secret never reaches the browser. `front-page.php` injects `window.ENTERNSTECH_PAYPAL`
and the PayPal JS SDK into `<head>` (because `readfile` bypasses `wp_head`). To finish
wiring, edit the design source payment button (`payAmount`/`payPlan`) to call the
endpoints, then rebuild the bundle.

## Coding conventions

- All site logic lives in the bundle; edit the **design source** then rebuild, rather
  than hand-editing the compiled `static/index.html`.
- Quick content tweaks: search the string in `static/index.html` (e.g. `info@enternstech.com`).
- Color system: accent `#22D3EE` (cyan), secondary `#3BA4FF`, bg `#05080F`,
  surface `#0C1426`, text `#ECF2FF`, muted `#6B7280`.

## Forms

Contact/partner forms post to **FormSubmit** (`info@enternstech.com`). Activate by
submitting one form after deploy and clicking the confirmation email.

## Deployment

- GitHub Actions (`deploy.yml`) uploads `enternstech/` to
  `public_html/wp-content/themes/enternstech/` on Bluehost on every push to `main`.
- Required repo secrets: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`.
- Set the theme's front page under **Settings → Reading**.
