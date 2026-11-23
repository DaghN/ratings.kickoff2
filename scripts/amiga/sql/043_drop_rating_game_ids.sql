-- ARCHIVE COPY, NOT APPLIED. The file in use is sql/derived/043_drop_rating_game_ids.sql.
-- Amiga rating peak/nadir are event-level (finalize), not per-game.
-- Online playertable retains PeakRatingGameID / LowestRatingGameID; Amiga drops them.

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_event_snapshots`
  DROP COLUMN `PeakRatingGameID`,
  DROP COLUMN `LowestRatingGameID`;

ALTER TABLE `amiga_player_current`
  DROP COLUMN `PeakRatingGameID`,
  DROP COLUMN `LowestRatingGameID`;