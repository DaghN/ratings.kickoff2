-- Amiga realm core schema (ko2amiga_db). Ground truth + derived truth (A2 split).
-- Fresh install: use sql/ground/, sql/structure/, sql/derived/ via schema_bundles.py (slice 1).
-- This flat file remains for archaeology; amiga_game_ratings also in sql/derived/001_game_ratings.sql.
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `tournaments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_id` int(11) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `chrono` double DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `is_cup` tinyint(1) NOT NULL DEFAULT 0,
  `country` varchar(50) DEFAULT NULL,
  `equal_teams` tinyint(1) NOT NULL DEFAULT 0,
  `player_count` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tournaments_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ground truth: player identity
CREATE TABLE IF NOT EXISTS `amiga_players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `country` varchar(50) NOT NULL DEFAULT '',
  `display` tinyint(1) NOT NULL DEFAULT 1,
  `player_source` enum('import','live_ops') NOT NULL DEFAULT 'import',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amiga_players_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ground truth: canonical match results
CREATE TABLE IF NOT EXISTS `amiga_games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_scores_id` int(11) NOT NULL,
  `game_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `player_a_id` int(11) NOT NULL,
  `player_b_id` int(11) NOT NULL,
  `tournament_id` int(11) DEFAULT NULL,
  `phase` varchar(50) DEFAULT NULL,
  `goals_a` int(11) NOT NULL,
  `goals_b` int(11) NOT NULL,
  `extra` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amiga_games_source_scores_id` (`source_scores_id`),
  KEY `idx_amiga_games_date` (`game_date`),
  KEY `idx_amiga_games_player_a` (`player_a_id`),
  KEY `idx_amiga_games_player_b` (`player_b_id`),
  KEY `idx_amiga_games_tournament` (`tournament_id`),
  CONSTRAINT `fk_amiga_games_player_a` FOREIGN KEY (`player_a_id`) REFERENCES `amiga_players` (`id`),
  CONSTRAINT `fk_amiga_games_player_b` FOREIGN KEY (`player_b_id`) REFERENCES `amiga_players` (`id`),
  CONSTRAINT `fk_amiga_games_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Derived: per-game Elo and outcome flags (1:1 with amiga_games)
CREATE TABLE IF NOT EXISTS `amiga_game_ratings` (
  `game_id` int(11) NOT NULL,
  `rating_a` decimal(10,6) DEFAULT NULL,
  `rating_b` decimal(10,6) DEFAULT NULL,
  `rating_difference` decimal(10,6) DEFAULT NULL,
  `expected_score_a` decimal(10,6) DEFAULT NULL,
  `expected_score_b` decimal(10,6) DEFAULT NULL,
  `actual_score` decimal(10,6) DEFAULT NULL,
  `adjustment_a` decimal(10,6) DEFAULT NULL,
  `adjustment_b` decimal(10,6) DEFAULT NULL,
  `new_rating_a` decimal(10,6) DEFAULT NULL,
  `new_rating_b` decimal(10,6) DEFAULT NULL,
  `sum_of_goals` int(11) DEFAULT NULL,
  `goal_difference` int(11) DEFAULT NULL,
  `winner_id` int(11) DEFAULT NULL,
  `home_win` tinyint(4) DEFAULT NULL,
  `draw` tinyint(4) DEFAULT NULL,
  `away_win` tinyint(4) DEFAULT NULL,
  `dd_player_a` tinyint(4) DEFAULT NULL,
  `dd_player_b` tinyint(4) DEFAULT NULL,
  `cs_player_a` tinyint(4) DEFAULT NULL,
  `cs_player_b` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`game_id`),
  CONSTRAINT `fk_amiga_game_ratings_game` FOREIGN KEY (`game_id`) REFERENCES `amiga_games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- amiga_player_stats removed slice 8 — career truth in amiga_player_event_snapshots + amiga_player_current.
