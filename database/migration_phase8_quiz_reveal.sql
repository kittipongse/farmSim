-- Phase 8: show correct-answer winners for 5s after bonus quiz (pause month timer)
SET NAMES utf8mb4;
SET @db = DATABASE();

SELECT COUNT(*) INTO @col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'game_rooms' AND COLUMN_NAME = 'bonus_quiz_reveal_until';
SET @sql = IF(@col = 0,
  'ALTER TABLE game_rooms ADD COLUMN bonus_quiz_reveal_until TIMESTAMP NULL DEFAULT NULL COMMENT ''แสดงรายชื่อผู้ตอบถูกจนถึงเวลานี้'' AFTER current_bonus_quiz_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE room_bonus_quizzes
  MODIFY COLUMN status ENUM('active','revealing','closed') NOT NULL DEFAULT 'active';
