<?php

require_once __DIR__ . '/../config/Db.php';
require_once __DIR__ . '/PlayerModel.php';
require_once __DIR__ . '/GameRoomModel.php';
require_once __DIR__ . '/SeasonModel.php';
require_once __DIR__ . '/CropModel.php';

class CropPlanModel
{
    const MISMATCH_WRONG_REGION = 'wrong_region';
    const MISMATCH_UNKNOWN = 'unknown_crop';

    public static function findCropInRegion($regionId, $cropName)
    {
        $pdo = Db::connection();
        $regionStmt = $pdo->prepare('SELECT country_id FROM country_regions WHERE id = ?');
        $regionStmt->execute([$regionId]);
        $region = $regionStmt->fetch();
        if (!$region) {
            return null;
        }

        $crop = CropModel::findByNameInCountry((int) $region['country_id'], $cropName);
        if (!$crop) {
            return null;
        }

        return self::enrichCropWithRate($crop, $regionId);
    }

    public static function findCropInCountry($countryId, $cropName)
    {
        $crop = CropModel::findByNameInCountry($countryId, $cropName);
        if (!$crop) {
            return null;
        }

        $best = CropModel::getBestRegionForCrop((int) $crop['id'], $countryId);
        if ($best) {
            $crop['matched_region_id'] = (int) $best['region_id'];
            $crop['region_name_th'] = $best['region_name_th'];
            $crop['coin_multiplier'] = (float) $best['coin_multiplier'];
        }

        return $crop;
    }

    private static function enrichCropWithRate(array $crop, $regionId)
    {
        $rate = CropModel::getRegionRate((int) $crop['id'], $regionId);
        if ($rate) {
            $crop['coin_multiplier'] = (float) $rate['coin_multiplier'];
            $crop['suitability_level'] = $rate['suitability_level'];
            $crop['matched_region_id'] = $regionId;
            $crop['region_name_th'] = $rate['region_name_th'];
        }
        return $crop;
    }

    /**
     * @return array{crop: ?array, region_match: bool, mismatch_reason: ?string, growth_months: int, display_name: string, coin_multiplier: float}
     */
    public static function validateCropName($playerId, $cropName)
    {
        $player = PlayerModel::findById($playerId);
        if (!$player) {
            Response::error('ไม่พบผู้เล่น', 404);
        }

        $inputName = trim((string) $cropName);
        if ($inputName === '') {
            Response::error('กรุณาระบุชื่อพืช', 400);
        }

        $resolved = self::resolveCropForPlayer($player, $inputName);
        $room = GameRoomModel::findById((int) $player['room_id']);
        $countryId = (int) ((isset($room['country_id']) ? $room['country_id'] : 0));
        $valid = $resolved['mismatch_reason'] !== self::MISMATCH_UNKNOWN;
        $suggestions = $valid ? array() : CropModel::suggestNames($countryId, '', 3);

        // ไม่เปิดเผยคำแนะนำฤดู/ภูมิภาค/Coin ตอนวางแผน — ให้ค้นคว้าเอง
        return array(
            'valid' => $valid,
            'found' => $resolved['crop'] !== null,
            'display_name' => $valid ? $resolved['display_name'] : null,
            'message' => $valid
                ? ('พบ "' . $resolved['display_name'] . '" ในระบบ')
                : ('ไม่พบพืช "' . $inputName . '" ในระบบ'),
            'suggestions' => $suggestions,
        );
    }

    public static function assertKnownCrop(array $player, $cropName)
    {
        $resolved = self::resolveCropForPlayer($player, $cropName);
        if ($resolved['mismatch_reason'] !== self::MISMATCH_UNKNOWN) {
            return $resolved;
        }

        $room = GameRoomModel::findById((int) $player['room_id']);
        $countryId = (int) ((isset($room['country_id']) ? $room['country_id'] : 0));
        $suggestions = CropModel::suggestNames($countryId, '', 3);
        $hint = count($suggestions) > 0
            ? ' ตัวเลือก: ' . implode(', ', $suggestions)
            : '';

        Response::error(
            'ไม่พบพืช "' . trim($cropName) . '" ในระบบ — พิมพ์ชื่อให้ถูกต้อง' . $hint,
            400
        );
    }

