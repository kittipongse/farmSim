-- Season planting + season_match column (run once on existing DB)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

USE cp393722_farmsim;

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

ALTER TABLE player_crop_plans
  ADD COLUMN season_match TINYINT(1) NOT NULL DEFAULT 1 AFTER mismatch_reason;

-- Seed seasons (skip if already imported)
INSERT IGNORE INTO agricultural_seasons (id, country_id, code, name_th, name_en, description_th, sort_order) VALUES
(1, 1, 'rainy', 'ฤดูฝน', 'Rainy Season', 'พฤษภาคม–ตุลาคม ปริมาณน้ำฝนสูง เหมาะข้าวและพืชหลัก', 1),
(2, 1, 'cool', 'ฤดูหนาว', 'Cool Season', 'พฤศจิกายน–กุมภาพันธ์ อากาศเย็น เหมาะผักและอ้อย', 2),
(3, 1, 'hot', 'ฤดูร้อน', 'Hot Season', 'มีนาคม–เมษายน ร้อนแล้ง เตรียมแปลงและพืชบางชนิด', 3),
(4, 2, 'spring', 'ฤดูใบไม้ผลิ', 'Spring', 'มีนาคม–พฤษภาคม เริ่มปลูกพืชหลัก', 1),
(5, 2, 'summer', 'ฤดูร้อน', 'Summer', 'มิถุนายน–สิงหาคม เติบโตเร็ว', 2),
(6, 2, 'fall', 'ฤดูใบไม้ร่วง', 'Fall', 'กันยายน–พฤศจิกายน เก็บเกี่ยวและปลูกฤดูหนาว', 3),
(7, 2, 'winter', 'ฤดูหนาว', 'Winter', 'ธันวาคม–กุมภาพันธ์ อากาศเย็น', 4);

INSERT IGNORE INTO agricultural_season_months (season_id, month) VALUES
(1, 5), (1, 6), (1, 7), (1, 8), (1, 9), (1, 10),
(2, 11), (2, 12), (2, 1), (2, 2),
(3, 3), (3, 4),
(4, 3), (4, 4), (4, 5),
(5, 6), (5, 7), (5, 8),
(6, 9), (6, 10), (6, 11),
(7, 12), (7, 1), (7, 2);

INSERT IGNORE INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 1 FROM region_crops rc WHERE rc.region_id IN (1,2,3,4) AND rc.name_en IN ('Rice', 'Tea', 'Rubber', 'Durian', 'Coffee');

INSERT IGNORE INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 2 FROM region_crops rc WHERE rc.region_id IN (1,2,4) AND rc.name_en IN ('Vegetables', 'Sugarcane');

INSERT IGNORE INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 3 FROM region_crops rc WHERE rc.region_id IN (1,3,4) AND rc.name_en IN ('Corn', 'Pineapple', 'Cassava');

INSERT IGNORE INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 1 FROM region_crops rc WHERE rc.region_id = 3 AND rc.name_en = 'Pineapple';

INSERT IGNORE INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 4 FROM region_crops rc WHERE rc.region_id IN (5,6,7,8) AND rc.name_en IN ('Corn', 'Soybean', 'Cotton', 'Rice', 'Peanuts', 'Grapes', 'Sorghum', 'Sunflower');

INSERT IGNORE INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 5 FROM region_crops rc WHERE rc.region_id = 5 AND rc.name_en = 'Soybean';

INSERT IGNORE INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 6 FROM region_crops rc WHERE rc.region_id IN (5,7,8) AND rc.name_en IN ('Wheat', 'Lettuce');

INSERT IGNORE INTO region_crop_seasons (region_crop_id, season_id)
SELECT rc.id, 7 FROM region_crops rc WHERE rc.region_id = 7 AND rc.name_en IN ('Almonds', 'Lettuce');
