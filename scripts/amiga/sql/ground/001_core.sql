-- L1 witness ground: tournaments, players, canonical games (ko2amiga_db).
-- Bundle: sql/ground/ — see scripts/amiga/schema_bundles.py
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

CREATE TABLE IF NOT EXISTS `amiga_players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `country` varchar(50) NOT NULL DEFAULT '',
  `display` tinyint(1) NOT NULL DEFAULT 1,
  `player_source` enum('import','live_ops') NOT NULL DEFAULT 'import',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amiga_players_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
