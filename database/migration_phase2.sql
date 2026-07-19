-- Phase 2 migration (idempotent) — รันได้ซ้ำโดยข้ามส่วนที่มีแล้ว
USE cp393722_farmsim;

SET NAMES utf8mb4;

DELIMITER //

DROP PROCEDURE IF EXISTS farmsim_migrate_phase2//

CREATE PROCEDURE farmsim_migrate_phase2()
BEGIN
  -- สถานะห้อง + cancelled
  ALTER TABLE game_rooms
    MODIFY status ENUM(
      'lobby', 'countdown', 'planning', 'simulating', 'finished', 'cancelled'
    ) NOT NULL DEFAULT 'lobby';

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_rooms' AND COLUMN_NAME = 'simulation_started_at'
  ) THEN
    ALTER TABLE game_rooms
      ADD COLUMN simulation_started_at TIMESTAMP NULL DEFAULT NULL
        COMMENT 'เมื่อเริ่มจำลองปีปัจจุบัน' AFTER player_count;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_rooms' AND COLUMN_NAME = 'simulation_pause_until'
  ) THEN
    ALTER TABLE game_rooms
      ADD COLUMN simulation_pause_until TIMESTAMP NULL DEFAULT NULL
        COMMENT 'รอจนกว่าจะ advance เดือนถัดไป' AFTER simulation_started_at;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_rooms' AND COLUMN_NAME = 'current_event_id'
  ) THEN
    ALTER TABLE game_rooms
      ADD COLUMN current_event_id INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Breaking News ที่กำลังแสดง' AFTER simulation_pause_until;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'cards_submitted_year'
  ) THEN
    ALTER TABLE players
      ADD COLUMN cards_submitted_year TINYINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'ปีที่ยืนยันแผนการ์ดแล้ว' AFTER is_ready;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cards' AND COLUMN_NAME = 'sprite_index'
  ) THEN
    ALTER TABLE cards
      ADD COLUMN sprite_index TINYINT UNSIGNED NULL
        COMMENT 'cardgame.png sprite 0-7' AFTER description;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'breaking_news_templates' AND COLUMN_NAME = 'sprite_index'
  ) THEN
    ALTER TABLE breaking_news_templates
      ADD COLUMN sprite_index TINYINT UNSIGNED NULL
        COMMENT 'cardgame.png sprite 8-15' AFTER event_type;
  END IF;
END//

DELIMITER ;

CALL farmsim_migrate_phase2();
DROP PROCEDURE IF EXISTS farmsim_migrate_phase2;

-- Indexes (รันซ้ำได้ถ้ายังไม่มี)
SET @db = DATABASE();

-- uk_player_year_card
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'player_year_cards' AND INDEX_NAME = 'uk_player_year_card');
SET @sql = IF(@exists = 0,
  'ALTER TABLE player_year_cards ADD UNIQUE KEY uk_player_year_card (player_id, year, card_code)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- uk_room_year_month
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'room_year_events' AND INDEX_NAME = 'uk_room_year_month');
SET @sql = IF(@exists = 0,
  'ALTER TABLE room_year_events ADD UNIQUE KEY uk_room_year_month (room_id, year, month)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- uk_player_event
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'player_event_responses' AND INDEX_NAME = 'uk_player_event');
SET @sql = IF(@exists = 0,
  'ALTER TABLE player_event_responses ADD UNIQUE KEY uk_player_event (player_id, event_id)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- uk_player_room_year
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'player_scores' AND INDEX_NAME = 'uk_player_room_year');
SET @sql = IF(@exists = 0,
  'ALTER TABLE player_scores ADD UNIQUE KEY uk_player_room_year (player_id, room_id, year)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- FK current_event
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'game_rooms' AND CONSTRAINT_NAME = 'fk_game_rooms_current_event');
SET @sql = IF(@exists = 0,
  'ALTER TABLE game_rooms ADD CONSTRAINT fk_game_rooms_current_event FOREIGN KEY (current_event_id) REFERENCES room_year_events(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Sprite index master data
UPDATE cards SET sprite_index = 0 WHERE code = 'PLANT' AND sprite_index IS NULL;
UPDATE cards SET sprite_index = 1 WHERE code = 'WATER' AND sprite_index IS NULL;
UPDATE cards SET sprite_index = 2 WHERE code = 'FERTILIZE' AND sprite_index IS NULL;
UPDATE cards SET sprite_index = 3 WHERE code = 'PROTECT' AND sprite_index IS NULL;
UPDATE cards SET sprite_index = 4 WHERE code = 'HARVEST' AND sprite_index IS NULL;
UPDATE cards SET sprite_index = 5 WHERE code = 'TECH' AND sprite_index IS NULL;
UPDATE cards SET sprite_index = 6 WHERE code = 'SOIL' AND sprite_index IS NULL;
UPDATE cards SET sprite_index = 7 WHERE code = 'TRADE' AND sprite_index IS NULL;

UPDATE breaking_news_templates SET sprite_index = 8  WHERE code = 'flood_th' AND sprite_index IS NULL;
UPDATE breaking_news_templates SET sprite_index = 9  WHERE code IN ('drought_isan', 'drought_plains') AND sprite_index IS NULL;
UPDATE breaking_news_templates SET sprite_index = 10 WHERE code = 'tornado' AND sprite_index IS NULL;
UPDATE breaking_news_templates SET sprite_index = 11 WHERE code = 'wildfire' AND sprite_index IS NULL;
UPDATE breaking_news_templates SET sprite_index = 12 WHERE code = 'typhoon_us' AND sprite_index IS NULL;
UPDATE breaking_news_templates SET sprite_index = 13 WHERE code = 'pest_outbreak' AND sprite_index IS NULL;
UPDATE breaking_news_templates SET sprite_index = 14 WHERE code IN ('organic_fertilizer', 'irrigation_north') AND sprite_index IS NULL;
UPDATE breaking_news_templates SET sprite_index = 15 WHERE code IN ('farm_bill', 'trade_tariff') AND sprite_index IS NULL;

SELECT 'Phase 2 migration completed' AS result;
