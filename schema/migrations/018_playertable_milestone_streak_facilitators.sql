-- SCH-018: playertable facilitators for post-game chronological milestones (P6).
-- Contract: docs/website-data-contract.md · docs/coordination/post-game-milestone-facilitators-pending.md
-- Idempotent: no-op when column already exists.

SET time_zone = '+00:00';

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'playertable' AND COLUMN_NAME = 'ScoreStreak'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `playertable` ADD COLUMN `ScoreStreak` smallint(6) NOT NULL DEFAULT 0 AFTER `LongestNonLossStreak`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'playertable' AND COLUMN_NAME = 'MerchantStreak'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `playertable` ADD COLUMN `MerchantStreak` smallint(6) NOT NULL DEFAULT 0 AFTER `ScoreStreak`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'playertable' AND COLUMN_NAME = 'ExactTenGoalStreak'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `playertable` ADD COLUMN `ExactTenGoalStreak` smallint(6) NOT NULL DEFAULT 0 AFTER `MerchantStreak`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'playertable' AND COLUMN_NAME = 'WinMarginOneStreak'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `playertable` ADD COLUMN `WinMarginOneStreak` smallint(6) NOT NULL DEFAULT 0 AFTER `ExactTenGoalStreak`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'playertable' AND COLUMN_NAME = 'LossMarginOneStreak'
);
SET @sql := IF(@has > 0, 'SELECT 1', 'ALTER TABLE `playertable` ADD COLUMN `LossMarginOneStreak` smallint(6) NOT NULL DEFAULT 0 AFTER `WinMarginOneStreak`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
