-- Add stored blowout_rate + geography international game metrics to WC stats.
-- Spec: docs/amiga-world-cup-stats-table-plan.md §3.13

SET time_zone = '+00:00';

ALTER TABLE `amiga_world_cup_stats`
  ADD COLUMN `blowout_rate` decimal(10,8) DEFAULT NULL AFTER `low_scoring_rate`,
  ADD COLUMN `international_games` int(11) NOT NULL DEFAULT 0 AFTER `distinct_opponent_countries_pairs`,
  ADD COLUMN `international_game_share` decimal(10,8) DEFAULT NULL AFTER `international_games`;
