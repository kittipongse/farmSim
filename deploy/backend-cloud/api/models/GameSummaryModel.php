<?php

require_once __DIR__ . '/../config/Db.php';
require_once __DIR__ . '/PlayerModel.php';
require_once __DIR__ . '/ScoreModel.php';

class GameSummaryModel
{
    public static function buildForRoom($roomId)
    {
        $pdo = Db::connection();
        $players = PlayerModel::listByRoom($roomId);

        $roomStmt = $pdo->prepare(
            'SELECT gr.*, c.name_th AS country_name_th
             FROM game_rooms gr JOIN countries c ON c.id = gr.country_id WHERE gr.id = ?'
        );
        $roomStmt->execute(array($roomId));
        $room = $roomStmt->fetch();

        $playerSummaries = array();
        foreach ($players as $player) {
            $playerSummaries[] = self::buildForPlayer((int) $player['id'], $roomId, $player);
        }

        return array(
            'room_id' => $roomId,
            'country_name_th' => (isset($room['country_name_th']) ? $room['country_name_th'] : null),
            'ranking' => ScoreModel::getRanking($roomId, null),
            'players' => $playerSummaries,
        );
    }

    /**
     * สรุปผลสำหรับผู้เล่นคนเดียว (หน้าจบเกมบนมือถือ)
     */
    public static function buildReviewForPlayer($playerId)
    {
        $player = PlayerModel::findById($playerId);
        if (!$player) {
            Response::error('ไม่พบผู้เล่น', 404);
        }

        $roomId = (int) $player['room_id'];
        $summary = self::buildForPlayer($playerId, $roomId, $player);
        $ranking = ScoreModel::getRanking($roomId, null);

        $myRank = null;
        foreach ($ranking as $row) {
            if ((int) $row['player_id'] === $playerId) {
                $myRank = $row;
                break;
            }
        }

        return array(
            'player' => $summary,
            'my_rank' => $myRank,
            'ranking' => $ranking,
        );
    }

