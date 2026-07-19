<?php

require_once __DIR__ . '/../config/Db.php';

class SeasonModel
{
    public static function seasonsByCountry($countryId)
    {
        $pdo = Db::connection();
        $stmt = $pdo->prepare(
            'SELECT id, code, name_th, name_en, description_th, sort_order
             FROM agricultural_seasons WHERE country_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$countryId]);
        $seasons = $stmt->fetchAll();

        $monthStmt = $pdo->prepare(
            'SELECT month FROM agricultural_season_months WHERE season_id = ? ORDER BY month'
        );

        return array_map(function (array $season) use ($monthStmt) {
            $monthStmt->execute([(int) $season['id']]);
            $months = array_map('intval', array_column($monthStmt->fetchAll(), 'month'));
            return [
                'id' => (int) $season['id'],
                'code' => $season['code'],
                'name_th' => $season['name_th'],
                'name_en' => $season['name_en'],
                'description_th' => $season['description_th'],
                'months' => $months,
            ];
        }, $seasons);
    }

    public static function idealPlantMonths($cropId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT DISTINCT asm.month
             FROM crop_seasons cs
             JOIN agricultural_season_months asm ON asm.season_id = cs.season_id
             WHERE cs.crop_id = ?
             ORDER BY asm.month'
        );
        $stmt->execute([$cropId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'month'));
    }

    public static function idealSeasonNames($cropId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT DISTINCT s.name_th
             FROM crop_seasons cs
             JOIN agricultural_seasons s ON s.id = cs.season_id
             WHERE cs.crop_id = ?
             ORDER BY s.sort_order, s.id'
        );
        $stmt->execute([$cropId]);
        return array_column($stmt->fetchAll(), 'name_th');
    }

    public static function checkPlantMonth($cropId, $plantMonth)
    {
        $idealMonths = self::idealPlantMonths($cropId);
        if ($idealMonths === []) {
            return ['match' => true, 'ideal_months' => [], 'ideal_seasons' => [], 'message' => null];
        }

        $match = in_array($plantMonth, $idealMonths, true);
        $seasonNames = self::idealSeasonNames($cropId);

        if ($match) {
            return [
                'match' => true,
                'ideal_months' => $idealMonths,
                'ideal_seasons' => $seasonNames,
                'message' => null,
            ];
        }

        $monthList = self::formatMonthList($idealMonths);
        $seasonList = implode(', ', $seasonNames);

        return [
            'match' => false,
            'ideal_months' => $idealMonths,
            'ideal_seasons' => $seasonNames,
            'message' => "เดือนปลูกไม่ตรงฤดูกาลที่เหมาะ ({$seasonList}) — ควรปลูกเดือน {$monthList} ผลผลิตจะต่ำกว่าปกติ",
        ];
    }

    public static function getPlantingGuide($regionId)
    {
        $pdo = Db::connection();
        $regionStmt = $pdo->prepare(
            'SELECT cr.*, c.id AS country_id, c.name_th AS country_name_th
             FROM country_regions cr
             JOIN countries c ON c.id = cr.country_id
             WHERE cr.id = ?'
        );
        $regionStmt->execute([$regionId]);
        $region = $regionStmt->fetch();
        if (!$region) {
            return ['region' => null, 'seasons' => [], 'crops' => []];
        }

        $countryId = (int) $region['country_id'];
        $seasons = self::seasonsByCountry($countryId);

        $cropStmt = $pdo->prepare(
            'SELECT c.id, c.name_th, c.name_en, c.growth_months, c.crop_category,
                    c.base_coin, crr.coin_multiplier, crr.suitability_level
             FROM crops c
             JOIN crop_region_rates crr ON crr.crop_id = c.id
             WHERE crr.region_id = ? AND c.is_active = 1
             ORDER BY crr.coin_multiplier DESC, c.growth_months, c.name_th'
        );
        $cropStmt->execute([$regionId]);
        $crops = array_map(function (array $crop) {
            $cropId = (int) $crop['id'];
            return [
                'id' => $cropId,
                'name_th' => $crop['name_th'],
                'name_en' => $crop['name_en'],
                'growth_months' => (int) $crop['growth_months'],
                'crop_category' => $crop['crop_category'],
                'base_coin' => (int) $crop['base_coin'],
                'coin_multiplier' => (float) $crop['coin_multiplier'],
                'suitability_level' => $crop['suitability_level'],
                'ideal_seasons' => self::idealSeasonNames($cropId),
                'ideal_plant_months' => self::idealPlantMonths($cropId),
            ];
        }, $cropStmt->fetchAll());

        return [
            'region' => [
                'id' => (int) $region['id'],
                'name_th' => $region['name_th'],
                'name_en' => $region['name_en'],
                'country_name_th' => $region['country_name_th'],
            ],
            'seasons' => $seasons,
            'crops' => $crops,
        ];
    }

    public static function formatMonthList(array $months)
    {
        $labels = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
            5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
            9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.',
        ];
        return implode(', ', array_map(function ($m) use ($labels) { return isset($labels[$m]) ? $labels[$m] : (string) $m; }, $months));
    }
}