    public static function resolveCropForPlayer(array $player, $cropName)
    {
        $config = require __DIR__ . '/../config/app.php';
        $defaultGrowth = (int) ((isset($config['crop_unknown_growth_months']) ? $config['crop_unknown_growth_months'] : 4));
        $unknownMult = (float) ((isset($config['crop_unknown_multiplier']) ? $config['crop_unknown_multiplier'] : 0.25));
        $inputName = trim($cropName);

        if (!$player['region_id']) {
            Response::error('ต้องเลือกภูมิภาคก่อนวางแผนปลูก', 400);
        }

        $regionId = (int) $player['region_id'];
        $room = GameRoomModel::findById((int) $player['room_id']);
        $countryId = (int) ((isset($room['country_id']) ? $room['country_id'] : 0));

        $crop = CropModel::findByNameInCountry($countryId, $inputName);
        if (!$crop) {
            return array(
                'crop' => null,
                'region_match' => false,
                'mismatch_reason' => self::MISMATCH_UNKNOWN,
                'growth_months' => $defaultGrowth,
                'display_name' => $inputName,
                'coin_multiplier' => $unknownMult,
                'warning' => null,
            );
        }

        $rate = CropModel::getRegionRate((int) $crop['id'], $regionId);
        $coinMult = $rate ? (float) $rate['coin_multiplier'] : $unknownMult;
        $enriched = self::enrichCropWithRate($crop, $regionId);
        $regionMatch = CropModel::isRegionMatch($coinMult);

        // ไม่แจ้งคำแนะนำภูมิภาค/Coin/ฤดู — คำนวณผลในเกมเท่านั้น ให้ผู้เล่นค้นคว้าเอง
        return array(
            'crop' => $enriched,
            'region_match' => $regionMatch,
            'mismatch_reason' => $regionMatch ? null : self::MISMATCH_WRONG_REGION,
            'growth_months' => (int) $crop['growth_months'],
            'display_name' => $crop['name_th'],
            'coin_multiplier' => $coinMult,
            'suitability_level' => $rate
                ? $rate['suitability_level']
                : ($regionMatch ? 'good' : 'poor'),
            'warning' => null,
        );
    }

    public static function yieldMultiplier(
        $coinMultiplier,
        $mismatchReason = null,
        $seasonMatch = true
    ) {
        $config = require __DIR__ . '/../config/app.php';
        $regionMult = (float) $coinMultiplier;
        if ($regionMult <= 0) {
            if ($mismatchReason === self::MISMATCH_UNKNOWN) {
                $regionMult = (float) ((isset($config['crop_unknown_multiplier']) ? $config['crop_unknown_multiplier'] : 0.25));
            } else {
                $regionMult = (float) ((isset($config['crop_wrong_region_multiplier']) ? $config['crop_wrong_region_multiplier'] : 0.35));
            }
        }

        $seasonMult = $seasonMatch
            ? (float) ((isset($config['crop_season_match_multiplier']) ? $config['crop_season_match_multiplier'] : 1.0))
            : (float) ((isset($config['crop_wrong_season_multiplier']) ? $config['crop_wrong_season_multiplier'] : 0.5));

        return $regionMult * $seasonMult;
    }

