-- SCH-010: Per-player medal counts by league kind + time grain (8 slices).
-- Register: docs/coordination/schema-register.md
-- Rules: docs/leagues-rules-spec.md

CREATE TABLE IF NOT EXISTS `player_league_slice_totals` (
  `player_id` int(11) NOT NULL,
  `league_kind` enum('points','activity') NOT NULL,
  `period_type` enum('day','week','month','year') NOT NULL,
  `gold` int(10) unsigned NOT NULL DEFAULT 0,
  `silver` int(10) unsigned NOT NULL DEFAULT 0,
  `bronze` int(10) unsigned NOT NULL DEFAULT 0,
  `podiums` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`, `league_kind`, `period_type`),
  KEY `idx_slice_lookup` (`league_kind`, `period_type`, `gold`, `player_id`),
  KEY `idx_slice_player` (`player_id`, `league_kind`, `period_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
