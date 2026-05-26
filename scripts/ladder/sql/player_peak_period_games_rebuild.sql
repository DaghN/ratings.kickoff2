-- Rebuild player_peak_period_games from player_period_games.
-- Destructive: truncates and repopulates the peak aggregate. Run after player_period_games is rebuilt.

TRUNCATE TABLE `player_peak_period_games`;

INSERT INTO `player_peak_period_games` (`period_type`, `player_id`, `period_start`, `games`)
SELECT `period_type`, `player_id`, `period_start`, `games`
FROM (
  SELECT
    `period_type`,
    `player_id`,
    `period_start`,
    `games`,
    ROW_NUMBER() OVER (
      PARTITION BY `period_type`, `player_id`
      ORDER BY `games` DESC, `period_start` ASC
    ) AS `rn`
  FROM `player_period_games`
  WHERE `period_type` IN ('day', 'week', 'month', 'year')
) AS ranked
WHERE `rn` = 1;
