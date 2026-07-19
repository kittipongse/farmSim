<?php

class AppPath
{
    public static function base()
    {
        $dir = dirname((isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : ''));
        $dir = str_replace('\\', '/', $dir);
        if ($dir === '/' || $dir === '.') {
            return '';
        }
        return rtrim($dir, '/');
    }

    public static function uploadUrl()
    {
        $base = self::base();
        return ($base === '' ? '' : $base) . '/uploads/';
    }

    /** Path สำหรับ router เช่น /api/health */
    public static function requestApiPath()
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        if ($path === null || $path === false) {
            $path = '/';
        }

        $scriptDir = str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : ''));

        if ($scriptDir !== '/' && $scriptDir !== '' && strpos($path, $scriptDir) === 0) {
            $path = substr($path, strlen($scriptDir));
            if ($path === '' || $path === false) {
                $path = '/';
            }
        }

        if ($path === '/index.php' || strpos($path, '/index.php/') === 0) {
            $path = substr($path, strlen('/index.php'));
            if ($path === '' || $path === false) {
                $path = '/';
            }
        }

        if (substr($path, -4) === '.php') {
            $path = substr($path, 0, -4);
            if ($path === '') {
                $path = '/';
            }
        }

        if (substr($scriptDir, -4) === '/api' && strpos($path, '/api') !== 0) {
            $path = '/api' . ($path === '/' ? '' : $path);
        }

        return $path;
    }
}
