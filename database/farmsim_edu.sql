-- FarmSim EDU — Full database (Phase 2)
-- Import: phpMyAdmin (Z.com) หรือ database\import-farmsim_edu.bat (XAMPP)
-- Database: cp393722_farmsim

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- Z.com: ไม่ใช้ DROP/CREATE DATABASE (โฮสต์สร้าง DB ให้แล้ว)
-- DROP DATABASE IF EXISTS cp393722_farmsim;
-- CREATE DATABASE cp393722_farmsim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
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

CREATE TABLE IF NOT EXISTS region_crops (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  region_id INT UNSIGNED NOT NULL,
  name_th VARCHAR(100) NOT NULL,
  name_en VARCHAR(100) NOT NULL,
  growth_months TINYINT UNSIGNED NOT NULL,
  crop_category ENUM('short', 'medium', 'long') NOT NULL,
  base_buy_price INT NOT NULL DEFAULT 10,
  base_sell_price INT NOT NULL DEFAULT 20,
  capability_bonus TINYINT UNSIGNED NOT NULL DEFAULT 2,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (region_id) REFERENCES country_regions(id),
  KEY idx_region_crops_region (region_id),
  CONSTRAINT chk_growth_months CHECK (growth_months BETWEEN 1 AND 12)
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

CREATE TABLE IF NOT EXISTS region_crop_seasons (
  region_crop_id INT UNSIGNED NOT NULL,
  season_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (region_crop_id, season_id),
  FOREIGN KEY (region_crop_id) REFERENCES region_crops(id) ON DELETE CASCADE,
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
  FOREIGN KEY (crop_id) REFERENCES region_crops(id) ON DELETE SET NULL,
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
  FOREIGN KEY (crop_id) REFERENCES region_crops(id),
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
  FOREIGN KEY (crop_id) REFERENCES region_crops(id) ON DELETE SET NULL,
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


-- ---------------------------------------------------------------------------
-- Seed data
-- ---------------------------------------------------------------------------

-- Countries
INSERT INTO countries (id, code, name_th, name_en) VALUES
(1, 'TH', 'ประเทศไทย', 'Thailand'),
(2, 'US', 'สหรัฐอเมริกา', 'United States');

-- Thailand regions
INSERT INTO country_regions (id, country_id, code, name_th, name_en, default_coins, default_water, default_soil_quality, description) VALUES
(1, 1, 'north', 'เหนือ', 'North', 480, 75, 75, 'อากาศเย็น ฤดูแล้งยาว'),
(2, 1, 'central', 'กลาง', 'Central', 520, 90, 80, 'ลุ่มน้ำ ชลประทานดี'),
(3, 1, 'south', 'ใต้', 'South', 500, 85, 70, 'ฝนชุก ชื้นสูง'),
(4, 1, 'isan', 'อีสาน', 'Isan', 450, 60, 65, 'แล้ง ดินทราย');

-- USA regions
INSERT INTO country_regions (id, country_id, code, name_th, name_en, default_coins, default_water, default_soil_quality, description) VALUES
(5, 2, 'midwest', 'Midwest', 'Midwest', 550, 70, 85, 'Corn Belt'),
(6, 2, 'south', 'South', 'South', 500, 80, 75, 'อุ่นชื้น'),
(7, 2, 'west', 'West', 'West', 600, 50, 60, 'แห้งแล้ง ชลประทาน'),
(8, 2, 'great_plains', 'Great Plains', 'Great Plains', 480, 55, 80, 'ทุ่งหญ้า แล้ง');

-- Decision cards (sprite 0-7 ใน cardgame.png)
INSERT INTO cards (code, name_th, name_en, description, sprite_index) VALUES
('PLANT', 'ปลูกพืช', 'Plant Crop', 'ระบุชื่อพืช + เดือนเริ่มปลูก', 0),
('WATER', 'จัดการน้ำ', 'Water Management', 'ลงทุนระบบน้ำ/ชลประทาน', 1),
('FERTILIZE', 'ใส่ปุ๋ย', 'Fertilize', 'เพิ่มผลผลิต', 2),
('PROTECT', 'ป้องกันศัตรู', 'Protect', 'กำจัดศัตรูพืช/โรค', 3),
('HARVEST', 'เก็บเกี่ยว', 'Harvest', 'เก็บผลผลิตเข้าคลัง', 4),
('TECH', 'ลงทุนเทคโนโลยี', 'Technology', 'เครื่องจักร/เซ็นเซอร์/Drone', 5),
('SOIL', 'ปรับปรุงดิน', 'Soil Improvement', 'ปรับสภาพดิน', 6),
('TRADE', 'ขายผลผลิต', 'Trade', 'ขายผลผลิตที่ตลาดกลาง', 7);

-- Thailand crops
INSERT INTO region_crops (region_id, name_th, name_en, growth_months, crop_category, base_buy_price, base_sell_price, capability_bonus) VALUES
(1, 'ข้าว', 'Rice', 4, 'medium', 15, 30, 2),
(1, 'ข้าวโพด', 'Corn', 4, 'medium', 12, 25, 2),
(1, 'ผัก', 'Vegetables', 2, 'short', 8, 18, 1),
(1, 'ชา', 'Tea', 6, 'long', 20, 45, 3),
(2, 'ข้าว', 'Rice', 4, 'medium', 15, 30, 2),
(2, 'อ้อย', 'Sugarcane', 5, 'medium', 18, 35, 2),
(2, 'ทุเรียน', 'Durian', 6, 'long', 25, 55, 3),
(2, 'ผัก', 'Vegetables', 2, 'short', 8, 18, 1),
(3, 'ยางพารา', 'Rubber', 8, 'long', 22, 50, 3),
(3, 'ทุเรียน', 'Durian', 6, 'long', 25, 55, 3),
(3, 'สับปะรด', 'Pineapple', 3, 'short', 10, 22, 2),
(3, 'กาแฟ', 'Coffee', 6, 'long', 20, 42, 3),
(4, 'ข้าว', 'Rice', 4, 'medium', 15, 28, 2),
(4, 'มัน', 'Cassava', 5, 'medium', 12, 24, 2),
(4, 'อ้อย', 'Sugarcane', 5, 'medium', 18, 35, 2),
(4, 'ผัก', 'Vegetables', 2, 'short', 8, 18, 1);

-- USA crops
INSERT INTO region_crops (region_id, name_th, name_en, growth_months, crop_category, base_buy_price, base_sell_price, capability_bonus) VALUES
(5, 'Corn', 'Corn', 4, 'medium', 14, 28, 2),
(5, 'Soybean', 'Soybean', 4, 'medium', 16, 32, 2),
(5, 'Wheat', 'Wheat', 5, 'medium', 12, 26, 2),
(6, 'Cotton', 'Cotton', 5, 'medium', 18, 38, 2),
(6, 'Rice', 'Rice', 4, 'medium', 15, 30, 2),
(6, 'Peanuts', 'Peanuts', 3, 'short', 10, 22, 2),
(7, 'Almonds', 'Almonds', 8, 'long', 30, 65, 3),
(7, 'Grapes', 'Grapes', 5, 'medium', 20, 42, 2),
(7, 'Lettuce', 'Lettuce', 2, 'short', 8, 16, 1),
(8, 'Wheat', 'Wheat', 5, 'medium', 12, 26, 2),
(8, 'Sorghum', 'Sorghum', 4, 'medium', 11, 24, 2),
(8, 'Sunflower', 'Sunflower', 3, 'short', 9, 20, 2);

-- Breaking news (sprite 8-15 ใน cardgame.png)
INSERT INTO breaking_news_templates (
  country_id, region_id, code, name_th, name_en, event_type, sprite_index,
  capability_penalty_min, capability_penalty_max
) VALUES
(1, NULL, 'flood_th', 'น้ำท่วม', 'Flood', 'disaster', 8, 15, 20),
(1, 4, 'drought_isan', 'ภัยแล้ง', 'Drought', 'disaster', 9, 12, 18),
(1, NULL, 'pest_outbreak', 'โรคระบาดพืช', 'Crop Disease', 'disaster', 13, 10, 15),
(1, NULL, 'organic_fertilizer', 'นโยบายรัฐสนับสนุน', 'Government Support', 'government_policy', 14, 5, 10),
(1, 1, 'irrigation_north', 'โครงการชลประทานภาคเหนือ', 'Northern Irrigation Project', 'government_policy', 14, 5, 8),
(2, NULL, 'tornado', 'พายุทอร์นาโด', 'Tornado', 'disaster', 10, 18, 25),
(2, 8, 'drought_plains', 'ภัยแล้ง', 'Drought', 'disaster', 9, 15, 22),
(2, 7, 'wildfire', 'ไฟป่า', 'Wildfire', 'disaster', 11, 12, 18),
(2, NULL, 'typhoon_us', 'พายุไต้ฝุ่น', 'Typhoon', 'disaster', 12, 16, 22),
(2, NULL, 'farm_bill', 'นโยบายการค้า', 'Farm Bill', 'government_policy', 15, 5, 10),
(2, NULL, 'trade_tariff', 'นโยบายภาษีการค้า', 'Trade Tariff Policy', 'government_policy', 15, 8, 12);

-- Agricultural seasons & crop planting windows
INSERT INTO agricultural_seasons (id, country_id, code, name_th, name_en, description_th, sort_order) VALUES
(1, 1, 'rainy', 'ฤดูฝน', 'Rainy Season', 'พฤษภาคม–ตุลาคม ปริมาณน้ำฝนสูง เหมาะข้าวและพืชหลัก', 1),
(2, 1, 'cool', 'ฤดูหนาว', 'Cool Season', 'พฤศจิกายน–กุมภาพันธ์ อากาศเย็น เหมาะผักและอ้อย', 2),
(3, 1, 'hot', 'ฤดูร้อน', 'Hot Season', 'มีนาคม–เมษายน ร้อนแล้ง เตรียมแปลงและพืชบางชนิด', 3),
(4, 2, 'spring', 'ฤดูใบไม้ผลิ', 'Spring', 'มีนาคม–พฤษภาคม เริ่มปลูกพืชหลัก', 1),
(5, 2, 'summer', 'ฤดูร้อน', 'Summer', 'มิถุนายน–สิงหาคม เติบโตเร็ว', 2),
(6, 2, 'fall', 'ฤดูใบไม้ร่วง', 'Fall', 'กันยายน–พฤศจิกายน เก็บเกี่ยวและปลูกฤดูหนาว', 3),
(7, 2, 'winter', 'ฤดูหนาว', 'Winter', 'ธันวาคม–กุมภาพันธ์ อากาศเย็น', 4);

INSERT INTO agricultural_season_months (season_id, month) VALUES
(1, 5), (1, 6), (1, 7), (1, 8), (1, 9), (1, 10),
(2, 11), (2, 12), (2, 1), (2, 2),
(3, 3), (3, 4),
(4, 3), (4, 4), (4, 5),
(5, 6), (5, 7), (5, 8),
(6, 9), (6, 10), (6, 11),
(7, 12), (7, 1), (7, 2);

INSERT INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 1 FROM region_crops rc WHERE rc.region_id IN (1,2,3,4) AND rc.name_en IN ('Rice', 'Tea', 'Rubber', 'Durian', 'Coffee');

INSERT INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 2 FROM region_crops rc WHERE rc.region_id IN (1,2,4) AND rc.name_en IN ('Vegetables', 'Sugarcane');

INSERT INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 3 FROM region_crops rc WHERE rc.region_id IN (1,3,4) AND rc.name_en IN ('Corn', 'Pineapple', 'Cassava');

INSERT INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 1 FROM region_crops rc WHERE rc.region_id = 3 AND rc.name_en = 'Pineapple';

INSERT INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 4 FROM region_crops rc WHERE rc.region_id IN (5,6,7,8) AND rc.name_en IN ('Corn', 'Soybean', 'Cotton', 'Rice', 'Peanuts', 'Grapes', 'Sorghum', 'Sunflower');

INSERT INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 5 FROM region_crops rc WHERE rc.region_id = 5 AND rc.name_en = 'Soybean';

INSERT INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 6 FROM region_crops rc WHERE rc.region_id IN (5,7,8) AND rc.name_en IN ('Wheat', 'Lettuce');

INSERT INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 7 FROM region_crops rc WHERE rc.region_id = 7 AND rc.name_en IN ('Almonds', 'Lettuce');

