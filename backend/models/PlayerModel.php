<?php

require_once __DIR__ . '/../config/Db.php';
require_once __DIR__ . '/GameRoomModel.php';
require_once __DIR__ . '/../helpers/AppConfig.php';

class PlayerModel
{
    public static function findById($id)
    {
        $stmt = Db::connection()->prepare(
            'SELECT p.*, cr.name_th AS region_name_th, cr.name_en AS region_name_en,
                    cr.code AS region_code
             FROM players p
             LEFT JOIN country_regions cr ON cr.id = p.region_id
             WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findBySessionToken($token)
    {
        $stmt = Db::connection()->prepare('SELECT * FROM players WHERE session_token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listByRoom($roomId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT p.id, p.name, p.region_id, p.profile_image, p.agricultural_capability,
                    p.is_ready, p.cards_submitted_year, p.plan_adjustments_used, p.in_plan_adjustment,
                    p.created_at,
                    cr.name_th AS region_name_th, cr.name_en AS region_name_en,
                    cr.code AS region_code
             FROM players p
             LEFT JOIN country_regions cr ON cr.id = p.region_id
             WHERE p.room_id = ?
             ORDER BY p.created_at ASC'
        );
        $stmt->execute([$roomId]);
        return $stmt->fetchAll();
    }

    public static function create($roomId, $name)
    {
        $pdo = Db::connection();
        $config = require __DIR__ . '/../config/app.php';

        $room = GameRoomModel::findById($roomId);
        if (!$room) {
            Response::error('ไม่พบห้องเกม', 404);
        }
        if ($room['status'] !== 'lobby') {
            Response::error('ห้องเกมเริ่มแล้ว ไม่สามารถเข้าร่วมได้', 400);
        }
        if ((int) $room['player_count'] >= $config['max_players']) {
            Response::error('ห้องเต็มแล้ว (8 คน)', 400);
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            Response::error('ชื่อผู้เล่นไม่ถูกต้อง', 400);
        }

        $check = $pdo->prepare('SELECT id FROM players WHERE room_id = ? AND name = ?');
        $check->execute([$roomId, $name]);
        if ($check->fetch()) {
            Response::error('ชื่อนี้ถูกใช้ในห้องแล้ว', 400);
        }

        $token = generate_session_token();
        $stmt = $pdo->prepare(
            'INSERT INTO players (room_id, name, session_token) VALUES (?, ?, ?)'
        );
        $stmt->execute([$roomId, $name, $token]);

        $playerId = (int) $pdo->lastInsertId();

        $resStmt = $pdo->prepare(
            'INSERT INTO player_resources (player_id, coins, workforce, water, soil_quality)
             VALUES (?, 500, 10, 70, 70)'
        );
        $resStmt->execute([$playerId]);

        GameRoomModel::refreshPlayerCount($roomId);

        return self::findById($playerId);
    }

    public static function selectRegion($playerId, $regionId)
    {
        $player = self::findById($playerId);
        if (!$player) {
            Response::error('ไม่พบผู้เล่น', 404);
        }

        $room = GameRoomModel::findById((int) $player['room_id']);
        if (!$room || !in_array($room['status'], ['lobby', 'countdown', 'planning'], true)) {
            Response::error('ไม่สามารถเลือกภูมิภาคในสถานะนี้ได้', 400);
        }

        $stmt = Db::connection()->prepare(
            'SELECT * FROM country_regions WHERE id = ? AND country_id = ?'
        );
        $stmt->execute([$regionId, $room['country_id']]);
        $region = $stmt->fetch();
        if (!$region) {
            Response::error('ภูมิภาคไม่ถูกต้องสำหรับประเทศนี้', 400);
        }

        // ผู้เล่นเลือกภูมิภาคได้อิสระ — อนุญาตให้หลายคนอยู่ภูมิภาคเดียวกัน
        $pdo = Db::connection();
        $pdo->prepare('UPDATE players SET region_id = ?, is_ready = 1 WHERE id = ?')
            ->execute([$regionId, $playerId]);

        $pdo->prepare(
            'UPDATE player_resources SET coins = ?, water = ?, soil_quality = ? WHERE player_id = ?'
        )->execute([
            $region['default_coins'],
            $region['default_water'],
            $region['default_soil_quality'],
            $playerId,
        ]);

        GameRoomModel::bumpVersion((int) $room['id']);

        return self::findById($playerId);
    }

    public static function updateProfileImage($playerId, $filename)
    {
        $stmt = Db::connection()->prepare(
            'UPDATE players SET profile_image = ? WHERE id = ?'
        );
        $stmt->execute([$filename, $playerId]);
        return self::findById($playerId);
    }

    public static function getResources($playerId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT * FROM player_resources WHERE player_id = ?'
        );
        $stmt->execute([$playerId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function markCardsSubmitted($playerId, $year)
    {
        Db::connection()->prepare(
            'UPDATE players SET cards_submitted_year = ? WHERE id = ?'
        )->execute([$year, $playerId]);
    }

    public static function hasSubmittedCards($playerId, $year)
    {
        $player = self::findById($playerId);
        return $player && (int) ((isset($player['cards_submitted_year']) ? $player['cards_submitted_year'] : 0)) === $year;
    }

    public static function getCapability($playerId)
    {
        $player = self::findById($playerId);
        return (int) ((isset($player['agricultural_capability']) ? $player['agricultural_capability'] : 100));
    }

    public static function adjustCapability($playerId, $delta)
    {
        $pdo = Db::connection();
        $pdo->prepare(
            'UPDATE players SET agricultural_capability = LEAST(100, GREATEST(0, agricultural_capability + ?))
             WHERE id = ?'
        )->execute([$delta, $playerId]);
    }

    public static function adjustCoins($playerId, $delta)
    {
        Db::connection()->prepare(
            'UPDATE player_resources SET coins = GREATEST(0, coins + ?) WHERE player_id = ?'
        )->execute([$delta, $playerId]);
    }

    public static function adjustResource($playerId, $field, $delta, $max = 100)
    {
        $allowed = ['water', 'soil_quality', 'tech_level', 'sustainability', 'env_impact', 'workforce'];
        if (!in_array($field, $allowed, true)) {
            return;
        }
        $maxClause = $max !== null ? 'LEAST(' . (int) $max . ', ' : '';
        $maxClose = $max !== null ? ')' : '';
        $sql = "UPDATE player_resources SET {$field} = {$maxClause}GREATEST(0, {$field} + ?){$maxClose} WHERE player_id = ?";
        Db::connection()->prepare($sql)->execute([$delta, $playerId]);
    }

    public static function setResourceField($playerId, $field, $value)
    {
        $allowed = ['stock_amount', 'coins', 'water', 'soil_quality', 'tech_level', 'sustainability'];
        if (!in_array($field, $allowed, true)) {
            return;
        }
        Db::connection()->prepare(
            "UPDATE player_resources SET {$field} = ? WHERE player_id = ?"
        )->execute([max(0, $value), $playerId]);
    }

    public static function formatForRoomList(array $player)
    {
        return [
            'id' => (int) $player['id'],
            'name' => $player['name'],
            'region_id' => $player['region_id'] ? (int) $player['region_id'] : null,
            'region_name_th' => (isset($player['region_name_th']) ? $player['region_name_th'] : null),
            'region_name_en' => (isset($player['region_name_en']) ? $player['region_name_en'] : null),
            'region_code' => (isset($player['region_code']) ? $player['region_code'] : null),
            'profile_image' => (isset($player['profile_image']) ? $player['profile_image'] : null),
            'agricultural_capability' => (int) ((isset($player['agricultural_capability']) ? $player['agricultural_capability'] : 100)),
            'is_ready' => (bool) ((isset($player['is_ready']) ? $player['is_ready'] : false)),
            'cards_submitted_year' => isset($player['cards_submitted_year'])
                ? (int) $player['cards_submitted_year'] : null,
            'plan_adjustments_used' => (int) ((isset($player['plan_adjustments_used']) ? $player['plan_adjustments_used'] : 0)),
            'in_plan_adjustment' => (bool) ((isset($player['in_plan_adjustment']) ? $player['in_plan_adjustment'] : false)),
        ];
    }

    public static function formatPublic(array $player)
    {
        $maxAdjust = AppConfig::maxPlanAdjustments();
        $used = (int) ((isset($player['plan_adjustments_used']) ? $player['plan_adjustments_used'] : 0));
        return [
            'id' => (int) $player['id'],
            'name' => $player['name'],
            'region_id' => $player['region_id'] ? (int) $player['region_id'] : null,
            'region_name_th' => (isset($player['region_name_th']) ? $player['region_name_th'] : null),
            'region_name_en' => (isset($player['region_name_en']) ? $player['region_name_en'] : null),
            'profile_image' => $player['profile_image'],
            'agricultural_capability' => (int) $player['agricultural_capability'],
            'is_ready' => (bool) $player['is_ready'],
            'session_token' => (isset($player['session_token']) ? $player['session_token'] : null),
            'plan_adjustments_used' => $used,
            'plan_adjustments_remaining' => max(0, $maxAdjust - $used),
            'in_plan_adjustment' => (bool) ((isset($player['in_plan_adjustment']) ? $player['in_plan_adjustment'] : false)),
        ];
    }

    public static function startPlanAdjustment($playerId)
    {
        require_once __DIR__ . '/EventModel.php';
        $player = self::findById($playerId);
        if (!$player) {
            Response::error('ไม่พบผู้เล่น', 404);
        }

        $max = AppConfig::maxPlanAdjustments();
        $used = (int) ((isset($player['plan_adjustments_used']) ? $player['plan_adjustments_used'] : 0));
        if ($used >= $max) {
            Response::error('ใช้สิทธิ์ปรับแผนกิจกรรมครบแล้ว (' . $max . ' ครั้ง)', 400);
        }

        $room = GameRoomModel::findById((int) $player['room_id']);
        $event = EventModel::resolveActiveEvent($room);
        if (!$room || $room['status'] !== 'simulating' || !$event) {
            Response::error('ปรับแผนได้เฉพาะช่วง Breaking News ภัยพิบัติ', 400);
        }

        if ($event['event_type'] !== 'disaster') {
            Response::error('ปรับแผนได้เฉพาะภัยพิบัติร้ายแรง', 400);
        }

        Db::connection()->prepare(
            'UPDATE players SET in_plan_adjustment = 1 WHERE id = ?'
        )->execute([$playerId]);

        GameRoomModel::bumpVersion((int) $room['id']);

        return self::findById($playerId);
    }

    public static function finishPlanAdjustment($playerId)
    {
        $player = self::findById($playerId);
        if (!$player) {
            Response::error('ไม่พบผู้เล่น', 404);
        }

        if (!(bool) ((isset($player['in_plan_adjustment']) ? $player['in_plan_adjustment'] : false))) {
            Response::error('ยังไม่ได้เริ่มปรับแผน', 400);
        }

        $room = GameRoomModel::findById((int) $player['room_id']);
        $pdo = Db::connection();
        $pdo->prepare(
            'UPDATE players SET in_plan_adjustment = 0, plan_adjustments_used = plan_adjustments_used + 1 WHERE id = ?'
        )->execute([$playerId]);

        if ($room && !empty($room['current_event_id'])) {
            $eventId = (int) $room['current_event_id'];
        } elseif ($room) {
            require_once __DIR__ . '/EventModel.php';
            $eventId = EventModel::resolveActiveEventId($room);
        } else {
            $eventId = null;
        }

        if ($eventId) {
            $existing = $pdo->prepare(
                'SELECT id FROM player_event_responses WHERE player_id = ? AND event_id = ?'
            );
            $existing->execute([$playerId, $eventId]);
            if (!$existing->fetch()) {
                $pdo->prepare(
                    'INSERT INTO player_event_responses (player_id, event_id, response_type, handled_well)
                     VALUES (?, ?, ?, 1)'
                )->execute([$playerId, $eventId, 'replan']);
            }
        }

        GameRoomModel::bumpVersion((int) $player['room_id']);

        return self::findById($playerId);
    }

    public static function cancelPlanAdjustment($playerId)
    {
        $player = self::findById($playerId);
        if (!$player) {
            Response::error('ไม่พบผู้เล่น', 404);
        }

        Db::connection()->prepare(
            'UPDATE players SET in_plan_adjustment = 0 WHERE id = ?'
        )->execute([$playerId]);

        return self::findById($playerId);
    }
}
