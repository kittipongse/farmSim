<?php

class PlayerController
{
    public static function show($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        require_once __DIR__ . '/../models/GameRoomModel.php';
        require_once __DIR__ . '/../models/EventModel.php';
        require_once __DIR__ . '/../models/BonusQuizModel.php';
        $playerId = (int) $params['id'];
        $player = self::getPlayerOrFail($playerId);
        $resources = PlayerModel::getResources($playerId);

        $eventResponse = null;
        $bonusQuizAnswer = null;
        $room = GameRoomModel::findById((int) $player['room_id']);
        if ($room && $room['status'] === 'simulating') {
            $eventId = EventModel::resolveActiveEventId($room);
            if ($eventId) {
                $eventResponse = EventModel::getPlayerResponse($playerId, $eventId);
            }
            if (!empty($room['current_bonus_quiz_id'])) {
                $bonusQuizAnswer = BonusQuizModel::getPlayerAnswer(
                    $playerId,
                    (int) $room['current_bonus_quiz_id']
                );
            }
        }

        Response::success(array(
            'player' => PlayerModel::formatPublic($player),
            'resources' => $resources ? self::formatResources($resources) : null,
            'event_response' => $eventResponse,
            'bonus_quiz_answer' => $bonusQuizAnswer,
        ));
    }

    public static function resources($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        $player = self::getPlayerOrFail((int) $params['id']);
        $resources = PlayerModel::getResources((int) $player['id']);
        if (!$resources) {
            Response::error('ไม่พบข้อมูลทรัพยากร', 404);
        }
        Response::success(self::formatResources($resources));
    }

    public static function selectRegion($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        $playerId = (int) $params['id'];
        $body = json_body();
        $regionId = (int) (isset($body['region_id']) ? $body['region_id'] : 0);
        if ($regionId <= 0) {
            Response::error('กรุณาเลือกภูมิภาค', 400);
        }

        $player = PlayerModel::selectRegion($playerId, $regionId);
        $resources = PlayerModel::getResources($playerId);

        Response::success(array(
            'player' => PlayerModel::formatPublic($player),
            'resources' => $resources ? self::formatResources($resources) : null,
        ), 'เลือกภูมิภาคสำเร็จ');
    }

    public static function cardsForYear($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        require_once __DIR__ . '/../models/CardModel.php';
        $playerId = (int) $params['id'];
        $year = (int) (isset($params['year']) ? $params['year'] : 0);
        self::getPlayerOrFail($playerId);

        if ($year <= 0) {
            Response::error('ปีไม่ถูกต้อง', 400);
        }

        $cards = CardModel::listForPlayerYear($playerId, $year);
        Response::success(array(
            'year' => $year,
            'cards' => $cards,
            'placed_count' => count($cards),
            'required_count' => 12,
        ));
    }

    public static function validateCrop($params)
    {
        require_once __DIR__ . '/../models/CropPlanModel.php';
        $playerId = (int) $params['id'];
        $body = json_body();
        $cropName = isset($body['crop_name']) ? $body['crop_name'] : '';

        self::getPlayerOrFail($playerId);

        $result = CropPlanModel::validateCropName($playerId, $cropName);
        Response::success($result);
    }

    public static function assignCard($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        require_once __DIR__ . '/../models/CardModel.php';
        $playerId = (int) $params['id'];
        $body = json_body();
        $year = (int) (isset($body['year']) ? $body['year'] : 0);
        $month = (int) (isset($body['month']) ? $body['month'] : 0);
        $cardCode = isset($body['card_code']) ? $body['card_code'] : '';
        $cropName = isset($body['crop_name']) ? $body['crop_name'] : null;

        self::getPlayerOrFail($playerId);

        if ($year <= 0) {
            Response::error('ปีไม่ถูกต้อง', 400);
        }

        $cards = CardModel::assign($playerId, $year, $cardCode, $month, $cropName);
        Response::success(array(
            'year' => $year,
            'cards' => $cards,
            'placed_count' => count($cards),
        ), 'วางการ์ดสำเร็จ');
    }

