-- Mirror: scripts/amiga/sql/derived/035_drop_realm_aggregate_columns.sql

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
