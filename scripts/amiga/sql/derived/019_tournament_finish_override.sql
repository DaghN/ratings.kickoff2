-- Event finish migration slice 9: curated finish overrides (Tier E hook).
-- Apply after 018: mysql ko2amiga_db < scripts/amiga/sql/019_tournament_finish_override.sql
-- Policy: docs/amiga-tournament-honours-rules.md §3 Tier E

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
