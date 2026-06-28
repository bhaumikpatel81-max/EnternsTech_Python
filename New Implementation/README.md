# EnternsTech Python Portal — Claude Code Handoff Package

This folder is your VS Code → Claude Code handoff. It implements the approved
mentor/mentee workflow gaps and redesigns the landing page.

## How to use
1. Open the project in VS Code with Claude Code.
2. **Paste `00_SHARED_CONTEXT.md` once** at the start of each Claude Code session.
3. Then paste **one phase file at a time**, in order. Commit + test after each.
4. After every phase, Claude Code should run `python -c "import app.main"` and list
   changed files. Don't start the next phase until the current one imports cleanly.

## Phase order (dependencies matter)
| # | File | What it adds | Depends on |
|---|------|--------------|-----------|
| 01 | `01_PHASE_migration.md` | DB migration 006: escrow, reviews, cancellations tables + new columns + state enums | — |
| 02 | `02_PHASE_timezone.md` | Store mentee/mentor IANA tz, convert with `zoneinfo`, display local | 01 |
| 03 | `03_PHASE_matching.md` | Skill filtering, capacity gate, ±7.5% fee split, Jitsi meeting link, bundle booking, dual delivery | 01,02 |
| 04 | `04_PHASE_escrow.md` | Real escrow ledger + state machine Locked→Partial→Full→Refunded | 01,03 |
| 05 | `05_PHASE_cancellation.md` | Cancel / no-show / reschedule + SLA offense rules + escrow refunds | 01,04 |
| 06 | `06_PHASE_reviews.md` | Double-blind review gate, time-release after N days, **admin-only** visibility | 01 |
| 07 | `07_PHASE_lifecycle.md` | Capacity counters, slot release, marketplace reactivation, mentor specialization/profile, invoice | 01–06 |
| 08 | `08_PHASE_landing.md` | Landing-page redesign matching the design export (Jinja2+CSS, no framework) | none (frontend) |

Phase 08 is independent — you can do it first if you want a quick visual win.

## Confirmed decisions baked into the prompts
- **Meeting link:** Jitsi (`https://meet.jit.si/enterns-<id>-<random>`), privacy-safe,
  no external API/account.
- **Escrow:** real ledger + state machine, milestone release per completed session.
- **Timezone:** persist UTC, store mentee tz, convert in Python (`zoneinfo` + `tzdata`).
- **Reviews:** full double-blind; released ratings **visible to admin only**.
- **Migrations:** add `006_workflow.sql` + idempotent runner `scripts/migrate_006.py`.

## One thing to confirm before Phase 03
The diagram says "Mentee +7.5% / Mentor −7.5%". The prompt implements exactly that
via `PLATFORM_FEE_MENTEE_PCT`/`PLATFORM_FEE_MENTOR_PCT` in `config.py`. If your real
commercial split differs, change those two constants — no other code changes needed.

## Gap summary (what was missing in the Python code vs the workflow)
- §2 targeted skill filtering + capacity gate — **was missing** (showed all mentors).
- §3 timezone conversion, fee split, escrow lock, meeting link generation — **missing**.
- §4 cancellation / no-show / reschedule / SLA — **entirely missing**.
- §5 milestone escrow release, double-blind reviews, slot release, capacity
  reactivation, invoice — **missing** (a `feedback` table existed but was unused).
- State machines for escrow/review/booking — **not modeled**.
- Mentor specialization tags + profile/avatar — **partial** (free-text only).
