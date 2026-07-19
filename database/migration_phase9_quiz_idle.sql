-- Phase 9: quiz idle auto-close activity tracking
SET NAMES utf8mb4;

SET @db = DATABASE();

SELECT COUNT(*) INTO @col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'room_bonus_quizzes' AND COLUMN_NAME = 'last_activity_at';
SET @sql = IF(@col = 0,
  'ALTER TABLE room_bonus_quizzes ADD COLUMN last_activity_at TIMESTAMP NULL DEFAULT NULL COMMENT ''กิจกรรมล่าสุด (พิมพ์/คลิก) สำหรับ idle auto-close'' AFTER started_at',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE room_bonus_quizzes SET last_activity_at = started_at WHERE last_activity_at IS NULL;
