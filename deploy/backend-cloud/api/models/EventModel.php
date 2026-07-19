<?php

require_once __DIR__ . '/../config/Db.php';
require_once __DIR__ . '/PlayerModel.php';

class EventModel
{
    const SPRITE_MAP = [
        'flood_th' => 8,
        'drought_isan' => 9,
        'pest_outbreak' => 13,
        'organic_fertilizer' => 14,
        'irrigation_north' => 14,
        'tornado' => 10,
        'drought_plains' => 9,
        'wildfire' => 11,
        'typhoon_us' => 12,
        'farm_bill' => 15,
        'trade_tariff' => 15,
    ];

    public static function ensureYearEvents($roomId, $year, $countryId)
    {
        $pdo = Db::connection();
        $check = $pdo->prepare(
            'SELECT COUNT(*) AS cnt FROM room_year_events WHERE room_id = ? AND year = ?'
        );
        $check->execute([$roomId, $year]);
        if ((int) (($__row = $check->fetch()) && isset($__row['cnt']) ? $__row['cnt'] : 0) > 0) {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM breaking_news_templates
             WHERE country_id = ? OR country_id IS NULL
             ORDER BY RAND()'
        );
        $stmt->execute([$countryId]);
        $templates = $stmt->fetchAll();

        $disasters = array_values(array_filter($templates, function ($t) { return $t['event_type'] === 'disaster'; }));
        $policies = array_values(array_filter($templates, function ($t) { return $t['event_type'] === 'government_policy'; }));

        if (count($disasters) === 0) {
            return;
        }

        $eventCount = random_int(2, 3);
        $months = self::pickMonths($eventCount);
        $selected = [];

        $selected[] = $disasters[array_rand($disasters)];
        while (count($selected) < $eventCount && count($policies) > 0) {
            $selected[] = $policies[array_rand($policies)];
        }
        while (count($selected) < $eventCount) {
            $selected[] = $disasters[array_rand($disasters)];
        }

        $insert = $pdo->prepare(
            'INSERT INTO room_year_events (room_id, template_id, year, month, event_type)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($months as $i => $month) {
            $tpl = $selected[$i];
            $insert->execute([
                $roomId,
                (int) $tpl['id'],
                $year,
                $month,
                $tpl['event_type'],
            ]);
        }
    }

    public static function getForMonth($roomId, $year, $month)
    {
        $stmt = Db::connection()->prepare(
            'SELECT rye.*, bnt.code, bnt.name_th, bnt.name_en, bnt.event_type,
                    bnt.sprite_index, bnt.capability_penalty_min, bnt.capability_penalty_max,
                    bnt.country_id, bnt.region_id
             FROM room_year_events rye
             JOIN breaking_news_templates bnt ON bnt.id = rye.template_id
             WHERE rye.room_id = ? AND rye.year = ? AND rye.month = ?
             LIMIT 1'
        );
        $stmt->execute([$roomId, $year, $month]);
        $row = $stmt->fetch();
        return $row ? self::formatEvent($row) : null;
    }

