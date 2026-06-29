# EnternsTech IT — Full Build Spec

A single-page marketing + enrolment site for a career-placement company ("From learning to employment"). Dark, techy, animated. This document describes **every visual detail, all motion, and the real content data** so it can be rebuilt exactly — plus a **new dark/light theme system** (the only addition vs. the original, which was dark-only).

> Stack-agnostic. Build it as plain HTML/CSS/JS, or React, or whatever Claude Code is using. The original is one self-contained page. Fonts: **Space Grotesk** (headings, numbers, UI accents) + **Inter** (body). Load both from Google Fonts, weights 400/500/600/700.

---

## 0. The one new requirement — Dark / Light theme

The original site is **dark only**. You must add a theme system that:

1. **Auto-picks** dark or light on first load from the device/browser setting via `prefers-color-scheme`.
2. Has a **manual toggle** (sun/moon button) in the nav bar (and ideally mirrored in the mobile nav).
3. **Persists** the user's manual choice in `localStorage` (key e.g. `et_theme`). Order of precedence: saved choice → system preference → default dark.
4. Reacts live if the OS theme changes while no manual choice is stored (listen to `matchMedia('(prefers-color-scheme: dark)').addEventListener('change', …)`).

### Implementation pattern (CSS variables)

Put every color behind a CSS custom property on `:root`, and override them under `[data-theme="light"]`. Set `document.documentElement.dataset.theme = 'dark' | 'light'`.

```css
:root, [data-theme="dark"] {
  --bg:            #05080F;   /* page background */
  --bg-footer:     #06090F;
  --surface:       rgba(255,255,255,.025);  /* card fill */
  --surface-2:     rgba(255,255,255,.012);  /* faint band fill */
  --border:        rgba(255,255,255,.09);
  --border-soft:   rgba(255,255,255,.06);
  --modal-grad-a:  #0C1426;
  --modal-grad-b:  #080D18;

  --text:          #ECF2FF;   /* primary */
  --text-2:        #CDD9EE;   /* secondary */
  --text-3:        #9FB1CE;   /* body muted */
  --text-4:        #8295B5;   /* muted */
  --text-5:        #6B7C9E;   /* faint */
  --label:         #5E708F;   /* eyebrow labels */
  --marquee:       #48597A;
  --dash:          #3C4A63;   /* "—" off marks */

  --accent:        #22D3EE;   /* primary cyan */
  --accent-2:      #3BA4FF;   /* gradient blue end */
  --accent-tint:   #9FE9F5;
  --accent-soft:   #7CC3FF;
  --on-accent:     #05101F;   /* text on cyan buttons */
  --good:          #5BE89A;   /* success green */
  --good-dot:      #25D366;   /* whatsapp green */
  --err:           #FF8A80;

  --glow: rgba(34,211,238,.30);
}

[data-theme="light"] {
  --bg:            #F6F8FC;
  --bg-footer:     #EEF2F8;
  --surface:       rgba(13,30,60,.035);
  --surface-2:     rgba(13,30,60,.02);
  --border:        rgba(13,30,60,.12);
  --border-soft:   rgba(13,30,60,.07);
  --modal-grad-a:  #FFFFFF;
  --modal-grad-b:  #F1F5FB;

  --text:          #0E1726;
  --text-2:        #233148;
  --text-3:        #3C4F6B;
  --text-4:        #5A6B86;
  --text-5:        #7787A1;
  --label:         #8A9AB5;
  --marquee:       #AFBBD0;
  --dash:          #B6C0D2;

  --accent:        #0E9FBD;   /* slightly deepened cyan so it reads on white */
  --accent-2:      #2A7FE0;
  --accent-tint:   #0E7C92;
  --accent-soft:   #2A7FE0;
  --on-accent:     #FFFFFF;
  --good:          #16A34A;
  --good-dot:      #16C95C;
  --err:           #DC2626;

  --glow: rgba(14,159,189,.22);
}
```

Then everywhere the original used a literal color, use the variable. Notes when porting the dark literals:

