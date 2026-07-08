-- Denormalize World Cup flag on event snapshots (parity with has_league / has_cup).
SET time_zone = '+00:00';

ALTER TABLE `amiga_player_event_snapshots`
  ADD COLUMN `is_world_cup` tinyint(1) NOT NULL DEFAULT 0 AFTER `has_cup`;