    public static function buildForPlayer($playerId, $roomId, array $player)
    {
        $pdo = Db::connection();
        $resources = PlayerModel::getResources($playerId);

        $cropStmt = $pdo->prepare(
            'SELECT pcp.*, best.region_name_th AS ideal_region_name, c.growth_months AS crop_growth,
                    c.base_coin, crr.coin_multiplier AS region_coin_mult
             FROM player_crop_plans pcp
             LEFT JOIN crops c ON c.id = pcp.crop_id
             LEFT JOIN crop_region_rates crr
               ON crr.crop_id = c.id AND crr.region_id = ?
             LEFT JOIN (
                 SELECT crr2.crop_id, cr.name_th AS region_name_th
                 FROM crop_region_rates crr2
                 JOIN country_regions cr ON cr.id = crr2.region_id
                 INNER JOIN (
                     SELECT crop_id, MAX(coin_multiplier) AS max_mult
                     FROM crop_region_rates GROUP BY crop_id
                 ) mx ON mx.crop_id = crr2.crop_id AND mx.max_mult = crr2.coin_multiplier
             ) best ON best.crop_id = c.id
             WHERE pcp.player_id = ?
             ORDER BY pcp.year, pcp.plant_month'
        );
        $regionId = (int) ((isset($player['region_id']) ? $player['region_id'] : 0));
        $cropStmt->execute(array($regionId, $playerId));
        $crops = $cropStmt->fetchAll();

        $cardStmt = $pdo->prepare(
            'SELECT year, card_code, month, crop_name
             FROM player_year_cards
             WHERE player_id = ?
             ORDER BY year, month'
        );
        $cardStmt->execute(array($playerId));
        $cards = $cardStmt->fetchAll();

        $cropHistory = array();
        $totalYield = 0;
        $mismatchYield = 0;
        $nativeYield = 0;
        $strengths = array();
        $mistakes = array();
        $harvestedCount = 0;
        $unharvestedCount = 0;
        $goodRegionCount = 0;
        $badRegionCount = 0;
        $badSeasonCount = 0;

        foreach ($crops as $row) {
            $yield = (int) $row['yield_amount'];
            $totalYield += $yield;
            $isRegionMatch = (bool) ((isset($row['region_match']) ? $row['region_match'] : true));
            $isSeasonMatch = (bool) ((isset($row['season_match']) ? $row['season_match'] : true));
            $status = $row['status'];
            $name = (isset($row['input_crop_name']) ? $row['input_crop_name'] : $row['crop_name']);
            $plantMonth = (int) $row['plant_month'];
            $harvestMonth = (int) $row['harvest_month'];
            $year = (int) $row['year'];

            if ($isRegionMatch && $isSeasonMatch) {
                $nativeYield += $yield;
            } else {
                $mismatchYield += $yield;
            }

            if ($status === 'harvested') {
                $harvestedCount++;
            } elseif (in_array($status, array('planned', 'growing'), true) || $harvestMonth > 12) {
                $unharvestedCount++;
            }

            if ($isRegionMatch) {
                $goodRegionCount++;
            } else {
                $badRegionCount++;
                $mistakes[] = self::issueTextForRegion(array(
                    'year' => $year,
                    'input_crop_name' => $name,
                    'crop_name' => $row['crop_name'],
                    'mismatch_reason' => $row['mismatch_reason'],
                ), (isset($player['region_name_th']) ? $player['region_name_th'] : ''));
            }

            if (!$isSeasonMatch) {
                $badSeasonCount++;
                $mistakes[] = self::issueTextForSeason(array(
                    'year' => $year,
                    'input_crop_name' => $name,
                    'crop_name' => $row['crop_name'],
                    'plant_month' => $plantMonth,
                ));
            }

            if ($harvestMonth > 12) {
                $mistakes[] = "ปี {$year}: ปลูก \"{$name}\" เดือน {$plantMonth} แต่โตไม่ทันในปีนี้ (เก็บเกี่ยวเดือน {$harvestMonth}) → ไม่ได้ผลผลิต";
            } elseif ($status !== 'harvested' && $harvestMonth <= 12) {
                $mistakes[] = "ปี {$year}: ปลูก \"{$name}\" ควรเก็บเกี่ยวเดือน {$harvestMonth} แต่ไม่ได้วางการ์ดเก็บเกี่ยว → เสียผลผลิต";
            }

            if ($isRegionMatch && $isSeasonMatch && $status === 'harvested' && $yield > 0) {
                $mult = isset($row['region_coin_mult']) ? (float) $row['region_coin_mult'] : 0;
                if ($mult >= 1.0) {
                    $strengths[] = "ปลูก \"{$name}\" เหมาะกับภูมิภาคเป็นอย่างดี (Coin ×{$mult}) ได้ผลผลิต {$yield} หน่วย";
                } elseif ($mult >= 0.85) {
                    $strengths[] = "ปลูก \"{$name}\" ตรงภูมิภาคและฤดู ได้ผลผลิต {$yield} หน่วย";
                }
            }

            $cropHistory[] = array(
                'year' => $year,
                'input_crop_name' => $name,
                'crop_name' => $row['crop_name'],
                'plant_month' => $plantMonth,
                'harvest_month' => $harvestMonth,
                'growth_months' => (int) ((isset($row['growth_months']) ? $row['growth_months'] : (isset($row['crop_growth']) ? $row['crop_growth'] : 0))),
                'region_match' => $isRegionMatch,
                'season_match' => $isSeasonMatch,
                'mismatch_reason' => $row['mismatch_reason'],
                'yield_amount' => $yield,
                'status' => $status,
                'ideal_region_name' => (isset($row['ideal_region_name']) ? $row['ideal_region_name'] : null),
            );
        }

        $planAnalysis = self::analyzeCardPlan($cards, $mistakes, $strengths);

        $eventStmt = $pdo->prepare(
            'SELECT COUNT(*) AS cnt,
                    SUM(CASE WHEN per.handled_well = 1 THEN 1 ELSE 0 END) AS good_cnt,
                    SUM(CASE WHEN per.handled_well = 0 THEN 1 ELSE 0 END) AS bad_cnt
             FROM player_event_responses per
             JOIN room_year_events rye ON rye.id = per.event_id
             WHERE per.player_id = ? AND rye.room_id = ?'
        );
        $eventStmt->execute(array($playerId, $roomId));
        $eventRow = $eventStmt->fetch();
        $badEvents = (int) ((isset($eventRow['bad_cnt']) ? $eventRow['bad_cnt'] : 0));
        $goodEvents = (int) ((isset($eventRow['good_cnt']) ? $eventRow['good_cnt'] : 0));

        if ($badEvents > 0) {
            $mistakes[] = "รับมือ Breaking News ไม่ดี {$badEvents} ครั้ง → ความสามารถด้านการเกษตรลดลง";
        }
        if ($goodEvents > 0) {
            $strengths[] = "รับมือ Breaking News ได้ดี {$goodEvents} ครั้ง";
        }

        $capability = (int) ((isset($player['agricultural_capability']) ? $player['agricultural_capability'] : 100));
        if ($capability >= 85) {
            $strengths[] = "ความสามารถด้านการเกษตรสูง ({$capability}%) — บริหารฟาร์มและรับความเสี่ยงได้ดี";
        } elseif ($capability < 70) {
            $mistakes[] = "ความสามารถด้านการเกษตรเหลือ {$capability}% (ต่ำกว่าเกณฑ์ดี) — ควรเลือกพืชที่เหมาะและรับมือภัยพิบัติให้ดีขึ้น";
        }

        $coins = (int) ((isset($resources['coins']) ? $resources['coins'] : 0));
        $stock = (int) ((isset($resources['stock_amount']) ? $resources['stock_amount'] : 0));
        $tech = (int) ((isset($resources['tech_level']) ? $resources['tech_level'] : 0));
        $soil = (int) ((isset($resources['soil_quality']) ? $resources['soil_quality'] : 0));
        $water = (int) ((isset($resources['water']) ? $resources['water'] : 0));

        if ($tech >= 2) {
            $strengths[] = "ลงทุนเทคโนโลยีถึงระดับ {$tech} — ได้คะแนนมิติเทคโนโลยี";
        }
        if ($soil >= 85) {
            $strengths[] = "คุณภาพดินปลายเกมดี ({$soil}) — ช่วยเพิ่มผลผลิตตอนเก็บเกี่ยว";
        }
        if ($stock > 0) {
            $mistakes[] = "ยังมีผลผลิตในคลัง {$stock} หน่วยที่ยังไม่ได้ขาย (TRADE) → เสียโอกาสได้เหรียญ";
        }
        if ($coins >= 400) {
            $strengths[] = "เหลือเหรียญปลายเกม {$coins} — บริหารงบประมาณได้ดี";
        } elseif ($coins < 50) {
            $mistakes[] = "เหรียญเหลือเพียง {$coins} — ใช้จ่ายสูงหรือขายผลผลิตน้อย";
        }

        if ($goodRegionCount > 0 && $badRegionCount === 0 && count($crops) > 0) {
            $strengths[] = 'เลือกพืชตรงกับภูมิภาคทุกแปลงที่ปลูก';
        }
        if ($badSeasonCount === 0 && count($crops) > 0 && $harvestedCount > 0) {
            $strengths[] = 'ปลูกตรงฤดูกาลที่ระบบกำหนดทุกครั้ง';
        }
        if ($harvestedCount >= 3) {
            $strengths[] = "เก็บเกี่ยวสำเร็จ {$harvestedCount} รอบ — หมุนเวียนผลผลิตได้ดี";
        }

        $strengths = array_values(array_unique($strengths));
        $mistakes = array_values(array_unique($mistakes));

        if (count($strengths) === 0 && count($crops) === 0) {
            $mistakes[] = 'ไม่มีการปลูกพืชทั้งปี — ไม่มีผลผลิตเข้าคลัง';
        }

        $scoreRow = null;
        $ranking = ScoreModel::getRanking($roomId, null);
        foreach ($ranking as $row) {
            if ((int) $row['player_id'] === $playerId) {
                $scoreRow = $row;
                break;
            }
        }

        // อันดับรวมเก็บแค่ total — ดึงมิติย่อยจากคะแนนรายปีมาแสดง
        if ($scoreRow) {
            $yearRank = ScoreModel::getRanking($roomId, 1);
            foreach ($yearRank as $row) {
                if ((int) $row['player_id'] === $playerId) {
                    $scoreRow['production_score'] = $row['production_score'];
                    $scoreRow['resource_score'] = $row['resource_score'];
                    $scoreRow['capability_score'] = $row['capability_score'];
                    break;
                }
            }
        }

        $advice = self::buildAdvice($mistakes, $strengths, $planAnalysis);

        return array(
            'player_id' => $playerId,
            'name' => $player['name'],
            'region_name_th' => (isset($player['region_name_th']) ? $player['region_name_th'] : null),
            'agricultural_capability' => $capability,
            'resources' => $resources ? array(
                'coins' => $coins,
                'stock_amount' => $stock,
                'water' => $water,
                'tech_level' => $tech,
                'soil_quality' => $soil,
            ) : null,
            'crop_history' => $cropHistory,
            'card_plan' => $planAnalysis,
            'total_yield' => $totalYield,
            'native_yield' => $nativeYield,
            'mismatch_yield' => $mismatchYield,
            'harvested_count' => $harvestedCount,
            'unharvested_count' => $unharvestedCount,
            'strengths' => $strengths,
            'mistakes' => $mistakes,
            'advice' => $advice,
            'score' => $scoreRow,
            // รองรับ UI เดิม
            'issues' => $mistakes,
            'outcomes' => $strengths,
        );
    }

