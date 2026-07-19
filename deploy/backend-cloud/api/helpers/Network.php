<?php

class Network
{
    public static function getLanIp()
    {
        if (function_exists('socket_create')) {
            $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($sock) {
                @socket_connect($sock, '8.8.8.8', 53);
                if (@socket_getsockname($sock, $ip) && $ip && $ip !== '127.0.0.1') {
                    socket_close($sock);
                    return $ip;
                }
                socket_close($sock);
            }
        }

        $hostname = gethostname();
        if ($hostname) {
            $ip = gethostbyname($hostname);
            if ($ip && $ip !== $hostname && $ip !== '127.0.0.1') {
                return $ip;
            }
        }

        return '127.0.0.1';
    }
}
