<?php

function farmsim_send_cors_headers()
{
    if (headers_sent()) {
        return;
    }
    header('Access-Control-Allow-Origin: *', true);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS', true);
    header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept, X-Requested-With', true);
    header('Access-Control-Max-Age: 86400', true);
}
