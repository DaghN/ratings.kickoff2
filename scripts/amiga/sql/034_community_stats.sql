-- Community stats (realm-wide Activity aggregates) — separate from HoF generalstats.
-- Policy: docs/amiga-community-stats-policy.md
-- Mirror of sql/derived/034_community_stats.sql

SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `amiga_community_stats` (
  `id` tinyint(4) NOT NULL,
  `NumberOfPlayers` int(11) DEFAULT NULL,
  `DifferentOpponentsAverage` decimal(10,5) DEFAULT NULL,
  `GamesPlayed` int(11) DEFAULT NULL,
  `GamesPlayedAverage` decimal(10,3) DEFAULT NULL,
  `NumberOfDecidedGames` int(11) DEFAULT NULL,
  `NumberOfDraws` int(11) DEFAULT NULL,
  `DecidedGamesRatio` decimal(10,8) DEFAULT NULL,
  `DrawsRatio` decimal(10,8) DEFAULT NULL,
  `GoalsScored` int(11) DEFAULT NULL,
  `GoalsPerGameAverage` decimal(10,7) DEFAULT NULL,
  `DoubleDigits` int(11) DEFAULT NULL,
  `CleanSheets` int(11) DEFAULT NULL,
  `DoubleDigitsRatio` decimal(10,8) DEFAULT NULL,
  `CleanSheetsRatio` decimal(10,8) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `amiga_community_stats` (`id`) VALUES (1);

CREATE TABLE IF NOT EXISTS `amiga_community_stats_snapshots` (
  `tournament_id` int(11) NOT NULL,
  `event_date` date DEFAULT NULL,
  `event_chrono` double DEFAULT NULL,
  `tournament_name` varchar(50) NOT NULL,
  `finalized_at` datetime NOT NULL,
  `NumberOfPlayers` int(11) DEFAULT NULL,
  `DifferentOpponentsAverage` decimal(10,5) DEFAULT NULL,
  `GamesPlayed` int(11) DEFAULT NULL,
  `GamesPlayedAverage` decimal(10,3) DEFAULT NULL,
  `NumberOfDecidedGames` int(11) DEFAULT NULL,
  `NumberOfDraws` int(11) DEFAULT NULL,
  `DecidedGamesRatio` decimal(10,8) DEFAULT NULL,
  `DrawsRatio` decimal(10,8) DEFAULT NULL,
  `GoalsScored` int(11) DEFAULT NULL,
  `GoalsPerGameAverage` decimal(10,7) DEFAULT NULL,
  `DoubleDigits` int(11) DEFAULT NULL,
  `CleanSheets` int(11) DEFAULT NULL,
  `DoubleDigitsRatio` decimal(10,8) DEFAULT NULL,
  `CleanSheetsRatio` decimal(10,8) DEFAULT NULL,
  PRIMARY KEY (`tournament_id`),
  KEY `idx_community_snapshots_chrono` (`event_date`, `event_chrono`, `tournament_id`),
  CONSTRAINT `fk_community_stats_snapshots_tournament`
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `amiga_community_stat_facts` (
  `tournament_id` int(11) NOT NULL,
  `period_type` varchar(16) NOT NULL,
  `period_key` varchar(16) NOT NULL,
  `slice_type` varchar(32) NOT NULL,
  `slice_key` varchar(64) NOT NULL,
  `metric_key` varchar(32) NOT NULL,
  `count_basis` enum('game','participant') NOT NULL,
  `value` decimal(20,4) NOT NULL DEFAULT 0,
  PRIMARY KEY (
    `tournament_id`,
    `period_type`,
    `period_key`,
    `slice_type`,
    `slice_key`,
    `metric_key`,
    `count_basis`
  ),
  KEY `idx_community_facts_lookup` (
    `tournament_id`,
    `period_type`,
    `period_key`,
    `slice_type`,
    `slice_key`
  ),
  CONSTRAINT `fk_community_stat_facts_tournament`
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
