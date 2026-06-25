-- ============================================================
-- Migration 003 — Real admin auth + universal password reset
-- MySQL/MariaDB-safe. Re-runnable. Collation-safe.
-- ============================================================
SET NAMES utf8mb4;
SET @old_coll = @@collation_connection;
SET collation_connection = 'utf8mb4_unicode_ci';

ALTER TABLE users
  MODIFY COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL;

UPDATE users SET password_hash = NULL WHERE password_hash = '';

DROP PROCEDURE IF EXISTS et_add_col;
DELIMITER //
CREATE PROCEDURE et_add_col(
  IN p_table VARCHAR(64) CHARACTER SET utf8mb4,
  IN p_col   VARCHAR(64) CHARACTER SET utf8mb4,
  IN p_def   TEXT        CHARACTER SET utf8mb4
)
BEGIN
  DECLARE v_count INT DEFAULT 0;
  SELECT COUNT(*) INTO v_count
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = p_table COLLATE utf8mb4_unicode_ci
      AND COLUMN_NAME  = p_col   COLLATE utf8mb4_unicode_ci;
  IF v_count = 0 THEN
    SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_col, '` ', p_def);
    PREPARE stmt FROM @ddl;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END //
DELIMITER ;

CALL et_add_col('users', 'status', "ENUM('pending','active','suspended') NOT NULL DEFAULT 'active'");

DROP PROCEDURE IF EXISTS et_add_col;

CREATE TABLE IF NOT EXISTS password_tokens (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT           NOT NULL,
  token_hash  VARCHAR(255)  NOT NULL,
  purpose     ENUM('set','reset') NOT NULL DEFAULT 'set',
  used        TINYINT(1)    NOT NULL DEFAULT 0,
  expires_at  DATETIME      NOT NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user (user_id),
  KEY idx_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET collation_connection = @old_coll;