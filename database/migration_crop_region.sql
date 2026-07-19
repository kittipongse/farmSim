-- Allow crop planting outside player's region (track mismatch + penalize yield)
-- Run once: mysql -u root --default-character-set=utf8mb4 cp393722_farmsim < migration_crop_region.sql

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

USE cp393722_farmsim;

ALTER TABLE player_crop_plans
  ADD COLUMN input_crop_name VARCHAR(100) NULL COMMENT 'ชื่อที่ผู้เล่นพิมพ์' AFTER crop_name;

ALTER TABLE player_crop_plans
  ADD COLUMN region_match TINYINT(1) NOT NULL DEFAULT 1 AFTER growth_months;

ALTER TABLE player_crop_plans
  ADD COLUMN mismatch_reason VARCHAR(30) NULL COMMENT 'wrong_region | unknown_crop' AFTER region_match;

UPDATE player_crop_plans SET input_crop_name = crop_name WHERE input_crop_name IS NULL;
