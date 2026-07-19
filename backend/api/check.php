<?php

header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');

echo 'PHP ' . PHP_VERSION . "\n";

try {
    require __DIR__ . '/config/Db.php';
    Db::connection()->query('SELECT 1');
    echo "DB OK\n";
} catch (Exception $e) {
    echo 'DB FAIL: ' . $e->getMessage() . "\n";
}
