-- Career peak/nadir rating event anchors (Amiga commits rating at tournament finalize).
-- Policy: docs/amiga-stored-field-semantics.md · docs/amiga-data-contract.md
-- Populated: tournament finalize (Python + PHP) via apply_peak_from_event_rating.

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_event_snapshots`
  ADD COLUMN `peak_rating_tournament_id` int(11) DEFAULT NULL AFTER `PeakRating`,
  ADD COLUMN `lowest_rating_tournament_id` int(11) DEFAULT NULL AFTER `LowestRating`;

ALTER TABLE `amiga_player_current`
  ADD COLUMN `peak_rating_tournament_id` int(11) DEFAULT NULL AFTER `PeakRating`,
  ADD COLUMN `lowest_rating_tournament_id` int(11) DEFAULT NULL AFTER `LowestRating`;

ALTER TABLE `amiga_player_event_snapshots`
  ADD KEY `idx_peak_rating_tournament` (`peak_rating_tournament_id`),
  ADD KEY `idx_lowest_rating_tournament` (`lowest_rating_tournament_id`);

ALTER TABLE `amiga_player_current`
  ADD KEY `idx_peak_rating_tournament` (`peak_rating_tournament_id`),
  ADD KEY `idx_lowest_rating_tournament` (`lowest_rating_tournament_id`);