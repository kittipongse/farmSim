<?php

/**
 * ทดสอบสร้างห้อง — ลบหลังแก้เสร็จ
 * GET https://znix.online/farmsim/api/room-test.php
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

function step($msg)
{
    echo $msg . "\n";
}

try {
    step('1 compat');
    require __DIR__ . '/helpers/compat.php';
    step('2 router');
    require __DIR__ . '/helpers/Router.php';
    step('3 db');
    require __DIR__ . '/config/Db.php';
    step('4 game room model');
    require __DIR__ . '/models/GameRoomModel.php';
    step('5 country model');
    require __DIR__ . '/models/CountryModel.php';

    $countryId = 1;
    if (!CountryModel::find($countryId)) {
        step('FAIL: country not found');
        exit;
    }
    step('6 create room');
    $room = GameRoomModel::create($countryId);
    if (!$room) {
        step('FAIL: create returned null');
        exit;
    }
    step('OK room_code=' . $room['room_code']);
} catch (Exception $e) {
    step('EXCEPTION: ' . $e->getMessage());
}
