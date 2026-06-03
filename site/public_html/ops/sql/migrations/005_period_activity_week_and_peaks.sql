-- SCH-006: Add weekly activity rows and per-player peak period cache.
-- Register: docs/coordination/schema-register.md
-- Steve: apply on staging/prod kooldb before the matching rebuild and post-game snippets.

ALTER TABLE `player_period_games`
  MODIFY `period_type` enum('day','week','month','year') NOT NULL;

CREATE TABLE IF NOT EXISTS `player_peak_period_games` (
  `period_type` enum('day','week','month','year') NOT NULL,
  `player_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `games` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`period_type`, `player_id`),
  KEY `idx_player_peak_period_games_leaderboard` (`period_type`, `games`, `period_start`, `player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
