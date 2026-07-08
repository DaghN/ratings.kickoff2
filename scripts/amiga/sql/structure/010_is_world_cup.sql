-- World Cup catalog membership (L3 witness on tournaments).
SET time_zone = '+00:00';

ALTER TABLE `tournaments`
  ADD COLUMN `is_world_cup` tinyint(1) NOT NULL DEFAULT 0 AFTER `has_cup`;