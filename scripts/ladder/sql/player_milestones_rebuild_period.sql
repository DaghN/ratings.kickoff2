-- Period burst milestones: first UTC day/month when period games cross threshold.
-- achieved_at + source_game_id = last rated game that calendar day (when available).
-- MariaDB-safe: correlated subqueries (no LATERAL — unsupported on staging MariaDB).

SET time_zone = '+00:00';

-- hot_day (5), marathon_day (10), absurd_day (20), ultra_day_30 (30), grind_month (50)

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fp.`player_id`, 'hot_day',
  (SELECT r.`Date` FROM `ratedresults` r
   WHERE DATE(r.`Date`) = fp.`period_start`
     AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
   ORDER BY r.`Date` DESC, r.`id` DESC LIMIT 1),
  5,
  'game',
  (SELECT r.`id` FROM `ratedresults` r
   WHERE DATE(r.`Date`) = fp.`period_start`
     AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
   ORDER BY r.`Date` DESC, r.`id` DESC LIMIT 1),
  NULL, NULL, NULL
FROM (
  SELECT `player_id`, `period_start`,
         ROW_NUMBER() OVER (PARTITION BY `player_id` ORDER BY `period_start` ASC) AS rn
  FROM `player_period_games`
  WHERE `period_type` = 'day' AND `games` >= 5
) AS fp
INNER JOIN `playertable` pt ON pt.`ID` = fp.`player_id` AND pt.`NumberGames` >= 1
WHERE fp.rn = 1
  AND EXISTS (
    SELECT 1 FROM `ratedresults` r
    WHERE DATE(r.`Date`) = fp.`period_start`
      AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
  );

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fp.`player_id`, 'marathon_day',
  (SELECT r.`Date` FROM `ratedresults` r
   WHERE DATE(r.`Date`) = fp.`period_start`
     AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
   ORDER BY r.`Date` DESC, r.`id` DESC LIMIT 1),
  10,
  'game',
  (SELECT r.`id` FROM `ratedresults` r
   WHERE DATE(r.`Date`) = fp.`period_start`
     AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
   ORDER BY r.`Date` DESC, r.`id` DESC LIMIT 1),
  NULL, NULL, NULL
FROM (
  SELECT `player_id`, `period_start`,
         ROW_NUMBER() OVER (PARTITION BY `player_id` ORDER BY `period_start` ASC) AS rn
  FROM `player_period_games`
  WHERE `period_type` = 'day' AND `games` >= 10
) AS fp
INNER JOIN `playertable` pt ON pt.`ID` = fp.`player_id` AND pt.`NumberGames` >= 1
WHERE fp.rn = 1
  AND EXISTS (
    SELECT 1 FROM `ratedresults` r
    WHERE DATE(r.`Date`) = fp.`period_start`
      AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
  );

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fp.`player_id`, 'absurd_day',
  (SELECT r.`Date` FROM `ratedresults` r
   WHERE DATE(r.`Date`) = fp.`period_start`
     AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
   ORDER BY r.`Date` DESC, r.`id` DESC LIMIT 1),
  20,
  'game',
  (SELECT r.`id` FROM `ratedresults` r
   WHERE DATE(r.`Date`) = fp.`period_start`
     AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
   ORDER BY r.`Date` DESC, r.`id` DESC LIMIT 1),
  NULL, NULL, NULL
FROM (
  SELECT `player_id`, `period_start`,
         ROW_NUMBER() OVER (PARTITION BY `player_id` ORDER BY `period_start` ASC) AS rn
  FROM `player_period_games`
  WHERE `period_type` = 'day' AND `games` >= 20
) AS fp
INNER JOIN `playertable` pt ON pt.`ID` = fp.`player_id` AND pt.`NumberGames` >= 1
WHERE fp.rn = 1
  AND EXISTS (
    SELECT 1 FROM `ratedresults` r
    WHERE DATE(r.`Date`) = fp.`period_start`
      AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
  );

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fp.`player_id`, 'ultra_day_30',
  (SELECT r.`Date` FROM `ratedresults` r
   WHERE DATE(r.`Date`) = fp.`period_start`
     AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
   ORDER BY r.`Date` DESC, r.`id` DESC LIMIT 1),
  30,
  'game',
  (SELECT r.`id` FROM `ratedresults` r
   WHERE DATE(r.`Date`) = fp.`period_start`
     AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
   ORDER BY r.`Date` DESC, r.`id` DESC LIMIT 1),
  NULL, NULL, NULL
FROM (
  SELECT `player_id`, `period_start`,
         ROW_NUMBER() OVER (PARTITION BY `player_id` ORDER BY `period_start` ASC) AS rn
  FROM `player_period_games`
  WHERE `period_type` = 'day' AND `games` >= 30
) AS fp
INNER JOIN `playertable` pt ON pt.`ID` = fp.`player_id` AND pt.`NumberGames` >= 1
WHERE fp.rn = 1
  AND EXISTS (
    SELECT 1 FROM `ratedresults` r
    WHERE DATE(r.`Date`) = fp.`period_start`
      AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
  );

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fp.`player_id`, 'grind_month',
  (SELECT r.`Date` FROM `ratedresults` r
   WHERE DATE_FORMAT(r.`Date`, '%Y-%m-01') = fp.`period_start`
     AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
   ORDER BY r.`Date` DESC, r.`id` DESC LIMIT 1),
  50,
  'game',
  (SELECT r.`id` FROM `ratedresults` r
   WHERE DATE_FORMAT(r.`Date`, '%Y-%m-01') = fp.`period_start`
     AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
   ORDER BY r.`Date` DESC, r.`id` DESC LIMIT 1),
  NULL, NULL, NULL
FROM (
  SELECT `player_id`, `period_start`,
         ROW_NUMBER() OVER (PARTITION BY `player_id` ORDER BY `period_start` ASC) AS rn
  FROM `player_period_games`
  WHERE `period_type` = 'month' AND `games` >= 50
) AS fp
INNER JOIN `playertable` pt ON pt.`ID` = fp.`player_id` AND pt.`NumberGames` >= 1
WHERE fp.rn = 1
  AND EXISTS (
    SELECT 1 FROM `ratedresults` r
    WHERE DATE_FORMAT(r.`Date`, '%Y-%m-01') = fp.`period_start`
      AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
  );
