-- SCH-021: Stored holder count per catalog key (hub catalog + milestone detail).
-- Register: docs/coordination/schema-register.md
-- DDL only — counts come from lobby rebuild (prepare) + incremental bump (simul/live).
-- Live writer: k2_milestone_holder_count_bump() from milestone_unlock.php.
-- Lobby bulk seed repair: k2_milestone_holder_counts_rebuild() in ops_seed_lobby.php only.

SET @has_holder_count := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE table_schema = DATABASE()
    AND table_name = 'milestone_definitions'
    AND column_name = 'holder_count'
);

SET @sql := IF(
  @has_holder_count = 0,
  'ALTER TABLE `milestone_definitions`
    ADD COLUMN `holder_count` int(10) unsigned NOT NULL DEFAULT 0
      COMMENT ''Unlock row count per key (history; includes deleted accounts)'' AFTER `sort_order`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
