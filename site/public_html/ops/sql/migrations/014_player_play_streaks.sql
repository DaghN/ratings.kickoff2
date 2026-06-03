-- SCH-014: Per-player rated play streaks (UTC day / Mon–Sun week) + HoF columns on generalstatstable.
-- Register: docs/coordination/schema-register.md
-- Contract: docs/website-data-contract.md § player_play_streaks

SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `player_play_streaks` (
  `player_id` int(11) NOT NULL,
  `streak_type` enum('day','week') NOT NULL,
  `current_streak` int(11) NOT NULL DEFAULT 0,
  `current_anchor` date DEFAULT NULL COMMENT 'UTC day or week Monday (UTC) of last period in current run',
  `current_last_game_id` int(11) DEFAULT NULL COMMENT 'First rated game on current_anchor period',
  `best_streak` int(11) NOT NULL DEFAULT 0,
  `best_achieved_at` datetime DEFAULT NULL COMMENT 'ratedresults.Date of best_last_game_id',
  `best_last_game_id` int(11) DEFAULT NULL COMMENT 'First rated game on last day/week of best run',
  PRIMARY KEY (`player_id`, `streak_type`),
  KEY `idx_player_play_streaks_hof` (`streak_type`, `best_streak`, `best_achieved_at`, `player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @has_play_streak_hof := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE table_schema = DATABASE()
    AND table_name = 'generalstatstable'
    AND column_name = 'LongestDailyPlayStreak'
);

SET @sql := IF(
  @has_play_streak_hof = 0,
  'ALTER TABLE `generalstatstable`
    ADD COLUMN `LongestDailyPlayStreak` int(11) DEFAULT NULL AFTER `LongestNonLossStreak`,
    ADD COLUMN `LongestWeeklyPlayStreak` int(11) DEFAULT NULL AFTER `LongestDailyPlayStreak`,
    ADD COLUMN `LongestDailyPlayStreakID` int(11) DEFAULT NULL AFTER `LongestNonLossStreakID`,
    ADD COLUMN `LongestWeeklyPlayStreakID` int(11) DEFAULT NULL AFTER `LongestDailyPlayStreakID`,
    ADD COLUMN `LongestDailyPlayStreakName` varchar(16) DEFAULT NULL AFTER `LongestNonLossStreakName`,
    ADD COLUMN `LongestWeeklyPlayStreakName` varchar(16) DEFAULT NULL AFTER `LongestDailyPlayStreakName`,
    ADD COLUMN `LongestDailyPlayStreakDate` mediumtext DEFAULT NULL AFTER `LongestNonLossStreakDate`,
    ADD COLUMN `LongestWeeklyPlayStreakDate` mediumtext DEFAULT NULL AFTER `LongestDailyPlayStreakDate`,
    ADD COLUMN `LongestDailyPlayStreakGameID` int(11) DEFAULT NULL AFTER `MostGoalsScoredInOneGameGameID`,
    ADD COLUMN `LongestWeeklyPlayStreakGameID` int(11) DEFAULT NULL AFTER `LongestDailyPlayStreakGameID`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