- `#05080F` → `var(--bg)`, `#06090F` → `var(--bg-footer)`.
- All `#ECF2FF / #CDD9EE / #9FB1CE / #8295B5 / #6B7C9E / #5E708F` → the matching `--text*`/`--label` var.
- `#22D3EE` → `var(--accent)`; the cyan→blue button gradient `linear-gradient(135deg,#22D3EE,#3BA4FF)` → `linear-gradient(135deg,var(--accent),var(--accent-2))`.
- Button text `#05101F` → `var(--on-accent)`.
- `rgba(255,255,255,.0x)` fills/borders → `var(--surface*)` / `var(--border*)` so they invert in light mode (in light they become subtle dark-on-light tints).
- Accent-tinted fills like `rgba(34,211,238,.06/.08/.1)` can stay as-is in both themes (cyan-on-light still works), or wrap in a `--accent-fill` var if you want finer control.
- The dotted/ambient glows (`rgba(34,211,238,.07)` blobs) should be **dimmed further in light mode** (≈.04) so they don't muddy a white background.
- Canvas drawings (hero particle web, recruiter network) read colors as JS literals — see §6; pass theme-aware colors instead of hardcoded `rgba(34,211,238,…)`.

### Toggle button

Place it in the nav, left of the "Book a call" button. 38×38 round/rounded, `var(--surface)` bg, `var(--border)` border, accent icon. Swap a moon glyph (dark active) / sun glyph (light active). On click: flip `data-theme`, save to localStorage, re-draw canvases with new colors.

Boot script (run before paint to avoid flash):

```js
(function(){
  var saved = localStorage.getItem('et_theme');
  var sys = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark':'light';
  document.documentElement.dataset.theme = saved || sys;
})();
```

`::selection` should also use the accent: `background: var(--glow); color: var(--text);`.

---

## 1. Global design tokens

**Fonts**
- Headings / numbers / nav / buttons / stat figures: `'Space Grotesk'`, weights 600–700.
- Body / paragraphs / inputs: `'Inter', system-ui, sans-serif`.
- Headlines use tight tracking: `letter-spacing` between `-1.2px` and `-2.4px` depending on size.

**Type scale (clamp, responsive)**
- H1 hero: `clamp(40px, 6.6vw, 82px)`, line-height 1.02, `letter-spacing:-2.4px`.
- Section H2: `clamp(28px–30px, 4–4.4vw, 46–52px)`, `letter-spacing:-1.2 to -1.4px`.
- H3 card titles: 18–26px.
- Body: `clamp(16px, 2vw, 21px)` hero sub; 14–17px elsewhere.
- Eyebrow labels: 11–12px, `letter-spacing:2–3px`, `text-transform:uppercase`, color `var(--label)` or `var(--accent)`.

**Layout**
- Max content width: `1240px`, side padding `28px`. Narrower bands: 880px (FAQ), 1080px (roadmap), 1020px (payment).
- Section vertical rhythm: ~90–110px top/bottom padding.
- Border radius: pills/buttons 10–12px; cards 14–20px; big feature panels 20–24px.
- Card base: fill `var(--surface)`, `1px solid var(--border)`.

**Global resets**
- `html,body { margin:0; background:var(--bg); }`, `* { box-sizing:border-box; -webkit-font-smoothing:antialiased; }`
- `img { max-width:100%; height:auto; }`, inputs inherit font.
- Page wrapper: `position:relative; color:var(--text); font-family:'Inter'; overflow-x:hidden;`

**Responsive collapses**
- ≤900px: 5-col & 4-col grids → 2 cols.
- ≤760px: most multi-col grids → 1 col; hero floating cards hidden; journey connector line hidden; nav links become horizontally scrollable; pricing grids stack.
- `.et-floatcard` (hero floating widgets) `display:none` below 980px.

---

## 2. Global motion & keyframes

Define these `@keyframes` (the heart of the "motion website" feel):

| Name | Effect |
|---|---|
| `etSpin` | `rotate(0→360deg)` — orbit rings |
| `etFloat` | translateY 0 → −16px → 0, 3–9s ease-in-out infinite — floating cards/blobs |
| `etFloat2` | translateY 0 → +12px → 0 — counter-float |
| `etPulse` | opacity .35→.9 + scale 1→1.06 — loader ring |
| `etDrift` | translate(0,0)→(40px,−30px)→0, 22s — ambient bg blob |
| `etDrift2` | translate(0,0)→(−50px,30px)→0, 26s — second blob |
| `etMarquee` | translateX(0 → −50%) — logo/keyword ticker |
| `etGlow` | opacity .5↔1 1.6s — live status dot |
| `etLoadBar` | scaleX(0→1) from left — loader progress |
| `etScan`, `etDash`, `etRise`, `etBlink` | defined but used sparingly |

**Reveal-on-scroll** — elements with `data-reveal`:
- Initial: `opacity:0; transform:translateY(30px); filter:blur(6px)`.
- Transition: `.9s cubic-bezier(.16,1,.3,1)` on opacity/transform/filter.
- IntersectionObserver (threshold 0.12, rootMargin `0px 0px -7% 0px`): on enter → opacity 1, transform none, filter none, then unobserve.
- `data-delay="N"` sets `transition-delay: N ms` (used to stagger journey steps, logo cards: 0/80/120/160/240/320…).

