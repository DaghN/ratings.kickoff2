-- SCH-005: Status page performance indexes + monthly league aggregate.
-- Register: docs/coordination/schema-register.md
-- Steve: apply on staging/prod before enabling PG-006 monthly league post-game writes.

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = DATABASE()
     AND table_name = 'ratedresults'
     AND index_name = 'idx_ratedresults_date') = 0,
  'CREATE INDEX idx_ratedresults_date ON ratedresults (`Date`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = DATABASE()
     AND table_name = 'resulttable'
     AND index_name = 'idx_resulttable_live_status') = 0,
  'CREATE INDEX idx_resulttable_live_status ON resulttable (HasStarted, HasFinished, Shelved, StartTime)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `player_monthly_league` (
  `month_start` date NOT NULL,
  `player_id` int(11) NOT NULL,
  `played` int(11) NOT NULL DEFAULT 0,
  `wins` int(11) NOT NULL DEFAULT 0,
  `draws` int(11) NOT NULL DEFAULT 0,
  `losses` int(11) NOT NULL DEFAULT 0,
  `goals_for` int(11) NOT NULL DEFAULT 0,
  `goals_against` int(11) NOT NULL DEFAULT 0,
  `goal_difference` int(11) NOT NULL DEFAULT 0,
  `points` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`month_start`, `player_id`),
  KEY `idx_player_monthly_league_player` (`player_id`, `month_start`),
  KEY `idx_player_monthly_league_table` (`month_start`, `points`, `goal_difference`, `goals_for`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
