-- Rebuild server_daily_activity from player_period_games (preferred)
-- or from ratedresults directly if player_period_games is not populated.
-- Destructive: truncates and repopulates. Run only through a reviewed wrapper/handoff.

SET time_zone = '+00:00';

TRUNCATE TABLE `server_daily_activity`;

-- Preferred path: derive from player_period_games daily rows.
-- active_players = COUNT(*) (one row per player per day)
-- rated_games = SUM(games) (total appearances / 2 would be games, but SUM is total player-games)
-- Actually rated_games per day = SUM(games) / 2 since each game produces two player rows.
-- Correction: rated_games should equal number of distinct games on that day.
-- Since each ratedresults row produces exactly 2 player_period_games rows (A + B),
-- rated_games = SUM(games) / 2 is correct.

INSERT INTO `server_daily_activity` (`activity_day`, `rated_games`, `active_players`)
SELECT
  `period_start` AS `activity_day`,
  CAST(SUM(`games`) / 2 AS UNSIGNED) AS `rated_games`,
  COUNT(*) AS `active_players`
FROM `player_period_games`
WHERE `period_type` = 'day'
GROUP BY `period_start`
ORDER BY `period_start` ASC;
