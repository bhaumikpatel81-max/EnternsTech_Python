-- ============================================================
-- Migration 010 — Admin audit trail  [G13]
--
-- Every significant admin action is logged here.
-- Append-only: never DELETE from this table.
-- ============================================================

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_user_id  INT             NOT NULL,
    action         VARCHAR(100)    NOT NULL,
    target_table   VARCHAR(50)     NULL,
    target_id      INT             NULL,
    notes          TEXT            NULL,
    ip             VARCHAR(45)     NULL,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_aal_admin   (admin_user_id),
    KEY idx_aal_created (created_at),
    KEY idx_aal_action  (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
