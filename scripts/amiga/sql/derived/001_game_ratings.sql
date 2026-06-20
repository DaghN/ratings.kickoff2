-- L3 derived: per-game Elo and outcome flags (1:1 with amiga_games).
-- Split from legacy sql/001_core.sql (ground layers slice 1).
SET time_zone = '+00:00';

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
