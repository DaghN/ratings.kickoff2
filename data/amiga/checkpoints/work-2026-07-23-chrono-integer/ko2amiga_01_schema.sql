-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: ko2amiga_work
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `tournament_format_templates`
--

DROP TABLE IF EXISTS `tournament_format_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tournament_format_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `schema_version` smallint NOT NULL DEFAULT '1',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `spec_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tournament_format_templates_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournaments`
--

DROP TABLE IF EXISTS `tournaments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tournaments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `source_id` int DEFAULT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `chrono` double DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `is_cup` tinyint(1) NOT NULL DEFAULT '0',
  `country` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `equal_teams` tinyint(1) NOT NULL DEFAULT '0',
  `player_count` smallint DEFAULT NULL,
  `format_template_id` int DEFAULT NULL,
  `format_overrides` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `has_league` tinyint(1) NOT NULL DEFAULT '0',
  `has_cup` tinyint(1) NOT NULL DEFAULT '0',
  `is_world_cup` tinyint(1) NOT NULL DEFAULT '0',
  `scoring_win_points_default` tinyint unsigned DEFAULT NULL,
  `scoring_draw_points_default` tinyint unsigned DEFAULT NULL,
  `scoring_loss_points_default` tinyint unsigned DEFAULT NULL,
  `frozen_scoring_schema_version` smallint DEFAULT NULL,
  `scoring_frozen_at` datetime DEFAULT NULL,
  `lifecycle_status` enum('draft','registration','ready','running','completed','archived','void') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `rating_finalized` tinyint(1) NOT NULL DEFAULT '0',
  `rating_finalized_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tournaments_name` (`name`),
  KEY `idx_tournaments_format_template` (`format_template_id`),
  KEY `idx_tournaments_format_flags` (`has_league`,`has_cup`),
  KEY `idx_tournaments_lifecycle_status` (`lifecycle_status`),
  KEY `idx_tournaments_rating_finalized` (`rating_finalized`),
  CONSTRAINT `fk_tournaments_format_template` FOREIGN KEY (`format_template_id`) REFERENCES `tournament_format_templates` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=608 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_players`
--

DROP TABLE IF EXISTS `amiga_players`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_players` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `country` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `display` tinyint(1) NOT NULL DEFAULT '1',
  `player_source` enum('import','live_ops') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'import',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amiga_players_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=470 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_tournament_finish_override`
--

DROP TABLE IF EXISTS `amiga_tournament_finish_override`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_tournament_finish_override` (
  `tournament_id` int NOT NULL,
  `player_id` int NOT NULL,
  `event_finish_position` smallint NOT NULL,
  PRIMARY KEY (`tournament_id`,`player_id`),
  KEY `idx_finish_override_player` (`player_id`),
  CONSTRAINT `fk_finish_override_player` FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_finish_override_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournament_entrants`
--

DROP TABLE IF EXISTS `tournament_entrants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tournament_entrants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tournament_id` int NOT NULL,
  `player_id` int NOT NULL,
  `seed_no` smallint DEFAULT NULL,
  `status` enum('registered','withdrawn','replaced') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'registered',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tournament_entrants_player` (`tournament_id`,`player_id`),
  KEY `idx_tournament_entrants_tournament_seed` (`tournament_id`,`seed_no`),
  KEY `idx_tournament_entrants_player` (`player_id`),
  CONSTRAINT `fk_tournament_entrants_player` FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tournament_entrants_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournament_stages`
--

DROP TABLE IF EXISTS `tournament_stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tournament_stages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tournament_id` int NOT NULL,
  `parent_stage_id` int DEFAULT NULL,
  `stage_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `stage_type` enum('round_robin','knockout') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `track_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sequence_no` smallint NOT NULL DEFAULT '0',
  `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `scoring_primitive` enum('league_table','knockout_tie') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `scoring_schema_version` smallint DEFAULT NULL,
  `scoring_win_points` tinyint unsigned DEFAULT NULL,
  `scoring_draw_points` tinyint unsigned DEFAULT NULL,
  `scoring_loss_points` tinyint unsigned DEFAULT NULL,
  `frozen_scoring_primitive` enum('league_table','knockout_tie') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `frozen_scoring_schema_version` smallint DEFAULT NULL,
  `frozen_scoring_win_points` tinyint unsigned DEFAULT NULL,
  `frozen_scoring_draw_points` tinyint unsigned DEFAULT NULL,
  `frozen_scoring_loss_points` tinyint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tournament_stages_key` (`tournament_id`,`stage_key`),
  KEY `idx_tournament_stages_tournament` (`tournament_id`,`sequence_no`),
  KEY `idx_tournament_stages_parent` (`parent_stage_id`),
  CONSTRAINT `fk_tournament_stages_parent` FOREIGN KEY (`parent_stage_id`) REFERENCES `tournament_stages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tournament_stages_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1376 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournament_stage_scoring_steps`
--

DROP TABLE IF EXISTS `tournament_stage_scoring_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tournament_stage_scoring_steps` (
  `stage_id` int NOT NULL,
  `sequence_no` smallint NOT NULL,
  `step` enum('points','head_to_head','goal_difference','goals_for','games_played','aggregate_goal_difference','extra_time','penalty_shootout','golden_goal') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`stage_id`,`sequence_no`),
  KEY `idx_tournament_stage_scoring_steps_stage` (`stage_id`,`sequence_no`),
  CONSTRAINT `fk_tournament_stage_scoring_steps_stage` FOREIGN KEY (`stage_id`) REFERENCES `tournament_stages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournament_stage_players`
--

DROP TABLE IF EXISTS `tournament_stage_players`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tournament_stage_players` (
  `stage_id` int NOT NULL,
  `player_id` int NOT NULL,
  `seed_no` smallint DEFAULT NULL,
  `group_key` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`stage_id`,`player_id`),
  KEY `idx_tournament_stage_players_player` (`player_id`),
  CONSTRAINT `fk_tournament_stage_players_player` FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tournament_stage_players_stage` FOREIGN KEY (`stage_id`) REFERENCES `tournament_stages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournament_fixtures`
--

DROP TABLE IF EXISTS `tournament_fixtures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tournament_fixtures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stage_id` int NOT NULL,
  `fixture_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `player_a_id` int DEFAULT NULL,
  `player_b_id` int DEFAULT NULL,
  `leg_no` smallint NOT NULL DEFAULT '1',
  `status` enum('scheduled','played','void') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'scheduled',
  `phase_label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `goals_a` smallint unsigned DEFAULT NULL,
  `goals_b` smallint unsigned DEFAULT NULL,
  `extra` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `goals_et_a` smallint unsigned DEFAULT NULL,
  `goals_et_b` smallint unsigned DEFAULT NULL,
  `pens_a` smallint unsigned DEFAULT NULL,
  `pens_b` smallint unsigned DEFAULT NULL,
  `result_recorded_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tournament_fixtures_key` (`stage_id`,`fixture_key`),
  KEY `idx_tournament_fixtures_stage` (`stage_id`,`status`),
  KEY `idx_tournament_fixtures_player_a` (`player_a_id`),
  KEY `idx_tournament_fixtures_player_b` (`player_b_id`),
  CONSTRAINT `fk_tournament_fixtures_player_a` FOREIGN KEY (`player_a_id`) REFERENCES `amiga_players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tournament_fixtures_player_b` FOREIGN KEY (`player_b_id`) REFERENCES `amiga_players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tournament_fixtures_stage` FOREIGN KEY (`stage_id`) REFERENCES `tournament_stages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20342 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_games`
--

DROP TABLE IF EXISTS `amiga_games`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_games` (
  `id` int NOT NULL AUTO_INCREMENT,
  `source_scores_id` int NOT NULL,
  `game_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `player_a_id` int NOT NULL,
  `player_b_id` int NOT NULL,
  `tournament_id` int DEFAULT NULL,
  `fixture_id` int DEFAULT NULL,
  `phase` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `goals_a` int NOT NULL,
  `goals_b` int NOT NULL,
  `extra` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `goals_et_a` smallint unsigned DEFAULT NULL,
  `goals_et_b` smallint unsigned DEFAULT NULL,
  `pens_a` smallint unsigned DEFAULT NULL,
  `pens_b` smallint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amiga_games_source_scores_id` (`source_scores_id`),
  KEY `idx_amiga_games_date` (`game_date`),
  KEY `idx_amiga_games_player_a` (`player_a_id`),
  KEY `idx_amiga_games_player_b` (`player_b_id`),
  KEY `idx_amiga_games_tournament` (`tournament_id`),
  KEY `idx_amiga_games_fixture` (`fixture_id`),
  CONSTRAINT `fk_amiga_games_fixture` FOREIGN KEY (`fixture_id`) REFERENCES `tournament_fixtures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_amiga_games_player_a` FOREIGN KEY (`player_a_id`) REFERENCES `amiga_players` (`id`),
  CONSTRAINT `fk_amiga_games_player_b` FOREIGN KEY (`player_b_id`) REFERENCES `amiga_players` (`id`),
  CONSTRAINT `fk_amiga_games_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27475 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_game_ratings`
--

DROP TABLE IF EXISTS `amiga_game_ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_game_ratings` (
  `game_id` int NOT NULL,
  `rating_a` decimal(10,6) DEFAULT NULL,
  `rating_b` decimal(10,6) DEFAULT NULL,
  `rating_difference` decimal(10,6) DEFAULT NULL,
  `expected_score_a` decimal(10,6) DEFAULT NULL,
  `expected_score_b` decimal(10,6) DEFAULT NULL,
  `actual_score` decimal(10,6) DEFAULT NULL,
  `adjustment_a` decimal(10,6) DEFAULT NULL,
  `adjustment_b` decimal(10,6) DEFAULT NULL,
  `new_rating_a` decimal(10,6) DEFAULT NULL,
  `new_rating_b` decimal(10,6) DEFAULT NULL,
  `sum_of_goals` int DEFAULT NULL,
  `goal_difference` int DEFAULT NULL,
  `winner_id` int DEFAULT NULL,
  `home_win` tinyint DEFAULT NULL,
  `draw` tinyint DEFAULT NULL,
  `away_win` tinyint DEFAULT NULL,
  `dd_player_a` tinyint DEFAULT NULL,
  `dd_player_b` tinyint DEFAULT NULL,
  `cs_player_a` tinyint DEFAULT NULL,
  `cs_player_b` tinyint DEFAULT NULL,
  PRIMARY KEY (`game_id`),
  CONSTRAINT `fk_amiga_game_ratings_game` FOREIGN KEY (`game_id`) REFERENCES `amiga_games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_player_event_snapshots`
--

DROP TABLE IF EXISTS `amiga_player_event_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_player_event_snapshots` (
  `player_id` int NOT NULL,
  `tournament_id` int NOT NULL,
  `event_date` date DEFAULT NULL,
  `event_chrono` double DEFAULT NULL,
  `tournament_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_cup` tinyint(1) NOT NULL DEFAULT '0',
  `country` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `has_league` tinyint(1) NOT NULL DEFAULT '0',
  `has_cup` tinyint(1) NOT NULL DEFAULT '0',
  `is_world_cup` tinyint(1) NOT NULL DEFAULT '0',
  `finalized_at` datetime NOT NULL,
  `event_finish_position` smallint DEFAULT NULL,
  `event_points` smallint NOT NULL DEFAULT '0',
  `games` smallint NOT NULL DEFAULT '0',
  `wins` smallint NOT NULL DEFAULT '0',
  `draws` smallint NOT NULL DEFAULT '0',
  `losses` smallint NOT NULL DEFAULT '0',
  `goals_for` smallint NOT NULL DEFAULT '0',
  `goals_against` smallint NOT NULL DEFAULT '0',
  `avg_goals_for` decimal(6,4) DEFAULT NULL,
  `avg_goals_against` decimal(6,4) DEFAULT NULL,
  `rating_before` decimal(10,6) DEFAULT NULL,
  `rating_delta` decimal(10,6) DEFAULT NULL,
  `rating_after` decimal(10,6) DEFAULT NULL,
  `performance_rating` decimal(10,6) DEFAULT NULL,
  `games_in_event` smallint NOT NULL DEFAULT '0',
  `is_winner` tinyint(1) NOT NULL DEFAULT '0',
  `best_knockout_phase` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_perfect_event` tinyint(1) NOT NULL DEFAULT '0',
  `Display` mediumint DEFAULT '0',
  `Rating` decimal(10,6) DEFAULT NULL,
  `elo_rank` smallint unsigned DEFAULT NULL,
  `NumberGames` mediumint DEFAULT NULL,
  `NumberWins` mediumint DEFAULT NULL,
  `NumberDraws` mediumint DEFAULT NULL,
  `NumberLosses` mediumint DEFAULT NULL,
  `WinRatio` decimal(5,4) DEFAULT NULL,
  `DrawRatio` decimal(5,4) DEFAULT NULL,
  `LossRatio` decimal(5,4) DEFAULT NULL,
  `GoalsFor` mediumint DEFAULT NULL,
  `GoalsAgainst` mediumint DEFAULT NULL,
  `AverageGoalsFor` decimal(6,4) DEFAULT NULL,
  `AverageGoalsAgainst` decimal(6,4) DEFAULT NULL,
  `GoalRatio` decimal(7,4) DEFAULT NULL,
  `MostGoalsScored` tinyint DEFAULT NULL,
  `LeastGoalsScored` tinyint NOT NULL DEFAULT '50',
  `MostGoalsConceded` tinyint DEFAULT NULL,
  `LeastGoalsConceded` tinyint NOT NULL DEFAULT '50',
  `BiggestWinDifference` tinyint DEFAULT NULL,
  `BiggestDrawSum` tinyint DEFAULT NULL,
  `BiggestLossDifference` tinyint DEFAULT NULL,
  `SmallestSumOfGoals` tinyint NOT NULL DEFAULT '50',
  `BiggestSumOfGoals` tinyint DEFAULT NULL,
  `DoubleDigits` mediumint DEFAULT NULL,
  `CleanSheets` mediumint DEFAULT NULL,
  `DoubleDigitsConceded` mediumint DEFAULT NULL,
  `CleanSheetsConceded` mediumint DEFAULT NULL,
  `DoubleDigitsRatio` decimal(5,4) DEFAULT NULL,
  `CleanSheetsRatio` decimal(5,4) DEFAULT NULL,
  `DoubleDigitsConcededRatio` decimal(5,4) DEFAULT NULL,
  `CleanSheetsConcededRatio` decimal(5,4) DEFAULT NULL,
  `DifferentOpponents` smallint DEFAULT NULL,
  `DifferentVictims` smallint DEFAULT NULL,
  `DoubleDigitsVictims` smallint DEFAULT NULL,
  `CleanSheetsVictims` smallint DEFAULT NULL,
  `MostGoalsConcededVictims` smallint DEFAULT NULL,
  `LeastGoalsScoredVictims` smallint DEFAULT NULL,
  `BiggestLossVictims` smallint DEFAULT NULL,
  `DifferentCulprits` smallint DEFAULT NULL,
  `DoubleDigitsCulprits` smallint DEFAULT NULL,
  `CleanSheetsCulprits` smallint DEFAULT NULL,
  `MostGoalsScoredCulprits` smallint DEFAULT NULL,
  `LeastGoalsConcededCulprits` smallint DEFAULT NULL,
  `BiggestWinCulprits` smallint DEFAULT NULL,
  `SumOfOpponentsRating` decimal(15,6) DEFAULT NULL,
  `AverageOpponentRating` decimal(7,3) DEFAULT NULL,
  `HighestRatedVictim` decimal(6,2) DEFAULT NULL,
  `LowestRatedCulprit` decimal(6,2) NOT NULL DEFAULT '5000.00',
  `CurrentRatingAscent` decimal(14,6) DEFAULT NULL,
  `BiggestRatingAscent` decimal(14,6) DEFAULT NULL,
  `CurrentRatingDescent` decimal(14,6) DEFAULT NULL,
  `BiggestRatingDescent` decimal(14,6) DEFAULT NULL,
  `LowestRating` decimal(10,6) NOT NULL DEFAULT '5000.000000',
  `lowest_rating_tournament_id` int DEFAULT NULL,
  `PeakRating` decimal(10,6) DEFAULT NULL,
  `peak_rating_tournament_id` int DEFAULT NULL,
  `WinningStreak` mediumint DEFAULT NULL,
  `DrawingStreak` mediumint DEFAULT NULL,
  `LosingStreak` mediumint DEFAULT NULL,
  `NonWinStreak` mediumint DEFAULT NULL,
  `NonDrawStreak` mediumint DEFAULT NULL,
  `NonLossStreak` mediumint DEFAULT NULL,
  `LongestWinningStreak` mediumint DEFAULT NULL,
  `LongestDrawingStreak` mediumint DEFAULT NULL,
  `LongestLosingStreak` mediumint DEFAULT NULL,
  `LongestNonWinStreak` mediumint DEFAULT NULL,
  `LongestNonDrawStreak` mediumint DEFAULT NULL,
  `LongestNonLossStreak` mediumint DEFAULT NULL,
  `ScoreStreak` mediumint NOT NULL DEFAULT '0',
  `MerchantStreak` mediumint NOT NULL DEFAULT '0',
  `ExactTenGoalStreak` mediumint NOT NULL DEFAULT '0',
  `WinMarginOneStreak` mediumint NOT NULL DEFAULT '0',
  `LossMarginOneStreak` mediumint NOT NULL DEFAULT '0',
  `LastGame` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `LastGameGameID` int DEFAULT NULL,
  `LastWinGameID` int DEFAULT NULL,
  `LastDrawGameID` int DEFAULT NULL,
  `LastLossGameID` int DEFAULT NULL,
  `MostGoalsScoredGameID` int DEFAULT NULL,
  `LeastGoalsScoredGameID` int DEFAULT NULL,
  `MostGoalsConcededGameID` int DEFAULT NULL,
  `LeastGoalsConcededGameID` int DEFAULT NULL,
  `BiggestWinGameID` int DEFAULT NULL,
  `BiggestDrawGameID` int DEFAULT NULL,
  `BiggestLossGameID` int DEFAULT NULL,
  `SmallestSumOfGoalsGameID` int DEFAULT NULL,
  `BiggestSumOfGoalsGameID` int DEFAULT NULL,
  `MostGoalsScoredVictimID` int DEFAULT NULL,
  `LeastGoalsConcededVictimID` int DEFAULT NULL,
  `BiggestWinVictimID` int DEFAULT NULL,
  `MostGoalsConcededCulpritID` int DEFAULT NULL,
  `LeastGoalsScoredCulpritID` int DEFAULT NULL,
  `BiggestLossCulpritID` int DEFAULT NULL,
  `HighestRatedVictimGameID` int DEFAULT NULL,
  `LowestRatedCulpritGameID` int DEFAULT NULL,
  `tournaments_played` int NOT NULL DEFAULT '0',
  `tournaments_won` int NOT NULL DEFAULT '0',
  `event_gold` int NOT NULL DEFAULT '0',
  `event_silver` int NOT NULL DEFAULT '0',
  `event_bronze` int NOT NULL DEFAULT '0',
  `event_podiums` int NOT NULL DEFAULT '0',
  `perfect_events` smallint NOT NULL DEFAULT '0',
  `honours_last_event_date` date DEFAULT NULL,
  `honours_last_tournament_id` int DEFAULT NULL,
  `career_best_performance_rating` decimal(10,6) DEFAULT NULL,
  `career_best_performance_tournament_id` int DEFAULT NULL,
  `peak_year_games` smallint unsigned NOT NULL DEFAULT '0',
  `peak_year_games_year` smallint unsigned DEFAULT NULL,
  `peak_year_tournaments` smallint unsigned NOT NULL DEFAULT '0',
  `peak_year_tournaments_year` smallint unsigned DEFAULT NULL,
  `countries_played_in` smallint unsigned NOT NULL DEFAULT '0',
  `opponent_countries_faced` smallint unsigned NOT NULL DEFAULT '0',
  `opponent_countries_beaten` smallint unsigned NOT NULL DEFAULT '0',
  `opponent_countries_beaten_by` smallint unsigned NOT NULL DEFAULT '0',
  `tournaments_played_last_rise_tournament_id` int DEFAULT NULL,
  `tournaments_played_last_rise_event_date` date DEFAULT NULL,
  `event_gold_last_rise_tournament_id` int DEFAULT NULL,
  `event_gold_last_rise_event_date` date DEFAULT NULL,
  `perfect_events_last_rise_tournament_id` int DEFAULT NULL,
  `perfect_events_last_rise_event_date` date DEFAULT NULL,
  `countries_played_in_last_rise_tournament_id` int DEFAULT NULL,
  `countries_played_in_last_rise_event_date` date DEFAULT NULL,
  `opponent_countries_faced_last_rise_tournament_id` int DEFAULT NULL,
  `opponent_countries_faced_last_rise_event_date` date DEFAULT NULL,
  `opponent_countries_beaten_last_rise_tournament_id` int DEFAULT NULL,
  `opponent_countries_beaten_last_rise_event_date` date DEFAULT NULL,
  `number_games_last_rise_tournament_id` int DEFAULT NULL,
  `number_games_last_rise_event_date` date DEFAULT NULL,
  `number_wins_last_rise_tournament_id` int DEFAULT NULL,
  `number_wins_last_rise_event_date` date DEFAULT NULL,
  `goals_for_last_rise_tournament_id` int DEFAULT NULL,
  `goals_for_last_rise_event_date` date DEFAULT NULL,
  `double_digits_last_rise_tournament_id` int DEFAULT NULL,
  `double_digits_last_rise_event_date` date DEFAULT NULL,
  `clean_sheets_last_rise_tournament_id` int DEFAULT NULL,
  `clean_sheets_last_rise_event_date` date DEFAULT NULL,
  `different_opponents_last_rise_tournament_id` int DEFAULT NULL,
  `different_opponents_last_rise_event_date` date DEFAULT NULL,
  `different_victims_last_rise_tournament_id` int DEFAULT NULL,
  `different_victims_last_rise_event_date` date DEFAULT NULL,
  `double_digits_victims_last_rise_tournament_id` int DEFAULT NULL,
  `double_digits_victims_last_rise_event_date` date DEFAULT NULL,
  `clean_sheets_victims_last_rise_tournament_id` int DEFAULT NULL,
  `clean_sheets_victims_last_rise_event_date` date DEFAULT NULL,
  `biggest_rating_ascent_last_rise_tournament_id` int DEFAULT NULL,
  `biggest_rating_ascent_last_rise_event_date` date DEFAULT NULL,
  PRIMARY KEY (`player_id`,`tournament_id`),
  KEY `idx_snapshots_player_chrono` (`player_id`,`event_date`,`event_chrono`,`tournament_id`),
  KEY `idx_snapshots_tournament_player` (`tournament_id`,`player_id`),
  KEY `idx_snapshots_rating_after` (`rating_after`),
  KEY `idx_snapshots_finalized_at` (`finalized_at`),
  KEY `idx_peak_rating_tournament` (`peak_rating_tournament_id`),
  KEY `idx_lowest_rating_tournament` (`lowest_rating_tournament_id`),
  CONSTRAINT `fk_snapshots_player` FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_snapshots_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_player_current`
--

DROP TABLE IF EXISTS `amiga_player_current`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_player_current` (
  `player_id` int NOT NULL,
  `last_tournament_id` int DEFAULT NULL,
  `last_event_date` date DEFAULT NULL,
  `last_finalized_at` datetime DEFAULT NULL,
  `Display` mediumint DEFAULT '0',
  `Rating` decimal(10,6) DEFAULT NULL,
  `elo_rank` smallint unsigned DEFAULT NULL,
  `peak_elo_rank` smallint unsigned DEFAULT NULL,
  `peak_elo_rank_tournament_id` int DEFAULT NULL,
  `NumberGames` mediumint DEFAULT NULL,
  `NumberWins` mediumint DEFAULT NULL,
  `NumberDraws` mediumint DEFAULT NULL,
  `NumberLosses` mediumint DEFAULT NULL,
  `WinRatio` decimal(5,4) DEFAULT NULL,
  `DrawRatio` decimal(5,4) DEFAULT NULL,
  `LossRatio` decimal(5,4) DEFAULT NULL,
  `GoalsFor` mediumint DEFAULT NULL,
  `GoalsAgainst` mediumint DEFAULT NULL,
  `AverageGoalsFor` decimal(6,4) DEFAULT NULL,
  `AverageGoalsAgainst` decimal(6,4) DEFAULT NULL,
  `GoalRatio` decimal(7,4) DEFAULT NULL,
  `MostGoalsScored` tinyint DEFAULT NULL,
  `LeastGoalsScored` tinyint NOT NULL DEFAULT '50',
  `MostGoalsConceded` tinyint DEFAULT NULL,
  `LeastGoalsConceded` tinyint NOT NULL DEFAULT '50',
  `BiggestWinDifference` tinyint DEFAULT NULL,
  `BiggestDrawSum` tinyint DEFAULT NULL,
  `BiggestLossDifference` tinyint DEFAULT NULL,
  `SmallestSumOfGoals` tinyint NOT NULL DEFAULT '50',
  `BiggestSumOfGoals` tinyint DEFAULT NULL,
  `DoubleDigits` mediumint DEFAULT NULL,
  `CleanSheets` mediumint DEFAULT NULL,
  `DoubleDigitsConceded` mediumint DEFAULT NULL,
  `CleanSheetsConceded` mediumint DEFAULT NULL,
  `DoubleDigitsRatio` decimal(5,4) DEFAULT NULL,
  `CleanSheetsRatio` decimal(5,4) DEFAULT NULL,
  `DoubleDigitsConcededRatio` decimal(5,4) DEFAULT NULL,
  `CleanSheetsConcededRatio` decimal(5,4) DEFAULT NULL,
  `DifferentOpponents` smallint DEFAULT NULL,
  `DifferentVictims` smallint DEFAULT NULL,
  `DoubleDigitsVictims` smallint DEFAULT NULL,
  `CleanSheetsVictims` smallint DEFAULT NULL,
  `MostGoalsConcededVictims` smallint DEFAULT NULL,
  `LeastGoalsScoredVictims` smallint DEFAULT NULL,
  `BiggestLossVictims` smallint DEFAULT NULL,
  `DifferentCulprits` smallint DEFAULT NULL,
  `DoubleDigitsCulprits` smallint DEFAULT NULL,
  `CleanSheetsCulprits` smallint DEFAULT NULL,
  `MostGoalsScoredCulprits` smallint DEFAULT NULL,
  `LeastGoalsConcededCulprits` smallint DEFAULT NULL,
  `BiggestWinCulprits` smallint DEFAULT NULL,
  `SumOfOpponentsRating` decimal(15,6) DEFAULT NULL,
  `AverageOpponentRating` decimal(7,3) DEFAULT NULL,
  `HighestRatedVictim` decimal(6,2) DEFAULT NULL,
  `LowestRatedCulprit` decimal(6,2) NOT NULL DEFAULT '5000.00',
  `CurrentRatingAscent` decimal(14,6) DEFAULT NULL,
  `BiggestRatingAscent` decimal(14,6) DEFAULT NULL,
  `CurrentRatingDescent` decimal(14,6) DEFAULT NULL,
  `BiggestRatingDescent` decimal(14,6) DEFAULT NULL,
  `LowestRating` decimal(10,6) NOT NULL DEFAULT '5000.000000',
  `lowest_rating_tournament_id` int DEFAULT NULL,
  `PeakRating` decimal(10,6) DEFAULT NULL,
  `peak_rating_tournament_id` int DEFAULT NULL,
  `WinningStreak` mediumint DEFAULT NULL,
  `DrawingStreak` mediumint DEFAULT NULL,
  `LosingStreak` mediumint DEFAULT NULL,
  `NonWinStreak` mediumint DEFAULT NULL,
  `NonDrawStreak` mediumint DEFAULT NULL,
  `NonLossStreak` mediumint DEFAULT NULL,
  `LongestWinningStreak` mediumint DEFAULT NULL,
  `LongestDrawingStreak` mediumint DEFAULT NULL,
  `LongestLosingStreak` mediumint DEFAULT NULL,
  `LongestNonWinStreak` mediumint DEFAULT NULL,
  `LongestNonDrawStreak` mediumint DEFAULT NULL,
  `LongestNonLossStreak` mediumint DEFAULT NULL,
  `ScoreStreak` mediumint NOT NULL DEFAULT '0',
  `MerchantStreak` mediumint NOT NULL DEFAULT '0',
  `ExactTenGoalStreak` mediumint NOT NULL DEFAULT '0',
  `WinMarginOneStreak` mediumint NOT NULL DEFAULT '0',
  `LossMarginOneStreak` mediumint NOT NULL DEFAULT '0',
  `LastGame` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `LastGameGameID` int DEFAULT NULL,
  `LastWinGameID` int DEFAULT NULL,
  `LastDrawGameID` int DEFAULT NULL,
  `LastLossGameID` int DEFAULT NULL,
  `MostGoalsScoredGameID` int DEFAULT NULL,
  `LeastGoalsScoredGameID` int DEFAULT NULL,
  `MostGoalsConcededGameID` int DEFAULT NULL,
  `LeastGoalsConcededGameID` int DEFAULT NULL,
  `BiggestWinGameID` int DEFAULT NULL,
  `BiggestDrawGameID` int DEFAULT NULL,
  `BiggestLossGameID` int DEFAULT NULL,
  `SmallestSumOfGoalsGameID` int DEFAULT NULL,
  `BiggestSumOfGoalsGameID` int DEFAULT NULL,
  `MostGoalsScoredVictimID` int DEFAULT NULL,
  `LeastGoalsConcededVictimID` int DEFAULT NULL,
  `BiggestWinVictimID` int DEFAULT NULL,
  `MostGoalsConcededCulpritID` int DEFAULT NULL,
  `LeastGoalsScoredCulpritID` int DEFAULT NULL,
  `BiggestLossCulpritID` int DEFAULT NULL,
  `HighestRatedVictimGameID` int DEFAULT NULL,
  `LowestRatedCulpritGameID` int DEFAULT NULL,
  `tournaments_played` int NOT NULL DEFAULT '0',
  `tournaments_won` int NOT NULL DEFAULT '0',
  `event_gold` int NOT NULL DEFAULT '0',
  `event_silver` int NOT NULL DEFAULT '0',
  `event_bronze` int NOT NULL DEFAULT '0',
  `event_podiums` int NOT NULL DEFAULT '0',
  `perfect_events` smallint NOT NULL DEFAULT '0',
  `career_best_performance_rating` decimal(10,6) DEFAULT NULL,
  `career_best_performance_tournament_id` int DEFAULT NULL,
  `peak_year_games` smallint unsigned NOT NULL DEFAULT '0',
  `peak_year_games_year` smallint unsigned DEFAULT NULL,
  `peak_year_tournaments` smallint unsigned NOT NULL DEFAULT '0',
  `peak_year_tournaments_year` smallint unsigned DEFAULT NULL,
  `countries_played_in` smallint unsigned NOT NULL DEFAULT '0',
  `opponent_countries_faced` smallint unsigned NOT NULL DEFAULT '0',
  `opponent_countries_beaten` smallint unsigned NOT NULL DEFAULT '0',
  `opponent_countries_beaten_by` smallint unsigned NOT NULL DEFAULT '0',
  `tournaments_played_last_rise_tournament_id` int DEFAULT NULL,
  `tournaments_played_last_rise_event_date` date DEFAULT NULL,
  `event_gold_last_rise_tournament_id` int DEFAULT NULL,
  `event_gold_last_rise_event_date` date DEFAULT NULL,
  `perfect_events_last_rise_tournament_id` int DEFAULT NULL,
  `perfect_events_last_rise_event_date` date DEFAULT NULL,
  `countries_played_in_last_rise_tournament_id` int DEFAULT NULL,
  `countries_played_in_last_rise_event_date` date DEFAULT NULL,
  `opponent_countries_faced_last_rise_tournament_id` int DEFAULT NULL,
  `opponent_countries_faced_last_rise_event_date` date DEFAULT NULL,
  `opponent_countries_beaten_last_rise_tournament_id` int DEFAULT NULL,
  `opponent_countries_beaten_last_rise_event_date` date DEFAULT NULL,
  `number_games_last_rise_tournament_id` int DEFAULT NULL,
  `number_games_last_rise_event_date` date DEFAULT NULL,
  `number_wins_last_rise_tournament_id` int DEFAULT NULL,
  `number_wins_last_rise_event_date` date DEFAULT NULL,
  `goals_for_last_rise_tournament_id` int DEFAULT NULL,
  `goals_for_last_rise_event_date` date DEFAULT NULL,
  `double_digits_last_rise_tournament_id` int DEFAULT NULL,
  `double_digits_last_rise_event_date` date DEFAULT NULL,
  `clean_sheets_last_rise_tournament_id` int DEFAULT NULL,
  `clean_sheets_last_rise_event_date` date DEFAULT NULL,
  `different_opponents_last_rise_tournament_id` int DEFAULT NULL,
  `different_opponents_last_rise_event_date` date DEFAULT NULL,
  `different_victims_last_rise_tournament_id` int DEFAULT NULL,
  `different_victims_last_rise_event_date` date DEFAULT NULL,
  `double_digits_victims_last_rise_tournament_id` int DEFAULT NULL,
  `double_digits_victims_last_rise_event_date` date DEFAULT NULL,
  `clean_sheets_victims_last_rise_tournament_id` int DEFAULT NULL,
  `clean_sheets_victims_last_rise_event_date` date DEFAULT NULL,
  `biggest_rating_ascent_last_rise_tournament_id` int DEFAULT NULL,
  `biggest_rating_ascent_last_rise_event_date` date DEFAULT NULL,
  PRIMARY KEY (`player_id`),
  KEY `idx_player_current_rating` (`Rating`),
  KEY `idx_player_current_number_games` (`NumberGames`),
  KEY `idx_player_current_last_tournament` (`last_tournament_id`),
  KEY `idx_peak_rating_tournament` (`peak_rating_tournament_id`),
  KEY `idx_lowest_rating_tournament` (`lowest_rating_tournament_id`),
  CONSTRAINT `fk_player_current_player` FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_player_elo_rank_at_event`
--

DROP TABLE IF EXISTS `amiga_player_elo_rank_at_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_player_elo_rank_at_event` (
  `player_id` int NOT NULL,
  `tournament_id` int NOT NULL,
  `event_date` date DEFAULT NULL,
  `event_chrono` double DEFAULT NULL,
  `elo_rank` smallint unsigned NOT NULL,
  `peak_elo_rank` smallint unsigned DEFAULT NULL,
  `peak_elo_rank_tournament_id` int DEFAULT NULL,
  PRIMARY KEY (`player_id`,`tournament_id`),
  KEY `idx_elo_rank_tournament` (`tournament_id`),
  KEY `idx_elo_rank_player_chrono` (`player_id`,`event_date`,`event_chrono`,`tournament_id`),
  KEY `idx_peak_elo_rank_tournament` (`peak_elo_rank_tournament_id`),
  CONSTRAINT `fk_elo_rank_player` FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_elo_rank_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_player_inverse_count_at_event`
--

DROP TABLE IF EXISTS `amiga_player_inverse_count_at_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_player_inverse_count_at_event` (
  `player_id` int NOT NULL,
  `tournament_id` int NOT NULL,
  `metric` enum('mgs_culprits','bw_culprits','mgc_victims','bl_victims') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `value_after` smallint unsigned NOT NULL,
  `event_date` date DEFAULT NULL,
  `event_chrono` double DEFAULT NULL,
  PRIMARY KEY (`player_id`,`tournament_id`,`metric`),
  KEY `idx_inv_count_tournament` (`tournament_id`),
  KEY `idx_inv_count_metric_chrono` (`metric`,`event_date`,`event_chrono`,`tournament_id`),
  KEY `idx_inv_count_player_metric_chrono` (`player_id`,`metric`,`event_date`,`event_chrono`,`tournament_id`),
  CONSTRAINT `fk_inv_count_player` FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_count_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_player_matchup_at_event`
--

DROP TABLE IF EXISTS `amiga_player_matchup_at_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_player_matchup_at_event` (
  `player_id` int NOT NULL,
  `opponent_id` int NOT NULL,
  `as_of_tournament_id` int NOT NULL,
  `event_date` date NOT NULL,
  `event_chrono` double NOT NULL,
  `games` smallint unsigned NOT NULL DEFAULT '0',
  `wins` smallint unsigned NOT NULL DEFAULT '0',
  `draws` smallint unsigned NOT NULL DEFAULT '0',
  `losses` smallint unsigned NOT NULL DEFAULT '0',
  `goals_for` smallint unsigned NOT NULL DEFAULT '0',
  `goals_against` smallint unsigned NOT NULL DEFAULT '0',
  `max_goals_for` smallint unsigned NOT NULL DEFAULT '0',
  `max_goals_against` smallint unsigned NOT NULL DEFAULT '0',
  `min_goals_for` smallint unsigned NOT NULL DEFAULT '0',
  `min_goals_against` smallint unsigned NOT NULL DEFAULT '0',
  `max_win_margin` smallint unsigned DEFAULT NULL,
  `max_loss_margin` smallint unsigned DEFAULT NULL,
  `max_draw_goals` smallint unsigned DEFAULT NULL,
  `max_goal_sum` smallint unsigned NOT NULL DEFAULT '0',
  `min_goal_sum` smallint unsigned NOT NULL DEFAULT '0',
  `dd_wins` smallint unsigned NOT NULL DEFAULT '0',
  `dd_losses` smallint unsigned NOT NULL DEFAULT '0',
  `cs_wins` smallint unsigned NOT NULL DEFAULT '0',
  `cs_losses` smallint unsigned NOT NULL DEFAULT '0',
  `performance_rating` decimal(10,6) DEFAULT NULL,
  PRIMARY KEY (`player_id`,`opponent_id`,`as_of_tournament_id`),
  KEY `idx_matchup_at_event_player_chrono` (`player_id`,`event_date`,`event_chrono`,`as_of_tournament_id`),
  KEY `idx_matchup_at_event_tournament` (`as_of_tournament_id`,`player_id`),
  KEY `fk_matchup_at_event_opponent` (`opponent_id`),
  CONSTRAINT `fk_matchup_at_event_opponent` FOREIGN KEY (`opponent_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_matchup_at_event_player` FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_matchup_at_event_tournament` FOREIGN KEY (`as_of_tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_player_matchup_summary`
--

DROP TABLE IF EXISTS `amiga_player_matchup_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_player_matchup_summary` (
  `player_id` int NOT NULL,
  `opponent_id` int NOT NULL,
  `games` smallint unsigned NOT NULL DEFAULT '0',
  `wins` smallint unsigned NOT NULL DEFAULT '0',
  `draws` smallint unsigned NOT NULL DEFAULT '0',
  `losses` smallint unsigned NOT NULL DEFAULT '0',
  `goals_for` smallint unsigned NOT NULL DEFAULT '0',
  `goals_against` smallint unsigned NOT NULL DEFAULT '0',
  `max_goals_for` smallint unsigned NOT NULL DEFAULT '0',
  `max_goals_against` smallint unsigned NOT NULL DEFAULT '0',
  `min_goals_for` smallint unsigned NOT NULL DEFAULT '0',
  `min_goals_against` smallint unsigned NOT NULL DEFAULT '0',
  `max_win_margin` smallint unsigned DEFAULT NULL,
  `max_loss_margin` smallint unsigned DEFAULT NULL,
  `max_draw_goals` smallint unsigned DEFAULT NULL,
  `max_goal_sum` smallint unsigned NOT NULL DEFAULT '0',
  `min_goal_sum` smallint unsigned NOT NULL DEFAULT '0',
  `dd_wins` smallint unsigned NOT NULL DEFAULT '0',
  `dd_losses` smallint unsigned NOT NULL DEFAULT '0',
  `cs_wins` smallint unsigned NOT NULL DEFAULT '0',
  `cs_losses` smallint unsigned NOT NULL DEFAULT '0',
  `performance_rating` decimal(10,6) DEFAULT NULL,
  PRIMARY KEY (`player_id`,`opponent_id`),
  KEY `idx_matchup_opponent` (`opponent_id`,`player_id`),
  CONSTRAINT `fk_matchup_opponent` FOREIGN KEY (`opponent_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_matchup_player` FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_tournament_standings`
--

DROP TABLE IF EXISTS `amiga_tournament_standings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_tournament_standings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tournament_id` int NOT NULL,
  `player_id` int NOT NULL,
  `scope_type` enum('league','knockout') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'league',
  `scope_key` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `stage_id` int DEFAULT NULL,
  `position` smallint NOT NULL,
  `games` smallint NOT NULL DEFAULT '0',
  `wins` smallint NOT NULL DEFAULT '0',
  `draws` smallint NOT NULL DEFAULT '0',
  `losses` smallint NOT NULL DEFAULT '0',
  `goals_for` smallint NOT NULL DEFAULT '0',
  `goals_against` smallint NOT NULL DEFAULT '0',
  `points` smallint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amiga_tournament_standings_scope_player` (`tournament_id`,`scope_type`,`scope_key`,`player_id`),
  KEY `idx_amiga_tournament_standings_tournament` (`tournament_id`),
  KEY `idx_amiga_tournament_standings_lookup` (`tournament_id`,`scope_type`,`scope_key`,`position`),
  KEY `fk_amiga_tournament_standings_player` (`player_id`),
  KEY `idx_amiga_tournament_standings_stage` (`tournament_id`,`stage_id`),
  KEY `fk_amiga_tournament_standings_stage` (`stage_id`),
  CONSTRAINT `fk_amiga_tournament_standings_player` FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_amiga_tournament_standings_stage` FOREIGN KEY (`stage_id`) REFERENCES `tournament_stages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_amiga_tournament_standings_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=189390 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_tournament_catalog_stats`
--

DROP TABLE IF EXISTS `amiga_tournament_catalog_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_tournament_catalog_stats` (
  `tournament_id` int NOT NULL,
  `game_count` int NOT NULL DEFAULT '0',
  `standing_players` int NOT NULL DEFAULT '0',
  `standing_rows` int NOT NULL DEFAULT '0',
  `league_scopes` int NOT NULL DEFAULT '0',
  `knockout_ties` int NOT NULL DEFAULT '0',
  `has_perfect_participant` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`tournament_id`),
  CONSTRAINT `fk_amiga_tournament_catalog_stats_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_generalstats`
--

DROP TABLE IF EXISTS `amiga_generalstats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_generalstats` (
  `id` tinyint NOT NULL,
  `MostGamesPlayed` int DEFAULT NULL,
  `MostWins` int DEFAULT NULL,
  `MostGoalsScored` int DEFAULT NULL,
  `MostGoalsScoredInOneGame` int DEFAULT NULL,
  `BiggestWinDifference` int DEFAULT NULL,
  `BiggestDrawSum` int DEFAULT NULL,
  `BiggestSumOfGoals` int DEFAULT NULL,
  `MostDoubleDigits` int DEFAULT NULL,
  `MostCleanSheets` int DEFAULT NULL,
  `MostDifferentOpponents` int DEFAULT NULL,
  `MostDifferentVictims` int DEFAULT NULL,
  `MostDoubleDigitsVictims` int DEFAULT NULL,
  `MostCleanSheetsVictims` int DEFAULT NULL,
  `BiggestRatingAscent` decimal(10,5) DEFAULT NULL,
  `MostGamesPlayedID` int DEFAULT NULL,
  `MostWinsID` int DEFAULT NULL,
  `MostGoalsScoredID` int DEFAULT NULL,
  `MostGoalsScoredInOneGameID` int DEFAULT NULL,
  `BiggestWinDifferenceID` int DEFAULT NULL,
  `BiggestDrawSumIDA` int DEFAULT NULL,
  `BiggestDrawSumIDB` int DEFAULT NULL,
  `BiggestSumOfGoalsIDA` int DEFAULT NULL,
  `BiggestSumOfGoalsIDB` int DEFAULT NULL,
  `MostDoubleDigitsID` int DEFAULT NULL,
  `MostCleanSheetsID` int DEFAULT NULL,
  `MostDifferentOpponentsID` int DEFAULT NULL,
  `MostDifferentVictimsID` int DEFAULT NULL,
  `MostDoubleDigitsVictimsID` int DEFAULT NULL,
  `MostCleanSheetsVictimsID` int DEFAULT NULL,
  `BiggestRatingAscentID` int DEFAULT NULL,
  `MostGamesPlayedName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWinsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostGoalsScoredName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostGoalsScoredInOneGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestWinDifferenceName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestDrawSumNameA` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestDrawSumNameB` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestSumOfGoalsNameA` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestSumOfGoalsNameB` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostDoubleDigitsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostCleanSheetsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostDifferentOpponentsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostDifferentVictimsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostDoubleDigitsVictimsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostCleanSheetsVictimsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestRatingAscentName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostGamesPlayedDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostWinsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostGoalsScoredDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostGoalsScoredInOneGameDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `BiggestWinDifferenceDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `BiggestDrawSumDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `BiggestSumOfGoalsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostDoubleDigitsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostCleanSheetsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostDifferentOpponentsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostDifferentVictimsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostDoubleDigitsVictimsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostCleanSheetsVictimsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `BiggestRatingAscentDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostGoalsScoredInOneGameGameID` int DEFAULT NULL,
  `BiggestWinDifferenceGameID` int DEFAULT NULL,
  `BiggestDrawSumGameID` int DEFAULT NULL,
  `BiggestSumOfGoalsGameID` int DEFAULT NULL,
  `BiggestWinRatio` decimal(5,4) DEFAULT NULL,
  `BiggestWinRatioID` int DEFAULT NULL,
  `BiggestWinRatioName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestGoalsForAverage` decimal(6,4) DEFAULT NULL,
  `BiggestGoalsForAverageID` int DEFAULT NULL,
  `BiggestGoalsForAverageName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `SmallestGoalsAgainstAverage` decimal(6,4) DEFAULT NULL,
  `SmallestGoalsAgainstAverageID` int DEFAULT NULL,
  `SmallestGoalsAgainstAverageName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestGoalRatio` decimal(7,4) DEFAULT NULL,
  `BiggestGoalRatioID` int DEFAULT NULL,
  `BiggestGoalRatioName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestDoubleDigitsRatio` decimal(5,4) DEFAULT NULL,
  `BiggestDoubleDigitsRatioID` int DEFAULT NULL,
  `BiggestDoubleDigitsRatioName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestCleanSheetsRatio` decimal(5,4) DEFAULT NULL,
  `BiggestCleanSheetsRatioID` int DEFAULT NULL,
  `BiggestCleanSheetsRatioName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostGamesInOneYear` int DEFAULT NULL,
  `MostGamesInOneYearID` int DEFAULT NULL,
  `MostGamesInOneYearName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostGamesInOneYearDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostTournamentsInOneYear` int DEFAULT NULL,
  `MostTournamentsInOneYearID` int DEFAULT NULL,
  `MostTournamentsInOneYearName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostTournamentsInOneYearDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostTournamentsPlayed` int DEFAULT NULL,
  `MostTournamentsPlayedID` int DEFAULT NULL,
  `MostTournamentsPlayedName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostTournamentsPlayedDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostTournamentWins` int DEFAULT NULL,
  `MostTournamentWinsID` int DEFAULT NULL,
  `MostTournamentWinsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostTournamentWinsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostPerfectEvents` int DEFAULT NULL,
  `MostPerfectEventsID` int DEFAULT NULL,
  `MostPerfectEventsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostPerfectEventsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostCountriesPlayedIn` int DEFAULT NULL,
  `MostCountriesPlayedInID` int DEFAULT NULL,
  `MostCountriesPlayedInName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostCountriesPlayedInDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostOpponentCountriesFaced` int DEFAULT NULL,
  `MostOpponentCountriesFacedID` int DEFAULT NULL,
  `MostOpponentCountriesFacedName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostOpponentCountriesFacedDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostOpponentCountriesBeaten` int DEFAULT NULL,
  `MostOpponentCountriesBeatenID` int DEFAULT NULL,
  `MostOpponentCountriesBeatenName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostOpponentCountriesBeatenDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_realm_snapshots`
--

DROP TABLE IF EXISTS `amiga_realm_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_realm_snapshots` (
  `tournament_id` int NOT NULL,
  `event_date` date DEFAULT NULL,
  `event_chrono` double DEFAULT NULL,
  `tournament_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `finalized_at` datetime NOT NULL,
  `MostGamesPlayed` int DEFAULT NULL,
  `MostWins` int DEFAULT NULL,
  `MostGoalsScored` int DEFAULT NULL,
  `MostGoalsScoredInOneGame` int DEFAULT NULL,
  `BiggestWinDifference` int DEFAULT NULL,
  `BiggestDrawSum` int DEFAULT NULL,
  `BiggestSumOfGoals` int DEFAULT NULL,
  `MostDoubleDigits` int DEFAULT NULL,
  `MostCleanSheets` int DEFAULT NULL,
  `MostDifferentOpponents` int DEFAULT NULL,
  `MostDifferentVictims` int DEFAULT NULL,
  `MostDoubleDigitsVictims` int DEFAULT NULL,
  `MostCleanSheetsVictims` int DEFAULT NULL,
  `BiggestRatingAscent` decimal(10,5) DEFAULT NULL,
  `MostGamesPlayedID` int DEFAULT NULL,
  `MostWinsID` int DEFAULT NULL,
  `MostGoalsScoredID` int DEFAULT NULL,
  `MostGoalsScoredInOneGameID` int DEFAULT NULL,
  `BiggestWinDifferenceID` int DEFAULT NULL,
  `BiggestDrawSumIDA` int DEFAULT NULL,
  `BiggestDrawSumIDB` int DEFAULT NULL,
  `BiggestSumOfGoalsIDA` int DEFAULT NULL,
  `BiggestSumOfGoalsIDB` int DEFAULT NULL,
  `MostDoubleDigitsID` int DEFAULT NULL,
  `MostCleanSheetsID` int DEFAULT NULL,
  `MostDifferentOpponentsID` int DEFAULT NULL,
  `MostDifferentVictimsID` int DEFAULT NULL,
  `MostDoubleDigitsVictimsID` int DEFAULT NULL,
  `MostCleanSheetsVictimsID` int DEFAULT NULL,
  `BiggestRatingAscentID` int DEFAULT NULL,
  `MostGamesPlayedName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWinsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostGoalsScoredName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostGoalsScoredInOneGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestWinDifferenceName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestDrawSumNameA` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestDrawSumNameB` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestSumOfGoalsNameA` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestSumOfGoalsNameB` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostDoubleDigitsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostCleanSheetsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostDifferentOpponentsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostDifferentVictimsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostDoubleDigitsVictimsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostCleanSheetsVictimsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestRatingAscentName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostGamesPlayedDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostWinsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostGoalsScoredDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostGoalsScoredInOneGameDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `BiggestWinDifferenceDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `BiggestDrawSumDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `BiggestSumOfGoalsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostDoubleDigitsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostCleanSheetsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostDifferentOpponentsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostDifferentVictimsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostDoubleDigitsVictimsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostCleanSheetsVictimsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `BiggestRatingAscentDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostGoalsScoredInOneGameGameID` int DEFAULT NULL,
  `BiggestWinDifferenceGameID` int DEFAULT NULL,
  `BiggestDrawSumGameID` int DEFAULT NULL,
  `BiggestSumOfGoalsGameID` int DEFAULT NULL,
  `BiggestWinRatio` decimal(5,4) DEFAULT NULL,
  `BiggestWinRatioID` int DEFAULT NULL,
  `BiggestWinRatioName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestGoalsForAverage` decimal(6,4) DEFAULT NULL,
  `BiggestGoalsForAverageID` int DEFAULT NULL,
  `BiggestGoalsForAverageName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `SmallestGoalsAgainstAverage` decimal(6,4) DEFAULT NULL,
  `SmallestGoalsAgainstAverageID` int DEFAULT NULL,
  `SmallestGoalsAgainstAverageName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestGoalRatio` decimal(7,4) DEFAULT NULL,
  `BiggestGoalRatioID` int DEFAULT NULL,
  `BiggestGoalRatioName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestDoubleDigitsRatio` decimal(5,4) DEFAULT NULL,
  `BiggestDoubleDigitsRatioID` int DEFAULT NULL,
  `BiggestDoubleDigitsRatioName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestCleanSheetsRatio` decimal(5,4) DEFAULT NULL,
  `BiggestCleanSheetsRatioID` int DEFAULT NULL,
  `BiggestCleanSheetsRatioName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostGamesInOneYear` int DEFAULT NULL,
  `MostGamesInOneYearID` int DEFAULT NULL,
  `MostGamesInOneYearName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostGamesInOneYearDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostTournamentsInOneYear` int DEFAULT NULL,
  `MostTournamentsInOneYearID` int DEFAULT NULL,
  `MostTournamentsInOneYearName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostTournamentsInOneYearDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostTournamentsPlayed` int DEFAULT NULL,
  `MostTournamentsPlayedID` int DEFAULT NULL,
  `MostTournamentsPlayedName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostTournamentsPlayedDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostTournamentWins` int DEFAULT NULL,
  `MostTournamentWinsID` int DEFAULT NULL,
  `MostTournamentWinsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostTournamentWinsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostPerfectEvents` int DEFAULT NULL,
  `MostPerfectEventsID` int DEFAULT NULL,
  `MostPerfectEventsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostPerfectEventsDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostCountriesPlayedIn` int DEFAULT NULL,
  `MostCountriesPlayedInID` int DEFAULT NULL,
  `MostCountriesPlayedInName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostCountriesPlayedInDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostOpponentCountriesFaced` int DEFAULT NULL,
  `MostOpponentCountriesFacedID` int DEFAULT NULL,
  `MostOpponentCountriesFacedName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostOpponentCountriesFacedDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `MostOpponentCountriesBeaten` int DEFAULT NULL,
  `MostOpponentCountriesBeatenID` int DEFAULT NULL,
  `MostOpponentCountriesBeatenName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostOpponentCountriesBeatenDate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`tournament_id`),
  KEY `idx_realm_snapshots_chrono` (`event_date`,`event_chrono`,`tournament_id`),
  CONSTRAINT `fk_realm_snapshots_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_community_stats`
--

DROP TABLE IF EXISTS `amiga_community_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_community_stats` (
  `id` tinyint NOT NULL,
  `NumberOfPlayers` int DEFAULT NULL,
  `DifferentOpponentsAverage` decimal(10,5) DEFAULT NULL,
  `GamesPlayed` int DEFAULT NULL,
  `GamesPlayedAverage` decimal(10,3) DEFAULT NULL,
  `NumberOfDecidedGames` int DEFAULT NULL,
  `NumberOfDraws` int DEFAULT NULL,
  `DecidedGamesRatio` decimal(10,8) DEFAULT NULL,
  `DrawsRatio` decimal(10,8) DEFAULT NULL,
  `GoalsScored` int DEFAULT NULL,
  `GoalsPerGameAverage` decimal(10,7) DEFAULT NULL,
  `DoubleDigits` int DEFAULT NULL,
  `CleanSheets` int DEFAULT NULL,
  `DoubleDigitsRatio` decimal(10,8) DEFAULT NULL,
  `CleanSheetsRatio` decimal(10,8) DEFAULT NULL,
  `TournamentsFinalized` int DEFAULT NULL,
  `DistinctHostCountries` int DEFAULT NULL,
  `WcGamesPlayed` int DEFAULT NULL,
  `DistinctOpponentPairs` int DEFAULT NULL,
  `PlayersDebuted` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_community_stats_snapshots`
--

DROP TABLE IF EXISTS `amiga_community_stats_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_community_stats_snapshots` (
  `tournament_id` int NOT NULL,
  `event_date` date DEFAULT NULL,
  `event_chrono` double DEFAULT NULL,
  `tournament_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `finalized_at` datetime NOT NULL,
  `NumberOfPlayers` int DEFAULT NULL,
  `DifferentOpponentsAverage` decimal(10,5) DEFAULT NULL,
  `GamesPlayed` int DEFAULT NULL,
  `GamesPlayedAverage` decimal(10,3) DEFAULT NULL,
  `NumberOfDecidedGames` int DEFAULT NULL,
  `NumberOfDraws` int DEFAULT NULL,
  `DecidedGamesRatio` decimal(10,8) DEFAULT NULL,
  `DrawsRatio` decimal(10,8) DEFAULT NULL,
  `GoalsScored` int DEFAULT NULL,
  `GoalsPerGameAverage` decimal(10,7) DEFAULT NULL,
  `DoubleDigits` int DEFAULT NULL,
  `CleanSheets` int DEFAULT NULL,
  `DoubleDigitsRatio` decimal(10,8) DEFAULT NULL,
  `CleanSheetsRatio` decimal(10,8) DEFAULT NULL,
  `TournamentsFinalized` int DEFAULT NULL,
  `DistinctHostCountries` int DEFAULT NULL,
  `WcGamesPlayed` int DEFAULT NULL,
  `DistinctOpponentPairs` int DEFAULT NULL,
  `PlayersDebuted` int DEFAULT NULL,
  PRIMARY KEY (`tournament_id`),
  KEY `idx_community_snapshots_chrono` (`event_date`,`event_chrono`,`tournament_id`),
  CONSTRAINT `fk_community_stats_snapshots_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_community_stat_facts`
--

DROP TABLE IF EXISTS `amiga_community_stat_facts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_community_stat_facts` (
  `tournament_id` int NOT NULL,
  `period_type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `period_key` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `slice_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `slice_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `metric_key` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `count_basis` enum('game','participant') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `value` decimal(20,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`tournament_id`,`period_type`,`period_key`,`slice_type`,`slice_key`,`metric_key`,`count_basis`),
  KEY `idx_community_facts_lookup` (`tournament_id`,`period_type`,`period_key`,`slice_type`,`slice_key`),
  KEY `idx_community_facts_metric_period` (`period_type`,`slice_type`,`slice_key`,`metric_key`,`count_basis`,`period_key`,`tournament_id`),
  CONSTRAINT `fk_community_stat_facts_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_world_cup_stats`
--

DROP TABLE IF EXISTS `amiga_world_cup_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_world_cup_stats` (
  `tournament_id` int NOT NULL,
  `tournament_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `calendar_year` int DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `event_chrono` double DEFAULT NULL,
  `host_country` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `host_city` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rated_games` int NOT NULL DEFAULT '0',
  `decided_games` int NOT NULL DEFAULT '0',
  `draws` int NOT NULL DEFAULT '0',
  `goals` int NOT NULL DEFAULT '0',
  `double_digit_slots` int NOT NULL DEFAULT '0',
  `clean_sheet_slots` int NOT NULL DEFAULT '0',
  `high_scoring_games` int NOT NULL DEFAULT '0',
  `low_scoring_games` int NOT NULL DEFAULT '0',
  `blowout_games` int NOT NULL DEFAULT '0',
  `knockout_games` int NOT NULL DEFAULT '0',
  `group_games` int NOT NULL DEFAULT '0',
  `goals_per_game` decimal(12,7) DEFAULT NULL,
  `draw_rate` decimal(10,8) DEFAULT NULL,
  `decided_rate` decimal(10,8) DEFAULT NULL,
  `double_digit_rate` decimal(10,8) DEFAULT NULL,
  `clean_sheet_rate` decimal(10,8) DEFAULT NULL,
  `high_scoring_rate` decimal(10,8) DEFAULT NULL,
  `low_scoring_rate` decimal(10,8) DEFAULT NULL,
  `blowout_rate` decimal(10,8) DEFAULT NULL,
  `distinct_players` int NOT NULL DEFAULT '0',
  `distinct_player_nationalities` int NOT NULL DEFAULT '0',
  `max_games_one_player` int NOT NULL DEFAULT '0',
  `first_time_wc_players` int NOT NULL DEFAULT '0',
  `distinct_opponent_pairs` int NOT NULL DEFAULT '0',
  `avg_games_per_player` decimal(10,3) DEFAULT NULL,
  `avg_opponents_per_player` decimal(10,3) DEFAULT NULL,
  `distinct_host_country_players` int NOT NULL DEFAULT '0',
  `distinct_guest_players` int NOT NULL DEFAULT '0',
  `guest_player_share` decimal(10,8) DEFAULT NULL,
  `distinct_opponent_countries_pairs` int NOT NULL DEFAULT '0',
  `international_games` int NOT NULL DEFAULT '0',
  `international_game_share` decimal(10,8) DEFAULT NULL,
  `highest_goal_sum` int DEFAULT NULL,
  `highest_goal_sum_game_id` int DEFAULT NULL,
  `lowest_goal_sum` int DEFAULT NULL,
  `lowest_goal_sum_game_id` int DEFAULT NULL,
  `biggest_margin` int DEFAULT NULL,
  `biggest_margin_game_id` int DEFAULT NULL,
  `highest_scoring_draw_sum` int DEFAULT NULL,
  `highest_scoring_draw_game_id` int DEFAULT NULL,
  `most_goals_one_player_game` int DEFAULT NULL,
  `most_goals_one_player_game_id` int DEFAULT NULL,
  `gold_player_id` int DEFAULT NULL,
  `silver_player_id` int DEFAULT NULL,
  `bronze_player_id` int DEFAULT NULL,
  `champion_game_count` int DEFAULT NULL,
  `share_of_year_games` decimal(10,8) DEFAULT NULL,
  `finalized_at` datetime NOT NULL,
  PRIMARY KEY (`tournament_id`),
  KEY `idx_wc_stats_year` (`calendar_year`,`event_date`,`tournament_id`),
  CONSTRAINT `fk_wc_stats_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_player_slice_totals`
--

DROP TABLE IF EXISTS `amiga_player_slice_totals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_player_slice_totals` (
  `player_id` int NOT NULL,
  `slice_key` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tournaments_played` int NOT NULL DEFAULT '0',
  `gold` int NOT NULL DEFAULT '0',
  `silver` int NOT NULL DEFAULT '0',
  `bronze` int NOT NULL DEFAULT '0',
  `podiums` int NOT NULL DEFAULT '0',
  `games` int NOT NULL DEFAULT '0',
  `wins` int NOT NULL DEFAULT '0',
  `draws` int NOT NULL DEFAULT '0',
  `losses` int NOT NULL DEFAULT '0',
  `goals_for` int NOT NULL DEFAULT '0',
  `goals_against` int NOT NULL DEFAULT '0',
  `points` int NOT NULL DEFAULT '0',
  `goal_ratio` decimal(10,8) DEFAULT NULL,
  `most_goals_scored` tinyint NOT NULL DEFAULT '0',
  `most_goals_conceded` tinyint NOT NULL DEFAULT '0',
  `biggest_win_difference` tinyint NOT NULL DEFAULT '0',
  `biggest_loss_difference` tinyint NOT NULL DEFAULT '0',
  `biggest_sum_of_goals` tinyint NOT NULL DEFAULT '0',
  `biggest_draw_sum` tinyint NOT NULL DEFAULT '0',
  `double_digits` mediumint NOT NULL DEFAULT '0',
  `clean_sheets` mediumint NOT NULL DEFAULT '0',
  `double_digits_ratio` decimal(5,4) DEFAULT NULL,
  `clean_sheets_ratio` decimal(5,4) DEFAULT NULL,
  `double_digits_conceded` mediumint NOT NULL DEFAULT '0',
  `clean_sheets_conceded` mediumint NOT NULL DEFAULT '0',
  `double_digits_conceded_ratio` decimal(5,4) DEFAULT NULL,
  `clean_sheets_conceded_ratio` decimal(5,4) DEFAULT NULL,
  `opponent_countries_faced` smallint unsigned NOT NULL DEFAULT '0',
  `opponent_countries_beaten` smallint unsigned NOT NULL DEFAULT '0',
  `opponent_countries_beaten_by` smallint unsigned NOT NULL DEFAULT '0',
  `different_opponents` smallint NOT NULL DEFAULT '0',
  `different_victims` smallint NOT NULL DEFAULT '0',
  `double_digits_victims` smallint NOT NULL DEFAULT '0',
  `clean_sheets_victims` smallint NOT NULL DEFAULT '0',
  `different_culprits` smallint NOT NULL DEFAULT '0',
  `double_digits_culprits` smallint NOT NULL DEFAULT '0',
  `clean_sheets_culprits` smallint NOT NULL DEFAULT '0',
  `best_attack_awards` int NOT NULL DEFAULT '0',
  `best_defense_awards` int NOT NULL DEFAULT '0',
  `best_single_wc_gf_per_game` decimal(6,4) DEFAULT NULL,
  `best_single_wc_gf_per_game_tournament_id` int DEFAULT NULL,
  `best_single_wc_ga_per_game` decimal(6,4) DEFAULT NULL,
  `best_single_wc_ga_per_game_tournament_id` int DEFAULT NULL,
  `tournaments_played_last_rise_tournament_id` int DEFAULT NULL,
  `tournaments_played_last_rise_event_date` date DEFAULT NULL,
  PRIMARY KEY (`player_id`,`slice_key`),
  KEY `idx_slice_totals_key_tournaments` (`slice_key`,`tournaments_played`),
  KEY `idx_slice_totals_key_gold` (`slice_key`,`gold`),
  KEY `idx_slice_totals_key_points` (`slice_key`,`points`),
  CONSTRAINT `fk_slice_totals_player` FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_player_slice_at_event`
--

DROP TABLE IF EXISTS `amiga_player_slice_at_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_player_slice_at_event` (
  `player_id` int NOT NULL,
  `slice_key` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `as_of_tournament_id` int NOT NULL,
  `event_date` date DEFAULT NULL,
  `event_chrono` double DEFAULT NULL,
  `tournaments_played` int NOT NULL DEFAULT '0',
  `gold` int NOT NULL DEFAULT '0',
  `silver` int NOT NULL DEFAULT '0',
  `bronze` int NOT NULL DEFAULT '0',
  `podiums` int NOT NULL DEFAULT '0',
  `games` int NOT NULL DEFAULT '0',
  `wins` int NOT NULL DEFAULT '0',
  `draws` int NOT NULL DEFAULT '0',
  `losses` int NOT NULL DEFAULT '0',
  `goals_for` int NOT NULL DEFAULT '0',
  `goals_against` int NOT NULL DEFAULT '0',
  `points` int NOT NULL DEFAULT '0',
  `goal_ratio` decimal(10,8) DEFAULT NULL,
  `most_goals_scored` tinyint NOT NULL DEFAULT '0',
  `most_goals_conceded` tinyint NOT NULL DEFAULT '0',
  `biggest_win_difference` tinyint NOT NULL DEFAULT '0',
  `biggest_loss_difference` tinyint NOT NULL DEFAULT '0',
  `biggest_sum_of_goals` tinyint NOT NULL DEFAULT '0',
  `biggest_draw_sum` tinyint NOT NULL DEFAULT '0',
  `double_digits` mediumint NOT NULL DEFAULT '0',
  `clean_sheets` mediumint NOT NULL DEFAULT '0',
  `double_digits_ratio` decimal(5,4) DEFAULT NULL,
  `clean_sheets_ratio` decimal(5,4) DEFAULT NULL,
  `double_digits_conceded` mediumint NOT NULL DEFAULT '0',
  `clean_sheets_conceded` mediumint NOT NULL DEFAULT '0',
  `double_digits_conceded_ratio` decimal(5,4) DEFAULT NULL,
  `clean_sheets_conceded_ratio` decimal(5,4) DEFAULT NULL,
  `opponent_countries_faced` smallint unsigned NOT NULL DEFAULT '0',
  `opponent_countries_beaten` smallint unsigned NOT NULL DEFAULT '0',
  `opponent_countries_beaten_by` smallint unsigned NOT NULL DEFAULT '0',
  `different_opponents` smallint NOT NULL DEFAULT '0',
  `different_victims` smallint NOT NULL DEFAULT '0',
  `double_digits_victims` smallint NOT NULL DEFAULT '0',
  `clean_sheets_victims` smallint NOT NULL DEFAULT '0',
  `different_culprits` smallint NOT NULL DEFAULT '0',
  `double_digits_culprits` smallint NOT NULL DEFAULT '0',
  `clean_sheets_culprits` smallint NOT NULL DEFAULT '0',
  `best_attack_awards` int NOT NULL DEFAULT '0',
  `best_defense_awards` int NOT NULL DEFAULT '0',
  `best_single_wc_gf_per_game` decimal(6,4) DEFAULT NULL,
  `best_single_wc_gf_per_game_tournament_id` int DEFAULT NULL,
  `best_single_wc_ga_per_game` decimal(6,4) DEFAULT NULL,
  `best_single_wc_ga_per_game_tournament_id` int DEFAULT NULL,
  `tournaments_played_last_rise_tournament_id` int DEFAULT NULL,
  `tournaments_played_last_rise_event_date` date DEFAULT NULL,
  PRIMARY KEY (`player_id`,`slice_key`,`as_of_tournament_id`),
  KEY `idx_slice_at_event_tournament` (`as_of_tournament_id`),
  KEY `idx_slice_at_event_player_chrono` (`player_id`,`slice_key`,`event_date`,`event_chrono`,`as_of_tournament_id`),
  CONSTRAINT `fk_slice_at_event_player` FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_slice_at_event_tournament` FOREIGN KEY (`as_of_tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_country_slice_totals`
--

DROP TABLE IF EXISTS `amiga_country_slice_totals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_country_slice_totals` (
  `country_token` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `slice_key` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `players` smallint NOT NULL DEFAULT '0',
  `wc_participations` mediumint NOT NULL DEFAULT '0',
  `wc_participations_per_player` decimal(8,4) DEFAULT NULL,
  `games_per_player` decimal(8,4) DEFAULT NULL,
  `domestic_games` mediumint NOT NULL DEFAULT '0',
  `domestic_game_share` decimal(8,6) DEFAULT NULL,
  `international_games` mediumint NOT NULL DEFAULT '0',
  `international_game_share` decimal(8,6) DEFAULT NULL,
  `games_share` decimal(8,6) DEFAULT NULL,
  `goals_share` decimal(8,6) DEFAULT NULL,
  `realm_wc_tournament_count` smallint NOT NULL DEFAULT '0',
  `realm_wc_player_games` mediumint NOT NULL DEFAULT '0',
  `realm_wc_goals_for` mediumint NOT NULL DEFAULT '0',
  `tournaments_with_nation` smallint NOT NULL DEFAULT '0',
  `gold` smallint NOT NULL DEFAULT '0',
  `silver` smallint NOT NULL DEFAULT '0',
  `bronze` smallint NOT NULL DEFAULT '0',
  `podiums` smallint NOT NULL DEFAULT '0',
  `games` mediumint NOT NULL DEFAULT '0',
  `wins` mediumint NOT NULL DEFAULT '0',
  `draws` mediumint NOT NULL DEFAULT '0',
  `losses` mediumint NOT NULL DEFAULT '0',
  `points` mediumint NOT NULL DEFAULT '0',
  `points_per_realm_wc` decimal(8,4) DEFAULT NULL,
  `win_rate` decimal(8,6) DEFAULT NULL,
  `average_opponent_rating` decimal(10,4) DEFAULT NULL,
  `performance_rating` decimal(10,4) DEFAULT NULL,
  `goals_for` mediumint NOT NULL DEFAULT '0',
  `goals_against` mediumint NOT NULL DEFAULT '0',
  `goal_ratio` decimal(10,8) DEFAULT NULL,
  `most_goals_scored` tinyint NOT NULL DEFAULT '0',
  `most_goals_conceded` tinyint NOT NULL DEFAULT '0',
  `biggest_win_difference` tinyint NOT NULL DEFAULT '0',
  `biggest_loss_difference` tinyint NOT NULL DEFAULT '0',
  `biggest_sum_of_goals` tinyint NOT NULL DEFAULT '0',
  `biggest_draw_sum` tinyint NOT NULL DEFAULT '0',
  `double_digits` mediumint NOT NULL DEFAULT '0',
  `clean_sheets` mediumint NOT NULL DEFAULT '0',
  `double_digits_ratio` decimal(5,4) DEFAULT NULL,
  `clean_sheets_ratio` decimal(5,4) DEFAULT NULL,
  `double_digits_conceded` mediumint NOT NULL DEFAULT '0',
  `clean_sheets_conceded` mediumint NOT NULL DEFAULT '0',
  `double_digits_conceded_ratio` decimal(5,4) DEFAULT NULL,
  `clean_sheets_conceded_ratio` decimal(5,4) DEFAULT NULL,
  `opponent_countries_faced` smallint unsigned NOT NULL DEFAULT '0',
  `opponent_countries_beaten` smallint unsigned NOT NULL DEFAULT '0',
  `opponent_countries_beaten_by` smallint unsigned NOT NULL DEFAULT '0',
  `different_opponents` smallint NOT NULL DEFAULT '0',
  `different_victims` smallint NOT NULL DEFAULT '0',
  `double_digits_victims` smallint NOT NULL DEFAULT '0',
  `clean_sheets_victims` smallint NOT NULL DEFAULT '0',
  PRIMARY KEY (`country_token`,`slice_key`),
  KEY `idx_country_slice_totals_key_gold` (`slice_key`,`gold`),
  KEY `idx_country_slice_totals_key_points` (`slice_key`,`points`),
  KEY `idx_country_slice_totals_key_games` (`slice_key`,`games`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_country_slice_at_event`
--

DROP TABLE IF EXISTS `amiga_country_slice_at_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_country_slice_at_event` (
  `country_token` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `slice_key` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `as_of_tournament_id` int NOT NULL,
  `event_date` date DEFAULT NULL,
  `event_chrono` double DEFAULT NULL,
  `players` smallint NOT NULL DEFAULT '0',
  `wc_participations` mediumint NOT NULL DEFAULT '0',
  `wc_participations_per_player` decimal(8,4) DEFAULT NULL,
  `games_per_player` decimal(8,4) DEFAULT NULL,
  `domestic_games` mediumint NOT NULL DEFAULT '0',
  `domestic_game_share` decimal(8,6) DEFAULT NULL,
  `international_games` mediumint NOT NULL DEFAULT '0',
  `international_game_share` decimal(8,6) DEFAULT NULL,
  `games_share` decimal(8,6) DEFAULT NULL,
  `goals_share` decimal(8,6) DEFAULT NULL,
  `realm_wc_tournament_count` smallint NOT NULL DEFAULT '0',
  `realm_wc_player_games` mediumint NOT NULL DEFAULT '0',
  `realm_wc_goals_for` mediumint NOT NULL DEFAULT '0',
  `tournaments_with_nation` smallint NOT NULL DEFAULT '0',
  `gold` smallint NOT NULL DEFAULT '0',
  `silver` smallint NOT NULL DEFAULT '0',
  `bronze` smallint NOT NULL DEFAULT '0',
  `podiums` smallint NOT NULL DEFAULT '0',
  `games` mediumint NOT NULL DEFAULT '0',
  `wins` mediumint NOT NULL DEFAULT '0',
  `draws` mediumint NOT NULL DEFAULT '0',
  `losses` mediumint NOT NULL DEFAULT '0',
  `points` mediumint NOT NULL DEFAULT '0',
  `points_per_realm_wc` decimal(8,4) DEFAULT NULL,
  `win_rate` decimal(8,6) DEFAULT NULL,
  `average_opponent_rating` decimal(10,4) DEFAULT NULL,
  `performance_rating` decimal(10,4) DEFAULT NULL,
  `goals_for` mediumint NOT NULL DEFAULT '0',
  `goals_against` mediumint NOT NULL DEFAULT '0',
  `goal_ratio` decimal(10,8) DEFAULT NULL,
  `most_goals_scored` tinyint NOT NULL DEFAULT '0',
  `most_goals_conceded` tinyint NOT NULL DEFAULT '0',
  `biggest_win_difference` tinyint NOT NULL DEFAULT '0',
  `biggest_loss_difference` tinyint NOT NULL DEFAULT '0',
  `biggest_sum_of_goals` tinyint NOT NULL DEFAULT '0',
  `biggest_draw_sum` tinyint NOT NULL DEFAULT '0',
  `double_digits` mediumint NOT NULL DEFAULT '0',
  `clean_sheets` mediumint NOT NULL DEFAULT '0',
  `double_digits_ratio` decimal(5,4) DEFAULT NULL,
  `clean_sheets_ratio` decimal(5,4) DEFAULT NULL,
  `double_digits_conceded` mediumint NOT NULL DEFAULT '0',
  `clean_sheets_conceded` mediumint NOT NULL DEFAULT '0',
  `double_digits_conceded_ratio` decimal(5,4) DEFAULT NULL,
  `clean_sheets_conceded_ratio` decimal(5,4) DEFAULT NULL,
  `opponent_countries_faced` smallint unsigned NOT NULL DEFAULT '0',
  `opponent_countries_beaten` smallint unsigned NOT NULL DEFAULT '0',
  `opponent_countries_beaten_by` smallint unsigned NOT NULL DEFAULT '0',
  `different_opponents` smallint NOT NULL DEFAULT '0',
  `different_victims` smallint NOT NULL DEFAULT '0',
  `double_digits_victims` smallint NOT NULL DEFAULT '0',
  `clean_sheets_victims` smallint NOT NULL DEFAULT '0',
  PRIMARY KEY (`country_token`,`slice_key`,`as_of_tournament_id`),
  KEY `idx_country_slice_at_event_tournament` (`as_of_tournament_id`),
  CONSTRAINT `fk_country_slice_at_event_tournament` FOREIGN KEY (`as_of_tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_wc_hof_snapshots`
--

DROP TABLE IF EXISTS `amiga_wc_hof_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_wc_hof_snapshots` (
  `tournament_id` int NOT NULL,
  `event_date` date DEFAULT NULL,
  `event_chrono` double DEFAULT NULL,
  `tournament_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `finalized_at` datetime NOT NULL,
  `MostWcPlayed` int DEFAULT NULL,
  `MostWcGold` int DEFAULT NULL,
  `MostWcGames` int DEFAULT NULL,
  `MostWcWins` int DEFAULT NULL,
  `MostWcPoints` int DEFAULT NULL,
  `BestWcPtsPerGame` decimal(6,4) DEFAULT NULL,
  `BestWcWinRate` decimal(5,4) DEFAULT NULL,
  `MostWcGoalsFor` int DEFAULT NULL,
  `BestWcGoalsForPerGame` decimal(6,4) DEFAULT NULL,
  `BestWcGoalsAgainstPerGame` decimal(6,4) DEFAULT NULL,
  `BestWcGoalDiffPerGame` decimal(7,4) DEFAULT NULL,
  `BestWcGoalRatio` decimal(7,4) DEFAULT NULL,
  `MostWcDoubleDigits` int DEFAULT NULL,
  `BestWcDoubleDigitsRatio` decimal(5,4) DEFAULT NULL,
  `MostWcCleanSheets` int DEFAULT NULL,
  `BestWcCleanSheetsRatio` decimal(5,4) DEFAULT NULL,
  `MostWcOpponents` int DEFAULT NULL,
  `MostWcVictims` int DEFAULT NULL,
  `MostWcDoubleDigitsVictims` int DEFAULT NULL,
  `MostWcCleanSheetsVictims` int DEFAULT NULL,
  `MostWcGoalsInOneGame` int DEFAULT NULL,
  `BiggestWcWinDifference` int DEFAULT NULL,
  `BiggestWcDrawSum` int DEFAULT NULL,
  `BiggestWcSumOfGoals` int DEFAULT NULL,
  `MostWcBestAttackAwards` int DEFAULT NULL,
  `MostWcBestDefenseAwards` int DEFAULT NULL,
  `BestSingleWcGoalsForPerGame` decimal(6,4) DEFAULT NULL,
  `BestSingleWcGoalsAgainstPerGame` decimal(6,4) DEFAULT NULL,
  `MostWcPlayedID` int DEFAULT NULL,
  `MostWcGoldID` int DEFAULT NULL,
  `MostWcGamesID` int DEFAULT NULL,
  `MostWcWinsID` int DEFAULT NULL,
  `MostWcPointsID` int DEFAULT NULL,
  `BestWcPtsPerGameID` int DEFAULT NULL,
  `BestWcWinRateID` int DEFAULT NULL,
  `MostWcGoalsForID` int DEFAULT NULL,
  `BestWcGoalsForPerGameID` int DEFAULT NULL,
  `BestWcGoalsAgainstPerGameID` int DEFAULT NULL,
  `BestWcGoalDiffPerGameID` int DEFAULT NULL,
  `BestWcGoalRatioID` int DEFAULT NULL,
  `MostWcDoubleDigitsID` int DEFAULT NULL,
  `BestWcDoubleDigitsRatioID` int DEFAULT NULL,
  `MostWcCleanSheetsID` int DEFAULT NULL,
  `BestWcCleanSheetsRatioID` int DEFAULT NULL,
  `MostWcOpponentsID` int DEFAULT NULL,
  `MostWcVictimsID` int DEFAULT NULL,
  `MostWcDoubleDigitsVictimsID` int DEFAULT NULL,
  `MostWcCleanSheetsVictimsID` int DEFAULT NULL,
  `MostWcGoalsInOneGameID` int DEFAULT NULL,
  `BiggestWcWinDifferenceID` int DEFAULT NULL,
  `BiggestWcDrawSumIDA` int DEFAULT NULL,
  `BiggestWcDrawSumIDB` int DEFAULT NULL,
  `BiggestWcSumOfGoalsIDA` int DEFAULT NULL,
  `BiggestWcSumOfGoalsIDB` int DEFAULT NULL,
  `MostWcBestAttackAwardsID` int DEFAULT NULL,
  `MostWcBestDefenseAwardsID` int DEFAULT NULL,
  `BestSingleWcGoalsForPerGameID` int DEFAULT NULL,
  `BestSingleWcGoalsAgainstPerGameID` int DEFAULT NULL,
  `MostWcPlayedName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcGoldName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcGamesName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcWinsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcPointsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcPtsPerGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcWinRateName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcGoalsForName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcGoalsForPerGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcGoalsAgainstPerGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcGoalDiffPerGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcGoalRatioName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcDoubleDigitsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcDoubleDigitsRatioName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcCleanSheetsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcCleanSheetsRatioName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcOpponentsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcVictimsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcDoubleDigitsVictimsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcCleanSheetsVictimsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcGoalsInOneGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestWcWinDifferenceName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestWcDrawSumNameA` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestWcDrawSumNameB` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestWcSumOfGoalsNameA` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestWcSumOfGoalsNameB` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcBestAttackAwardsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcBestDefenseAwardsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestSingleWcGoalsForPerGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestSingleWcGoalsAgainstPerGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcPlayedDate` date DEFAULT NULL,
  `MostWcGoldDate` date DEFAULT NULL,
  `MostWcGamesDate` date DEFAULT NULL,
  `MostWcWinsDate` date DEFAULT NULL,
  `MostWcPointsDate` date DEFAULT NULL,
  `BestWcPtsPerGameDate` date DEFAULT NULL,
  `BestWcWinRateDate` date DEFAULT NULL,
  `MostWcGoalsForDate` date DEFAULT NULL,
  `BestWcGoalsForPerGameDate` date DEFAULT NULL,
  `BestWcGoalsAgainstPerGameDate` date DEFAULT NULL,
  `BestWcGoalDiffPerGameDate` date DEFAULT NULL,
  `BestWcGoalRatioDate` date DEFAULT NULL,
  `MostWcDoubleDigitsDate` date DEFAULT NULL,
  `BestWcDoubleDigitsRatioDate` date DEFAULT NULL,
  `MostWcCleanSheetsDate` date DEFAULT NULL,
  `BestWcCleanSheetsRatioDate` date DEFAULT NULL,
  `MostWcOpponentsDate` date DEFAULT NULL,
  `MostWcVictimsDate` date DEFAULT NULL,
  `MostWcDoubleDigitsVictimsDate` date DEFAULT NULL,
  `MostWcCleanSheetsVictimsDate` date DEFAULT NULL,
  `MostWcGoalsInOneGameDate` date DEFAULT NULL,
  `BiggestWcWinDifferenceDate` date DEFAULT NULL,
  `BiggestWcDrawSumDate` date DEFAULT NULL,
  `BiggestWcSumOfGoalsDate` date DEFAULT NULL,
  `MostWcBestAttackAwardsDate` date DEFAULT NULL,
  `MostWcBestDefenseAwardsDate` date DEFAULT NULL,
  `BestSingleWcGoalsForPerGameDate` date DEFAULT NULL,
  `BestSingleWcGoalsAgainstPerGameDate` date DEFAULT NULL,
  `MostWcGoalsInOneGameGameID` int DEFAULT NULL,
  `BiggestWcWinDifferenceGameID` int DEFAULT NULL,
  `BiggestWcDrawSumGameID` int DEFAULT NULL,
  `BiggestWcSumOfGoalsGameID` int DEFAULT NULL,
  `BestSingleWcGoalsForPerGameTournamentID` int DEFAULT NULL,
  `BestSingleWcGoalsAgainstPerGameTournamentID` int DEFAULT NULL,
  PRIMARY KEY (`tournament_id`),
  KEY `idx_wc_hof_snapshots_chrono` (`event_date`,`event_chrono`,`tournament_id`),
  CONSTRAINT `fk_wc_hof_snapshots_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `amiga_wc_hof_present`
--

DROP TABLE IF EXISTS `amiga_wc_hof_present`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amiga_wc_hof_present` (
  `id` tinyint NOT NULL,
  `MostWcPlayed` int DEFAULT NULL,
  `MostWcGold` int DEFAULT NULL,
  `MostWcGames` int DEFAULT NULL,
  `MostWcWins` int DEFAULT NULL,
  `MostWcPoints` int DEFAULT NULL,
  `BestWcPtsPerGame` decimal(6,4) DEFAULT NULL,
  `BestWcWinRate` decimal(5,4) DEFAULT NULL,
  `MostWcGoalsFor` int DEFAULT NULL,
  `BestWcGoalsForPerGame` decimal(6,4) DEFAULT NULL,
  `BestWcGoalsAgainstPerGame` decimal(6,4) DEFAULT NULL,
  `BestWcGoalDiffPerGame` decimal(7,4) DEFAULT NULL,
  `BestWcGoalRatio` decimal(7,4) DEFAULT NULL,
  `MostWcDoubleDigits` int DEFAULT NULL,
  `BestWcDoubleDigitsRatio` decimal(5,4) DEFAULT NULL,
  `MostWcCleanSheets` int DEFAULT NULL,
  `BestWcCleanSheetsRatio` decimal(5,4) DEFAULT NULL,
  `MostWcOpponents` int DEFAULT NULL,
  `MostWcVictims` int DEFAULT NULL,
  `MostWcDoubleDigitsVictims` int DEFAULT NULL,
  `MostWcCleanSheetsVictims` int DEFAULT NULL,
  `MostWcGoalsInOneGame` int DEFAULT NULL,
  `BiggestWcWinDifference` int DEFAULT NULL,
  `BiggestWcDrawSum` int DEFAULT NULL,
  `BiggestWcSumOfGoals` int DEFAULT NULL,
  `MostWcBestAttackAwards` int DEFAULT NULL,
  `MostWcBestDefenseAwards` int DEFAULT NULL,
  `BestSingleWcGoalsForPerGame` decimal(6,4) DEFAULT NULL,
  `BestSingleWcGoalsAgainstPerGame` decimal(6,4) DEFAULT NULL,
  `MostWcPlayedID` int DEFAULT NULL,
  `MostWcGoldID` int DEFAULT NULL,
  `MostWcGamesID` int DEFAULT NULL,
  `MostWcWinsID` int DEFAULT NULL,
  `MostWcPointsID` int DEFAULT NULL,
  `BestWcPtsPerGameID` int DEFAULT NULL,
  `BestWcWinRateID` int DEFAULT NULL,
  `MostWcGoalsForID` int DEFAULT NULL,
  `BestWcGoalsForPerGameID` int DEFAULT NULL,
  `BestWcGoalsAgainstPerGameID` int DEFAULT NULL,
  `BestWcGoalDiffPerGameID` int DEFAULT NULL,
  `BestWcGoalRatioID` int DEFAULT NULL,
  `MostWcDoubleDigitsID` int DEFAULT NULL,
  `BestWcDoubleDigitsRatioID` int DEFAULT NULL,
  `MostWcCleanSheetsID` int DEFAULT NULL,
  `BestWcCleanSheetsRatioID` int DEFAULT NULL,
  `MostWcOpponentsID` int DEFAULT NULL,
  `MostWcVictimsID` int DEFAULT NULL,
  `MostWcDoubleDigitsVictimsID` int DEFAULT NULL,
  `MostWcCleanSheetsVictimsID` int DEFAULT NULL,
  `MostWcGoalsInOneGameID` int DEFAULT NULL,
  `BiggestWcWinDifferenceID` int DEFAULT NULL,
  `BiggestWcDrawSumIDA` int DEFAULT NULL,
  `BiggestWcDrawSumIDB` int DEFAULT NULL,
  `BiggestWcSumOfGoalsIDA` int DEFAULT NULL,
  `BiggestWcSumOfGoalsIDB` int DEFAULT NULL,
  `MostWcBestAttackAwardsID` int DEFAULT NULL,
  `MostWcBestDefenseAwardsID` int DEFAULT NULL,
  `BestSingleWcGoalsForPerGameID` int DEFAULT NULL,
  `BestSingleWcGoalsAgainstPerGameID` int DEFAULT NULL,
  `MostWcPlayedName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcGoldName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcGamesName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcWinsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcPointsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcPtsPerGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcWinRateName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcGoalsForName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcGoalsForPerGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcGoalsAgainstPerGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcGoalDiffPerGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcGoalRatioName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcDoubleDigitsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcDoubleDigitsRatioName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcCleanSheetsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestWcCleanSheetsRatioName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcOpponentsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcVictimsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcDoubleDigitsVictimsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcCleanSheetsVictimsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcGoalsInOneGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestWcWinDifferenceName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestWcDrawSumNameA` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestWcDrawSumNameB` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestWcSumOfGoalsNameA` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BiggestWcSumOfGoalsNameB` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcBestAttackAwardsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcBestDefenseAwardsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestSingleWcGoalsForPerGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BestSingleWcGoalsAgainstPerGameName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MostWcPlayedDate` date DEFAULT NULL,
  `MostWcGoldDate` date DEFAULT NULL,
  `MostWcGamesDate` date DEFAULT NULL,
  `MostWcWinsDate` date DEFAULT NULL,
  `MostWcPointsDate` date DEFAULT NULL,
  `BestWcPtsPerGameDate` date DEFAULT NULL,
  `BestWcWinRateDate` date DEFAULT NULL,
  `MostWcGoalsForDate` date DEFAULT NULL,
  `BestWcGoalsForPerGameDate` date DEFAULT NULL,
  `BestWcGoalsAgainstPerGameDate` date DEFAULT NULL,
  `BestWcGoalDiffPerGameDate` date DEFAULT NULL,
  `BestWcGoalRatioDate` date DEFAULT NULL,
  `MostWcDoubleDigitsDate` date DEFAULT NULL,
  `BestWcDoubleDigitsRatioDate` date DEFAULT NULL,
  `MostWcCleanSheetsDate` date DEFAULT NULL,
  `BestWcCleanSheetsRatioDate` date DEFAULT NULL,
  `MostWcOpponentsDate` date DEFAULT NULL,
  `MostWcVictimsDate` date DEFAULT NULL,
  `MostWcDoubleDigitsVictimsDate` date DEFAULT NULL,
  `MostWcCleanSheetsVictimsDate` date DEFAULT NULL,
  `MostWcGoalsInOneGameDate` date DEFAULT NULL,
  `BiggestWcWinDifferenceDate` date DEFAULT NULL,
  `BiggestWcDrawSumDate` date DEFAULT NULL,
  `BiggestWcSumOfGoalsDate` date DEFAULT NULL,
  `MostWcBestAttackAwardsDate` date DEFAULT NULL,
  `MostWcBestDefenseAwardsDate` date DEFAULT NULL,
  `BestSingleWcGoalsForPerGameDate` date DEFAULT NULL,
  `BestSingleWcGoalsAgainstPerGameDate` date DEFAULT NULL,
  `MostWcGoalsInOneGameGameID` int DEFAULT NULL,
  `BiggestWcWinDifferenceGameID` int DEFAULT NULL,
  `BiggestWcDrawSumGameID` int DEFAULT NULL,
  `BiggestWcSumOfGoalsGameID` int DEFAULT NULL,
  `BestSingleWcGoalsForPerGameTournamentID` int DEFAULT NULL,
  `BestSingleWcGoalsAgainstPerGameTournamentID` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-23 17:25:31
