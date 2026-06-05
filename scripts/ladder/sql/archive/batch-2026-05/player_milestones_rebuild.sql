-- Rebuild player_milestones (first-unlock rows per player per milestone_key).
-- Each row links to source game (ratedresults.id) or source league (award period).
-- Destructive: truncates and repopulates. Run after REP-012 (player_league_award).
-- Wrapper: scripts/rebuild_website_derived_data_local.ps1 (milestones step is last).

SET time_zone = '+00:00';

TRUNCATE TABLE `player_milestones`;

-- established_20: 20th rated appearance
INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  player_id, 'established_20', `Date`, 20,
  'game', game_id, NULL, NULL, NULL
FROM (
  SELECT pid AS player_id, `Date`, game_id,
         ROW_NUMBER() OVER (PARTITION BY pid ORDER BY `Date` ASC, game_id ASC) AS game_num
  FROM (
    SELECT id AS game_id, idA AS pid, `Date` FROM ratedresults
    UNION ALL
    SELECT id, idB, `Date` FROM ratedresults
  ) AS appearances
) AS ranked
WHERE game_num = 20;

-- dd_merchant_10: first 10+ goal game
INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  player_id, 'dd_merchant_10', `Date`, 10,
  'game', game_id, NULL, NULL, NULL
FROM (
  SELECT pid AS player_id, `Date`, game_id,
         ROW_NUMBER() OVER (PARTITION BY pid ORDER BY `Date` ASC, game_id ASC) AS rn
  FROM (
    SELECT id AS game_id, idA AS pid, `Date` FROM ratedresults WHERE GoalsA >= 10
    UNION ALL
    SELECT id, idB, `Date` FROM ratedresults WHERE GoalsB >= 10
  ) AS big_scores
) AS ranked
WHERE rn = 1;

-- debut: first rated appearance; persistence: 10th rated appearance
INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  ranked.player_id, 'debut', ranked.`Date`, 1,
  'game', ranked.game_id, NULL, NULL, NULL
FROM (
  SELECT pid AS player_id, `Date`, game_id,
         ROW_NUMBER() OVER (PARTITION BY pid ORDER BY `Date` ASC, game_id ASC) AS game_num
  FROM (
    SELECT id AS game_id, idA AS pid, `Date` FROM ratedresults
    UNION ALL
    SELECT id, idB, `Date` FROM ratedresults
  ) AS appearances
) AS ranked
INNER JOIN `playertable` pt ON pt.`ID` = ranked.`player_id`
WHERE game_num = 1 AND pt.`NumberGames` >= 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  ranked.player_id, 'persistence', ranked.`Date`, 10,
  'game', ranked.game_id, NULL, NULL, NULL
FROM (
  SELECT pid AS player_id, `Date`, game_id,
         ROW_NUMBER() OVER (PARTITION BY pid ORDER BY `Date` ASC, game_id ASC) AS game_num
  FROM (
    SELECT id AS game_id, idA AS pid, `Date` FROM ratedresults
    UNION ALL
    SELECT id, idB, `Date` FROM ratedresults
  ) AS appearances
) AS ranked
INNER JOIN `playertable` pt ON pt.`ID` = ranked.`player_id`
WHERE game_num = 10 AND pt.`NumberGames` >= 10;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  ranked.player_id, 'club_500', ranked.`Date`, 500,
  'game', ranked.game_id, NULL, NULL, NULL
FROM (
  SELECT pid AS player_id, `Date`, game_id,
         ROW_NUMBER() OVER (PARTITION BY pid ORDER BY `Date` ASC, game_id ASC) AS game_num
  FROM (
    SELECT id AS game_id, idA AS pid, `Date` FROM ratedresults
    UNION ALL
    SELECT id, idB, `Date` FROM ratedresults
  ) AS appearances
) AS ranked
INNER JOIN `playertable` pt ON pt.`ID` = ranked.`player_id`
WHERE game_num = 500 AND pt.`NumberGames` >= 500;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  ranked.player_id, 'club_10000', ranked.`Date`, 10000,
  'game', ranked.game_id, NULL, NULL, NULL
FROM (
  SELECT pid AS player_id, `Date`, game_id,
         ROW_NUMBER() OVER (PARTITION BY pid ORDER BY `Date` ASC, game_id ASC) AS game_num
  FROM (
    SELECT id AS game_id, idA AS pid, `Date` FROM ratedresults
    UNION ALL
    SELECT id, idB, `Date` FROM ratedresults
  ) AS appearances
) AS ranked
INNER JOIN `playertable` pt ON pt.`ID` = ranked.`player_id`
WHERE game_num = 10000 AND pt.`NumberGames` >= 10000;

-- Peak rating clubs: first game where running peak (post-game NewRating) crosses threshold
INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  fc.`player_id`, fc.`mk`, fc.`Date`, fc.`thresh`,
  'game', fc.`game_id`, NULL, NULL, NULL
