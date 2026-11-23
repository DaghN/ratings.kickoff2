-- ARCHIVE COPY, NOT APPLIED. New DDL goes into sql/ground, sql/structure or sql/derived (see README.md).
-- Slice 8: drop legacy player tables superseded by snapshots + current.
-- Upgrade-only: run on DBs that still have these tables after migrating to snapshots.
-- Fresh installs: apply_schema never creates them (see import_access.py).
SET time_zone = '+00:00';

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `amiga_player_tournament_totals`;
DROP TABLE IF EXISTS `amiga_player_tournament_participation`;
DROP TABLE IF EXISTS `amiga_rating_events`;
DROP TABLE IF EXISTS `amiga_player_stats`;

SET FOREIGN_KEY_CHECKS = 1;
