<?php

require_once __DIR__ . '/../config/Db.php';

class CropModel
{
    const SUITABILITY_GOOD_THRESHOLD = 0.85;

    public static function findById($cropId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT * FROM crops WHERE id = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$cropId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByNameInCountry($countryId, $cropName)
    {
        $name = trim($cropName);
        if ($name === '') {
            return null;
        }

        // Exact match only (TH/EN) — ไม่ใช้ LIKE เพื่อบังคับพิมพ์ชื่อถูกต้อง
        $stmt = Db::connection()->prepare(
            'SELECT c.* FROM crops c
             WHERE c.country_id = ? AND c.is_active = 1
               AND (LOWER(c.name_th) = LOWER(?) OR LOWER(c.name_en) = LOWER(?))
             LIMIT 1'
        );
        $stmt->execute(array($countryId, $name, $name));
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getRegionRate($cropId, $regionId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT crr.*, cr.name_th AS region_name_th, cr.code AS region_code
             FROM crop_region_rates crr
             JOIN country_regions cr ON cr.id = crr.region_id
             WHERE crr.crop_id = ? AND crr.region_id = ?
             LIMIT 1'
        );
        $stmt->execute([$cropId, $regionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getBestRegionForCrop($cropId, $countryId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT crr.coin_multiplier, cr.name_th AS region_name_th, cr.id AS region_id
             FROM crop_region_rates crr
             JOIN country_regions cr ON cr.id = crr.region_id
             WHERE crr.crop_id = ? AND cr.country_id = ?
             ORDER BY crr.coin_multiplier DESC
             LIMIT 1'
        );
        $stmt->execute([$cropId, $countryId]);
        return $stmt->fetch() ?: null;
    }

    public static function cropsByRegion($regionId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT c.id, c.code, c.name_th, c.name_en, c.category, c.growth_months,
                    c.crop_category, c.base_coin, c.base_buy_price, c.base_sell_price,
                    c.capability_bonus, crr.suitability_level, crr.coin_multiplier
             FROM crops c
             JOIN crop_region_rates crr ON crr.crop_id = c.id
             WHERE crr.region_id = ? AND c.is_active = 1
             ORDER BY crr.coin_multiplier DESC, c.name_th'
        );
        $stmt->execute([$regionId]);
        return $stmt->fetchAll();
    }

    public static function cropsByCountry($countryId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT c.id, c.code, c.name_th, c.name_en, c.category, c.growth_months,
                    c.crop_category, c.base_coin, c.base_buy_price, c.base_sell_price,
                    c.capability_bonus
             FROM crops c
             WHERE c.country_id = ? AND c.is_active = 1
             ORDER BY c.category, c.name_th'
        );
        $stmt->execute([$countryId]);
        return $stmt->fetchAll();
    }

    public static function isRegionMatch($coinMultiplier)
    {
        return (float) $coinMultiplier >= self::SUITABILITY_GOOD_THRESHOLD;
    }

    public static function suitabilityLabel($level)
    {
        $labels = [
            'excellent' => 'เหมาะสมมาก',
            'good' => 'เหมาะสม',
            'moderate' => 'พอปลูกได้',
            'poor' => 'ไม่ค่อยเหมาะสม',
            'unsuitable' => 'ไม่เหมาะสม',
        ];
        return isset($labels[$level]) ? $labels[$level] : $level;
    }

    /**
     * สุ่มชื่อพืชในประเทศ (ไม่แนะนำตามความใกล้เคียง — ให้ผู้เล่นค้นคว้าเอง)
     *
     * @return string[]
     */
    public static function suggestNames($countryId, $input = '', $limit = 3)
    {
        $limit = max(1, min(10, (int) $limit));
        $stmt = Db::connection()->prepare(
            'SELECT name_th FROM crops
             WHERE country_id = ? AND is_active = 1
             ORDER BY RAND()
             LIMIT ' . $limit
        );
        $stmt->execute(array($countryId));
        $names = array();
        foreach ($stmt->fetchAll() as $row) {
            $names[] = $row['name_th'];
        }
        return array_values(array_unique($names));
    }
}
