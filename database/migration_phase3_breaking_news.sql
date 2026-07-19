-- Breaking news pause + plan adjustment (run once on existing DB)
SET NAMES utf8mb4;

SET @db = DATABASE();

SELECT COUNT(*) INTO @col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'game_rooms' AND COLUMN_NAME = 'breaking_news_until';
SET @sql = IF(@col = 0,
  'ALTER TABLE game_rooms ADD COLUMN breaking_news_until TIMESTAMP NULL DEFAULT NULL COMMENT ''แสดง Breaking News จนถึงเวลานี้'' AFTER simulation_pause_until',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'game_rooms' AND COLUMN_NAME = 'month_timer_remaining';
SET @sql = IF(@col = 0,
  'ALTER TABLE game_rooms ADD COLUMN month_timer_remaining SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT ''วินาทีนับถอยหลังเดือนที่หยุดไว้ระหว่าง Breaking News'' AFTER breaking_news_until',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'players' AND COLUMN_NAME = 'plan_adjustments_used';
SET @sql = IF(@col = 0,
  'ALTER TABLE players ADD COLUMN plan_adjustments_used TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''จำนวนครั้งที่ปรับแผนกิจกรรมระหว่างภัยพิบัติ'' AFTER cards_submitted_year',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'players' AND COLUMN_NAME = 'in_plan_adjustment';
SET @sql = IF(@col = 0,
  'ALTER TABLE players ADD COLUMN in_plan_adjustment TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''กำลังปรับแผนกิจกรรม'' AFTER plan_adjustments_used',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
