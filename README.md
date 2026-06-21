# Enterns Tech — WordPress Theme

The Enterns Tech IT training & placement website, packaged as a WordPress theme
and ready to deploy to **Bluehost** at **www.enternstech.com** via **GitHub**.

The full site is a self-contained "Design Canvas" bundle (`enternstech/static/index.html`)
— all fonts, images, scripts, and animations are embedded. The theme serves that
bundle as the front page, and adds a **PayPal** payment backend.

---

## Repository layout

```
/
├── enternstech/                      ← the WordPress theme (this is what gets deployed)
│   ├── static/index.html             ← the complete bundled site
│   ├── assets/enterns-logo-motion.png
│   ├── source/                       ← editable design source (.dc.html + support.js)
│   ├── style.css                     ← theme header + fallback styles
│   ├── functions.php                 ← theme setup + PayPal REST endpoints
│   ├── front-page.php                ← serves the bundle, injects PayPal config
│   ├── header.php  footer.php  index.php  page.php  single.php  404.php
│   └── readme.txt
├── .github/workflows/deploy.yml      ← auto-deploy to Bluehost on push to main
├── wp-config-paypal-snippet.txt      ← PayPal keys to paste into wp-config.php
├── .gitignore
├── AGENTS.md
└── README.md
```

---

## Run / preview locally (VS Code)

You don't need PHP to preview the visual site — the bundle is plain HTML:

```bash
# from the repo root
python3 -m http.server 8080 --directory enternstech/static
# then open http://localhost:8080
```

To test the WordPress + PayPal parts, run a local WordPress (e.g. **Local by Flywheel**
or `wp-env`), symlink/copy the `enternstech/` folder into `wp-content/themes/`, and
activate the theme.

---

## Deploy to Bluehost via GitHub

### 1. Push this folder to GitHub
```bash
cd <this folder>
git init
git add .
git commit -m "Enterns Tech theme v2"
git branch -M main
git remote add origin https://github.com/<you>/enternstech-site.git
git push -u origin main
```

### 2. Add your Bluehost FTP secrets to the repo
GitHub → your repo → **Settings → Secrets and variables → Actions** → add:

| Secret | Where to find it |
|---|---|
| `FTP_SERVER` | Bluehost → **Files → FTP Accounts** (e.g. `ftp.enternstech.com`) |
| `FTP_USERNAME` | your FTP account username |
| `FTP_PASSWORD` | your FTP account password |

### 3. Push to deploy
Every push to `main` runs `.github/workflows/deploy.yml`, which uploads the
`enternstech/` folder to `public_html/wp-content/themes/enternstech/` on Bluehost.
(If your WordPress install is in a subfolder, edit `server-dir` in that file.)

### 4. Activate + set the front page
In WordPress admin:
1. **Appearance → Themes → EnternsTech → Activate**
2. Create an empty page (e.g. "Home").
3. **Settings → Reading → Your homepage displays → A static page → Homepage: Home.**

The bundled site now serves at `https://www.enternstech.com`.

---

## Wire up PayPal (real payments)

The payment screen in the bundle is a UI prototype. To take real money:

1. Get REST API credentials at <https://developer.paypal.com/dashboard/> →
   **Apps & Credentials** (create an app; note the **Client ID** and **Secret**;
   use Sandbox first, then Live).
2. On Bluehost, open `public_html/wp-config.php` and paste the block from
   `wp-config-paypal-snippet.txt` above `/* That's all, stop editing! */`.
   Set `ENTERNSTECH_PAYPAL_ENV` to `sandbox` for testing, `live` when ready.
3. The theme then exposes:
   - `POST /wp-json/enternstech/v1/paypal/create`  → `{ "plan": "...", "amount": 0 }`
   - `POST /wp-json/enternstech/v1/paypal/capture` → `{ "orderID": "..." }`
   and injects `window.ENTERNSTECH_PAYPAL` (clientId, env, createUrl, captureUrl)
   plus the PayPal JS SDK into the page `<head>`.
4. Hook the bundle's payment button to those endpoints. Because the bundle is a
   single compiled file, the cleanest path is to edit the **design source**
   (`enternstech/source/Enterns Tech IT.dc.html`), search for `payAmount` / `payPlan`,
   and call `window.ENTERNSTECH_PAYPAL.createUrl`, then rebuild the bundle.

**Why server-side?** Your PayPal *secret* must never live in browser code. The
REST endpoints in `functions.php` keep it on the server (read from `wp-config.php`).

---

## Other production to-dos (carried over from the prototype)

- **FormSubmit** — contact/partner forms post to FormSubmit (`info@enternstech.com`).
  Submit one form after going live and click the confirmation email to activate.
- **Admin login** — prototype password `enterns2026` is hard-coded in the bundle.
  Replace before relying on it for anything real.
- **Data storage** — plans, combos, comparison table, and form leads are stored in
  the browser (`localStorage`). Move to a real backend if you need them to persist
  across devices.

---

© 2026 Enterns Tech — www.enternstech.com