**Reduced motion**: `@media (prefers-reduced-motion: reduce)` kills all animation/transition durations (`.001ms`) and forces reveal elements visible. Also gate all JS motion (cursor, parallax, canvases) behind a `reduce` check.

---

## 3. Loader (intro)

Full-screen overlay (`position:fixed; inset:0; z-index:200; background:var(--bg)`), centered column, gap 34px:
1. **Logo tile** 108×108, `etFloat 3s infinite`, with the `enterns-logo-motion.png` logo (rounded 26px, cyan glow `box-shadow:0 0 60px var(--glow)`), plus a surrounding ring `inset:-6px; border:1px solid rgba(34,211,238,.4)` running `etPulse 2s`.
2. **Progress bar** 200×2px track (`rgba(255,255,255,.08)`) with a cyan→light-cyan fill running `etLoadBar 1.9s cubic-bezier(.7,0,.3,1) forwards`.
3. **Tagline**: `FROM LEARNING TO EMPLOYMENT` — 11px, letter-spacing 4px, uppercase, `var(--label)`.

Loader dismisses after **2050ms**, then motion systems boot (`boot()` on next frame). Logo asset: `assets/enterns-logo-motion.png`.

---

## 4. Ambient layer (always behind everything)

Four fixed, pointer-events:none layers:
- **Pointer glow** (`z-index:1`): 520×520 radial cyan glow that lerps toward the cursor (`cx += (tx-cx)*.12` each frame). Margin −260 to center on cursor.
- **Custom cursor dot** (`z-index:400`): 14px cyan circle, lerps toward cursor at `.32`. Scales to 2.6× and goes translucent (`rgba(34,211,238,.18)`) when hovering any `a,button,input,select,textarea,[data-magnetic]`. Hidden on coarse pointers (touch).
- **Two drifting blobs** (`z-index:0`): ~48vw radial glows top-left (cyan) and bottom-right (blue `rgba(80,120,255,.07)`), blurred 20px, running `etDrift`/`etDrift2`. Dim these in light theme.

---

## 5. Sections (in document order)

The page has **3 routes** swapped via display:none — `home`, `pricing`, `pay` — plus 2 modals (`partner`, `admin`). Nav stays fixed across all. See §7.

### 5.1 Nav (fixed, all routes)
- Fixed top, `z-index:100`, height 68px, inner max-width 1240px.
- Transparent over the top of the page; once `window.scrollY > 24` it becomes solid: `background:rgba(5,8,15,.82); backdrop-filter:blur(16px); border-bottom:1px solid rgba(255,255,255,.07)`. (Light theme: translucent white equivalent.) Transition `.3s`.
- **Left**: logo tile (38×38, rounded 10px, cyan glow) + wordmark "Enterns Tech" (Space Grotesk 600, 18px). Click → go to home / scroll top.
- **Center links** (`navLinks`): Journey · Tracks · Pricing · Success · Referral. 14px, 500, color `var(--text-3)`, hover `var(--text)`. "Pricing" switches route; others scroll to section.
- **Right**: **[NEW] theme toggle button** then **"Book a call"** CTA — gradient cyan→blue pill, `var(--on-accent)` text, `box-shadow:0 6px 24px var(--glow)`, hover lifts `translateY(-2px)` + stronger shadow. Click → scroll to contact.

### 5.2 Hero (`home`)
- Padding `150px 28px 90px`, max-width 1240px.
- **Background canvas** (`heroCanvasRef`): animated particle web — 46 floating nodes, lines drawn between nodes within 150px, cyan at low alpha. Opacity .9, z-index −1. (See §6.)
- **3D tilt scene**: the whole hero content tilts on mousemove (`perspective(1300px) rotateY(±4deg) rotateX(±4deg)`); child layers with `data-depth` parallax-shift by `depth*26px`.
- **Eyebrow badge**: pill, `1px solid rgba(34,211,238,.28)`, faint cyan fill, with a glowing pulsing dot (`etGlow`): text "Career Transformation Ecosystem · US · Canada · India".
- **H1**: "From learning" / "to **employment.**" — "employment." filled with cyan→light-cyan gradient text (`background-clip:text; color:transparent`). Words individually `data-reveal` with staggered delays (0, 120).
- **Sub** (`data-delay 240`): "We turn skilled graduates into working professionals. Two decades aligning your skills with the roles, recruiters and companies that fit — train, get marketed, get hired."
- **Buttons** (`data-delay 340`): "Explore programs →" (gradient, magnetic) → pricing; "Talk to a mentor" (ghost, `1px border`) → contact.
- **Stat row** (`data-delay 440`), three figures separated by 1px dividers, each with animated count-up (§6):
  - **6,825+** Careers launched
  - **20 yrs** In the US market
  - **7,000+** Career tracks
