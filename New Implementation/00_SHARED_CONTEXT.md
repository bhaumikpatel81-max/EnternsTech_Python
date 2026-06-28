# EnternsTech Python Portal — Shared Context for Claude Code

> Paste this block **once** at the start of your Claude Code session, then paste
> the individual phase prompts (01–08) one at a time. Each phase prompt assumes
> Claude Code has already read this context. Do **one phase per session/commit**.

## Project facts (do not re-derive — these are confirmed)

- **Stack:** FastAPI + Jinja2 + PyMySQL (raw SQL, no ORM). Razorpay for payments.
  Deployed on Bluehost shared hosting via Phusion Passenger (`passenger_wsgi.py`
  bridges ASGI→WSGI with `a2wsgi`).
- **Python target: 3.9.** Every new module that uses `dict | None`, `list[dict]`,
  `X | Y` unions etc. MUST start with `from __future__ import annotations`.
- **DB access layer** is `app/database.py`. Use ONLY these helpers — never open
  your own connections:
  - `fetchone(sql, params=()) -> dict | None`
  - `fetchall(sql, params=()) -> list[dict]`
  - `execute(sql, params=()) -> int`  (returns lastrowid for INSERT)
  - `execute_many(sql, params_list)`
  All use `%s` placeholders and DictCursor. `autocommit=True`.
- **Auth** is `app/auth.py`: JWT in an HTTP-only cookie named `enp_token`.
  `get_current_user(request) -> dict | None` returns `{"sub": <user_id>, "role": ..., "email": ...}`.
  Route guards check `user.get("role")` and redirect to `/login` (pages) or
  return `JSONResponse({"ok": False, "error": ...}, status_code=401)` (APIs).
  Follow the existing `_admin_required` / `_get_student` / `_get_mentor` patterns.
- **Templates** are rendered with the NEW signature:
  `templates.TemplateResponse(request, "name.html", {ctx})` (request first).
- **Routers** live in `app/routes/*.py`, services in `app/services/*.py`,
  registered in `app/main.py` via `app.include_router(...)`.
- **Money** is stored in **paise** (INR × 100) everywhere. Never trust client
  amounts; the authoritative source is `app/services/catalog.py` (DB `plans`/`combos`).
- **Privacy rule (already enforced, keep it):** students never see mentor
  phone/email; mentors never see student phone/email. All contact stays
  in-platform. See `app/services/privacy.py`. Any new feature must NOT leak
  direct contact info between mentor and mentee.

## Existing DB tables (migrations 001–005)
users, mentors, students, sessions, payments, requests, plans, plan_features,
combos, discounts, app_settings, manual_revenue, password_tokens, feedback,
psy_assessments, psy_items, psy_responses, psy_scores, psy_rate_limits.

`sessions` currently: id, student_id, mentor_id, scheduled_at (DATETIME, naive UTC),
duration_min, status ENUM('scheduled'|'planned','completed','cancelled'),
mentor_paid, rate_applied, notes, created_at. `booked_by`, `topic` columns are
written by code — verify they exist; add them in migration 006 if missing.

## Confirmed product decisions (build to THESE, do not substitute)
1. **Meeting link = self-hosted/free + privacy-preserving.** Use **Jitsi Meet**
   with a non-guessable room name (e.g. `enterns-<session_id>-<secrets.token_urlsafe(8)>`),
   URL `https://meet.jit.si/<room>`. No external API, no account, no Google.
   Store the generated URL on the session. Do not expose mentor/mentee identity
   in the room name.
2. **Escrow = real ledger + state machine.** New `escrow` table with states
   `locked → partially_released → fully_released` (+ `refunded`). Releases happen
   per completed session (milestone). Keep a per-transaction `escrow_ledger`.
3. **Timezone = store mentee IANA tz, convert in Python with `zoneinfo`** (stdlib,
   3.9 has it via `from zoneinfo import ZoneInfo`; add `tzdata` to requirements for
   Bluehost). Persist `scheduled_at` as UTC; display in the viewer's local tz.
4. **Reviews = full double-blind.** Ratings are hidden until BOTH parties submit
   OR N days pass (default 7), then they become "released". **Released ratings are
   shown to ADMIN ONLY** — never surfaced to student or mentor in this build.
5. **DB migrations allowed.** Add `migrations/006_workflow.sql`. Make every
   statement idempotent / safe to re-run: `CREATE TABLE IF NOT EXISTS`, and for
   `ALTER TABLE ... ADD COLUMN` guard with an `INFORMATION_SCHEMA` check or use a
   small Python migration runner that ignores "duplicate column" errors. MySQL 5.7/8
   on Bluehost — avoid Postgres-only syntax. Use `from __future__ import annotations`
   in any helper scripts.

## Workflow being implemented (from the approved diagram)
1. **Mentor Lifecycle:** application → admin verification gate → profile/specialization → calendar (UTC) → pricing.
2. **Mentee Lifecycle:** account → skill intake → **targeted filtering (skill match)** → **capacity gate (mentor full?)** → available vs locked.
3. **Matching:** UTC↔local conversion → single-slot vs bundle → **fee calc (platform split)** → **escrow lock** → **meeting link** → dual delivery (dashboard + email).
4. **Cancellation/No-show:** disruption → reschedule prompt → outcome → **SLA / offense rules**.
5. **Post-session:** completion → **milestone escrow release** → invoice → **double-blind review gate** → rating release after N days → slot release on bundle completion → marketplace reactivation (capacity +1).
6. **State machines:** Mentor Applied→Verified→Live; Booking Pending→Confirmed→Active→Complete; Escrow Locked→Partial→Full; Review Pending→Hidden→Visible(admin).

## Platform fee split (CONFIRM exact number with owner if unsure)
Diagram shows "Mentee +7.5% / Mentor −7.5%". Implement as constants in
`app/config.py`: `PLATFORM_FEE_MENTEE_PCT = 7.5`, `PLATFORM_FEE_MENTOR_PCT = 7.5`.
Mentee pays `rate × (1 + 0.075)`; mentor receives `rate × (1 − 0.075)`; platform
keeps the spread. All math in paise, round with `int(round(...))`.

## Working rules for Claude Code
- Make the **minimum** changes needed for the phase. Don't refactor unrelated code.
- After each phase: run `python -c "import app.main"` to confirm no import errors,
  and list the files you changed.
- Match existing code style (snake_case, the `@router.post` + `JSONResponse({"ok":...})`
  convention, naive-UTC `datetime.now(timezone.utc).replace(tzinfo=None)` for DB writes).
- If a column/table you need doesn't exist yet, it belongs in migration 006 — add it
  there, don't silently assume it.
- Never print secrets. Read config via `from app.config import settings`.
