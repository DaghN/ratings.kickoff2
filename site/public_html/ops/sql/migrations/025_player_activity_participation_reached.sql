-- SCH-025: Establishing game when player last extended each active_* count (HoF/LB tie-break).
-- Written on P4b is_new_period; backfill via scripts/rebuild_participation_reached.php
-- Register: docs/coordination/schema-register.md
-- Contract: docs/website-data-contract.md § player_activity_participation

ALTER TABLE `player_activity_participation`
  ADD COLUMN `active_days_reached_at` datetime DEFAULT NULL COMMENT 'UTC when active_days last incremented (establishing game)' AFTER `active_days`,
  ADD COLUMN `active_days_reached_game_id` int(11) DEFAULT NULL AFTER `active_days_reached_at`,
  ADD COLUMN `active_weeks_reached_at` datetime DEFAULT NULL COMMENT 'UTC when active_weeks last incremented' AFTER `active_weeks`,
  ADD COLUMN `active_weeks_reached_game_id` int(11) DEFAULT NULL AFTER `active_weeks_reached_at`,
  ADD COLUMN `active_months_reached_at` datetime DEFAULT NULL COMMENT 'UTC when active_months last incremented' AFTER `active_months`,
  ADD COLUMN `active_months_reached_game_id` int(11) DEFAULT NULL AFTER `active_months_reached_at`,
  ADD COLUMN `active_years_reached_at` datetime DEFAULT NULL COMMENT 'UTC when active_years last incremented' AFTER `active_years`,
  ADD COLUMN `active_years_reached_game_id` int(11) DEFAULT NULL AFTER `active_years_reached_at`,
  ADD KEY `idx_activity_participation_days_reached` (`active_days`, `active_days_reached_at`, `player_id`),
  ADD KEY `idx_activity_participation_weeks_reached` (`active_weeks`, `active_weeks_reached_at`, `player_id`),
  ADD KEY `idx_activity_participation_months_reached` (`active_months`, `active_months_reached_at`, `player_id`),
  ADD KEY `idx_activity_participation_years_reached` (`active_years`, `active_years_reached_at`, `player_id`);
