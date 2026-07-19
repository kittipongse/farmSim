<?php

require_once __DIR__ . '/../config/Db.php';
require_once __DIR__ . '/GameRoomModel.php';
require_once __DIR__ . '/PlayerModel.php';
require_once __DIR__ . '/CropPlanModel.php';
require_once __DIR__ . '/EventModel.php';
require_once __DIR__ . '/MarketModel.php';
require_once __DIR__ . '/ScoreModel.php';
require_once __DIR__ . '/BonusQuizModel.php';
require_once __DIR__ . '/../helpers/AppConfig.php';

class SimulationModel
{
    public static function tick(array $room)
    {
        if ($room['status'] === 'planning') {
            self::tryStartSimulation($room);
            $__room = GameRoomModel::findById((int) $room['id']);
            return $__room ? $__room : $room;
        }

        if ($room['status'] !== 'simulating') {
            return $room;
        }

        $room = self::resolveStuckOverlays($room);
        $room = BonusQuizModel::recoverOrphanQuiz($room);
        $room = self::processBreakingNewsPhase($room);
        $room = BonusQuizModel::processActivePhase($room);

        if (!self::shouldAdvanceMonth($room)) {
            return $room;
        }

        self::runMonth((int) $room['id']);
        $__room = GameRoomModel::findById((int) $room['id']);
        return $__room ? $__room : $room;
    }

    public static function tryStartSimulation(array $room)
    {
        $roomId = (int) $room['id'];
        $year = (int) $room['current_year'];

        if (!self::allPlayersSubmitted($roomId, $year)) {
            return;
        }

        $pdo = Db::connection();
        EventModel::ensureYearEvents($roomId, $year, (int) $room['country_id']);
        MarketModel::initRoomMarket($roomId, (int) $room['country_id']);

        $month = (int) $room['current_month'];
        $event = EventModel::getForMonth($roomId, $year, $month);
        self::startMonthPhase($roomId, $month, $event);
    }

    private static function startMonthPhase($roomId, $month, $event)
    {
        $pdo = Db::connection();
        $monthSeconds = AppConfig::simulationMonthSeconds();

        if ($event) {
            $breakingSeconds = AppConfig::breakingNewsSeconds();
            $pdo->prepare(
                'UPDATE game_rooms
                 SET status = ?, simulation_started_at = COALESCE(simulation_started_at, NOW()),
                     breaking_news_until = DATE_ADD(NOW(), INTERVAL ? SECOND),
                     month_timer_remaining = ?,
                     simulation_pause_until = NULL,
                     current_event_id = ?, current_bonus_quiz_id = NULL, bonus_quiz_reveal_until = NULL, version = version + 1
                 WHERE id = ?'
            )->execute([
                'simulating',
                $breakingSeconds,
                $monthSeconds,
                (int) $event['id'],
                $roomId,
            ]);
            return;
        }

        $room = GameRoomModel::findById($roomId);
        if ($room && BonusQuizModel::shouldStartForRoom($room, $month)) {
            BonusQuizModel::startForRoom($roomId, (int) $room['current_year'], $month);
            return;
        }

        $pdo->prepare(
            'UPDATE game_rooms
             SET status = ?, simulation_started_at = COALESCE(simulation_started_at, NOW()),
                 breaking_news_until = NULL, month_timer_remaining = NULL,
                 simulation_pause_until = DATE_ADD(NOW(), INTERVAL ? SECOND),
                 current_event_id = NULL, current_bonus_quiz_id = NULL, bonus_quiz_reveal_until = NULL, version = version + 1
             WHERE id = ?'
        )->execute(['simulating', $monthSeconds, $roomId]);
    }

    public static function allPlayersSubmitted($roomId, $year)
    {
        $players = PlayerModel::listByRoom($roomId);
        if (count($players) === 0) {
            return false;
        }

        foreach ($players as $player) {
            if ((int) ((isset($player['cards_submitted_year']) ? $player['cards_submitted_year'] : 0)) !== $year) {
                return false;
            }
        }
        return true;
    }

