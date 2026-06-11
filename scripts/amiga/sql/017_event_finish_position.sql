-- Event finish migration slice 0: additive columns on participation.
-- Apply after 016: mysql ko2amiga_db < scripts/amiga/sql/017_event_finish_position.sql
-- Policy: docs/amiga-tournament-honours-rules.md · Plan: docs/amiga-event-finish-implementation-plan.md slice 0
-- Writers populate in slice 5; overall_position dropped in slice 8.

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_tournament_participation`
  ADD COLUMN `event_finish_position` smallint DEFAULT NULL AFTER `overall_position`,
  ADD COLUMN `best_knockout_phase` varchar(50) DEFAULT NULL AFTER `wc_medal`;
