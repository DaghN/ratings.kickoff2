-- Rebuild player_period_league from ratedresults.
-- Destructive: truncates and repopulates. Run only through a reviewed wrapper/handoff.

SET time_zone = '+00:00';

TRUNCATE TABLE `player_period_league`;

-- Day
INSERT INTO `player_period_league`
  (`period_type`, `period_start`, `player_id`, `played`, `wins`, `draws`, `losses`, `goals_for`, `goals_against`, `goal_difference`, `points`)
SELECT 'day', `period_start`, `pid`,
       COUNT(*) AS played,
       SUM(w) AS wins, SUM(d) AS draws, SUM(l) AS losses,
       SUM(gf) AS goals_for, SUM(ga) AS goals_against,
       CAST(SUM(gf) - SUM(ga) AS SIGNED) AS goal_difference,
       SUM(pts) AS points
FROM (
  SELECT DATE(`Date`) AS `period_start`, `idA` AS pid,
         CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS w,
         CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
         CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS l,
         GoalsA AS gf, GoalsB AS ga,
         CASE WHEN ActualScore = 1 THEN 3 WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS pts
  FROM `ratedresults`
  UNION ALL
  SELECT DATE(`Date`) AS `period_start`, `idB` AS pid,
         CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS w,
         CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
         CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS l,
         GoalsB AS gf, GoalsA AS ga,
         CASE WHEN ActualScore = 0 THEN 3 WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS pts
  FROM `ratedresults`
) AS sides
GROUP BY `period_start`, `pid`;

-- Week
INSERT INTO `player_period_league`
  (`period_type`, `period_start`, `player_id`, `played`, `wins`, `draws`, `losses`, `goals_for`, `goals_against`, `goal_difference`, `points`)
SELECT 'week', `period_start`, `pid`,
       COUNT(*) AS played,
       SUM(w) AS wins, SUM(d) AS draws, SUM(l) AS losses,
       SUM(gf) AS goals_for, SUM(ga) AS goals_against,
       CAST(SUM(gf) - SUM(ga) AS SIGNED) AS goal_difference,
       SUM(pts) AS points
FROM (
  SELECT DATE_SUB(DATE(`Date`), INTERVAL WEEKDAY(`Date`) DAY) AS `period_start`, `idA` AS pid,
         CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS w,
         CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
         CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS l,
         GoalsA AS gf, GoalsB AS ga,
         CASE WHEN ActualScore = 1 THEN 3 WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS pts
  FROM `ratedresults`
  UNION ALL
  SELECT DATE_SUB(DATE(`Date`), INTERVAL WEEKDAY(`Date`) DAY) AS `period_start`, `idB` AS pid,
         CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS w,
         CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
         CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS l,
         GoalsB AS gf, GoalsA AS ga,
         CASE WHEN ActualScore = 0 THEN 3 WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS pts
  FROM `ratedresults`
) AS sides
GROUP BY `period_start`, `pid`;

-- Month
INSERT INTO `player_period_league`
  (`period_type`, `period_start`, `player_id`, `played`, `wins`, `draws`, `losses`, `goals_for`, `goals_against`, `goal_difference`, `points`)
SELECT 'month', `period_start`, `pid`,
       COUNT(*) AS played,
       SUM(w) AS wins, SUM(d) AS draws, SUM(l) AS losses,
       SUM(gf) AS goals_for, SUM(ga) AS goals_against,
       CAST(SUM(gf) - SUM(ga) AS SIGNED) AS goal_difference,
       SUM(pts) AS points
FROM (
  SELECT CAST(DATE_FORMAT(`Date`, '%Y-%m-01') AS DATE) AS `period_start`, `idA` AS pid,
         CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS w,
         CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
         CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS l,
         GoalsA AS gf, GoalsB AS ga,
         CASE WHEN ActualScore = 1 THEN 3 WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS pts
  FROM `ratedresults`
  UNION ALL
  SELECT CAST(DATE_FORMAT(`Date`, '%Y-%m-01') AS DATE) AS `period_start`, `idB` AS pid,
         CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS w,
         CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
         CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS l,
         GoalsB AS gf, GoalsA AS ga,
         CASE WHEN ActualScore = 0 THEN 3 WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS pts
  FROM `ratedresults`
) AS sides
GROUP BY `period_start`, `pid`;

-- Year
INSERT INTO `player_period_league`
  (`period_type`, `period_start`, `player_id`, `played`, `wins`, `draws`, `losses`, `goals_for`, `goals_against`, `goal_difference`, `points`)
SELECT 'year', `period_start`, `pid`,
       COUNT(*) AS played,
       SUM(w) AS wins, SUM(d) AS draws, SUM(l) AS losses,
       SUM(gf) AS goals_for, SUM(ga) AS goals_against,
       CAST(SUM(gf) - SUM(ga) AS SIGNED) AS goal_difference,
       SUM(pts) AS points
FROM (
  SELECT CAST(CONCAT(YEAR(`Date`), '-01-01') AS DATE) AS `period_start`, `idA` AS pid,
         CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS w,
         CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
         CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS l,
         GoalsA AS gf, GoalsB AS ga,
         CASE WHEN ActualScore = 1 THEN 3 WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS pts
  FROM `ratedresults`
  UNION ALL
  SELECT CAST(CONCAT(YEAR(`Date`), '-01-01') AS DATE) AS `period_start`, `idB` AS pid,
         CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS w,
         CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
         CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS l,
         GoalsB AS gf, GoalsA AS ga,
         CASE WHEN ActualScore = 0 THEN 3 WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS pts
  FROM `ratedresults`
) AS sides
GROUP BY `period_start`, `pid`;
