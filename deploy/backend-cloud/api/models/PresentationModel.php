<?php

require_once __DIR__ . '/../config/Db.php';
require_once __DIR__ . '/GameRoomModel.php';
require_once __DIR__ . '/PlayerModel.php';
require_once __DIR__ . '/GameSummaryModel.php';
require_once __DIR__ . '/ScoreModel.php';
require_once __DIR__ . '/../helpers/AppConfig.php';
require_once __DIR__ . '/../helpers/Response.php';

class PresentationModel
{
    public static function presentationSeconds()
    {
        return (int) AppConfig::get('presentation_seconds', 45);
    }

    public static function submit($playerId)
    {
        $player = PlayerModel::findById($playerId);
        if (!$player) {
            Response::error('ไม่พบผู้เล่น', 404);
        }

        $roomId = (int) $player['room_id'];
        $room = GameRoomModel::findById($roomId);
        if (!$room || $room['status'] !== 'finished') {
            Response::error('ส่งผลได้เมื่อเกมจบแล้วเท่านั้น', 400);
        }

        $pdo = Db::connection();
        $existing = self::findQueueRow($roomId, $playerId);
        if ($existing) {
            return self::formatSubmitResponse($roomId, $playerId, $existing);
        }

        $pdo->prepare(
            'INSERT INTO room_presentation_queue (room_id, player_id, status)
             VALUES (?, ?, ?)'
        )->execute([$roomId, $playerId, 'queued']);

        GameRoomModel::bumpVersion($roomId);
        self::tryStartNext($roomId);

        $row = self::findQueueRow($roomId, $playerId);
        return self::formatSubmitResponse($roomId, $playerId, $row);
    }

    public static function getPlayerStatus($playerId)
    {
        $player = PlayerModel::findById($playerId);
        if (!$player) {
            Response::error('ไม่พบผู้เล่น', 404);
        }

        $roomId = (int) $player['room_id'];
        $row = self::findQueueRow($roomId, $playerId);
        if (!$row) {
            return [
                'submitted' => false,
                'status' => null,
                'queue_position' => null,
                'queue_total' => self::countQueued($roomId),
            ];
        }

        return self::formatSubmitResponse($roomId, $playerId, $row);
    }

