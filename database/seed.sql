-- FarmSim EDU Seed Data (Phase 2)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

USE cp393722_farmsim;

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

-- Crop catalog: run migration_phase5_crops_v2.sql after this seed (see install-fresh.bat)
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

-- ---------------------------------------------------------------------------
-- Agricultural seasons & crop planting windows
-- ---------------------------------------------------------------------------

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

-- Crop seasons: populated by migration_phase5_crops_v2.sql
