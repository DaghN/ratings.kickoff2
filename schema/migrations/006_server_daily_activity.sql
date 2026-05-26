-- SCH-007: Precomputed daily server activity for fast Activity charts.
-- Register: docs/coordination/schema-register.md
-- Steve: apply on staging/prod kooldb before enabling post-game writes.

CREATE TABLE IF NOT EXISTS `server_daily_activity` (
  `activity_day` date NOT NULL,
  `rated_games` int(11) NOT NULL DEFAULT 0,
  `active_players` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`activity_day`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
