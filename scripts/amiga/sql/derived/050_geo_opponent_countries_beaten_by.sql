-- Geography fix: opponent_countries_beaten_by + no own-country auto-seed (writers).
-- Policy: docs/amiga-hof-tournament-geo-policy.md H5-H8

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_event_snapshots`
  ADD COLUMN `opponent_countries_beaten_by` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `opponent_countries_beaten`;

ALTER TABLE `amiga_player_current`
  ADD COLUMN `opponent_countries_beaten_by` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `opponent_countries_beaten`;

ALTER TABLE `amiga_player_slice_totals`
  ADD COLUMN `opponent_countries_beaten_by` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `opponent_countries_beaten`;

ALTER TABLE `amiga_player_slice_at_event`
  ADD COLUMN `opponent_countries_beaten_by` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `opponent_countries_beaten`;

ALTER TABLE `amiga_country_slice_totals`
  ADD COLUMN `opponent_countries_beaten_by` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `opponent_countries_beaten`;

ALTER TABLE `amiga_country_slice_at_event`
  ADD COLUMN `opponent_countries_beaten_by` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `opponent_countries_beaten`;