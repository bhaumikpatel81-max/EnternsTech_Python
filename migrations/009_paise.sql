-- ============================================================
-- Migration 009 — Convert payments.amount from rupees (FLOAT)
--                 to paise (BIGINT)  [G12]
--
-- !!  BACKUP FIRST  !!
-- ---------------------------------------------------------------
--   mysqldump -u <user> -p <db> payments > payments_pre_009.sql
-- ---------------------------------------------------------------
-- DO NOT run this until the dump is verified.
--
-- The original float values are preserved in amount_rupees_backup
-- so the migration can be manually reversed if needed.
--
-- Safe to inspect after each step:
--   SELECT id, amount_rupees_backup, amount FROM payments LIMIT 10;
-- ============================================================

-- Step 1: snapshot existing values
ALTER TABLE payments
    ADD COLUMN amount_rupees_backup DECIMAL(10,2) NULL DEFAULT NULL;

UPDATE payments SET amount_rupees_backup = amount;

-- Step 2: convert column to paise (rupees × 100, rounded to nearest integer)
UPDATE payments SET amount = ROUND(COALESCE(amount, 0) * 100);

ALTER TABLE payments
    MODIFY COLUMN amount BIGINT NOT NULL DEFAULT 0;

-- Verify (run manually and check output before going live):
-- SELECT id, amount_rupees_backup, amount,
--        ROUND(amount_rupees_backup * 100) AS expected_paise,
--        ABS(amount - ROUND(amount_rupees_backup * 100)) AS drift
-- FROM payments
-- WHERE ABS(amount - ROUND(amount_rupees_backup * 100)) > 1
-- LIMIT 20;
