-- Tournament format foundation (ground truth metadata for legacy + future live events).
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `tournament_format_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `schema_version` smallint(6) NOT NULL DEFAULT 1,
  `description` varchar(255) DEFAULT NULL,
  `spec_json` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tournament_format_templates_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `tournaments`
  ADD COLUMN `format_template_id` int(11) DEFAULT NULL AFTER `player_count`;

ALTER TABLE `tournaments`
  ADD COLUMN `format_overrides` longtext DEFAULT NULL AFTER `format_template_id`;

ALTER TABLE `tournaments`
  ADD COLUMN `has_league` tinyint(1) NOT NULL DEFAULT 0 AFTER `format_overrides`;

ALTER TABLE `tournaments`
  ADD COLUMN `has_cup` tinyint(1) NOT NULL DEFAULT 0 AFTER `has_league`;

ALTER TABLE `tournaments`
  ADD KEY `idx_tournaments_format_template` (`format_template_id`);

ALTER TABLE `tournaments`
  ADD KEY `idx_tournaments_format_flags` (`has_league`, `has_cup`);

ALTER TABLE `tournaments`
  ADD CONSTRAINT `fk_tournaments_format_template`
    FOREIGN KEY (`format_template_id`) REFERENCES `tournament_format_templates` (`id`);
