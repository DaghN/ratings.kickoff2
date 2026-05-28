-- REP-013: Rebuild player_league_slice_totals from player_league_award.
-- Run via: php scripts/finalize_league_periods.php --rebuild-aggregates
-- (also runs at end of --full-rebuild / PER-003 finalize)

SET time_zone = '+00:00';

SELECT 'Use: php scripts/finalize_league_periods.php --rebuild-aggregates' AS instruction;
