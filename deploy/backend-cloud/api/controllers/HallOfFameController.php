<?php

class HallOfFameController
{
    public static function top($params)
    {
        require_once __DIR__ . '/../models/HallOfFameModel.php';
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 5;
        Response::success(HallOfFameModel::top($limit));
    }
}
