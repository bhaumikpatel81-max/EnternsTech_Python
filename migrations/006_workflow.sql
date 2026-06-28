-- ============================================================
-- Migration 006 — Workflow foundation (escrow, reviews,
-- cancellations, new columns on sessions / students / mentors).
--
-- All statements are safe to re-run.  The Python runner
-- (scripts/migrate_006.py) catches MySQL errors 1050/1060/1061
-- (table exists, duplicate column, duplicate key) and skips them.
--
-- Target: MySQL 5.7/8 on Bluehost.  No DELIMITER / stored procs.
-- Usage:  python scripts/migrate_006.py
-- ============================================================

-- ── sessions: add workflow columns ───────────────────────────
-- booked_by, topic, meeting_link already present on existing DBs
-- from migration 005; runner catches error 1060 and skips them.
ALTER TABLE sessions ADD COLUMN booked_by     VARCHAR(20)     NULL;
ALTER TABLE sessions ADD COLUMN topic         VARCHAR(255)    NULL;
ALTER TABLE sessions ADD COLUMN meeting_link  VARCHAR(500)    NULL;
ALTER TABLE sessions ADD COLUMN mentee_tz     VARCHAR(64)     NULL;
ALTER TABLE sessions ADD COLUMN bundle_id     BIGINT UNSIGNED NULL;
ALTER TABLE sessions ADD COLUMN cancel_reason VARCHAR(255)    NULL;
ALTER TABLE sessions ADD COLUMN cancelled_by  VARCHAR(20)     NULL;
ALTER TABLE sessions ADD COLUMN no_show_by    VARCHAR(20)     NULL;

-- Extend status ENUM — keeps legacy 'planned'; MODIFY is idempotent.
ALTER TABLE sessions
  MODIFY COLUMN status
  ENUM('pending','planned','scheduled','confirmed','active',
       'completed','cancelled','no_show','rescheduled')
  NOT NULL DEFAULT 'scheduled';

-- ── students: new columns ────────────────────────────────────
ALTER TABLE students ADD COLUMN timezone   VARCHAR(64)  NOT NULL DEFAULT 'Asia/Kolkata';
ALTER TABLE students ADD COLUMN skill_tags VARCHAR(500) NULL;

-- ── mentors: new columns ─────────────────────────────────────
ALTER TABLE mentors ADD COLUMN timezone        VARCHAR(64)       NOT NULL DEFAULT 'Asia/Kolkata';
ALTER TABLE mentors ADD COLUMN specializations VARCHAR(500)      NULL;
ALTER TABLE mentors ADD COLUMN capacity        SMALLINT UNSIGNED NOT NULL DEFAULT 5;
ALTER TABLE mentors ADD COLUMN active_mentees  SMALLINT UNSIGNED NOT NULL DEFAULT 0;

-- ── escrow ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS escrow (
  id                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  student_id        BIGINT UNSIGNED   NOT NULL,
  mentor_id         BIGINT UNSIGNED   NOT NULL,
  bundle_id         BIGINT UNSIGNED   NULL,
  payment_id        BIGINT UNSIGNED   NULL,
  total_paise       BIGINT            NOT NULL,
  released_paise    BIGINT            NOT NULL DEFAULT 0,
  sessions_total    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  sessions_released SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  state             ENUM('locked','partially_released','fully_released','refunded')
                    NOT NULL DEFAULT 'locked',
  created_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_escrow_student (student_id),
  KEY idx_escrow_mentor  (mentor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── escrow_ledger ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS escrow_ledger (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  escrow_id    BIGINT UNSIGNED NOT NULL,
  session_id   BIGINT UNSIGNED NULL,
  direction    ENUM('lock','release','refund') NOT NULL,
  amount_paise BIGINT          NOT NULL,
  note         VARCHAR(255)    NULL,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ledger_escrow (escrow_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── reviews ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reviews (
  id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  session_id   BIGINT UNSIGNED  NOT NULL,
  author_role  ENUM('student','mentor') NOT NULL,
  rating       TINYINT UNSIGNED NOT NULL,
  comment      TEXT             NULL,
  state        ENUM('pending','hidden','released') NOT NULL DEFAULT 'pending',
  submitted_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  released_at  DATETIME         NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_review (session_id, author_role),
  KEY idx_review_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── cancellations ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cancellations (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id BIGINT UNSIGNED NOT NULL,
  actor_role ENUM('student','mentor','admin','system') NOT NULL,
  kind       ENUM('cancel','no_show','reschedule')     NOT NULL,
  reason     VARCHAR(255)    NULL,
  within_sla TINYINT(1)      NOT NULL DEFAULT 1,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cancel_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