- **Floating cards** (`.et-floatcard`, hidden <980px, each `etFloat`):
  - Top-right pill: "Recently placed" / "Data Scientist · 11 weeks".
  - Lower pill: "6 months" / "avg. time to offer".
  - **Orbit graphic** (300×300): concentric rings (solid + dashed), orbiting cyan dots via `etSpin` (22s fwd, 34s reverse), with the logo tile in the center (108×108, rounded 26px, double shadow + cyan glow).

### 5.3 Trusted-ecosystem marquee
- Full-width band, top+bottom hairline borders, faint fill.
- Centered eyebrow: "ONE ECOSYSTEM · STUDENTS · RECRUITERS · HIRING PARTNERS".
- Infinite horizontal **marquee** (`etMarquee 32s linear`), list doubled for seamless loop, Space Grotesk 600 22px in `var(--marquee)`:
  `Recruiter Network · Hiring Partners · Live Use-Cases · Dedicated Recruiters · US · Canada · India · Resume Marketing · OPT / CPT Friendly · Mock Interviews · Automation Tools · 6,825+ Placed`

### 5.4 Career Journey ("One path. Five transformations.")
- Eyebrow "THE JOURNEY"; H2 "One path. Five transformations."; sub "Every candidate moves through the same proven arc — from first skill to signed offer."
- 5-column grid with a horizontal connector line behind (gradient cyan, hidden on mobile). Each step `data-reveal` staggered (delays 0/90/180/270/360):
  - **01 Learn** — Skill assessment, recorded + live technical training mapped to your target role.
  - **02 Build** — Hackathon-style live use-cases and a portfolio of real projects.
  - **03 Market** — US-standard resume crafted and promoted to recruiters daily.
  - **04 Interview** — One-to-one coaching, mock interviews and real-time prep.
  - **05 Get hired** — Offer, onboarding documentation and a confident strong start.
- Each: 68×68 rounded-18 number tile (cyan gradient fill, cyan border, cyan glow), title (Space Grotesk 600 18px), desc.

### 5.5 Technology Tracks ("7,000+ career tracks. One built for you.")
- Eyebrow "TECHNOLOGY TRACKS"; sub "From AI to enterprise apps — we train and place across the roles the US market is hiring for right now."
- Two-column (0.9fr / 1.4fr): **left = vertical tab list**, **right = active track panel**.
- Clicking a tab sets active (cyan border + fill on active tab). Panel shows track name, tag, and the roles as wrapped chips; chips hover to cyan. Decorative floating radial glow top-right of the panel. Footer note: "Don't see your role? We train for it too — including AI/ML and emerging tracks."
- **Tracks data** (tab title · tag · roles):
  1. **AI, ML & Data Science** · *Trending* · AI / ML Engineer, Generative AI / LLM Engineer, Data Scientist, Data Analyst, Data Engineer, MLOps Engineer, NLP Engineer, Computer Vision Engineer, BI Developer
  2. **Software Engineering** · *Most roles open* · Full Stack Developer, Java Developer, Python Developer, .NET / C# Developer, Node.js Developer, React Developer, Angular Developer, Mobile App Developer
  3. **Cloud, DevOps & Infrastructure** · *Top paying* · Cloud / AWS Engineer, Azure Engineer, GCP Engineer, DevOps Engineer, Site Reliability Engineer, Kubernetes / Platform Engineer, Cloud Architect
  4. **QA, Security & Enterprise Apps** · *Always hiring* · QA / Automation Test Engineer, Cybersecurity Analyst, SOC Analyst, Salesforce Developer / Admin, ServiceNow Developer, SAP Consultant, Business Analyst, Scrum Master / PM
  5. **Data Platforms & Analytics** · *Fast growing* · Snowflake / Databricks Engineer, ETL Developer, Power BI / Tableau Developer, Database Administrator (DBA)

