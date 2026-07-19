<?php

require_once __DIR__ . '/../models/GameRoomModel.php';

class RoomController
{
    public static function create($params)
    {
        $body = json_body();
        $countryId = (int) (isset($body['country_id']) ? $body['country_id'] : 0);
        if ($countryId <= 0) {
            Response::error('กรุณาเลือกประเทศ', 400);
        }

        require_once __DIR__ . '/../models/CountryModel.php';
        if (!CountryModel::find($countryId)) {
            Response::error('ไม่พบประเทศที่เลือก', 404);
        }

        $room = GameRoomModel::create($countryId);
        if (!$room) {
            Response::error('สร้างห้องไม่สำเร็จ กรุณาลองใหม่', 500);
        }

        Response::success(self::formatRoom($room), 'สร้างห้องเกมสำเร็จ');
    }

    public static function show($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        $room = self::getRoomOrFail($params['roomCode']);
        $room = GameRoomModel::processLobbyTimer($room);
        $players = PlayerModel::listByRoom((int) $room['id']);

        Response::success(array(
            'room' => self::formatRoom($room),
            'players' => array_map(array('PlayerModel', 'formatForRoomList'), $players),
        ));
    }

    public static function status($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        $room = self::getRoomOrFail($params['roomCode']);
        $room = GameRoomModel::processLobbyTimer($room);
        $players = PlayerModel::listByRoom((int) $room['id']);

        $payload = array(
            'room' => self::formatRoom($room),
            'players' => array_map(array('PlayerModel', 'formatForRoomList'), $players),
            'lobby_remaining_seconds' => GameRoomModel::getLobbyRemainingSeconds($room),
            'countdown_remaining_seconds' => GameRoomModel::getCountdownRemainingSeconds($room),
            'simulation_remaining_seconds' => 0,
        );

        if (!in_array($room['status'], array('lobby', 'countdown'), true)) {
            require_once __DIR__ . '/../models/SimulationModel.php';
            $room = SimulationModel::tick($room);
            $payload['room'] = self::formatRoom($room);
            $payload['simulation_remaining_seconds'] = SimulationModel::getSimulationRemainingSeconds($room);
            $payload['breaking_news_remaining_seconds'] = SimulationModel::getBreakingNewsRemainingSeconds($room);
            $payload['simulation'] = SimulationModel::getState((int) $room['id']);
            require_once __DIR__ . '/../models/ScoreModel.php';
            $payload['ranking'] = ScoreModel::getRanking(
                (int) $room['id'],
                $room['status'] === 'finished' ? null : (int) $room['current_year']
            );
        }

        Response::success($payload);
    }

    public static function join($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        $room = self::getRoomOrFail($params['roomCode']);
        $body = json_body();
        $name = isset($body['name']) ? $body['name'] : '';
        $pin = isset($body['pin']) ? $body['pin'] : '';

        if ($pin !== $room['pin']) {
            Response::error('Game PIN ไม่ถูกต้อง', 400);
        }

        $player = PlayerModel::create((int) $room['id'], $name);
        Response::success(array(
            'player' => PlayerModel::formatPublic($player),
            'room' => self::formatRoom(GameRoomModel::findByCode($room['room_code'])),
        ), 'เข้าร่วมเกมสำเร็จ');
    }

    public static function extendLobby($params)
    {
        Response::error('ระบบขยายเวลา Lobby ถูกปิดแล้ว — กด「เริ่มเกม」เมื่อพร้อม', 400);
    }

    public static function startGame($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        $room = self::getRoomOrFail($params['roomCode']);
        $room = GameRoomModel::startGame((int) $room['id']);
        $players = PlayerModel::listByRoom((int) $room['id']);

        Response::success(array(
            'room' => self::formatRoom($room),
            'players' => array_map(array('PlayerModel', 'formatForRoomList'), $players),
        ), 'เริ่มเกมแล้ว — เข้าสู่ช่วงวางแผน');
    }

    public static function cardsStatus($params)
    {
        require_once __DIR__ . '/../models/CardModel.php';
        $room = self::getRoomOrFail($params['roomCode']);
        $room = GameRoomModel::processLobbyTimer($room);
        $year = (int) $room['current_year'];

        Response::success(CardModel::roomStatus((int) $room['id'], $year));
    }

    public static function cancel($params)
    {
        $room = self::getRoomOrFail($params['roomCode']);
        if (!in_array($room['status'], array('lobby', 'countdown'), true)) {
            Response::error('ไม่สามารถยกเลิกห้องในสถานะนี้ได้', 400);
        }
        GameRoomModel::cancelRoom((int) $room['id']);
        Response::success(null, 'ยกเลิกห้องแล้ว');
    }

    public static function completePresentation($params)
    {
        require_once __DIR__ . '/../models/PresentationModel.php';
        $room = self::getRoomOrFail($params['roomCode']);
        if ($room['status'] !== 'finished') {
            Response::error('แสดงผลได้เมื่อเกมจบแล้วเท่านั้น', 400);
        }
        $ok = PresentationModel::completeCurrent((int) $room['id']);
        Response::success([
            'completed' => $ok,
            'presentation' => PresentationModel::getStateForRoom((int) $room['id']),
        ], $ok ? 'แสดงผลเสร็จแล้ว' : 'ไม่มีรายการที่กำลังแสดง');
    }

    private static function getRoomOrFail($roomCode)
    {
        $room = GameRoomModel::findByCode(strtoupper($roomCode));
        if (!$room) {
            Response::error('ไม่พบห้องเกม', 404);
        }
        return $room;
    }

    public static function formatRoom($room)
    {
        return array(
            'id' => (int) $room['id'],
            'room_code' => $room['room_code'],
            'pin' => $room['pin'],
            'country_id' => (int) $room['country_id'],
            'country_name_th' => isset($room['country_name_th']) ? $room['country_name_th'] : null,
            'country_name_en' => isset($room['country_name_en']) ? $room['country_name_en'] : null,
            'country_code' => isset($room['country_code']) ? $room['country_code'] : null,
            'status' => $room['status'],
            'player_count' => (int) $room['player_count'],
            'max_players' => 8,
            'current_year' => (int) $room['current_year'],
            'current_month' => (int) $room['current_month'],
            'version' => (int) $room['version'],
            'lobby_started_at' => $room['lobby_started_at'],
            'countdown_started_at' => $room['countdown_started_at'],
            'simulation_pause_until' => isset($room['simulation_pause_until']) ? $room['simulation_pause_until'] : null,
            'breaking_news_until' => isset($room['breaking_news_until']) ? $room['breaking_news_until'] : null,
            'current_event_id' => isset($room['current_event_id']) ? (int) $room['current_event_id'] : null,
        );
    }
}
