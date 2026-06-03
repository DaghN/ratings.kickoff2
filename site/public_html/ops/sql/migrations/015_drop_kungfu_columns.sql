-- SCH-015: Drop legacy KungFu columns (unused by ratings site; coordinate Steve on prod C++ writes).
-- playertable: KungFu* (nine columns on prod snapshot)
-- resulttable: KungFuGameID
-- Idempotent: no-op when columns already absent (e.g. after prior apply).

-- Prod snapshot may have invalid zero dates on NOT NULL datetime cols; ALTER rebuild validates rows.
SET @OLD_SQL_MODE := @@SESSION.sql_mode;
SET SESSION sql_mode = 'ALLOW_INVALID_DATES,NO_ENGINE_SUBSTITUTION';

UPDATE `playertable`
SET `LastGame` = '1970-01-01 00:00:00'
WHERE `LastGame` = '0000-00-00 00:00:00' OR `LastGame` < '1000-01-01 00:00:00';

UPDATE `playertable`
SET `LastLogin` = '1970-01-01 00:00:00'
WHERE `LastLogin` = '0000-00-00 00:00:00' OR `LastLogin` < '1000-01-01 00:00:00';

SET SESSION group_concat_max_len = 8192;

SET @drop_playertable := (
  SELECT GROUP_CONCAT(CONCAT('DROP COLUMN `', COLUMN_NAME, '`') SEPARATOR ', ')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'playertable'
    AND COLUMN_NAME LIKE 'KungFu%'
);

SET @sql_pt := IF(
  @drop_playertable IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE `playertable` ', @drop_playertable)
);
PREPARE stmt_pt FROM @sql_pt;
EXECUTE stmt_pt;
DEALLOCATE PREPARE stmt_pt;

SET @has_kungfu_game_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'resulttable'
    AND COLUMN_NAME = 'KungFuGameID'
);

SET @sql_rt := IF(
  @has_kungfu_game_id = 0,
  'SELECT 1',
  'ALTER TABLE `resulttable` DROP COLUMN `KungFuGameID`'
);
PREPARE stmt_rt FROM @sql_rt;
EXECUTE stmt_rt;
DEALLOCATE PREPARE stmt_rt;

SET SESSION sql_mode = @OLD_SQL_MODE;
