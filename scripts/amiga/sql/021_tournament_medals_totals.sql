-- Tournament medals unification v2 slice 0: career totals schema (event_* + wc_played/wc_podiums; drop cup_*).
-- Apply after 020: mysql ko2amiga_db < scripts/amiga/sql/021_tournament_medals_totals.sql
-- Policy: docs/amiga-tournament-honours-rules.md v2 · Plan: docs/amiga-tournament-medals-unification-implementation-plan.md slice 0

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_tournament_totals`
  ADD COLUMN `event_gold` int(11) NOT NULL DEFAULT 0 AFTER `tournaments_won`,
  ADD COLUMN `event_silver` int(11) NOT NULL DEFAULT 0 AFTER `event_gold`,
  ADD COLUMN `event_bronze` int(11) NOT NULL DEFAULT 0 AFTER `event_silver`;

ALTER TABLE `amiga_player_tournament_totals`
  DROP COLUMN `cup_gold`,
  DROP COLUMN `cup_silver`,
  DROP COLUMN `cup_bronze`;

ALTER TABLE `amiga_player_tournament_totals`
  CHANGE COLUMN `podiums` `event_podiums` int(11) NOT NULL DEFAULT 0;

ALTER TABLE `amiga_player_tournament_totals`
  ADD COLUMN `wc_played` int(11) NOT NULL DEFAULT 0 AFTER `event_bronze`,
  ADD COLUMN `wc_podiums` int(11) NOT NULL DEFAULT 0 AFTER `wc_bronze`;
