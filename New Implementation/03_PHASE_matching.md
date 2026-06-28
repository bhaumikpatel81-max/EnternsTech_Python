# PHASE 03 — Matching: skill filtering, capacity gate, fee split, meeting link

**Depends on:** Phases 01–02.
**Paste 00_SHARED_CONTEXT.md first if new session.**

Covers diagram §2 (targeted filtering, capacity gate) and §3 (fee calc, meeting link,
dual delivery). Escrow LOCK is wired here but the escrow service itself is Phase 04 —
call into a thin stub if needed and finish wiring in Phase 04. Prefer to do this
phase AFTER Phase 04 if you'd rather not stub; if so, swap the order.

## 1. Platform fee config
In `app/config.py` add:
`PLATFORM_FEE_MENTEE_PCT: float = 7.5` and `PLATFORM_FEE_MENTOR_PCT: float = 7.5`.

## 2. New service `app/services/matching.py`
`from __future__ import annotations`. Implement:
- `normalize_tags(text: str) -> set[str]` — lowercase, split on commas/whitespace,
  strip blanks.
- `match_score(student_tags: str, mentor_specializations: str) -> int` — size of
  tag intersection.
- `filtered_mentors(student: dict) -> list[dict]` — approved mentors, sorted by
  `match_score` desc then name; EXCLUDE mentors at/over capacity
  (`active_mentees >= capacity`) unless none match (then show all approved, flagged).
  Each returned dict gets `match_score` and `is_full` keys. Apply
  `privacy.student_view_of_mentor` before returning.
- `mentor_has_capacity(mentor_id: int) -> bool`.

## 3. Fee calculation in `app/services/matching.py`
- `def fee_breakdown(rate_paise: int) -> dict:` returns
  `{mentee_pays, mentor_gets, platform_keeps}` (all paise, int-rounded) using the
  two config percentages. mentee_pays = rate*(1+mentee/100); mentor_gets =
  rate*(1-mentor/100); platform_keeps = mentee_pays - mentor_gets.

## 4. Wire filtering + capacity into student dashboard
In `app/routes/student.py` dashboard: replace the "all approved mentors" list with
`matching.filtered_mentors(student)`. In the template, render a mentor's state as
**Available for Booking** vs **Locked / Filtered Out** (grey, non-clickable) based
on `is_full`. Keep the existing "pick mentor" / "request change" actions for
available mentors only.

## 5. Meeting link (Jitsi, privacy-preserving)
New service `app/services/meeting.py` (`from __future__ import annotations`):
- `def jitsi_url(session_id: int) -> str:` →
  `f"https://meet.jit.si/enterns-{session_id}-{secrets.token_urlsafe(8)}"`.
  Room name must NOT contain names/emails.
On successful booking (`/student/api/book-slot`) and on admin `sessions/add`:
- After inserting the session, generate the link, `UPDATE sessions SET meeting_url=%s`.
- Compute `fee_breakdown(rate)` and store the mentor portion for escrow (Phase 04
  consumes it). If Phase 04 not done yet, just store the link and leave escrow to 04.

## 6. Selection: single slot vs bundle
Support a `bundle` flag in the book-slot payload. If the student's plan grants N
sessions and they choose "book bundle", create N session rows sharing a new
`bundle_id` (use the lastrowid of the first as the bundle_id, or a uuid-int).
Single booking = `bundle_id NULL`. Validate each slot is still free.

## 7. Dual delivery
After booking, (a) the dashboard already reflects it, and (b) send a confirmation
email to the student via `app/email_service.py` — add
`send_booking_confirmation(student_email, when_local_str, meeting_url, mentor_name_masked)`.
Do NOT include mentor's real contact — only first name or display handle.

## Acceptance
- Student sees mentors ranked by skill match; full mentors show as Locked.
- Booking creates session(s) with a Jitsi `meeting_url` and correct fee math.
- Confirmation email function exists and is called (can be a no-op if SMTP unset).
- `python -c "import app.main"` imports.

Touch: `app/config.py`, `app/services/matching.py` (new), `app/services/meeting.py`
(new), `app/routes/student.py`, `app/routes/admin.py` (sessions/add link+fee),
`app/email_service.py`, student dashboard template.
