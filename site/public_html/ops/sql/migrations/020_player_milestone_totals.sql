-- SCH-020: Per-player milestone tier counts (meta leaderboard + profile hero).
-- Register: docs/coordination/schema-register.md
-- Live writer: k2_milestone_unlock_insert() bump after unlock; repair: k2_milestone_totals_rebuild().

CREATE TABLE IF NOT EXISTS `player_milestone_totals` (
  `player_id` int(11) NOT NULL,
  `total` smallint(5) unsigned NOT NULL DEFAULT 0,
  `aspirational` smallint(5) unsigned NOT NULL DEFAULT 0,
  `dedicated` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'tier_band veteran',
  `accomplished` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'tier_band key',
  `legendary` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`),
  KEY `idx_milestone_totals_lb` (`total`, `aspirational`, `dedicated`, `accomplished`, `legendary`, `player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `player_milestone_totals` (
  `player_id`,
  `total`,
  `aspirational`,
  `dedicated`,
  `accomplished`,
  `legendary`
)
SELECT
  pm.`player_id`,
  COUNT(*) AS `total`,
  COALESCE(SUM(md.`tier_band` = 'aspirational'), 0) AS `aspirational`,
  COALESCE(SUM(md.`tier_band` = 'veteran'), 0) AS `dedicated`,
  COALESCE(SUM(md.`tier_band` = 'key'), 0) AS `accomplished`,
  COALESCE(SUM(md.`tier_band` = 'legendary'), 0) AS `legendary`
FROM `player_milestones` pm
INNER JOIN `milestone_definitions` md ON md.`milestone_key` = pm.`milestone_key`
GROUP BY pm.`player_id`
ON DUPLICATE KEY UPDATE
  `total` = VALUES(`total`),
  `aspirational` = VALUES(`aspirational`),
  `dedicated` = VALUES(`dedicated`),
  `accomplished` = VALUES(`accomplished`),
  `legendary` = VALUES(`legendary`);
