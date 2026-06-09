-- Directed head-to-head pair totals (derived from amiga_games).
-- Apply after 011: mysql ko2amiga_db < scripts/amiga/sql/012_player_matchup_summary.sql
-- Populate: slice 8+ rebuild.

SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `amiga_player_matchup_summary` (
  `player_id` int(11) NOT NULL,
  `opponent_id` int(11) NOT NULL,
  `games` smallint(5) unsigned NOT NULL DEFAULT 0,
  `wins` smallint(5) unsigned NOT NULL DEFAULT 0,
  `draws` smallint(5) unsigned NOT NULL DEFAULT 0,
  `losses` smallint(5) unsigned NOT NULL DEFAULT 0,
  `goals_for` smallint(5) unsigned NOT NULL DEFAULT 0,
  `goals_against` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`, `opponent_id`),
  KEY `idx_matchup_opponent` (`opponent_id`, `player_id`),
  CONSTRAINT `fk_matchup_player`
    FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_matchup_opponent`
    FOREIGN KEY (`opponent_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
