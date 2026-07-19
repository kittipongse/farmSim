<?php

require_once __DIR__ . '/../config/Db.php';
require_once __DIR__ . '/GameRoomModel.php';
require_once __DIR__ . '/PlayerModel.php';
require_once __DIR__ . '/../helpers/AppConfig.php';
require_once __DIR__ . '/../helpers/Response.php';

class BonusQuizModel
{
    public static function isBonusMonth($month)
    {
        $months = AppConfig::bonusQuizMonths();
        return in_array((int) $month, $months, true);
    }

    public static function shouldStartForRoom(array $room, $month)
    {
        if ($room['status'] !== 'simulating') {
            return false;
        }
        if (!self::isBonusMonth($month)) {
            return false;
        }
        if (!empty($room['current_bonus_quiz_id'])) {
            return false;
        }

        $stmt = Db::connection()->prepare(
            'SELECT id FROM room_bonus_quizzes
             WHERE room_id = ? AND year = ? AND month = ?
               AND status IN (\'active\', \'revealing\')
             LIMIT 1'
        );
        $stmt->execute([(int) $room['id'], (int) $room['current_year'], (int) $month]);
        return !$stmt->fetch();
    }

    public static function startForRoom($roomId, $year, $month)
    {
        $room = GameRoomModel::findById($roomId);
        if (!$room || !self::shouldStartForRoom($room, $month)) {
            return;
        }

        if (self::isBreakingNewsActive($room)) {
            return;
        }

        $question = self::pickRandomQuestion();
        if (!$question) {
            return;
        }

        $pdo = Db::connection();
        $hasActivityCol = self::hasActivityColumn($pdo);
        if ($hasActivityCol) {
            $pdo->prepare(
                'INSERT INTO room_bonus_quizzes
                 (room_id, year, month, question_th, correct_answer, answer_type, status, last_activity_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $roomId,
                $year,
                $month,
                $question['question_th'],
                $question['correct_answer'],
                $question['answer_type'],
                'active',
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO room_bonus_quizzes
                 (room_id, year, month, question_th, correct_answer, answer_type, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $roomId,
                $year,
                $month,
                $question['question_th'],
                $question['correct_answer'],
                $question['answer_type'],
                'active',
            ]);
        }
        $quizId = (int) $pdo->lastInsertId();

        $monthSeconds = AppConfig::simulationMonthSeconds();
        $frozen = (int) ((isset($room['month_timer_remaining']) ? $room['month_timer_remaining'] : 0));
        if ($frozen <= 0 && !empty($room['simulation_pause_until'])) {
            $stmt = $pdo->prepare(
                'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), simulation_pause_until)) AS remaining
                 FROM game_rooms WHERE id = ?'
            );
            $stmt->execute([$roomId]);
            $__row = $stmt->fetch();
            $frozen = (int) (isset($__row['remaining']) ? $__row['remaining'] : 0);
        }
        if ($frozen <= 0) {
            $frozen = $monthSeconds;
        }

        $pdo->prepare(
            'UPDATE game_rooms
             SET current_bonus_quiz_id = ?,
                 breaking_news_until = NULL,
                 simulation_pause_until = NULL,
                 month_timer_remaining = ?,
                 version = version + 1
             WHERE id = ?'
        )->execute([$quizId, $frozen, $roomId]);
    }

    /** กู้ quiz ที่ status=active แต่ current_bonus_quiz_id หลุด (เคยถูก clear ผิด) */
    public static function recoverOrphanQuiz(array $room)
    {
        if ($room['status'] !== 'simulating' || !empty($room['current_bonus_quiz_id'])) {
            return $room;
        }
        if (!empty($room['breaking_news_until']) && self::isBreakingNewsActive($room)) {
            return $room;
        }
        if (!self::isBonusMonth((int) $room['current_month'])) {
            return $room;
        }

        $roomId = (int) $room['id'];
        $year = (int) $room['current_year'];
        $month = (int) $room['current_month'];
        $stmt = Db::connection()->prepare(
            'SELECT id FROM room_bonus_quizzes
             WHERE room_id = ? AND year = ? AND month = ? AND status = ?
             LIMIT 1'
        );
        $stmt->execute([$roomId, $year, $month, 'active']);
        $row = $stmt->fetch();
        if (!$row) {
            return $room;
        }

        Db::connection()->prepare(
            'UPDATE game_rooms
             SET current_bonus_quiz_id = ?,
                 breaking_news_until = NULL,
                 simulation_pause_until = NULL,
                 version = version + 1
             WHERE id = ?'
        )->execute([(int) $row['id'], $roomId]);

        $__room = GameRoomModel::findById($roomId);
        return $__room ? $__room : $room;
    }

    public static function processActivePhase(array $room)
    {
        if (empty($room['current_bonus_quiz_id'])) {
            return $room;
        }

        $quizId = (int) $room['current_bonus_quiz_id'];
        $quiz = self::findById($quizId);
        if (!$quiz) {
            return self::clearQuizState((int) $room['id']);
        }

        // เฟสประกาศผลผู้ตอบถูก — ยังหยุดนับเวลาเดือนไว้
        if ($quiz['status'] === 'revealing' || !empty($room['bonus_quiz_reveal_until'])) {
            return self::processRevealPhase($room, $quizId);
        }

        if ($quiz['status'] !== 'active') {
            return self::clearQuizState((int) $room['id']);
        }

        $answersCount = self::countAnswers($quizId);
        if ($answersCount === 0 && self::shouldIdleClose($quiz)) {
            return self::closeAndReveal((int) $room['id'], $quizId);
        }

        if (self::allPlayersAnswered($quizId, (int) $room['id'])) {
            return self::closeAndReveal((int) $room['id'], $quizId);
        }

        if (self::isQuizExpired($quiz)) {
            return self::closeAndReveal((int) $room['id'], $quizId);
        }

        return $room;
    }

    public static function recordActivity($playerId)
    {
        $player = PlayerModel::findById($playerId);
        if (!$player) {
            Response::error('ไม่พบผู้เล่น', 404);
        }

        $room = GameRoomModel::findById((int) $player['room_id']);
        if (!$room || $room['status'] !== 'simulating' || empty($room['current_bonus_quiz_id'])) {
            Response::error('ขณะนี้ไม่มีโบนัสทายปัญหา', 400);
        }

        $quizId = (int) $room['current_bonus_quiz_id'];
        $quiz = self::findById($quizId);
        if (!$quiz || $quiz['status'] !== 'active') {
            Response::error('โบนัสทายปัญหาจบแล้ว', 400);
        }

        if (self::getPlayerAnswer($playerId, $quizId)) {
            return ['quiz' => self::formatForClient($quiz, (int) $room['id'], $room)];
        }

        self::touchQuizActivity($quizId);
        GameRoomModel::bumpVersion((int) $room['id']);
        $quiz = self::findById($quizId);
        $room = GameRoomModel::findById((int) $room['id']);

        return ['quiz' => self::formatForClient($quiz, (int) $room['id'], $room)];
    }

    private static function isBreakingNewsActive(array $room)
    {
        if (empty($room['breaking_news_until'])) {
            return false;
        }

        $stmt = Db::connection()->prepare(
            'SELECT breaking_news_until > NOW() AS active FROM game_rooms WHERE id = ?'
        );
        $stmt->execute([(int) $room['id']]);
        $row = $stmt->fetch();
        return (bool) (isset($row['active']) ? $row['active'] : false);
    }

    private static function hasActivityColumn($pdo = null)
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $pdo = $pdo ? $pdo : Db::connection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?'
        );
        $stmt->execute(['room_bonus_quizzes', 'last_activity_at']);
        $cached = (int) $stmt->fetchColumn() > 0;
        return $cached;
    }

