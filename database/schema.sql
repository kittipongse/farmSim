-- FarmSim EDU Database Schema
-- MySQL 8+ | utf8mb4_unicode_ci
-- ติดตั้งใหม่: database\install-fresh.bat หรือ import farmsim_edu.sql
-- Database: cp393722_farmsim

CREATE DATABASE IF NOT EXISTS cp393722_farmsim
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE cp393722_farmsim;

-- ---------------------------------------------------------------------------
-- Master data
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS countries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(10) NOT NULL UNIQUE,
  name_th VARCHAR(100) NOT NULL,
  name_en VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS country_regions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  country_id INT UNSIGNED NOT NULL,
  code VARCHAR(50) NOT NULL,
  name_th VARCHAR(100) NOT NULL,
  name_en VARCHAR(100) NOT NULL,
  default_coins INT NOT NULL DEFAULT 500,
  default_water TINYINT UNSIGNED NOT NULL DEFAULT 70,
  default_soil_quality TINYINT UNSIGNED NOT NULL DEFAULT 70,
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (country_id) REFERENCES countries(id),
  UNIQUE KEY uk_country_code (country_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crops (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  country_id INT UNSIGNED NOT NULL,
  code VARCHAR(50) NOT NULL,
  name_th VARCHAR(100) NOT NULL,
  name_en VARCHAR(100) NOT NULL,
  category ENUM('field_crop','vegetable','fruit','herb','economic') NOT NULL,
  growth_months TINYINT UNSIGNED NOT NULL DEFAULT 4,
  crop_category ENUM('short','medium','long') NOT NULL DEFAULT 'medium',
  base_coin INT UNSIGNED NOT NULL DEFAULT 100,
  base_buy_price INT NOT NULL DEFAULT 15,
  base_sell_price INT NOT NULL DEFAULT 30,
  capability_bonus TINYINT UNSIGNED NOT NULL DEFAULT 2,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (country_id) REFERENCES countries(id),
  UNIQUE KEY uk_crop_country_code (country_id, code),
  CONSTRAINT chk_crops_growth CHECK (growth_months BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crop_region_rates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  crop_id INT UNSIGNED NOT NULL,
  region_id INT UNSIGNED NOT NULL,
  suitability_level ENUM('excellent','good','moderate','poor','unsuitable') NOT NULL,
  coin_multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_crop_region (crop_id, region_id),
  FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE CASCADE,
  FOREIGN KEY (region_id) REFERENCES country_regions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agricultural_seasons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  country_id INT UNSIGNED NOT NULL,
  code VARCHAR(30) NOT NULL,
  name_th VARCHAR(100) NOT NULL,
  name_en VARCHAR(100) NOT NULL,
  description_th VARCHAR(255) NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE,
  UNIQUE KEY uk_season_country_code (country_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agricultural_season_months (
  season_id INT UNSIGNED NOT NULL,
  month TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (season_id, month),
  FOREIGN KEY (season_id) REFERENCES agricultural_seasons(id) ON DELETE CASCADE,
  CONSTRAINT chk_asm_month CHECK (month BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crop_seasons (
  crop_id INT UNSIGNED NOT NULL,
  season_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (crop_id, season_id),
  FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE CASCADE,
  FOREIGN KEY (season_id) REFERENCES agricultural_seasons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cards (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name_th VARCHAR(100) NOT NULL,
  name_en VARCHAR(100) NOT NULL,
  description TEXT NULL,
  sprite_index TINYINT UNSIGNED NULL COMMENT 'cardgame.png sprite 0-7 for decision cards',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS breaking_news_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  country_id INT UNSIGNED NULL,
  region_id INT UNSIGNED NULL,
  code VARCHAR(50) NOT NULL UNIQUE,
  name_th VARCHAR(150) NOT NULL,
  name_en VARCHAR(150) NOT NULL,
  event_type ENUM('disaster', 'government_policy') NOT NULL,
  sprite_index TINYINT UNSIGNED NULL COMMENT 'cardgame.png sprite 8-15 for events',
  capability_penalty_min TINYINT NOT NULL DEFAULT 10,
  capability_penalty_max TINYINT NOT NULL DEFAULT 25,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE SET NULL,
  FOREIGN KEY (region_id) REFERENCES country_regions(id) ON DELETE SET NULL,
  KEY idx_bnt_country (country_id),
  KEY idx_bnt_region (region_id),
  KEY idx_bnt_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Game session
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS game_rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_code VARCHAR(10) NOT NULL UNIQUE,
  pin VARCHAR(6) NOT NULL,
  country_id INT UNSIGNED NOT NULL,
  status ENUM(
    'lobby', 'countdown', 'planning', 'simulating', 'finished', 'cancelled'
  ) NOT NULL DEFAULT 'lobby',
  lobby_started_at TIMESTAMP NULL,
  countdown_started_at TIMESTAMP NULL,
  current_year TINYINT UNSIGNED NOT NULL DEFAULT 1,
  current_month TINYINT UNSIGNED NOT NULL DEFAULT 1,
  player_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  simulation_started_at TIMESTAMP NULL COMMENT 'เมื่อเริ่มจำลองปีปัจจุบัน',
  simulation_pause_until TIMESTAMP NULL COMMENT 'รอจนกว่าจะ advance เดือนถัดไป',
  breaking_news_until TIMESTAMP NULL COMMENT 'แสดง Breaking News จนถึงเวลานี้',
  month_timer_remaining SMALLINT UNSIGNED NULL COMMENT 'วินาทีนับถอยหลังที่หยุดไว้ระหว่าง Breaking News',
  current_event_id INT UNSIGNED NULL COMMENT 'Breaking News ที่กำลังแสดง',
  current_bonus_quiz_id INT UNSIGNED NULL COMMENT 'โบนัสทายปัญหาที่กำลังเปิด/ประกาศผล',
  bonus_quiz_reveal_until TIMESTAMP NULL COMMENT 'แสดงรายชื่อผู้ตอบถูกจนถึงเวลานี้',
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (country_id) REFERENCES countries(id),
  KEY idx_rooms_status (status),
  KEY idx_rooms_simulation_pause (simulation_pause_until),
  CONSTRAINT chk_current_year CHECK (current_year BETWEEN 1 AND 5),
  CONSTRAINT chk_current_month CHECK (current_month BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS players (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  region_id INT UNSIGNED NULL,
  profile_image VARCHAR(255) NULL,
  agricultural_capability TINYINT UNSIGNED NOT NULL DEFAULT 100,
  session_token VARCHAR(64) NOT NULL,
  is_ready TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'เลือกภูมิภาคแล้ว',
  cards_submitted_year TINYINT UNSIGNED NULL COMMENT 'ปีที่ยืนยันแผนการ์ดแล้ว',
  plan_adjustments_used TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนครั้งที่ปรับแผนกิจกรรมระหว่างภัยพิบัติ',
  in_plan_adjustment TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'กำลังปรับแผนกิจกรรม',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES game_rooms(id) ON DELETE CASCADE,
  FOREIGN KEY (region_id) REFERENCES country_regions(id) ON DELETE SET NULL,
  UNIQUE KEY uk_room_name (room_id, name),
  KEY idx_players_room (room_id),
  KEY idx_players_cards_submitted (cards_submitted_year),
  CONSTRAINT chk_capability CHECK (agricultural_capability BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_resources (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  player_id INT UNSIGNED NOT NULL UNIQUE,
  coins INT NOT NULL DEFAULT 500,
  workforce TINYINT UNSIGNED NOT NULL DEFAULT 10,
  water TINYINT UNSIGNED NOT NULL DEFAULT 70,
  soil_quality TINYINT UNSIGNED NOT NULL DEFAULT 70,
  tech_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
  stock_amount INT NOT NULL DEFAULT 0,
  sustainability TINYINT UNSIGNED NOT NULL DEFAULT 50,
  env_impact TINYINT UNSIGNED NOT NULL DEFAULT 30,
  knowledge_score INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  CONSTRAINT chk_workforce CHECK (workforce BETWEEN 0 AND 20),
  CONSTRAINT chk_tech_level CHECK (tech_level BETWEEN 0 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Phase 2: Planning & simulation
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS player_year_cards (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  player_id INT UNSIGNED NOT NULL,
  year TINYINT UNSIGNED NOT NULL,
  card_code VARCHAR(20) NOT NULL,
  month TINYINT UNSIGNED NOT NULL,
  crop_name VARCHAR(100) NULL COMMENT 'ใช้กับการ์ด PLANT',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  UNIQUE KEY uk_player_year_month (player_id, year, month),
  KEY idx_pyc_player_year (player_id, year),
  CONSTRAINT chk_pyc_month CHECK (month BETWEEN 1 AND 12),
  CONSTRAINT chk_pyc_year CHECK (year BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_crop_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  player_id INT UNSIGNED NOT NULL,
  year TINYINT UNSIGNED NOT NULL,
  crop_id INT UNSIGNED NULL,
  crop_name VARCHAR(100) NOT NULL,
  input_crop_name VARCHAR(100) NULL COMMENT 'ชื่อที่ผู้เล่นพิมพ์',
  plant_month TINYINT UNSIGNED NOT NULL,
  harvest_month TINYINT UNSIGNED NOT NULL,
  growth_months TINYINT UNSIGNED NOT NULL,
  region_match TINYINT(1) NOT NULL DEFAULT 1,
  mismatch_reason VARCHAR(30) NULL COMMENT 'wrong_region | unknown_crop',
  season_match TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('planned', 'growing', 'harvested', 'failed') NOT NULL DEFAULT 'planned',
  yield_amount INT NOT NULL DEFAULT 0,
  sold TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE SET NULL,
  KEY idx_pcp_player_year (player_id, year),
  KEY idx_pcp_status (status),
  CONSTRAINT chk_pcp_year CHECK (year BETWEEN 1 AND 5),
  CONSTRAINT chk_pcp_plant_month CHECK (plant_month BETWEEN 1 AND 12),
  CONSTRAINT chk_pcp_harvest_month CHECK (harvest_month BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS room_year_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  template_id INT UNSIGNED NOT NULL,
  year TINYINT UNSIGNED NOT NULL,
  month TINYINT UNSIGNED NOT NULL,
  event_type ENUM('disaster', 'government_policy') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES game_rooms(id) ON DELETE CASCADE,
  FOREIGN KEY (template_id) REFERENCES breaking_news_templates(id),
  UNIQUE KEY uk_room_year_month (room_id, year, month),
  KEY idx_rye_room_year (room_id, year),
  CONSTRAINT chk_rye_month CHECK (month BETWEEN 1 AND 12),
  CONSTRAINT chk_rye_year CHECK (year BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_event_responses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  player_id INT UNSIGNED NOT NULL,
  event_id INT UNSIGNED NOT NULL,
  response_type VARCHAR(50) NOT NULL,
  handled_well TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (event_id) REFERENCES room_year_events(id) ON DELETE CASCADE,
  UNIQUE KEY uk_player_event (player_id, event_id),
  KEY idx_per_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Market & scoring
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS market_prices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  crop_id INT UNSIGNED NOT NULL,
  buy_price DECIMAL(10,2) NOT NULL,
  sell_price DECIMAL(10,2) NOT NULL,
  supply INT NOT NULL DEFAULT 0,
  demand INT NOT NULL DEFAULT 100,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES game_rooms(id) ON DELETE CASCADE,
  FOREIGN KEY (crop_id) REFERENCES crops(id),
  UNIQUE KEY uk_room_crop (room_id, crop_id),
  KEY idx_market_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS market_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  player_id INT UNSIGNED NOT NULL,
  crop_id INT UNSIGNED NULL,
  type ENUM('buy', 'sell') NOT NULL,
  amount INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  year TINYINT UNSIGNED NOT NULL DEFAULT 1,
  month TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES game_rooms(id) ON DELETE CASCADE,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE SET NULL,
  KEY idx_mt_room_year_month (room_id, year, month),
  KEY idx_mt_player (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_scores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  player_id INT UNSIGNED NOT NULL,
  room_id INT UNSIGNED NOT NULL,
  year TINYINT UNSIGNED NULL COMMENT 'NULL = สรุปทั้งเกม',
  production_score INT NOT NULL DEFAULT 0,
  resource_score INT NOT NULL DEFAULT 0,
  technology_score INT NOT NULL DEFAULT 0,
  sustainability_score INT NOT NULL DEFAULT 0,
  risk_score INT NOT NULL DEFAULT 0,
  knowledge_score INT NOT NULL DEFAULT 0,
  env_score INT NOT NULL DEFAULT 0,
  capability_score INT NOT NULL DEFAULT 0,
  total_score INT NOT NULL DEFAULT 0,
  rank_position TINYINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (room_id) REFERENCES game_rooms(id) ON DELETE CASCADE,
  UNIQUE KEY uk_player_room_year (player_id, room_id, year),
  KEY idx_scores_room_year (room_id, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  player_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  detail TEXT NULL COMMENT 'JSON string',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES game_rooms(id) ON DELETE CASCADE,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL,
  KEY idx_logs_room_time (room_id, created_at),
  KEY idx_logs_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Deferred foreign keys (หลังสร้าง room_year_events)
-- ---------------------------------------------------------------------------

-- ลบ FK เดิมถ้ามี (กรณีรัน schema ซ้ำบน DB ที่มี constraint แล้ว)
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'game_rooms'
    AND CONSTRAINT_NAME = 'fk_game_rooms_current_event'
);

SET @sql = IF(
  @fk_exists > 0,
  'SELECT 1',
  'ALTER TABLE game_rooms ADD CONSTRAINT fk_game_rooms_current_event FOREIGN KEY (current_event_id) REFERENCES room_year_events(id) ON DELETE SET NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
