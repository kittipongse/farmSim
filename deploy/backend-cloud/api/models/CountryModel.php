<?php

require_once __DIR__ . '/../config/Db.php';

class CountryModel
{
    public static function all()
    {
        $stmt = Db::connection()->query(
            'SELECT id, code, name_th, name_en FROM countries ORDER BY id'
        );
        return $stmt->fetchAll();
    }

    public static function find($id)
    {
        $stmt = Db::connection()->prepare(
            'SELECT id, code, name_th, name_en FROM countries WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function regionsByCountry($countryId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT id, country_id, code, name_th, name_en,
                    default_coins, default_water, default_soil_quality, description
             FROM country_regions WHERE country_id = ? ORDER BY id'
        );
        $stmt->execute([$countryId]);
        return $stmt->fetchAll();
    }

    public static function cropsByRegion($regionId)
    {
        require_once __DIR__ . '/CropModel.php';
        return CropModel::cropsByRegion($regionId);
    }
}
