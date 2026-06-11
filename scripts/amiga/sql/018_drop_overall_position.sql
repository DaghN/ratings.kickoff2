-- Event finish migration slice 8: drop legacy overall_position from participation.
-- Apply after 017 + participation-rebuild on slice 5+ data.
-- Policy: docs/amiga-tournament-honours-rules.md · Plan: slice 8

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_tournament_participation`
  DROP COLUMN `overall_position`;
