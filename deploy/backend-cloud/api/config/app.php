<?php

return [
    'lobby_timeout_seconds' => 120,
    'lobby_extend_seconds' => 30,
    'countdown_seconds' => 3,
    'max_players' => 8,
    'game_years' => 1,
    'simulation_month_seconds' => 10,
    // Breaking News: อ่านข่าวบนจอ + หยิบมือถือ + เลือกวิธีรับมือ (8 คนสูงสุด)
    'breaking_news_seconds' => 15,
    'bonus_quiz_months' => [3, 6, 9, 12],
    // Quiz: A/B เร็ว — พอให้ทุกคนแตะตอบ (เดือนหยุดรอระหว่างตอบ)
    'bonus_quiz_seconds' => 25,
    // ไม่มีใครตอบ: รอ 8 วิ → นับถอยหลัง 5 วิ → ปิดอัตโนมัติ
    'bonus_quiz_idle_seconds' => 8,
    'bonus_quiz_idle_close_seconds' => 5,
    // ประกาศผู้ตอบถูกบนจอใหญ่
    'bonus_quiz_reveal_seconds' => 6,
    'presentation_seconds' => 45,
    'simulation_event_seconds' => 15,
    'max_plan_adjustments' => 2,
    'crop_region_match_multiplier' => 1.2,
    'crop_wrong_region_multiplier' => 0.35,
    'crop_unknown_multiplier' => 0.25,
    'crop_season_match_multiplier' => 1.0,
    'crop_wrong_season_multiplier' => 0.5,
    'crop_unknown_growth_months' => 4,
    'upload_dir' => __DIR__ . '/../uploads/',
    'upload_url' => '/uploads/',
    'frontend_port' => 5173,
    // ตั้งค่าเองได้ เช่น 'http://192.168.1.10:5173' ถ้า auto-detect ไม่ตรง
    'public_frontend_url' => null,
];