    public static function shouldAdvanceMonth(array $room)
    {
        if (!empty($room['breaking_news_until'])) {
            if (self::getBreakingNewsRemainingSeconds($room) > 0) {
                return false;
            }
        }

        if (!empty($room['current_bonus_quiz_id'])) {
            return false;
        }

        if (!empty($room['bonus_quiz_reveal_until'])) {
            return false;
        }

        if (empty($room['simulation_pause_until'])) {
            return false;
        }

        $stmt = Db::connection()->prepare(
            'SELECT NOW() >= simulation_pause_until AS due FROM game_rooms WHERE id = ?'
        );
        $stmt->execute([(int) $room['id']]);
        $__row = $stmt->fetch(); return (bool) (isset($__row['due']) ? $__row['due'] : false);
    }

    public static function processBreakingNewsPhase(array $room)
    {
        if (empty($room['breaking_news_until'])) {
            return $room;
        }

        $roomId = (int) $room['id'];
        $remaining = (int) ((isset($room['month_timer_remaining']) ? $room['month_timer_remaining'] : 0));
        if ($remaining <= 0) {
            $remaining = AppConfig::simulationMonthSeconds();
        }

        $pdo = Db::connection();
        $stmt = $pdo->prepare(
            'UPDATE game_rooms
             SET breaking_news_until = NULL,
                 simulation_pause_until = DATE_ADD(NOW(), INTERVAL ? SECOND),
                 version = version + 1
             WHERE id = ? AND breaking_news_until IS NOT NULL AND breaking_news_until <= NOW()'
        );
        $stmt->execute([$remaining, $roomId]);
        if ($stmt->rowCount() === 0) {
            return $room;
        }

        $__room = GameRoomModel::findById($roomId);
        if ($__room && BonusQuizModel::shouldStartForRoom($__room, (int) $__room['current_month'])) {
            BonusQuizModel::startForRoom($roomId, (int) $__room['current_year'], (int) $__room['current_month']);
            $__room = GameRoomModel::findById($roomId);
        }
        return $__room ? $__room : $room;
    }

    /**
     * แก้สถานะ overlay ค้าง / ทับซ้อน (Breaking News + Quiz)
     */
    private static function resolveStuckOverlays(array $room)
    {
        $roomId = (int) $room['id'];
        $changed = false;

        if (!empty($room['breaking_news_until'])) {
            $remaining = self::getBreakingNewsRemainingSeconds($room);
            if ($remaining <= 0) {
                return self::processBreakingNewsPhase($room);
            }
        }

        // Quiz เริ่มแล้ว — ปิด Breaking News ที่ค้าง (ไม่ลบ quiz เพราะจะทำให้เดือนนั้นตอบ quiz ไม่ได้อีก)
        if (!empty($room['current_bonus_quiz_id']) && !empty($room['breaking_news_until'])) {
            Db::connection()->prepare(
                'UPDATE game_rooms SET breaking_news_until = NULL, version = version + 1 WHERE id = ?'
            )->execute([$roomId]);
            $changed = true;
        }

        if (!empty($room['bonus_quiz_reveal_until'])) {
            $stmt = Db::connection()->prepare(
                'SELECT NOW() >= bonus_quiz_reveal_until AS due FROM game_rooms WHERE id = ?'
            );
            $stmt->execute([$roomId]);
            $row = $stmt->fetch();
            if ((bool) (isset($row['due']) ? $row['due'] : false) && !empty($room['current_bonus_quiz_id'])) {
                BonusQuizModel::processActivePhase($room);
                $changed = true;
            }
        }

        if ($changed) {
            $__room = GameRoomModel::findById($roomId);
            return $__room ? $__room : $room;
        }

        return $room;
    }

