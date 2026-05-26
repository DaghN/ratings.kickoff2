-- Rebuild server_daily_activity directly from ratedresults (fallback).
-- Use when player_period_games is not available.
-- Destructive: truncates and repopulates.

SET time_zone = '+00:00';

TRUNCATE TABLE `server_daily_activity`;

INSERT INTO `server_daily_activity` (`activity_day`, `rated_games`, `active_players`)
SELECT
  g.`day` AS `activity_day`,
  g.`rated_games`,
  p.`active_players`
FROM (
  SELECT DATE(`Date`) AS `day`, COUNT(*) AS `rated_games`
  FROM `ratedresults`
  WHERE `Date` IS NOT NULL
  GROUP BY `day`
) g
INNER JOIN (
  SELECT `day`, COUNT(DISTINCT `player_id`) AS `active_players`
  FROM (
    SELECT DATE(`Date`) AS `day`, `idA` AS `player_id` FROM `ratedresults` WHERE `idA` IS NOT NULL
    UNION ALL
    SELECT DATE(`Date`) AS `day`, `idB` AS `player_id` FROM `ratedresults` WHERE `idB` IS NOT NULL
  ) appearances
  GROUP BY `day`
) p ON g.`day` = p.`day`
ORDER BY `activity_day` ASC;
