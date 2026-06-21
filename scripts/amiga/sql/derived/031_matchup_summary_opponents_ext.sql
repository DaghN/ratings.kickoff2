-- SCH-031 (Amiga): matchup summary + at-event — goal extremes (online SCH-019 parity).
-- Contract: docs/amiga-opponents-wing-policy.md · docs/website-data-contract.md § player_matchup_summary
-- Idempotent: no-op when column already exists.

SET time_zone = '+00:00';

-- amiga_player_matchup_summary
SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_summary' AND COLUMN_NAME = 'max_goals_for'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_summary` ADD COLUMN `max_goals_for` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `goals_against`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_summary' AND COLUMN_NAME = 'max_goals_against'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_summary` ADD COLUMN `max_goals_against` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `max_goals_for`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_summary' AND COLUMN_NAME = 'min_goals_for'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_summary` ADD COLUMN `min_goals_for` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `max_goals_against`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_summary' AND COLUMN_NAME = 'min_goals_against'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_summary` ADD COLUMN `min_goals_against` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `min_goals_for`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_summary' AND COLUMN_NAME = 'max_win_margin'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_summary` ADD COLUMN `max_win_margin` smallint(5) unsigned NULL DEFAULT NULL AFTER `min_goals_against`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_summary' AND COLUMN_NAME = 'max_loss_margin'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_summary` ADD COLUMN `max_loss_margin` smallint(5) unsigned NULL DEFAULT NULL AFTER `max_win_margin`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_summary' AND COLUMN_NAME = 'max_draw_goals'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_summary` ADD COLUMN `max_draw_goals` smallint(5) unsigned NULL DEFAULT NULL AFTER `max_loss_margin`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_summary' AND COLUMN_NAME = 'max_goal_sum'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_summary` ADD COLUMN `max_goal_sum` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `max_draw_goals`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_summary' AND COLUMN_NAME = 'min_goal_sum'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_summary` ADD COLUMN `min_goal_sum` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `max_goal_sum`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- amiga_player_matchup_at_event (same tail — time-travel reads need extremes at cutoff)
SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_at_event' AND COLUMN_NAME = 'max_goals_for'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_at_event` ADD COLUMN `max_goals_for` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `goals_against`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_at_event' AND COLUMN_NAME = 'max_goals_against'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_at_event` ADD COLUMN `max_goals_against` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `max_goals_for`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_at_event' AND COLUMN_NAME = 'min_goals_for'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_at_event` ADD COLUMN `min_goals_for` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `max_goals_against`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_at_event' AND COLUMN_NAME = 'min_goals_against'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_at_event` ADD COLUMN `min_goals_against` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `min_goals_for`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_at_event' AND COLUMN_NAME = 'max_win_margin'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_at_event` ADD COLUMN `max_win_margin` smallint(5) unsigned NULL DEFAULT NULL AFTER `min_goals_against`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_at_event' AND COLUMN_NAME = 'max_loss_margin'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_at_event` ADD COLUMN `max_loss_margin` smallint(5) unsigned NULL DEFAULT NULL AFTER `max_win_margin`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_at_event' AND COLUMN_NAME = 'max_draw_goals'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_at_event` ADD COLUMN `max_draw_goals` smallint(5) unsigned NULL DEFAULT NULL AFTER `max_loss_margin`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_at_event' AND COLUMN_NAME = 'max_goal_sum'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_at_event` ADD COLUMN `max_goal_sum` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `max_draw_goals`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_at_event' AND COLUMN_NAME = 'min_goal_sum'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_at_event` ADD COLUMN `min_goal_sum` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `max_goal_sum`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
