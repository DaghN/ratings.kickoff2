-- Tournament medals unification v2 slice 2: backfill WC event_finish_position from wc_medal.
-- Apply after 021: mysql ko2amiga_db < scripts/amiga/sql/021b_wc_finish_backfill.sql
-- Idempotent — safe to re-run.

SET time_zone = '+00:00';

UPDATE `amiga_player_tournament_participation` p
SET p.`event_finish_position` = CASE p.`wc_medal`
    WHEN 'gold' THEN 1
    WHEN 'silver' THEN 2
    WHEN 'bronze' THEN 3
    ELSE p.`event_finish_position`
END
WHERE p.`tournament_name` REGEXP '^World Cup[[:space:]]+[^[:space:]]'
  AND p.`wc_medal` IN ('gold', 'silver', 'bronze');
