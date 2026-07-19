<?php

class SimulationController
{
    public static function show($params)
    {
        require_once __DIR__ . '/../models/GameRoomModel.php';
        require_once __DIR__ . '/../models/SimulationModel.php';
        require_once __DIR__ . '/RoomController.php';

        $room = self::getRoomOrFail($params['roomCode']);
        $room = GameRoomModel::processLobbyTimer($room);
        $room = SimulationModel::tick($room);

        Response::success(array(
            'room' => RoomController::formatRoom($room),
            'simulation' => SimulationModel::getState((int) $room['id']),
            'simulation_remaining_seconds' => SimulationModel::getSimulationRemainingSeconds($room),
        ));
    }

    public static function ranking($params)
    {
        require_once __DIR__ . '/../models/GameRoomModel.php';
        require_once __DIR__ . '/../models/ScoreModel.php';

        $room = self::getRoomOrFail($params['roomCode']);
        $year = isset($_GET['year']) ? (int) $_GET['year'] : (int) $room['current_year'];
        Response::success(ScoreModel::getRanking((int) $room['id'], $year));
    }

    public static function events($params)
    {
        require_once __DIR__ . '/../models/GameRoomModel.php';
        require_once __DIR__ . '/../models/EventModel.php';

        $room = self::getRoomOrFail($params['roomCode']);
        $year = isset($_GET['year']) ? (int) $_GET['year'] : (int) $room['current_year'];
        Response::success(EventModel::listForYear((int) $room['id'], $year));
    }

    public static function market($params)
    {
        require_once __DIR__ . '/../models/GameRoomModel.php';
        require_once __DIR__ . '/../models/MarketModel.php';

        $room = self::getRoomOrFail($params['roomCode']);
        Response::success(MarketModel::listPrices((int) $room['id']));
    }

    private static function getRoomOrFail($roomCode)
    {
        $room = GameRoomModel::findByCode(strtoupper($roomCode));
        if (!$room) {
            Response::error('ไม่พบห้องเกม', 404);
        }
        return $room;
    }
}
