# HANDOFF v2 — Regional Psychometric Assessment Module (Enterns Tech Plugin)

**For:** Claude Code (VS Code) · **Architect:** Bhaumik (non-developer; you implement)
**Supersedes:** HANDOFF v1. Same system (WordPress portal plugin, front-end-only, WP native auth, Razorpay, PHPMailer SMTP, dark CSS, LiteSpeed).
**Run mode:** Execute autonomously. Only pause for genuine business/credential decisions — those are pre-answered in §0.

---

## 0. Decisions (already made — wire these in, no placeholders)

- **Admin result email:** `admin@enternstech.com`
- **Link expiry:** 7 days (configurable constant)
- **Razorpay auto-trigger:** OFF by default; **admin enables it per product**
- **Regions:** UK, US, CA, IN
- **Education levels:** 1=School-leaving · 2=Diploma/Associate · 3=Bachelor · 4=Postgraduate
- **Fields:** IT, DATA_AI, BUSINESS, HR, FINANCE, MARKETING, INFRA, INFOSEC, HONORS
- **Hard rule (unchanged):** the candidate NEVER sees any score, band, answer key, or interpretation. Submit endpoint returns only `{status:"ok"}`.

---

## 1. Question bank (provided — do not invent questions)

A tagged bank ships as **`Enterns_Psychometric_QuestionBank.xlsx`** (sheets S1–S8 + Scoring_Config + READ ME).
**Your job is to consume it, not rewrite it.** Import it into the DB (or a versioned config) on activation/seed. Each item carries: `item_id, section, type, region, edu_min, edu_max, field, difficulty, reverse_scored, trait_or_cluster, question_text, option_a..d, correct`.

**Critical:** `correct` and `reverse_scored` are server-side only. Strip them before any data reaches the browser.

---

## 2. The resolver + rotation (the core new logic)

At assessment start, build a per-candidate paper:

1. Inputs: `region`, `education_level`, `field`.
2. For each section, filter eligible items: `(region == candidate OR region contains 'ALL')` AND `(edu_min<=level<=edu_max OR 'ALL')` AND `(field == candidate OR field=='ALL')`.
3. **Randomly select** the required count per section from the eligible pool (Sec1=12, Sec2=10, Sec3=8, Sec4=10, Sec5=8, Sec6=15 *but exactly 3 per trait C/E/ES/O/A*, Sec7=6, Sec8=4).
4. For Sec7 pick across difficulty bands appropriate to the education level (lower levels weight diff 1–2, higher weight 3–4); include field-specific items where the field matches.
5. **Randomise option order** for MCQ/forced-choice per render.
6. **Persist the selected item_ids + order** against the assessment token so a refresh shows the same paper, and so scoring knows exactly what was asked. This is the "rotation": different candidates draw different subsets, so papers differ.
7. If an eligible pool is smaller than the required count, top up from `field=='ALL'` items, then log a content-gap warning for the admin.

---

## 3. Candidate experience (Mettl-style, NO third-party branding anywhere)

Model the *experience* on a standard online-assessment platform — clean, professional, distraction-reduced — but it is 100% Enterns Tech branded. Do not name, reference, or copy any third-party platform.

**Flow when candidate opens `…/psy-assessment/?t={token}`:**

1. **Validate token** server-side (exists, not expired, not submitted). Invalid/expired → friendly "this link is no longer valid" page.
2. **Landing / welcome screen** (Enterns Tech dark theme): logo, assessment title, est. time (~25–30 min), number of sections, plain-language instructions, honesty note, and a "Begin" button. Show a consent/privacy line.
3. **Candidate details form** (collect & store on the assessment record): Full name, Email, Contact number, plus a **Field of interest / programme** dropdown (the 9 fields) and **Highest education** dropdown (the 4 levels) — used to confirm/override the resolver inputs the admin set. Pre-fill if admin already provided them. Basic validation (email format, phone required).
4. **Instructions screen:** how each question type works (Likert scale meaning, A/B choice, ranking, MCQ, open text).
5. **Sectioned test runner** (one section per step):
   - Progress bar + "Section X of 8" + overall % complete.
   - One question type per section, rendered to match the tag.
   - **Autosave** answers per step (so a dropout can resume within validity).
   - Optional soft timer display (not enforced; informational) — keep it gentle, this is profiling not an exam.
   - "Next / Back" navigation; require all items answered before advancing (client-side completeness only — NO scoring client-side).
6. **Review screen:** "You've answered all sections" → Submit.
7. **Submit:** server re-validates token, persists raw responses, runs scoring, writes scores, sets `submitted`, emails `admin@enternstech.com`. Candidate sees a **thank-you / confirmation screen only** — no result, no score, no "you passed". Send `Cache-Control: no-store`; exclude route from LiteSpeed.

