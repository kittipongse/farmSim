<?php

require_once __DIR__ . '/../config/Db.php';

class GameRoomModel
{
    public static function findByCode($roomCode)
    {
        $stmt = Db::connection()->prepare(
            'SELECT gr.*, c.name_th AS country_name_th, c.name_en AS country_name_en, c.code AS country_code
             FROM game_rooms gr
             JOIN countries c ON c.id = gr.country_id
             WHERE gr.room_code = ?'
        );
        $stmt->execute([$roomCode]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById($id)
    {
        $stmt = Db::connection()->prepare('SELECT * FROM game_rooms WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create($countryId)
    {
        $pdo = Db::connection();
        $attempts = 0;
        do {
            $roomCode = generate_room_code();
            $pin = generate_pin();
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO game_rooms (room_code, pin, country_id, lobby_started_at, status)
                     VALUES (?, ?, ?, NOW(), ?)'
                );
                $stmt->execute([$roomCode, $pin, $countryId, 'lobby']);
                break;
            } catch (PDOException $e) {
                $attempts++;
                if ($attempts > 10) {
                    throw $e;
                }
            }
        } while (true);

        $room = self::findByCode($roomCode);
        if ($room) {
            return $room;
        }

        $roomId = (int) $pdo->lastInsertId();
        if ($roomId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM game_rooms WHERE id = ?');
            $stmt->execute(array($roomId));
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    public static function bumpVersion($roomId)
    {
        $stmt = Db::connection()->prepare(
            'UPDATE game_rooms SET version = version + 1, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$roomId]);
    }

    public static function updateStatus($roomId, $status, $extra = null)
    {
        if ($status === 'countdown') {
            $stmt = Db::connection()->prepare(
                'UPDATE game_rooms SET status = ?, countdown_started_at = NOW(), version = version + 1 WHERE id = ?'
            );
            $stmt->execute([$status, $roomId]);
            return;
        }
        $stmt = Db::connection()->prepare(
            'UPDATE game_rooms SET status = ?, version = version + 1 WHERE id = ?'
        );
        $stmt->execute([$status, $roomId]);
    }

    public static function refreshPlayerCount($roomId)
    {
        $stmt = Db::connection()->prepare(
            'UPDATE game_rooms gr
             SET gr.player_count = (SELECT COUNT(*) FROM players p WHERE p.room_id = gr.id),
                 gr.version = gr.version + 1
             WHERE gr.id = ?'
        );
        $stmt->execute([$roomId]);
    }

    public static function extendLobbyTimer($roomId, $seconds)
    {
        $stmt = Db::connection()->prepare(
            'UPDATE game_rooms
             SET lobby_started_at = DATE_ADD(lobby_started_at, INTERVAL ? SECOND),
                 version = version + 1
             WHERE id = ? AND status = ?'
        );
        $stmt->execute([$seconds, $roomId, 'lobby']);
    }

    public static function cancelRoom($roomId)
    {
        $stmt = Db::connection()->prepare(
            'UPDATE game_rooms SET status = ?, version = version + 1 WHERE id = ?'
        );
        $stmt->execute(['cancelled', $roomId]);
    }

    /**
     * เดิมใช้ไล่ timer / countdown อัตโนมัติ — ปิดแล้ว
     * เริ่มเกมด้วยแอดมินผ่าน startGame() เท่านั้น
     */
    public static function processLobbyTimer(array $room)
    {
        // ถ้าค้างสถานะ countdown จากรอบเก่า ให้รอแอดมินกดเริ่ม (ไม่เลื่อนไป planning เอง)
        return $room;
    }

    /**
     * แอดมินกดเริ่มเกม → เข้าโหมดวางแผนทันที (ไม่ผ่าน countdown)
     */
    public static function startGame($roomId)
    {
        $room = self::findById($roomId);
        if (!$room) {
            Response::error('ไม่พบห้องเกม', 404);
        }

        if (!in_array($room['status'], array('lobby', 'countdown'), true)) {
            Response::error('เริ่มเกมได้เฉพาะขณะรอผู้เล่นใน Lobby', 400);
        }

        $countStmt = Db::connection()->prepare(
            'SELECT COUNT(*) FROM players WHERE room_id = ?'
        );
        $countStmt->execute(array($roomId));
        $playerCount = (int) $countStmt->fetchColumn();
        if ($playerCount < 1) {
            Response::error('ต้องมีผู้เล่นอย่างน้อย 1 คนก่อนเริ่มเกม', 400);
        }

        $stmt = Db::connection()->prepare(
            'UPDATE game_rooms
             SET status = ?, countdown_started_at = NULL, version = version + 1, updated_at = NOW()
             WHERE id = ? AND status IN (?, ?)'
        );
        $stmt->execute(array('planning', $roomId, 'lobby', 'countdown'));

        $updated = self::findById($roomId);
        if (!$updated || $updated['status'] !== 'planning') {
            Response::error('เริ่มเกมไม่สำเร็จ กรุณาลองใหม่', 500);
        }

        return $updated;
    }

    public static function getLobbyRemainingSeconds(array $room)
    {
        // ไม่ใช้ timer อัตโนมัติแล้ว
        return 0;
    }

    public static function getCountdownRemainingSeconds(array $room)
    {
        // ไม่ใช้ countdown อัตโนมัติแล้ว
        return 0;
    }
}