    public static function unassignCard($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        require_once __DIR__ . '/../models/CardModel.php';
        $playerId = (int) $params['id'];
        $body = json_body();
        $year = (int) (isset($body['year']) ? $body['year'] : 0);
        $month = (int) (isset($body['month']) ? $body['month'] : 0);

        self::getPlayerOrFail($playerId);

        if ($year <= 0) {
            Response::error('ปีไม่ถูกต้อง', 400);
        }

        $cards = CardModel::unassign($playerId, $year, $month);
        Response::success(array(
            'year' => $year,
            'cards' => $cards,
            'placed_count' => count($cards),
        ), 'ยกเลิกการวางการ์ดแล้ว');
    }

    public static function submitCards($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        require_once __DIR__ . '/../models/CardModel.php';
        $playerId = (int) $params['id'];
        $body = json_body();
        $year = (int) (isset($body['year']) ? $body['year'] : 0);

        self::getPlayerOrFail($playerId);

        if ($year <= 0) {
            Response::error('ปีไม่ถูกต้อง', 400);
        }

        $result = CardModel::submit($playerId, $year);
        Response::success($result, 'ยืนยันแผนปีสำเร็จ');
    }

    public static function moveCard($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        require_once __DIR__ . '/../models/CardModel.php';
        $playerId = (int) $params['id'];
        $body = json_body();
        $year = (int) (isset($body['year']) ? $body['year'] : 0);
        $fromMonth = (int) (isset($body['from_month']) ? $body['from_month'] : 0);
        $toMonth = (int) (isset($body['to_month']) ? $body['to_month'] : 0);

        self::getPlayerOrFail($playerId);

        if ($year <= 0) {
            Response::error('ปีไม่ถูกต้อง', 400);
        }

        $cards = CardModel::move($playerId, $year, $fromMonth, $toMonth);
        Response::success(array('year' => $year, 'cards' => $cards), 'ย้ายการ์ดสำเร็จ');
    }

    public static function respondEvent($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        require_once __DIR__ . '/../models/EventModel.php';
        $playerId = (int) $params['id'];
        $body = json_body();
        $eventId = (int) (isset($body['event_id']) ? $body['event_id'] : 0);
        $action = isset($body['action']) ? $body['action'] : '';

        self::getPlayerOrFail($playerId);

        if ($eventId <= 0 || $action === '') {
            Response::error('ข้อมูลไม่ครบ', 400);
        }

        $result = EventModel::respond($playerId, $eventId, $action);
        Response::success($result, 'บันทึกการตอบสนองแล้ว');
    }

    public static function answerBonusQuiz($params)
    {
        require_once __DIR__ . '/../models/BonusQuizModel.php';
        $playerId = (int) $params['id'];
        $body = json_body();
        $answer = isset($body['answer']) ? $body['answer'] : '';

        self::getPlayerOrFail($playerId);
        $result = BonusQuizModel::submitAnswer($playerId, $answer);
        Response::success($result, 'ส่งคำตอบแล้ว');
    }

    public static function pingBonusQuizActivity($params)
    {
        require_once __DIR__ . '/../models/BonusQuizModel.php';
        $playerId = (int) $params['id'];
        self::getPlayerOrFail($playerId);
        $result = BonusQuizModel::recordActivity($playerId);
        Response::success($result, 'บันทึกกิจกรรมแล้ว');
    }

    public static function submitPresentation($params)
    {
        require_once __DIR__ . '/../models/PresentationModel.php';
        $playerId = (int) $params['id'];
        self::getPlayerOrFail($playerId);
        $result = PresentationModel::submit($playerId);
        Response::success($result, 'ส่งผลเข้าคิวแสดงบนจอแล้ว');
    }

    public static function presentationStatus($params)
    {
        require_once __DIR__ . '/../models/PresentationModel.php';
        $playerId = (int) $params['id'];
        self::getPlayerOrFail($playerId);
        Response::success(PresentationModel::getPlayerStatus($playerId));
    }

    public static function startPlanAdjustment($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        $playerId = (int) $params['id'];
        self::getPlayerOrFail($playerId);
        $player = PlayerModel::startPlanAdjustment($playerId);
        Response::success(array(
            'player' => PlayerModel::formatPublic($player),
        ), 'เริ่มปรับแผนกิจกรรม — แก้ไขได้ตั้งแต่เดือนปัจจุบัน');
    }

