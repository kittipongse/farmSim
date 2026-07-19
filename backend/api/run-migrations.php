<?php

/**
 * One-shot idempotent migrations on production (call once after deploy).
 * https://znix.online/farmsim/api/run-migrations.php?key=farmsim-phase3
 */

header('Content-Type: application/json; charset=utf-8');

$key = isset($_GET['key']) ? $_GET['key'] : '';
if ($key !== 'farmsim-phase3') {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'Forbidden'), JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/config/Db.php';
require_once __DIR__ . '/helpers/CropsMigration.php';

$pdo = Db::connection();
$applied = array();

function column_exists($pdo, $table, $column)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(array($table, $column));
    return (int) $stmt->fetchColumn() > 0;
}

try {
    if (!column_exists($pdo, 'game_rooms', 'breaking_news_until')) {
        $pdo->exec(
            "ALTER TABLE game_rooms
             ADD COLUMN breaking_news_until TIMESTAMP NULL DEFAULT NULL
             COMMENT 'แสดง Breaking News จนถึงเวลานี้' AFTER simulation_pause_until"
        );
        $applied[] = 'game_rooms.breaking_news_until';
    }

    if (!column_exists($pdo, 'game_rooms', 'month_timer_remaining')) {
        $pdo->exec(
            "ALTER TABLE game_rooms
             ADD COLUMN month_timer_remaining SMALLINT UNSIGNED NULL DEFAULT NULL
             COMMENT 'วินาทีนับถอยหลังที่หยุดไว้ระหว่าง Breaking News' AFTER breaking_news_until"
        );
        $applied[] = 'game_rooms.month_timer_remaining';
    }

    if (!column_exists($pdo, 'players', 'plan_adjustments_used')) {
        $pdo->exec(
            "ALTER TABLE players
             ADD COLUMN plan_adjustments_used TINYINT UNSIGNED NOT NULL DEFAULT 0
             COMMENT 'จำนวนครั้งที่ปรับแผนกิจกรรมระหว่างภัยพิบัติ' AFTER cards_submitted_year"
        );
        $applied[] = 'players.plan_adjustments_used';
    }

    if (!column_exists($pdo, 'players', 'in_plan_adjustment')) {
        $pdo->exec(
            "ALTER TABLE players
             ADD COLUMN in_plan_adjustment TINYINT(1) NOT NULL DEFAULT 0
             COMMENT 'กำลังปรับแผนกิจกรรม' AFTER plan_adjustments_used"
        );
        $applied[] = 'players.in_plan_adjustment';
    }

    if (!column_exists($pdo, 'game_rooms', 'current_bonus_quiz_id')) {
        $pdo->exec(
            "ALTER TABLE game_rooms
             ADD COLUMN current_bonus_quiz_id INT UNSIGNED NULL DEFAULT NULL
             COMMENT 'โบนัสทายปัญหาที่กำลังเปิด' AFTER current_event_id"
        );
        $applied[] = 'game_rooms.current_bonus_quiz_id';
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bonus_quiz_questions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_th VARCHAR(500) NOT NULL,
            correct_answer VARCHAR(100) NOT NULL,
            answer_type ENUM('number', 'text') NOT NULL DEFAULT 'text',
            active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $applied[] = 'bonus_quiz_questions';

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS room_bonus_quizzes (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $applied[] = 'room_bonus_quizzes';

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS player_bonus_quiz_answers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id INT UNSIGNED NOT NULL,
            player_id INT UNSIGNED NOT NULL,
            answer VARCHAR(200) NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            coins_delta INT NOT NULL DEFAULT 0,
            correct_order TINYINT UNSIGNED NULL DEFAULT NULL,
            answered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_player_quiz (quiz_id, player_id),
            KEY idx_quiz (quiz_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $applied[] = 'player_bonus_quiz_answers';

    $count = (int) $pdo->query('SELECT COUNT(*) FROM bonus_quiz_questions')->fetchColumn();
    if ($count === 0) {
        $seed = $pdo->prepare(
            'INSERT INTO bonus_quiz_questions (question_th, correct_answer, answer_type) VALUES (?, ?, ?)'
        );
        $questions = array(
            array('ประเทศไทยมีกี่จังหวัด', '77', 'number'),
            array('1 ปีมีกี่เดือน', '12', 'number'),
            array('แมงมุมมีกี่ขา', '8', 'number'),
            array('ร้อยมีกี่หลัก', '3', 'number'),
            array('สัปดาห์มีกี่วัน', '7', 'number'),
            array('หนึ่งโหลมีกี่ชิ้น', '12', 'number'),
            array('ประเทศไทยใช้สกุลเงินอะไร', 'บาท', 'text'),
            array('กรุงเทพมหานครเคยชื่อว่าอะไร', 'กรุงเทพ', 'text'),
        );
        foreach ($questions as $q) {
            $seed->execute($q);
        }
        $applied[] = 'bonus_quiz_questions.seed';
    }

    $cropsSql = __DIR__ . '/migrations/migration_phase5_crops_v2.sql';
    if (!CropsMigration::isApplied($pdo)) {
        $result = CropsMigration::migrate($pdo, $cropsSql);
        if (!empty($result['skipped'])) {
            $applied[] = 'crops_v2.skipped';
        } else {
            $applied[] = 'crops_v2.applied';
            $applied[] = 'crops_v2.count=' . (int) $result['crops'];
            $applied[] = 'crop_region_rates.count=' . (int) $result['rates'];
        }
    } else {
        $applied[] = 'crops_v2.already_up_to_date';
    }

    // Phase 6: allow duplicate card types within a year (still 1 card per month)
    $idxStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND INDEX_NAME = ?'
    );
    $idxStmt->execute(array('player_year_cards', 'uk_player_year_card'));
    if ((int) $idxStmt->fetchColumn() > 0) {
        $pdo->exec('ALTER TABLE player_year_cards DROP INDEX uk_player_year_card');
        $applied[] = 'drop.uk_player_year_card';
    }

    // Phase 7: civic education quiz bank (A/B)
    $choiceCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM bonus_quiz_questions WHERE answer_type = 'choice'"
    )->fetchColumn();
    if ($choiceCount < 20) {
        $modelPath = __DIR__ . '/models/BonusQuizModel.php';
        if (!is_file($modelPath)) {
            $modelPath = __DIR__ . '/../models/BonusQuizModel.php';
        }
        require_once $modelPath;
        $pdo->exec('DELETE FROM bonus_quiz_questions');
        BonusQuizModel::ensureSeedQuestions();
        $applied[] = 'bonus_quiz_questions.phase7';
    }

    // Phase 8: bonus quiz reveal winners screen (pause month timer)
    if (!column_exists($pdo, 'game_rooms', 'bonus_quiz_reveal_until')) {
        $pdo->exec(
            "ALTER TABLE game_rooms
             ADD COLUMN bonus_quiz_reveal_until TIMESTAMP NULL DEFAULT NULL
             COMMENT 'แสดงรายชื่อผู้ตอบถูกจนถึงเวลานี้' AFTER current_bonus_quiz_id"
        );
        $applied[] = 'game_rooms.bonus_quiz_reveal_until';
    }

    try {
        $pdo->exec(
            "ALTER TABLE room_bonus_quizzes
             MODIFY COLUMN status ENUM('active','revealing','closed') NOT NULL DEFAULT 'active'"
        );
        $applied[] = 'room_bonus_quizzes.status_revealing';
    } catch (Exception $e) {
        // already updated or unsupported — ignore
    }

    try {
        $pdo->exec(
            "ALTER TABLE bonus_quiz_questions
             MODIFY COLUMN answer_type ENUM('number','text','choice') NOT NULL DEFAULT 'text'"
        );
        $pdo->exec(
            "ALTER TABLE room_bonus_quizzes
             MODIFY COLUMN answer_type ENUM('number','text','choice') NOT NULL DEFAULT 'text'"
        );
        $applied[] = 'bonus_quiz.answer_type_choice';
    } catch (Exception $e) {
        // ignore
    }

    // Phase 9: quiz idle auto-close activity tracking
    if (!column_exists($pdo, 'room_bonus_quizzes', 'last_activity_at')) {
        $pdo->exec(
            "ALTER TABLE room_bonus_quizzes
             ADD COLUMN last_activity_at TIMESTAMP NULL DEFAULT NULL
             COMMENT 'กิจกรรมล่าสุด (พิมพ์/คลิก) สำหรับ idle auto-close' AFTER started_at"
        );
        $applied[] = 'room_bonus_quizzes.last_activity_at';
    }
    $pdo->exec(
        'UPDATE room_bonus_quizzes SET last_activity_at = started_at WHERE last_activity_at IS NULL'
    );

    // Phase 11: presentation queue for game result display
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS room_presentation_queue (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $applied[] = 'room_presentation_queue';

    // Phase 10: global Top score hall of fame (with player photo)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS hall_of_fame (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            player_id INT UNSIGNED NULL,
            room_id INT UNSIGNED NOT NULL,
            room_code VARCHAR(16) NOT NULL,
            player_name VARCHAR(100) NOT NULL,
            profile_image VARCHAR(255) NULL,
            total_score INT NOT NULL DEFAULT 0,
            rank_position TINYINT UNSIGNED NOT NULL DEFAULT 1,
            country_name_th VARCHAR(100) NULL,
            achieved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_room_player (room_id, player_id),
            KEY idx_score (total_score)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $applied[] = 'hall_of_fame';

    // Backfill Top scores จากห้องที่จบแล้ว (ถ้ายังไม่มีใน hall_of_fame)
    $backfillCount = 0;
    try {
        $finished = $pdo->query(
            "SELECT gr.id AS room_id, gr.room_code, c.name_th AS country_name_th,
                    ps.player_id, ps.total_score, ps.rank_position,
                    p.name AS player_name, p.profile_image
             FROM game_rooms gr
             INNER JOIN countries c ON c.id = gr.country_id
             INNER JOIN player_scores ps ON ps.room_id = gr.id AND ps.year IS NULL
             INNER JOIN players p ON p.id = ps.player_id
             WHERE gr.status = 'finished'"
        )->fetchAll(PDO::FETCH_ASSOC);

        if ($finished) {
            $ins = $pdo->prepare(
                'INSERT INTO hall_of_fame
                    (player_id, room_id, room_code, player_name, profile_image,
                     total_score, rank_position, country_name_th)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    player_name = VALUES(player_name),
                    profile_image = VALUES(profile_image),
                    total_score = VALUES(total_score),
                    rank_position = VALUES(rank_position),
                    country_name_th = VALUES(country_name_th)'
            );
            foreach ($finished as $row) {
                $ins->execute(array(
                    (int) $row['player_id'],
                    (int) $row['room_id'],
                    $row['room_code'],
                    $row['player_name'],
                    isset($row['profile_image']) ? $row['profile_image'] : null,
                    (int) $row['total_score'],
                    (int) $row['rank_position'],
                    isset($row['country_name_th']) ? $row['country_name_th'] : null,
                ));
                $backfillCount++;
            }
        }
        $applied[] = 'hall_of_fame.backfill=' . $backfillCount;
    } catch (Exception $e) {
        $applied[] = 'hall_of_fame.backfill_skip';
    }

    // Phase 9: universal short crops (upsert rates + new herbs/veggies)
    $shortResult = CropsMigration::upsertUniversalShortCrops($pdo);
    if (!empty($shortResult['skipped'])) {
        $applied[] = 'crops_short.skipped';
    } else {
        $applied[] = 'crops_short.upserted';
        $applied[] = 'crops_short.inserted=' . (int) $shortResult['inserted'];
        $applied[] = 'crops_short.updated=' . (int) $shortResult['updated'];
    }

    echo json_encode(array(
        'ok' => true,
        'applied' => $applied,
        'message' => count($applied) ? 'Migration applied' : 'Already up to date',
    ), JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'ok' => false,
        'error' => $e->getMessage(),
    ), JSON_UNESCAPED_UNICODE);
}