### 5.6 Services ("Everything you need, end to end.")
- Eyebrow "WHAT YOU GET". 3-col grid of cards, each `data-tilt3d` (3D tilt on hover, `perspective(820px) rotateY/X ±5deg translateY(-5px)`), hover lifts + cyan border. Each card: 42×42 icon tile (cyan gradient) holding a small glowing cyan square, title, desc.
- **Services data**:
  - **Career Guidance** — Strategic insights from career experts who demystify the path to your target role.
  - **Resume Crafting** — Resume optimization meeting US-market standards so you make a stellar first impression.
  - **Live Use-Cases** — Hackathon-style learning on live problem statements — you build skills by solving real cases, not studying theory.
  - **Resume Marketing** — We promote your profile to recruiters daily, opening doors to new opportunities.
  - **Interview Prep** — One-to-one coaching and mock interviews with real-time feedback to build confidence.
  - **Documentation Support** — Once placed, our team handles essential documentation for a smooth onboarding.

### 5.7 Why + Metrics ("Career success architects — not a recruiting firm.")
- Band with top/bottom borders, subtle cyan top-gradient.
- Eyebrow "WHY ENTERNSTECH"; sub "We go beyond placement, nurturing long-term career growth."
- 3 value cards (cyan title):
  - **Professional & Ethical Approach** — We say the thing. No hedging, no jargon — clear guidance you can act on.
  - **Results-Driven** — Every program is built around one outcome: getting you placed.
  - **Personalized Attention** — Tailored learning experiences mapped to your specific career goals.
- **Metrics panel** (4-col, cyan-tinted gradient box, count-up):
  - **6,825+** Candidates placed · **20 yrs** Industry experience · **7,000+** Career tracks · **3** Markets: US · Canada · India

### 5.8 Roadmap ("Seven steps to your dream job.")
- Eyebrow "HOW IT WORKS". Vertical timeline (left rail gradient line, numbered 28px nodes with cyan border + glow). Each `data-reveal`:
  1. **Enrolment & Personal Assessment** — We map your skills, goals, and the right career track.
  2. **Practical Know-How** — Hands-on, project-based practice that mirrors real job tasks — so you can do the work, not just describe it.
  3. **Portfolio Creation** — Build a portfolio of real projects and live use-cases that proves your skills to employers.
  4. **Resume Building** — A standout resume tuned to the US tech market.
  5. **Resume Marketing** — Recruiters market your profile to hiring companies.
  6. **Interview Preparation & Assistance** — Mock interviews, prep, and live support until you land it.
  7. **Job Facilitation** — Offer, onboarding documentation, and a strong start.

### 5.9 Placement success + Recruiter network
- Band, faint fill. Eyebrow "PLACEMENT SUCCESS"; H2 "Real outcomes, faster than you'd expect."; sub "A snapshot of recent placements across our tracks."
- Two-column (1.05fr / 0.95fr): **left = 2-col outcome cards**, **right = animated recruiter-network canvas** (§6).
- **Outcomes data** (role · timing · track · plan):
  - Data Scientist · Placed in 11 weeks · AI, ML & Data Science · Elite
  - Java Developer · Placed in 9 weeks · Software Engineering · Premium
  - DevOps Engineer · Placed in 13 weeks · Cloud & DevOps · Elite
  - Business Analyst · Placed in 15 weeks · Enterprise Apps · Basic
  - Cybersecurity Analyst · Placed in 12 weeks · Security · Premium
  - Full Stack Developer · Placed in 10 weeks · Software Engineering · Elite

### 5.10 Pricing route (`pricing`)
Reached via nav "Pricing" or hero "Explore programs". Top padding 128px.
- Eyebrow "CAREER ACCELERATION PROGRAMS"; H2 "Pick the level of support that fits."; sub "All plans open the gateway to your desired role."
- **Audience selector** — two big buttons (active = cyan fill + border + shadow):
  - **International** — "Residing in US or Canada" — Priced in USD ($)
  - **Domestic** — "Residing in India" — Priced in INR (₹)