    public static function validateAndSave($playerId, $year, array $cards)
    {
        $player = PlayerModel::findById($playerId);
        if (!$player) {
            Response::error('ไม่พบผู้เล่น', 404);
        }

        $plantCards = array_values(array_filter($cards, function ($c) {
            return $c['card_code'] === 'PLANT';
        }));

        $pdo = Db::connection();
        $pdo->prepare('DELETE FROM player_crop_plans WHERE player_id = ? AND year = ?')
            ->execute([$playerId, $year]);

        if (count($plantCards) === 0) {
            return [
                'crop_name' => null,
                'crop_name_en' => null,
                'input_crop_name' => null,
                'growth_months' => 0,
                'plant_month' => null,
                'harvest_month' => null,
                'region_match' => false,
                'season_match' => false,
                'mismatch_reason' => null,
                'coin_multiplier' => 0,
                'suitability_level' => null,
                'ideal_plant_months' => [],
                'ideal_seasons' => [],
                'warning' => null,
                'warnings' => [],
                'yield_multiplier_hint' => 0,
                'total_growth_months' => 0,
                'plant_count' => 0,
                'plans' => [],
            ];
        }

        $plans = [];
        $insert = $pdo->prepare(
            'INSERT INTO player_crop_plans
             (player_id, year, crop_id, crop_name, input_crop_name, plant_month, harvest_month,
              growth_months, region_match, mismatch_reason, season_match, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($plantCards as $plant) {
            $cropName = trim((string) ((isset($plant['crop_name']) ? $plant['crop_name'] : '')));
            if ($cropName === '') {
                Response::error('การ์ดปลูกพืชต้องระบุชื่อพืช', 400);
            }

            $resolved = self::assertKnownCrop($player, $cropName);
            $plantMonth = (int) $plant['month'];
            $growth = $resolved['growth_months'];
            $harvestMonth = $plantMonth + $growth - 1;
            $crop = $resolved['crop'];
            $seasonMatch = true;

            // คำนวณฤดูกาลภายในเท่านั้น — ไม่แจ้งเดือน/ฤดูที่แนะนำให้ผู้เล่น
            if ($crop) {
                $seasonCheck = SeasonModel::checkPlantMonth((int) $crop['id'], $plantMonth);
                $seasonMatch = $seasonCheck['match'];
            }

            $insert->execute([
                $playerId,
                $year,
                $crop ? (int) $crop['id'] : null,
                $resolved['display_name'],
                $cropName,
                $plantMonth,
                $harvestMonth,
                $growth,
                $resolved['region_match'] ? 1 : 0,
                $resolved['mismatch_reason'],
                $seasonMatch ? 1 : 0,
                'planned',
            ]);

            $plans[] = [
                'crop_name' => $resolved['display_name'],
                'plant_month' => $plantMonth,
                'harvest_month' => $harvestMonth,
                'growth_months' => $growth,
                'region_match' => $resolved['region_match'],
                'season_match' => $seasonMatch,
                'coin_multiplier' => $resolved['coin_multiplier'],
            ];
        }

        $first = $plans[0];
        return [
            'crop_name' => $first['crop_name'],
            'crop_name_en' => null,
            'input_crop_name' => (isset($plantCards[0]['crop_name']) ? $plantCards[0]['crop_name'] : null),
            'growth_months' => $first['growth_months'],
            'plant_month' => $first['plant_month'],
            'harvest_month' => $first['harvest_month'],
            'region_match' => $first['region_match'],
            'season_match' => $first['season_match'],
            'mismatch_reason' => null,
            'coin_multiplier' => $first['coin_multiplier'],
            'suitability_level' => null,
            'ideal_plant_months' => [],
            'ideal_seasons' => [],
            'warning' => null,
            'warnings' => [],
            'yield_multiplier_hint' => $first['coin_multiplier'],
            'total_growth_months' => $first['growth_months'],
            'plant_count' => count($plans),
            'plans' => $plans,
        ];
    }

    public static function listForPlayerYear($playerId, $year)
    {
        $stmt = Db::connection()->prepare(
            'SELECT pcp.*, c.name_en AS crop_name_en, c.capability_bonus, c.base_coin
             FROM player_crop_plans pcp
             LEFT JOIN crops c ON c.id = pcp.crop_id
             WHERE pcp.player_id = ? AND pcp.year = ?
             ORDER BY pcp.plant_month'
        );
        $stmt->execute([$playerId, $year]);
        return array_map(function (array $row) {
            return [
                'id' => (int) $row['id'],
                'crop_id' => $row['crop_id'] ? (int) $row['crop_id'] : null,
                'crop_name' => $row['crop_name'],
                'input_crop_name' => (isset($row['input_crop_name']) ? $row['input_crop_name'] : $row['crop_name']),
                'crop_name_en' => (isset($row['crop_name_en']) ? $row['crop_name_en'] : null),
                'plant_month' => (int) $row['plant_month'],
                'harvest_month' => (int) $row['harvest_month'],
                'growth_months' => (int) $row['growth_months'],
                'region_match' => (bool) ((isset($row['region_match']) ? $row['region_match'] : true)),
                'season_match' => (bool) ((isset($row['season_match']) ? $row['season_match'] : true)),
                'mismatch_reason' => $row['mismatch_reason'],
                'status' => $row['status'],
                'yield_amount' => (int) $row['yield_amount'],
                'sold' => (bool) $row['sold'],
            ];
        }, $stmt->fetchAll());
    }

    public static function getActivePlan($playerId, $year)
    {
        $stmt = Db::connection()->prepare(
            'SELECT * FROM player_crop_plans
             WHERE player_id = ? AND year = ? AND status IN (\'planned\', \'growing\')
             LIMIT 1'
        );
        $stmt->execute([$playerId, $year]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
