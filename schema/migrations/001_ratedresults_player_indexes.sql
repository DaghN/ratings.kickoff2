-- SCH-001: Profile / player APIs filter ratedresults by idA OR idB.
-- Idempotent across local MySQL and server MariaDB.
-- Register: docs/coordination/schema-register.md

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = DATABASE()
     AND table_name = 'ratedresults'
     AND index_name = 'idx_ratedresults_idA') = 0,
  'CREATE INDEX idx_ratedresults_idA ON ratedresults (idA)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = DATABASE()
     AND table_name = 'ratedresults'
     AND index_name = 'idx_ratedresults_idB') = 0,
  'CREATE INDEX idx_ratedresults_idB ON ratedresults (idB)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
