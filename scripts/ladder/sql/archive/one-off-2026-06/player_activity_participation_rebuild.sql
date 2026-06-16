-- Repair only: rebuild player_activity_participation from player_period_games.
-- Not the smoke-gate definition of correct — orthogonal parity uses this shape as oracle.
-- Destructive: truncates and repopulates. Run only on repair targets (e.g. ko2unity_db).

SET time_zone = '+00:00';

TRUNCATE TABLE `player_activity_participation`;

INSERT INTO `player_activity_participation`
  (`player_id`, `active_days`, `active_weeks`, `active_months`, `active_years`, `first_rated_day`, `last_rated_day`)
SELECT
  `player_id`,
  SUM(`period_type` = 'day'),
  SUM(`period_type` = 'week'),
  SUM(`period_type` = 'month'),
  SUM(`period_type` = 'year'),
  MIN(CASE WHEN `period_type` = 'day' THEN `period_start` END),
  MAX(CASE WHEN `period_type` = 'day' THEN `period_start` END)
FROM `player_period_games`
GROUP BY `player_id`;
