-- Repair only: rebuild counts from player_period_games.
-- SCH-025 reached_at columns: php scripts/rebuild_participation_reached.php after this + migrate 025.

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
