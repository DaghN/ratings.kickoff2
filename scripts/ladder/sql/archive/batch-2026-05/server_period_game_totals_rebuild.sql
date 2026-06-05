-- Rebuild server_period_game_totals from ratedresults.
-- Destructive: truncates and repopulates. Run only through a reviewed wrapper/handoff.

SET time_zone = '+00:00';

TRUNCATE TABLE `server_period_game_totals`;

-- Day
INSERT INTO `server_period_game_totals`
  (`period_type`, `period_start`, `rated_games`, `total_goals`, `draws`, `double_digit_games`, `clean_sheets`)
SELECT 'day', DATE(`Date`) AS ps,
       COUNT(*) AS rated_games,
       SUM(GoalsA + GoalsB) AS total_goals,
       SUM(CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END) AS draws,
       SUM(CASE WHEN GoalsA + GoalsB >= 10 THEN 1 ELSE 0 END) AS double_digit_games,
       SUM(CASE WHEN GoalsA = 0 OR GoalsB = 0 THEN 1 ELSE 0 END) AS clean_sheets
FROM `ratedresults`
GROUP BY ps;

-- Week
INSERT INTO `server_period_game_totals`
  (`period_type`, `period_start`, `rated_games`, `total_goals`, `draws`, `double_digit_games`, `clean_sheets`)
SELECT 'week', DATE_SUB(DATE(`Date`), INTERVAL WEEKDAY(`Date`) DAY) AS ps,
       COUNT(*) AS rated_games,
       SUM(GoalsA + GoalsB) AS total_goals,
       SUM(CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END) AS draws,
       SUM(CASE WHEN GoalsA + GoalsB >= 10 THEN 1 ELSE 0 END) AS double_digit_games,
       SUM(CASE WHEN GoalsA = 0 OR GoalsB = 0 THEN 1 ELSE 0 END) AS clean_sheets
FROM `ratedresults`
GROUP BY ps;

-- Month
INSERT INTO `server_period_game_totals`
  (`period_type`, `period_start`, `rated_games`, `total_goals`, `draws`, `double_digit_games`, `clean_sheets`)
SELECT 'month', CAST(DATE_FORMAT(`Date`, '%Y-%m-01') AS DATE) AS ps,
       COUNT(*) AS rated_games,
       SUM(GoalsA + GoalsB) AS total_goals,
       SUM(CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END) AS draws,
       SUM(CASE WHEN GoalsA + GoalsB >= 10 THEN 1 ELSE 0 END) AS double_digit_games,
       SUM(CASE WHEN GoalsA = 0 OR GoalsB = 0 THEN 1 ELSE 0 END) AS clean_sheets
FROM `ratedresults`
GROUP BY ps;

-- Year
INSERT INTO `server_period_game_totals`
  (`period_type`, `period_start`, `rated_games`, `total_goals`, `draws`, `double_digit_games`, `clean_sheets`)
SELECT 'year', CAST(CONCAT(YEAR(`Date`), '-01-01') AS DATE) AS ps,
       COUNT(*) AS rated_games,
       SUM(GoalsA + GoalsB) AS total_goals,
       SUM(CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END) AS draws,
       SUM(CASE WHEN GoalsA + GoalsB >= 10 THEN 1 ELSE 0 END) AS double_digit_games,
       SUM(CASE WHEN GoalsA = 0 OR GoalsB = 0 THEN 1 ELSE 0 END) AS clean_sheets
FROM `ratedresults`
GROUP BY ps;
