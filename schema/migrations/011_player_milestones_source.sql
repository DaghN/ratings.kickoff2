-- SCH-012: Link each milestone unlock to the game or league that caused it.
-- Register: docs/coordination/schema-register.md
-- Contract: docs/website-data-contract.md § player_milestones

ALTER TABLE `player_milestones`
  ADD COLUMN `source_kind` enum('game','league') DEFAULT NULL
    COMMENT 'game = ratedresults row; league = closed league award' AFTER `value`,
  ADD COLUMN `source_game_id` int(11) DEFAULT NULL
    COMMENT 'ratedresults.id when source_kind=game' AFTER `source_kind`,
  ADD COLUMN `source_league_kind` enum('points','activity') DEFAULT NULL AFTER `source_game_id`,
  ADD COLUMN `source_period_type` enum('day','week','month','year') DEFAULT NULL AFTER `source_league_kind`,
  ADD COLUMN `source_period_start` date DEFAULT NULL
    COMMENT 'league_period key when source_kind=league' AFTER `source_period_type`,
  ADD KEY `idx_milestone_source_game` (`source_game_id`),
  ADD KEY `idx_milestone_source_league` (`source_league_kind`, `source_period_type`, `source_period_start`);
