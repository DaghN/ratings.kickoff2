-- Community headline aggregates live on amiga_community_* only (policy §10).
-- Drops legacy duplicate columns from HoF / realm snapshot tables.
-- Apply after 034: fresh bundle via schema_bundles DERIVED_SQL.

SET time_zone = '+00:00';

ALTER TABLE `amiga_generalstats`
  DROP COLUMN `NumberOfPlayers`,
  DROP COLUMN `DifferentOpponentsAverage`,
  DROP COLUMN `GamesPlayed`,
  DROP COLUMN `GamesPlayedAverage`,
  DROP COLUMN `NumberOfDecidedGames`,
  DROP COLUMN `NumberOfDraws`,
  DROP COLUMN `DecidedGamesRatio`,
  DROP COLUMN `DrawsRatio`,
  DROP COLUMN `GoalsScored`,
  DROP COLUMN `GoalsPerGameAverage`,
  DROP COLUMN `DoubleDigits`,
  DROP COLUMN `CleanSheets`,
  DROP COLUMN `DoubleDigitsRatio`,
  DROP COLUMN `CleanSheetsRatio`;

ALTER TABLE `amiga_realm_snapshots`
  DROP COLUMN `NumberOfPlayers`,
  DROP COLUMN `DifferentOpponentsAverage`,
  DROP COLUMN `GamesPlayed`,
  DROP COLUMN `GamesPlayedAverage`,
  DROP COLUMN `NumberOfDecidedGames`,
  DROP COLUMN `NumberOfDraws`,
  DROP COLUMN `DecidedGamesRatio`,
  DROP COLUMN `DrawsRatio`,
  DROP COLUMN `GoalsScored`,
  DROP COLUMN `GoalsPerGameAverage`,
  DROP COLUMN `DoubleDigits`,
  DROP COLUMN `CleanSheets`,
  DROP COLUMN `DoubleDigitsRatio`,
  DROP COLUMN `CleanSheetsRatio`;
