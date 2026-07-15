-- Sparse inverse victim/culprit count changelog (ghost-event TT authority).
-- Policy: docs/amiga-player-inverse-count-timeline-policy.md
-- Populated at tournament finalize (Python + PHP) when a player's inverse count changes.

SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `amiga_player_inverse_count_at_event` (
  `player_id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `metric` enum('mgs_culprits','bw_culprits','mgc_victims','bl_victims') NOT NULL,
  `value_after` smallint unsigned NOT NULL,
  `event_date` date DEFAULT NULL,
  `event_chrono` double DEFAULT NULL,
  PRIMARY KEY (`player_id`, `tournament_id`, `metric`),
  KEY `idx_inv_count_tournament` (`tournament_id`),
  KEY `idx_inv_count_metric_chrono` (`metric`, `event_date`, `event_chrono`, `tournament_id`),
  KEY `idx_inv_count_player_metric_chrono` (`player_id`, `metric`, `event_date`, `event_chrono`, `tournament_id`),
  CONSTRAINT `fk_inv_count_player`
    FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_count_tournament`
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;