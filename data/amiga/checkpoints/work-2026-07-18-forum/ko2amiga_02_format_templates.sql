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
-- Dumping data for table `tournament_format_templates`
--

LOCK TABLES `tournament_format_templates` WRITE;
/*!40000 ALTER TABLE `tournament_format_templates` DISABLE KEYS */;
INSERT INTO `tournament_format_templates` VALUES (1,'legacy_inferred','Legacy inferred',1,'Imported Access event; structure inferred from phase labels and catalog hints.','{\"fixture_backed\": false, \"legacy_phase_fallback\": true, \"source\": \"access_import\"}'),(2,'kitchen_marathon','Kitchen marathon',1,'Single-table round-robin or open marathon format.','{\"legacy_phase_fallback\": false, \"stages\": [{\"key\": \"overall\", \"type\": \"league\"}]}'),(3,'group_knockout','Group + knockout',1,'Group league stage followed by elimination ties.','{\"knockout_rounds\": [\"last_16\", \"quarter\", \"semi\", \"final\", \"placement_3rd\"], \"legacy_phase_fallback\": false, \"stages\": [{\"key\": \"groups\", \"type\": \"league_groups\"}, {\"key\": \"knockout\", \"type\": \"knockout\"}]}'),(4,'world_cup_class','World Cup class',1,'Multi-track World Cup-style event with groups and placement cups.','{\"legacy_phase_fallback\": false, \"stages\": [{\"key\": \"round_1\", \"type\": \"league_groups\"}, {\"key\": \"round_2\", \"type\": \"league_groups\"}, {\"key\": \"classification\", \"type\": \"knockout_tracks\"}], \"tracks\": [\"main\", \"silver\", \"bronze\", \"koa\"]}'),(5,'swiss','Swiss system',1,'Pairing-based rounds with cumulative overall standings.','{\"legacy_phase_fallback\": false, \"pairing_policy\": \"swiss_standard\", \"round_count_policy\": \"ceil_log2_players\", \"stage_factory\": \"create_swiss_tournament\", \"stages\": [{\"key\": \"overall\", \"type\": \"league\"}], \"standings_resolver\": \"swiss_overall_league\", \"status\": \"implemented\"}'),(6,'double_elimination','Double elimination',1,'Winners and losers brackets with grand final (4 or 8 players).','{\"advance_hook\": \"advance_double_elim\", \"bracket_sizes\": [4, 8], \"legacy_phase_fallback\": false, \"stage_factory\": \"create_double_elimination_tournament\", \"stages\": [{\"key\": \"winners\", \"type\": \"knockout\"}, {\"key\": \"losers\", \"type\": \"knockout\"}, {\"key\": \"grand_final\", \"type\": \"knockout\"}], \"standings_resolver\": \"knockout_fixture_scopes\", \"status\": \"implemented\"}');
/*!40000 ALTER TABLE `tournament_format_templates` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-18  6:34:07