- Until one is chosen, show prompt: "Choose International or Domestic above to view the plans and pricing for you." Once chosen, show "Showing {label} pricing · change".
- **3 plan cards** (Elite is `featured` — cyan gradient fill, cyan border, glow shadow, badge). Each card: badge (if any, floating top-left pill), name, tagline (min-height 38px), big price, note, optional alt-price line, duration, checklist of features (each row: 15px rounded cyan check tile + text), and an "Enrol now" gradient button (magnetic) → payment route. Cards `data-tilt3d`, hover lift.

  **Plan data** (intl $ / domestic ₹):

  - **Basic Plan** — $2,500 / ₹1,50,000 — *Initial non-refundable fee* — "Foundations to get market-ready" — Avg placement duration: 6 months — no badge.
    Features: Interview Preparation (Webinar); Recorded Technical Training; Resume Preparation; Resume Understanding Webinar; Resume Marketing; Associate Recruiter; Up to 200 Applications (market-dependent).
  - **Elite Plan** *(featured, badge "Most popular")* — $4,000 / ₹2,50,000 — *Initial non-refundable fee* — "Live coaching + dedicated marketing" — Avg 6 months.
    Features: Live Technical Brush-up with Tech Expert; Mock Interview Sessions; Interview Preparation (Webinar); Recorded Technical Training; Interview Help; Resume Preparation; One-to-One Resume Session; Resume Marketing; Associate Recruiter; Up to 200 Applications; Email / LinkedIn Chat Support; Full Time / W2.
  - **Premium Plan** *(badge "White-glove")* — $5,500 / ₹3,50,000 — *Initial non-refundable fee* — alt line: "or $13,000 one-time — flat fee" / "or ₹8,00,000 one-time — flat fee" — "Personal recruiter + automation — everything in Elite, and more" — Avg 6 months.
    Features: Personal Recruiter (dedicated to you); Automation Tools for applications; Priority interview scheduling; Dedicated onboarding documentation support; Live Technical Brush-up with Tech Expert; Mock Interview Sessions; Interview Preparation (Webinar); Recorded Technical Training; Interview Help; Resume Preparation; One-to-One Resume Session; Resume Marketing; Associate Recruiter; Up to 200 Applications; Email / LinkedIn Chat Support; Full Time / W2.

  Disclaimer under cards: "*Placement duration is subject to eligibility. *Terms & conditions apply."

- **Combo offers** ("bundle & save") — 2-col, each card shows plans tag, name, desc, price, note, "Enrol now":
  - **Career Accelerator Combo** (Elite + Premium) — $8,500 / ₹5,50,000 — *Save vs. buying separately* — "Full Elite coaching and marketing, plus the top Premium delivery upgrades — for a faster, fully guided placement."
  - **Career Starter Combo** (Basic + Elite) — $5,800 / ₹3,75,000 — *Save vs. buying separately* — "Everything in Basic, plus a few key Elite upgrades to sharpen your interviews and support."

- **Comparison table** ("Compare what's included") — sticky header row: Feature | Basic | Elite | Premium. Cells show cyan ✓ (rounded tile) or grey "—". Grouped by department:
  - **Technical Department**: Live Technical Brush-up with Tech Expert (—/✓/✓); Mock Interview Session (—/✓/✓); Interview Preparation (Webinar) (✓/✓/✓); Recorded Technical Training (✓/✓/✓); Interview Help (—/✓/✓).
  - **Resume Department**: Resume Preparation (✓/✓/✓); Resume Understanding Webinar (✓/✓/✓); One-to-One Resume Session (—/✓/✓).
  - **Delivery Department**: Resume Marketing (✓/✓/✓); Associate Recruiter (✓/✓/✓); Personal Recruiter (—/—/✓); Up to 200 Applications (✓/✓/✓); Email / LinkedIn Chat Support (—/✓/✓); Automation Tools (—/—/✓); Full Time / W2 (✓/✓/✓).

### 5.11 Payment gateway route (`pay`)
Reached by clicking any "Enrol now". Demo only — no real charge. Top padding 118px, max-width 1020px. "← Back to pricing" link at top.

- **Idle state**: eyebrow "SECURE CHECKOUT"; H1 "Complete your enrolment". Two-column (1.45fr / 1fr): left = method + form, right = sticky order summary.
- **Method tabs** (pills): UPI, Credit Card, Debit Card, Net Banking, Wallet. **If currency is $ (international), only Credit/Debit Card show** (UPI/NetBanking/Wallet are INR-only). Default method: ₹ → UPI, $ → Credit Card.
  - **UPI**: QR code (generated via `api.qrserver.com` from a `upi://pay?pa=enternstech@okhdfcbank&...` link including amount + txn ref) on white tile + "Scan & pay with any UPI app · Google Pay · PhonePe · Paytm · BHIM", UPI ID `enternstech@okhdfcbank`, Ref `ENT{timestamp}`, "Refresh QR" button.
  - **Card** (credit/debit): fields Card number, Name on card, Expiry MM/YY, CVV (password). Title switches "Credit/Debit card details".
  - **Net Banking**: 6 popular bank chips (SBI, HDFC, ICICI, Axis, Kotak, PNB) with 2-letter initials, plus a full dropdown of 30 banks.
  - **Wallet**: 6 chips — Paytm, PhonePe, Amazon Pay, Mobikwik, Freecharge, Airtel Payments Bank.
  - Validation error line in `--err` if incomplete.
