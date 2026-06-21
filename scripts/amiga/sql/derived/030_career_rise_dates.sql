-- Career cumulative HoF last-rise event anchors (SCH-030).
-- Policy: docs/amiga-hof-record-date-policy.md § SCH-030
-- Apply after 029: mysql ko2amiga_db < scripts/amiga/sql/derived/030_career_rise_dates.sql

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_event_snapshots`
  ADD COLUMN `number_games_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `number_games_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `number_wins_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `number_wins_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `goals_for_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `goals_for_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `double_digits_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `double_digits_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `clean_sheets_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `clean_sheets_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `different_opponents_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `different_opponents_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `different_victims_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `different_victims_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `double_digits_victims_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `double_digits_victims_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `clean_sheets_victims_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `clean_sheets_victims_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `biggest_rating_ascent_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `biggest_rating_ascent_last_rise_event_date` date DEFAULT NULL;

ALTER TABLE `amiga_player_current`
  ADD COLUMN `number_games_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `number_games_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `number_wins_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `number_wins_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `goals_for_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `goals_for_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `double_digits_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `double_digits_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `clean_sheets_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `clean_sheets_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `different_opponents_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `different_opponents_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `different_victims_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `different_victims_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `double_digits_victims_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `double_digits_victims_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `clean_sheets_victims_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `clean_sheets_victims_last_rise_event_date` date DEFAULT NULL,
  ADD COLUMN `biggest_rating_ascent_last_rise_tournament_id` int(11) DEFAULT NULL,
  ADD COLUMN `biggest_rating_ascent_last_rise_event_date` date DEFAULT NULL;