    public static function finishPlanAdjustment($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        require_once __DIR__ . '/../models/CardModel.php';
        require_once __DIR__ . '/../models/GameRoomModel.php';
        $playerId = (int) $params['id'];
        self::getPlayerOrFail($playerId);
        $player = PlayerModel::finishPlanAdjustment($playerId);
        $room = GameRoomModel::findById((int) $player['room_id']);
        $year = $room ? (int) $room['current_year'] : 1;
        $cards = CardModel::listForPlayerYear($playerId, $year);
        Response::success(array(
            'player' => PlayerModel::formatPublic($player),
            'cards' => $cards,
        ), 'บันทึกการปรับแผนแล้ว');
    }

    public static function cancelPlanAdjustment($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        $playerId = (int) $params['id'];
        self::getPlayerOrFail($playerId);
        $player = PlayerModel::cancelPlanAdjustment($playerId);
        Response::success(array(
            'player' => PlayerModel::formatPublic($player),
        ), 'ยกเลิกการปรับแผน');
    }

    public static function cropPlans($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        require_once __DIR__ . '/../models/CropPlanModel.php';
        $playerId = (int) $params['id'];
        $year = (int) (isset($params['year']) ? $params['year'] : 0);
        self::getPlayerOrFail($playerId);

        if ($year <= 0) {
            Response::error('ปีไม่ถูกต้อง', 400);
        }

        Response::success(array(
            'year' => $year,
            'plans' => CropPlanModel::listForPlayerYear($playerId, $year),
        ));
    }

    public static function gameReview($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        require_once __DIR__ . '/../models/GameSummaryModel.php';
        require_once __DIR__ . '/../models/GameRoomModel.php';
        $playerId = (int) $params['id'];
        $player = self::getPlayerOrFail($playerId);
        $room = GameRoomModel::findById((int) $player['room_id']);
        if (!$room || $room['status'] !== 'finished') {
            Response::error('ดูสรุปผลได้เมื่อเกมจบแล้ว', 400);
        }

        Response::success(GameSummaryModel::buildReviewForPlayer($playerId));
    }

