-- Rebuild player_monthly_league from ratedresults.
-- Destructive: truncates and repopulates the aggregate. Run only through a reviewed wrapper/handoff.

TRUNCATE TABLE `player_monthly_league`;

INSERT INTO `player_monthly_league` (
  `month_start`,
  `player_id`,
  `played`,
  `wins`,
  `draws`,
  `losses`,
  `goals_for`,
  `goals_against`,
  `goal_difference`,
  `points`
)
SELECT
  `month_start`,
  `player_id`,
  COUNT(*) AS `played`,
  SUM(`wins`) AS `wins`,
  SUM(`draws`) AS `draws`,
  SUM(`losses`) AS `losses`,
  SUM(`goals_for`) AS `goals_for`,
  SUM(`goals_against`) AS `goals_against`,
  SUM(`goals_for`) - SUM(`goals_against`) AS `goal_difference`,
  SUM(`points`) AS `points`
FROM (
  SELECT
    CAST(DATE_FORMAT(`Date`, '%Y-%m-01') AS DATE) AS `month_start`,
    `idA` AS `player_id`,
    CASE WHEN `ActualScore` = 1 THEN 1 ELSE 0 END AS `wins`,
    CASE WHEN `ActualScore` = 0.5 THEN 1 ELSE 0 END AS `draws`,
    CASE WHEN `ActualScore` = 0 THEN 1 ELSE 0 END AS `losses`,
    `GoalsA` AS `goals_for`,
    `GoalsB` AS `goals_against`,
    CASE WHEN `ActualScore` = 1 THEN 3 WHEN `ActualScore` = 0.5 THEN 1 ELSE 0 END AS `points`
  FROM `ratedresults`
  WHERE `idA` IS NOT NULL

  UNION ALL

  SELECT
    CAST(DATE_FORMAT(`Date`, '%Y-%m-01') AS DATE) AS `month_start`,
    `idB` AS `player_id`,
    CASE WHEN `ActualScore` = 0 THEN 1 ELSE 0 END AS `wins`,
    CASE WHEN `ActualScore` = 0.5 THEN 1 ELSE 0 END AS `draws`,
    CASE WHEN `ActualScore` = 1 THEN 1 ELSE 0 END AS `losses`,
    `GoalsB` AS `goals_for`,
    `GoalsA` AS `goals_against`,
    CASE WHEN `ActualScore` = 0 THEN 3 WHEN `ActualScore` = 0.5 THEN 1 ELSE 0 END AS `points`
  FROM `ratedresults`
  WHERE `idB` IS NOT NULL
) AS `appearances`
GROUP BY `month_start`, `player_id`;
