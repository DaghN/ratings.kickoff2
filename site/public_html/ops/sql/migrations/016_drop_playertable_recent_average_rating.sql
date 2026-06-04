-- SCH-016: Drop retired playertable.RecentAverageRating (website no longer reads or writes it).
-- Was: rolling AVG of player's own NewRatingA/B over last N games (legacy C++ per-game ratedresults scan).
-- Idempotent: no-op when column already absent.
-- Prod C++ may still reference until post-game cutover; coordinate Steve before prod apply.

SET @has_recent_avg := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'playertable'
    AND COLUMN_NAME = 'RecentAverageRating'
);

SET @sql := IF(
  @has_recent_avg = 0,
  'SELECT 1',
  'ALTER TABLE `playertable` DROP COLUMN `RecentAverageRating`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
