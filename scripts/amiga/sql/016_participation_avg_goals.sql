-- Event average goals per game on participation (player x tournament).
-- Apply after 015: mysql ko2amiga_db < scripts/amiga/sql/016_participation_avg_goals.sql

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_tournament_participation`
  ADD COLUMN `avg_goals_for` decimal(6,4) DEFAULT NULL AFTER `goals_against`,
  ADD COLUMN `avg_goals_against` decimal(6,4) DEFAULT NULL AFTER `avg_goals_for`;
