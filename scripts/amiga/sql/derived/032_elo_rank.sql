-- Career Elo ladder rank at each finalize (slice: elo_rank).
-- Policy: docs/amiga-event-snapshot-policy.md S8 · docs/amiga-time-travel-policy.md
-- Populated: tournament finalize (Python + PHP) for all players with NumberGames > 0.

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_event_snapshots`
  ADD COLUMN `elo_rank` smallint unsigned DEFAULT NULL AFTER `Rating`;

ALTER TABLE `amiga_player_current`
  ADD COLUMN `elo_rank` smallint unsigned DEFAULT NULL AFTER `Rating`;

CREATE TABLE IF NOT EXISTS `amiga_player_elo_rank_at_event` (
  `player_id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `event_date` date DEFAULT NULL,
  `event_chrono` double DEFAULT NULL,
  `elo_rank` smallint unsigned NOT NULL,
  PRIMARY KEY (`player_id`, `tournament_id`),
  KEY `idx_elo_rank_tournament` (`tournament_id`),
  KEY `idx_elo_rank_player_chrono` (`player_id`, `event_date`, `event_chrono`, `tournament_id`),
  CONSTRAINT `fk_elo_rank_player`
    FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_elo_rank_tournament`
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