    public static function uploadProfile($params)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        require_once __DIR__ . '/../models/GameRoomModel.php';
        $playerId = (int) $params['id'];
        self::getPlayerOrFail($playerId);

        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        if (strpos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true);
            if (is_array($body) && !empty($body['image_base64'])) {
                self::saveProfileFromBase64(
                    $playerId,
                    $body['image_base64'],
                    isset($body['filename']) ? $body['filename'] : 'photo.jpg',
                    isset($body['mime_type']) ? $body['mime_type'] : 'image/jpeg'
                );
                return;
            }
        }

        if (!isset($_FILES['profile']) || $_FILES['profile']['error'] !== UPLOAD_ERR_OK) {
            $code = isset($_FILES['profile']['error']) ? (int) $_FILES['profile']['error'] : UPLOAD_ERR_NO_FILE;
            if ($code === UPLOAD_ERR_NO_FILE || $code === UPLOAD_ERR_OK) {
                Response::error('กรุณาอัปโหลดรูปภาพ — ถ้าใช้มือถือ ลองถ่ายใหม่หรือเลือกรูปจากแกลเลอรี', 400);
            }
            Response::error('อัปโหลดรูปไม่สำเร็จ (รหัส ' . $code . ')', 400);
        }

        $file = $_FILES['profile'];
        $clientType = isset($file['type']) ? $file['type'] : '';
        $mime = farmsim_detect_mime($file['tmp_name'], $clientType);
        if ($mime === 'application/octet-stream' && !empty($file['name'])) {
            $fromName = farmsim_mime_from_filename($file['name']);
            if ($fromName !== '') {
                $mime = $fromName;
            }
        }
        if ($mime === 'application/octet-stream' && $clientType !== '') {
            $mime = $clientType;
        }

        self::saveProfileFromTempFile($playerId, $file['tmp_name'], $mime, true);
    }

    private static function saveProfileFromBase64($playerId, $base64, $filename, $clientType)
    {
        $base64 = preg_replace('/\s+/', '', $base64);
        if (strpos($base64, ',') !== false) {
            $base64 = substr($base64, strrpos($base64, ',') + 1);
        }
        $data = base64_decode($base64, true);
        if ($data === false || $data === '') {
            Response::error('ข้อมูลรูปไม่ถูกต้อง', 400);
        }
        if (strlen($data) > 5 * 1024 * 1024) {
            Response::error('รูปใหญ่เกินไป (สูงสุด 5 MB)', 400);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pf');
        if ($tmp === false || file_put_contents($tmp, $data) === false) {
            Response::error('ประมวลผลรูปไม่สำเร็จ', 500);
        }

        $mime = farmsim_detect_mime($tmp, $clientType);
        if ($mime === 'application/octet-stream' && $filename !== '') {
            $fromName = farmsim_mime_from_filename($filename);
            if ($fromName !== '') {
                $mime = $fromName;
            }
        }
        if ($mime === 'application/octet-stream' && $clientType !== '') {
            $mime = $clientType;
        }

        self::saveProfileFromTempFile($playerId, $tmp, $mime, false);
    }

    private static function saveProfileFromTempFile($playerId, $tmpPath, $mime, $isUploadedFile)
    {
        require_once __DIR__ . '/../models/PlayerModel.php';
        require_once __DIR__ . '/../models/GameRoomModel.php';

        $allowed = array('image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/jpg', 'image/pjpeg');
        if ($mime === 'image/jpg' || $mime === 'image/pjpeg') {
            $mime = 'image/jpeg';
        }

        if (!in_array($mime, $allowed, true)) {
            if (!$isUploadedFile && is_file($tmpPath)) {
                @unlink($tmpPath);
            }
            if (in_array($mime, array('image/heic', 'image/heif'), true)) {
                Response::error('รูป HEIC จาก iPhone ยังไม่รองรับ — ตั้งค่ากล้องเป็น "Most Compatible" (JPEG) แล้วถ่ายใหม่', 400);
            }
            Response::error('รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, WebP, GIF)', 400);
        }

        $ext = 'jpg';
        if ($mime === 'image/png') {
            $ext = 'png';
        } elseif ($mime === 'image/webp') {
            $ext = 'webp';
        } elseif ($mime === 'image/gif') {
            $ext = 'gif';
        }

        $config = require __DIR__ . '/../config/app.php';
        $uploadDir = $config['upload_dir'];
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true)) {
                if (!$isUploadedFile && is_file($tmpPath)) {
                    @unlink($tmpPath);
                }
                Response::error('สร้างโฟลเดอร์ uploads ไม่สำเร็จ', 500);
            }
        }
        if (!is_writable($uploadDir)) {
            if (!$isUploadedFile && is_file($tmpPath)) {
                @unlink($tmpPath);
            }
            Response::error('โฟลเดอร์ uploads บนเซิร์ฟเวอร์เขียนไม่ได้', 500);
        }

        $filename = 'player_' . $playerId . '_' . time() . '.' . $ext;
        $dest = $uploadDir . $filename;

        $saved = false;
        if ($isUploadedFile) {
            $saved = move_uploaded_file($tmpPath, $dest);
        } else {
            $saved = rename($tmpPath, $dest);
            if (!$saved) {
                $saved = copy($tmpPath, $dest);
                @unlink($tmpPath);
            }
        }

        if (!$saved) {
            Response::error('อัปโหลดรูปไม่สำเร็จ', 500);
        }

        $player = PlayerModel::updateProfileImage($playerId, $filename);
        GameRoomModel::bumpVersion((int) $player['room_id']);

        Response::success(array(
            'player' => PlayerModel::formatPublic($player),
            'profile_url' => AppPath::uploadUrl() . $filename,
        ), 'อัปโหลดรูปโปรไฟล์สำเร็จ');
    }

    private static function getPlayerOrFail($id)
    {
        $player = PlayerModel::findById($id);
        if (!$player) {
            Response::error('ไม่พบผู้เล่น', 404);
        }
        return $player;
    }

    private static function formatResources($r)
    {
        return array(
            'coins' => (int) $r['coins'],
            'workforce' => (int) $r['workforce'],
            'water' => (int) $r['water'],
            'soil_quality' => (int) $r['soil_quality'],
            'tech_level' => (int) $r['tech_level'],
            'stock_amount' => (int) $r['stock_amount'],
            'sustainability' => (int) $r['sustainability'],
            'env_impact' => (int) $r['env_impact'],
            'knowledge_score' => (int) $r['knowledge_score'],
        );
    }
}
