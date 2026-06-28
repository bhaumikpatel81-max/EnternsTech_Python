# PHASE 02 — Timezone handling (store UTC, display mentee-local)

**Depends on:** Phase 01 (students.timezone, mentors.timezone, sessions.mentee_tz).
**Paste 00_SHARED_CONTEXT.md first if new session.**

Goal (diagram §3.1 "UTC ↔ Mentee Local Time"): all `scheduled_at` values are
stored as **naive UTC** (unchanged), but every slot/session shown to a user is
rendered in **their** IANA timezone. Mentors define slots in their own tz.

## 1. Requirements
Add `tzdata>=2024.1` to `requirements.txt` (Bluehost's Python may lack system tz data;
`zoneinfo` needs it). Don't remove anything.

## 2. New service `app/services/tz.py`
Start with `from __future__ import annotations`. Implement:
- `valid_tz(name: str) -> bool` — true if `ZoneInfo(name)` constructs.
- `to_utc(naive_local: datetime, tz: str) -> datetime` — interpret a naive local
  datetime in `tz`, return naive UTC.
- `from_utc(naive_utc: datetime, tz: str) -> datetime` — return tz-aware local.
- `fmt_local(naive_utc: datetime, tz: str, fmt="%a %d %b, %H:%M %Z") -> str`.
- `COMMON_TZS: list[str]` — a short curated list for dropdowns
  (`Asia/Kolkata`, `America/New_York`, `America/Los_Angeles`, `Europe/London`,
   `Asia/Dubai`, `Asia/Singapore`, `Australia/Sydney`, `UTC`).
Use stdlib only: `from zoneinfo import ZoneInfo`, `from datetime import datetime, timezone`.

## 3. Capture timezone
- **Student:** add a timezone `<select>` (use `COMMON_TZS`) on the student dashboard
  profile area; POST to a new `student.py` endpoint `/student/api/set-timezone`
  that validates with `valid_tz` and updates `students.timezone`.
- **Mentor:** same on mentor dashboard → `/mentor/api/set-timezone` → `mentors.timezone`.
- Default both to `Asia/Kolkata` (already the column default).

## 4. Slot generation becomes tz-aware
In `app/routes/student.py`, the `_open_slots(mentor, ...)` helper currently treats
mentor `slots_json` start times as naive UTC. Change it so:
- Mentor slot times are interpreted in **mentor's** timezone (`mentor["timezone"]`),
  converted to UTC via `tz.to_utc` for storage/compare.
- The `display` string for each slot is rendered in the **student's** timezone via
  `tz.fmt_local`. Keep the machine `datetime` key in `"%Y-%m-%d %H:%M"` **UTC** so
  the existing booking-validation compare still works.
- When booking (`/student/api/book-slot`), persist `sessions.mentee_tz = student.timezone`.

## 5. Display conversions
- Student dashboard: show each session's time using `tz.fmt_local(scheduled_at, student.timezone)`.
- Mentor dashboard: show using `tz.fmt_local(scheduled_at, mentor.timezone)`.
- Admin sessions page: show UTC plus a small "(stored UTC)" hint (admin is global).
Add a Jinja filter or pass pre-formatted strings from the route — prefer formatting
in the route to keep templates simple.

## Acceptance
- Booking a slot writes UTC to `scheduled_at` and the student's tz to `mentee_tz`.
- Same session shows different local clock times on student vs mentor dashboards.
- `python -c "import app.main"` imports; invalid tz strings are rejected by the API.

Touch only: `requirements.txt`, `app/services/tz.py` (new), `app/routes/student.py`,
`app/routes/mentor.py`, and the two dashboard templates. Don't start escrow yet.
