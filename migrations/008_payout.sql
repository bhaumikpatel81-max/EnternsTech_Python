-- ============================================================
-- Migration 008 — Payout tracking + concurrency-safe booking
--
--  G8  : mentor_payouts table; payout_id FK on escrow
--  G10 : generated virtual column + unique index on sessions
--        prevents two active bookings for the same mentor+slot
--        at the database level (application SELECT-FOR-UPDATE
--        is the primary guard; this is the safety net).
--
-- NOTE: The slot_key UNIQUE index will fail if existing rows
--       already have duplicate active sessions for the same
--       mentor+slot.  Fix any such rows before applying.
--
-- Safe to re-run only if all ALTER TABLE steps are idempotent
-- on your MySQL version.  Apply once per database.
-- Usage: mysql -u <user> -p <db> < migrations/008_payout.sql
-- ============================================================

-- ── G8: payout records ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mentor_payouts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mentor_id    INT             NOT NULL,
    amount_paise BIGINT          NOT NULL,
    notes        VARCHAR(500)    NOT NULL DEFAULT '',
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY idx_mp_mentor (mentor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link each paid-out escrow row back to its payout record
ALTER TABLE escrow
    ADD COLUMN payout_id BIGINT UNSIGNED NULL DEFAULT NULL;

-- ── G10: unique active-slot constraint ────────────────────────
-- slot_key is non-NULL only for sessions that are NOT in a
-- terminal state.  The UNIQUE index on this generated column
-- prevents two active bookings for the same mentor+slot without
-- requiring a partial index (which MySQL does not support natively).
--
-- When a session is cancelled/rescheduled/no_show, MySQL sets
-- slot_key back to NULL automatically, freeing the slot.
ALTER TABLE sessions
    ADD COLUMN slot_key VARCHAR(50)
        GENERATED ALWAYS AS (
            IF(
                status NOT IN ('cancelled', 'rescheduled', 'no_show'),
                CONCAT(mentor_id, '_',
                       DATE_FORMAT(scheduled_at, '%Y%m%d%H%i')),
                NULL
            )
        ) VIRTUAL;

ALTER TABLE sessions
    ADD UNIQUE KEY uk_active_slot (slot_key);