    public static function runMonth($roomId)
    {
        $pdo = Db::connection();
        $claim = $pdo->prepare(
            'UPDATE game_rooms
             SET simulation_pause_until = NULL, version = version + 1
             WHERE id = ? AND status = \'simulating\'
               AND breaking_news_until IS NULL
               AND current_bonus_quiz_id IS NULL
               AND bonus_quiz_reveal_until IS NULL
               AND simulation_pause_until IS NOT NULL
               AND simulation_pause_until <= NOW()'
        );
        $claim->execute([$roomId]);
        if ($claim->rowCount() === 0) {
            return;
        }

        $room = GameRoomModel::findById($roomId);
        if (!$room || $room['status'] !== 'simulating') {
            return;
        }

        $year = (int) $room['current_year'];
        $month = (int) $room['current_month'];
        $pdo = Db::connection();

        if (!empty($room['current_event_id'])) {
            EventModel::applyPendingPenalties($roomId, (int) $room['current_event_id']);
        }

        $players = PlayerModel::listByRoom($roomId);
        $logs = [];

        foreach ($players as $player) {
            $playerId = (int) $player['id'];
            $card = self::getPlayerCardForMonth($playerId, $year, $month);
            $result = self::applyMonthForPlayer($playerId, $room, $year, $month, $card);
            $logs[] = ['player_id' => $playerId, 'result' => $result];
        }

        $pdo->prepare(
            'INSERT INTO game_logs (room_id, action, detail)
             VALUES (?, ?, ?)'
        )->execute([
            $roomId,
            'month_simulated',
            json_encode(['year' => $year, 'month' => $month, 'players' => $logs], JSON_UNESCAPED_UNICODE),
        ]);

        self::advanceTimeline($room);
    }

    private static function applyMonthForPlayer(
        $playerId,
        array $room,
        $year,
        $month,
        $card
    ) {
        $roomId = (int) $room['id'];
        $summary = ['card' => (isset($card['card_code']) ? $card['card_code'] : 'AUTO'), 'effects' => []];

        if (!$card) {
            self::applyAutoMonth($playerId);
            $summary['effects'][] = 'auto_run';
            return $summary;
        }

        $code = $card['card_code'];

        switch ($code) {
            case 'PLANT':
                $summary['effects'][] = self::applyPlant($playerId, $year, $month);
                break;
            case 'WATER':
                PlayerModel::adjustCoins($playerId, -30);
                PlayerModel::adjustResource($playerId, 'water', 25);
                $summary['effects'][] = 'water_up';
                break;
            case 'FERTILIZE':
                PlayerModel::adjustCoins($playerId, -40);
                PlayerModel::adjustResource($playerId, 'sustainability', -5);
                $summary['effects'][] = 'fertilize';
                break;
            case 'PROTECT':
                PlayerModel::adjustCoins($playerId, -25);
                $summary['effects'][] = 'protect';
                break;
            case 'HARVEST':
                $summary['effects'][] = self::applyHarvest($playerId, $year, $month);
                break;
            case 'TECH':
                PlayerModel::adjustCoins($playerId, -80);
                PlayerModel::adjustResource($playerId, 'tech_level', 1, 5);
                $summary['effects'][] = 'tech';
                break;
            case 'SOIL':
                PlayerModel::adjustCoins($playerId, -35);
                PlayerModel::adjustResource($playerId, 'soil_quality', 15, 100);
                PlayerModel::adjustResource($playerId, 'sustainability', 5, 100);
                $summary['effects'][] = 'soil';
                break;
            case 'TRADE':
                $summary['effects'][] = MarketModel::sellPlayerStock($playerId, $roomId, $year, $month);
                break;
        }

        return $summary;
    }

    private static function applyAutoMonth($playerId)
    {
        PlayerModel::adjustResource($playerId, 'water', 5, 100);
        $pdo = Db::connection();
        $pdo->prepare(
            'UPDATE player_crop_plans SET status = \'growing\'
             WHERE player_id = ? AND status = \'planned\''
        )->execute([$playerId]);
    }

    private static function applyPlant($playerId, $year, $month)
    {
        PlayerModel::adjustCoins($playerId, -10);
        PlayerModel::adjustResource($playerId, 'water', -5);

        $pdo = Db::connection();
        $pdo->prepare(
            'UPDATE player_crop_plans SET status = \'growing\'
             WHERE player_id = ? AND year = ? AND plant_month = ?'
        )->execute([$playerId, $year, $month]);

        return 'planted';
    }

