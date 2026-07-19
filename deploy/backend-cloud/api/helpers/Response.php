<?php

class Response
{
    public static function json($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        echo json_encode($data, $flags);
        exit;
    }

    public static function success($data = null, $message = 'สำเร็จ')
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public static function error($message, $status = 400, $errors = null)
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
