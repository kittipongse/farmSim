<?php

class DashboardController
{
    public static function show($params)
    {
        require_once __DIR__ . '/../models/GameRoomModel.php';
        require_once __DIR__ . '/../models/PlayerModel.php';
        require_once __DIR__ . '/RoomController.php';

        $room = GameRoomModel::findByCode(strtoupper($params['roomCode']));
        if (!$room) {
            Response::error('ไม่พบห้องเกม', 404);
        }

        $room = GameRoomModel::processLobbyTimer($room);
        $players = PlayerModel::listByRoom((int) $room['id']);

        $playerList = array();
        foreach ($players as $p) {
            $resources = PlayerModel::getResources((int) $p['id']);
            $formatted = PlayerModel::formatForRoomList($p);
            $formatted['resources'] = $resources ? array(
                'coins' => (int) $resources['coins'],
                'workforce' => (int) $resources['workforce'],
                'water' => (int) $resources['water'],
                'agricultural_capability' => (int) $p['agricultural_capability'],
            ) : null;
            $playerList[] = $formatted;
        }

        $payload = array(
            'room' => RoomController::formatRoom($room),
            'players' => $playerList,
            'lobby_remaining_seconds' => GameRoomModel::getLobbyRemainingSeconds($room),
            'countdown_remaining_seconds' => GameRoomModel::getCountdownRemainingSeconds($room),
            'simulation_remaining_seconds' => 0,
            'simulation' => null,
            'ranking' => array(),
            'game_summary' => null,
        );

        if (in_array($room['status'], array('lobby', 'countdown'), true)) {
            Response::success($payload);
        }

        require_once __DIR__ . '/../models/SimulationModel.php';
        require_once __DIR__ . '/../models/ScoreModel.php';
        $room = SimulationModel::tick($room);
        require_once __DIR__ . '/../models/PresentationModel.php';
        PresentationModel::processTick($room);
        $payload['room'] = RoomController::formatRoom($room);
        $payload['simulation_remaining_seconds'] = SimulationModel::getSimulationRemainingSeconds($room);
        $payload['breaking_news_remaining_seconds'] = SimulationModel::getBreakingNewsRemainingSeconds($room);
        $payload['simulation'] = SimulationModel::getState((int) $room['id']);

        $payload['ranking'] = $room['status'] === 'finished'
            ? ScoreModel::getRanking((int) $room['id'], null)
            : ScoreModel::getRanking((int) $room['id'], (int) $room['current_year']);

        // จบเกมบน Dashboard แสดงอันดับ + คิวส่งผลแสดงบนจอ
        $payload['game_summary'] = null;
        require_once __DIR__ . '/../models/PresentationModel.php';
        $payload['presentation'] = PresentationModel::getStateForRoom((int) $room['id']);

        Response::success($payload);
    }
}