- **Order summary** (sticky, cyan-tinted): "ORDER SUMMARY", plan label, "Enrolment fee", **editable amount field** (prefilled from plan price, currency-aware), "Total payable" big figure (`₹`/`$` with locale grouping — `en-IN`/`en-US`), "Pay {amount}" gradient button, footer "🔒 Secured demo checkout · no real charge".
- **Success state**: green ✓ circle, "Payment initiated", "Thank you for enrolling in {plan}. This is a demo gateway — no real charge was made…", buttons "Make another payment" / "Back to pricing".

### 5.12 FAQ ("Still have questions?")
- Max-width 880px. Accordion; one open at a time (default first open). Button row with "+"/"–" cyan sign; answer slides open.
  - **Why should I partner with a staffing firm in my job hunt?** — Our mission is to streamline and enhance your job-hunting process, providing benefits that significantly boost your career prospects — from training and resume marketing to dedicated recruiters working on your behalf.
  - **Do you provide training to enhance my skills?** — Yes. We offer various training and professional development opportunities — live technical brush-ups, recorded courses, mock interviews, and mentorship — to keep you ahead in the dynamic tech industry.
  - **What's the average timeframe for placement?** — On average, candidates are placed within several weeks to a few months. The exact duration depends on your skill set, industry demand, specific role requirements, and overall market conditions.
  - **Which plan is right for me?** — Basic suits self-starters who want core training and marketing. Elite adds live coaching and one-to-one support. Premium gives you a personal recruiter and automation tools. Combos bundle plans for the best value — talk to an advisor and we'll recommend the right fit.

### 5.13 Referral ("Refer & earn")
- Big rounded cyan-gradient panel with a floating radial glow. Eyebrow "REFER & EARN"; H2 "Know someone job-hunting? Earn for every referral."; copy "For every referral who enrolls, you earn a generous bonus. The more you refer, the more you earn."
- 3 numbered steps: **1 Refer a friend** (Share your referral with anyone launching a tech career.) · **2 They enroll** (Your referral signs up for any EnternsTech plan.) · **3 You get paid** (Receive your bonus once enrollment is confirmed.)

### 5.14 Contact ("Map your path to placement.")
- Band, faint fill. Two-column.
- **Left**: eyebrow "GET IN TOUCH"; H2 "Map your path to placement."; copy "Tell us about yourself and we'll get back to you to map your path. Prefer to talk first? Our advisors are happy to answer questions before you commit." Contact rows (40px cyan icon tiles):
  - Information — `info@enternstech.com`
  - Queries & support — `support@enternstech.com`
  - Markets — United States · Canada · India
  - Green "Ping us on WhatsApp" button (green border/fill, glowing green dot).
- **Right**: form card — Full name*, Phone*, Email*, Plan of interest (select: Basic / Elite / Premium / Career Accelerator Combo / Career Starter Combo / Not sure yet), Message / target role. Submit → validates name+email+phone, posts to FormSubmit (see §8), then shows success card "Request received" with "Submit another".

### 5.15 Brand / logo directions
- Eyebrow "BRAND · LOGO DIRECTIONS"; H2 "Five marks to build the future logo from."; sub "Concept sketches — the UI is logo-agnostic, so any direction drops in without a redesign."
- 5-col grid of cards, each an inline SVG concept + name + caption (hover cyan border):
  1. **Ascending steps** — Growth from learning to hire (3 rising bars, deepest cyan tallest).
  2. **Orbit mark** — Talent orbiting a hiring core (center dot + ellipse + small satellite).
  3. **Pathway node** — Journey converging to a hire (rising line through 3 dots).
  4. **Aperture** — Doors opening, focus on outcome (ring with a cyan wedge).
  5. **Signal wordmark** — Clean type, rising accent (uses the logo PNG).

### 5.16 Footer
- `var(--bg-footer)`, top border. 3-col: brand blurb ("Career success and growth architects — not just a recruiting and employment firm. From learning to employment.") | Company links (About us, Services, Pricing, Resources, Refer & Earn, FAQ, Contact) | Contact (`info@`, `support@`, "Phone: to be added", "Address coming soon") + "Partner with Us" button.
- Bottom bar: "© 2026 Enterns Tech. All rights reserved." · Terms & Conditions · Partner with Us · **Admin login** (small dot + label).

### 5.17 Partner modal
Opened by any "Partner with Us". Centered overlay (`rgba(3,6,12,.72)` + blur), 520px card. Eyebrow "PARTNER WITH US", H3 "Join us as a professional partner.", copy about recruiters/trainers/franchises/institutions. Form fields: Full name*, Company/organization, Email*, Phone*, **Partner type*** (select: Freelancer, Recruiter, Business Development, Franchise, Trainer / Mentor, University / College, Hiring Company, Other), Website/LinkedIn, Country/region, Years in business, "How would you like to partner with us?" textarea. Submit → validates name+email+phone+ptype, posts to FormSubmit, shows "Thanks for reaching out" success.

