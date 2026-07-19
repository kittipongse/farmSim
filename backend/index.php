<?php

require_once __DIR__ . '/helpers/compat.php';
require_once __DIR__ . '/helpers/Cors.php';

farmsim_send_cors_headers();

date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');
ini_set('display_errors', '0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Router.php';
require_once __DIR__ . '/helpers/Network.php';
require_once __DIR__ . '/helpers/AppPath.php';

function farmsim_controller($class, $method)
{
    return function ($params) use ($class, $method) {
        require_once __DIR__ . '/controllers/' . $class . '.php';
        call_user_func(array($class, $method), $params);
    };
}

$router = new Router();

function farmsim_health_response()
{
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
}

// Health check (รองรับ /api, /api/health, /health.php ผ่าน rewrite)
$router->get('/api/health', 'farmsim_health_response');
$router->get('/api', 'farmsim_health_response');

// Hall of Fame (Top scores)
$router->get('/api/hall-of-fame', farmsim_controller('HallOfFameController', 'top'));

// Countries
$router->get('/api/countries', farmsim_controller('CountryController', 'index'));
$router->get('/api/countries/{id}/regions', farmsim_controller('CountryController', 'regions'));
$router->get('/api/regions/{id}/suitable-crops', farmsim_controller('CountryController', 'crops'));
$router->get('/api/regions/{id}/planting-guide', farmsim_controller('CountryController', 'plantingGuide'));

// Rooms
$router->post('/api/rooms/create', farmsim_controller('RoomController', 'create'));
$router->get('/api/rooms/{roomCode}', farmsim_controller('RoomController', 'show'));
$router->get('/api/rooms/{roomCode}/status', farmsim_controller('RoomController', 'status'));
$router->post('/api/rooms/{roomCode}/join', farmsim_controller('RoomController', 'join'));
$router->post('/api/rooms/{roomCode}/extend-lobby', farmsim_controller('RoomController', 'extendLobby'));
$router->post('/api/rooms/{roomCode}/start', farmsim_controller('RoomController', 'startGame'));
$router->post('/api/rooms/{roomCode}/cancel', farmsim_controller('RoomController', 'cancel'));
$router->get('/api/rooms/{roomCode}/cards/status', farmsim_controller('RoomController', 'cardsStatus'));

// Players
$router->get('/api/players/{id}', farmsim_controller('PlayerController', 'show'));
$router->get('/api/uploads/{filename}', farmsim_controller('UploadController', 'serve'));
$router->get('/api/players/{id}/resources', farmsim_controller('PlayerController', 'resources'));
$router->post('/api/players/{id}/select-region', farmsim_controller('PlayerController', 'selectRegion'));
$router->post('/api/players/{id}/upload-profile', farmsim_controller('PlayerController', 'uploadProfile'));
$router->get('/api/players/{id}/cards/{year}', farmsim_controller('PlayerController', 'cardsForYear'));
$router->post('/api/players/{id}/validate-crop', farmsim_controller('PlayerController', 'validateCrop'));
$router->post('/api/players/{id}/cards/assign', farmsim_controller('PlayerController', 'assignCard'));
$router->post('/api/players/{id}/cards/unassign', farmsim_controller('PlayerController', 'unassignCard'));
$router->post('/api/players/{id}/cards/submit', farmsim_controller('PlayerController', 'submitCards'));
$router->post('/api/players/{id}/cards/move', farmsim_controller('PlayerController', 'moveCard'));
$router->post('/api/players/{id}/events/respond', farmsim_controller('PlayerController', 'respondEvent'));
$router->post('/api/players/{id}/bonus-quiz/answer', farmsim_controller('PlayerController', 'answerBonusQuiz'));
$router->post('/api/players/{id}/bonus-quiz/activity', farmsim_controller('PlayerController', 'pingBonusQuizActivity'));
$router->post('/api/players/{id}/presentation/submit', farmsim_controller('PlayerController', 'submitPresentation'));
$router->get('/api/players/{id}/presentation/status', farmsim_controller('PlayerController', 'presentationStatus'));
$router->post('/api/rooms/{roomCode}/presentation/complete', farmsim_controller('RoomController', 'completePresentation'));
$router->post('/api/players/{id}/plan-adjustment/start', farmsim_controller('PlayerController', 'startPlanAdjustment'));
$router->post('/api/players/{id}/plan-adjustment/finish', farmsim_controller('PlayerController', 'finishPlanAdjustment'));
$router->post('/api/players/{id}/plan-adjustment/cancel', farmsim_controller('PlayerController', 'cancelPlanAdjustment'));
$router->get('/api/players/{id}/crop-plans/{year}', farmsim_controller('PlayerController', 'cropPlans'));
$router->get('/api/players/{id}/game-review', farmsim_controller('PlayerController', 'gameReview'));

// Dashboard
$router->get('/api/dashboard/{roomCode}', farmsim_controller('DashboardController', 'show'));
$router->get('/api/dashboard/{roomCode}/ranking', farmsim_controller('SimulationController', 'ranking'));
$router->get('/api/dashboard/{roomCode}/events', farmsim_controller('SimulationController', 'events'));
$router->get('/api/dashboard/{roomCode}/market', farmsim_controller('SimulationController', 'market'));
$router->get('/api/rooms/{roomCode}/simulation', farmsim_controller('SimulationController', 'show'));

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
$uri = AppPath::requestApiPath();

try {
    $router->dispatch($method, $uri);
} catch (PDOException $e) {
    Response::error('ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    Response::error('เกิดข้อผิดพลาดภายในระบบ: ' . $e->getMessage(), 500);
}
