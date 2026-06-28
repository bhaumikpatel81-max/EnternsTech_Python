# PHASE 01 — Database migration 006 (foundation for all later phases)

**Paste 00_SHARED_CONTEXT.md first if this is a new session.**

Goal: add every schema change the workflow needs, in ONE idempotent migration,
so phases 02–08 have their tables/columns ready. Do not write feature logic yet.

## Create `migrations/006_workflow.sql`
Make all statements safe to re-run on MySQL 5.7/8 (Bluehost). Use
`CREATE TABLE IF NOT EXISTS`. For `ALTER TABLE ADD COLUMN`, wrap each in a guard
(prepared statement checking `INFORMATION_SCHEMA.COLUMNS`) OR put column adds in
the Python runner described below and keep the .sql for tables/indexes only —
your call, but it MUST NOT crash if a column already exists.

### 1. `sessions` — add columns (idempotent)
- `meeting_url        VARCHAR(255) NULL`
- `booked_by          VARCHAR(20)  NULL`      (verify; code already writes it)
- `topic              VARCHAR(255) NULL`      (verify; code already writes it)
- `mentee_tz          VARCHAR(64)  NULL`      (IANA tz captured at booking)
- `bundle_id          BIGINT UNSIGNED NULL`   (groups bundle sessions)
- `cancel_reason      VARCHAR(255) NULL`
- `cancelled_by       VARCHAR(20)  NULL`      ('student'|'mentor'|'admin'|'system')
- `no_show_by         VARCHAR(20)  NULL`
- Extend `status` ENUM to:
  `('pending','scheduled','confirmed','active','completed','cancelled','no_show','rescheduled')`
  (keep legacy 'planned' value to avoid breaking old rows — include it in the enum).

### 2. `students` — add columns
- `timezone  VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata'`
- `skill_tags VARCHAR(500) NULL`  (normalized, comma-separated, for matching)

### 3. `mentors` — add columns
- `timezone        VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata'`
- `specializations VARCHAR(500) NULL`  (comma-separated domain tags)
- `capacity        SMALLINT UNSIGNED NOT NULL DEFAULT 5`  (max active mentees)
- `active_mentees  SMALLINT UNSIGNED NOT NULL DEFAULT 0`  (denormalized count)

### 4. New table `escrow`  (one row per booking/bundle)
```
id              BIGINT UNSIGNED AUTO_INCREMENT PK
student_id      BIGINT UNSIGNED NOT NULL
mentor_id       BIGINT UNSIGNED NOT NULL
bundle_id       BIGINT UNSIGNED NULL
payment_id      BIGINT UNSIGNED NULL
total_paise     BIGINT NOT NULL              -- mentor's earnable portion (after fee)
released_paise  BIGINT NOT NULL DEFAULT 0
sessions_total  SMALLINT UNSIGNED NOT NULL DEFAULT 1
sessions_released SMALLINT UNSIGNED NOT NULL DEFAULT 0
state           ENUM('locked','partially_released','fully_released','refunded') NOT NULL DEFAULT 'locked'
created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
KEY idx_escrow_student (student_id), KEY idx_escrow_mentor (mentor_id)
```

### 5. New table `escrow_ledger`  (audit trail of every movement)
```
id          BIGINT UNSIGNED AUTO_INCREMENT PK
escrow_id   BIGINT UNSIGNED NOT NULL
session_id  BIGINT UNSIGNED NULL
direction   ENUM('lock','release','refund') NOT NULL
amount_paise BIGINT NOT NULL
note        VARCHAR(255) NULL
created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
KEY idx_ledger_escrow (escrow_id)
```

### 6. New table `reviews`  (double-blind, admin-only visibility)
```
id            BIGINT UNSIGNED AUTO_INCREMENT PK
session_id    BIGINT UNSIGNED NOT NULL
author_role   ENUM('student','mentor') NOT NULL
rating        TINYINT UNSIGNED NOT NULL         -- 1..5
comment       TEXT NULL
state         ENUM('pending','hidden','released') NOT NULL DEFAULT 'pending'
submitted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
released_at   DATETIME NULL
UNIQUE KEY uq_review (session_id, author_role)
KEY idx_review_session (session_id)
```

### 7. New table `cancellations`  (SLA / offense tracking)
```
id           BIGINT UNSIGNED AUTO_INCREMENT PK
session_id   BIGINT UNSIGNED NOT NULL
actor_role   ENUM('student','mentor','admin','system') NOT NULL
kind         ENUM('cancel','no_show','reschedule') NOT NULL
reason       VARCHAR(255) NULL
within_sla   TINYINT(1) NOT NULL DEFAULT 1     -- 0 = late/offense
created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
KEY idx_cancel_session (session_id)
```

## Also create `scripts/migrate_006.py`
A tiny runner that:
- Reads `migrations/006_workflow.sql`, splits on `;`, executes each statement via
  `app.database.execute`, and **catches + ignores** MySQL errors 1060 (duplicate
  column), 1061 (duplicate key), 1050 (table exists) so re-runs are safe.
- Prints each statement's result. Start file with `from __future__ import annotations`.

## Acceptance
- `python scripts/migrate_006.py` runs cleanly twice in a row (second run is a no-op).
- `python -c "import app.main"` still imports.
- Report the exact list of columns/tables created.

Do NOT touch routes, services, or templates in this phase.
