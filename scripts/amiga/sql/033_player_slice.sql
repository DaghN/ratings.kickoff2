-- World Cup player slice tables + retire wc_* from honours block on snapshots/current.
-- Policy: docs/amiga-world-cups-leaderboard-policy.md (slice 0)

SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `amiga_player_slice_totals` (
  `player_id` int(11) NOT NULL,
  `slice_key` varchar(32) NOT NULL,
  `tournaments_played` int(11) NOT NULL DEFAULT 0,
  `gold` int(11) NOT NULL DEFAULT 0,
  `silver` int(11) NOT NULL DEFAULT 0,
  `bronze` int(11) NOT NULL DEFAULT 0,
  `podiums` int(11) NOT NULL DEFAULT 0,
  `games` int(11) NOT NULL DEFAULT 0,
  `wins` int(11) NOT NULL DEFAULT 0,
  `draws` int(11) NOT NULL DEFAULT 0,
  `losses` int(11) NOT NULL DEFAULT 0,
  `goals_for` int(11) NOT NULL DEFAULT 0,
  `goals_against` int(11) NOT NULL DEFAULT 0,
  `points` int(11) NOT NULL DEFAULT 0,
  `tournaments_played_last_rise_tournament_id` int(11) DEFAULT NULL,
  `tournaments_played_last_rise_event_date` date DEFAULT NULL,
  PRIMARY KEY (`player_id`, `slice_key`),
  KEY `idx_slice_totals_key_tournaments` (`slice_key`, `tournaments_played`),
  KEY `idx_slice_totals_key_gold` (`slice_key`, `gold`),
  KEY `idx_slice_totals_key_points` (`slice_key`, `points`),
  CONSTRAINT `fk_slice_totals_player`
    FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `amiga_player_slice_at_event` (
  `player_id` int(11) NOT NULL,
  `slice_key` varchar(32) NOT NULL,
  `as_of_tournament_id` int(11) NOT NULL,
  `event_date` date DEFAULT NULL,
  `event_chrono` double DEFAULT NULL,
  `tournaments_played` int(11) NOT NULL DEFAULT 0,
  `gold` int(11) NOT NULL DEFAULT 0,
  `silver` int(11) NOT NULL DEFAULT 0,
  `bronze` int(11) NOT NULL DEFAULT 0,
  `podiums` int(11) NOT NULL DEFAULT 0,
  `games` int(11) NOT NULL DEFAULT 0,
  `wins` int(11) NOT NULL DEFAULT 0,
  `draws` int(11) NOT NULL DEFAULT 0,
  `losses` int(11) NOT NULL DEFAULT 0,
  `goals_for` int(11) NOT NULL DEFAULT 0,
  `goals_against` int(11) NOT NULL DEFAULT 0,
  `points` int(11) NOT NULL DEFAULT 0,
  `tournaments_played_last_rise_tournament_id` int(11) DEFAULT NULL,
  `tournaments_played_last_rise_event_date` date DEFAULT NULL,
  PRIMARY KEY (`player_id`, `slice_key`, `as_of_tournament_id`),
  KEY `idx_slice_at_event_tournament` (`as_of_tournament_id`),
  KEY `idx_slice_at_event_player_chrono` (`player_id`, `slice_key`, `event_date`, `event_chrono`, `as_of_tournament_id`),
  CONSTRAINT `fk_slice_at_event_player`
    FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_slice_at_event_tournament`
    FOREIGN KEY (`as_of_tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
