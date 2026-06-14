-- SCH-021: Stored holder count per catalog key (hub catalog + milestone detail).
-- Register: docs/coordination/schema-register.md
-- Live writer: k2_milestone_holder_count_bump() from milestone_unlock.php; repair: k2_milestone_holder_counts_rebuild().

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
      COMMENT ''Players with unlock (playertable join); catalog sort/display'' AFTER `sort_order`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `milestone_definitions` SET `holder_count` = 0;

UPDATE `milestone_definitions` d
INNER JOIN (
  SELECT pm.`milestone_key`, COUNT(*) AS `holders`
  FROM `player_milestones` pm
  INNER JOIN `playertable` p ON p.`ID` = pm.`player_id`
  GROUP BY pm.`milestone_key`
) h ON h.`milestone_key` = d.`milestone_key`
SET d.`holder_count` = h.`holders`;
