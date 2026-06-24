-- ============================================================
-- Migration 002 — Manual revenue table
-- Run after 001_schema.sql on existing databases.
-- Razorpay payments are tracked in the existing `payments` table.
-- ============================================================

SET NAMES utf8mb4;

-- ── app_settings (generic key-value store replacing wp_options) ──────────────
CREATE TABLE IF NOT EXISTS app_settings (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(100)  NOT NULL,
  setting_val LONGTEXT      NOT NULL DEFAULT '',
  updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── manual_revenue (non-transactional / offline income entries) ───────────────
CREATE TABLE IF NOT EXISTS manual_revenue (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entry_date  DATE          NOT NULL,
  amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency    VARCHAR(5)    NOT NULL DEFAULT 'INR',
  category    VARCHAR(50)   NOT NULL DEFAULT 'other',
  description TEXT,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_entry_date (entry_date),
  KEY idx_category   (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