    public static function getById($eventId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT rye.*, bnt.code, bnt.name_th, bnt.name_en, bnt.event_type,
                    bnt.sprite_index, bnt.capability_penalty_min, bnt.capability_penalty_max,
                    bnt.country_id, bnt.region_id
             FROM room_year_events rye
             JOIN breaking_news_templates bnt ON bnt.id = rye.template_id
             WHERE rye.id = ?'
        );
        $stmt->execute([$eventId]);
        $row = $stmt->fetch();
        return $row ? self::formatEvent($row) : null;
    }

    public static function listForYear($roomId, $year)
    {
        $stmt = Db::connection()->prepare(
            'SELECT rye.*, bnt.code, bnt.name_th, bnt.name_en, bnt.event_type,
                    bnt.sprite_index, bnt.capability_penalty_min, bnt.capability_penalty_max
             FROM room_year_events rye
             JOIN breaking_news_templates bnt ON bnt.id = rye.template_id
             WHERE rye.room_id = ? AND rye.year = ?
             ORDER BY rye.month'
        );
        $stmt->execute([$roomId, $year]);
        return array_map(array('EventModel', 'formatEvent'), $stmt->fetchAll());
    }

    public static function respond($playerId, $eventId, $action)
    {
        $player = PlayerModel::findById($playerId);
        if (!$player) {
            Response::error('ไม่พบผู้เล่น', 404);
        }

        $event = self::getById($eventId);
        if (!$event) {
            Response::error('ไม่พบเหตุการณ์', 404);
        }

        $pdo = Db::connection();
        $room = $pdo->prepare('SELECT * FROM game_rooms WHERE id = ?');
        $room->execute([(int) $player['room_id']]);
        $roomRow = $room->fetch();

        if (!$roomRow || $roomRow['status'] !== 'simulating') {
            Response::error('ตอบสนองได้เฉพาะช่วงจำลองเกม', 400);
        }

        if ((int) $roomRow['current_event_id'] !== $eventId) {
            $eventMonth = (int) $event['month'];
            $roomMonth = (int) $roomRow['current_month'];
            if ($eventMonth !== $roomMonth || (int) $event['year'] !== (int) $roomRow['current_year']) {
                Response::error('ไม่ใช่เหตุการณ์ปัจจุบัน', 400);
            }
        }

        $existing = $pdo->prepare(
            'SELECT id FROM player_event_responses WHERE player_id = ? AND event_id = ?'
        );
        $existing->execute([$playerId, $eventId]);
        if ($existing->fetch()) {
            Response::error('ตอบสนองเหตุการณ์นี้แล้ว', 400);
        }

        $handledWell = self::evaluateResponse($player, $event, $action);

        $pdo->prepare(
            'INSERT INTO player_event_responses (player_id, event_id, response_type, handled_well)
             VALUES (?, ?, ?, ?)'
        )->execute([$playerId, $eventId, $action, $handledWell ? 1 : 0]);

        if (!$handledWell) {
            self::applyCapabilityPenalty($playerId, $event);
        } elseif ($event['event_type'] === 'government_policy') {
            PlayerModel::adjustCapability($playerId, 5);
            PlayerModel::adjustCoins($playerId, 30);
        }

        return [
            'handled_well' => $handledWell,
            'action' => $action,
            'capability' => PlayerModel::getCapability($playerId),
        ];
    }

    public static function resolveActiveEventId($room)
    {
        if (!$room || !is_array($room)) {
            return null;
        }
        if (!empty($room['current_event_id'])) {
            return (int) $room['current_event_id'];
        }
        if ($room['status'] !== 'simulating') {
            return null;
        }
        $event = self::getForMonth(
            (int) $room['id'],
            (int) $room['current_year'],
            (int) $room['current_month']
        );
        return $event ? (int) $event['id'] : null;
    }

    public static function resolveActiveEvent($room)
    {
        $eventId = self::resolveActiveEventId($room);
        return $eventId ? self::getById($eventId) : null;
    }

    public static function getPlayerResponse($playerId, $eventId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT response_type, handled_well FROM player_event_responses
             WHERE player_id = ? AND event_id = ? LIMIT 1'
        );
        $stmt->execute([$playerId, $eventId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return [
            'event_id' => (int) $eventId,
            'response_type' => $row['response_type'],
            'handled_well' => (bool) $row['handled_well'],
        ];
    }

    public static function applyPendingPenalties($roomId, $eventId)
    {
        $event = self::getById($eventId);
        if (!$event) {
            return;
        }

        $players = PlayerModel::listByRoom($roomId);
        $pdo = Db::connection();
        $check = $pdo->prepare(
            'SELECT id, handled_well FROM player_event_responses WHERE player_id = ? AND event_id = ?'
        );

        foreach ($players as $player) {
            $playerId = (int) $player['id'];
            if ($event['region_id'] && (int) $player['region_id'] !== (int) $event['region_id']) {
                continue;
            }

            $check->execute([$playerId, $eventId]);
            $resp = $check->fetch();
            if (!$resp) {
                if ($event['event_type'] === 'disaster') {
                    self::applyCapabilityPenalty($playerId, $event);
                    $pdo->prepare(
                        'INSERT INTO player_event_responses (player_id, event_id, response_type, handled_well)
                         VALUES (?, ?, ?, 0)'
                    )->execute([$playerId, $eventId, 'timeout']);
                } elseif ($event['event_type'] === 'government_policy') {
                    self::applyCapabilityPenalty($playerId, $event, 5, 10);
                    $pdo->prepare(
                        'INSERT INTO player_event_responses (player_id, event_id, response_type, handled_well)
                         VALUES (?, ?, ?, 0)'
                    )->execute([$playerId, $eventId, 'timeout']);
                }
            }
        }
    }

    private static function evaluateResponse(array $player, array $event, $action)
    {
        $playerId = (int) $player['id'];
        if ($event['region_id'] && (int) $player['region_id'] !== (int) $event['region_id']) {
            return true;
        }

        if ($event['event_type'] === 'government_policy') {
            return in_array($action, ['invest', 'prepare'], true);
        }

        return in_array($action, ['protect', 'move', 'prepare', 'replan'], true);
    }

    private static function applyCapabilityPenalty(
        $playerId,
        array $event,
        $minOverride = null,
        $maxOverride = null
    ) {
        $min =(isset($minOverride) ? $minOverride : (int) $event['capability_penalty_min']);
        $max =(isset($maxOverride) ? $maxOverride : (int) $event['capability_penalty_max']);
        $penalty = random_int($min, $max);
        PlayerModel::adjustCapability($playerId, -$penalty);
    }

    private static function pickMonths($count)
    {
        $pool = range(2, 11);
        shuffle($pool);
        $months = array_slice($pool, 0, $count);
        sort($months);
        return $months;
    }

    private static function formatEvent(array $row)
    {
        $code =(isset($row['code']) ? $row['code'] : '');
        $sprite = isset($row['sprite_index']) && $row['sprite_index'] !== null
            ? (int) $row['sprite_index']
            : (array_key_exists($code, self::SPRITE_MAP) ? self::SPRITE_MAP[$code] : 8);
        return [
            'id' => (int) $row['id'],
            'room_id' => (int) $row['room_id'],
            'template_id' => (int) $row['template_id'],
            'year' => (int) $row['year'],
            'month' => (int) $row['month'],
            'event_type' => $row['event_type'],
            'code' => $code,
            'name_th' => $row['name_th'],
            'name_en' => $row['name_en'],
            'sprite_index' => $sprite,
            'region_id' => $row['region_id'] ? (int) $row['region_id'] : null,
        ];
    }
}