UX requirements: mobile-first (works at ~380px; Bhaumik tests on phone, Eruda), keyboard accessible, ranking has a numeric fallback, autosave indicator, no answer keys/scores in any payload or JS bundle.

---

## 4. Admin portal (front-end admin UI, not wp-admin)

- **Generate Link:** candidate name/email/phone (optional at this stage), region (Auto / UK / US / CA / IN), education level, field, optional payment ref, expiry (default 7d). → creates token, returns shareable URL + copy button + "email candidate" action.
- **Auto region:** if region = Auto, resolve from candidate country / payer billing country; unknown → UK + `defaulted` flag (visible to admin).
- **Assessments list:** token, candidate, region, field, level, status, created/expiry, link to result.
- **Result view (ONLY place scores exist):** indices + bands per dimension, Big Five radar, Sec1 cluster bars, reasoning x/6, top-3 motivators, preference one-liner, the open responses, editable recommendation. Add the **fields/department context** to the report header (Field, Education level, Region).
- **Per-product Razorpay toggle:** setting to enable auto-trigger on specific paid products.

---

## 5. Data model (extends v1)

Tables (existing prefix + migration pattern):
- `psy_items` — imported question bank (all tag columns; `correct`,`reverse_scored` server-only).
- `psy_assessments` — token, candidate name/email/phone, region, region_source, education_level, field, created_by, payment_ref, status, expires_at (+7d), timestamps, `selected_items` (JSON: the resolved paper + order), `defaulted` flag.
- `psy_responses` — assessment_id, item_id, section, answer_value (JSON for rank), timestamps.
- `psy_scores` — all computed indices, cluster JSON, 5 trait scores (raw + normalised), reasoning_score, motivation_top3, preference_profile, open_responses JSON, overall_band, recommendation, computed_at.
Index token, assessment_id, status, region, field.

---

## 6. Scoring engine (server-side, per Scoring_Config sheet)

Pure, testable class. Likert index `(sum−min)/(max−min)×100`. Reverse any item with `reverse_scored=Y` (`6−raw`) before summing. Sec6: 3 items/trait, sum 3–15, normalise. Sec7: compare to `correct`, x/6. Sec4 top-3. Sec2 A/B tally → one-liner. Sec1 cluster averages. Bands 80/60/40. **Scoring uses the persisted `selected_items` so it scores exactly what was shown.** Ship known-input→known-output tests.

---

## 7. Email to admin (existing PHPMailer/SMTP)

On submit → email `admin@enternstech.com`: candidate name/contact, region, field, education level, all indices+bands, traits, reasoning x/6, top-3 motivators, preference one-liner, 4 open responses. Subject `New Psychometric Result — {candidate} ({region}/{field})`. **Never email results to the candidate.**

---

## 8. Security / cache / edge cases

Crypto-random single-use expiring tokens; re-submission blocked; expired→graceful page; rate-limit submit; sanitise/escape all I/O; nonce on submit + server re-validate; assessment route excluded from LiteSpeed + `no-store`; **no scores/keys in any client payload, JS, HTML comment, or candidate email** (verify in review); `defaulted` region visible to admin; autosave must not leak scoring.

---

## 9. Definition of done

1. Import `Enterns_Psychometric_QuestionBank.xlsx` → `psy_items` (seed/migration).
2. Resolver + rotation (random per-candidate selection, persisted, option shuffle, gap-warning).
3. Candidate flow: validate → welcome → details (name/email/phone/field/level) → instructions → sectioned runner w/ autosave & progress → review → submit → thank-you only.
4. Admin: generate link, list, result view (front-end), per-product Razorpay toggle.
5. Scoring engine + tests, scoring the persisted paper.
6. Admin email on submit to `admin@enternstech.com`.
7. LiteSpeed exclusion + no-cache on assessment routes.
8. Update CLAUDE.md / PROGRESS.md: new module, tables, bank-import location, resolver rules, reverse/answer-key config note, the "no score to candidate" invariant.
9. Manual test matrix: generate links for {UK,IN} × {IT, FINANCE} × {level 2, level 4}; complete each; confirm correct region/field/difficulty content, correct scoring against persisted paper, admin email + dashboard populated, **zero score leakage to candidate**, mobile render at 380px, autosave-resume works.

**First steps:** read CLAUDE.md/PROGRESS.md & existing admin-UI/table patterns → resolve the known admin-interface consolidation issue (don't duplicate admin UI) → import bank → build resolver + scoring with tests → candidate flow → admin flow → email + Razorpay toggle → update context docs.
