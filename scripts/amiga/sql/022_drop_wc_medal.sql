-- Tournament medals unification v2 slice 6: drop participation wc_medal column.
-- Apply after 021b: mysql ko2amiga_db < scripts/amiga/sql/022_drop_wc_medal.sql
-- Policy: docs/amiga-tournament-honours-rules.md v2 M5

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_tournament_participation`
  DROP COLUMN `wc_medal`;
