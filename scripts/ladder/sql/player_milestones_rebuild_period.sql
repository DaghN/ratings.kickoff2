-- Period burst milestones: first UTC day/month when period games cross threshold.
-- achieved_at + source_game_id = last rated game that calendar day (when available).

SET time_zone = '+00:00';

-- helper pattern: first period_start per player meeting games >= N
-- hot_day (5), marathon_day (10), absurd_day (20), ultra_day_30 (30)
INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fp.`player_id`, 'hot_day', g.`Date`, 5,
  'game', g.`id`, NULL, NULL, NULL
FROM (
  SELECT `player_id`, `period_start`,
         ROW_NUMBER() OVER (PARTITION BY `player_id` ORDER BY `period_start` ASC) AS rn
  FROM `player_period_games`
  WHERE `period_type` = 'day' AND `games` >= 5
) AS fp
INNER JOIN `playertable` pt ON pt.`ID` = fp.`player_id` AND pt.`NumberGames` >= 1
INNER JOIN LATERAL (
  SELECT r.`id`, r.`Date`
  FROM `ratedresults` r
  WHERE DATE(r.`Date`) = fp.`period_start`
    AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
  ORDER BY r.`Date` DESC, r.`id` DESC
  LIMIT 1
) AS g ON TRUE
WHERE fp.rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fp.`player_id`, 'marathon_day', g.`Date`, 10,
  'game', g.`id`, NULL, NULL, NULL
FROM (
  SELECT `player_id`, `period_start`,
         ROW_NUMBER() OVER (PARTITION BY `player_id` ORDER BY `period_start` ASC) AS rn
  FROM `player_period_games`
  WHERE `period_type` = 'day' AND `games` >= 10
) AS fp
INNER JOIN `playertable` pt ON pt.`ID` = fp.`player_id` AND pt.`NumberGames` >= 1
INNER JOIN LATERAL (
  SELECT r.`id`, r.`Date`
  FROM `ratedresults` r
  WHERE DATE(r.`Date`) = fp.`period_start`
    AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
  ORDER BY r.`Date` DESC, r.`id` DESC
  LIMIT 1
) AS g ON TRUE
WHERE fp.rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fp.`player_id`, 'absurd_day', g.`Date`, 20,
  'game', g.`id`, NULL, NULL, NULL
FROM (
  SELECT `player_id`, `period_start`,
         ROW_NUMBER() OVER (PARTITION BY `player_id` ORDER BY `period_start` ASC) AS rn
  FROM `player_period_games`
  WHERE `period_type` = 'day' AND `games` >= 20
) AS fp
INNER JOIN `playertable` pt ON pt.`ID` = fp.`player_id` AND pt.`NumberGames` >= 1
INNER JOIN LATERAL (
  SELECT r.`id`, r.`Date`
  FROM `ratedresults` r
  WHERE DATE(r.`Date`) = fp.`period_start`
    AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
  ORDER BY r.`Date` DESC, r.`id` DESC
  LIMIT 1
) AS g ON TRUE
WHERE fp.rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fp.`player_id`, 'ultra_day_30', g.`Date`, 30,
  'game', g.`id`, NULL, NULL, NULL
FROM (
  SELECT `player_id`, `period_start`,
         ROW_NUMBER() OVER (PARTITION BY `player_id` ORDER BY `period_start` ASC) AS rn
  FROM `player_period_games`
  WHERE `period_type` = 'day' AND `games` >= 30
) AS fp
INNER JOIN `playertable` pt ON pt.`ID` = fp.`player_id` AND pt.`NumberGames` >= 1
INNER JOIN LATERAL (
  SELECT r.`id`, r.`Date`
  FROM `ratedresults` r
  WHERE DATE(r.`Date`) = fp.`period_start`
    AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
  ORDER BY r.`Date` DESC, r.`id` DESC
  LIMIT 1
) AS g ON TRUE
WHERE fp.rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fp.`player_id`, 'grind_month', g.`Date`, 50,
  'game', g.`id`, NULL, NULL, NULL
FROM (
  SELECT `player_id`, `period_start`,
         ROW_NUMBER() OVER (PARTITION BY `player_id` ORDER BY `period_start` ASC) AS rn
  FROM `player_period_games`
  WHERE `period_type` = 'month' AND `games` >= 50
) AS fp
INNER JOIN `playertable` pt ON pt.`ID` = fp.`player_id` AND pt.`NumberGames` >= 1
INNER JOIN LATERAL (
  SELECT r.`id`, r.`Date`
  FROM `ratedresults` r
  WHERE DATE_FORMAT(r.`Date`, '%Y-%m-01') = fp.`period_start`
    AND (r.`idA` = fp.`player_id` OR r.`idB` = fp.`player_id`)
  ORDER BY r.`Date` DESC, r.`id` DESC
  LIMIT 1
) AS g ON TRUE
WHERE fp.rn = 1;
