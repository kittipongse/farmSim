<?php

/**
 * Health check แบบเรียกไฟล์ตรง (ไม่ผ่าน router)
 * ทดสอบ: https://znix.online/farmsim/api/health.php
 */
require_once __DIR__ . '/helpers/compat.php';
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Network.php';

header('Access-Control-Allow-Origin: *');

$config = require __DIR__ . '/config/app.php';
$lanIp = Network::getLanIp();
$frontendPort = (int) (isset($config['frontend_port']) ? $config['frontend_port'] : 5173);
$suggestedUrl = isset($config['public_frontend_url'])
    ? $config['public_frontend_url']
    : sprintf('http://%s:%d', $lanIp, $frontendPort);

Response::success(array(
    'status' => 'ok',
    'app' => 'FarmSim EDU',
    'php' => PHP_VERSION,
    'lan_ip' => $lanIp,
    'suggested_frontend_url' => rtrim($suggestedUrl, '/'),
));
