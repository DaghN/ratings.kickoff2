-- SCH-024: Personal-best play streak start anchor (In a row tooltips / date range).
-- Register: docs/coordination/schema-register.md
-- Contract: docs/website-data-contract.md § player_play_streaks

SET time_zone = '+00:00';

SET @has_best_anchor_start := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE table_schema = DATABASE()
    AND table_name = 'player_play_streaks'
    AND column_name = 'best_anchor_start'
);

SET @sql := IF(
  @has_best_anchor_start = 0,
  'ALTER TABLE `player_play_streaks`
    ADD COLUMN `best_anchor_start` date DEFAULT NULL
      COMMENT ''First period anchor of personal-best run (day, week Monday, month Y-m-01, year Y-01-01)''
    AFTER `best_streak`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
