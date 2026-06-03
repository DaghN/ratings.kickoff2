-- REP-013: Rebuild player_league_slice_totals from player_league_award.
-- Run via: php site/public_html/ops/run_finalize_league.php rebuild-aggregates --target local-dev
-- (also runs at end of --full-rebuild / PER-003 finalize)

SET time_zone = '+00:00';

SELECT 'Use: php site/public_html/ops/run_finalize_league.php rebuild-aggregates --target local-dev' AS instruction;
