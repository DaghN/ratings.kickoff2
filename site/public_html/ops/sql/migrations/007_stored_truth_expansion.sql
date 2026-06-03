-- SCH-008: Stored truth expansion — period leagues, milestones, matchups, server totals.
-- Register: docs/coordination/schema-register.md
-- Steve: apply on staging/prod kooldb before enabling post-game writes.

-- 1. Period league standings per player per period
CREATE TABLE IF NOT EXISTS `player_period_league` (
  `period_type` enum('day','week','month','year') NOT NULL,
  `period_start` date NOT NULL,
  `player_id` int(11) NOT NULL,
  `played` smallint(5) unsigned NOT NULL DEFAULT 0,
  `wins` smallint(5) unsigned NOT NULL DEFAULT 0,
  `draws` smallint(5) unsigned NOT NULL DEFAULT 0,
  `losses` smallint(5) unsigned NOT NULL DEFAULT 0,
  `goals_for` smallint(5) unsigned NOT NULL DEFAULT 0,
  `goals_against` smallint(5) unsigned NOT NULL DEFAULT 0,
  `goal_difference` smallint(6) NOT NULL DEFAULT 0,
  `points` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`period_type`, `period_start`, `player_id`),
  KEY `idx_period_points` (`period_type`, `period_start`, `points`, `goal_difference`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Player milestones
CREATE TABLE IF NOT EXISTS `player_milestones` (
  `player_id` int(11) NOT NULL,
  `milestone_key` varchar(50) NOT NULL,
  `achieved_at` datetime NOT NULL,
  `value` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`, `milestone_key`),
  KEY `idx_milestone_key` (`milestone_key`, `achieved_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Directed player matchup summaries
CREATE TABLE IF NOT EXISTS `player_matchup_summary` (
  `player_id` int(11) NOT NULL,
  `opponent_id` int(11) NOT NULL,
  `games` smallint(5) unsigned NOT NULL DEFAULT 0,
  `wins` smallint(5) unsigned NOT NULL DEFAULT 0,
  `draws` smallint(5) unsigned NOT NULL DEFAULT 0,
  `losses` smallint(5) unsigned NOT NULL DEFAULT 0,
  `goals_for` smallint(5) unsigned NOT NULL DEFAULT 0,
  `goals_against` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`, `opponent_id`),
  KEY `idx_opponent` (`opponent_id`, `player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Server period game totals
CREATE TABLE IF NOT EXISTS `server_period_game_totals` (
  `period_type` enum('day','week','month','year') NOT NULL,
  `period_start` date NOT NULL,
  `rated_games` int(10) unsigned NOT NULL DEFAULT 0,
  `total_goals` int(10) unsigned NOT NULL DEFAULT 0,
  `draws` int(10) unsigned NOT NULL DEFAULT 0,
  `double_digit_games` int(10) unsigned NOT NULL DEFAULT 0,
  `clean_sheets` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`period_type`, `period_start`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Server period matchup breadth (canonical pair per period)
CREATE TABLE IF NOT EXISTS `server_period_matchups` (
  `period_type` enum('day','week','month','year') NOT NULL,
  `period_start` date NOT NULL,
  `player_a` int(11) NOT NULL,
  `player_b` int(11) NOT NULL,
  `games` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`period_type`, `period_start`, `player_a`, `player_b`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
