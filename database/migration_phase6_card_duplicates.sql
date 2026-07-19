-- Allow duplicate decision card types in a year (one card per month still via uk_player_year_month)
-- Players may place the same card up to 12 times (once per month).

SET @db = DATABASE();

SET @exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'player_year_cards'
    AND INDEX_NAME = 'uk_player_year_card'
);

SET @sql = IF(
  @exists > 0,
  'ALTER TABLE player_year_cards DROP INDEX uk_player_year_card',
  'SELECT 1'
);
PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;
