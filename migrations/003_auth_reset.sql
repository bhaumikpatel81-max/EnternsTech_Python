-- ============================================================
-- Migration 003 — Real admin auth + universal password reset
-- Run after 002_transactions_revenue.sql
-- ============================================================

SET NAMES utf8mb4;

-- Allow NULL password_hash (new users before they set a password have no hash)
ALTER TABLE users
  MODIFY COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL;

-- Set existing empty-string hashes to NULL
UPDATE users SET password_hash = NULL WHERE password_hash = '';

-- Account lifecycle status (pending = hash not yet set, active = normal, suspended = blocked)
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS status ENUM('pending','active','suspended') NOT NULL DEFAULT 'active';

-- Single-use expiring tokens for set-password and forgot-password flows
CREATE TABLE IF NOT EXISTS password_tokens (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  token_hash  VARCHAR(255)  NOT NULL,   -- sha256 of the raw token; raw token is never stored
  purpose     ENUM('set','reset') NOT NULL DEFAULT 'set',
  used        TINYINT(1)    NOT NULL DEFAULT 0,
  expires_at  DATETIME      NOT NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user   (user_id),
  KEY idx_hash   (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
