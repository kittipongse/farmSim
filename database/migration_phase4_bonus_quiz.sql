-- Bonus quiz ระหว่างเล่น (run once on existing DB)
SET NAMES utf8mb4;

SET @db = DATABASE();

SELECT COUNT(*) INTO @col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'game_rooms' AND COLUMN_NAME = 'current_bonus_quiz_id';
SET @sql = IF(@col = 0,
  'ALTER TABLE game_rooms ADD COLUMN current_bonus_quiz_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''โบนัสทายปัญหาที่กำลังเปิด'' AFTER current_event_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS bonus_quiz_questions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_th VARCHAR(500) NOT NULL,
  correct_answer VARCHAR(100) NOT NULL,
  answer_type ENUM('number', 'text') NOT NULL DEFAULT 'text',
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS room_bonus_quizzes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_id INT UNSIGNED NOT NULL,
  year SMALLINT UNSIGNED NOT NULL,
  month TINYINT UNSIGNED NOT NULL,
  question_th VARCHAR(500) NOT NULL,
  correct_answer VARCHAR(100) NOT NULL,
  answer_type ENUM('number', 'text') NOT NULL DEFAULT 'text',
  status ENUM('active', 'closed') NOT NULL DEFAULT 'active',
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_room_bonus_month (room_id, year, month),
  KEY idx_room_status (room_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_bonus_quiz_answers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quiz_id INT UNSIGNED NOT NULL,
  player_id INT UNSIGNED NOT NULL,
  answer VARCHAR(200) NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  coins_delta INT NOT NULL DEFAULT 0,
  correct_order TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'ลำดับผู้ตอบถูก 1=คนแรก',
  answered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_player_quiz (quiz_id, player_id),
  KEY idx_quiz (quiz_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bonus_quiz_questions (question_th, correct_answer, answer_type) VALUES
  ('ประเทศไทยมีกี่จังหวัด', '77', 'number'),
  ('1 ปีมีกี่เดือน', '12', 'number'),
  ('แมงมุมมีกี่ขา', '8', 'number'),
  ('ร้อยมีกี่หลัก', '3', 'number'),
  ('สัปดาห์มีกี่วัน', '7', 'number'),
  ('หนึ่งโหลมีกี่ชิ้น', '12', 'number'),
  ('ประเทศไทยใช้สกุลเงินอะไร', 'บาท', 'text'),
  ('กรุงเทพมหานครเคยชื่อว่าอะไร', 'กรุงเทพ', 'text')
ON DUPLICATE KEY UPDATE question_th = VALUES(question_th);