FROM (
  SELECT `player_id`, `mk`, `game_id`, `Date`, `thresh`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`, `mk`
           ORDER BY `Date` ASC, `game_id` ASC
         ) AS rn
  FROM (
    SELECT p.`player_id`, p.`game_id`, p.`Date`, t.`mk`, t.`thresh`
    FROM (
      SELECT `player_id`, `game_id`, `Date`,
             MAX(`new_rating`) OVER (
               PARTITION BY `player_id`
               ORDER BY `Date` ASC, `game_id` ASC
               ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
             ) AS `peak_so_far`
      FROM (
        SELECT idA AS `player_id`, id AS `game_id`, `Date`,
               CAST(NewRatingA AS DECIMAL(10, 3)) AS `new_rating`
        FROM ratedresults
        WHERE NewRatingA IS NOT NULL AND NewRatingA > 0
        UNION ALL
        SELECT idB, id, `Date`, CAST(NewRatingB AS DECIMAL(10, 3))
        FROM ratedresults
        WHERE NewRatingB IS NOT NULL AND NewRatingB > 0
      ) AS raw
    ) AS p
    CROSS JOIN (
      SELECT 'club_1700' AS `mk`, 1700 AS `thresh`
      UNION ALL SELECT 'club_1800', 1800
      UNION ALL SELECT 'club_2000', 2000
      UNION ALL SELECT 'club_2300', 2300
    ) AS t
    WHERE p.`peak_so_far` >= t.`thresh`
  ) AS crossed
) AS fc
INNER JOIN `playertable` pt ON pt.`ID` = fc.`player_id` AND pt.`PeakRating` >= fc.`thresh`
WHERE fc.rn = 1;

-- Exists feats: player_milestones_rebuild_exists.sql (spliced before league in rebuild_website_derived_data_local.ps1)

-- ---------------------------------------------------------------------------
-- League wave: first matching award row (rn=1 by period_end)
-- ---------------------------------------------------------------------------

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'moment_of_glory', `period_end`, 1,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'points' AND `period_type` = 'day' AND `is_winner` = 1
) AS first_award
WHERE rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'activity_king', `period_end`, 1,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'activity' AND `period_type` = 'month' AND `is_winner` = 1
) AS first_award
WHERE rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_daily_points_medal', `period_end`, 3,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'points' AND `period_type` = 'day' AND `finish_rank` <= 3
) AS first_award
WHERE rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_daily_activity_medal', `period_end`, 3,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'activity' AND `period_type` = 'day' AND `finish_rank` <= 3
) AS first_award
WHERE rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_daily_activity_winner', `period_end`, 1,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'activity' AND `period_type` = 'day' AND `is_winner` = 1
) AS first_award
WHERE rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_weekly_points_medal', `period_end`, 3,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'points' AND `period_type` = 'week' AND `finish_rank` <= 3
) AS first_award
WHERE rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_weekly_points_winner', `period_end`, 1,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'points' AND `period_type` = 'week' AND `is_winner` = 1
) AS first_award
WHERE rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_weekly_activity_medal', `period_end`, 3,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'activity' AND `period_type` = 'week' AND `finish_rank` <= 3
) AS first_award
WHERE rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_weekly_activity_winner', `period_end`, 1,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'activity' AND `period_type` = 'week' AND `is_winner` = 1
) AS first_award
WHERE rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_monthly_points_medal', `period_end`, 3,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'points' AND `period_type` = 'month' AND `finish_rank` <= 3
) AS first_award
WHERE rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_monthly_points_winner', `period_end`, 1,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'points' AND `period_type` = 'month' AND `is_winner` = 1
) AS first_award
WHERE rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_monthly_activity_medal', `period_end`, 3,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'activity' AND `period_type` = 'month' AND `finish_rank` <= 3
) AS first_award
WHERE rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_yearly_points_medal', `period_end`, 3,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'points' AND `period_type` = 'year' AND `finish_rank` <= 3
) AS first_award
WHERE rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_yearly_points_winner', `period_end`, 1,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'points' AND `period_type` = 'year' AND `is_winner` = 1
) AS first_award
WHERE rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_yearly_activity_medal', `period_end`, 3,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'activity' AND `period_type` = 'year' AND `finish_rank` <= 3
) AS first_award
WHERE rn = 1;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_yearly_activity_winner', `period_end`, 1,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS rn
  FROM `player_league_award`
  WHERE `league_kind` = 'activity' AND `period_type` = 'year' AND `is_winner` = 1
) AS first_award
WHERE rn = 1;

-- Career league wins: Nth winning league instance
INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_wins_10', `period_end`, 10,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS win_num
  FROM `player_league_award`
  WHERE `is_winner` = 1
) AS ranked
WHERE win_num = 10;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_wins_50', `period_end`, 50,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS win_num
  FROM `player_league_award`
  WHERE `is_winner` = 1
) AS ranked
WHERE win_num = 50;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_wins_100', `period_end`, 100,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS win_num
  FROM `player_league_award`
  WHERE `is_winner` = 1
) AS ranked
WHERE win_num = 100;

INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `player_id`, 'league_wins_500', `period_end`, 500,
  'league', NULL, `league_kind`, `period_type`, `period_start`
FROM (
  SELECT `player_id`, `period_end`, `league_kind`, `period_type`, `period_start`,
         ROW_NUMBER() OVER (
           PARTITION BY `player_id`
           ORDER BY `period_end` ASC, `league_kind` ASC, `period_type` ASC, `period_start` ASC
         ) AS win_num
  FROM `player_league_award`
  WHERE `is_winner` = 1
) AS ranked
WHERE win_num = 500;

-- entered_arena: account registration = entering the lobby (JoinDate; not replay-derived)
INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `ID`, 'entered_arena', `JoinDate`, 1,
  'lobby', NULL, NULL, NULL, NULL
FROM `playertable`
WHERE `NumberGames` >= 1;
