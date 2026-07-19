<?php

class UploadController
{
    public static function serve($params)
    {
        $filename = isset($params['filename']) ? basename($params['filename']) : '';
        if ($filename === '' || !preg_match('/^player_\d+_\d+\.(jpe?g|png|webp|gif)$/i', $filename)) {
            http_response_code(404);
            exit;
        }

        $config = require __DIR__ . '/../config/app.php';
        $path = $config['upload_dir'] . $filename;
        if (!is_file($path) || !is_readable($path)) {
            http_response_code(404);
            exit;
        }

        $mime = farmsim_detect_mime($path, '');
        if ($mime === 'application/octet-stream') {
            $fromName = farmsim_mime_from_filename($filename);
            if ($fromName !== '') {
                $mime = $fromName;
            }
        }
        if ($mime === 'image/jpg' || $mime === 'image/pjpeg') {
            $mime = 'image/jpeg';
        }
        if (strpos($mime, 'image/') !== 0) {
            http_response_code(404);
            exit;
        }

        require_once __DIR__ . '/../helpers/Cors.php';
        farmsim_send_cors_headers();
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
