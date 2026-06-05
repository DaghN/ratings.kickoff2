-- Rebuild player_period_games from ratedresults.
-- Destructive: truncates and repopulates the aggregate. Run only through a reviewed wrapper/handoff.

SET time_zone = '+00:00';

TRUNCATE TABLE `player_period_games`;

INSERT INTO `player_period_games` (`period_type`, `period_start`, `player_id`, `games`)
SELECT 'day', `period_start`, `player_id`, COUNT(*) AS `games`
FROM (
  SELECT DATE(`Date`) AS `period_start`, `idA` AS `player_id`
  FROM `ratedresults`
  WHERE `idA` IS NOT NULL
  UNION ALL
  SELECT DATE(`Date`) AS `period_start`, `idB` AS `player_id`
  FROM `ratedresults`
  WHERE `idB` IS NOT NULL
) AS appearances
GROUP BY `period_start`, `player_id`;

INSERT INTO `player_period_games` (`period_type`, `period_start`, `player_id`, `games`)
SELECT 'week', `period_start`, `player_id`, COUNT(*) AS `games`
FROM (
  SELECT DATE_SUB(DATE(`Date`), INTERVAL WEEKDAY(`Date`) DAY) AS `period_start`, `idA` AS `player_id`
  FROM `ratedresults`
  WHERE `idA` IS NOT NULL
  UNION ALL
  SELECT DATE_SUB(DATE(`Date`), INTERVAL WEEKDAY(`Date`) DAY) AS `period_start`, `idB` AS `player_id`
  FROM `ratedresults`
  WHERE `idB` IS NOT NULL
) AS appearances
GROUP BY `period_start`, `player_id`;

INSERT INTO `player_period_games` (`period_type`, `period_start`, `player_id`, `games`)
SELECT 'month', `period_start`, `player_id`, COUNT(*) AS `games`
FROM (
  SELECT CAST(DATE_FORMAT(`Date`, '%Y-%m-01') AS DATE) AS `period_start`, `idA` AS `player_id`
  FROM `ratedresults`
  WHERE `idA` IS NOT NULL
  UNION ALL
  SELECT CAST(DATE_FORMAT(`Date`, '%Y-%m-01') AS DATE) AS `period_start`, `idB` AS `player_id`
  FROM `ratedresults`
  WHERE `idB` IS NOT NULL
) AS appearances
GROUP BY `period_start`, `player_id`;

INSERT INTO `player_period_games` (`period_type`, `period_start`, `player_id`, `games`)
SELECT 'year', `period_start`, `player_id`, COUNT(*) AS `games`
FROM (
  SELECT CAST(CONCAT(YEAR(`Date`), '-01-01') AS DATE) AS `period_start`, `idA` AS `player_id`
  FROM `ratedresults`
  WHERE `idA` IS NOT NULL
  UNION ALL
  SELECT CAST(CONCAT(YEAR(`Date`), '-01-01') AS DATE) AS `period_start`, `idB` AS `player_id`
  FROM `ratedresults`
  WHERE `idB` IS NOT NULL
) AS appearances
GROUP BY `period_start`, `player_id`;
