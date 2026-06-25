-- Career peak Elo ladder rank (best rank ever) at each finalize.
-- Policy: docs/amiga-time-travel-policy.md · docs/amiga-data-contract.md
-- Populated: tournament finalize (Python + PHP) alongside elo_rank.

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_elo_rank_at_event`
  ADD COLUMN `peak_elo_rank` smallint unsigned DEFAULT NULL AFTER `elo_rank`,
  ADD COLUMN `peak_elo_rank_tournament_id` int(11) DEFAULT NULL AFTER `peak_elo_rank`;

ALTER TABLE `amiga_player_current`
  ADD COLUMN `peak_elo_rank` smallint unsigned DEFAULT NULL AFTER `elo_rank`,
  ADD COLUMN `peak_elo_rank_tournament_id` int(11) DEFAULT NULL AFTER `peak_elo_rank`;

ALTER TABLE `amiga_player_elo_rank_at_event`
  ADD KEY `idx_peak_elo_rank_tournament` (`peak_elo_rank_tournament_id`);