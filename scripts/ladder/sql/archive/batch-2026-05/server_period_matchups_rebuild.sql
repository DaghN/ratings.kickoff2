-- Rebuild server_period_matchups from ratedresults.
-- Destructive: truncates and repopulates. Run only through a reviewed wrapper/handoff.

SET time_zone = '+00:00';

TRUNCATE TABLE `server_period_matchups`;

-- Day
INSERT INTO `server_period_matchups`
  (`period_type`, `period_start`, `player_a`, `player_b`, `games`)
SELECT 'day', DATE(`Date`) AS ps,
       LEAST(idA, idB) AS player_a,
       GREATEST(idA, idB) AS player_b,
       COUNT(*) AS games
FROM `ratedresults`
GROUP BY ps, player_a, player_b;

-- Week
INSERT INTO `server_period_matchups`
  (`period_type`, `period_start`, `player_a`, `player_b`, `games`)
SELECT 'week', DATE_SUB(DATE(`Date`), INTERVAL WEEKDAY(`Date`) DAY) AS ps,
       LEAST(idA, idB) AS player_a,
       GREATEST(idA, idB) AS player_b,
       COUNT(*) AS games
FROM `ratedresults`
GROUP BY ps, player_a, player_b;

-- Month
INSERT INTO `server_period_matchups`
  (`period_type`, `period_start`, `player_a`, `player_b`, `games`)
SELECT 'month', CAST(DATE_FORMAT(`Date`, '%Y-%m-01') AS DATE) AS ps,
       LEAST(idA, idB) AS player_a,
       GREATEST(idA, idB) AS player_b,
       COUNT(*) AS games
FROM `ratedresults`
GROUP BY ps, player_a, player_b;

-- Year
INSERT INTO `server_period_matchups`
  (`period_type`, `period_start`, `player_a`, `player_b`, `games`)
SELECT 'year', CAST(CONCAT(YEAR(`Date`), '-01-01') AS DATE) AS ps,
       LEAST(idA, idB) AS player_a,
       GREATEST(idA, idB) AS player_b,
       COUNT(*) AS games
FROM `ratedresults`
GROUP BY ps, player_a, player_b;