    private static function touchQuizActivity($quizId)
    {
        if (!self::hasActivityColumn()) {
            return;
        }

        Db::connection()->prepare(
            'UPDATE room_bonus_quizzes SET last_activity_at = NOW() WHERE id = ?'
        )->execute([(int) $quizId]);
    }

    private static function countAnswers($quizId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT COUNT(*) FROM player_bonus_quiz_answers WHERE quiz_id = ?'
        );
        $stmt->execute([(int) $quizId]);
        return (int) $stmt->fetchColumn();
    }

    private static function secondsSinceActivity(array $quiz)
    {
        if (self::hasActivityColumn()) {
            $stmt = Db::connection()->prepare(
                'SELECT TIMESTAMPDIFF(SECOND, COALESCE(last_activity_at, started_at), NOW()) AS elapsed
                 FROM room_bonus_quizzes WHERE id = ?'
            );
        } else {
            $stmt = Db::connection()->prepare(
                'SELECT TIMESTAMPDIFF(SECOND, started_at, NOW()) AS elapsed
                 FROM room_bonus_quizzes WHERE id = ?'
            );
        }
        $stmt->execute([(int) $quiz['id']]);
        $row = $stmt->fetch();
        return (int) (isset($row['elapsed']) ? $row['elapsed'] : 0);
    }

    private static function idleCloseState(array $quiz, $answersCount)
    {
        if ($answersCount > 0) {
            return [
                'idle_close_active' => false,
                'idle_close_remaining_seconds' => 0,
            ];
        }

        $idleSeconds = AppConfig::bonusQuizIdleSeconds();
        $closeSeconds = AppConfig::bonusQuizIdleCloseSeconds();
        $elapsed = self::secondsSinceActivity($quiz);
        $totalLimit = $idleSeconds + $closeSeconds;

        return [
            'idle_close_active' => $elapsed >= $idleSeconds,
            'idle_close_remaining_seconds' => max(0, $totalLimit - $elapsed),
        ];
    }

    private static function shouldIdleClose(array $quiz)
    {
        $idleSeconds = AppConfig::bonusQuizIdleSeconds();
        $closeSeconds = AppConfig::bonusQuizIdleCloseSeconds();
        if ($idleSeconds <= 0 || $closeSeconds <= 0) {
            return false;
        }

        return self::secondsSinceActivity($quiz) >= ($idleSeconds + $closeSeconds);
    }

    /**
     * จบคำถามแล้วแสดงผลผู้ตอบถูก 5 วินาที — ยังไม่เดินเวลาเดือน
     */
    public static function closeAndReveal($roomId, $quizId)
    {
        $pdo = Db::connection();
        $room = GameRoomModel::findById($roomId);
        if (!$room) {
            return $room;
        }

        $revealSeconds = AppConfig::bonusQuizRevealSeconds();
        if ($revealSeconds <= 0) {
            return self::closeAndResume($roomId, $quizId);
        }

        // คง month_timer_remaining ไว้ / ไม่ตั้ง simulation_pause_until จนกว่า reveal จะจบ
        $pdo->prepare(
            'UPDATE room_bonus_quizzes SET status = ? WHERE id = ? AND status = ?'
        )->execute(['revealing', $quizId, 'active']);

        $pdo->prepare(
            'UPDATE game_rooms
             SET bonus_quiz_reveal_until = DATE_ADD(NOW(), INTERVAL ? SECOND),
                 simulation_pause_until = NULL,
                 version = version + 1
             WHERE id = ?'
        )->execute([$revealSeconds, $roomId]);

        $__room = GameRoomModel::findById($roomId);
        return $__room ? $__room : $room;
    }

    public static function processRevealPhase(array $room, $quizId)
    {
        $roomId = (int) $room['id'];
        if (empty($room['bonus_quiz_reveal_until'])) {
            return self::finishRevealAndResume($roomId, $quizId);
        }

        $pdo = Db::connection();
        $stmt = $pdo->prepare(
            'SELECT NOW() >= bonus_quiz_reveal_until AS due FROM game_rooms WHERE id = ?'
        );
        $stmt->execute([$roomId]);
        $row = $stmt->fetch();
        $due = (bool) (isset($row['due']) ? $row['due'] : false);
        if (!$due) {
            return $room;
        }

        return self::finishRevealAndResume($roomId, $quizId);
    }

    public static function finishRevealAndResume($roomId, $quizId)
    {
        $pdo = Db::connection();
        $room = GameRoomModel::findById($roomId);
        if (!$room) {
            return $room;
        }

        $remaining = (int) ((isset($room['month_timer_remaining']) ? $room['month_timer_remaining'] : 0));
        if ($remaining <= 0) {
            $remaining = AppConfig::simulationMonthSeconds();
        }

        $pdo->prepare(
            'UPDATE room_bonus_quizzes SET status = ? WHERE id = ? AND status IN (?, ?)'
        )->execute(['closed', $quizId, 'revealing', 'active']);

        $pdo->prepare(
            'UPDATE game_rooms
             SET current_bonus_quiz_id = NULL,
                 bonus_quiz_reveal_until = NULL,
                 simulation_pause_until = DATE_ADD(NOW(), INTERVAL ? SECOND),
                 version = version + 1
             WHERE id = ?'
        )->execute([$remaining, $roomId]);

        $__room = GameRoomModel::findById($roomId);
        return $__room ? $__room : $room;
    }

    public static function closeAndResume($roomId, $quizId)
    {
        return self::finishRevealAndResume($roomId, $quizId);
    }

    public static function revealRemainingSeconds(array $room)
    {
        if (empty($room['bonus_quiz_reveal_until'])) {
            return 0;
        }
        $stmt = Db::connection()->prepare(
            'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), bonus_quiz_reveal_until)) AS remaining
             FROM game_rooms WHERE id = ?'
        );
        $stmt->execute([(int) $room['id']]);
        $row = $stmt->fetch();
        return (int) (isset($row['remaining']) ? $row['remaining'] : 0);
    }

    public static function listWinners($quizId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT pba.player_id, p.name AS player_name, pba.correct_order, pba.coins_delta
             FROM player_bonus_quiz_answers pba
             JOIN players p ON p.id = pba.player_id
             WHERE pba.quiz_id = ? AND pba.is_correct = 1
             ORDER BY pba.correct_order ASC, pba.answered_at ASC'
        );
        $stmt->execute([(int) $quizId]);
        $winners = [];
        while ($row = $stmt->fetch()) {
            $winners[] = [
                'player_id' => (int) $row['player_id'],
                'player_name' => $row['player_name'],
                'correct_order' => $row['correct_order'] !== null ? (int) $row['correct_order'] : null,
                'coins_delta' => (int) $row['coins_delta'],
            ];
        }
        return $winners;
    }

    public static function isQuizExpired(array $quiz)
    {
        $limit = AppConfig::bonusQuizSeconds();
        if ($limit <= 0) {
            return false;
        }

        $stmt = Db::connection()->prepare(
            'SELECT (TIMESTAMPDIFF(SECOND, started_at, NOW()) >= ?) AS expired
             FROM room_bonus_quizzes WHERE id = ?'
        );
        $stmt->execute([$limit, (int) $quiz['id']]);
        $row = $stmt->fetch();
        return (bool) (isset($row['expired']) ? $row['expired'] : false);
    }

    public static function remainingSeconds(array $quiz)
    {
        $limit = AppConfig::bonusQuizSeconds();
        if ($limit <= 0) {
            return 0;
        }

        $stmt = Db::connection()->prepare(
            'SELECT GREATEST(0, ? - TIMESTAMPDIFF(SECOND, started_at, NOW())) AS remaining
             FROM room_bonus_quizzes WHERE id = ?'
        );
        $stmt->execute([$limit, (int) $quiz['id']]);
        $row = $stmt->fetch();
        return (int) (isset($row['remaining']) ? $row['remaining'] : 0);
    }

    public static function allPlayersAnswered($quizId, $roomId)
    {
        $players = PlayerModel::listByRoom($roomId);
        if (count($players) === 0) {
            return false;
        }

        $stmt = Db::connection()->prepare(
            'SELECT COUNT(*) FROM player_bonus_quiz_answers WHERE quiz_id = ?'
        );
        $stmt->execute([$quizId]);
        $answered = (int) $stmt->fetchColumn();

        return $answered >= count($players);
    }

    public static function clearQuizState($roomId)
    {
        $pdo = Db::connection();
        $room = GameRoomModel::findById($roomId);
        if ($room && !empty($room['current_bonus_quiz_id'])) {
            $pdo->prepare(
                'UPDATE room_bonus_quizzes SET status = ? WHERE id = ? AND status IN (?, ?)'
            )->execute(['closed', (int) $room['current_bonus_quiz_id'], 'active', 'revealing']);
        }
        $pdo->prepare(
            'UPDATE game_rooms
             SET current_bonus_quiz_id = NULL,
                 bonus_quiz_reveal_until = NULL,
                 version = version + 1
             WHERE id = ?'
        )->execute([$roomId]);
        $__room = GameRoomModel::findById($roomId);
        return $__room ? $__room : null;
    }

    public static function submitAnswer($playerId, $answer)
    {
        $player = PlayerModel::findById($playerId);
        if (!$player) {
            Response::error('ไม่พบผู้เล่น', 404);
        }

        $room = GameRoomModel::findById((int) $player['room_id']);
        if (!$room || $room['status'] !== 'simulating' || empty($room['current_bonus_quiz_id'])) {
            Response::error('ขณะนี้ไม่มีโบนัสทายปัญหา', 400);
        }

        $quiz = self::findById((int) $room['current_bonus_quiz_id']);
        if (!$quiz || $quiz['status'] !== 'active') {
            Response::error('โบนัสทายปัญหาจบแล้ว', 400);
        }

        if (self::getPlayerAnswer($playerId, (int) $quiz['id'])) {
            Response::error('คุณส่งคำตอบแล้ว', 400);
        }

        $answerText = trim((string) $answer);
        if ($answerText === '') {
            Response::error('กรุณากรอกคำตอบ', 400);
        }

        $isCorrect = self::answersMatch($answerText, $quiz['correct_answer'], $quiz['answer_type']);
        $coinsDelta = self::scoreCoins((int) $quiz['id'], $isCorrect);

        $correctOrder = null;
        if ($isCorrect) {
            $correctOrder = self::nextCorrectOrder((int) $quiz['id']);
        }

        $pdo = Db::connection();
        $pdo->prepare(
            'INSERT INTO player_bonus_quiz_answers
             (quiz_id, player_id, answer, is_correct, coins_delta, correct_order)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            (int) $quiz['id'],
            $playerId,
            $answerText,
            $isCorrect ? 1 : 0,
            $coinsDelta,
            $correctOrder,
        ]);

        PlayerModel::adjustCoins($playerId, $coinsDelta);
        GameRoomModel::bumpVersion((int) $room['id']);

        $resources = PlayerModel::getResources($playerId);

        return [
            'is_correct' => $isCorrect,
            'coins_delta' => $coinsDelta,
            'correct_order' => $correctOrder,
            'resources' => $resources ? [
                'coins' => (int) $resources['coins'],
            ] : null,
            'quiz' => self::formatForClient($quiz, (int) $room['id']),
        ];
    }

    private static function scoreCoins($quizId, $isCorrect)
    {
        if (!$isCorrect) {
            return -10;
        }

        $stmt = Db::connection()->prepare(
            'SELECT COUNT(*) FROM player_bonus_quiz_answers
             WHERE quiz_id = ? AND is_correct = 1'
        );
        $stmt->execute([$quizId]);
        $priorCorrect = (int) $stmt->fetchColumn();

        return $priorCorrect === 0 ? 10 : 2;
    }

    private static function nextCorrectOrder($quizId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT COUNT(*) FROM player_bonus_quiz_answers
             WHERE quiz_id = ? AND is_correct = 1'
        );
        $stmt->execute([$quizId]);
        return (int) $stmt->fetchColumn() + 1;
    }

    public static function answersMatch($given, $expected, $type)
    {
        if ($type === 'number') {
            return self::normalizeNumber($given) === self::normalizeNumber($expected);
        }

        if ($type === 'choice') {
            $givenChoice = self::normalizeChoice($given);
            $expectedChoice = self::normalizeChoice($expected);
            return $givenChoice !== '' && $givenChoice === $expectedChoice;
        }

        $givenNorm = self::normalizeText($given);
        $expectedNorm = self::normalizeText($expected);
        if ($givenNorm === $expectedNorm) {
            return true;
        }

        return mb_strpos($givenNorm, $expectedNorm, 0, 'UTF-8') !== false
            || mb_strpos($expectedNorm, $givenNorm, 0, 'UTF-8') !== false;
    }

    private static function normalizeChoice($value)
    {
        $raw = trim((string) $value);
        if (preg_match('/^A([\.\):\s]|$)/i', $raw)) {
            return 'A';
        }
        if (preg_match('/^B([\.\):\s]|$)/i', $raw)) {
            return 'B';
        }

        $text = mb_strtolower(preg_replace('/\s+/u', '', $raw), 'UTF-8');
        if ($text === 'a' || $text === 'ใช่' || $text === 'yes' || $text === 'true' || $text === '1') {
            return 'A';
        }
        if ($text === 'b' || $text === 'ไม่ใช่' || $text === 'no' || $text === 'false' || $text === '0') {
            return 'B';
        }
        if (mb_strpos($text, 'ไม่ใช่', 0, 'UTF-8') !== false) {
            return 'B';
        }
        if (mb_strpos($text, 'ใช่', 0, 'UTF-8') !== false) {
            return 'A';
        }

        return '';
    }

    private static function normalizeNumber($value)
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $value);
        return $digits === '' ? '' : (string) (int) $digits;
    }

    private static function normalizeText($value)
    {
        $text = mb_strtolower(trim((string) $value), 'UTF-8');
        return preg_replace('/\s+/u', '', $text);
    }

    public static function getStateForRoom(array $room)
    {
        if (empty($room['current_bonus_quiz_id'])) {
            return null;
        }

        $quiz = self::findById((int) $room['current_bonus_quiz_id']);
        if (!$quiz) {
            return null;
        }

        if ($quiz['status'] === 'active') {
            return self::formatForClient($quiz, (int) $room['id'], $room);
        }

        if ($quiz['status'] === 'revealing' || !empty($room['bonus_quiz_reveal_until'])) {
            return self::formatRevealForClient($quiz, (int) $room['id'], $room);
        }

        return null;
    }

    public static function formatRevealForClient(array $quiz, $roomId, $room = null)
    {
        $winners = self::listWinners((int) $quiz['id']);
        $revealLeft = (is_array($room)) ? self::revealRemainingSeconds($room) : 0;

        return [
            'id' => (int) $quiz['id'],
            'active' => false,
            'revealing' => true,
            'question' => $quiz['question_th'],
            'year' => (int) $quiz['year'],
            'month' => (int) $quiz['month'],
            'winners' => $winners,
            'winners_count' => count($winners),
            'remaining_seconds' => $revealLeft,
            'scoring' => [
                'first_correct' => 10,
                'later_correct' => 2,
                'wrong' => -10,
            ],
        ];
    }

    public static function formatForClient(array $quiz, $roomId, $room = null)
    {
        $players = PlayerModel::listByRoom($roomId);
        $stmt = Db::connection()->prepare(
            'SELECT pba.player_id, pba.answer, pba.is_correct, pba.coins_delta, pba.correct_order, p.name
             FROM player_bonus_quiz_answers pba
             JOIN players p ON p.id = pba.player_id
             WHERE pba.quiz_id = ?
             ORDER BY pba.answered_at ASC'
        );
        $stmt->execute([(int) $quiz['id']]);
        $answers = [];
        while ($row = $stmt->fetch()) {
            $answers[] = [
                'player_id' => (int) $row['player_id'],
                'player_name' => $row['name'],
                'answer' => $row['answer'],
                'is_correct' => (bool) $row['is_correct'],
                'coins_delta' => (int) $row['coins_delta'],
                'correct_order' => $row['correct_order'] !== null ? (int) $row['correct_order'] : null,
            ];
        }

        $idleState = self::idleCloseState($quiz, count($answers));

        return [
            'id' => (int) $quiz['id'],
            'active' => $quiz['status'] === 'active',
            'revealing' => false,
            'question' => $quiz['question_th'],
            'answer_type' => (isset($quiz['answer_type']) ? $quiz['answer_type'] : 'text'),
            'choices' => [
                ['key' => 'A', 'label' => 'ใช่'],
                ['key' => 'B', 'label' => 'ไม่ใช่'],
            ],
            'scoring' => [
                'first_correct' => 10,
                'later_correct' => 2,
                'wrong' => -10,
            ],
            'year' => (int) $quiz['year'],
            'month' => (int) $quiz['month'],
            'answers_count' => count($answers),
            'total_players' => count($players),
            'remaining_seconds' => self::remainingSeconds($quiz),
            'idle_close_active' => $idleState['idle_close_active'],
            'idle_close_remaining_seconds' => $idleState['idle_close_remaining_seconds'],
            'answers' => $answers,
        ];
    }

    public static function getPlayerAnswer($playerId, $quizId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT answer, is_correct, coins_delta, correct_order
             FROM player_bonus_quiz_answers
             WHERE quiz_id = ? AND player_id = ? LIMIT 1'
        );
        $stmt->execute([$quizId, $playerId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return [
            'answer' => $row['answer'],
            'is_correct' => (bool) $row['is_correct'],
            'coins_delta' => (int) $row['coins_delta'],
            'correct_order' => $row['correct_order'] !== null ? (int) $row['correct_order'] : null,
        ];
    }

    public static function findById($quizId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT * FROM room_bonus_quizzes WHERE id = ? LIMIT 1'
        );
        $stmt->execute([(int) $quizId]);
        $row = $stmt->fetch();
        return $row ? $row : null;
    }

    private static function pickRandomQuestion()
    {
        self::ensureSeedQuestions();
        $stmt = Db::connection()->query(
            'SELECT question_th, correct_answer, answer_type
             FROM bonus_quiz_questions
             WHERE active = 1
             ORDER BY RAND()
             LIMIT 1'
        );
        $row = $stmt->fetch();
        return $row ? $row : null;
    }

    public static function ensureSeedQuestions()
    {
        $pdo = Db::connection();
        $stmt = $pdo->query('SELECT COUNT(*) FROM bonus_quiz_questions');
        $count = (int) $stmt->fetchColumn();

        // อัปเกรดคลังเก่า (น้อยกว่า 20 ข้อ หรือยังไม่มีแบบ choice)
        $choiceCount = (int) $pdo->query(
            "SELECT COUNT(*) FROM bonus_quiz_questions WHERE answer_type = 'choice'"
        )->fetchColumn();

        if ($count >= 20 && $choiceCount > 0) {
            return;
        }

        $pdo->exec('DELETE FROM bonus_quiz_questions');
        $questions = self::defaultQuestionBank();
        $insert = $pdo->prepare(
            'INSERT INTO bonus_quiz_questions (question_th, correct_answer, answer_type) VALUES (?, ?, ?)'
        );
        foreach ($questions as $q) {
            $insert->execute($q);
        }
    }

    public static function defaultQuestionBank()
    {
        return [
            ['ประเทศไทยปกครองด้วยระบอบประชาธิปไตยอันมีพระมหากษัตริย์ทรงเป็นประมุขหรือไม่?', 'A', 'choice'],
            ['รัฐธรรมนูญเป็นกฎหมายสูงสุดของประเทศไทยหรือไม่?', 'A', 'choice'],
            ['อำนาจอธิปไตยเป็นของประชาชนหรือไม่?', 'A', 'choice'],
            ['รัฐสภาเป็นผู้ใช้อำนาจนิติบัญญัติหรือไม่?', 'A', 'choice'],
            ['คณะรัฐมนตรีเป็นผู้ใช้อำนาจบริหารหรือไม่?', 'A', 'choice'],
            ['ศาลเป็นผู้ใช้อำนาจตุลาการหรือไม่?', 'A', 'choice'],
            ['การเลือกตั้งเป็นการใช้สิทธิของประชาชนหรือไม่?', 'A', 'choice'],
            ['ผู้มีสิทธิเลือกตั้งสามารถซื้อสิทธิขายเสียงได้อย่างถูกต้องหรือไม่?', 'B', 'choice'],
            ['การเสียภาษีเป็นหน้าที่ของประชาชนหรือไม่?', 'A', 'choice'],
            ['ทุกคนควรปฏิบัติตามกฎหมายหรือไม่?', 'A', 'choice'],
            ['สิทธิมนุษยชนเป็นสิทธิพื้นฐานของทุกคนหรือไม่?', 'A', 'choice'],
            ['การเลือกปฏิบัติต่อผู้อื่นเป็นสิ่งที่ควรทำหรือไม่?', 'B', 'choice'],
            ['ประเทศไทยเป็นสมาชิกของอาเซียนหรือไม่?', 'A', 'choice'],
            ['อาเซียนมีสมาชิก 10 ประเทศหรือไม่?', 'A', 'choice'],
            ['เงินสกุลของประเทศไทยคือเงินบาทหรือไม่?', 'A', 'choice'],
            ['เงินเฟ้อทำให้ราคาสินค้าโดยทั่วไปสูงขึ้นหรือไม่?', 'A', 'choice'],
            ['การออมเงินช่วยสร้างความมั่นคงทางการเงินหรือไม่?', 'A', 'choice'],
            ['ธนาคารมีหน้าที่รับฝากเงินหรือไม่?', 'A', 'choice'],
            ['ความต้องการของมนุษย์มีอย่างจำกัดหรือไม่?', 'B', 'choice'],
            ['การวางแผนใช้จ่ายช่วยลดปัญหาหนี้สินได้หรือไม่?', 'A', 'choice'],
            ['กรุงเทพมหานครเป็นเมืองหลวงของประเทศไทยหรือไม่?', 'A', 'choice'],
            ['ประเทศไทยตั้งอยู่ในทวีปเอเชียหรือไม่?', 'A', 'choice'],
            ['แม่น้ำเจ้าพระยาไหลลงสู่อ่าวไทยหรือไม่?', 'A', 'choice'],
            ['ประเทศไทยมีพรมแดนติดประเทศญี่ปุ่นหรือไม่?', 'B', 'choice'],
            ['การอนุรักษ์ทรัพยากรธรรมชาติเป็นหน้าที่ของประชาชนทุกคนหรือไม่?', 'A', 'choice'],
            ['ประเทศไทยมีชายฝั่งติดทะเลอันดามันหรือไม่?', 'A', 'choice'],
            ['ภาคเหนือมีภูเขามากกว่าภาคกลางหรือไม่?', 'A', 'choice'],
            ['ภาคกลางเหมาะกับการทำนาหรือไม่?', 'A', 'choice'],
            ['แม่น้ำโขงเป็นพรมแดนธรรมชาติของไทยหรือไม่?', 'A', 'choice'],
            ['ประเทศไทยมี 77 จังหวัดหรือไม่?', 'A', 'choice'],
            ['ประเทศไทยอยู่ในซีกโลกใต้หรือไม่?', 'B', 'choice'],
            ['สินค้าและบริการเหมือนกันทุกอย่างหรือไม่?', 'B', 'choice'],
            ['การกู้เงินโดยไม่คิดก่อนเป็นสิ่งที่ควรทำหรือไม่?', 'B', 'choice'],
            ['ศาสนาพุทธสอนให้ละเว้นความชั่วหรือไม่?', 'A', 'choice'],
            ['ศีล 5 เป็นหลักปฏิบัติของชาวพุทธหรือไม่?', 'A', 'choice'],
            ['การทำความดีส่งผลดีต่อสังคมหรือไม่?', 'A', 'choice'],
            ['ความซื่อสัตย์เป็นคุณธรรมที่ดีหรือไม่?', 'A', 'choice'],
            ['การโกงเป็นสิ่งที่ควรทำหรือไม่?', 'B', 'choice'],
            ['การมีน้ำใจช่วยเหลือผู้อื่นเป็นคุณธรรมหรือไม่?', 'A', 'choice'],
            ['การรักษาวัฒนธรรมไทยเป็นสิ่งสำคัญหรือไม่?', 'A', 'choice'],
            ['วันวิสาขบูชาเกี่ยวข้องกับพระพุทธเจ้าหรือไม่?', 'A', 'choice'],
            ['การเคารพผู้ใหญ่เป็นค่านิยมที่ดีหรือไม่?', 'A', 'choice'],
            ['การใช้ความรุนแรงแก้ปัญหาเป็นวิธีที่เหมาะสมหรือไม่?', 'B', 'choice'],
            ['เด็กทุกคนมีสิทธิได้รับการศึกษาหรือไม่?', 'A', 'choice'],
            ['การทิ้งขยะลงแม่น้ำเป็นการอนุรักษ์สิ่งแวดล้อมหรือไม่?', 'B', 'choice'],
            ['การปลูกต้นไม้ช่วยลดภาวะโลกร้อนหรือไม่?', 'A', 'choice'],
            ['การรีไซเคิลช่วยลดขยะหรือไม่?', 'A', 'choice'],
            ['น้ำเป็นทรัพยากรธรรมชาติหรือไม่?', 'A', 'choice'],
            ['ป่าไม้มีความสำคัญต่อระบบนิเวศหรือไม่?', 'A', 'choice'],
            ['การเผาป่าช่วยรักษาสิ่งแวดล้อมหรือไม่?', 'B', 'choice'],
        ];
    }
}
