-- Period burst milestones: first UTC day/month when period games cross threshold.
-- achieved_at + source_game_id = the rated game where that period count hits N (5/10/20/30/50).
-- MariaDB-safe: window functions on ratedresults sides (no LATERAL).

SET time_zone = '+00:00';

-- hot_day (5), marathon_day (10), absurd_day (20), ultra_day_30 (30), grind_month (50)

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fp.`player_id`, 'hot_day', cross.`Date`, 5,
  'game', cross.`game_id`, NULL, NULL, NULL
FROM (
  SELECT `player_id`, `period_start`,
         ROW_NUMBER() OVER (PARTITION BY `player_id` ORDER BY `period_start` ASC) AS rn
  FROM `player_period_games`
  WHERE `period_type` = 'day' AND `games` >= 5
) AS fp
INNER JOIN (
  SELECT `pid`, `period_day`, `game_id`, `Date` FROM (
    SELECT `idA` AS `pid`, DATE(`Date`) AS `period_day`, `id` AS `game_id`, `Date`,
           ROW_NUMBER() OVER (PARTITION BY `idA`, DATE(`Date`) ORDER BY `Date` ASC, `id` ASC) AS `rn`
    FROM `ratedresults` WHERE `NewRatingA` IS NOT NULL
    UNION ALL
    SELECT `idB`, DATE(`Date`), `id`, `Date`,
           ROW_NUMBER() OVER (PARTITION BY `idB`, DATE(`Date`) ORDER BY `Date` ASC, `id` ASC) AS `rn`
    FROM `ratedresults` WHERE `NewRatingA` IS NOT NULL
  ) AS `s` WHERE `rn` = 5
) AS `cross` ON `cross`.`pid` = fp.`player_id` AND `cross`.`period_day` = fp.`period_start`
INNER JOIN `playertable` pt ON pt.`ID` = fp.`player_id` AND pt.`NumberGames` >= 1
WHERE fp.`rn` = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fp.`player_id`, 'marathon_day', cross.`Date`, 10,
  'game', cross.`game_id`, NULL, NULL, NULL
FROM (
  SELECT `player_id`, `period_start`,
         ROW_NUMBER() OVER (PARTITION BY `player_id` ORDER BY `period_start` ASC) AS rn
  FROM `player_period_games`
  WHERE `period_type` = 'day' AND `games` >= 10
) AS fp
INNER JOIN (
  SELECT `pid`, `period_day`, `game_id`, `Date` FROM (
    SELECT `idA` AS `pid`, DATE(`Date`) AS `period_day`, `id` AS `game_id`, `Date`,
           ROW_NUMBER() OVER (PARTITION BY `idA`, DATE(`Date`) ORDER BY `Date` ASC, `id` ASC) AS `rn`
    FROM `ratedresults` WHERE `NewRatingA` IS NOT NULL
    UNION ALL
    SELECT `idB`, DATE(`Date`), `id`, `Date`,
           ROW_NUMBER() OVER (PARTITION BY `idB`, DATE(`Date`) ORDER BY `Date` ASC, `id` ASC) AS `rn`
    FROM `ratedresults` WHERE `NewRatingA` IS NOT NULL
  ) AS `s` WHERE `rn` = 10
) AS `cross` ON `cross`.`pid` = fp.`player_id` AND `cross`.`period_day` = fp.`period_start`
INNER JOIN `playertable` pt ON pt.`ID` = fp.`player_id` AND pt.`NumberGames` >= 1
WHERE fp.`rn` = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fp.`player_id`, 'absurd_day', cross.`Date`, 20,
  'game', cross.`game_id`, NULL, NULL, NULL
