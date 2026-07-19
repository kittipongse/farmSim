<?php

require_once __DIR__ . '/../config/Db.php';
require_once __DIR__ . '/PlayerModel.php';
require_once __DIR__ . '/GameRoomModel.php';
require_once __DIR__ . '/CropPlanModel.php';
require_once __DIR__ . '/EventModel.php';

class CardModel
{
    const DECISION_CODES = [
        'PLANT', 'WATER', 'FERTILIZE', 'PROTECT',
        'HARVEST', 'TECH', 'SOIL', 'TRADE',
    ];

    public static function listForPlayerYear($playerId, $year)
    {
        $stmt = Db::connection()->prepare(
            'SELECT card_code, month, crop_name
             FROM player_year_cards
             WHERE player_id = ? AND year = ?
             ORDER BY month ASC'
        );
        $stmt->execute([$playerId, $year]);
        return array_map(function (array $row) {
            return [
                'card_code' => $row['card_code'],
                'month' => (int) $row['month'],
                'crop_name' => $row['crop_name'],
            ];
        }, $stmt->fetchAll());
    }

    public static function assign($playerId, $year, $cardCode, $month, $cropName = null)
    {
        $room = self::assertPlayerCanEditCards($playerId, $year);
        self::assertMonthEditable($room, $month);

        $cardCode = strtoupper(trim($cardCode));
        if (!in_array($cardCode, self::DECISION_CODES, true)) {
            Response::error('การ์ดไม่ถูกต้อง', 400);
        }
        if ($month < 1 || $month > 12) {
            Response::error('เดือนไม่ถูกต้อง (1–12)', 400);
        }

        if ($cardCode === 'PLANT') {
            $cropName = trim((string) $cropName);
            if ($cropName === '') {
                Response::error('การ์ดปลูกพืชต้องระบุชื่อพืช', 400);
            }
            $player = PlayerModel::findById($playerId);
            $resolved = CropPlanModel::assertKnownCrop($player, $cropName);
            $cropName = $resolved['display_name'];
        } else {
            $cropName = null;
        }

        $pdo = Db::connection();

        $monthStmt = $pdo->prepare(
            'SELECT card_code FROM player_year_cards WHERE player_id = ? AND year = ? AND month = ?'
        );
        $monthStmt->execute([$playerId, $year, $month]);
        $existingMonth = $monthStmt->fetch();

        if ($existingMonth) {
            $pdo->prepare(
                'UPDATE player_year_cards SET card_code = ?, crop_name = ?
                 WHERE player_id = ? AND year = ? AND month = ?'
            )->execute([$cardCode, $cropName, $playerId, $year, $month]);
        } else {
            $pdo->prepare(
                'INSERT INTO player_year_cards (player_id, year, card_code, month, crop_name)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$playerId, $year, $cardCode, $month, $cropName]);
        }

        GameRoomModel::bumpVersion((int) self::playerRoomId($playerId));

        return self::listForPlayerYear($playerId, $year);
    }

    public static function unassign($playerId, $year, $month)
    {
        $room = self::assertPlayerCanEditCards($playerId, $year);
        self::assertMonthEditable($room, $month);

        if ($month < 1 || $month > 12) {
            Response::error('เดือนไม่ถูกต้อง (1–12)', 400);
        }

        $player = PlayerModel::findById($playerId);
        if (PlayerModel::hasSubmittedCards($playerId, $year) && !(bool) ((isset($player['in_plan_adjustment']) ? $player['in_plan_adjustment'] : false))) {
            Response::error('ยืนยันแผนแล้ว ไม่สามารถแก้ไขได้', 400);
        }

        Db::connection()->prepare(
            'DELETE FROM player_year_cards WHERE player_id = ? AND year = ? AND month = ?'
        )->execute([$playerId, $year, $month]);

        GameRoomModel::bumpVersion((int) self::playerRoomId($playerId));

        return self::listForPlayerYear($playerId, $year);
    }

    public static function move($playerId, $year, $fromMonth, $toMonth)
    {
        $player = PlayerModel::findById($playerId);
        if (!$player) {
            Response::error('ไม่พบผู้เล่น', 404);
        }

        $room = GameRoomModel::findById((int) $player['room_id']);
        $eventId = EventModel::resolveActiveEventId($room);
        if (!$room || $room['status'] !== 'simulating' || !$eventId) {
            Response::error('ย้ายการ์ดได้เฉพาะช่วงภัยพิบัติ', 400);
        }

        $pdo = Db::connection();
        $fromStmt = $pdo->prepare(
            'SELECT * FROM player_year_cards WHERE player_id = ? AND year = ? AND month = ?'
        );
        $fromStmt->execute([$playerId, $year, $fromMonth]);
        $card = $fromStmt->fetch();
        if (!$card) {
            Response::error('ไม่พบการ์ดในเดือนที่เลือก', 400);
        }

        $toStmt = $pdo->prepare(
            'SELECT id FROM player_year_cards WHERE player_id = ? AND year = ? AND month = ?'
        );
        $toStmt->execute([$playerId, $year, $toMonth]);
        if ($toStmt->fetch()) {
            Response::error('เดือนปลายทางมีการ์ดแล้ว', 400);
        }

        $currentMonth = (int) $room['current_month'];
        if ($fromMonth < $currentMonth || $toMonth < $currentMonth) {
            Response::error('ย้ายได้เฉพาะเดือนปัจจุบันและเดือนถัดไป', 400);
        }

        PlayerModel::adjustCoins($playerId, -15);
        PlayerModel::adjustResource($playerId, 'water', -20);

        $pdo->prepare(
            'UPDATE player_year_cards SET month = ? WHERE player_id = ? AND year = ? AND month = ?'
        )->execute([$toMonth, $playerId, $year, $fromMonth]);

        $existing = $pdo->prepare(
            'SELECT id FROM player_event_responses WHERE player_id = ? AND event_id = ?'
        );
        $existing->execute([$playerId, $eventId]);
        if (!$existing->fetch()) {
            $pdo->prepare(
                'INSERT INTO player_event_responses (player_id, event_id, response_type, handled_well)
                 VALUES (?, ?, ?, 1)'
            )->execute([$playerId, $eventId, 'move']);
        }

        GameRoomModel::bumpVersion((int) $room['id']);

        return self::listForPlayerYear($playerId, $year);
    }

    public static function submit($playerId, $year)
    {
        self::assertPlayerCanPlan($playerId, $year);

        if (PlayerModel::hasSubmittedCards($playerId, $year)) {
            Response::error('ยืนยันแผนแล้ว', 400);
        }

        $cards = self::listForPlayerYear($playerId, $year);
        if (count($cards) !== 12) {
            Response::error('ต้องวางการ์ดครบ 12 เดือนก่อนยืนยันแผน', 400);
        }

        $months = array_column($cards, 'month');
        sort($months);
        if ($months !== range(1, 12)) {
            Response::error('ต้องวางการ์ดครบทุกเดือน (1–12)', 400);
        }

        $cropPlan = CropPlanModel::validateAndSave($playerId, $year, $cards);
        $planQuality = self::applyPlanQualityPenalty($playerId, $cards);
        PlayerModel::markCardsSubmitted($playerId, $year);

        $roomId = self::playerRoomId($playerId);
        GameRoomModel::bumpVersion($roomId);

        $room = GameRoomModel::findById($roomId);
        if ($room) {
            require_once __DIR__ . '/SimulationModel.php';
            SimulationModel::tryStartSimulation($room);
        }

        $warnings = (isset($cropPlan['warnings']) ? $cropPlan['warnings'] : array());
        if (!empty($planQuality['warnings'])) {
            $warnings = array_merge($warnings, $planQuality['warnings']);
            $cropPlan['warnings'] = $warnings;
            if (empty($cropPlan['warning']) && !empty($warnings[0])) {
                $cropPlan['warning'] = $warnings[0];
            }
        }

        return [
            'cards' => $cards,
            'crop_plan' => $cropPlan,
            'plan_quality' => $planQuality,
            'submitted' => true,
            'placed_count' => 12,
        ];
    }

    /**
     * แผนแย่ (ซ้ำใบเดียว / ไม่ปลูก) ยังยืนยันได้ แต่ลดความสามารถและเตือนคะแนนต่ำ
     */
    public static function applyPlanQualityPenalty($playerId, array $cards)
    {
        $codes = array_column($cards, 'card_code');
        $unique = array_values(array_unique($codes));
        $uniqueCount = count($unique);
        $hasPlant = in_array('PLANT', $codes, true);
        $hasHarvest = in_array('HARVEST', $codes, true);
        $capabilityDelta = 0;

        if ($uniqueCount === 1) {
            $capabilityDelta = -45;
        } elseif ($uniqueCount === 2) {
            $capabilityDelta = -25;
        } elseif ($uniqueCount === 3) {
            $capabilityDelta = -10;
        }

        if (!$hasPlant) {
            $capabilityDelta -= 20;
        } elseif (!$hasHarvest) {
            $capabilityDelta -= 10;
        }

        if ($capabilityDelta !== 0) {
            PlayerModel::adjustCapability($playerId, $capabilityDelta);
        }

        return [
            'unique_card_types' => $uniqueCount,
            'capability_delta' => $capabilityDelta,
            'warnings' => array(),
        ];
    }

    public static function roomStatus($roomId, $year)
    {
        $players = PlayerModel::listByRoom($roomId);

        $list = [];
        $allReady = true;

        foreach ($players as $player) {
            $submitted = (int) ((isset($player['cards_submitted_year']) ? $player['cards_submitted_year'] : 0)) === $year;
            $countStmt = Db::connection()->prepare(
                'SELECT COUNT(*) AS cnt FROM player_year_cards WHERE player_id = ? AND year = ?'
            );
            $countStmt->execute([(int) $player['id'], $year]);
            $placed = (int) (($__row = $countStmt->fetch()) && isset($__row['cnt']) ? $__row['cnt'] : 0);

            if (!$submitted) {
                $allReady = false;
            }
            $list[] = [
                'player_id' => (int) $player['id'],
                'name' => $player['name'],
                'placed_count' => $placed,
                'submitted' => $submitted,
                'ready' => $submitted,
            ];
        }

        return [
            'year' => $year,
            'players' => $list,
            'all_ready' => $allReady && count($list) > 0,
            'player_count' => count($list),
            'months_with_cards' => self::monthsWithCards($roomId, $year),
        ];
    }

    public static function monthsWithCards($roomId, $year)
    {
        $stmt = Db::connection()->prepare(
            'SELECT pyc.month, COUNT(DISTINCT pyc.player_id) AS cnt
             FROM player_year_cards pyc
             JOIN players p ON p.id = pyc.player_id
             WHERE p.room_id = ? AND pyc.year = ?
             GROUP BY pyc.month
             ORDER BY pyc.month'
        );
        $stmt->execute([$roomId, $year]);
        $months = [];
        foreach ($stmt->fetchAll() as $row) {
            $months[(int) $row['month']] = (int) $row['cnt'];
        }
        return $months;
    }

    private static function assertPlayerCanEditCards($playerId, $year)
    {
        $player = PlayerModel::findById($playerId);
        if (!$player) {
            Response::error('ไม่พบผู้เล่น', 404);
        }

        $inAdjustment = (bool) ((isset($player['in_plan_adjustment']) ? $player['in_plan_adjustment'] : false));

        if (!$inAdjustment && PlayerModel::hasSubmittedCards($playerId, $year)) {
            Response::error('ยืนยันแผนแล้ว ไม่สามารถแก้ไขได้', 400);
        }

        $room = GameRoomModel::findById((int) $player['room_id']);
        if (!$room) {
            Response::error('ไม่พบห้องเกม', 404);
        }

        if ($inAdjustment) {
            if ($room['status'] !== 'simulating' || !EventModel::resolveActiveEventId($room)) {
                Response::error('ปรับแผนได้เฉพาะช่วงภัยพิบัติ', 400);
            }
        } elseif ($room['status'] !== 'planning') {
            Response::error('วางการ์ดได้เฉพาะช่วงวางแผน', 400);
        }

        if ((int) $room['current_year'] !== $year) {
            Response::error('ปีไม่ตรงกับเกมปัจจุบัน', 400);
        }

        return $room;
    }

    private static function assertMonthEditable(array $room, $month)
    {
        if ($room['status'] !== 'simulating') {
            return;
        }

        $currentMonth = (int) $room['current_month'];
        if ($month < $currentMonth) {
            Response::error('เดือนก่อนหน้าถูกล็อก ไม่สามารถแก้ไขได้', 400);
        }
    }

    private static function assertPlayerCanPlan($playerId, $year)
    {
        self::assertPlayerCanEditCards($playerId, $year);
    }

    private static function playerRoomId($playerId)
    {
        $player = PlayerModel::findById($playerId);
        return (int) ((isset($player['room_id']) ? $player['room_id'] : 0));
    }
}
