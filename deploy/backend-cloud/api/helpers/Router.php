<?php

class Router
{
    /** @var array<int, array<string, mixed>> */
    private $routes = [];

    public function get($pattern, callable $handler)
    {
        $this->addRoute('GET', $pattern, $handler);
    }

    public function post($pattern, callable $handler)
    {
        $this->addRoute('POST', $pattern, $handler);
    }

    private function addRoute($method, $pattern, callable $handler)
    {
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = compact('method', 'pattern', 'regex', 'handler');
    }

    public function dispatch($method, $uri)
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === null || $path === false) {
            $path = '/';
        }
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match('#^' . $route['regex'] . '$#', $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                call_user_func($route['handler'], $params);
                return;
            }
        }

        Response::error('ไม่พบ API ที่ต้องการ', 404);
    }
}

function json_body()
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function generate_room_code()
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function generate_pin()
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function generate_session_token()
{
    return bin2hex(random_bytes(32));
}
