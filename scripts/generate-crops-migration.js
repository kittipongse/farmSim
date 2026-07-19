/**
 * Generates database/migration_phase5_crops_v2.sql from PDF crop data.
 * Run: node scripts/generate-crops-migration.js
 */
const fs = require('fs');
const path = require('path');

const REGION_IDS = { north: 1, central: 2, south: 3, isan: 4 };

function level(mult) {
  if (mult >= 1.0) return 'excellent';
  if (mult >= 0.85) return 'good';
  if (mult >= 0.65) return 'moderate';
  if (mult >= 0.45) return 'poor';
  return 'unsuitable';
}

function slug(nameEn) {
  return nameEn.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
}

// [name_th, name_en, category, growth_months, crop_category, base_coin, rates: {north, central, isan, south}]
const CROPS = [
  ['ข้าว', 'Rice', 'field_crop', 4, 'medium', 100, { north: 1.0, central: 1.0, isan: 1.0, south: 0.85 }],
  ['ข้าวหอมมะลิ', 'Jasmine Rice', 'field_crop', 4, 'medium', 120, { north: 0.65, central: 0.85, isan: 1.0, south: 0.45 }],
  ['ข้าวโพดเลี้ยงสัตว์', 'Feed Corn', 'field_crop', 4, 'medium', 90, { north: 1.0, central: 0.85, isan: 0.85, south: 0.45 }],
  ['ข้าวโพดหวาน', 'Sweet Corn', 'field_crop', 3, 'short', 100, { north: 1.0, central: 1.0, isan: 1.0, south: 0.85 }],
  ['ข้าวโพด', 'Corn', 'field_crop', 4, 'medium', 90, { north: 0.85, central: 0.85, isan: 0.85, south: 0.45 }],
  ['อ้อย', 'Sugarcane', 'field_crop', 5, 'medium', 110, { north: 0.65, central: 1.0, isan: 1.0, south: 0.45 }],
  ['มันสำปะหลัง', 'Cassava', 'field_crop', 5, 'medium', 90, { north: 0.65, central: 0.85, isan: 1.0, south: 0.45 }],
  ['มันเทศ', 'Sweet Potato', 'field_crop', 4, 'medium', 85, { north: 0.85, central: 0.65, isan: 1.0, south: 0.65 }],
  ['มันฝรั่ง', 'Potato', 'field_crop', 4, 'medium', 95, { north: 1.0, central: 0.45, isan: 0.45, south: 0.25 }],
  ['ถั่วเหลือง', 'Soybean', 'field_crop', 4, 'medium', 95, { north: 1.0, central: 0.85, isan: 0.85, south: 0.45 }],
  ['ถั่วเขียว', 'Mung Bean', 'field_crop', 3, 'short', 80, { north: 0.85, central: 0.85, isan: 1.0, south: 0.85 }],
  ['ถั่วลิสง', 'Peanut', 'field_crop', 3, 'short', 90, { north: 0.85, central: 0.85, isan: 1.0, south: 0.85 }],
  ['งา', 'Sesame', 'field_crop', 3, 'short', 85, { north: 0.85, central: 0.85, isan: 1.0, south: 0.85 }],
  ['ทานตะวัน', 'Sunflower', 'field_crop', 4, 'medium', 90, { north: 0.85, central: 1.0, isan: 0.65, south: 0.25 }],
  ['ฝ้าย', 'Cotton', 'field_crop', 5, 'medium', 100, { north: 0.65, central: 0.45, isan: 1.0, south: 0.25 }],
  ['กะหล่ำปลี', 'Cabbage', 'vegetable', 3, 'short', 70, { north: 1.0, central: 0.65, isan: 0.45, south: 0.25 }],
  ['ผักกาด', 'Chinese Cabbage', 'vegetable', 2, 'short', 65, { north: 1.0, central: 0.85, isan: 0.65, south: 0.45 }],
  ['ผักกาดขาว', 'Napa Cabbage', 'vegetable', 2, 'short', 65, { north: 1.0, central: 0.85, isan: 0.85, south: 0.85 }],
  ['ผักสลัด', 'Lettuce', 'vegetable', 2, 'short', 70, { north: 1.0, central: 0.85, isan: 0.45, south: 0.45 }],
  ['คะน้า', 'Kale', 'vegetable', 2, 'short', 65, { north: 1.0, central: 1.0, isan: 0.85, south: 0.85 }],
  ['กวางตุ้ง', 'Bok Choy', 'vegetable', 2, 'short', 65, { north: 1.0, central: 1.0, isan: 0.85, south: 0.85 }],
  ['ผักบุ้ง', 'Morning Glory', 'vegetable', 2, 'short', 60, { north: 0.85, central: 1.0, isan: 0.85, south: 0.85 }],
  ['ผักกาดหอม', 'Celery', 'vegetable', 3, 'short', 70, { north: 0.85, central: 1.0, isan: 0.65, south: 0.65 }],
  ['มะเขือเทศ', 'Tomato', 'vegetable', 3, 'short', 80, { north: 1.0, central: 0.85, isan: 0.65, south: 0.45 }],
  ['มะเขือ', 'Eggplant', 'vegetable', 3, 'short', 75, { north: 0.85, central: 1.0, isan: 0.85, south: 0.65 }],
  ['พริก', 'Chili', 'vegetable', 3, 'short', 85, { north: 0.85, central: 1.0, isan: 1.0, south: 0.85 }],
  ['ฟักทอง', 'Pumpkin', 'vegetable', 3, 'short', 75, { north: 1.0, central: 1.0, isan: 0.85, south: 0.85 }],
  ['แตงกวา', 'Cucumber', 'vegetable', 2, 'short', 70, { north: 0.85, central: 1.0, isan: 0.85, south: 0.85 }],
  ['แตงโม', 'Watermelon', 'vegetable', 3, 'short', 90, { north: 0.65, central: 1.0, isan: 1.0, south: 0.85 }],
  ['ถั่วฝักยาว', 'Long Bean', 'vegetable', 2, 'short', 65, { north: 0.85, central: 1.0, isan: 0.85, south: 1.0 }],
  ['ลำไย', 'Longan', 'fruit', 6, 'long', 150, { north: 1.0, central: 0.65, isan: 0.45, south: 0.25 }],
  ['ลิ้นจี่', 'Lychee', 'fruit', 6, 'long', 140, { north: 1.0, central: 0.45, isan: 0.25, south: 0.25 }],
  ['สตรอว์เบอร์รี', 'Strawberry', 'fruit', 4, 'medium', 160, { north: 1.0, central: 0.25, isan: 0.25, south: 0.25 }],
  ['ส้มเขียวหวาน', 'Mandarin', 'fruit', 5, 'medium', 130, { north: 1.0, central: 0.85, isan: 0.65, south: 0.45 }],
  ['อะโวคาโด', 'Avocado', 'fruit', 6, 'long', 180, { north: 1.0, central: 0.45, isan: 0.45, south: 0.45 }],
  ['มะม่วง', 'Mango', 'fruit', 5, 'medium', 140, { north: 0.85, central: 1.0, isan: 1.0, south: 0.65 }],
  ['กล้วย', 'Banana', 'fruit', 4, 'medium', 100, { north: 0.85, central: 1.0, isan: 0.85, south: 1.0 }],
  ['มะพร้าว', 'Coconut', 'fruit', 6, 'long', 120, { north: 0.45, central: 1.0, isan: 0.45, south: 1.0 }],
  ['มะละกอ', 'Papaya', 'fruit', 4, 'medium', 90, { north: 0.65, central: 1.0, isan: 0.85, south: 0.85 }],
  ['ฝรั่ง', 'Guava', 'fruit', 4, 'medium', 95, { north: 0.65, central: 1.0, isan: 0.85, south: 0.65 }],
  ['ชมพู่', 'Rose Apple', 'fruit', 4, 'medium', 100, { north: 0.45, central: 1.0, isan: 0.65, south: 0.85 }],
  ['ทุเรียน', 'Durian', 'fruit', 6, 'long', 300, { north: 0.45, central: 1.0, isan: 0.45, south: 1.0 }],
  ['ขนุน', 'Jackfruit', 'fruit', 6, 'long', 150, { north: 0.65, central: 1.0, isan: 0.85, south: 0.85 }],
  ['มะเฟือง', 'Marian Plum', 'fruit', 5, 'medium', 120, { north: 0.65, central: 1.0, isan: 0.65, south: 0.65 }],
  ['มะขามหวาน', 'Sweet Tamarind', 'fruit', 5, 'medium', 130, { north: 0.85, central: 0.65, isan: 1.0, south: 0.25 }],
  ['สับปะรด', 'Pineapple', 'fruit', 3, 'short', 110, { north: 0.65, central: 1.0, isan: 0.65, south: 1.0 }],
  ['มังคุด', 'Mangosteen', 'fruit', 6, 'long', 200, { north: 0.25, central: 0.65, isan: 0.25, south: 1.0 }],
  ['เงาะ', 'Rambutan', 'fruit', 5, 'medium', 140, { north: 0.45, central: 0.85, isan: 0.45, south: 1.0 }],
  ['ลองกอง', 'Longkong', 'fruit', 5, 'medium', 130, { north: 0.25, central: 0.45, isan: 0.25, south: 1.0 }],
  ['สละ', 'Salak', 'fruit', 5, 'medium', 125, { north: 0.25, central: 0.85, isan: 0.25, south: 1.0 }],
  ['จำปาดะ', 'Cempedak', 'fruit', 5, 'medium', 140, { north: 0.25, central: 0.45, isan: 0.25, south: 1.0 }],
  ['มะนาว', 'Lime', 'fruit', 4, 'medium', 100, { north: 0.65, central: 0.85, isan: 0.65, south: 0.85 }],
  ['ยางพารา', 'Rubber', 'economic', 8, 'long', 150, { north: 0.45, central: 0.65, isan: 0.85, south: 1.0 }],
  ['ปาล์มน้ำมัน', 'Palm Oil', 'economic', 8, 'long', 160, { north: 0.25, central: 0.45, isan: 0.25, south: 1.0 }],
  ['ชา', 'Tea', 'economic', 6, 'long', 140, { north: 1.0, central: 0.25, isan: 0.25, south: 0.45 }],
  ['กาแฟอาราบิก้า', 'Arabica Coffee', 'economic', 6, 'long', 180, { north: 1.0, central: 0.25, isan: 0.25, south: 0.45 }],
  ['กาแฟโรบัสตา', 'Robusta Coffee', 'economic', 6, 'long', 160, { north: 0.45, central: 0.25, isan: 0.25, south: 1.0 }],
  ['โกโก้', 'Cocoa', 'economic', 6, 'long', 170, { north: 0.45, central: 0.65, isan: 0.45, south: 1.0 }],
  ['แมคคาเดเมีย', 'Macadamia', 'economic', 8, 'long', 220, { north: 1.0, central: 0.25, isan: 0.25, south: 0.45 }],
  ['พริกไทย', 'Black Pepper', 'economic', 5, 'medium', 130, { north: 0.45, central: 0.65, isan: 0.45, south: 1.0 }],
  ['หม่อน', 'Mulberry', 'economic', 4, 'medium', 100, { north: 0.85, central: 0.45, isan: 1.0, south: 0.25 }],
  ['ไผ่', 'Bamboo', 'economic', 5, 'medium', 110, { north: 0.85, central: 0.65, isan: 1.0, south: 0.85 }],
  ['ยูคาลิปตัส', 'Eucalyptus', 'economic', 6, 'long', 120, { north: 0.65, central: 0.65, isan: 1.0, south: 0.45 }],
  ['กระเทียม', 'Garlic', 'herb', 4, 'medium', 90, { north: 1.0, central: 0.65, isan: 0.45, south: 0.25 }],
  ['หอมแดง', 'Shallot', 'herb', 3, 'short', 80, { north: 0.85, central: 0.65, isan: 1.0, south: 0.45 }],
  ['หอมหัวใหญ่', 'Onion', 'herb', 4, 'medium', 85, { north: 1.0, central: 0.65, isan: 0.45, south: 0.25 }],
  ['ขิง', 'Ginger', 'herb', 4, 'medium', 95, { north: 1.0, central: 0.85, isan: 0.65, south: 0.85 }],
  ['ขมิ้น', 'Turmeric', 'herb', 4, 'medium', 90, { north: 0.65, central: 0.85, isan: 0.65, south: 1.0 }],
  ['กระชาย', 'Galangal', 'herb', 4, 'medium', 95, { north: 0.65, central: 1.0, isan: 0.85, south: 0.85 }],
  ['ตะไคร้', 'Lemongrass', 'herb', 3, 'short', 75, { north: 0.85, central: 1.0, isan: 0.85, south: 1.0 }],
  ['ผักชี', 'Coriander', 'herb', 2, 'short', 60, { north: 1.0, central: 0.85, isan: 0.85, south: 0.85 }],
  ['ต้นหอม', 'Spring Onion', 'herb', 2, 'short', 60, { north: 1.0, central: 0.85, isan: 0.85, south: 0.85 }],
  ['โหระพา', 'Sweet Basil', 'herb', 2, 'short', 65, { north: 0.85, central: 1.0, isan: 1.0, south: 1.0 }],
  ['กะเพรา', 'Holy Basil', 'herb', 2, 'short', 65, { north: 0.85, central: 1.0, isan: 1.0, south: 1.0 }],
  ['แมงลัก', 'Lemon Basil', 'herb', 2, 'short', 65, { north: 0.85, central: 1.0, isan: 0.85, south: 1.0 }],
  ['กระเจี๊ยบแดง', 'Roselle', 'herb', 3, 'short', 80, { north: 0.65, central: 0.85, isan: 1.0, south: 0.65 }],
  ['สะตอ', 'Stink Bean', 'herb', 5, 'medium', 120, { north: 0.25, central: 0.45, isan: 0.25, south: 1.0 }],
  ['ลูกเนียง', 'Parkia', 'herb', 5, 'medium', 115, { north: 0.25, central: 0.45, isan: 0.25, south: 1.0 }],
];

