-- SCH-004: Precomputed player activity counts for day/month/year leaderboards.
-- Register: docs/coordination/schema-register.md
-- Steve: apply on staging/prod kooldb before enabling post-game writes.

CREATE TABLE IF NOT EXISTS `player_period_games` (
  `period_type` enum('day','month','year') NOT NULL,
  `period_start` date NOT NULL,
  `player_id` int(11) NOT NULL,
  `games` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`period_type`, `period_start`, `player_id`),
  KEY `idx_player_period_games_player` (`player_id`, `period_type`, `period_start`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
