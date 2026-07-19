<?php

require_once __DIR__ . '/../config/Db.php';
require_once __DIR__ . '/PlayerModel.php';

class ScoreModel
{
    public static function calculateYearScores($roomId, $year)
    {
        $players = PlayerModel::listByRoom($roomId);
        $scores = [];

        foreach ($players as $player) {
            $playerId = (int) $player['id'];
            $resources = PlayerModel::getResources($playerId);
            if (!$resources) {
                continue;
            }

            $production = (int) $resources['stock_amount'] * 2;
            $resource = (int) floor(((int) $resources['coins']) / 10);
            if ($resource > 100) {
                $resource = 100;
            }
            $technology = (int) $resources['tech_level'] * 15;
            $sustainability = (int) $resources['sustainability'];
            $risk = max(0, 100 - (int) $player['agricultural_capability']);
            $knowledge = (int) $resources['knowledge_score'];
            $env = max(0, 100 - (int) $resources['env_impact']);
            $capability = (int) $player['agricultural_capability'];
            $diversityPenalty = self::planDiversityPenalty($playerId, $year);

            $total = $production + $resource + $technology + $sustainability
                + $knowledge + $env + $capability - (int) floor($risk / 2) - $diversityPenalty;

            $scores[] = [
                'player_id' => $playerId,
                'total' => $total,
                'production_score' => $production,
                'resource_score' => $resource,
                'technology_score' => $technology,
                'sustainability_score' => $sustainability,
                'risk_score' => $risk,
                'knowledge_score' => $knowledge,
                'env_score' => $env,
                'capability_score' => $capability,
            ];
        }

        usort($scores, function ($a, $b) { if ($b['total'] == $a['total']) { return 0; } return ($b['total'] < $a['total']) ? -1 : 1; });

        $pdo = Db::connection();
        $pdo->prepare('DELETE FROM player_scores WHERE room_id = ? AND year = ?')
            ->execute([$roomId, $year]);

        $insert = $pdo->prepare(
            'INSERT INTO player_scores
             (player_id, room_id, year, production_score, resource_score, technology_score,
              sustainability_score, risk_score, knowledge_score, env_score, capability_score,
              total_score, rank_position)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $rank = 1;
        foreach ($scores as $row) {
            $insert->execute([
                $row['player_id'],
                $roomId,
                $year,
                $row['production_score'],
                $row['resource_score'],
                $row['technology_score'],
                $row['sustainability_score'],
                $row['risk_score'],
                $row['knowledge_score'],
                $row['env_score'],
                $row['capability_score'],
                $row['total'],
                $rank,
            ]);
            $rank++;
        }
    }

    /**
     * แผนการ์ดซ้ำชนิดเดียว/น้อยชนิด → หักคะแนนหนัก (ติดลบได้)
     */
    private static function planDiversityPenalty($playerId, $year)
    {
        $stmt = Db::connection()->prepare(
            'SELECT card_code FROM player_year_cards WHERE player_id = ? AND year = ?'
        );
        $stmt->execute([$playerId, $year]);
        $codes = [];
        while ($row = $stmt->fetch()) {
            $codes[] = $row['card_code'];
        }
        if (count($codes) === 0) {
            return 80;
        }

        $unique = count(array_unique($codes));
        $hasPlant = in_array('PLANT', $codes, true);
        $penalty = 0;

        if ($unique === 1) {
            $penalty = 120;
        } elseif ($unique === 2) {
            $penalty = 70;
        } elseif ($unique === 3) {
            $penalty = 35;
        } elseif ($unique <= 5) {
            $penalty = 10;
        }

        if (!$hasPlant) {
            $penalty += 40;
        }

        return $penalty;
    }

    public static function getRanking($roomId, $year = null)
    {
        $pdo = Db::connection();
        if ($year !== null) {
            $stmt = $pdo->prepare(
                'SELECT ps.*, p.name AS player_name
                 FROM player_scores ps
                 JOIN players p ON p.id = ps.player_id
                 WHERE ps.room_id = ? AND ps.year = ?
                 ORDER BY ps.rank_position'
            );
            $stmt->execute([$roomId, $year]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT ps.*, p.name AS player_name
                 FROM player_scores ps
                 JOIN players p ON p.id = ps.player_id
                 WHERE ps.room_id = ? AND ps.year IS NULL
                 ORDER BY ps.rank_position'
            );
            $stmt->execute([$roomId]);
        }

        return array_map(function (array $row) {
            return [
                'player_id' => (int) $row['player_id'],
                'player_name' => $row['player_name'],
                'year' => $row['year'] !== null ? (int) $row['year'] : null,
                'total_score' => (int) $row['total_score'],
                'rank' => (int) $row['rank_position'],
                'production_score' => (int) $row['production_score'],
                'resource_score' => (int) $row['resource_score'],
                'capability_score' => (int) $row['capability_score'],
            ];
        }, $stmt->fetchAll());
    }

    public static function calculateFinalScores($roomId)
    {
        $config = require __DIR__ . '/../config/app.php';
        $years = (int) ((isset($config['game_years']) ? $config['game_years'] : 5));
        for ($y = 1; $y <= $years; $y++) {
            self::calculateYearScores($roomId, $y);
        }

        $players = PlayerModel::listByRoom($roomId);
        $totals = [];

        foreach ($players as $player) {
            $stmt = Db::connection()->prepare(
                'SELECT COALESCE(SUM(total_score), 0) AS grand_total
                 FROM player_scores WHERE player_id = ? AND room_id = ? AND year IS NOT NULL'
            );
            $stmt->execute([(int) $player['id'], $roomId]);
            $totals[] = [
                'player_id' => (int) $player['id'],
                'total' => (int) (($__row = $stmt->fetch()) && isset($__row['grand_total']) ? $__row['grand_total'] : 0),
            ];
        }

        usort($totals, function ($a, $b) { if ($b['total'] == $a['total']) { return 0; } return ($b['total'] < $a['total']) ? -1 : 1; });

        $pdo = Db::connection();
        $pdo->prepare('DELETE FROM player_scores WHERE room_id = ? AND year IS NULL')
            ->execute([$roomId]);

        $insert = $pdo->prepare(
            'INSERT INTO player_scores (player_id, room_id, year, total_score, rank_position)
             VALUES (?, ?, NULL, ?, ?)'
        );
        $rank = 1;
        foreach ($totals as $row) {
            $insert->execute([$row['player_id'], $roomId, $row['total'], $rank]);
            $rank++;
        }
    }
}
