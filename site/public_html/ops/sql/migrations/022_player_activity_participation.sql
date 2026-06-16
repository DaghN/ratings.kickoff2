-- SCH-022: Per-player participation totals + longevity endpoints (Activity wing P4b).
-- Register: docs/coordination/schema-register.md
-- Contract: docs/activity-wing-stored-truth-policy.md

CREATE TABLE IF NOT EXISTS `player_activity_participation` (
  `player_id` int(11) NOT NULL,
  `active_days` int(11) NOT NULL DEFAULT 0,
  `active_weeks` int(11) NOT NULL DEFAULT 0,
  `active_months` int(11) NOT NULL DEFAULT 0,
  `active_years` int(11) NOT NULL DEFAULT 0,
  `first_rated_day` date DEFAULT NULL COMMENT 'UTC date of first rated game',
  `last_rated_day` date DEFAULT NULL COMMENT 'UTC date of most recent rated game (by game date)',
  PRIMARY KEY (`player_id`),
  KEY `idx_activity_participation_days` (`active_days`, `player_id`),
  KEY `idx_activity_participation_weeks` (`active_weeks`, `player_id`),
  KEY `idx_activity_participation_months` (`active_months`, `player_id`),
  KEY `idx_activity_participation_years` (`active_years`, `player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
