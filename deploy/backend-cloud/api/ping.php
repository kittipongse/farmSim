<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

echo json_encode(array(
    'status' => 'ok',
    'php' => PHP_VERSION,
    'time' => date('c'),
), JSON_UNESCAPED_UNICODE);
