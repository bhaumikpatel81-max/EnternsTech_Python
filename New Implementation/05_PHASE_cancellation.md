# PHASE 05 — Cancellation / No-show / Reschedule + SLA offense rules

**Depends on:** Phases 01, 04 (cancellations table, escrow.refund).
**Paste 00_SHARED_CONTEXT.md first if new session.**

Covers diagram §4 (Disruption → Reschedule Prompt → Outcome → SLA/Offense rules)
and the "Return to Schedule Flow" arrow.

## 1. SLA config (`app/config.py`)
- `CANCEL_SLA_HOURS: int = 24`  (cancel earlier than this = within SLA / no penalty)
- `NOSHOW_GRACE_MIN: int = 15`

## 2. New service `app/services/scheduling.py` (`from __future__ import annotations`)
- `def within_sla(scheduled_at_utc: datetime) -> bool` — true if now is more than
  `CANCEL_SLA_HOURS` before the session.
- `def cancel_session(session_id, actor_role, reason) -> dict` —
  sets `sessions.status='cancelled'`, `cancelled_by=actor_role`, `cancel_reason`,
  writes a `cancellations` row with `kind='cancel'`, `within_sla` flag.
  If the session was the only/last consumer of an escrow and cancelled within SLA by
  mentor/admin, trigger `escrow.refund` for that session's share (or full escrow if
  no sessions released). Student late-cancel: no refund of that session's escrow,
  but DO restore one `sessions_used` only when within SLA (business rule —
  keep it simple: within SLA restores the session credit, late does not).
- `def mark_no_show(session_id, no_show_by) -> dict` — status='no_show',
  record `cancellations` row `kind='no_show'`, `within_sla=0`. Mentor no-show =
  refund that session's escrow share; student no-show = forfeit (no refund).
- `def reschedule(session_id, new_slot_utc, mentee_tz) -> dict` — only if session not
  completed; set old row status='rescheduled', create a NEW session row with same
  mentor/student/bundle_id/escrow linkage, fresh `meeting_url`
  (call `meeting.jitsi_url`), `cancellations` row `kind='reschedule'`. This is the
  "Return to Schedule Flow" path — the new session re-enters the normal lifecycle.

## 3. Offense tracking
- `def offense_count(role_id, role) -> int` — count `within_sla=0` rows for that
  student or mentor in the last 90 days.
- Surface offense_count on the admin student/mentor views. (No auto-ban in this
  build — admin decides. Just expose the number.)

## 4. Routes
Add endpoints (follow existing auth-guard patterns, return `JSONResponse`):
- Student: `POST /student/api/cancel-session` (own sessions only),
  `POST /student/api/reschedule-session`.
- Mentor: `POST /mentor/api/cancel-session`, `POST /mentor/api/no-show`
  (mentor reports student no-show), `POST /mentor/api/reschedule-session`.
- Admin: `POST /admin/sessions/cancel`, `/admin/sessions/no-show`,
  `/admin/sessions/reschedule` (admin override, any session).
Validate ownership: a student/mentor may only act on sessions where they are the
student_id/mentor_id. Reschedule must re-validate the new slot is free (reuse the
Phase-02 slot logic) and store `mentee_tz`.

## 5. UI
Add "Cancel" and "Reschedule" buttons to upcoming sessions on student & mentor
dashboards (only for status in scheduled/confirmed/active). Reschedule opens the
same slot picker used for booking. Admin sessions page gets cancel/no-show/reschedule
controls.

## Acceptance
- Mentor cancels >24h out → escrow share refunded, `cancellations.within_sla=1`.
- Student no-show → status no_show, escrow forfeit, offense_count increments.
- Reschedule creates a fresh session with a new Jitsi link and keeps the escrow link.
- `python -c "import app.main"` imports.

Touch: `app/config.py`, `app/services/scheduling.py` (new),
`app/routes/{student,mentor,admin}.py`, dashboard + admin templates.
