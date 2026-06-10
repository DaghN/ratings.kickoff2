-- Event performance rating (chess-style TPR) on rating events + participation denorm.
-- Apply: mysql ko2amiga_db < scripts/amiga/sql/015_performance_rating.sql
-- Backfill: python -m scripts.amiga performance-rating-rebuild
--            python -m scripts.amiga participation-rebuild

SET time_zone = '+00:00';

ALTER TABLE `amiga_rating_events`
  ADD COLUMN `performance_rating` decimal(10,6) DEFAULT NULL
  AFTER `rating_after`;

ALTER TABLE `amiga_player_tournament_participation`
  ADD COLUMN `performance_rating` decimal(10,6) DEFAULT NULL
  AFTER `rating_after`;
