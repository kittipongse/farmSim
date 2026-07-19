<?php

require_once __DIR__ . '/../config/Db.php';
require_once __DIR__ . '/GameRoomModel.php';
require_once __DIR__ . '/PlayerModel.php';

class HallOfFameModel
{
    public static function ensureTable()
    {
        Db::connection()->exec(
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
    }

    /**
     * บันทึกคะแนนรวมของผู้เล่นทุกคนเมื่อเกมจบ (idempotent ต่อห้อง)
     */
    public static function recordFromRoom($roomId)
    {
        self::ensureTable();

        $room = GameRoomModel::findById($roomId);
        if (!$room) {
            return;
        }

        $roomFull = GameRoomModel::findByCode($room['room_code']);
        if ($roomFull) {
            $room = $roomFull;
        }

        $pdo = Db::connection();
        $stmt = $pdo->prepare(
            'SELECT ps.player_id, ps.total_score, ps.rank_position,
                    p.name AS player_name, p.profile_image
             FROM player_scores ps
             INNER JOIN players p ON p.id = ps.player_id
             WHERE ps.room_id = ? AND ps.year IS NULL
             ORDER BY ps.rank_position ASC'
        );
        $stmt->execute([(int) $roomId]);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            return;
        }

        $insert = $pdo->prepare(
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

        $roomCode = isset($room['room_code']) ? $room['room_code'] : '';
        $country = isset($room['country_name_th']) ? $room['country_name_th'] : null;

        foreach ($rows as $row) {
            $insert->execute([
                (int) $row['player_id'],
                (int) $roomId,
                $roomCode,
                $row['player_name'],
                isset($row['profile_image']) ? $row['profile_image'] : null,
                (int) $row['total_score'],
                (int) $row['rank_position'],
                $country,
            ]);
        }
    }

    public static function top($limit = 5)
    {
        self::ensureTable();
        $limit = max(1, min(20, (int) $limit));

        $stmt = Db::connection()->query(
            'SELECT player_name, profile_image, total_score, rank_position,
                    country_name_th, room_code, achieved_at
             FROM hall_of_fame
             ORDER BY total_score DESC, achieved_at ASC
             LIMIT ' . $limit
        );

        $rank = 1;
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[] = [
                'rank' => $rank,
                'player_name' => $row['player_name'],
                'profile_image' => isset($row['profile_image']) ? $row['profile_image'] : null,
                'total_score' => (int) $row['total_score'],
                'country_name_th' => isset($row['country_name_th']) ? $row['country_name_th'] : null,
                'room_code' => isset($row['room_code']) ? $row['room_code'] : null,
                'achieved_at' => isset($row['achieved_at']) ? $row['achieved_at'] : null,
            ];
            $rank++;
        }
        return $out;
    }
}