    public static function processTick(array $room)
    {
        if ($room['status'] !== 'finished') {
            return $room;
        }

        $roomId = (int) $room['id'];
        $current = self::findPresenting($roomId);
        if (!$current) {
            self::tryStartNext($roomId);
            return $room;
        }

        if (empty($current['started_at'])) {
            return $room;
        }

        $seconds = self::presentationSeconds();
        $stmt = Db::connection()->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, started_at, NOW()) >= ? AS due
             FROM room_presentation_queue WHERE id = ?'
        );
        $stmt->execute([$seconds, (int) $current['id']]);
        $row = $stmt->fetch();
        if ((bool) (isset($row['due']) ? $row['due'] : false)) {
            self::completeCurrent($roomId);
        }

        return $room;
    }

    public static function completeCurrent($roomId)
    {
        $current = self::findPresenting((int) $roomId);
        if (!$current) {
            return false;
        }

        $pdo = Db::connection();
        $pdo->prepare(
            'UPDATE room_presentation_queue
             SET status = ?, finished_at = NOW()
             WHERE id = ? AND status = ?'
        )->execute(['done', (int) $current['id'], 'presenting']);

        GameRoomModel::bumpVersion((int) $roomId);
        self::tryStartNext((int) $roomId);
        return true;
    }

    public static function getStateForRoom($roomId)
    {
        $roomId = (int) $roomId;
        $current = self::findPresenting($roomId);
        $queue = self::listQueue($roomId);

        $payload = [
            'active' => (bool) $current,
            'current' => null,
            'queue' => $queue,
            'queue_waiting' => self::countWaiting($roomId),
            'presentation_seconds' => self::presentationSeconds(),
        ];

        if ($current) {
            $payload['current'] = self::buildPresentationPayload((int) $current['player_id'], $roomId);
            $payload['current']['queue_id'] = (int) $current['id'];
            $payload['current']['started_at'] = $current['started_at'];
        }

        return $payload;
    }

    private static function tryStartNext($roomId)
    {
        $roomId = (int) $roomId;
        if (self::findPresenting($roomId)) {
            return;
        }

        $pdo = Db::connection();
        $stmt = $pdo->prepare(
            'SELECT id, player_id FROM room_presentation_queue
             WHERE room_id = ? AND status = ?
             ORDER BY submitted_at ASC, id ASC
             LIMIT 1'
        );
        $stmt->execute([$roomId, 'queued']);
        $next = $stmt->fetch();
        if (!$next) {
            return;
        }

        $pdo->prepare(
            'UPDATE room_presentation_queue
             SET status = ?, started_at = NOW()
             WHERE id = ? AND status = ?'
        )->execute(['presenting', (int) $next['id'], 'queued']);

        GameRoomModel::bumpVersion($roomId);
    }

    private static function buildPresentationPayload($playerId, $roomId)
    {
        $player = PlayerModel::findById($playerId);
        if (!$player) {
            return null;
        }

        $summary = GameSummaryModel::buildForPlayer($playerId, $roomId, $player);
        $ranking = ScoreModel::getRanking($roomId, null);
        $myRank = null;
        foreach ($ranking as $row) {
            if ((int) $row['player_id'] === $playerId) {
                $myRank = $row;
                break;
            }
        }

        $pdo = Db::connection();
        $cardStmt = $pdo->prepare(
            'SELECT month, card_code, crop_name FROM player_year_cards
             WHERE player_id = ? AND year = (
               SELECT MAX(year) FROM player_year_cards WHERE player_id = ?
             )
             ORDER BY month ASC'
        );
        $cardStmt->execute([$playerId, $playerId]);
        $cardsByMonth = [];
        while ($row = $cardStmt->fetch()) {
            $cardsByMonth[] = [
                'month' => (int) $row['month'],
                'card_code' => $row['card_code'],
                'crop_name' => $row['crop_name'],
            ];
        }

        $rank = $myRank ? (int) $myRank['rank'] : null;

        return [
            'player_id' => $playerId,
            'name' => $player['name'],
            'profile_image' => isset($player['profile_image']) ? $player['profile_image'] : null,
            'region_name_th' => isset($player['region_name_th']) ? $player['region_name_th'] : null,
            'agricultural_capability' => (int) $player['agricultural_capability'],
            'rank' => $rank,
            'is_winner' => $rank === 1,
            'total_score' => $myRank ? (int) $myRank['total_score'] : 0,
            'score' => isset($summary['score']) ? $summary['score'] : null,
            'strengths' => array_slice(isset($summary['strengths']) ? $summary['strengths'] : [], 0, 6),
            'mistakes' => array_slice(isset($summary['mistakes']) ? $summary['mistakes'] : [], 0, 6),
            'advice' => array_slice(isset($summary['advice']) ? $summary['advice'] : [], 0, 4),
            'card_plan' => isset($summary['card_plan']) ? $summary['card_plan'] : null,
            'cards_by_month' => $cardsByMonth,
            'total_yield' => isset($summary['total_yield']) ? $summary['total_yield'] : 0,
            'resources' => isset($summary['resources']) ? $summary['resources'] : null,
        ];
    }

    private static function listQueue($roomId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT q.id, q.player_id, q.status, q.submitted_at, q.started_at,
                    p.name, p.profile_image
             FROM room_presentation_queue q
             JOIN players p ON p.id = q.player_id
             WHERE q.room_id = ?
             ORDER BY q.submitted_at ASC, q.id ASC'
        );
        $stmt->execute([(int) $roomId]);
        $items = [];
        $position = 0;
        while ($row = $stmt->fetch()) {
            if ($row['status'] === 'queued') {
                $position++;
            }
            $items[] = [
                'queue_id' => (int) $row['id'],
                'player_id' => (int) $row['player_id'],
                'player_name' => $row['name'],
                'profile_image' => $row['profile_image'],
                'status' => $row['status'],
                'queue_position' => $row['status'] === 'queued' ? $position : null,
                'submitted_at' => $row['submitted_at'],
            ];
        }
        return $items;
    }

    private static function findQueueRow($roomId, $playerId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT * FROM room_presentation_queue
             WHERE room_id = ? AND player_id = ? LIMIT 1'
        );
        $stmt->execute([(int) $roomId, (int) $playerId]);
        $row = $stmt->fetch();
        return $row ? $row : null;
    }

    private static function findPresenting($roomId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT * FROM room_presentation_queue
             WHERE room_id = ? AND status = ? LIMIT 1'
        );
        $stmt->execute([(int) $roomId, 'presenting']);
        $row = $stmt->fetch();
        return $row ? $row : null;
    }

    private static function countQueued($roomId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT COUNT(*) FROM room_presentation_queue
             WHERE room_id = ? AND status = ?'
        );
        $stmt->execute([(int) $roomId, 'queued']);
        return (int) $stmt->fetchColumn();
    }

    private static function countWaiting($roomId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT COUNT(*) FROM room_presentation_queue
             WHERE room_id = ? AND status IN (?, ?)'
        );
        $stmt->execute([(int) $roomId, 'queued', 'presenting']);
        return (int) $stmt->fetchColumn();
    }

    private static function formatSubmitResponse($roomId, $playerId, $row)
    {
        if (!$row) {
            return [
                'submitted' => false,
                'status' => null,
                'queue_position' => null,
                'queue_total' => self::countQueued($roomId),
            ];
        }

        $position = null;
        if ($row['status'] === 'queued') {
            $stmt = Db::connection()->prepare(
                'SELECT COUNT(*) FROM room_presentation_queue
                 WHERE room_id = ? AND status = ?
                   AND (submitted_at < ? OR (submitted_at = ? AND id <= ?))'
            );
            $stmt->execute([
                (int) $roomId,
                'queued',
                $row['submitted_at'],
                $row['submitted_at'],
                (int) $row['id'],
            ]);
            $position = (int) $stmt->fetchColumn();
        }

        return [
            'submitted' => true,
            'status' => $row['status'],
            'queue_position' => $position,
            'queue_total' => self::countQueued($roomId),
            'is_presenting' => $row['status'] === 'presenting',
            'is_done' => $row['status'] === 'done',
        ];
    }
}
