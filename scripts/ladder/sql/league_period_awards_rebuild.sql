-- REP-012: League awards rebuild — run via PHP (rules need ratedresults first-game tie-breaks).
-- Do not run this file directly in mysql; use:
--   php scripts/finalize_league_periods.php --full-rebuild
--
-- Contract: docs/leagues-rules-spec.md

SELECT 'Use: php scripts/finalize_league_periods.php --full-rebuild' AS instruction;