    private static function analyzeCardPlan(array $cards, array &$mistakes, array &$strengths)
    {
        if (count($cards) === 0) {
            $mistakes[] = 'ไม่พบแผนการ์ดที่วางไว้';
            return array(
                'placed_count' => 0,
                'unique_types' => 0,
                'codes' => array(),
                'has_plant' => false,
                'has_harvest' => false,
                'has_trade' => false,
                'has_water' => false,
                'has_soil' => false,
            );
        }

        $codes = array();
        foreach ($cards as $c) {
            $codes[] = $c['card_code'];
        }
        $unique = array_values(array_unique($codes));
        $uniqueCount = count($unique);
        $hasPlant = in_array('PLANT', $codes, true);
        $hasHarvest = in_array('HARVEST', $codes, true);
        $hasTrade = in_array('TRADE', $codes, true);
        $hasWater = in_array('WATER', $codes, true);
        $hasSoil = in_array('SOIL', $codes, true);
        $hasProtect = in_array('PROTECT', $codes, true);
        $hasTech = in_array('TECH', $codes, true);

        if ($uniqueCount === 1) {
            $mistakes[] = 'วางการ์ดชนิดเดียวซ้ำทั้ง 12 เดือน — แผนไม่สมดุล คะแนนความหลากหลายต่ำมาก';
        } elseif ($uniqueCount <= 3) {
            $mistakes[] = "ใช้การ์ดเพียง {$uniqueCount} ชนิด — ความหลากหลายของแผนน้อย คะแนนอาจไม่ดี";
        } elseif ($uniqueCount >= 6) {
            $strengths[] = "ใช้การ์ดหลากหลาย {$uniqueCount} ชนิด — แผนครอบคลุมหลายกิจกรรม";
        }

        if (!$hasPlant) {
            $mistakes[] = 'ไม่มีปีปลูกพืชในแผน — ไม่มีผลผลิต';
        }
        if ($hasPlant && !$hasHarvest) {
            $mistakes[] = 'มีปลูกแต่ไม่มีเก็บเกี่ยว — แผนไม่ครบวงจร';
        }
        if ($hasHarvest && !$hasTrade) {
            $mistakes[] = 'มีเก็บเกี่ยวแต่ไม่มีขายผลผลิต (TRADE) — ผลผลิตค้างคลัง ไม่ได้เหรียญ';
        }
        if ($hasPlant && $hasHarvest && $hasTrade) {
            $strengths[] = 'มีวงจรครบ: ปลูก → เก็บเกี่ยว → ขาย';
        }
        if ($hasWater) {
            $strengths[] = 'มีการจัดการน้ำในแผน — ช่วยรักษาทรัพยากรน้ำ';
        } else {
            $mistakes[] = 'ไม่มีจัดการน้ำในแผน — เสี่ยงน้ำไม่พอเมื่อปลูกหลายรอบ';
        }
        if ($hasSoil) {
            $strengths[] = 'มีการปรับปรุงดิน — ช่วยเพิ่มผลผลิตตอนเก็บเกี่ยว';
        }
        if ($hasProtect) {
            $strengths[] = 'มีการ์ดป้องกัน — พร้อมรับมือภัยพิบัติมากขึ้น';
        }
        if ($hasTech) {
            $strengths[] = 'มีการลงทุนเทคโนโลยีในแผน';
        }

        $counts = array_count_values($codes);
        arsort($counts);
        $topCode = key($counts);
        $topCount = (int) $counts[$topCode];
        if ($topCount >= 6) {
            $mistakes[] = "ใช้การ์ด {$topCode} ซ้ำถึง {$topCount} เดือน — ซ้ำมากเกินไป";
        }

        return array(
            'placed_count' => count($cards),
            'unique_types' => $uniqueCount,
            'codes' => $codes,
            'code_counts' => $counts,
            'has_plant' => $hasPlant,
            'has_harvest' => $hasHarvest,
            'has_trade' => $hasTrade,
            'has_water' => $hasWater,
            'has_soil' => $hasSoil,
            'has_protect' => $hasProtect,
            'has_tech' => $hasTech,
        );
    }

