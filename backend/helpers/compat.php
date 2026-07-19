<?php

/** PHP 5.6 polyfills */
if (!function_exists('random_int')) {
    function random_int($min, $max)
    {
        $min = (int) $min;
        $max = (int) $max;
        if ($min > $max) {
            throw new Exception('random_int(): Minimum value must be less than or equal to the maximum value');
        }
        return $min + mt_rand(0, $max - $min);
    }
}

if (!function_exists('random_bytes')) {
    function random_bytes($length)
    {
        $length = (int) $length;
        if ($length < 1) {
            throw new Exception('random_bytes(): Length must be greater than 0');
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes($length);
            if ($bytes !== false && strlen($bytes) === $length) {
                return $bytes;
            }
        }
        $bytes = '';
        for ($i = 0; $i < $length; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }
        return $bytes;
    }
}

/** ตรวจ MIME type รูป (รองรับ host ที่ไม่มี fileinfo) */
function farmsim_detect_mime($filePath, $fallback = '')
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            if ($mime) {
                return $mime;
            }
        }
    }
    return $fallback !== '' ? $fallback : 'application/octet-stream';
}

function farmsim_mime_from_filename($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = array(
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'pjpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    );
    return isset($map[$ext]) ? $map[$ext] : '';
}
