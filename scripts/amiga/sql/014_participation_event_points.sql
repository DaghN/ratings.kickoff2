-- Participation: rename points → event_points (full-event 3-1-0 tally from games).
-- Phase-scoped league points remain in amiga_tournament_standings only.
-- Apply after 013: mysql ko2amiga_db < scripts/amiga/sql/014_participation_event_points.sql

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_tournament_participation`
  CHANGE COLUMN `points` `event_points` smallint(6) NOT NULL DEFAULT 0;
