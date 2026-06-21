-- HoF record last-rise event anchors (SCH-029).
-- Policy: docs/amiga-hof-record-date-policy.md
-- Apply after 028: mysql ko2amiga_db < scripts/amiga/sql/derived/029_hof_record_rise_dates.sql

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_event_snapshots`
  ADD COLUMN `tournaments_played_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `tournaments_played_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `event_gold_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `event_gold_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `wc_played_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `wc_played_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `countries_played_in_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `countries_played_in_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `opponent_countries_faced_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `opponent_countries_faced_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `opponent_countries_beaten_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `opponent_countries_beaten_last_rise_event_date` date DEFAULT NULL;

ALTER TABLE `amiga_player_current`
  ADD COLUMN `tournaments_played_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `tournaments_played_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `event_gold_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `event_gold_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `wc_played_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `wc_played_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `countries_played_in_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `countries_played_in_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `opponent_countries_faced_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `opponent_countries_faced_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `opponent_countries_beaten_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `opponent_countries_beaten_last_rise_event_date` date DEFAULT NULL;
