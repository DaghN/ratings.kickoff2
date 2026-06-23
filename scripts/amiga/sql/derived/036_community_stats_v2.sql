-- Community stats v2 — headline extension columns (question catalog step 3).
-- Policy: docs/amiga-community-stats-policy.md · plan: docs/amiga-community-stats-implementation-plan.md

SET time_zone = '+00:00';

ALTER TABLE `amiga_community_stats`
  ADD COLUMN `TournamentsFinalized` int(11) DEFAULT NULL AFTER `CleanSheetsRatio`,
  ADD COLUMN `DistinctHostCountries` int(11) DEFAULT NULL AFTER `TournamentsFinalized`,
  ADD COLUMN `WcGamesPlayed` int(11) DEFAULT NULL AFTER `DistinctHostCountries`,
  ADD COLUMN `DistinctOpponentPairs` int(11) DEFAULT NULL AFTER `WcGamesPlayed`,
  ADD COLUMN `PlayersDebuted` int(11) DEFAULT NULL AFTER `DistinctOpponentPairs`;

ALTER TABLE `amiga_community_stats_snapshots`
  ADD COLUMN `TournamentsFinalized` int(11) DEFAULT NULL AFTER `CleanSheetsRatio`,
  ADD COLUMN `DistinctHostCountries` int(11) DEFAULT NULL AFTER `TournamentsFinalized`,
  ADD COLUMN `WcGamesPlayed` int(11) DEFAULT NULL AFTER `DistinctHostCountries`,
  ADD COLUMN `DistinctOpponentPairs` int(11) DEFAULT NULL AFTER `WcGamesPlayed`,
  ADD COLUMN `PlayersDebuted` int(11) DEFAULT NULL AFTER `DistinctOpponentPairs`;
