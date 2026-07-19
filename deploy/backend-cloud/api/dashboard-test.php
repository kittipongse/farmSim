<?php

/**
 * ทดสอบ dashboard — GET .../api/dashboard-test.php?code=ROOMCODE
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

function step($msg)
{
    echo $msg . "\n";
}

$code = isset($_GET['code']) ? strtoupper($_GET['code']) : '658PHS';

try {
    step('1 compat');
    require_once __DIR__ . '/helpers/compat.php';
    step('2 response');
    require_once __DIR__ . '/helpers/Response.php';
    step('3 game room');
    require_once __DIR__ . '/models/GameRoomModel.php';
    step('4 player');
    require_once __DIR__ . '/models/PlayerModel.php';
    step('5 room controller');
    require_once __DIR__ . '/controllers/RoomController.php';

    $room = GameRoomModel::findByCode($code);
    if (!$room) {
        step('FAIL: room not found');
        exit;
    }
    step('6 process lobby timer');
    $room = GameRoomModel::processLobbyTimer($room);

    step('7 simulation model');
    require_once __DIR__ . '/models/SimulationModel.php';
    $room = SimulationModel::tick($room);

    step('8 players');
    $players = PlayerModel::listByRoom((int) $room['id']);
    step('players=' . count($players));

    step('9 lobby seconds');
    $lobby = GameRoomModel::getLobbyRemainingSeconds($room);
    step('lobby_remaining=' . $lobby);

    step('10 simulation state');
    $state = SimulationModel::getState((int) $room['id']);
    step('state ok year=' . (isset($state['year']) ? $state['year'] : '?'));

    step('11 score model');
    $ranking = ScoreModel::getRanking((int) $room['id'], (int) $room['current_year']);
    step('ranking count=' . count($ranking));

    step('OK dashboard path works');
} catch (Exception $e) {
    step('EXCEPTION: ' . $e->getMessage());
}
