-- ============================================================
-- Migration 004 — DB-driven plan catalog, combos & discounts
-- Run after 003_auth_reset.sql
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS plans (
  id           VARCHAR(32)   NOT NULL,
  name         VARCHAR(64)   NOT NULL DEFAULT '',
  tagline      VARCHAR(255)  NOT NULL DEFAULT '',
  price_intl   VARCHAR(32)   NOT NULL DEFAULT '',   -- display string e.g. "$2,500"
  price_dom    VARCHAR(32)   NOT NULL DEFAULT '',   -- display string e.g. "₹1,50,000"
  paise        BIGINT        NOT NULL DEFAULT 0,    -- authoritative INR in paise
  cents        BIGINT        NOT NULL DEFAULT 0,    -- authoritative USD in cents
  alt_intl     VARCHAR(64)   NOT NULL DEFAULT '',
  alt_dom      VARCHAR(64)   NOT NULL DEFAULT '',
  note         VARCHAR(128)  NOT NULL DEFAULT '',
  duration     VARCHAR(128)  NOT NULL DEFAULT '',
  sessions     INT           NOT NULL DEFAULT 4,
  badge        VARCHAR(32)   NOT NULL DEFAULT '',
  featured     TINYINT(1)    NOT NULL DEFAULT 0,
  active       TINYINT(1)    NOT NULL DEFAULT 1,
  sort_order   INT           NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_features (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id    VARCHAR(32)  NOT NULL,
  feature    VARCHAR(255) NOT NULL DEFAULT '',
  sort_order INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_plan_id (plan_id),
  FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS combos (
  id           VARCHAR(32)  NOT NULL,
  name         VARCHAR(64)  NOT NULL DEFAULT '',
  plans        VARCHAR(64)  NOT NULL DEFAULT '',   -- comma-separated plan ids
  price_intl   VARCHAR(32)  NOT NULL DEFAULT '',
  price_dom    VARCHAR(32)  NOT NULL DEFAULT '',
  paise        BIGINT       NOT NULL DEFAULT 0,
  cents        BIGINT       NOT NULL DEFAULT 0,
  note         VARCHAR(128) NOT NULL DEFAULT '',
  description  TEXT,
  active       TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order   INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discounts (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code        VARCHAR(40)  DEFAULT NULL,          -- NULL = automatic (no code needed)
  label       VARCHAR(128) NOT NULL DEFAULT '',
  kind        ENUM('percent','flat') NOT NULL DEFAULT 'percent',
  value       DECIMAL(10,2) NOT NULL DEFAULT 0,   -- percent (0-100) or flat amount
  currency    CHAR(3)      NOT NULL DEFAULT 'INR', -- for flat discounts
  applies_to  VARCHAR(64)  NOT NULL DEFAULT 'all', -- plan id, combo id, or 'all'
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  starts_at   DATE         DEFAULT NULL,
  ends_at     DATE         DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
