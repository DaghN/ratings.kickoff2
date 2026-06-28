-- SCH-044 (Amiga): per-opponent performance rating on matchup summary + at-event.
-- Contract: docs/amiga-performance-rating.md (pair TPR) · docs/amiga-matchup-at-event-policy.md
-- Cumulative directed pair TPR through the event (frozen per-game opponent ratings).
-- Idempotent: no-op when column already exists.

SET time_zone = '+00:00';

-- amiga_player_matchup_summary
SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_summary' AND COLUMN_NAME = 'performance_rating'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_summary` ADD COLUMN `performance_rating` decimal(10,6) NULL DEFAULT NULL AFTER `cs_losses`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- amiga_player_matchup_at_event
SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amiga_player_matchup_at_event' AND COLUMN_NAME = 'performance_rating'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_at_event` ADD COLUMN `performance_rating` decimal(10,6) NULL DEFAULT NULL AFTER `cs_losses`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;