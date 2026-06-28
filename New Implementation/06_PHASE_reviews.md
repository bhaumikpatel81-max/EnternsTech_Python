# PHASE 06 — Double-blind review insulation (admin-only visibility)

**Depends on:** Phase 01 (reviews table).
**Paste 00_SHARED_CONTEXT.md first if new session.**

Covers diagram §5 "Review Insulation — Double-Blind Gate", "Release Ratings Globally
after N days", and the Review state machine (Pending → Hidden → Released).
**CONFIRMED RULE:** released ratings are visible to **ADMIN ONLY**. Students and
mentors never see each other's ratings in this build. They only see whether their
own review was submitted.

## 1. Config (`app/config.py`)
`REVIEW_RELEASE_DAYS: int = 7`.

## 2. New service `app/services/reviews.py` (`from __future__ import annotations`)
- `def submit(session_id, author_role, rating, comment) -> dict` —
  rating clamped 1..5. Upsert into `reviews` (UNIQUE session_id+author_role).
  New rows start `state='hidden'` (double-blind: hidden from the other party from
  the moment it exists). Returns ok.
- `def maybe_release(session_id) -> None` — the double-blind gate: if BOTH roles
  have submitted for this session, set both rows `state='released'`, `released_at=now`.
  Call this at the end of `submit`.
- `def release_due() -> int` — time-based fallback: set any `reviews` still
  `state IN ('pending','hidden')` to `'released'` when their session's
  `scheduled_at` + `REVIEW_RELEASE_DAYS` < now. Returns count released. (This is
  "Release Ratings Globally after N days".)
- `def admin_list(filters) -> list[dict]` — released + unreleased, joined to
  session/student/mentor, for the admin view.
- `def mentor_avg(mentor_id) -> float | None` — average of RELEASED mentor-directed
  ratings, **for admin display only**.

## 3. Triggering time-based release without cron
Bluehost shared hosting + Passenger has no reliable cron from the app. Implement a
lightweight lazy trigger: call `reviews.release_due()` at most once per N minutes,
guarded by a timestamp in `app_settings` (key `reviews_last_release`), invoked from
the admin overview load and from the review-submit path. Also provide
`scripts/release_reviews.py` (`from __future__ import annotations`) so a real cron
can be added later if desired.

## 4. Routes
- Student: `POST /student/api/review` (rate the just-completed session/mentor).
  Only allowed for sessions with status completed where they are the student.
- Mentor: `POST /mentor/api/review` (rate the student), same guard.
- Both responses reveal ONLY the caller's own submission state — never the
  counterpart's rating.
- Admin: `GET /admin/reviews` page listing all reviews with state, rating, comment,
  session/parties, and per-mentor average. Add nav link in admin layout.

## 5. UI
On completed sessions, student & mentor dashboards show a "Leave feedback" form
(1–5 stars + optional comment) if they haven't submitted; after submit show
"Feedback submitted" with NO rating from the other side. Admin reviews page shows
everything.

## Acceptance
- Two parties submit → both flip to released; neither sees the other's score in
  their dashboard (only admin does).
- A session older than N days with one/zero reviews gets released by `release_due`.
- `python -c "import app.main"` imports.

Touch: `app/config.py`, `app/services/reviews.py` (new),
`scripts/release_reviews.py` (new), `app/routes/{student,mentor,admin}.py`,
`templates/admin/layout.html` (+ new `templates/admin/reviews.html`),
student & mentor dashboards.
