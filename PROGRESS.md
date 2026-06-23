# PROGRESS.md — Enterns Tech Portal

## Plugin version: 1.6.0

---

## Phase 1 — Core infrastructure
**Status: complete**
- Plugin scaffold, activation hooks, DB tables (mentors, students, sessions, payments, requests, feedback)
- Role creation (`et_mentor`, `et_student`)
- WP page creation (ET Admin, Mentor Portal, Student Portal, Partner with Us)

## Phase 2 — Shortcodes + asset pipeline
**Status: complete**
- `[enp_admin]`, `[enp_mentor]`, `[enp_student]`, `[enp_partner_form]`
- Dark-theme CSS design system (`portal.css`)
- Portal JS + WP localize script

## Phase 3 — Mentor onboarding
**Status: complete**
- Mentor apply form (`[enp_partner_form]`)
- Admin review queue in standalone portal
- Mentor dashboard template

## Phase 4 — Razorpay payments + student activation
**Status: complete**
- Razorpay order creation + webhook verification
- Manual mark-paid flow
- Student activation (role grant, welcome email)
- Payment history table in admin portal

## Phase 5 — Student portal
**Status: complete**
- Student dashboard (skills editor, mentor matching, plan upgrade)
- Session booking + session list
- Admin requests tab (approve/reject skill change, session, upgrade)

## Phase 6 — Psychometric assessment module
**Status: complete**
- `psy-bank.php` — 178 items imported from Excel (S1–S8 + Scoring_Config)
  - Generated via Node.js + xlsx package; never edit by hand
- `psy-install.php` — 4 tables (`psy_items`, `psy_assessments`, `psy_responses`, `psy_scores`)
  - `enp_activate()` in `install.php` calls seed + page creation
- `psy-resolver.php` — `ENP_Psy_Resolver` class
  - Region / edu / field filtering, random selection, gap warning, option shuffle
  - Sec6: exactly 3 per Big Five trait; Sec7: difficulty-weighted
- `psy-scorer.php` — `ENP_Psy_Scorer` class (all static)
  - Likert index, reverse scoring, Big Five normalisation, MCQ reasoning x/6
  - Reads `correct`/`reverse_scored` from DB — never from client
- `psy-ajax.php` — 7 AJAX endpoints (generate link, validate token, save details, autosave, submit, email link, save recommendation)
  - Submit: rate-limited (5/hr), runs scorer, emails admin, returns `{status:"ok"}` only
- `psy-shortcode.php` — `[enp_psychometric]` shortcode + asset enqueueing
  - LiteSpeed no-cache + `Cache-Control: no-store` on assessment routes
- `templates/psy-candidate.php` — multi-step candidate UI (8 screens)
  - No score/band shown to candidate; thank-you screen only
- `assets/css/psychometric.css` — dark-theme assessment styles
- `assets/js/psychometric.js` — candidate flow (token validate → details → instructions → sectioned runner → submit)
  - Drag-and-drop rank with numeric fallback
  - Autosave on section advance; submit returns `{status:"ok"}` only
- `tests/psy-scorer-test.php` — 35 unit tests (ReflectionClass, no WP/DB)
  - Run: `php enterns-portal/tests/psy-scorer-test.php`
- `admin-portal/index.php` — Assessments tab added
  - Sub-tabs: list | generate | settings
  - Generate link form (name/email/phone/region/edu/field/payment ref)
  - Assessments list table (status badge, copy link, view result)
  - Result view: all indices + bands, Big Five bars, reasoning x/6, top motivators, open responses, editable recommendation
  - Razorpay per-product toggle (stored in `enp_psy_rzp_plans` WP option)

---

## Pending / next phases

- [ ] Auto-trigger psychometric link on Razorpay activation (hook into `enp_activate_student`)
- [ ] Candidate reminder email (3 days before expiry)
- [ ] PDF export of result view (for admin use)
- [ ] Batch generate links (CSV upload)
