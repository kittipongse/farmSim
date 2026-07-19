<?php

class CountryController
{
    public static function index($params)
    {
        require_once __DIR__ . '/../models/CountryModel.php';
        Response::success(CountryModel::all());
    }

    public static function regions($params)
    {
        require_once __DIR__ . '/../models/CountryModel.php';
        $countryId = (int) $params['id'];
        $country = CountryModel::find($countryId);
        if (!$country) {
            Response::error('ไม่พบประเทศ', 404);
        }
        Response::success(CountryModel::regionsByCountry($countryId));
    }

    public static function crops($params)
    {
        require_once __DIR__ . '/../models/CountryModel.php';
        $regionId = (int) $params['id'];
        Response::success(CountryModel::cropsByRegion($regionId));
    }

    public static function plantingGuide($params)
    {
        require_once __DIR__ . '/../models/SeasonModel.php';
        $regionId = (int) $params['id'];
        Response::success(SeasonModel::getPlantingGuide($regionId));
    }
}
