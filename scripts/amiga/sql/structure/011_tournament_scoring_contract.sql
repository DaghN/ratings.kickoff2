-- L4b scoring contract (SC-0). Runtime authority on stages; defaults + freeze markers on tournaments.
-- Policy: docs/amiga-format-scoring-contract-policy.md
SET time_zone = '+00:00';

ALTER TABLE `tournament_stages`
  ADD COLUMN `scoring_primitive` enum('league_table','knockout_tie') DEFAULT NULL AFTER `config_json`;

ALTER TABLE `tournament_stages`
  ADD COLUMN `scoring_schema_version` smallint(6) DEFAULT NULL AFTER `scoring_primitive`;

ALTER TABLE `tournament_stages`
  ADD COLUMN `scoring_win_points` tinyint(3) unsigned DEFAULT NULL AFTER `scoring_schema_version`;

ALTER TABLE `tournament_stages`
  ADD COLUMN `scoring_draw_points` tinyint(3) unsigned DEFAULT NULL AFTER `scoring_win_points`;

ALTER TABLE `tournament_stages`
  ADD COLUMN `scoring_loss_points` tinyint(3) unsigned DEFAULT NULL AFTER `scoring_draw_points`;

ALTER TABLE `tournament_stages`
  ADD COLUMN `frozen_scoring_primitive` enum('league_table','knockout_tie') DEFAULT NULL AFTER `scoring_loss_points`;

ALTER TABLE `tournament_stages`
  ADD COLUMN `frozen_scoring_schema_version` smallint(6) DEFAULT NULL AFTER `frozen_scoring_primitive`;

ALTER TABLE `tournament_stages`
  ADD COLUMN `frozen_scoring_win_points` tinyint(3) unsigned DEFAULT NULL AFTER `frozen_scoring_schema_version`;

ALTER TABLE `tournament_stages`
  ADD COLUMN `frozen_scoring_draw_points` tinyint(3) unsigned DEFAULT NULL AFTER `frozen_scoring_win_points`;

ALTER TABLE `tournament_stages`
  ADD COLUMN `frozen_scoring_loss_points` tinyint(3) unsigned DEFAULT NULL AFTER `frozen_scoring_draw_points`;

ALTER TABLE `tournaments`
  ADD COLUMN `scoring_win_points_default` tinyint(3) unsigned DEFAULT NULL AFTER `is_world_cup`;

ALTER TABLE `tournaments`
  ADD COLUMN `scoring_draw_points_default` tinyint(3) unsigned DEFAULT NULL AFTER `scoring_win_points_default`;

ALTER TABLE `tournaments`
  ADD COLUMN `scoring_loss_points_default` tinyint(3) unsigned DEFAULT NULL AFTER `scoring_draw_points_default`;

ALTER TABLE `tournaments`
  ADD COLUMN `frozen_scoring_schema_version` smallint(6) DEFAULT NULL AFTER `scoring_loss_points_default`;

ALTER TABLE `tournaments`
  ADD COLUMN `scoring_frozen_at` datetime DEFAULT NULL AFTER `frozen_scoring_schema_version`;

CREATE TABLE IF NOT EXISTS `tournament_stage_scoring_steps` (
  `stage_id` int(11) NOT NULL,
  `sequence_no` smallint(6) NOT NULL,
  `step` enum(
    'points',
    'head_to_head',
    'goal_difference',
    'goals_for',
    'games_played',
    'aggregate_goal_difference',
    'extra_time',
    'penalty_shootout',
    'golden_goal'
  ) NOT NULL,
  PRIMARY KEY (`stage_id`, `sequence_no`),
  KEY `idx_tournament_stage_scoring_steps_stage` (`stage_id`, `sequence_no`),
  CONSTRAINT `fk_tournament_stage_scoring_steps_stage`
    FOREIGN KEY (`stage_id`) REFERENCES `tournament_stages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
