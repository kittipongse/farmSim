@echo off
chcp 65001 >nul
set MYSQL=C:\xampp\mysql\bin\mysql.exe
set DB=cp393722_farmsim

"%MYSQL%" -u root --default-character-set=utf8mb4 %DB% -e "SET FOREIGN_KEY_CHECKS=0; TRUNCATE TABLE market_transactions; TRUNCATE TABLE market_prices; TRUNCATE TABLE player_event_responses; TRUNCATE TABLE room_year_events; TRUNCATE TABLE player_crop_plans; TRUNCATE TABLE player_year_cards; TRUNCATE TABLE player_scores; TRUNCATE TABLE player_resources; TRUNCATE TABLE game_logs; TRUNCATE TABLE players; TRUNCATE TABLE game_rooms; TRUNCATE TABLE breaking_news_templates; TRUNCATE TABLE region_crop_seasons; TRUNCATE TABLE agricultural_season_months; TRUNCATE TABLE agricultural_seasons; TRUNCATE TABLE region_crops; TRUNCATE TABLE cards; TRUNCATE TABLE country_regions; TRUNCATE TABLE countries; SET FOREIGN_KEY_CHECKS=1;"

"%MYSQL%" -u root --default-character-set=utf8mb4 %DB% < "%~dp0seed.sql"
echo Seed reimported into %DB%.
pause
