# PHASE 07 — Lifecycle closeout: capacity counters, slot release, specializations, invoice

**Depends on:** Phases 01–06.
**Paste 00_SHARED_CONTEXT.md first if new session.**

Covers the remaining diagram boxes: Mentor "Specialization Mapping / Tag Domain
Expertise" (§1.2), "Avatar Customization / Profile" (§1 post-approve), Mentee
"Capacity Gate" counter mechanics, §5 "Slot Release: Final Bundle Session Closes",
§5 "Marketplace Reactivation: Headcount +1 / Reopen Slot", and "Payment/Invoice
Generation".

## 1. Capacity counters (make the §2 capacity gate real)
The `mentors.active_mentees` column (Phase 01) must be kept accurate:
- When a student is assigned a mentor (student `pick-mentor`, admin `assign-mentor`):
  increment that mentor's `active_mentees`; if reassigning away from an old mentor,
  decrement the old one. Never below 0; never above `capacity` (reject if full unless
  admin override).
- Add `app/services/capacity.py` (`from __future__ import annotations`) with
  `attach(student_id, mentor_id, *, admin_override=False)`,
  `detach(student_id, mentor_id)`, `recount(mentor_id)` (authoritative COUNT(*)
  rebuild used to self-heal drift). Phase-03 `filtered_mentors` already reads
  `active_mentees`/`capacity` — this phase makes the number trustworthy.

## 2. Slot release on bundle completion (§5)
When the FINAL session of a bundle completes (detect: all sessions sharing
`bundle_id` are completed/cancelled), and the student's plan sessions are exhausted:
- mark the engagement closed (set student `status` appropriately, or a flag),
- call `capacity.detach(student_id, mentor_id)` so the mentor's headcount frees up.
Single (non-bundle) sessions: no auto-detach (ongoing plan continues).

## 3. Marketplace reactivation (§5 "Headcount +1 / Reopen Slot")
After detach, if the mentor was previously `is_full`, they automatically re-appear
as Available in `filtered_mentors` (no extra code if counters are correct — just
verify and add a test).

## 4. Mentor specialization mapping (§1.2)
Add a specialization editor on the mentor dashboard: a tag input writing
comma-separated tags to `mentors.specializations` via `POST /mentor/api/set-specializations`
(validate/normalize with `matching.normalize_tags`). Admin mentor view can also edit
this. These tags feed the Phase-03 skill match.

## 5. Mentor profile / "avatar customization" (§1 post-approve)
Lightweight: let an approved mentor set a display name/headline/bio and (reuse the
existing photo upload from partner apply) an avatar. Store headline/bio in
`mentors.extra_fields` JSON (column already exists) to avoid new columns. Endpoint
`POST /mentor/api/update-profile`. Respect privacy (no contact fields exposed to students).

## 6. Invoice / payment generation (§5)
Add `app/services/invoice.py` (`from __future__ import annotations`):
- `def build_invoice(payment_id) -> dict` — assemble line items (plan, sessions,
  platform fee breakdown from `matching.fee_breakdown`, totals) from existing
  `payments` + `plans` data. No PDF library required; render an HTML invoice template
  `templates/invoice.html` the student can print to PDF from the browser.
- Route `GET /student/invoice/{payment_id}` (student owns it) and
  `GET /admin/invoice/{payment_id}` (admin any). Show amounts in ₹ (paise/100).

## Acceptance
- Assigning/detaching mentors keeps `active_mentees` correct; `recount` fixes drift.
- Completing a full bundle frees mentor capacity and the mentor reappears as Available.
- Mentor can set specializations + profile; tags change student match ordering.
- Student/admin can open a printable invoice for a payment.
- `python -c "import app.main"` imports.

Touch: `app/services/{capacity,invoice}.py` (new), `app/routes/{student,mentor,admin}.py`,
`templates/invoice.html` (new), mentor dashboard + admin mentor templates.
