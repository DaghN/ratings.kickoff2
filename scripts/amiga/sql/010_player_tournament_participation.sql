-- Player tournament participation (derived; one row per player x event with >=1 game).
-- Apply after 009: mysql ko2amiga_db < scripts/amiga/sql/010_player_tournament_participation.sql
-- Populate: slice 1+ rebuild (replay wires in slice 2).

SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `amiga_player_tournament_participation` (
  `player_id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `event_date` date DEFAULT NULL,
  `event_chrono` double DEFAULT NULL,
  `tournament_name` varchar(50) NOT NULL,
  `is_cup` tinyint(1) NOT NULL DEFAULT 0,
  `country` varchar(50) DEFAULT NULL,
  `has_league` tinyint(1) NOT NULL DEFAULT 0,
  `has_cup` tinyint(1) NOT NULL DEFAULT 0,
  `overall_position` smallint(6) DEFAULT NULL,
  `event_points` smallint(6) NOT NULL DEFAULT 0,
  `games` smallint(6) NOT NULL DEFAULT 0,
  `wins` smallint(6) NOT NULL DEFAULT 0,
  `draws` smallint(6) NOT NULL DEFAULT 0,
  `losses` smallint(6) NOT NULL DEFAULT 0,
  `goals_for` smallint(6) NOT NULL DEFAULT 0,
  `goals_against` smallint(6) NOT NULL DEFAULT 0,
  `rating_before` decimal(10,6) DEFAULT NULL,
  `rating_delta` decimal(10,6) DEFAULT NULL,
  `rating_after` decimal(10,6) DEFAULT NULL,
  `games_in_event` smallint(6) NOT NULL DEFAULT 0,
  `finalized_at` datetime DEFAULT NULL,
  `is_winner` tinyint(1) NOT NULL DEFAULT 0,
  `wc_medal` enum('none','gold','silver','bronze') NOT NULL DEFAULT 'none',
  PRIMARY KEY (`player_id`, `tournament_id`),
  KEY `idx_participation_player_chrono` (`player_id`, `event_chrono`, `tournament_id`),
  KEY `idx_participation_tournament_player` (`tournament_id`, `player_id`),
  CONSTRAINT `fk_participation_player`
    FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_participation_tournament`
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
