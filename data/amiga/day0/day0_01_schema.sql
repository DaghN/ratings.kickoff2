-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: ko2amiga_db
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
  `slug` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `schema_version` smallint NOT NULL DEFAULT '1',
  `description` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `spec_json` longtext COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tournament_format_templates_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `chrono` double DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `is_cup` tinyint(1) NOT NULL DEFAULT '0',
  `country` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `equal_teams` tinyint(1) NOT NULL DEFAULT '0',
  `player_count` smallint DEFAULT NULL,
  `format_template_id` int DEFAULT NULL,
  `format_overrides` longtext COLLATE utf8mb4_general_ci,
  `has_league` tinyint(1) NOT NULL DEFAULT '0',
  `has_cup` tinyint(1) NOT NULL DEFAULT '0',
  `is_world_cup` tinyint(1) NOT NULL DEFAULT '0',
  `lifecycle_status` enum('draft','registration','ready','running','completed','archived','void') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
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
) ENGINE=InnoDB AUTO_INCREMENT=606 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `country` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `display` tinyint(1) NOT NULL DEFAULT '1',
  `player_source` enum('import','live_ops') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'import',
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
  `phase` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `goals_a` int NOT NULL,
  `goals_b` int NOT NULL,
  `extra` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=27419 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-08  5:37:58