    private static function applyHarvest($playerId, $year, $month)
    {
        $player = PlayerModel::findById($playerId);
        $capability = (int) ((isset($player['agricultural_capability']) ? $player['agricultural_capability'] : 100));
        $resources = PlayerModel::getResources($playerId);

        $pdo = Db::connection();
        $stmt = $pdo->prepare(
            'SELECT pcp.*, c.capability_bonus, c.base_coin
             FROM player_crop_plans pcp
             LEFT JOIN crops c ON c.id = pcp.crop_id
             WHERE pcp.player_id = ? AND pcp.year = ? AND pcp.harvest_month = ?
               AND pcp.status IN (\'growing\', \'planned\')'
        );
        $stmt->execute([$playerId, $year, $month]);
        $plans = $stmt->fetchAll();

        if (!$plans) {
            return ['harvested' => 0];
        }

        $totalYield = 0;
        $lastMeta = null;
        $soil = (int) ((isset($resources['soil_quality']) ? $resources['soil_quality'] : 70));
        $stock = (int) ((isset($resources['stock_amount']) ? $resources['stock_amount'] : 0));

        foreach ($plans as $plan) {
            $mismatchReason = (isset($plan['mismatch_reason']) ? $plan['mismatch_reason'] : null);
            $seasonMatch = (bool) ((isset($plan['season_match']) ? $plan['season_match'] : true));

            $coinMult = 0.0;
            if ($plan['crop_id'] && $player['region_id']) {
                require_once __DIR__ . '/CropModel.php';
                $rate = CropModel::getRegionRate((int) $plan['crop_id'], (int) $player['region_id']);
                if ($rate) {
                    $coinMult = (float) $rate['coin_multiplier'];
                }
            }

            $yieldMult = CropPlanModel::yieldMultiplier($coinMult, $mismatchReason, $seasonMatch);
            $yield = (int) round((50 + (int) floor($soil / 5)) * ($capability / 100) * $yieldMult);
            if ($yield < 0) {
                $yield = 0;
            }

            $pdo->prepare(
                'UPDATE player_crop_plans SET status = \'harvested\', yield_amount = ? WHERE id = ?'
            )->execute([$yield, (int) $plan['id']]);

            $totalYield += $yield;
            $lastMeta = [
                'region_match' => (bool) ((isset($plan['region_match']) ? $plan['region_match'] : true)),
                'season_match' => $seasonMatch,
                'mismatch_reason' => $mismatchReason,
                'coin_multiplier' => $coinMult,
            ];
        }

        PlayerModel::setResourceField($playerId, 'stock_amount', $stock + $totalYield);

        $result = ['harvested' => $totalYield];
        if ($lastMeta) {
            $result = array_merge($result, $lastMeta);
        }
        return $result;
    }

    private static function getPlayerCardForMonth($playerId, $year, $month)
    {
        $stmt = Db::connection()->prepare(
            'SELECT card_code, month, crop_name FROM player_year_cards
             WHERE player_id = ? AND year = ? AND month = ?'
        );
        $stmt->execute([$playerId, $year, $month]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return [
            'card_code' => $row['card_code'],
            'month' => (int) $row['month'],
            'crop_name' => $row['crop_name'],
        ];
    }

    private static function advanceTimeline(array $room)
    {
        $roomId = (int) $room['id'];
        $year = (int) $room['current_year'];
        $month = (int) $room['current_month'];
        $maxYears = (int) AppConfig::get('game_years', 5);
        $pdo = Db::connection();

        $nextMonth = $month + 1;

        if ($nextMonth > 12) {
            ScoreModel::calculateYearScores($roomId, $year);

            if ($year >= $maxYears) {
                ScoreModel::calculateFinalScores($roomId);
                require_once __DIR__ . '/HallOfFameModel.php';
                HallOfFameModel::recordFromRoom($roomId);
                $pdo->prepare(
                    'UPDATE game_rooms SET status = ?, version = version + 1,
                     simulation_started_at = NULL, simulation_pause_until = NULL,
                     breaking_news_until = NULL, month_timer_remaining = NULL,
                     current_event_id = NULL, current_bonus_quiz_id = NULL, bonus_quiz_reveal_until = NULL
                     WHERE id = ?'
                )->execute(['finished', $roomId]);
                return;
            }

            $newYear = $year + 1;
            $pdo->prepare(
                'UPDATE players SET cards_submitted_year = NULL WHERE room_id = ?'
            )->execute([$roomId]);

            EventModel::ensureYearEvents($roomId, $newYear, (int) $room['country_id']);

            $pdo->prepare(
                'UPDATE game_rooms SET status = ?, current_year = ?, current_month = 1,
                 simulation_started_at = NULL, simulation_pause_until = NULL,
                 breaking_news_until = NULL, month_timer_remaining = NULL,
                 current_event_id = NULL, current_bonus_quiz_id = NULL, bonus_quiz_reveal_until = NULL, version = version + 1
                 WHERE id = ?'
            )->execute(['planning', $newYear, $roomId]);
            return;
        }

        $event = EventModel::getForMonth($roomId, $year, $nextMonth);
        $pdo->prepare(
            'UPDATE game_rooms SET current_month = ?, version = version + 1 WHERE id = ?'
        )->execute([$nextMonth, $roomId]);
        self::startMonthPhase($roomId, $nextMonth, $event);
    }

    public static function getState($roomId)
    {
        $room = GameRoomModel::findById($roomId);
        if (!$room) {
            return [];
        }

        $year = (int) $room['current_year'];
        $month = (int) $room['current_month'];
        $event = null;

        if (!empty($room['current_event_id'])) {
            $event = EventModel::getById((int) $room['current_event_id']);
        } elseif ($room['status'] === 'simulating') {
            $event = EventModel::getForMonth($roomId, $year, $month);
        }

        $remaining = self::getSimulationRemainingSeconds($room);
        $breakingRemaining = self::getBreakingNewsRemainingSeconds($room);
        $breakingActive = $breakingRemaining > 0
            && !empty($room['breaking_news_until'])
            && empty($room['current_bonus_quiz_id'])
            && empty($room['bonus_quiz_reveal_until']);

        $rankYear = $room['status'] === 'finished' ? null : $year;

        return [
            'year' => $year,
            'month' => $month,
            'status' => $room['status'],
            'pause_remaining_seconds' => $remaining,
            'breaking_news_active' => $breakingActive,
            'breaking_news_remaining_seconds' => $breakingRemaining,
            'month_timer_remaining' => (int) ((isset($room['month_timer_remaining']) ? $room['month_timer_remaining'] : 0)),
            'current_event' => $event,
            'bonus_quiz' => BonusQuizModel::getStateForRoom($room),
            'ranking' => ScoreModel::getRanking($roomId, $rankYear),
            'events' => EventModel::listForYear($roomId, $year),
            'market' => MarketModel::listPrices($roomId),
        ];
    }

    public static function getBreakingNewsRemainingSeconds(array $room)
    {
        if ($room['status'] !== 'simulating' || empty($room['breaking_news_until'])) {
            return 0;
        }
        $stmt = Db::connection()->prepare(
            'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), breaking_news_until)) AS remaining
             FROM game_rooms WHERE id = ?'
        );
        $stmt->execute([(int) $room['id']]);
        $__row = $stmt->fetch();
        return (int) (isset($__row['remaining']) ? $__row['remaining'] : 0);
    }

    public static function getSimulationRemainingSeconds(array $room)
    {
        if ($room['status'] !== 'simulating') {
            return 0;
        }

        if (!empty($room['breaking_news_until'])) {
            $breakingLeft = self::getBreakingNewsRemainingSeconds($room);
            if ($breakingLeft > 0) {
                $frozen = (int) ((isset($room['month_timer_remaining']) ? $room['month_timer_remaining'] : 0));
                return $frozen > 0 ? $frozen : AppConfig::simulationMonthSeconds();
            }
        }

        if (!empty($room['current_bonus_quiz_id'])) {
            $frozen = (int) ((isset($room['month_timer_remaining']) ? $room['month_timer_remaining'] : 0));
            return $frozen > 0 ? $frozen : AppConfig::simulationMonthSeconds();
        }

        if (empty($room['simulation_pause_until'])) {
            return 0;
        }
        $stmt = Db::connection()->prepare(
            'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), simulation_pause_until)) AS remaining
             FROM game_rooms WHERE id = ?'
        );
        $stmt->execute([(int) $room['id']]);
        $__row = $stmt->fetch(); return (int) (isset($__row['remaining']) ? $__row['remaining'] : 0);
    }
}
