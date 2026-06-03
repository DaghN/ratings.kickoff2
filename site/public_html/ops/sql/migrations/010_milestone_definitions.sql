-- SCH-011: Milestone catalog (display metadata). Unlock rows stay in player_milestones.
-- Register: docs/coordination/schema-register.md
-- Seed: data/milestones_definitions_seed.json via scripts/oneoff/load_milestone_definitions.py

CREATE TABLE IF NOT EXISTS `milestone_definitions` (
  `milestone_key` varchar(64) NOT NULL,
  `display_name` varchar(128) NOT NULL,
  `tier_band` enum('aspirational','veteran','key','legendary') NOT NULL,
  `chart_token` enum('pitch','chrome','amber','holo') NOT NULL,
  `rule_short` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT 0,
  `icon` varchar(64) DEFAULT NULL COMMENT 'Asset id; TBD Phase 4',
  PRIMARY KEY (`milestone_key`),
  KEY `idx_milestone_definitions_tier_sort` (`tier_band`, `sort_order`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