// USA crops [name_en used for both, region_id native, growth, category, base_coin]
const USA_CROPS = [
  ['Corn', 5, 4, 'field_crop', 100],
  ['Soybean', 5, 4, 'field_crop', 95],
  ['Wheat', 5, 5, 'field_crop', 90],
  ['Cotton', 6, 5, 'field_crop', 110],
  ['Rice', 6, 4, 'field_crop', 100],
  ['Peanuts', 6, 3, 'field_crop', 85],
  ['Almonds', 7, 8, 'economic', 200],
  ['Grapes', 7, 5, 'fruit', 150],
  ['Lettuce', 7, 2, 'vegetable', 70],
  ['Sorghum', 8, 4, 'field_crop', 85],
  ['Sunflower', 8, 3, 'field_crop', 90],
];

const TH_SEASONS = {
  rainy: 1, winter: 2, summer: 3,
};

function cropCategoryToEnum(cat) {
  return cat;
}

function lines() {
  const out = [];
  out.push('-- FarmSim EDU Phase 5: crops v2 (global catalog + region rates)');
  out.push('-- Generated by scripts/generate-crops-migration.js');
  out.push('SET NAMES utf8mb4;');
  out.push('USE cp393722_farmsim;');
  out.push('');
  out.push('-- New tables');
  out.push(`CREATE TABLE IF NOT EXISTS crops (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;`);
  out.push('');
  out.push(`CREATE TABLE IF NOT EXISTS crop_region_rates (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;`);
  out.push('');
  out.push(`CREATE TABLE IF NOT EXISTS crop_seasons (
  crop_id INT UNSIGNED NOT NULL,
  season_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (crop_id, season_id),
  FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE CASCADE,
  FOREIGN KEY (season_id) REFERENCES agricultural_seasons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;`);
  out.push('');
  out.push('-- Drop legacy FKs to region_crops if upgrading');
  out.push(`DROP PROCEDURE IF EXISTS farmsim_drop_crop_fks;
DELIMITER //
CREATE PROCEDURE farmsim_drop_crop_fks()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE fk_name VARCHAR(255);
  DECLARE tbl VARCHAR(255);
  DECLARE cur CURSOR FOR
    SELECT TABLE_NAME, CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND REFERENCED_TABLE_NAME = 'region_crops';
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO tbl, fk_name;
    IF done THEN LEAVE read_loop; END IF;
    SET @s = CONCAT('ALTER TABLE ', tbl, ' DROP FOREIGN KEY ', fk_name);
    PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END LOOP;
  CLOSE cur;
END//
DELIMITER ;
CALL farmsim_drop_crop_fks();
DROP PROCEDURE farmsim_drop_crop_fks;`);
  out.push('');
  out.push('-- Clear dependent data that references old crop IDs');
  out.push('DELETE FROM market_transactions;');
  out.push('DELETE FROM market_prices;');
  out.push('UPDATE player_crop_plans SET crop_id = NULL;');
  out.push('');
  out.push('DROP TABLE IF EXISTS region_crop_seasons;');
  out.push('DROP TABLE IF EXISTS region_crops;');
  out.push('');
  out.push('DELETE FROM crop_seasons;');
  out.push('DELETE FROM crop_region_rates;');
  out.push('DELETE FROM crops;');
  out.push('');
  out.push('-- Thailand crops');
  const usedCodes = new Set();
  CROPS.forEach((c, idx) => {
    const [nameTh, nameEn, category, growth, cropCat, baseCoin, rates] = c;
    let code = slug(nameEn);
    if (usedCodes.has(code)) code = `${code}_${idx}`;
    usedCodes.add(code);
    const buy = Math.round(baseCoin * 0.15);
    const sell = Math.round(baseCoin * 0.30);
    const cap = cropCat === 'long' ? 3 : cropCat === 'short' ? 1 : 2;
    out.push(
      `INSERT INTO crops (country_id, code, name_th, name_en, category, growth_months, crop_category, base_coin, base_buy_price, base_sell_price, capability_bonus) VALUES (1, '${code}', '${nameTh}', '${nameEn}', '${category}', ${growth}, '${cropCat}', ${baseCoin}, ${buy}, ${sell}, ${cap});`
    );
  });
  out.push('');
  out.push('-- USA crops');
  USA_CROPS.forEach((c, idx) => {
    const [nameEn, nativeRegion, growth, category, baseCoin] = c;
    const code = `us_${slug(nameEn)}`;
    const cropCat = growth <= 3 ? 'short' : growth >= 6 ? 'long' : 'medium';
    const buy = Math.round(baseCoin * 0.15);
    const sell = Math.round(baseCoin * 0.30);
    const cap = cropCat === 'long' ? 3 : cropCat === 'short' ? 1 : 2;
    out.push(
      `INSERT INTO crops (country_id, code, name_th, name_en, category, growth_months, crop_category, base_coin, base_buy_price, base_sell_price, capability_bonus) VALUES (2, '${code}', '${nameEn}', '${nameEn}', '${category}', ${growth}, '${cropCat}', ${baseCoin}, ${buy}, ${sell}, ${cap});`
    );
  });
  out.push('');
  out.push('-- Thailand region rates');
  CROPS.forEach((c, idx) => {
    const [, nameEn, , , , , rates] = c;
    let code = slug(nameEn);
    if (idx > 0 && CROPS.slice(0, idx).some((x) => slug(x[1]) === code)) code = `${code}_${idx}`;
    Object.entries(REGION_IDS).forEach(([regionCode, regionId]) => {
      const mult = rates[regionCode];
      out.push(
        `INSERT INTO crop_region_rates (crop_id, region_id, suitability_level, coin_multiplier)
         SELECT c.id, ${regionId}, '${level(mult)}', ${mult.toFixed(2)} FROM crops c WHERE c.country_id = 1 AND c.code = '${code}';`
      );
    });
  });
  out.push('');
  out.push('-- USA region rates (1.0 native, 0.45 elsewhere)');
  USA_CROPS.forEach((c) => {
    const [nameEn, nativeRegion] = c;
    const code = `us_${slug(nameEn)}`;
    for (let r = 5; r <= 8; r++) {
      const mult = r === nativeRegion ? 1.0 : 0.45;
      out.push(
        `INSERT INTO crop_region_rates (crop_id, region_id, suitability_level, coin_multiplier)
         SELECT c.id, ${r}, '${level(mult)}', ${mult.toFixed(2)} FROM crops c WHERE c.country_id = 2 AND c.code = '${code}';`
      );
    }
  });
  out.push('');
  out.push('-- Basic season mapping for Thailand (rainy=1, winter=2, summer=3)');
  out.push('-- Field crops: rainy+summer; vegetables: winter; fruits: varied; economic: long-term');
  const seasonRules = {
    field_crop: [1, 3],
    vegetable: [2, 3],
    fruit: [1, 2],
    herb: [2, 3],
    economic: [1],
  };
  ['field_crop', 'vegetable', 'fruit', 'herb', 'economic'].forEach((cat) => {
    seasonRules[cat].forEach((sid) => {
      out.push(
        `INSERT IGNORE INTO crop_seasons (crop_id, season_id)
         SELECT c.id, ${sid} FROM crops c WHERE c.country_id = 1 AND c.category = '${cat}';`
      );
    });
  });
  out.push(
    `INSERT IGNORE INTO crop_seasons (crop_id, season_id)
     SELECT c.id, 1 FROM crops c WHERE c.country_id = 2;`
  );
  out.push(
    `INSERT IGNORE INTO crop_seasons (crop_id, season_id)
     SELECT c.id, 4 FROM crops c WHERE c.country_id = 2;`
  );
  out.push('');
  out.push('-- Re-add FKs to crops table');
  out.push(`SET @has_mp_fk = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'market_prices' AND REFERENCED_TABLE_NAME = 'crops');`);
  out.push(`SET @sql = IF(@has_mp_fk = 0,
 'ALTER TABLE market_prices ADD CONSTRAINT market_prices_crop_fk FOREIGN KEY (crop_id) REFERENCES crops(id)',
 'SELECT 1');`);
  out.push('PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;');
  out.push(`SET @has_pcp_fk = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'player_crop_plans' AND REFERENCED_TABLE_NAME = 'crops');`);
  out.push(`SET @sql = IF(@has_pcp_fk = 0,
 'ALTER TABLE player_crop_plans ADD CONSTRAINT player_crop_plans_crop_fk FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE SET NULL',
 'SELECT 1');`);
  out.push('PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;');
  out.push(`SET @has_mt_fk = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'market_transactions' AND REFERENCED_TABLE_NAME = 'crops');`);
  out.push(`SET @sql = IF(@has_mt_fk = 0,
 'ALTER TABLE market_transactions ADD CONSTRAINT market_transactions_crop_fk FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE SET NULL',
 'SELECT 1');`);
  out.push('PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;');
  return out.join('\n');
}

const outPath = path.join(__dirname, '..', 'database', 'migration_phase5_crops_v2.sql');
fs.writeFileSync(outPath, lines(), 'utf8');
console.log('Wrote', outPath);
console.log('Thailand crops:', CROPS.length, '| USA crops:', USA_CROPS.length);
console.log('Total region rate rows:', CROPS.length * 4 + USA_CROPS.length * 4);
