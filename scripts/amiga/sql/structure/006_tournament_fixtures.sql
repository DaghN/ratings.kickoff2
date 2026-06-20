-- Tournament stage + fixture foundation for future live events.
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `tournament_stages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `parent_stage_id` int(11) DEFAULT NULL,
  `stage_key` varchar(80) NOT NULL,
  `name` varchar(120) NOT NULL,
  `stage_type` enum('round_robin','knockout') NOT NULL,
  `track_key` varchar(80) DEFAULT NULL,
  `sequence_no` smallint(6) NOT NULL DEFAULT 0,
  `config_json` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tournament_stages_key` (`tournament_id`, `stage_key`),
  KEY `idx_tournament_stages_tournament` (`tournament_id`, `sequence_no`),
  KEY `idx_tournament_stages_parent` (`parent_stage_id`),
  CONSTRAINT `fk_tournament_stages_tournament`
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tournament_stages_parent`
    FOREIGN KEY (`parent_stage_id`) REFERENCES `tournament_stages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tournament_stage_players` (
  `stage_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `seed_no` smallint(6) DEFAULT NULL,
  `group_key` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`stage_id`, `player_id`),
  KEY `idx_tournament_stage_players_player` (`player_id`),
  CONSTRAINT `fk_tournament_stage_players_stage`
    FOREIGN KEY (`stage_id`) REFERENCES `tournament_stages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tournament_stage_players_player`
    FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tournament_fixtures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stage_id` int(11) NOT NULL,
  `fixture_key` varchar(100) NOT NULL,
  `player_a_id` int(11) DEFAULT NULL,
  `player_b_id` int(11) DEFAULT NULL,
  `leg_no` smallint(6) NOT NULL DEFAULT 1,
  `status` enum('scheduled','played','void') NOT NULL DEFAULT 'scheduled',
  `phase_label` varchar(50) DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tournament_fixtures_key` (`stage_id`, `fixture_key`),
  KEY `idx_tournament_fixtures_stage` (`stage_id`, `status`),
  KEY `idx_tournament_fixtures_player_a` (`player_a_id`),
  KEY `idx_tournament_fixtures_player_b` (`player_b_id`),
  CONSTRAINT `fk_tournament_fixtures_stage`
    FOREIGN KEY (`stage_id`) REFERENCES `tournament_stages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tournament_fixtures_player_a`
    FOREIGN KEY (`player_a_id`) REFERENCES `amiga_players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tournament_fixtures_player_b`
    FOREIGN KEY (`player_b_id`) REFERENCES `amiga_players` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `amiga_games`
  ADD COLUMN `fixture_id` int(11) DEFAULT NULL AFTER `tournament_id`;

ALTER TABLE `amiga_games`
  ADD KEY `idx_amiga_games_fixture` (`fixture_id`);

ALTER TABLE `amiga_games`
  ADD CONSTRAINT `fk_amiga_games_fixture`
    FOREIGN KEY (`fixture_id`) REFERENCES `tournament_fixtures` (`id`) ON DELETE SET NULL;
