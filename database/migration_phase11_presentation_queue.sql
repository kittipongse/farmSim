-- Phase 11: presentation queue for game result display on dashboard
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS room_presentation_queue (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_id INT UNSIGNED NOT NULL,
  player_id INT UNSIGNED NOT NULL,
  status ENUM('queued','presenting','done') NOT NULL DEFAULT 'queued',
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at TIMESTAMP NULL DEFAULT NULL,
  finished_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_room_player (room_id, player_id),
  KEY idx_room_status (room_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
