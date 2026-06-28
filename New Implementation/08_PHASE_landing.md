# PHASE 08 — Landing page redesign (faithful to the Claude-Design export)

**Independent of 01–07** (pure frontend). Can be done anytime.
**Paste 00_SHARED_CONTEXT.md first if new session.**

Goal: replace the current plain `templates/home.html` with a rich, animated landing
page that matches the look of the approved design (the standalone HTML export). The
**color palette already matches** the portal — keep it. The gap is typography,
section richness, and motion. Build it as clean **Jinja2 + vanilla CSS + a little
vanilla JS** (NO Vue/React, NO build step — it must render under FastAPI/Jinja2 and
work on Bluehost). Keep the existing Razorpay payment modal + `/api/payments/*`
wiring intact.

## Design tokens (use exactly these)
- Background: `#05080F` (page), surfaces `#0C1426` / `#05101F`.
- Accent cyan: `#22D3EE`; secondary blue `#3BA4FF`; soft cyan `#9FE9F5`.
- Text: headings `#ECF2FF`; body `#9FB1CE`; muted `#8295B5` / `#6B7C9E`.
- Success green `#5BE89A`; warning/alert `#FF8A80`.
- Fonts: headings **'Space Grotesk'**, body **'Inter'** — load from Google Fonts in
  `<head>` (`<link>` to fonts.googleapis.com). Fall back to system-ui.
- Generous spacing, rounded cards (14–16px radius), subtle gradient hairlines
  (`linear-gradient(180deg,#22D3EE,...)`), soft glow on the cyan accents.
- Add an on-scroll reveal: elements with a `data-reveal` attribute fade/slide up via
  an `IntersectionObserver` (small inline `<script>`). Respect
  `prefers-reduced-motion`.

## Sections to build (in this order) with this exact copy
1. **Sticky nav** — logo "Enterns Tech" (cyan), links: Plans · Process · Become a
   Mentor · Sign In. Keep `/partner` and `/login` hrefs.

2. **Hero** — H1 "Launch your tech career with expert mentoring." (style the key
   phrase in cyan). Sub: a short line about CV redesign, mock interviews, 1-on-1
   mentoring. Two buttons: "View Plans" (primary) → #plans, "Become a Mentor"
   (ghost) → /partner.

3. **"One path. Five transformations."** sub: *"Every candidate moves through the
   same proven arc — from first skill to signed offer."* A 5-node horizontal
   stepper (animated).

4. **"7,000+ career tracks. One built for you."** sub: *"From AI to enterprise apps —
   we train and place across the roles the US market is hiring for right now."*
   Category cards (use these): **AI, ML & Data Science** (tag *Trending*),
   **Software Engineering** (tag *Most roles open*), **Cloud, DevOps &
   Infrastructure** (tag *Top paying*), **QA, Security & Enterprise Apps** (tag
   *Always hiring*), **Data Platforms & Analytics** (tag *Fast growing*).

5. **"Everything you need, end to end."** 6 service cards (3-col grid):
   - **Career Guidance** — Strategic insights from career experts who demystify the path to your target role.
   - **Resume Crafting** — Resume optimization meeting US-market standards so you make a stellar first impression.
   - **Live Use-Cases** — Hackathon-style learning on live problem statements — you build skills by solving real cases, not studying theory.
   - **Resume Marketing** — We promote your profile to recruiters daily, opening doors to new opportunities.
   - **Interview Prep** — One-to-one coaching and mock interviews with real-time feedback to build confidence.
   - **Documentation Support** — Once placed, our team handles essential documentation for a smooth onboarding.

6. **"Career success architects — not a recruiting firm."** sub: *"We go beyond
   placement, nurturing long-term career growth."* 3 value props:
   - **Professional & Ethical Approach** — We say the thing. No hedging, no jargon — clear guidance you can act on.
   - **Results-Driven** — Every program is built around one outcome: getting you placed.
   - **Personalized Attention** — Tailored learning experiences mapped to your specific career goals.

7. **"Seven steps to your dream job."** vertical timeline with a gradient spine,
   7 steps:
   1. **Enrolment & Personal Assessment** — We map your skills, goals, and the right career track.
   2. **Practical Know-How** — Hands-on, project-based practice that mirrors real job tasks.
   3. **Portfolio Creation** — Build a portfolio of real projects and live use-cases.
   4. **Resume Building** — A standout resume tuned to the US tech market.
   5. **Resume Marketing** — Recruiters market your profile to hiring companies.
   6. **Interview Preparation & Assistance** — Mock interviews, prep, and live support until you land it.
   7. **Job Facilitation** — Offer, onboarding documentation, and a strong start.

8. **"Real outcomes, faster than you'd expect."** sub: *"A snapshot of recent
   placements across our tracks."* Outcome cards (role → track): Data Scientist
   (AI, ML & Data Science), Java Developer (Software Engineering), DevOps Engineer
   (Cloud & DevOps), Business Analyst (Enterprise Apps), Cybersecurity Analyst
   (Security), Full Stack Developer (Software Engineering).

9. **"Pick the level of support that fits."** sub: *"All plans open the gateway to
   your desired role."* **Render plans dynamically** from the `plans` context the
   route already passes (`{% for plan_id, plan in plans.items() %}`). Keep the
   existing "Enrol Now" button calling `openPaymentModal(...)`. Show
   `plan.price_dom` and `plan.price_intl` if present, plus `plan.features`.
   Confirmed prices for reference (don't hardcode — they come from DB):
   Basic ₹1,50,000 / $2,500 · Elite ₹2,50,000 / $4,000 · Premium ₹3,50,000 / $5,500 ·
   Career Starter Combo ₹3,75,000 / $5,800 · Career Accelerator Combo ₹5,50,000 / $8,500.

10. **"Combo offers — bundle & save"** sub: *"Two plans combined into one offer —
    more services, better value."* Render from `combos` if available (route passes
    catalog); otherwise hide gracefully.

11. **Referral** — "Earn for every referral." *"For every referral who enrolls, you
    earn a generous bonus. The more you refer, the more you earn."*

12. **FAQ** — "Still have questions?" (4 collapsible items; write sensible Q/A about
    plans, payment, eligibility, placement support — accordion via vanilla JS).

13. **Footer** — © Enterns Tech, links to Become a Mentor / Login, brand line.

## Route changes
`app/main.py` `home()` already passes `plans`. Also pass `combos` (from
`get_catalog()["combos"]`) so section 10 can render. No other route changes.

## Hard constraints
- Pure server-rendered Jinja2 + CSS + minimal vanilla JS. No Vue/React/build tools.
- Mobile responsive (the owner uses mobile + desktop): grids collapse to 1 column
  under ~720px; nav becomes a simple stack/hamburger.
- Keep the payment modal markup + the existing `<script>` that talks to
  `/api/payments/create-order` and `/verify` (Razorpay checkout). Don't break it.
- Put the big CSS in `public/css/landing.css` (new) linked from `home.html`, so
  `portal.css` stays for the dashboards.

## Acceptance
- `/` renders the full multi-section page with fonts, reveal animations, and live
  plan cards from the DB.
- Razorpay enrol flow still works end to end.
- Looks right at 375px and 1440px widths.
- `python -c "import app.main"` imports.

Touch: `templates/home.html` (rewrite), `public/css/landing.css` (new),
`app/main.py` (pass `combos`). Do not touch dashboards.
