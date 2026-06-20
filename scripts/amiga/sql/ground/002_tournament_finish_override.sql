-- L3 witness: curated Tier E finish overrides (institutional claims).
-- Policy: docs/amiga-ground-layers-policy.md G5; docs/amiga-tournament-honours-rules.md §3 Tier E
-- Consumed at L5 finalize (participation_placement); rows are manifest-audited curated ground.

SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `amiga_tournament_finish_override` (
  `tournament_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `event_finish_position` smallint NOT NULL,
  PRIMARY KEY (`tournament_id`, `player_id`),
  KEY `idx_finish_override_player` (`player_id`),
  CONSTRAINT `fk_finish_override_tournament`
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_finish_override_player`
    FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
