# PHASE 04 — Escrow ledger + state machine (Locked → Partial → Full)

**Depends on:** Phase 01 (escrow, escrow_ledger tables), Phase 03 (fee_breakdown).
**Paste 00_SHARED_CONTEXT.md first if new session.**
**Tip:** if you didn't stub escrow in Phase 03, do this phase then revisit 03's
"store mentor portion / lock" step.

Covers diagram §3.4 (Escrow Lock), §5.2 (Milestone Release), and the
Escrow state machine (Locked→Partially Released→Fully Released, +Refunded).

## New service `app/services/escrow.py`  (`from __future__ import annotations`)
All amounts in paise. Every state change writes BOTH the `escrow` row and an
`escrow_ledger` audit row, inside the same logical operation.

- `def lock(student_id, mentor_id, mentor_portion_paise, sessions_total, *, bundle_id=None, payment_id=None) -> int:`
  Inserts an `escrow` row with `state='locked'`, `total_paise=mentor_portion_paise`,
  `sessions_total`, `released_paise=0`, `sessions_released=0`; writes a ledger
  `direction='lock'`. Returns escrow_id.

- `def release_for_session(session_id: int) -> dict:`
  Finds the escrow tied to that session's student+mentor (match on bundle_id when
  present, else most recent locked escrow for the pair). Releases
  `total_paise // sessions_total` (last session releases the remainder so rounding
  never strands paise). Increments `sessions_released`. Sets state to
  `partially_released`, or `fully_released` when `sessions_released == sessions_total`.
  Writes ledger `direction='release'`. Idempotent: if this session already triggered
  a release (track via ledger.session_id), do nothing and return `{"ok": True, "already": True}`.

- `def refund(escrow_id: int, reason: str) -> dict:`
  Moves remaining (`total_paise - released_paise`) to refunded, sets state
  `'refunded'`, ledger `direction='refund'`. Used by the cancellation phase.

- `def summary(escrow_id: int) -> dict` and
  `def mentor_balance(mentor_id: int) -> dict` (sum of released vs locked) for dashboards.

## Wire LOCK at booking
In the booking path (student `/api/book-slot` and admin `sessions/add`), after the
session(s) are created and `fee_breakdown` computed, call `escrow.lock(...)` with the
mentor portion (× number of sessions for a bundle). Store nothing sensitive in the
ledger note beyond ids.

## Wire RELEASE at completion
Wherever a session is marked complete today (admin `sessions/mark-complete` AND
mentor `/mentor/api/session/{id}/complete`), after setting status='completed' and
bumping `sessions_used`, call `escrow.release_for_session(session_id)`.

## Admin visibility
Add an escrow panel to the admin sessions or payments page: list each escrow with
state, total, released, remaining, and its ledger entries. Read-only is fine for now,
plus an admin "force refund" button calling `escrow.refund`.

## Acceptance
- Booking a 4-session bundle locks 4 × mentor-portion; each completion releases 1/4;
  the 4th flips state to `fully_released` with zero rounding loss.
- Re-running a completion does not double-release (idempotent).
- `escrow_ledger` shows a clean lock→release×N trail.
- `python -c "import app.main"` imports.

Touch: `app/services/escrow.py` (new), `app/routes/student.py`,
`app/routes/mentor.py`, `app/routes/admin.py`, admin template(s).
