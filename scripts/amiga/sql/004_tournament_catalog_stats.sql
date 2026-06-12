-- Tournament index aggregates for /amiga/tournaments.php (derived; no hot-path scans).
-- Apply after 003: mysql ko2amiga_db < scripts/amiga/sql/004_tournament_catalog_stats.sql
-- Populate: python -m scripts.amiga replay  (or catalog-stats-rebuild)

SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `amiga_tournament_catalog_stats` (
  `tournament_id` int(11) NOT NULL,
  `game_count` int(11) NOT NULL DEFAULT 0,
  `standing_players` int(11) NOT NULL DEFAULT 0,
  `standing_rows` int(11) NOT NULL DEFAULT 0,
  `league_scopes` int(11) NOT NULL DEFAULT 0,
  `knockout_ties` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`tournament_id`),
  CONSTRAINT `fk_amiga_tournament_catalog_stats_tournament`
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
