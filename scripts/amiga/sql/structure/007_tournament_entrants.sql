-- Tournament-level entrant registration ground truth for future live events.
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `tournament_entrants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `seed_no` smallint(6) DEFAULT NULL,
  `status` enum('registered','withdrawn','replaced') NOT NULL DEFAULT 'registered',
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tournament_entrants_player` (`tournament_id`, `player_id`),
  KEY `idx_tournament_entrants_tournament_seed` (`tournament_id`, `seed_no`),
  KEY `idx_tournament_entrants_player` (`player_id`),
  CONSTRAINT `fk_tournament_entrants_tournament`
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tournament_entrants_player`
    FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
