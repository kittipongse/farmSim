<?php

class AppConfig
{
    private static $config = null;

    public static function all()
    {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/app.php';
        }

        return self::$config;
    }

    public static function get($key, $default = null)
    {
        $config = self::all();
        return isset($config[$key]) ? $config[$key] : $default;
    }

    public static function simulationMonthSeconds()
    {
        return (int) self::get('simulation_month_seconds', 600);
    }

    public static function simulationEventSeconds()
    {
        return (int) self::get('simulation_event_seconds', 15);
    }

    public static function breakingNewsSeconds()
    {
        return (int) self::get('breaking_news_seconds', 15);
    }

    public static function maxPlanAdjustments()
    {
        return (int) self::get('max_plan_adjustments', 2);
    }

    public static function bonusQuizMonths()
    {
        $months = self::get('bonus_quiz_months', array(3, 6, 9, 12));
        return is_array($months) ? array_map('intval', $months) : array(3, 6, 9, 12);
    }

    public static function bonusQuizSeconds()
    {
        return (int) self::get('bonus_quiz_seconds', 25);
    }

    public static function bonusQuizIdleSeconds()
    {
        return (int) self::get('bonus_quiz_idle_seconds', 8);
    }

    public static function bonusQuizIdleCloseSeconds()
    {
        return (int) self::get('bonus_quiz_idle_close_seconds', 5);
    }

    public static function bonusQuizRevealSeconds()
    {
        return (int) self::get('bonus_quiz_reveal_seconds', 6);
    }
}
