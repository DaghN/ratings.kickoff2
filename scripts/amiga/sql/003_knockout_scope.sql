-- Knockout pair scopes (two-player elimination ties).
-- ARCHIVED from apply_schema (Jun 2026): regresses 002 league|knockout enum — upgrade archaeology only.
SET time_zone = '+00:00';

ALTER TABLE `amiga_tournament_standings`
  MODIFY COLUMN `scope_type` enum('overall','group','placement','knockout') NOT NULL DEFAULT 'overall';

ALTER TABLE `amiga_tournament_standings`
  MODIFY COLUMN `scope_key` varchar(120) NOT NULL DEFAULT '';
