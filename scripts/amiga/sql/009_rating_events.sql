-- Tournament finalize rating events + finalize markers (Slice 0).
-- Apply after 008: mysql ko2amiga_db < scripts/amiga/sql/009_rating_events.sql
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `amiga_rating_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `rating_before` decimal(10,6) NOT NULL,
  `rating_delta` decimal(10,6) NOT NULL,
  `rating_after` decimal(10,6) NOT NULL,
  `performance_rating` decimal(10,6) DEFAULT NULL,
  `games_in_event` smallint(6) NOT NULL DEFAULT 0,
  `finalized_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rating_event_tournament_player` (`tournament_id`, `player_id`),
  KEY `idx_rating_events_player_chrono` (`player_id`, `finalized_at`),
  KEY `idx_rating_events_tournament` (`tournament_id`),
  CONSTRAINT `fk_rating_events_tournament`
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rating_events_player`
    FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `tournaments`
  ADD COLUMN `rating_finalized` tinyint(1) NOT NULL DEFAULT 0 AFTER `completed_at`;

ALTER TABLE `tournaments`
  ADD COLUMN `rating_finalized_at` datetime DEFAULT NULL AFTER `rating_finalized`;

ALTER TABLE `tournaments`
  ADD KEY `idx_tournaments_rating_finalized` (`rating_finalized`);
