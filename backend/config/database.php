<?php

$defaults = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_NAME') ?: 'cp393722_farmsim',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];

$localFile = __DIR__ . '/database.local.php';
if (is_file($localFile)) {
    return array_merge($defaults, require $localFile);
}

return $defaults;
