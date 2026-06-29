-- ============================================================
-- Migration 007 — Security foundation
--   • rate_limits table  (G1 — DB-backed rate limiter)
-- Safe to re-run (CREATE TABLE IF NOT EXISTS).
-- Usage: mysql -u <user> -p <db> < migrations/007_security.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS rate_limits (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    key_email    VARCHAR(255)    NOT NULL,
    key_ip       VARCHAR(45)     NOT NULL,          -- IPv4 (15) or IPv6 (45)
    action       VARCHAR(50)     NOT NULL,          -- 'login' | 'forgot'
    attempted_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Covering index satisfies the COUNT(*) query entirely from the index
    KEY idx_rl_lookup (key_email, key_ip, action, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
