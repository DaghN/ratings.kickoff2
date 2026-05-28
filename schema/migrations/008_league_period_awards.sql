-- SCH-009: League period metadata, per-player awards (denormalized), career totals.
-- Register: docs/coordination/schema-register.md
-- Rules: docs/leagues-rules-spec.md

-- One row per league instance (8 kinds × history of period_start values).
CREATE TABLE IF NOT EXISTS `league_period` (
  `league_kind` enum('points','activity') NOT NULL,
  `period_type` enum('day','week','month','year') NOT NULL,
  `period_start` date NOT NULL,
  `period_end` datetime NOT NULL COMMENT 'Exclusive UTC boundary; canonical achievement instant for this league',
  `rated_games` int(10) unsigned NOT NULL DEFAULT 0,
  `finalized_at` datetime DEFAULT NULL COMMENT 'When batch job wrote awards; audit only',
  PRIMARY KEY (`league_kind`, `period_type`, `period_start`),
  KEY `idx_league_period_finalize` (`period_end`, `finalized_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Player-centric award history: full context on row for profile/history without joining league_period.
CREATE TABLE IF NOT EXISTS `player_league_award` (
  `player_id` int(11) NOT NULL,
  `league_kind` enum('points','activity') NOT NULL,
  `period_type` enum('day','week','month','year') NOT NULL,
  `period_start` date NOT NULL,
  `period_end` datetime NOT NULL COMMENT 'Same as league_period.period_end; when the league closed',
  `finish_rank` tinyint(3) unsigned NOT NULL COMMENT '1=gold/winner, 2=silver, 3=bronze',
  `medal` enum('gold','silver','bronze') NOT NULL,
  `is_winner` tinyint(1) NOT NULL DEFAULT 0,
  `points` smallint(5) unsigned DEFAULT NULL,
  `goal_difference` smallint(6) DEFAULT NULL,
  `goals_for` smallint(5) unsigned DEFAULT NULL,
  `played` smallint(5) unsigned DEFAULT NULL,
  `games` smallint(5) unsigned DEFAULT NULL,
  `first_game_id` int(11) NOT NULL,
  `first_game_side` enum('A','B') NOT NULL,
  PRIMARY KEY (`player_id`, `league_kind`, `period_type`, `period_start`),
  KEY `idx_award_league` (`league_kind`, `period_type`, `period_start`, `finish_rank`),
  KEY `idx_award_player_time` (`player_id`, `period_end`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Fast career aggregates (leaderboards, profile counts, milestone thresholds).
CREATE TABLE IF NOT EXISTS `player_league_totals` (
  `player_id` int(11) NOT NULL,
  `wins` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'finish_rank=1 in any league instance',
  `podiums` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'ranks 1-3',
  `gold` int(10) unsigned NOT NULL DEFAULT 0,
  `silver` int(10) unsigned NOT NULL DEFAULT 0,
  `bronze` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`),
  KEY `idx_league_totals_wins` (`wins`, `player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
