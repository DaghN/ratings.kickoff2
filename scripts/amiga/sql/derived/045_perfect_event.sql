-- Perfect event honours + HoF (SCH-045).
-- Policy: docs/amiga-perfect-event-policy.md
-- Apply: mysql ko2amiga_db < scripts/amiga/sql/derived/045_perfect_event.sql

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_event_snapshots`
  ADD COLUMN `is_perfect_event` tinyint(1) NOT NULL DEFAULT 0 AFTER `best_knockout_phase`,
  ADD COLUMN `perfect_events` smallint(6) NOT NULL DEFAULT 0 AFTER `event_podiums`,
  ADD COLUMN `perfect_events_last_rise_tournament_id` int(11) DEFAULT NULL AFTER `event_gold_last_rise_event_date`,
  ADD COLUMN `perfect_events_last_rise_event_date` date DEFAULT NULL AFTER `perfect_events_last_rise_tournament_id`;

ALTER TABLE `amiga_player_current`
  ADD COLUMN `perfect_events` smallint(6) NOT NULL DEFAULT 0 AFTER `event_podiums`,
  ADD COLUMN `perfect_events_last_rise_tournament_id` int(11) DEFAULT NULL AFTER `event_gold_last_rise_event_date`,
  ADD COLUMN `perfect_events_last_rise_event_date` date DEFAULT NULL AFTER `perfect_events_last_rise_tournament_id`;

ALTER TABLE `amiga_tournament_catalog_stats`
  ADD COLUMN `has_perfect_participant` tinyint(1) NOT NULL DEFAULT 0 AFTER `knockout_ties`;

ALTER TABLE `amiga_generalstats`
  ADD COLUMN `MostPerfectEvents` int DEFAULT NULL AFTER `MostTournamentWinsDate`,
  ADD COLUMN `MostPerfectEventsID` int DEFAULT NULL AFTER `MostPerfectEvents`,
  ADD COLUMN `MostPerfectEventsName` varchar(50) DEFAULT NULL AFTER `MostPerfectEventsID`,
  ADD COLUMN `MostPerfectEventsDate` mediumtext AFTER `MostPerfectEventsName`;

ALTER TABLE `amiga_realm_snapshots`
  ADD COLUMN `MostPerfectEvents` int DEFAULT NULL AFTER `MostTournamentWinsDate`,
  ADD COLUMN `MostPerfectEventsID` int DEFAULT NULL AFTER `MostPerfectEvents`,
  ADD COLUMN `MostPerfectEventsName` varchar(50) DEFAULT NULL AFTER `MostPerfectEventsID`,
  ADD COLUMN `MostPerfectEventsDate` mediumtext AFTER `MostPerfectEventsName`;