    private static function buildAdvice(array $mistakes, array $strengths, array $plan)
    {
        $advice = array();
        if (!$plan['has_plant'] || !$plan['has_harvest']) {
            $advice[] = 'ครั้งหน้าวางแผนให้มีทั้งปลูกและเก็บเกี่ยวในเดือนที่พืชโตครบ';
        }
        if (!$plan['has_trade']) {
            $advice[] = 'หลังเก็บเกี่ยวควรวางการ์ดขายผลผลิตเพื่อเปลี่ยนเป็นเหรียญ';
        }
        if ($plan['unique_types'] <= 3) {
            $advice[] = 'กระจายชนิดการ์ดให้หลากหลายขึ้น (น้ำ ดิน ป้องกัน เทคโนโลยี ฯลฯ)';
        }
        foreach ($mistakes as $m) {
            if (strpos($m, 'ภูมิภาค') !== false) {
                $advice[] = 'ศึกษาพืชที่เหมาะกับภูมิภาคของตนก่อนปลูก';
                break;
            }
        }
        foreach ($mistakes as $m) {
            if (strpos($m, 'ฤดูกาล') !== false || strpos($m, 'ฤดู') !== false) {
                $advice[] = 'จัดเดือนปลูกให้ตรงฤดูของพืชแต่ละประเภท (ผัก/สมุนไพรช่วงหนาว-ร้อน, พืชไร่ช่วงฝน-ร้อน)';
                break;
            }
        }
        if (count($strengths) >= 3 && count($mistakes) <= 1) {
            $advice[] = 'แผนโดยรวมดีแล้ว — ลองทดลองพืชมูลค่าสูงหรือหลายรอบเก็บเกี่ยวเพื่อเพิ่มคะแนน';
        }
        if (count($advice) === 0) {
            $advice[] = 'ทบทวนผลผลิตและอันดับแล้วปรับแผนในรอบถัดไป';
        }
        return array_values(array_unique($advice));
    }

    private static function issueTextForRegion(array $entry, $playerRegion)
    {
        $name = $entry['input_crop_name'] ?: $entry['crop_name'];
        $year = $entry['year'];

        if ($entry['mismatch_reason'] === 'unknown_crop') {
            return "ปี {$year}: ปลูก \"{$name}\" ที่ระบบไม่รู้จักในประเทศนี้ → ผลผลิตต่ำมาก ไม่คุ้มทุน";
        }

        $regionLabel = $playerRegion !== '' ? $playerRegion : 'ของคุณ';
        return "ปี {$year}: ปลูก \"{$name}\" ไม่เหมาะกับภูมิภาค{$regionLabel} → ผลผลิต/Coin ลดลง";
    }

    private static function issueTextForSeason(array $entry)
    {
        $name = $entry['input_crop_name'] ?: $entry['crop_name'];
        $year = $entry['year'];
        $month = $entry['plant_month'];

        return "ปี {$year}: ปลูก \"{$name}\" เดือน {$month} ไม่ตรงฤดูกาลที่เหมาะ → ผลผลิตลดเหลือประมาณครึ่งหนึ่ง";
    }
}