### 5.18 Admin modal (prototype)
Opened by footer "Admin login". 660px card.
- **Login**: email/user-ID + password. Allowed emails: `admin@enternstech.com`, `info@enternstech.com`. Password: `enterns2026` (shown as a hint in the prototype). "Forgot password?" → recovery flow (enter admin email → "Reset link sent" to `admin@enternstech.com`, demo only).
- **Dashboard** (after auth): tabs — **Plans** (edit each plan's intl/dom price + add/edit/delete feature rows), **Combo plans** (edit combo prices), **Comparison** (tap cells to toggle ✓/—), **Requests (N)** (list of contact-form leads + partner enquiries with all fields). All edits persist to `localStorage` and reflect live on the public pages. "Log out & close" at bottom.
- Persistence keys: `et_store_v1` (plans+combos), `et_comparison_v1` (comparison table), `et_subs_v1` (submissions).

---

## 6. Canvas / JS motion systems

All gated behind `prefers-reduced-motion` and touch checks where noted. Re-run color-dependent ones when theme changes.

**Hero particle web** (`heroCanvas`): 46 nodes drifting with small velocities, bouncing off edges; draw a line between any two nodes < 150px apart with alpha ∝ closeness (`rgba(accent, .14*(1-d/150))`); draw each node as a soft dot (`rgba(91,233,255,.55)`). DPR-aware. rAF loop; resize handler.

**Recruiter network** (`netCanvas`, in §5.9): a radial hub-and-spoke. 12 labels around a circle: **Google, Deloitte, Cognizant, TCS, Capgemini, Infosys, Accenture, Wipro, Amazon, IBM, Cisco, PwC**. Slow rotation; pulses of light travel out along each spoke (animated progress dots); each node is a small ringed circle with its label above; center is a pulsing cyan disc labelled **YOU** (in `--on-accent`). For light theme, swap the line/dot/label colors to theme-aware values and the center label color.

**Count-up** (`data-count` + `data-target` + optional `data-suffix`): triggered when the element scrolls into view (IO threshold 0.4); animates 0 → target over 1500ms with cubic ease-out, formatting with `toLocaleString('en-US')` and appending the suffix (e.g. `+`).

**Pointer glow / custom cursor / magnetic buttons / hero tilt / card tilt**: see §4 and §5.2. Magnetic = `[data-magnetic]` elements translate toward the cursor (`mx*0.3, my*0.5`) and spring back on leave. Card tilt = `[data-tilt3d]`. A MutationObserver re-initialises reveal/counters/tilt/cursor after route changes so newly shown sections animate.

---

## 7. Routing & state

- Single page, three "routes" toggled by showing/hiding wrappers: `home`, `pricing`, `pay`. Nav is always visible. Changing route scrolls to top smoothly.
- Section anchors for nav scroll: `#journey`(via section), `#tracks`, `#pricing`, `#success`, `#referral`, `#contact`. Scroll offset −70px for the fixed nav.
- Key state: `loading`, `solid` (nav scrolled), open FAQ index, active track index, `audience` (null/international/domestic), pricing `plans`/`combos`/`comparison` (overridable by admin via localStorage), payment fields, modal flags, admin auth/tab.

---

## 8. Forms & integrations

- All three forms (contact lead, partner, admin recovery) POST JSON to **FormSubmit** (`https://formsubmit.co/ajax/info@enternstech.com`, recovery → `admin@enternstech.com`) with `_subject` + fields, fire-and-forget (`.catch(()=>{})`). **These require the user to be online**; everything else works offline.
- Submissions are also stored locally and surfaced in the admin "Requests" tab.
- Emails are assembled at runtime (`'info'+'@'+'enternstech.com'`) as light obfuscation — keep that pattern.

## 9. Assets
- `assets/enterns-logo-motion.png` — primary logo tile (used in loader, nav, hero orbit, footer, logo-directions, admin).
- `assets/enternstech-logo.png` — alternate logo (available).
- UPI QR is generated on the fly from `api.qrserver.com` (needs network).

---

### Build order suggestion for Claude Code
1. Tokens + theme system (§0–1) and the boot/toggle script first — get dark/light working on an empty shell.
2. Nav + loader + ambient layer.
3. Home sections top-to-bottom (§5.2–5.9, 5.12–5.16).
4. Pricing route + data (§5.10).
5. Payment route (§5.11).
6. Modals (§5.17–5.18).
7. Canvas/JS motion (§6) last, theme-aware.
