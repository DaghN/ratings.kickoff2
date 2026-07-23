-- SCH-052: matchup at-event event_chrono double (matches tournaments.chrono).
SET time_zone = '+00:00';

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'amiga_player_matchup_at_event'
    AND COLUMN_NAME = 'event_chrono'
    AND DATA_TYPE = 'double'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `amiga_player_matchup_at_event` MODIFY COLUMN `event_chrono` double NOT NULL');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;