-- ============================================================
-- Migration 005 — Session booking. MySQL-safe, re-runnable, collation-safe.
-- ============================================================
SET NAMES utf8mb4;
SET @old_coll = @@collation_connection;
SET collation_connection = 'utf8mb4_unicode_ci';

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

CALL et_add_col('mentors',  'slots_json',   'LONGTEXT NULL');
CALL et_add_col('sessions', 'booked_by',    "ENUM('student','mentor','admin') NOT NULL DEFAULT 'admin'");
CALL et_add_col('sessions', 'topic',        "VARCHAR(255) NOT NULL DEFAULT ''");
CALL et_add_col('sessions', 'meeting_link', "VARCHAR(500) NOT NULL DEFAULT ''");

DROP PROCEDURE IF EXISTS et_add_col;

ALTER TABLE sessions
  MODIFY COLUMN status ENUM('planned','scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled';

SET collation_connection = @old_coll;