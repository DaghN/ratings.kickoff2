-- Track B: tournament standings derived table + ground-truth Extra column.
-- Apply after 001_core.sql: mysql ... < scripts/amiga/sql/002_tournament_standings.sql
SET time_zone = '+00:00';

-- Idempotent on DBs created before Track B (skip if 001_core already has extra).
ALTER TABLE `amiga_games`
  ADD COLUMN `extra` varchar(80) DEFAULT NULL AFTER `goals_b`;

CREATE TABLE IF NOT EXISTS `amiga_tournament_standings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `scope_type` enum('overall','group','placement','knockout') NOT NULL DEFAULT 'overall',
  `scope_key` varchar(120) NOT NULL DEFAULT '',
  `position` smallint(6) NOT NULL,
  `games` smallint(6) NOT NULL DEFAULT 0,
  `wins` smallint(6) NOT NULL DEFAULT 0,
  `draws` smallint(6) NOT NULL DEFAULT 0,
  `losses` smallint(6) NOT NULL DEFAULT 0,
  `goals_for` smallint(6) NOT NULL DEFAULT 0,
  `goals_against` smallint(6) NOT NULL DEFAULT 0,
  `points` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amiga_tournament_standings_scope_player` (
    `tournament_id`, `scope_type`, `scope_key`, `player_id`
  ),
  KEY `idx_amiga_tournament_standings_tournament` (`tournament_id`),
  KEY `idx_amiga_tournament_standings_lookup` (`tournament_id`, `scope_type`, `scope_key`, `position`),
  CONSTRAINT `fk_amiga_tournament_standings_tournament`
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_amiga_tournament_standings_player`
    FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
