-- Career tournament rollups per player (derived from participation).
-- Apply after 010: mysql ko2amiga_db < scripts/amiga/sql/011_player_tournament_totals.sql
-- Populate: slice 2+ rebuild.

SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `amiga_player_tournament_totals` (
  `player_id` int(11) NOT NULL,
  `tournaments_played` int(11) NOT NULL DEFAULT 0,
  `tournaments_won` int(11) NOT NULL DEFAULT 0,
  `event_gold` int(11) NOT NULL DEFAULT 0,
  `event_silver` int(11) NOT NULL DEFAULT 0,
  `event_bronze` int(11) NOT NULL DEFAULT 0,
  `event_podiums` int(11) NOT NULL DEFAULT 0,
  `wc_played` int(11) NOT NULL DEFAULT 0,
  `wc_gold` int(11) NOT NULL DEFAULT 0,
  `wc_silver` int(11) NOT NULL DEFAULT 0,
  `wc_bronze` int(11) NOT NULL DEFAULT 0,
  `wc_podiums` int(11) NOT NULL DEFAULT 0,
  `last_event_date` date DEFAULT NULL,
  `last_tournament_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`player_id`),
  CONSTRAINT `fk_tournament_totals_player`
    FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