FROM (
  SELECT `player_id`, `period_start`,
         ROW_NUMBER() OVER (PARTITION BY `player_id` ORDER BY `period_start` ASC) AS rn
  FROM `player_period_games`
  WHERE `period_type` = 'day' AND `games` >= 20
) AS fp
INNER JOIN (
  SELECT `pid`, `period_day`, `game_id`, `Date` FROM (
    SELECT `idA` AS `pid`, DATE(`Date`) AS `period_day`, `id` AS `game_id`, `Date`,
           ROW_NUMBER() OVER (PARTITION BY `idA`, DATE(`Date`) ORDER BY `Date` ASC, `id` ASC) AS `rn`
    FROM `ratedresults` WHERE `NewRatingA` IS NOT NULL
    UNION ALL
    SELECT `idB`, DATE(`Date`), `id`, `Date`,
           ROW_NUMBER() OVER (PARTITION BY `idB`, DATE(`Date`) ORDER BY `Date` ASC, `id` ASC) AS `rn`
    FROM `ratedresults` WHERE `NewRatingA` IS NOT NULL
  ) AS `s` WHERE `rn` = 20
) AS `cross` ON `cross`.`pid` = fp.`player_id` AND `cross`.`period_day` = fp.`period_start`
INNER JOIN `playertable` pt ON pt.`ID` = fp.`player_id` AND pt.`NumberGames` >= 1
WHERE fp.`rn` = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fp.`player_id`, 'ultra_day_30', cross.`Date`, 30,
  'game', cross.`game_id`, NULL, NULL, NULL
FROM (
  SELECT `player_id`, `period_start`,
         ROW_NUMBER() OVER (PARTITION BY `player_id` ORDER BY `period_start` ASC) AS rn
  FROM `player_period_games`
  WHERE `period_type` = 'day' AND `games` >= 30
) AS fp
INNER JOIN (
  SELECT `pid`, `period_day`, `game_id`, `Date` FROM (
    SELECT `idA` AS `pid`, DATE(`Date`) AS `period_day`, `id` AS `game_id`, `Date`,
           ROW_NUMBER() OVER (PARTITION BY `idA`, DATE(`Date`) ORDER BY `Date` ASC, `id` ASC) AS `rn`
    FROM `ratedresults` WHERE `NewRatingA` IS NOT NULL
    UNION ALL
    SELECT `idB`, DATE(`Date`), `id`, `Date`,
           ROW_NUMBER() OVER (PARTITION BY `idB`, DATE(`Date`) ORDER BY `Date` ASC, `id` ASC) AS `rn`
    FROM `ratedresults` WHERE `NewRatingA` IS NOT NULL
  ) AS `s` WHERE `rn` = 30
) AS `cross` ON `cross`.`pid` = fp.`player_id` AND `cross`.`period_day` = fp.`period_start`
INNER JOIN `playertable` pt ON pt.`ID` = fp.`player_id` AND pt.`NumberGames` >= 1
WHERE fp.`rn` = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fp.`player_id`, 'grind_month', cross.`Date`, 50,
  'game', cross.`game_id`, NULL, NULL, NULL
FROM (
  SELECT `player_id`, `period_start`,
         ROW_NUMBER() OVER (PARTITION BY `player_id` ORDER BY `period_start` ASC) AS rn
  FROM `player_period_games`
  WHERE `period_type` = 'month' AND `games` >= 50
) AS fp
INNER JOIN (
  SELECT `pid`, `period_month`, `game_id`, `Date` FROM (
    SELECT `idA` AS `pid`, DATE_FORMAT(`Date`, '%Y-%m-01') AS `period_month`, `id` AS `game_id`, `Date`,
           ROW_NUMBER() OVER (
             PARTITION BY `idA`, DATE_FORMAT(`Date`, '%Y-%m-01')
             ORDER BY `Date` ASC, `id` ASC
           ) AS `rn`
    FROM `ratedresults` WHERE `NewRatingA` IS NOT NULL
    UNION ALL
    SELECT `idB`, DATE_FORMAT(`Date`, '%Y-%m-01'), `id`, `Date`,
           ROW_NUMBER() OVER (
             PARTITION BY `idB`, DATE_FORMAT(`Date`, '%Y-%m-01')
             ORDER BY `Date` ASC, `id` ASC
           ) AS `rn`
    FROM `ratedresults` WHERE `NewRatingA` IS NOT NULL
  ) AS `s` WHERE `rn` = 50
) AS `cross` ON `cross`.`pid` = fp.`player_id` AND `cross`.`period_month` = fp.`period_start`
INNER JOIN `playertable` pt ON pt.`ID` = fp.`player_id` AND pt.`NumberGames` >= 1
WHERE fp.`rn` = 1;
