-- Fix game_logs.detail for hosts without native JSON type (Z.com / older MariaDB)
SET NAMES utf8mb4;
USE cp393722_farmsim;

ALTER TABLE game_logs
  MODIFY COLUMN detail TEXT NULL COMMENT 'JSON string';
