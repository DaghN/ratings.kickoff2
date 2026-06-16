-- SCH-023: Extend rated play streaks to calendar month/year + HoF GST columns.
-- Register: docs/coordination/schema-register.md
-- Contract: docs/activity-wing-stored-truth-policy.md § player_play_streaks

SET time_zone = '+00:00';

ALTER TABLE `player_play_streaks`
  MODIFY `streak_type` enum('day','week','month','year') NOT NULL;

ALTER TABLE `player_play_streaks`
  MODIFY `current_anchor` date DEFAULT NULL
    COMMENT 'UTC day, week Monday, month Y-m-01, or year Y-01-01 of last period in current run';

ALTER TABLE `player_play_streaks`
  MODIFY `best_last_game_id` int(11) DEFAULT NULL
    COMMENT 'First rated game on last period of best run';

SET @has_monthly_play_streak_hof := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE table_schema = DATABASE()
    AND table_name = 'generalstatstable'
    AND column_name = 'LongestMonthlyPlayStreak'
);

SET @sql := IF(
  @has_monthly_play_streak_hof = 0,
  'ALTER TABLE `generalstatstable`
    ADD COLUMN `LongestMonthlyPlayStreak` int(11) DEFAULT NULL AFTER `LongestWeeklyPlayStreak`,
    ADD COLUMN `LongestYearlyPlayStreak` int(11) DEFAULT NULL AFTER `LongestMonthlyPlayStreak`,
    ADD COLUMN `LongestMonthlyPlayStreakID` int(11) DEFAULT NULL AFTER `LongestWeeklyPlayStreakID`,
    ADD COLUMN `LongestYearlyPlayStreakID` int(11) DEFAULT NULL AFTER `LongestMonthlyPlayStreakID`,
    ADD COLUMN `LongestMonthlyPlayStreakName` varchar(16) DEFAULT NULL AFTER `LongestWeeklyPlayStreakName`,
    ADD COLUMN `LongestYearlyPlayStreakName` varchar(16) DEFAULT NULL AFTER `LongestMonthlyPlayStreakName`,
    ADD COLUMN `LongestMonthlyPlayStreakDate` mediumtext DEFAULT NULL AFTER `LongestWeeklyPlayStreakDate`,
    ADD COLUMN `LongestYearlyPlayStreakDate` mediumtext DEFAULT NULL AFTER `LongestMonthlyPlayStreakDate`,
    ADD COLUMN `LongestMonthlyPlayStreakGameID` int(11) DEFAULT NULL AFTER `LongestWeeklyPlayStreakGameID`,
    ADD COLUMN `LongestYearlyPlayStreakGameID` int(11) DEFAULT NULL AFTER `LongestMonthlyPlayStreakGameID`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
