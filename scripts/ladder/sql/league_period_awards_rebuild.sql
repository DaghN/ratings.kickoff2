-- REP-012: League awards rebuild — run via PHP (rules need ratedresults first-game tie-breaks).
-- Do not run this file directly in mysql; use:
--   php site/public_html/ops/run_finalize_league.php rebuild-all --target local-dev
--
-- Contract: docs/leagues-rules-spec.md

SELECT 'Use: php site/public_html/ops/run_finalize_league.php rebuild-all --target local-dev' AS instruction;
