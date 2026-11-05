-- MySQL dump 10.13  Distrib 9.1.0, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: fitbook
-- ------------------------------------------------------
-- Server version	9.1.0

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
-- Table structure for table `activity`
--

DROP TABLE IF EXISTS `activity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_AC74095A5E237E06` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity`
--

LOCK TABLES `activity` WRITE;
/*!40000 ALTER TABLE `activity` DISABLE KEYS */;
INSERT INTO `activity` VALUES (1,'Yoga','Postures, respiration et relaxation pour gagner en souplesse et en calme.','https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=900&q=72','Bien-être'),(2,'Pilates','Renforcement des muscles profonds et travail de la posture.','https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=900&q=72','Bien-être'),(3,'Stretching','Étirements guidés pour récupérer et prévenir les blessures.','https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=900&q=72','Bien-être'),(4,'CrossFit','Circuits fonctionnels variés, à haute intensité.','https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=72','Haute intensité'),(5,'Musculation','Travail guidé aux charges libres et aux machines.','https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=72','Force'),(6,'Boxe','Technique, cardio-boxe et travail au sac.','https://images.unsplash.com/photo-1549719386-74dfcbf7dbed?auto=format&fit=crop&w=900&q=72','Sport de combat'),(7,'Cardio','Endurance et dépense énergétique sur des formats courts.','https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=72','Cardio'),(8,'Spinning','Vélo en salle en musique, intervalles et montées.','https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=72','Cardio'),(9,'Running','Sorties en groupe, fractionné et préparation de courses.','https://images.unsplash.com/photo-1552674605-46e432e1a7ee?auto=format&fit=crop&w=900&q=72','Plein air'),(10,'Zumba','Fitness dansé sur des rythmes latins.','https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=72','Danse');
/*!40000 ALTER TABLE `activity` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `availability`
--

DROP TABLE IF EXISTS `availability`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `availability` (
  `id` int NOT NULL AUTO_INCREMENT,
  `day_of_week` smallint DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_recurring` tinyint NOT NULL,
  `date` date DEFAULT NULL,
  `coach_id` int DEFAULT NULL,
  `gym_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_3FB7A2BF3C105691` (`coach_id`),
  KEY `IDX_3FB7A2BFBD2F03` (`gym_id`),
  CONSTRAINT `FK_3FB7A2BF3C105691` FOREIGN KEY (`coach_id`) REFERENCES `coach_profile` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_3FB7A2BFBD2F03` FOREIGN KEY (`gym_id`) REFERENCES `gym` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_availability_kind` CHECK ((((`is_recurring` = 1) and (`day_of_week` between 1 and 7)) or ((`is_recurring` = 0) and (`date` is not null)))),
  CONSTRAINT `chk_availability_owner` CHECK ((((`coach_id` is not null) + (`gym_id` is not null)) = 1)),
  CONSTRAINT `chk_availability_times` CHECK ((`end_time` > `start_time`))
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `availability`
--

LOCK TABLES `availability` WRITE;
/*!40000 ALTER TABLE `availability` DISABLE KEYS */;
INSERT INTO `availability` VALUES (1,3,'07:00:00','11:00:00',1,NULL,1,NULL),(2,2,'09:00:00','12:00:00',1,NULL,1,NULL),(3,6,'12:00:00','15:00:00',1,NULL,2,NULL),(4,4,'13:00:00','17:00:00',1,NULL,2,NULL),(5,2,'15:00:00','17:00:00',1,NULL,3,NULL),(6,4,'13:00:00','15:00:00',1,NULL,3,NULL),(7,3,'13:00:00','17:00:00',1,NULL,4,NULL),(8,1,'07:00:00','11:00:00',1,NULL,4,NULL),(9,3,'17:00:00','20:00:00',1,NULL,5,NULL),(10,6,'16:00:00','18:00:00',1,NULL,5,NULL),(11,1,'13:00:00','16:00:00',1,NULL,6,NULL),(12,5,'17:00:00','21:00:00',1,NULL,6,NULL),(13,2,'10:00:00','12:00:00',1,NULL,7,NULL),(14,5,'08:00:00','10:00:00',1,NULL,7,NULL),(15,6,'16:00:00','19:00:00',1,NULL,8,NULL),(16,5,'14:00:00','17:00:00',1,NULL,8,NULL),(17,4,'16:00:00','19:00:00',1,NULL,9,NULL),(18,6,'07:00:00','10:00:00',1,NULL,9,NULL),(19,3,'12:00:00','15:00:00',1,NULL,10,NULL),(20,5,'15:00:00','17:00:00',1,NULL,10,NULL),(21,2,'12:00:00','16:00:00',1,NULL,11,NULL),(22,6,'17:00:00','20:00:00',1,NULL,11,NULL),(23,4,'17:00:00','21:00:00',1,NULL,12,NULL),(24,2,'16:00:00','19:00:00',1,NULL,12,NULL),(25,4,'10:00:00','13:00:00',1,NULL,13,NULL),(26,6,'12:00:00','15:00:00',1,NULL,13,NULL),(27,2,'12:00:00','14:00:00',1,NULL,14,NULL),(28,4,'10:00:00','13:00:00',1,NULL,14,NULL),(29,3,'16:00:00','19:00:00',1,NULL,15,NULL),(30,5,'14:00:00','17:00:00',1,NULL,15,NULL),(31,3,'14:00:00','17:00:00',1,NULL,NULL,1),(32,4,'08:00:00','11:00:00',1,NULL,NULL,1),(33,4,'09:00:00','11:00:00',1,NULL,NULL,2),(34,6,'14:00:00','18:00:00',1,NULL,NULL,2),(35,5,'14:00:00','16:00:00',1,NULL,NULL,3),(36,4,'08:00:00','12:00:00',1,NULL,NULL,3),(37,3,'16:00:00','20:00:00',1,NULL,NULL,4),(38,4,'12:00:00','15:00:00',1,NULL,NULL,4),(39,1,'07:00:00','09:00:00',1,NULL,NULL,5),(40,5,'09:00:00','12:00:00',1,NULL,NULL,5),(41,4,'12:00:00','14:00:00',1,NULL,NULL,6),(42,1,'07:00:00','10:00:00',1,NULL,NULL,6),(43,1,'09:00:00','13:00:00',1,NULL,NULL,7),(44,4,'17:00:00','20:00:00',1,NULL,NULL,7),(45,3,'14:00:00','18:00:00',1,NULL,NULL,8),(46,1,'16:00:00','18:00:00',1,NULL,NULL,8),(47,6,'10:00:00','14:00:00',1,NULL,NULL,9),(48,1,'11:00:00','14:00:00',1,NULL,NULL,9),(49,6,'07:00:00','10:00:00',1,NULL,NULL,10),(50,5,'16:00:00','19:00:00',1,NULL,NULL,10);
/*!40000 ALTER TABLE `availability` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coach_profile`
--

DROP TABLE IF EXISTS `coach_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coach_profile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bio` longtext COLLATE utf8mb4_unicode_ci,
  `specialities` json NOT NULL,
  `experience_years` smallint DEFAULT NULL,
  `hourly_rate` decimal(8,2) DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certifications` longtext COLLATE utf8mb4_unicode_ci,
  `user_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_3A874247A76ED395` (`user_id`),
  KEY `idx_coach_profile_city` (`city`),
  CONSTRAINT `FK_3A874247A76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coach_profile`
--

LOCK TABLES `coach_profile` WRITE;
/*!40000 ALTER TABLE `coach_profile` DISABLE KEYS */;
INSERT INTO `coach_profile` VALUES (1,'Coach diplômé·e, spécialisé·e en Spinning, Boxe. J’accompagne tous les niveaux avec un suivi personnalisé.','[\"Spinning\", \"Boxe\"]',1,30.00,'Ixelles','RYT 200 Yoga',7),(2,'Coach diplômé·e, spécialisé·e en Musculation, Zumba, Boxe. J’accompagne tous les niveaux avec un suivi personnalisé.','[\"Musculation\", \"Zumba\", \"Boxe\"]',10,40.00,'Bruxelles','Brevet ADEPS',8),(3,'Coach diplômé·e, spécialisé·e en Stretching, Running, Spinning. J’accompagne tous les niveaux avec un suivi personnalisé.','[\"Stretching\", \"Running\", \"Spinning\"]',5,26.00,'Saint-Gilles','Certification Pilates Mat',9),(4,'Coach diplômé·e, spécialisé·e en Musculation, Boxe, CrossFit. J’accompagne tous les niveaux avec un suivi personnalisé.','[\"Musculation\", \"Boxe\", \"CrossFit\"]',1,39.00,'Forest','Moniteur sportif initiateur',10),(5,'Coach diplômé·e, spécialisé·e en CrossFit. J’accompagne tous les niveaux avec un suivi personnalisé.','[\"CrossFit\"]',11,56.00,'Jette','BPJEPS AF',11),(6,'Coach diplômé·e, spécialisé·e en Cardio, Boxe, Spinning. J’accompagne tous les niveaux avec un suivi personnalisé.','[\"Cardio\", \"Boxe\", \"Spinning\"]',6,44.00,'Forest','Brevet ADEPS',12),(7,'Coach diplômé·e, spécialisé·e en Boxe. J’accompagne tous les niveaux avec un suivi personnalisé.','[\"Boxe\"]',2,48.00,'Forest','Certification Pilates Mat',13),(8,'Coach diplômé·e, spécialisé·e en Pilates, Musculation, Yoga. J’accompagne tous les niveaux avec un suivi personnalisé.','[\"Pilates\", \"Musculation\", \"Yoga\"]',9,35.00,'Ixelles','Brevet ADEPS',14),(9,'Coach diplômé·e, spécialisé·e en Yoga, Running, Zumba. J’accompagne tous les niveaux avec un suivi personnalisé.','[\"Yoga\", \"Running\", \"Zumba\"]',1,28.00,'Ixelles','Certification Pilates Mat',15),(10,'Coach diplômé·e, spécialisé·e en Yoga. J’accompagne tous les niveaux avec un suivi personnalisé.','[\"Yoga\"]',10,56.00,'Jette','Moniteur sportif initiateur',16),(11,'Coach diplômé·e, spécialisé·e en Spinning, CrossFit. J’accompagne tous les niveaux avec un suivi personnalisé.','[\"Spinning\", \"CrossFit\"]',5,32.00,'Schaerbeek','Moniteur sportif initiateur',17),(12,'Coach diplômé·e, spécialisé·e en Yoga. J’accompagne tous les niveaux avec un suivi personnalisé.','[\"Yoga\"]',14,25.00,'Etterbeek','Moniteur sportif initiateur',18),(13,'Coach diplômé·e, spécialisé·e en CrossFit. J’accompagne tous les niveaux avec un suivi personnalisé.','[\"CrossFit\"]',3,41.00,'Etterbeek','Brevet ADEPS',19),(14,'Coach diplômé·e, spécialisé·e en Spinning, Running. J’accompagne tous les niveaux avec un suivi personnalisé.','[\"Spinning\", \"Running\"]',2,44.00,'Uccle','BPJEPS AF',20),(15,'Coach diplômé·e, spécialisé·e en Boxe, Spinning. J’accompagne tous les niveaux avec un suivi personnalisé.','[\"Boxe\", \"Spinning\"]',10,44.00,'Jette','BPJEPS AF',21);
/*!40000 ALTER TABLE `coach_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctrine_migration_versions`
--

DROP TABLE IF EXISTS `doctrine_migration_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctrine_migration_versions`
--

LOCK TABLES `doctrine_migration_versions` WRITE;
/*!40000 ALTER TABLE `doctrine_migration_versions` DISABLE KEYS */;
INSERT INTO `doctrine_migration_versions` VALUES ('App\\Migrations\\Version20261105144007','2026-11-05 14:47:22',3585),('App\\Migrations\\Version20261105150000','2026-11-05 14:47:26',10);
/*!40000 ALTER TABLE `doctrine_migration_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `favorite`
--

DROP TABLE IF EXISTS `favorite`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `favorite` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `user_id` int NOT NULL,
  `gym_id` int DEFAULT NULL,
  `coach_id` int DEFAULT NULL,
  `session_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_favorite_user_gym` (`user_id`,`gym_id`),
  UNIQUE KEY `uq_favorite_user_coach` (`user_id`,`coach_id`),
  UNIQUE KEY `uq_favorite_user_session` (`user_id`,`session_id`),
  KEY `IDX_68C58ED9A76ED395` (`user_id`),
  KEY `IDX_68C58ED9BD2F03` (`gym_id`),
  KEY `IDX_68C58ED93C105691` (`coach_id`),
  KEY `IDX_68C58ED9613FECDF` (`session_id`),
  CONSTRAINT `FK_68C58ED93C105691` FOREIGN KEY (`coach_id`) REFERENCES `coach_profile` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_68C58ED9613FECDF` FOREIGN KEY (`session_id`) REFERENCES `session` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_68C58ED9A76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_68C58ED9BD2F03` FOREIGN KEY (`gym_id`) REFERENCES `gym` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_favorite_target` CHECK (((((`gym_id` is not null) + (`coach_id` is not null)) + (`session_id` is not null)) = 1)),
  CONSTRAINT `chk_favorite_type` CHECK ((((`type` = _utf8mb4'gym') and (`gym_id` is not null)) or ((`type` = _utf8mb4'coach') and (`coach_id` is not null)) or ((`type` = _utf8mb4'session') and (`session_id` is not null))))
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `favorite`
--

LOCK TABLES `favorite` WRITE;
/*!40000 ALTER TABLE `favorite` DISABLE KEYS */;
INSERT INTO `favorite` VALUES (1,'gym','2026-11-05 14:51:19',22,6,NULL,NULL),(2,'session','2026-11-05 14:51:19',23,NULL,NULL,23),(3,'session','2026-11-05 14:51:19',23,NULL,NULL,68),(4,'gym','2026-11-05 14:51:19',23,1,NULL,NULL),(5,'session','2026-11-05 14:51:19',23,NULL,NULL,32),(6,'session','2026-11-05 14:51:19',24,NULL,NULL,42),(7,'coach','2026-11-05 14:51:19',24,NULL,12,NULL),(8,'session','2026-11-05 14:51:19',25,NULL,NULL,33),(9,'session','2026-11-05 14:51:19',25,NULL,NULL,12),(10,'session','2026-11-05 14:51:19',25,NULL,NULL,75),(11,'session','2026-11-05 14:51:19',25,NULL,NULL,14),(12,'coach','2026-11-05 14:51:19',27,NULL,1,NULL),(13,'session','2026-11-05 14:51:19',27,NULL,NULL,47),(14,'session','2026-11-05 14:51:19',28,NULL,NULL,1),(15,'session','2026-11-05 14:51:19',29,NULL,NULL,51),(16,'session','2026-11-05 14:51:19',30,NULL,NULL,78),(17,'session','2026-11-05 14:51:19',30,NULL,NULL,21),(18,'session','2026-11-05 14:51:19',31,NULL,NULL,30),(19,'coach','2026-11-05 14:51:19',32,NULL,11,NULL),(20,'session','2026-11-05 14:51:19',32,NULL,NULL,53),(21,'session','2026-11-05 14:51:19',32,NULL,NULL,56),(22,'session','2026-11-05 14:51:19',33,NULL,NULL,22),(23,'session','2026-11-05 14:51:19',33,NULL,NULL,5),(24,'session','2026-11-05 14:51:19',34,NULL,NULL,11),(25,'coach','2026-11-05 14:51:19',34,NULL,3,NULL),(26,'session','2026-11-05 14:51:19',34,NULL,NULL,37),(27,'session','2026-11-05 14:51:19',35,NULL,NULL,75),(28,'gym','2026-11-05 14:51:19',36,10,NULL,NULL),(29,'coach','2026-11-05 14:51:19',37,NULL,15,NULL),(30,'session','2026-11-05 14:51:19',37,NULL,NULL,57),(31,'coach','2026-11-05 14:51:19',37,NULL,5,NULL),(32,'coach','2026-11-05 14:51:19',39,NULL,7,NULL),(33,'gym','2026-11-05 14:51:19',39,10,NULL,NULL),(34,'session','2026-11-05 14:51:19',39,NULL,NULL,5),(35,'session','2026-11-05 14:51:19',39,NULL,NULL,17),(36,'session','2026-11-05 14:51:19',41,NULL,NULL,74),(37,'session','2026-11-05 14:51:19',41,NULL,NULL,40),(38,'gym','2026-11-05 14:51:19',42,7,NULL,NULL),(39,'session','2026-11-05 14:51:19',42,NULL,NULL,47),(40,'session','2026-11-05 14:51:19',42,NULL,NULL,78),(41,'session','2026-11-05 14:51:19',42,NULL,NULL,10),(42,'session','2026-11-05 14:51:19',43,NULL,NULL,58),(43,'coach','2026-11-05 14:51:19',43,NULL,5,NULL),(44,'gym','2026-11-05 14:51:19',44,2,NULL,NULL),(45,'session','2026-11-05 14:51:19',44,NULL,NULL,6),(46,'session','2026-11-05 14:51:19',44,NULL,NULL,79),(47,'session','2026-11-05 14:51:19',45,NULL,NULL,40),(48,'session','2026-11-05 14:51:19',45,NULL,NULL,49),(49,'session','2026-11-05 14:51:19',46,NULL,NULL,48),(50,'session','2026-11-05 14:51:19',47,NULL,NULL,57),(51,'session','2026-11-05 14:51:19',48,NULL,NULL,47),(52,'session','2026-11-05 14:51:19',48,NULL,NULL,27),(53,'session','2026-11-05 14:51:19',48,NULL,NULL,13),(54,'gym','2026-11-05 14:51:19',49,2,NULL,NULL),(55,'session','2026-11-05 14:51:19',50,NULL,NULL,51),(56,'session','2026-11-05 14:51:19',50,NULL,NULL,75),(57,'session','2026-11-05 14:51:19',51,NULL,NULL,21),(58,'session','2026-11-05 14:51:19',52,NULL,NULL,9),(59,'session','2026-11-05 14:51:19',52,NULL,NULL,56),(60,'session','2026-11-05 14:51:19',52,NULL,NULL,79),(61,'gym','2026-11-05 14:51:19',52,1,NULL,NULL),(62,'session','2026-11-05 14:51:19',53,NULL,NULL,54),(63,'session','2026-11-05 14:51:19',53,NULL,NULL,77),(64,'session','2026-11-05 14:51:19',53,NULL,NULL,46),(65,'session','2026-11-05 14:51:19',53,NULL,NULL,42),(66,'session','2026-11-05 14:51:19',55,NULL,NULL,57),(67,'session','2026-11-05 14:51:19',56,NULL,NULL,19),(68,'session','2026-11-05 14:51:19',56,NULL,NULL,25),(69,'coach','2026-11-05 14:51:19',56,NULL,10,NULL),(70,'session','2026-11-05 14:51:19',57,NULL,NULL,4),(71,'session','2026-11-05 14:51:19',57,NULL,NULL,44),(72,'session','2026-11-05 14:51:19',57,NULL,NULL,75),(73,'session','2026-11-05 14:51:19',57,NULL,NULL,22),(74,'coach','2026-11-05 14:51:19',58,NULL,5,NULL),(75,'session','2026-11-05 14:51:19',58,NULL,NULL,36),(76,'coach','2026-11-05 14:51:19',59,NULL,8,NULL);
/*!40000 ALTER TABLE `favorite` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gym`
--

DROP TABLE IF EXISTS `gym`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gym` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `postal_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(9,6) DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opening_hours` json DEFAULT NULL,
  `photos` json NOT NULL,
  `created_at` datetime NOT NULL,
  `manager_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gym_city` (`city`),
  KEY `IDX_7F27DBED783E3463` (`manager_id`),
  CONSTRAINT `FK_7F27DBED783E3463` FOREIGN KEY (`manager_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gym`
--

LOCK TABLES `gym` WRITE;
/*!40000 ALTER TABLE `gym` DISABLE KEYS */;
INSERT INTO `gym` VALUES (1,'FitBook Flagey','Salle de sport à Ixelles : plateau de musculation, studio de cours collectifs et vestiaires.','Place Eugène Flagey 18','Ixelles','1050',50.827500,4.372000,'0552232793','contact.ixelles@fitbook.test','{\"jeudi\": \"07:00-22:00\", \"lundi\": \"07:00-22:00\", \"mardi\": \"07:00-22:00\", \"samedi\": \"09:00-18:00\", \"dimanche\": \"09:00-14:00\", \"mercredi\": \"07:00-22:00\", \"vendredi\": \"07:00-21:00\"}','[\"https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=72\"]','2026-11-05 14:51:17',2),(2,'FitBook Parvis','Salle de sport à Saint-Gilles : plateau de musculation, studio de cours collectifs et vestiaires.','Parvis de Saint-Gilles 31','Saint-Gilles','1060',50.827600,4.346000,'0151 963216','contact.saintgilles@fitbook.test','{\"jeudi\": \"07:00-22:00\", \"lundi\": \"07:00-22:00\", \"mardi\": \"07:00-22:00\", \"samedi\": \"09:00-18:00\", \"dimanche\": \"09:00-14:00\", \"mercredi\": \"07:00-22:00\", \"vendredi\": \"07:00-21:00\"}','[\"https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=72\"]','2026-11-05 14:51:17',3),(3,'FitBook Jourdan','Salle de sport à Etterbeek : plateau de musculation, studio de cours collectifs et vestiaires.','Place Jourdan 12','Etterbeek','1040',50.837000,4.379000,'0328615801','contact.etterbeek@fitbook.test','{\"jeudi\": \"07:00-22:00\", \"lundi\": \"07:00-22:00\", \"mardi\": \"07:00-22:00\", \"samedi\": \"09:00-18:00\", \"dimanche\": \"09:00-14:00\", \"mercredi\": \"07:00-22:00\", \"vendredi\": \"07:00-21:00\"}','[\"https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=72\"]','2026-11-05 14:51:17',4),(4,'FitBook Colignon','Salle de sport à Schaerbeek : plateau de musculation, studio de cours collectifs et vestiaires.','Place Colignon 5','Schaerbeek','1030',50.866000,4.378000,'081 32 98 19','contact.schaerbeek@fitbook.test','{\"jeudi\": \"07:00-22:00\", \"lundi\": \"07:00-22:00\", \"mardi\": \"07:00-22:00\", \"samedi\": \"09:00-18:00\", \"dimanche\": \"09:00-14:00\", \"mercredi\": \"07:00-22:00\", \"vendredi\": \"07:00-21:00\"}','[\"https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=72\"]','2026-11-05 14:51:17',5),(5,'FitBook Saint-Job','Salle de sport à Uccle : plateau de musculation, studio de cours collectifs et vestiaires.','Place de Saint-Job 22','Uccle','1180',50.793000,4.365000,'0695 03 60 17','contact.uccle@fitbook.test','{\"jeudi\": \"07:00-22:00\", \"lundi\": \"07:00-22:00\", \"mardi\": \"07:00-22:00\", \"samedi\": \"09:00-18:00\", \"dimanche\": \"09:00-14:00\", \"mercredi\": \"07:00-22:00\", \"vendredi\": \"07:00-21:00\"}','[\"https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=72\"]','2026-11-05 14:51:17',6),(6,'FitBook Woluwe','Salle de sport à Woluwe-Saint-Lambert : plateau de musculation, studio de cours collectifs et vestiaires.','Rue Saint-Lambert 200','Woluwe-Saint-Lambert','1200',50.846000,4.432000,'053 066008','contact.woluwesaintlambert@fitbook.test','{\"jeudi\": \"07:00-22:00\", \"lundi\": \"07:00-22:00\", \"mardi\": \"07:00-22:00\", \"samedi\": \"09:00-18:00\", \"dimanche\": \"09:00-14:00\", \"mercredi\": \"07:00-22:00\", \"vendredi\": \"07:00-21:00\"}','[\"https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=72\"]','2026-11-05 14:51:17',2),(7,'FitBook Vaillance','Salle de sport à Anderlecht : plateau de musculation, studio de cours collectifs et vestiaires.','Place de la Vaillance 8','Anderlecht','1070',50.836000,4.308000,'+32(0)6 2248733','contact.anderlecht@fitbook.test','{\"jeudi\": \"07:00-22:00\", \"lundi\": \"07:00-22:00\", \"mardi\": \"07:00-22:00\", \"samedi\": \"09:00-18:00\", \"dimanche\": \"09:00-14:00\", \"mercredi\": \"07:00-22:00\", \"vendredi\": \"07:00-21:00\"}','[\"https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=72\"]','2026-11-05 14:51:17',3),(8,'FitBook Sainte-Catherine','Salle de sport à Bruxelles : plateau de musculation, studio de cours collectifs et vestiaires.','Place Sainte-Catherine 40','Bruxelles','1000',50.851000,4.348000,'0575 103662','contact.bruxelles@fitbook.test','{\"jeudi\": \"07:00-22:00\", \"lundi\": \"07:00-22:00\", \"mardi\": \"07:00-22:00\", \"samedi\": \"09:00-18:00\", \"dimanche\": \"09:00-14:00\", \"mercredi\": \"07:00-22:00\", \"vendredi\": \"07:00-21:00\"}','[\"https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=72\"]','2026-11-05 14:51:17',4),(9,'FitBook Saint-Denis','Salle de sport à Forest : plateau de musculation, studio de cours collectifs et vestiaires.','Place Saint-Denis 3','Forest','1190',50.811000,4.320000,'0060501330','contact.forest@fitbook.test','{\"jeudi\": \"07:00-22:00\", \"lundi\": \"07:00-22:00\", \"mardi\": \"07:00-22:00\", \"samedi\": \"09:00-18:00\", \"dimanche\": \"09:00-14:00\", \"mercredi\": \"07:00-22:00\", \"vendredi\": \"07:00-21:00\"}','[\"https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=72\"]','2026-11-05 14:51:17',5),(10,'FitBook Reine Astrid','Salle de sport à Jette : plateau de musculation, studio de cours collectifs et vestiaires.','Place Reine Astrid 14','Jette','1090',50.879000,4.327000,'0126257596','contact.jette@fitbook.test','{\"jeudi\": \"07:00-22:00\", \"lundi\": \"07:00-22:00\", \"mardi\": \"07:00-22:00\", \"samedi\": \"09:00-18:00\", \"dimanche\": \"09:00-14:00\", \"mercredi\": \"07:00-22:00\", \"vendredi\": \"07:00-21:00\"}','[\"https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=72\", \"https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=72\"]','2026-11-05 14:51:17',6);
/*!40000 ALTER TABLE `gym` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messenger_messages`
--

DROP TABLE IF EXISTS `messenger_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messenger_messages` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `headers` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750` (`queue_name`,`available_at`,`delivered_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messenger_messages`
--

LOCK TABLES `messenger_messages` WRITE;
/*!40000 ALTER TABLE `messenger_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messenger_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification`
--

DROP TABLE IF EXISTS `notification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `user_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_BF5476CAA76ED395` (`user_id`),
  CONSTRAINT `FK_BF5476CAA76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification`
--

LOCK TABLES `notification` WRITE;
/*!40000 ALTER TABLE `notification` DISABLE KEYS */;
INSERT INTO `notification` VALUES (1,'bienvenue','Bienvenue sur FitBook ! Découvrez les séances près de chez vous.',0,'2026-11-05 14:51:19',22),(2,'bienvenue','Bienvenue sur FitBook ! Découvrez les séances près de chez vous.',0,'2026-11-05 14:51:19',23),(3,'bienvenue','Bienvenue sur FitBook ! Découvrez les séances près de chez vous.',0,'2026-11-05 14:51:19',24),(4,'bienvenue','Bienvenue sur FitBook ! Découvrez les séances près de chez vous.',0,'2026-11-05 14:51:19',25),(5,'bienvenue','Bienvenue sur FitBook ! Découvrez les séances près de chez vous.',0,'2026-11-05 14:51:19',26),(6,'bienvenue','Bienvenue sur FitBook ! Découvrez les séances près de chez vous.',0,'2026-11-05 14:51:19',27),(7,'bienvenue','Bienvenue sur FitBook ! Découvrez les séances près de chez vous.',0,'2026-11-05 14:51:19',28),(8,'bienvenue','Bienvenue sur FitBook ! Découvrez les séances près de chez vous.',0,'2026-11-05 14:51:19',29),(9,'bienvenue','Bienvenue sur FitBook ! Découvrez les séances près de chez vous.',0,'2026-11-05 14:51:19',30),(10,'bienvenue','Bienvenue sur FitBook ! Découvrez les séances près de chez vous.',0,'2026-11-05 14:51:19',31),(11,'moderation','Des avis sont en attente de modération.',0,'2026-11-05 14:51:19',1);
/*!40000 ALTER TABLE `notification` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reservation`
--

DROP TABLE IF EXISTS `reservation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reservation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `qr_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reminder_sent_at` datetime DEFAULT NULL,
  `user_id` int NOT NULL,
  `session_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reservation_user_session` (`user_id`,`session_id`),
  UNIQUE KEY `UNIQ_42C849551AE26361` (`qr_token`),
  KEY `idx_reservation_session_status` (`session_id`,`status`),
  KEY `IDX_42C84955A76ED395` (`user_id`),
  KEY `IDX_42C84955613FECDF` (`session_id`),
  CONSTRAINT `FK_42C84955613FECDF` FOREIGN KEY (`session_id`) REFERENCES `session` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_42C84955A76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_reservation_status` CHECK ((`status` in (_utf8mb4'confirmee',_utf8mb4'annulee',_utf8mb4'terminee')))
) ENGINE=InnoDB AUTO_INCREMENT=504 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservation`
--

LOCK TABLES `reservation` WRITE;
/*!40000 ALTER TABLE `reservation` DISABLE KEYS */;
INSERT INTO `reservation` VALUES (1,'confirmee','2026-11-03 17:00:00',NULL,NULL,NULL,27,5),(2,'confirmee','2026-11-04 17:00:00',NULL,NULL,NULL,36,5),(3,'confirmee','2026-11-01 17:00:00',NULL,NULL,NULL,45,5),(4,'confirmee','2026-10-31 11:00:00',NULL,NULL,NULL,48,6),(5,'annulee','2026-11-05 11:00:00','2026-11-04 14:51:19',NULL,NULL,44,6),(6,'confirmee','2026-11-01 11:00:00',NULL,NULL,NULL,59,6),(7,'confirmee','2026-10-28 11:00:00',NULL,NULL,NULL,57,6),(8,'confirmee','2026-11-24 11:00:00',NULL,NULL,NULL,53,7),(9,'annulee','2026-11-22 11:00:00','2026-11-04 14:51:19',NULL,NULL,52,7),(10,'confirmee','2026-11-25 11:00:00',NULL,NULL,NULL,34,7),(11,'confirmee','2026-11-28 11:00:00',NULL,NULL,NULL,56,7),(12,'confirmee','2026-11-21 08:00:00',NULL,NULL,NULL,28,8),(13,'confirmee','2026-11-16 08:00:00',NULL,NULL,NULL,38,8),(14,'confirmee','2026-11-22 08:00:00',NULL,NULL,NULL,43,8),(15,'confirmee','2026-11-24 08:00:00',NULL,NULL,NULL,22,8),(16,'confirmee','2026-11-18 08:00:00',NULL,NULL,NULL,35,8),(17,'confirmee','2026-11-25 08:00:00',NULL,NULL,NULL,33,8),(18,'confirmee','2026-11-22 08:00:00',NULL,NULL,NULL,53,8),(19,'confirmee','2026-11-22 08:00:00',NULL,NULL,NULL,45,8),(20,'confirmee','2026-11-24 08:00:00',NULL,NULL,NULL,23,8),(21,'confirmee','2026-11-22 08:00:00',NULL,NULL,NULL,49,8),(22,'confirmee','2026-11-18 08:00:00',NULL,NULL,NULL,58,8),(23,'confirmee','2026-11-24 08:00:00',NULL,NULL,NULL,42,8),(24,'confirmee','2026-11-24 08:00:00',NULL,NULL,NULL,56,8),(25,'confirmee','2026-11-26 16:00:00',NULL,NULL,NULL,42,9),(26,'confirmee','2026-11-05 16:00:00',NULL,NULL,NULL,38,10),(27,'confirmee','2026-11-10 16:00:00',NULL,NULL,NULL,27,10),(28,'confirmee','2026-11-14 16:00:00',NULL,NULL,NULL,36,10),(29,'confirmee','2026-11-11 16:00:00',NULL,NULL,NULL,25,10),(30,'confirmee','2026-11-13 16:00:00',NULL,NULL,NULL,32,10),(31,'confirmee','2026-11-12 16:00:00',NULL,NULL,NULL,49,10),(32,'confirmee','2026-11-14 16:00:00',NULL,NULL,NULL,51,10),(33,'confirmee','2026-11-06 16:00:00',NULL,NULL,NULL,45,10),(34,'confirmee','2026-11-05 16:00:00',NULL,NULL,NULL,47,10),(35,'confirmee','2026-11-04 06:00:00',NULL,NULL,NULL,45,11),(36,'confirmee','2026-11-02 06:00:00',NULL,NULL,NULL,43,11),(37,'confirmee','2026-11-04 06:00:00',NULL,NULL,NULL,41,11),(38,'confirmee','2026-11-01 06:00:00',NULL,NULL,NULL,25,11),(39,'confirmee','2026-11-04 06:00:00',NULL,NULL,NULL,35,11),(40,'confirmee','2026-11-08 06:00:00',NULL,NULL,NULL,55,11),(41,'confirmee','2026-11-04 06:00:00',NULL,NULL,NULL,38,11),(42,'confirmee','2026-11-09 06:00:00',NULL,NULL,NULL,22,11),(43,'confirmee','2026-11-24 06:30:00',NULL,NULL,NULL,59,12),(44,'confirmee','2026-11-15 06:30:00',NULL,NULL,NULL,55,12),(45,'confirmee','2026-11-21 06:30:00',NULL,NULL,NULL,41,12),(46,'confirmee','2026-11-23 06:30:00',NULL,NULL,NULL,28,12),(47,'confirmee','2026-11-15 06:30:00',NULL,NULL,NULL,46,12),(48,'confirmee','2026-11-27 11:00:00',NULL,NULL,NULL,56,13),(49,'confirmee','2026-11-26 11:00:00',NULL,NULL,NULL,28,13),(50,'confirmee','2026-11-25 11:00:00',NULL,NULL,NULL,24,13),(51,'confirmee','2026-11-25 11:00:00',NULL,NULL,NULL,43,13),(52,'confirmee','2026-11-26 11:00:00',NULL,NULL,NULL,57,13),(53,'confirmee','2026-11-29 11:00:00',NULL,NULL,NULL,22,13),(54,'confirmee','2026-11-21 11:00:00',NULL,NULL,NULL,59,13),(55,'confirmee','2026-11-28 11:00:00',NULL,NULL,NULL,37,13),(56,'confirmee','2026-11-20 17:00:00',NULL,NULL,NULL,25,14),(57,'confirmee','2026-11-21 17:00:00',NULL,NULL,NULL,49,14),(58,'confirmee','2026-11-18 17:00:00',NULL,NULL,NULL,53,14),(59,'confirmee','2026-11-23 17:00:00',NULL,NULL,NULL,33,14),(60,'confirmee','2026-11-18 17:00:00',NULL,NULL,NULL,45,14),(61,'confirmee','2026-11-17 17:00:00',NULL,NULL,NULL,30,14),(62,'confirmee','2026-11-24 17:00:00',NULL,NULL,NULL,28,14),(63,'confirmee','2026-11-15 17:00:00',NULL,NULL,NULL,48,14),(64,'confirmee','2026-11-20 17:00:00',NULL,NULL,NULL,57,14),(65,'confirmee','2026-11-23 17:00:00',NULL,NULL,NULL,35,14),(66,'confirmee','2026-11-21 17:00:00',NULL,NULL,NULL,37,14),(67,'confirmee','2026-11-19 16:00:00',NULL,NULL,NULL,43,18),(68,'confirmee','2026-11-20 16:00:00',NULL,NULL,NULL,23,18),(69,'annulee','2026-11-22 16:00:00','2026-11-04 14:51:19',NULL,NULL,51,18),(70,'confirmee','2026-11-23 16:00:00',NULL,NULL,NULL,24,18),(71,'confirmee','2026-11-25 16:00:00',NULL,NULL,NULL,33,18),(72,'confirmee','2026-11-18 16:00:00',NULL,NULL,NULL,59,18),(73,'confirmee','2026-11-13 06:00:00',NULL,NULL,NULL,46,19),(74,'confirmee','2026-11-17 06:00:00',NULL,NULL,NULL,59,19),(75,'confirmee','2026-11-12 06:00:00',NULL,NULL,NULL,55,19),(76,'confirmee','2026-11-17 06:00:00',NULL,NULL,NULL,53,19),(77,'confirmee','2026-11-14 06:00:00',NULL,NULL,NULL,42,19),(78,'confirmee','2026-11-11 06:00:00',NULL,NULL,NULL,49,19),(79,'confirmee','2026-11-15 09:00:00',NULL,NULL,NULL,33,20),(80,'confirmee','2026-11-14 09:00:00',NULL,NULL,NULL,56,20),(81,'confirmee','2026-11-11 09:00:00',NULL,NULL,NULL,53,20),(82,'confirmee','2026-11-16 09:00:00',NULL,NULL,NULL,27,20),(83,'confirmee','2026-11-09 09:00:00',NULL,NULL,NULL,25,20),(84,'confirmee','2026-11-14 09:00:00',NULL,NULL,NULL,50,20),(85,'confirmee','2026-11-12 09:00:00',NULL,NULL,NULL,58,20),(86,'confirmee','2026-11-15 09:00:00',NULL,NULL,NULL,42,20),(87,'confirmee','2026-11-16 09:00:00',NULL,NULL,NULL,52,20),(88,'annulee','2026-11-14 09:00:00','2026-11-04 14:51:19',NULL,NULL,59,20),(89,'confirmee','2026-11-18 09:00:00',NULL,NULL,NULL,39,20),(90,'confirmee','2026-11-18 18:30:00',NULL,NULL,NULL,49,21),(91,'confirmee','2026-11-23 18:30:00',NULL,NULL,NULL,51,21),(92,'confirmee','2026-11-18 18:30:00',NULL,NULL,NULL,47,21),(93,'confirmee','2026-11-11 18:00:00',NULL,NULL,NULL,53,22),(94,'confirmee','2026-11-11 18:00:00',NULL,NULL,NULL,56,22),(95,'annulee','2026-11-13 18:00:00','2026-11-04 14:51:19',NULL,NULL,31,22),(96,'confirmee','2026-11-11 18:00:00',NULL,NULL,NULL,25,22),(97,'confirmee','2026-11-13 18:00:00',NULL,NULL,NULL,48,22),(98,'confirmee','2026-11-03 06:00:00',NULL,NULL,NULL,35,23),(99,'confirmee','2026-11-05 06:00:00',NULL,NULL,NULL,50,23),(100,'confirmee','2026-10-29 06:00:00',NULL,NULL,NULL,24,23),(101,'confirmee','2026-11-03 06:00:00',NULL,NULL,NULL,49,23),(102,'confirmee','2026-11-07 06:00:00',NULL,NULL,NULL,58,23),(103,'confirmee','2026-11-03 06:00:00',NULL,NULL,NULL,39,23),(104,'confirmee','2026-11-03 07:00:00',NULL,NULL,NULL,54,24),(105,'confirmee','2026-11-01 07:00:00',NULL,NULL,NULL,23,24),(106,'confirmee','2026-11-01 07:00:00',NULL,NULL,NULL,52,24),(107,'confirmee','2026-11-03 07:00:00',NULL,NULL,NULL,43,24),(108,'confirmee','2026-11-07 07:00:00',NULL,NULL,NULL,57,24),(109,'confirmee','2026-11-02 07:00:00',NULL,NULL,NULL,55,24),(110,'confirmee','2026-11-02 07:00:00',NULL,NULL,NULL,22,24),(111,'confirmee','2026-11-02 07:00:00',NULL,NULL,NULL,28,24),(112,'confirmee','2026-11-01 07:00:00',NULL,NULL,NULL,46,24),(113,'confirmee','2026-12-01 18:00:00',NULL,NULL,NULL,37,25),(114,'confirmee','2026-11-03 09:00:00',NULL,NULL,NULL,51,27),(115,'confirmee','2026-10-29 09:00:00',NULL,NULL,NULL,58,27),(116,'confirmee','2026-11-01 09:00:00',NULL,NULL,NULL,29,27),(117,'confirmee','2026-11-01 09:00:00',NULL,NULL,NULL,54,27),(118,'confirmee','2026-11-05 09:00:00',NULL,NULL,NULL,47,27),(119,'confirmee','2026-11-20 07:00:00',NULL,NULL,NULL,44,28),(120,'confirmee','2026-11-17 07:00:00',NULL,NULL,NULL,37,28),(121,'confirmee','2026-11-19 07:00:00',NULL,NULL,NULL,53,28),(122,'confirmee','2026-11-20 07:00:00',NULL,NULL,NULL,28,28),(123,'confirmee','2026-11-20 07:00:00',NULL,NULL,NULL,26,28),(124,'confirmee','2026-11-15 07:00:00',NULL,NULL,NULL,36,28),(125,'confirmee','2026-11-11 07:00:00',NULL,NULL,NULL,46,28),(126,'confirmee','2026-11-16 07:00:00',NULL,NULL,NULL,35,28),(127,'confirmee','2026-11-20 07:00:00',NULL,NULL,NULL,24,28),(128,'confirmee','2026-11-12 07:00:00',NULL,NULL,NULL,48,28),(129,'confirmee','2026-11-15 07:00:00',NULL,NULL,NULL,43,28),(130,'confirmee','2026-11-20 07:00:00',NULL,NULL,NULL,52,28),(131,'confirmee','2026-11-17 07:00:00',NULL,NULL,NULL,22,28),(132,'confirmee','2026-11-17 07:00:00',NULL,NULL,NULL,59,28),(133,'confirmee','2026-11-21 11:00:00',NULL,NULL,NULL,56,29),(134,'confirmee','2026-11-24 11:00:00',NULL,NULL,NULL,29,29),(135,'confirmee','2026-11-22 11:00:00',NULL,NULL,NULL,59,29),(136,'confirmee','2026-11-19 11:00:00',NULL,NULL,NULL,24,29),(137,'confirmee','2026-11-28 11:00:00',NULL,NULL,NULL,42,29),(138,'confirmee','2026-11-19 11:00:00',NULL,NULL,NULL,52,29),(139,'confirmee','2026-11-26 11:00:00',NULL,NULL,NULL,27,29),(140,'confirmee','2026-11-18 11:30:00',NULL,NULL,NULL,55,30),(141,'confirmee','2026-11-14 11:30:00',NULL,NULL,NULL,54,30),(142,'confirmee','2026-11-12 11:30:00',NULL,NULL,NULL,50,30),(143,'confirmee','2026-11-11 11:30:00',NULL,NULL,NULL,28,30),(144,'confirmee','2026-11-11 11:30:00',NULL,NULL,NULL,37,30),(145,'confirmee','2026-11-16 11:30:00',NULL,NULL,NULL,57,30),(146,'confirmee','2026-11-17 11:30:00',NULL,NULL,NULL,24,30),(147,'confirmee','2026-11-17 11:30:00',NULL,NULL,NULL,35,30),(148,'confirmee','2026-11-11 11:30:00',NULL,NULL,NULL,41,30),(149,'confirmee','2026-11-20 11:30:00',NULL,NULL,NULL,31,30),(150,'confirmee','2026-11-16 11:30:00',NULL,NULL,NULL,51,30),(151,'confirmee','2026-10-30 11:00:00',NULL,NULL,NULL,53,31),(152,'confirmee','2026-11-23 18:30:00',NULL,NULL,NULL,37,32),(153,'confirmee','2026-11-15 17:00:00',NULL,NULL,NULL,25,33),(154,'confirmee','2026-11-06 17:00:00',NULL,NULL,NULL,38,33),(155,'confirmee','2026-11-13 17:00:00',NULL,NULL,NULL,30,33),(156,'confirmee','2026-11-09 17:00:00',NULL,NULL,NULL,50,33),(157,'confirmee','2026-11-12 17:00:00',NULL,NULL,NULL,32,33),(158,'confirmee','2026-11-14 17:00:00',NULL,NULL,NULL,43,33),(159,'confirmee','2026-11-06 17:00:00',NULL,NULL,NULL,48,33),(160,'confirmee','2026-11-03 07:00:00',NULL,NULL,NULL,27,34),(161,'confirmee','2026-11-05 07:00:00',NULL,NULL,NULL,23,34),(162,'confirmee','2026-11-07 07:00:00',NULL,NULL,NULL,43,34),(163,'confirmee','2026-11-03 07:00:00',NULL,NULL,NULL,52,34),(164,'annulee','2026-11-01 07:00:00','2026-11-04 14:51:19',NULL,NULL,37,34),(165,'annulee','2026-11-10 07:00:00','2026-11-04 14:51:19',NULL,NULL,24,34),(166,'confirmee','2026-11-05 07:00:00',NULL,NULL,NULL,55,34),(167,'confirmee','2026-11-21 11:30:00',NULL,NULL,NULL,35,35),(168,'confirmee','2026-11-19 11:30:00',NULL,NULL,NULL,52,35),(169,'confirmee','2026-11-15 11:30:00',NULL,NULL,NULL,48,35),(170,'confirmee','2026-11-21 11:30:00',NULL,NULL,NULL,36,35),(171,'confirmee','2026-11-16 11:30:00',NULL,NULL,NULL,57,35),(172,'confirmee','2026-11-15 11:30:00',NULL,NULL,NULL,25,35),(173,'confirmee','2026-11-22 11:30:00',NULL,NULL,NULL,28,35),(174,'annulee','2026-11-14 11:30:00','2026-11-04 14:51:19',NULL,NULL,24,35),(175,'confirmee','2026-11-20 11:30:00',NULL,NULL,NULL,33,35),(176,'confirmee','2026-11-25 18:00:00',NULL,NULL,NULL,45,36),(177,'confirmee','2026-12-02 18:00:00',NULL,NULL,NULL,51,36),(178,'confirmee','2026-11-24 18:00:00',NULL,NULL,NULL,24,36),(179,'confirmee','2026-11-30 18:00:00',NULL,NULL,NULL,27,36),(180,'confirmee','2026-11-25 18:00:00',NULL,NULL,NULL,36,36),(181,'confirmee','2026-11-23 18:00:00',NULL,NULL,NULL,55,36),(182,'confirmee','2026-11-23 18:00:00',NULL,NULL,NULL,31,36),(183,'confirmee','2026-11-29 18:00:00',NULL,NULL,NULL,57,36),(184,'confirmee','2026-11-17 16:30:00',NULL,NULL,NULL,32,37),(185,'annulee','2026-11-17 16:30:00','2026-11-04 14:51:19',NULL,NULL,56,37),(186,'confirmee','2026-11-25 16:30:00',NULL,NULL,NULL,31,37),(187,'confirmee','2026-11-25 18:00:00',NULL,NULL,NULL,48,38),(188,'annulee','2026-11-25 18:00:00','2026-11-04 14:51:19',NULL,NULL,34,38),(189,'confirmee','2026-11-07 17:00:00',NULL,NULL,NULL,54,39),(190,'confirmee','2026-11-07 17:00:00',NULL,NULL,NULL,27,39),(191,'confirmee','2026-11-28 18:00:00',NULL,NULL,NULL,33,40),(192,'confirmee','2026-11-21 18:00:00',NULL,NULL,NULL,24,40),(193,'confirmee','2026-11-26 18:00:00',NULL,NULL,NULL,31,40),(194,'confirmee','2026-11-22 07:00:00',NULL,NULL,NULL,48,41),(195,'confirmee','2026-11-06 07:00:00',NULL,NULL,NULL,32,42),(196,'confirmee','2026-11-12 07:00:00',NULL,NULL,NULL,50,42),(197,'confirmee','2026-11-06 07:00:00',NULL,NULL,NULL,52,42),(198,'confirmee','2026-11-04 07:00:00',NULL,NULL,NULL,48,42),(199,'confirmee','2026-11-03 07:00:00',NULL,NULL,NULL,43,42),(200,'confirmee','2026-11-09 07:00:00',NULL,NULL,NULL,57,42),(201,'confirmee','2026-11-09 07:00:00',NULL,NULL,NULL,35,42),(202,'confirmee','2026-11-02 06:30:00',NULL,NULL,NULL,45,44),(203,'confirmee','2026-11-02 06:30:00',NULL,NULL,NULL,28,44),(204,'confirmee','2026-10-31 06:30:00',NULL,NULL,NULL,23,44),(205,'confirmee','2026-10-31 06:30:00',NULL,NULL,NULL,36,44),(206,'confirmee','2026-11-06 06:30:00',NULL,NULL,NULL,43,44),(207,'confirmee','2026-11-08 11:00:00',NULL,NULL,NULL,49,45),(208,'confirmee','2026-11-05 11:00:00',NULL,NULL,NULL,22,45),(209,'confirmee','2026-11-14 11:00:00',NULL,NULL,NULL,58,45),(210,'confirmee','2026-11-07 11:00:00',NULL,NULL,NULL,33,45),(211,'confirmee','2026-11-12 11:00:00',NULL,NULL,NULL,57,45),(212,'confirmee','2026-11-10 11:00:00',NULL,NULL,NULL,59,45),(213,'confirmee','2026-11-07 11:00:00',NULL,NULL,NULL,52,45),(214,'confirmee','2026-11-09 11:00:00',NULL,NULL,NULL,46,45),(215,'confirmee','2026-11-09 11:00:00',NULL,NULL,NULL,50,45),(216,'confirmee','2026-11-04 07:30:00',NULL,NULL,NULL,23,46),(217,'confirmee','2026-11-06 07:30:00',NULL,NULL,NULL,24,46),(218,'confirmee','2026-11-08 07:30:00',NULL,NULL,NULL,22,46),(219,'confirmee','2026-11-10 07:30:00',NULL,NULL,NULL,35,46),(220,'confirmee','2026-11-03 07:30:00',NULL,NULL,NULL,59,46),(221,'confirmee','2026-11-08 07:30:00',NULL,NULL,NULL,26,46),(222,'confirmee','2026-11-06 07:30:00',NULL,NULL,NULL,38,46),(223,'confirmee','2026-11-05 07:30:00',NULL,NULL,NULL,29,46),(224,'confirmee','2026-11-04 17:30:00',NULL,NULL,NULL,32,47),(225,'annulee','2026-11-06 17:30:00','2026-11-04 14:51:19',NULL,NULL,33,47),(226,'confirmee','2026-11-06 17:30:00',NULL,NULL,NULL,49,47),(227,'confirmee','2026-11-05 17:30:00',NULL,NULL,NULL,29,47),(228,'confirmee','2026-11-04 17:30:00',NULL,NULL,NULL,54,47),(229,'annulee','2026-11-03 18:30:00','2026-11-04 14:51:19',NULL,NULL,42,48),(230,'confirmee','2026-11-08 18:30:00',NULL,NULL,NULL,38,48),(231,'confirmee','2026-10-31 18:30:00',NULL,NULL,NULL,45,48),(232,'confirmee','2026-11-01 18:30:00',NULL,NULL,NULL,34,48),(233,'confirmee','2026-11-03 18:30:00',NULL,NULL,NULL,58,48),(234,'confirmee','2026-11-06 09:30:00',NULL,NULL,NULL,52,49),(235,'confirmee','2026-11-06 09:30:00',NULL,NULL,NULL,23,49),(236,'confirmee','2026-11-07 09:30:00',NULL,NULL,NULL,40,49),(237,'confirmee','2026-10-30 09:30:00',NULL,NULL,NULL,53,49),(238,'confirmee','2026-11-03 09:30:00',NULL,NULL,NULL,33,49),(239,'confirmee','2026-10-29 09:30:00',NULL,NULL,NULL,41,49),(240,'confirmee','2026-11-03 09:30:00',NULL,NULL,NULL,46,49),(241,'confirmee','2026-11-11 18:30:00',NULL,NULL,NULL,56,50),(242,'confirmee','2026-11-14 18:30:00',NULL,NULL,NULL,23,50),(243,'confirmee','2026-11-15 18:30:00',NULL,NULL,NULL,47,50),(244,'confirmee','2026-11-04 19:00:00',NULL,NULL,NULL,39,51),(245,'confirmee','2026-11-01 19:00:00',NULL,NULL,NULL,30,51),(246,'confirmee','2026-11-07 19:00:00',NULL,NULL,NULL,34,51),(247,'confirmee','2026-11-05 19:00:00',NULL,NULL,NULL,42,51),(248,'confirmee','2026-11-08 19:00:00',NULL,NULL,NULL,25,51),(249,'confirmee','2026-11-04 19:00:00',NULL,NULL,NULL,26,51),(250,'confirmee','2026-11-10 19:00:00',NULL,NULL,NULL,47,51),(251,'confirmee','2026-11-06 19:00:00',NULL,NULL,NULL,28,51),(252,'confirmee','2026-11-07 18:00:00',NULL,NULL,NULL,45,52),(253,'confirmee','2026-10-30 18:00:00',NULL,NULL,NULL,23,52),(254,'confirmee','2026-11-02 18:00:00',NULL,NULL,NULL,29,52),(255,'confirmee','2026-11-03 18:00:00',NULL,NULL,NULL,25,53),(256,'confirmee','2026-11-03 18:00:00',NULL,NULL,NULL,43,53),(257,'confirmee','2026-11-07 18:00:00',NULL,NULL,NULL,58,53),(258,'confirmee','2026-11-07 18:00:00',NULL,NULL,NULL,42,53),(259,'confirmee','2026-11-02 18:00:00',NULL,NULL,NULL,22,53),(260,'confirmee','2026-11-04 18:00:00',NULL,NULL,NULL,27,53),(261,'confirmee','2026-11-10 18:00:00',NULL,NULL,NULL,49,53),(262,'confirmee','2026-11-02 18:00:00',NULL,NULL,NULL,37,53),(263,'confirmee','2026-11-07 18:00:00',NULL,NULL,NULL,39,53),(264,'confirmee','2026-11-05 18:00:00',NULL,NULL,NULL,32,53),(265,'confirmee','2026-11-03 18:00:00',NULL,NULL,NULL,55,53),(266,'confirmee','2026-11-18 16:00:00',NULL,NULL,NULL,31,54),(267,'confirmee','2026-11-25 16:00:00',NULL,NULL,NULL,29,54),(268,'confirmee','2026-11-20 16:00:00',NULL,NULL,NULL,42,54),(269,'confirmee','2026-11-26 16:00:00',NULL,NULL,NULL,38,54),(270,'confirmee','2026-11-20 16:00:00',NULL,NULL,NULL,23,54),(271,'confirmee','2026-11-24 16:00:00',NULL,NULL,NULL,43,54),(272,'confirmee','2026-11-18 16:00:00',NULL,NULL,NULL,45,54),(273,'confirmee','2026-11-06 19:00:00',NULL,NULL,NULL,32,55),(274,'confirmee','2026-10-31 19:00:00',NULL,NULL,NULL,51,55),(275,'confirmee','2026-10-30 19:00:00',NULL,NULL,NULL,59,55),(276,'annulee','2026-11-04 19:00:00','2026-11-04 14:51:19',NULL,NULL,57,55),(277,'confirmee','2026-11-30 06:00:00',NULL,NULL,NULL,52,56),(278,'confirmee','2026-11-24 06:00:00',NULL,NULL,NULL,37,56),(279,'confirmee','2026-11-27 06:00:00',NULL,NULL,NULL,57,56),(280,'confirmee','2026-11-25 06:00:00',NULL,NULL,NULL,59,56),(281,'confirmee','2026-11-30 06:00:00',NULL,NULL,NULL,31,56),(282,'confirmee','2026-11-26 06:00:00',NULL,NULL,NULL,41,56),(283,'confirmee','2026-11-05 17:00:00',NULL,NULL,NULL,45,57),(284,'confirmee','2026-11-07 17:00:00',NULL,NULL,NULL,58,57),(285,'confirmee','2026-11-06 17:00:00',NULL,NULL,NULL,46,57),(286,'confirmee','2026-11-08 17:00:00',NULL,NULL,NULL,32,57),(287,'confirmee','2026-11-04 17:00:00',NULL,NULL,NULL,36,57),(288,'confirmee','2026-11-06 17:00:00',NULL,NULL,NULL,56,57),(289,'confirmee','2026-11-02 17:00:00',NULL,NULL,NULL,51,57),(290,'confirmee','2026-11-08 17:00:00',NULL,NULL,NULL,39,57),(291,'confirmee','2026-11-03 17:00:00',NULL,NULL,NULL,44,57),(292,'confirmee','2026-11-02 17:00:00',NULL,NULL,NULL,52,57),(293,'confirmee','2026-11-16 11:00:00',NULL,NULL,NULL,42,58),(294,'confirmee','2026-11-13 11:00:00',NULL,NULL,NULL,57,58),(295,'confirmee','2026-11-11 11:00:00',NULL,NULL,NULL,54,58),(296,'confirmee','2026-11-08 11:00:00',NULL,NULL,NULL,48,58),(297,'confirmee','2026-11-07 11:00:00',NULL,NULL,NULL,33,58),(298,'confirmee','2026-11-04 17:00:00',NULL,NULL,NULL,38,59),(299,'confirmee','2026-11-04 17:00:00',NULL,NULL,NULL,31,59),(300,'confirmee','2026-11-03 17:00:00',NULL,NULL,NULL,55,59),(301,'confirmee','2026-11-06 17:00:00',NULL,NULL,NULL,47,59),(302,'confirmee','2026-11-06 17:00:00',NULL,NULL,NULL,41,59),(303,'confirmee','2026-11-24 08:30:00',NULL,NULL,NULL,25,60),(304,'confirmee','2026-11-23 08:30:00',NULL,NULL,NULL,29,60),(305,'confirmee','2026-11-24 08:30:00',NULL,NULL,NULL,42,60),(306,'confirmee','2026-11-24 08:30:00',NULL,NULL,NULL,38,60),(307,'confirmee','2026-11-02 18:00:00',NULL,NULL,NULL,47,61),(308,'confirmee','2026-11-04 18:00:00',NULL,NULL,NULL,24,61),(309,'confirmee','2026-10-31 18:00:00',NULL,NULL,NULL,55,61),(310,'confirmee','2026-11-02 18:00:00',NULL,NULL,NULL,32,61),(311,'confirmee','2026-10-31 18:00:00',NULL,NULL,NULL,29,61),(312,'confirmee','2026-11-20 08:00:00',NULL,NULL,NULL,28,62),(313,'confirmee','2026-11-19 16:00:00',NULL,NULL,NULL,47,63),(314,'confirmee','2026-11-13 16:00:00',NULL,NULL,NULL,34,63),(315,'confirmee','2026-11-16 16:00:00',NULL,NULL,NULL,36,63),(316,'confirmee','2026-11-17 16:00:00',NULL,NULL,NULL,42,63),(317,'annulee','2026-11-12 16:00:00','2026-11-04 14:51:19',NULL,NULL,35,63),(318,'confirmee','2026-11-16 16:00:00',NULL,NULL,NULL,25,63),(319,'confirmee','2026-11-20 16:00:00',NULL,NULL,NULL,24,63),(320,'confirmee','2026-11-21 09:00:00',NULL,NULL,NULL,27,65),(321,'confirmee','2026-11-25 09:00:00',NULL,NULL,NULL,58,65),(322,'confirmee','2026-11-25 09:00:00',NULL,NULL,NULL,25,65),(323,'confirmee','2026-11-21 09:00:00',NULL,NULL,NULL,51,65),(324,'confirmee','2026-11-21 09:00:00',NULL,NULL,NULL,48,65),(325,'confirmee','2026-11-27 09:00:00',NULL,NULL,NULL,24,65),(326,'confirmee','2026-11-22 09:00:00',NULL,NULL,NULL,29,65),(327,'confirmee','2026-11-27 09:00:00',NULL,NULL,NULL,50,65),(328,'confirmee','2026-11-28 09:00:00',NULL,NULL,NULL,23,65),(329,'confirmee','2026-11-22 09:00:00',NULL,NULL,NULL,52,65),(330,'confirmee','2026-11-24 09:00:00',NULL,NULL,NULL,56,65),(331,'confirmee','2026-11-17 08:00:00',NULL,NULL,NULL,42,66),(332,'confirmee','2026-11-18 08:00:00',NULL,NULL,NULL,59,66),(333,'confirmee','2026-11-20 08:00:00',NULL,NULL,NULL,53,66),(334,'confirmee','2026-11-17 08:00:00',NULL,NULL,NULL,46,66),(335,'confirmee','2026-11-23 08:00:00',NULL,NULL,NULL,45,66),(336,'confirmee','2026-10-29 11:00:00',NULL,NULL,NULL,38,67),(337,'confirmee','2026-11-05 11:00:00',NULL,NULL,NULL,26,67),(338,'confirmee','2026-11-06 11:00:00',NULL,NULL,NULL,43,67),(339,'confirmee','2026-11-02 17:00:00',NULL,NULL,NULL,58,68),(340,'annulee','2026-11-01 17:00:00','2026-11-04 14:51:19',NULL,NULL,55,68),(341,'confirmee','2026-11-02 17:00:00',NULL,NULL,NULL,50,68),(342,'confirmee','2026-11-08 17:00:00',NULL,NULL,NULL,48,68),(343,'confirmee','2026-11-18 08:00:00',NULL,NULL,NULL,41,69),(344,'confirmee','2026-11-17 08:00:00',NULL,NULL,NULL,56,69),(345,'confirmee','2026-11-18 08:00:00',NULL,NULL,NULL,40,69),(346,'confirmee','2026-11-24 08:00:00',NULL,NULL,NULL,53,69),(347,'confirmee','2026-11-22 08:00:00',NULL,NULL,NULL,43,69),(348,'confirmee','2026-11-23 08:00:00',NULL,NULL,NULL,26,69),(349,'confirmee','2026-11-15 08:00:00',NULL,NULL,NULL,48,69),(350,'confirmee','2026-11-16 08:00:00',NULL,NULL,NULL,47,69),(351,'confirmee','2026-11-26 17:00:00',NULL,NULL,NULL,56,70),(352,'confirmee','2026-11-17 11:00:00',NULL,NULL,NULL,26,71),(353,'confirmee','2026-11-14 11:00:00',NULL,NULL,NULL,44,71),(354,'confirmee','2026-11-14 11:00:00',NULL,NULL,NULL,42,71),(355,'confirmee','2026-11-17 11:00:00',NULL,NULL,NULL,53,71),(356,'confirmee','2026-11-22 18:00:00',NULL,NULL,NULL,52,72),(357,'confirmee','2026-11-19 18:00:00',NULL,NULL,NULL,48,72),(358,'confirmee','2026-11-25 18:00:00',NULL,NULL,NULL,25,72),(359,'confirmee','2026-11-20 18:00:00',NULL,NULL,NULL,22,72),(360,'confirmee','2026-11-22 18:00:00',NULL,NULL,NULL,37,72),(361,'confirmee','2026-11-20 18:00:00',NULL,NULL,NULL,49,72),(362,'confirmee','2026-11-26 18:00:00',NULL,NULL,NULL,47,72),(363,'confirmee','2026-11-26 18:00:00',NULL,NULL,NULL,43,72),(364,'confirmee','2026-11-22 18:00:00',NULL,NULL,NULL,58,72),(365,'confirmee','2026-11-25 18:00:00',NULL,NULL,NULL,57,72),(366,'confirmee','2026-11-26 18:00:00',NULL,NULL,NULL,29,72),(367,'confirmee','2026-11-21 18:00:00',NULL,NULL,NULL,35,72),(368,'confirmee','2026-11-21 18:00:00',NULL,NULL,NULL,31,72),(369,'confirmee','2026-11-22 06:30:00',NULL,NULL,NULL,55,73),(370,'confirmee','2026-11-21 06:30:00',NULL,NULL,NULL,58,73),(371,'confirmee','2026-11-21 06:30:00',NULL,NULL,NULL,27,73),(372,'confirmee','2026-11-23 06:30:00',NULL,NULL,NULL,24,73),(373,'confirmee','2026-11-16 11:00:00',NULL,NULL,NULL,46,74),(374,'confirmee','2026-11-15 11:00:00',NULL,NULL,NULL,49,74),(375,'confirmee','2026-11-17 11:00:00',NULL,NULL,NULL,27,74),(376,'confirmee','2026-11-12 11:00:00',NULL,NULL,NULL,52,74),(377,'confirmee','2026-11-10 11:00:00',NULL,NULL,NULL,48,74),(378,'confirmee','2026-11-08 09:30:00',NULL,NULL,NULL,31,75),(379,'confirmee','2026-11-06 09:30:00',NULL,NULL,NULL,54,75),(380,'confirmee','2026-10-30 09:30:00',NULL,NULL,NULL,22,75),(381,'confirmee','2026-10-30 09:30:00',NULL,NULL,NULL,52,75),(382,'confirmee','2026-10-30 09:30:00',NULL,NULL,NULL,50,75),(383,'confirmee','2026-11-02 09:30:00',NULL,NULL,NULL,59,75),(384,'confirmee','2026-11-04 09:30:00',NULL,NULL,NULL,30,75),(385,'confirmee','2026-11-02 09:30:00',NULL,NULL,NULL,32,75),(386,'confirmee','2026-11-26 06:30:00',NULL,NULL,NULL,56,76),(387,'confirmee','2026-12-01 06:30:00',NULL,NULL,NULL,36,76),(388,'confirmee','2026-11-26 06:30:00',NULL,NULL,NULL,47,76),(389,'confirmee','2026-11-23 06:30:00',NULL,NULL,NULL,54,76),(390,'confirmee','2026-11-26 06:30:00',NULL,NULL,NULL,55,76),(391,'confirmee','2026-11-26 06:30:00',NULL,NULL,NULL,22,76),(392,'confirmee','2026-11-25 06:30:00',NULL,NULL,NULL,25,76),(393,'annulee','2026-11-26 06:30:00','2026-11-04 14:51:19',NULL,NULL,53,76),(394,'confirmee','2026-11-30 06:30:00',NULL,NULL,NULL,51,76),(395,'confirmee','2026-11-24 06:30:00',NULL,NULL,NULL,59,76),(396,'confirmee','2026-12-01 06:30:00',NULL,NULL,NULL,43,76),(397,'confirmee','2026-12-01 06:30:00',NULL,NULL,NULL,44,76),(398,'confirmee','2026-11-07 17:00:00',NULL,NULL,NULL,23,77),(399,'confirmee','2026-11-04 07:30:00',NULL,NULL,NULL,49,78),(400,'confirmee','2026-10-31 07:30:00',NULL,NULL,NULL,48,78),(401,'confirmee','2026-11-04 07:30:00',NULL,NULL,NULL,57,78),(402,'confirmee','2026-11-07 07:30:00',NULL,NULL,NULL,27,78),(403,'confirmee','2026-11-02 07:30:00',NULL,NULL,NULL,55,78),(404,'confirmee','2026-11-05 07:30:00',NULL,NULL,NULL,35,78),(405,'confirmee','2026-11-11 06:00:00',NULL,NULL,NULL,45,79),(406,'annulee','2026-11-11 06:00:00','2026-11-04 14:51:19',NULL,NULL,47,79),(407,'confirmee','2026-11-15 06:00:00',NULL,NULL,NULL,23,79),(408,'confirmee','2026-11-12 06:00:00',NULL,NULL,NULL,28,79),(409,'confirmee','2026-11-11 06:00:00',NULL,NULL,NULL,27,79),(410,'confirmee','2026-11-18 06:00:00',NULL,NULL,NULL,40,79),(411,'confirmee','2026-11-17 06:00:00',NULL,NULL,NULL,30,79),(412,'confirmee','2026-11-19 06:00:00',NULL,NULL,NULL,33,79),(413,'confirmee','2026-11-26 08:00:00',NULL,NULL,NULL,27,80),(414,'confirmee','2026-11-22 08:00:00',NULL,NULL,NULL,56,80),(415,'confirmee','2026-11-28 08:00:00',NULL,NULL,NULL,22,80),(416,'confirmee','2026-11-24 08:00:00',NULL,NULL,NULL,35,80),(417,'confirmee','2026-11-24 08:00:00',NULL,NULL,NULL,23,80),(418,'confirmee','2026-11-25 08:00:00',NULL,NULL,NULL,45,80),(419,'confirmee','2026-11-21 08:00:00',NULL,NULL,NULL,25,80),(420,'confirmee','2026-11-27 08:00:00',NULL,NULL,NULL,46,80),(421,'confirmee','2026-11-25 08:00:00',NULL,NULL,NULL,41,80),(422,'confirmee','2026-11-25 08:00:00',NULL,NULL,NULL,31,80),(423,'confirmee','2026-11-28 08:00:00',NULL,NULL,NULL,42,80),(424,'confirmee','2026-11-27 08:00:00',NULL,NULL,NULL,43,80),(425,'confirmee','2026-11-28 08:00:00',NULL,NULL,NULL,52,80),(426,'terminee','2026-11-02 18:30:00',NULL,NULL,NULL,43,81),(427,'terminee','2026-11-01 18:30:00',NULL,NULL,NULL,55,81),(428,'terminee','2026-10-31 18:30:00',NULL,NULL,NULL,26,81),(429,'terminee','2026-11-03 18:30:00',NULL,NULL,NULL,22,81),(430,'terminee','2026-10-26 18:30:00',NULL,NULL,NULL,59,81),(431,'terminee','2026-11-03 18:30:00',NULL,NULL,NULL,42,81),(432,'terminee','2026-10-17 06:00:00',NULL,NULL,NULL,48,82),(433,'terminee','2026-10-17 06:00:00',NULL,NULL,NULL,55,82),(434,'terminee','2026-10-12 06:00:00',NULL,NULL,NULL,34,82),(435,'terminee','2026-10-13 06:00:00',NULL,NULL,NULL,32,82),(436,'terminee','2026-10-08 06:00:00',NULL,NULL,NULL,59,82),(437,'terminee','2026-10-16 06:00:00',NULL,NULL,NULL,38,82),(438,'terminee','2026-10-14 06:00:00',NULL,NULL,NULL,43,82),(439,'terminee','2026-10-17 08:30:00',NULL,NULL,NULL,23,83),(440,'terminee','2026-10-21 07:00:00',NULL,NULL,NULL,32,84),(441,'terminee','2026-10-18 07:00:00',NULL,NULL,NULL,34,85),(442,'terminee','2026-10-21 07:00:00',NULL,NULL,NULL,57,85),(443,'terminee','2026-10-18 07:00:00',NULL,NULL,NULL,55,85),(444,'terminee','2026-10-17 07:00:00',NULL,NULL,NULL,37,85),(445,'terminee','2026-10-17 07:00:00',NULL,NULL,NULL,58,85),(446,'terminee','2026-10-25 07:00:00',NULL,NULL,NULL,33,85),(447,'terminee','2026-10-20 07:00:00',NULL,NULL,NULL,30,85),(448,'terminee','2026-10-25 07:00:00',NULL,NULL,NULL,54,85),(449,'terminee','2026-10-27 17:00:00',NULL,NULL,NULL,35,86),(450,'terminee','2026-10-25 17:00:00',NULL,NULL,NULL,32,86),(451,'terminee','2026-10-26 17:00:00',NULL,NULL,NULL,49,86),(452,'terminee','2026-10-25 17:00:00',NULL,NULL,NULL,37,86),(453,'terminee','2026-10-26 18:00:00',NULL,NULL,NULL,43,87),(454,'terminee','2026-10-30 18:00:00',NULL,NULL,NULL,27,87),(455,'terminee','2026-10-27 18:00:00',NULL,NULL,NULL,58,87),(456,'terminee','2026-10-22 18:00:00',NULL,NULL,NULL,44,87),(457,'terminee','2026-10-22 18:00:00',NULL,NULL,NULL,57,87),(458,'terminee','2026-10-25 18:00:00',NULL,NULL,NULL,26,87),(459,'terminee','2026-10-28 18:00:00',NULL,NULL,NULL,38,87),(460,'terminee','2026-10-19 15:00:00',NULL,NULL,NULL,54,88),(461,'terminee','2026-10-12 15:00:00',NULL,NULL,NULL,45,88),(462,'terminee','2026-10-12 15:00:00',NULL,NULL,NULL,38,88),(463,'terminee','2026-10-17 15:00:00',NULL,NULL,NULL,41,88),(464,'terminee','2026-10-23 18:30:00',NULL,NULL,NULL,25,89),(465,'terminee','2026-10-22 18:30:00',NULL,NULL,NULL,43,89),(466,'terminee','2026-10-28 18:30:00',NULL,NULL,NULL,45,89),(467,'terminee','2026-10-25 18:30:00',NULL,NULL,NULL,36,89),(468,'terminee','2026-10-23 18:30:00',NULL,NULL,NULL,38,89),(469,'terminee','2026-10-24 18:30:00',NULL,NULL,NULL,35,89),(470,'terminee','2026-10-29 18:30:00',NULL,NULL,NULL,50,89),(471,'terminee','2026-10-19 16:00:00',NULL,NULL,NULL,22,90),(472,'terminee','2026-10-12 16:00:00',NULL,NULL,NULL,27,90),(473,'terminee','2026-10-15 16:00:00',NULL,NULL,NULL,34,90),(474,'terminee','2026-10-10 16:00:00',NULL,NULL,NULL,40,90),(475,'terminee','2026-10-18 11:30:00',NULL,NULL,NULL,55,92),(476,'terminee','2026-10-26 11:00:00',NULL,NULL,NULL,38,93),(477,'terminee','2026-10-21 11:00:00',NULL,NULL,NULL,45,93),(478,'terminee','2026-10-30 11:00:00',NULL,NULL,NULL,43,93),(479,'terminee','2026-10-30 11:00:00',NULL,NULL,NULL,53,93),(480,'terminee','2026-10-25 11:00:00',NULL,NULL,NULL,32,93),(481,'terminee','2026-10-24 11:00:00',NULL,NULL,NULL,41,93),(482,'terminee','2026-10-26 11:00:00',NULL,NULL,NULL,48,93),(483,'terminee','2026-10-30 11:00:00',NULL,NULL,NULL,46,93),(484,'terminee','2026-10-30 16:00:00',NULL,NULL,NULL,55,94),(485,'terminee','2026-10-29 16:00:00',NULL,NULL,NULL,31,94),(486,'terminee','2026-10-26 16:00:00',NULL,NULL,NULL,49,94),(487,'terminee','2026-10-22 16:00:00',NULL,NULL,NULL,48,94),(488,'terminee','2026-10-12 10:30:00',NULL,NULL,NULL,56,95),(489,'terminee','2026-10-30 17:00:00',NULL,NULL,NULL,58,96),(490,'terminee','2026-10-28 17:00:00',NULL,NULL,NULL,37,96),(491,'terminee','2026-11-02 17:00:00',NULL,NULL,NULL,24,96),(492,'terminee','2026-10-18 17:30:00',NULL,NULL,NULL,36,97),(493,'terminee','2026-10-17 17:30:00',NULL,NULL,NULL,28,97),(494,'terminee','2026-10-22 17:30:00',NULL,NULL,NULL,47,97),(495,'terminee','2026-10-18 17:30:00',NULL,NULL,NULL,45,97),(496,'terminee','2026-10-14 17:30:00',NULL,NULL,NULL,51,98),(497,'terminee','2026-10-22 17:30:00',NULL,NULL,NULL,25,98),(498,'terminee','2026-10-23 17:30:00',NULL,NULL,NULL,57,98),(499,'terminee','2026-10-17 17:30:00',NULL,NULL,NULL,53,98),(500,'terminee','2026-10-18 17:30:00',NULL,NULL,NULL,33,98),(501,'terminee','2026-10-23 17:30:00',NULL,NULL,NULL,34,98),(502,'terminee','2026-10-15 17:30:00',NULL,NULL,NULL,40,98),(503,'terminee','2026-10-14 16:00:00',NULL,NULL,NULL,24,99);
/*!40000 ALTER TABLE `reservation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review`
--

DROP TABLE IF EXISTS `review`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rating` smallint NOT NULL,
  `comment` longtext COLLATE utf8mb4_unicode_ci,
  `is_moderated` tinyint NOT NULL DEFAULT '0',
  `is_visible` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `author_id` int NOT NULL,
  `gym_id` int DEFAULT NULL,
  `coach_id` int DEFAULT NULL,
  `session_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_author_gym` (`author_id`,`gym_id`),
  UNIQUE KEY `uq_review_author_coach` (`author_id`,`coach_id`),
  UNIQUE KEY `uq_review_author_session` (`author_id`,`session_id`),
  KEY `IDX_794381C6F675F31B` (`author_id`),
  KEY `IDX_794381C6BD2F03` (`gym_id`),
  KEY `IDX_794381C63C105691` (`coach_id`),
  KEY `IDX_794381C6613FECDF` (`session_id`),
  CONSTRAINT `FK_794381C63C105691` FOREIGN KEY (`coach_id`) REFERENCES `coach_profile` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_794381C6613FECDF` FOREIGN KEY (`session_id`) REFERENCES `session` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_794381C6BD2F03` FOREIGN KEY (`gym_id`) REFERENCES `gym` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_794381C6F675F31B` FOREIGN KEY (`author_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_review_rating` CHECK ((`rating` between 1 and 5)),
  CONSTRAINT `chk_review_target` CHECK (((((`gym_id` is not null) + (`coach_id` is not null)) + (`session_id` is not null)) = 1))
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review`
--

LOCK TABLES `review` WRITE;
/*!40000 ALTER TABLE `review` DISABLE KEYS */;
INSERT INTO `review` VALUES (1,5,'Coach au top et ambiance motivante.',0,1,'2026-11-05 19:30:00',43,NULL,11,NULL),(2,5,'Coach au top et ambiance motivante.',0,1,'2026-11-05 19:30:00',26,NULL,11,NULL),(3,4,'Je reviendrai.',1,1,'2026-11-05 19:30:00',59,NULL,11,NULL),(4,5,'Coach au top et ambiance motivante.',1,1,'2026-11-05 19:30:00',42,NULL,11,NULL),(5,4,'Bon rythme, vestiaires propres.',0,1,'2026-10-19 07:30:00',48,NULL,NULL,82),(6,2,'Trop de monde pour un bon suivi.',1,0,'2026-10-19 07:30:00',32,NULL,NULL,82),(7,4,'Je reviendrai.',1,1,'2026-10-19 07:30:00',38,NULL,NULL,82),(8,4,'Très bonne séance, un peu chargée.',1,1,'2026-10-19 07:30:00',43,NULL,NULL,82),(9,5,'Parfait pour progresser.',1,1,'2026-10-27 09:45:00',23,NULL,NULL,83),(10,3,'Correct, mais la salle était bondée.',0,1,'2026-10-29 08:15:00',32,10,NULL,NULL),(11,2,'Démarrage en retard.',1,0,'2026-10-27 07:45:00',34,NULL,14,NULL),(12,4,'Très bonne séance, un peu chargée.',0,1,'2026-10-27 07:45:00',57,NULL,14,NULL),(13,4,'Très bonne séance, un peu chargée.',0,1,'2026-10-27 07:45:00',55,NULL,NULL,85),(14,3,'Séance sympa sans plus.',0,1,'2026-10-27 07:45:00',33,NULL,14,NULL),(15,3,'Séance sympa sans plus.',0,1,'2026-10-27 07:45:00',30,NULL,NULL,85),(16,4,'Bon rythme, vestiaires propres.',1,1,'2026-10-31 18:15:00',35,7,NULL,NULL),(17,5,'Excellente séance, je recommande !',0,1,'2026-10-31 18:15:00',49,7,NULL,NULL),(18,4,'Très bonne séance, un peu chargée.',0,1,'2026-10-31 18:15:00',37,NULL,6,NULL),(19,4,'Bon rythme, vestiaires propres.',1,1,'2026-11-01 18:45:00',43,NULL,NULL,87),(20,5,'Excellente séance, je recommande !',0,1,'2026-11-01 18:45:00',58,NULL,NULL,87),(21,2,'Démarrage en retard.',0,1,'2026-11-01 18:45:00',38,NULL,1,NULL),(22,4,'Je reviendrai.',1,1,'2026-10-23 16:00:00',54,NULL,NULL,88),(23,3,'Correct, mais la salle était bondée.',1,1,'2026-10-23 16:00:00',45,NULL,NULL,88),(24,4,'Je reviendrai.',0,1,'2026-10-23 16:00:00',38,5,NULL,NULL),(25,3,'Correct, mais la salle était bondée.',0,1,'2026-10-23 16:00:00',41,5,NULL,NULL),(26,5,'Excellente séance, je recommande !',0,1,'2026-11-01 19:30:00',38,NULL,9,NULL),(27,4,'Très bonne séance, un peu chargée.',0,1,'2026-10-21 16:45:00',27,5,NULL,NULL),(28,2,'Trop de monde pour un bon suivi.',1,1,'2026-10-21 16:45:00',34,NULL,NULL,90),(29,4,'Bon rythme, vestiaires propres.',1,1,'2026-10-21 16:45:00',40,NULL,NULL,90),(30,5,'Excellente séance, je recommande !',0,1,'2026-11-01 12:15:00',38,3,NULL,NULL),(31,5,'Excellente séance, je recommande !',1,1,'2026-11-01 12:15:00',43,NULL,2,NULL),(32,4,'Bon rythme, vestiaires propres.',1,1,'2026-11-01 12:15:00',41,NULL,2,NULL),(33,5,'Parfait pour progresser.',0,1,'2026-11-01 12:15:00',46,3,NULL,NULL),(34,4,'Très bonne séance, un peu chargée.',1,1,'2026-11-01 17:30:00',55,NULL,9,NULL),(35,4,'Bon rythme, vestiaires propres.',1,1,'2026-11-01 17:30:00',49,NULL,9,NULL),(36,4,'Bon rythme, vestiaires propres.',1,1,'2026-11-01 17:30:00',48,NULL,9,NULL),(37,4,'Je reviendrai.',0,1,'2026-10-21 12:00:00',56,NULL,6,NULL),(38,3,'Correct, mais la salle était bondée.',0,1,'2026-11-05 18:15:00',58,NULL,NULL,96),(39,2,'Trop de monde pour un bon suivi.',0,1,'2026-11-05 18:15:00',37,7,NULL,NULL),(40,5,'Excellente séance, je recommande !',1,1,'2026-11-05 18:15:00',24,7,NULL,NULL),(41,3,'Correct, mais la salle était bondée.',1,1,'2026-10-25 18:45:00',36,NULL,5,NULL),(42,5,'Parfait pour progresser.',0,1,'2026-10-25 18:45:00',28,NULL,5,NULL),(43,4,'Bon rythme, vestiaires propres.',0,1,'2026-10-25 18:45:00',47,7,NULL,NULL),(44,4,'Très bonne séance, un peu chargée.',1,1,'2026-10-25 18:30:00',25,4,NULL,NULL),(45,4,'Bon rythme, vestiaires propres.',0,1,'2026-10-25 18:30:00',57,4,NULL,NULL),(46,5,'Excellente séance, je recommande !',0,1,'2026-10-25 18:30:00',53,NULL,13,NULL),(47,5,'Excellente séance, je recommande !',0,1,'2026-10-18 17:30:00',24,NULL,NULL,99);
/*!40000 ALTER TABLE `review` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `session`
--

DROP TABLE IF EXISTS `session`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `session` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `capacity` smallint NOT NULL,
  `price` decimal(8,2) NOT NULL DEFAULT '0.00',
  `level` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `activity_id` int NOT NULL,
  `gym_id` int DEFAULT NULL,
  `coach_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_session_status_start` (`status`,`start_at`),
  KEY `IDX_D044D5D481C06096` (`activity_id`),
  KEY `IDX_D044D5D4BD2F03` (`gym_id`),
  KEY `IDX_D044D5D43C105691` (`coach_id`),
  CONSTRAINT `FK_D044D5D43C105691` FOREIGN KEY (`coach_id`) REFERENCES `coach_profile` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_D044D5D481C06096` FOREIGN KEY (`activity_id`) REFERENCES `activity` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_D044D5D4BD2F03` FOREIGN KEY (`gym_id`) REFERENCES `gym` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_session_capacity` CHECK ((`capacity` >= 1)),
  CONSTRAINT `chk_session_dates` CHECK ((`end_at` > `start_at`)),
  CONSTRAINT `chk_session_host` CHECK (((`gym_id` is not null) or (`coach_id` is not null))),
  CONSTRAINT `chk_session_level` CHECK ((`level` in (_utf8mb4'debutant',_utf8mb4'intermediaire',_utf8mb4'avance'))),
  CONSTRAINT `chk_session_price` CHECK ((`price` >= 0)),
  CONSTRAINT `chk_session_status` CHECK ((`status` in (_utf8mb4'active',_utf8mb4'annulee')))
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `session`
--

LOCK TABLES `session` WRITE;
/*!40000 ALTER TABLE `session` DISABLE KEYS */;
INSERT INTO `session` VALUES (1,'CrossFit du matin','Circuits fonctionnels variés, à haute intensité.','2026-11-07 18:00:00','2026-11-07 19:15:00',12,10.00,'avance','annulee',4,NULL,3),(2,'Stretching découverte','Étirements guidés pour récupérer et prévenir les blessures.','2026-11-11 11:00:00','2026-11-11 11:45:00',12,20.00,'debutant','annulee',3,5,NULL),(3,'Pilates du matin','Renforcement des muscles profonds et travail de la posture.','2026-11-06 11:00:00','2026-11-06 12:00:00',15,25.00,'debutant','annulee',2,1,7),(4,'Zumba découverte','Fitness dansé sur des rythmes latins.','2026-11-24 11:00:00','2026-11-24 12:00:00',10,20.00,'debutant','annulee',10,4,1),(5,'Musculation intensif','Travail guidé aux charges libres et aux machines.','2026-11-09 17:00:00','2026-11-09 18:30:00',12,18.00,'debutant','active',5,2,13),(6,'Cardio express','Endurance et dépense énergétique sur des formats courts.','2026-11-07 11:00:00','2026-11-07 12:30:00',20,0.00,'avance','active',7,7,6),(7,'Yoga avancé','Postures, respiration et relaxation pour gagner en souplesse et en calme.','2026-12-02 11:00:00','2026-12-02 12:00:00',20,0.00,'intermediaire','active',1,4,7),(8,'Spinning découverte','Vélo en salle en musique, intervalles et montées.','2026-11-26 08:00:00','2026-11-26 09:30:00',20,20.00,'intermediaire','active',8,3,3),(9,'CrossFit découverte','Circuits fonctionnels variés, à haute intensité.','2026-11-30 16:00:00','2026-11-30 17:00:00',12,12.00,'avance','active',4,9,8),(10,'Stretching intensif','Étirements guidés pour récupérer et prévenir les blessures.','2026-11-15 16:00:00','2026-11-15 17:00:00',15,12.00,'intermediaire','active',3,4,NULL),(11,'CrossFit découverte','Circuits fonctionnels variés, à haute intensité.','2026-11-11 06:00:00','2026-11-11 06:45:00',8,15.00,'avance','active',4,6,13),(12,'Yoga du matin','Postures, respiration et relaxation pour gagner en souplesse et en calme.','2026-11-25 06:30:00','2026-11-25 07:30:00',15,20.00,'avance','active',1,8,10),(13,'Pilates intensif','Renforcement des muscles profonds et travail de la posture.','2026-11-30 11:00:00','2026-11-30 11:45:00',8,0.00,'intermediaire','active',2,3,NULL),(14,'Musculation en groupe','Travail guidé aux charges libres et aux machines.','2026-11-25 17:00:00','2026-11-25 17:45:00',20,15.00,'debutant','active',5,4,9),(15,'CrossFit en groupe','Circuits fonctionnels variés, à haute intensité.','2026-12-03 16:00:00','2026-12-03 16:45:00',8,20.00,'avance','active',4,4,14),(16,'Musculation en groupe','Travail guidé aux charges libres et aux machines.','2026-11-16 08:30:00','2026-11-16 09:15:00',10,18.00,'intermediaire','active',5,6,1),(17,'Zumba du matin','Fitness dansé sur des rythmes latins.','2026-11-14 11:00:00','2026-11-14 12:00:00',20,30.00,'avance','active',10,9,11),(18,'CrossFit intensif','Circuits fonctionnels variés, à haute intensité.','2026-11-26 16:00:00','2026-11-26 17:15:00',10,25.00,'debutant','active',4,7,14),(19,'Running express','Sorties en groupe, fractionné et préparation de courses.','2026-11-21 06:00:00','2026-11-21 07:00:00',10,20.00,'avance','active',9,7,9),(20,'Zumba express','Fitness dansé sur des rythmes latins.','2026-11-19 09:00:00','2026-11-19 09:45:00',15,15.00,'intermediaire','active',10,6,NULL),(21,'Yoga en groupe','Postures, respiration et relaxation pour gagner en souplesse et en calme.','2026-11-26 18:30:00','2026-11-26 19:15:00',10,10.00,'avance','active',1,6,9),(22,'Spinning intensif','Vélo en salle en musique, intervalles et montées.','2026-11-18 18:00:00','2026-11-18 19:00:00',20,25.00,'debutant','active',8,3,14),(23,'Pilates en groupe','Renforcement des muscles profonds et travail de la posture.','2026-11-08 06:00:00','2026-11-08 07:15:00',10,0.00,'debutant','active',2,NULL,5),(24,'Zumba du matin','Fitness dansé sur des rythmes latins.','2026-11-09 07:00:00','2026-11-09 08:30:00',15,0.00,'intermediaire','active',10,6,10),(25,'Stretching avancé','Étirements guidés pour récupérer et prévenir les blessures.','2026-12-03 18:00:00','2026-12-03 18:45:00',8,0.00,'debutant','active',3,7,NULL),(26,'Musculation intensif','Travail guidé aux charges libres et aux machines.','2026-11-26 11:30:00','2026-11-26 13:00:00',12,30.00,'debutant','active',5,3,NULL),(27,'Yoga en groupe','Postures, respiration et relaxation pour gagner en souplesse et en calme.','2026-11-07 09:00:00','2026-11-07 10:15:00',20,20.00,'intermediaire','active',1,6,2),(28,'Spinning avancé','Vélo en salle en musique, intervalles et montées.','2026-11-21 07:00:00','2026-11-21 08:30:00',20,30.00,'debutant','active',8,3,NULL),(29,'CrossFit découverte','Circuits fonctionnels variés, à haute intensité.','2026-11-29 11:00:00','2026-11-29 12:00:00',20,20.00,'avance','active',4,9,12),(30,'Boxe intensif','Technique, cardio-boxe et travail au sac.','2026-11-21 11:30:00','2026-11-21 12:30:00',20,15.00,'avance','active',6,10,15),(31,'Musculation avancé','Travail guidé aux charges libres et aux machines.','2026-11-09 11:00:00','2026-11-09 11:45:00',15,0.00,'debutant','active',5,7,NULL),(32,'Musculation en groupe','Travail guidé aux charges libres et aux machines.','2026-11-26 18:30:00','2026-11-26 19:30:00',10,12.00,'avance','active',5,7,2),(33,'Cardio du matin','Endurance et dépense énergétique sur des formats courts.','2026-11-16 17:00:00','2026-11-16 18:00:00',10,20.00,'avance','active',7,9,NULL),(34,'Running avancé','Sorties en groupe, fractionné et préparation de courses.','2026-11-11 07:00:00','2026-11-11 08:00:00',10,0.00,'avance','active',9,5,1),(35,'Musculation en groupe','Travail guidé aux charges libres et aux machines.','2026-11-24 11:30:00','2026-11-24 12:15:00',15,25.00,'debutant','active',5,8,2),(36,'Musculation en groupe','Travail guidé aux charges libres et aux machines.','2026-12-03 18:00:00','2026-12-03 19:00:00',10,18.00,'avance','active',5,5,7),(37,'Spinning avancé','Vélo en salle en musique, intervalles et montées.','2026-11-26 16:30:00','2026-11-26 18:00:00',12,25.00,'debutant','active',8,6,NULL),(38,'Stretching du matin','Étirements guidés pour récupérer et prévenir les blessures.','2026-11-29 18:00:00','2026-11-29 19:00:00',8,30.00,'avance','active',3,NULL,12),(39,'Zumba express','Fitness dansé sur des rythmes latins.','2026-11-10 17:00:00','2026-11-10 17:45:00',10,30.00,'debutant','active',10,9,NULL),(40,'Running avancé','Sorties en groupe, fractionné et préparation de courses.','2026-12-01 18:00:00','2026-12-01 19:00:00',20,25.00,'debutant','active',9,5,10),(41,'Boxe express','Technique, cardio-boxe et travail au sac.','2026-11-23 07:00:00','2026-11-23 08:00:00',12,18.00,'avance','active',6,10,NULL),(42,'Stretching avancé','Étirements guidés pour récupérer et prévenir les blessures.','2026-11-13 07:00:00','2026-11-13 07:45:00',20,12.00,'debutant','active',3,4,2),(43,'Yoga en groupe','Postures, respiration et relaxation pour gagner en souplesse et en calme.','2026-11-09 08:30:00','2026-11-09 09:30:00',8,12.00,'intermediaire','active',1,3,NULL),(44,'Stretching découverte','Étirements guidés pour récupérer et prévenir les blessures.','2026-11-08 06:30:00','2026-11-08 07:15:00',8,18.00,'debutant','active',3,5,6),(45,'CrossFit express','Circuits fonctionnels variés, à haute intensité.','2026-11-15 11:00:00','2026-11-15 12:00:00',15,25.00,'debutant','active',4,NULL,8),(46,'Boxe découverte','Technique, cardio-boxe et travail au sac.','2026-11-11 07:30:00','2026-11-11 08:30:00',12,18.00,'avance','active',6,7,4),(47,'Spinning express','Vélo en salle en musique, intervalles et montées.','2026-11-07 17:30:00','2026-11-07 18:30:00',20,25.00,'intermediaire','active',8,4,8),(48,'Spinning du matin','Vélo en salle en musique, intervalles et montées.','2026-11-10 18:30:00','2026-11-10 19:30:00',15,30.00,'intermediaire','active',8,NULL,15),(49,'Pilates avancé','Renforcement des muscles profonds et travail de la posture.','2026-11-08 09:30:00','2026-11-08 10:30:00',10,20.00,'intermediaire','active',2,5,NULL),(50,'Spinning découverte','Vélo en salle en musique, intervalles et montées.','2026-11-18 18:30:00','2026-11-18 20:00:00',12,30.00,'avance','active',8,3,1),(51,'Musculation découverte','Travail guidé aux charges libres et aux machines.','2026-11-11 19:00:00','2026-11-11 20:00:00',10,15.00,'debutant','active',5,3,4),(52,'Cardio du matin','Endurance et dépense énergétique sur des formats courts.','2026-11-08 18:00:00','2026-11-08 19:00:00',8,18.00,'avance','active',7,3,2),(53,'Running intensif','Sorties en groupe, fractionné et préparation de courses.','2026-11-12 18:00:00','2026-11-12 19:30:00',20,20.00,'intermediaire','active',9,NULL,3),(54,'Pilates avancé','Renforcement des muscles profonds et travail de la posture.','2026-11-27 16:00:00','2026-11-27 16:45:00',15,25.00,'avance','active',2,10,4),(55,'Spinning express','Vélo en salle en musique, intervalles et montées.','2026-11-09 19:00:00','2026-11-09 20:15:00',12,30.00,'avance','active',8,8,NULL),(56,'Spinning du matin','Vélo en salle en musique, intervalles et montées.','2026-12-02 06:00:00','2026-12-02 06:45:00',12,25.00,'avance','active',8,NULL,12),(57,'Musculation du matin','Travail guidé aux charges libres et aux machines.','2026-11-09 17:00:00','2026-11-09 18:00:00',12,20.00,'intermediaire','active',5,NULL,13),(58,'Boxe avancé','Technique, cardio-boxe et travail au sac.','2026-11-17 11:00:00','2026-11-17 12:00:00',15,15.00,'debutant','active',6,10,12),(59,'Musculation intensif','Travail guidé aux charges libres et aux machines.','2026-11-13 17:00:00','2026-11-13 18:15:00',10,20.00,'intermediaire','active',5,NULL,10),(60,'Pilates du matin','Renforcement des muscles profonds et travail de la posture.','2026-11-30 08:30:00','2026-11-30 09:45:00',10,0.00,'debutant','active',2,7,11),(61,'Pilates avancé','Renforcement des muscles profonds et travail de la posture.','2026-11-08 18:00:00','2026-11-08 19:15:00',10,10.00,'debutant','active',2,9,14),(62,'Musculation découverte','Travail guidé aux charges libres et aux machines.','2026-11-26 08:00:00','2026-11-26 09:00:00',8,25.00,'debutant','active',5,10,14),(63,'Zumba en groupe','Fitness dansé sur des rythmes latins.','2026-11-22 16:00:00','2026-11-22 17:00:00',8,25.00,'debutant','active',10,9,NULL),(64,'Cardio en groupe','Endurance et dépense énergétique sur des formats courts.','2026-11-24 07:00:00','2026-11-24 08:00:00',8,20.00,'debutant','active',7,4,14),(65,'Yoga express','Postures, respiration et relaxation pour gagner en souplesse et en calme.','2026-11-30 09:00:00','2026-11-30 10:15:00',20,18.00,'intermediaire','active',1,5,12),(66,'Pilates en groupe','Renforcement des muscles profonds et travail de la posture.','2026-11-27 08:00:00','2026-11-27 08:45:00',10,25.00,'intermediaire','active',2,7,NULL),(67,'Yoga en groupe','Postures, respiration et relaxation pour gagner en souplesse et en calme.','2026-11-08 11:00:00','2026-11-08 12:15:00',8,0.00,'intermediaire','active',1,NULL,4),(68,'Yoga découverte','Postures, respiration et relaxation pour gagner en souplesse et en calme.','2026-11-09 17:00:00','2026-11-09 18:00:00',20,12.00,'debutant','active',1,1,13),(69,'Pilates express','Renforcement des muscles profonds et travail de la posture.','2026-11-25 08:00:00','2026-11-25 08:45:00',15,12.00,'avance','active',2,3,1),(70,'Pilates intensif','Renforcement des muscles profonds et travail de la posture.','2026-12-03 17:00:00','2026-12-03 18:15:00',8,20.00,'intermediaire','active',2,7,4),(71,'Yoga intensif','Postures, respiration et relaxation pour gagner en souplesse et en calme.','2026-11-19 11:00:00','2026-11-19 11:45:00',8,25.00,'intermediaire','active',1,6,13),(72,'Zumba en groupe','Fitness dansé sur des rythmes latins.','2026-11-27 18:00:00','2026-11-27 18:45:00',20,12.00,'debutant','active',10,6,10),(73,'Cardio du matin','Endurance et dépense énergétique sur des formats courts.','2026-11-24 06:30:00','2026-11-24 07:45:00',10,12.00,'intermediaire','active',7,NULL,11),(74,'Boxe express','Technique, cardio-boxe et travail au sac.','2026-11-20 11:00:00','2026-11-20 12:30:00',10,25.00,'avance','active',6,10,NULL),(75,'Cardio en groupe','Endurance et dépense énergétique sur des formats courts.','2026-11-09 09:30:00','2026-11-09 10:30:00',10,18.00,'debutant','active',7,7,8),(76,'Yoga du matin','Postures, respiration et relaxation pour gagner en souplesse et en calme.','2026-12-03 06:30:00','2026-12-03 07:45:00',15,30.00,'debutant','active',1,5,NULL),(77,'Running du matin','Sorties en groupe, fractionné et préparation de courses.','2026-11-16 17:00:00','2026-11-16 17:45:00',8,25.00,'avance','active',9,2,14),(78,'Spinning avancé','Vélo en salle en musique, intervalles et montées.','2026-11-10 07:30:00','2026-11-10 08:15:00',15,0.00,'intermediaire','active',8,3,NULL),(79,'Zumba découverte','Fitness dansé sur des rythmes latins.','2026-11-20 06:00:00','2026-11-20 06:45:00',12,30.00,'avance','active',10,1,NULL),(80,'CrossFit express','Circuits fonctionnels variés, à haute intensité.','2026-11-30 08:00:00','2026-11-30 09:00:00',20,25.00,'avance','active',4,9,NULL),(81,'CrossFit du matin','Circuits fonctionnels variés, à haute intensité.','2026-11-04 18:30:00','2026-11-04 19:30:00',12,18.00,'debutant','active',4,NULL,11),(82,'Cardio intensif','Endurance et dépense énergétique sur des formats courts.','2026-10-18 06:00:00','2026-10-18 07:30:00',8,18.00,'intermediaire','active',7,8,NULL),(83,'Boxe découverte','Technique, cardio-boxe et travail au sac.','2026-10-26 08:30:00','2026-10-26 09:45:00',12,25.00,'debutant','active',6,NULL,3),(84,'Yoga intensif','Postures, respiration et relaxation pour gagner en souplesse et en calme.','2026-10-28 07:00:00','2026-10-28 08:15:00',8,15.00,'debutant','active',1,10,12),(85,'Stretching avancé','Étirements guidés pour récupérer et prévenir les blessures.','2026-10-26 07:00:00','2026-10-26 07:45:00',10,20.00,'intermediaire','active',3,NULL,14),(86,'Spinning du matin','Vélo en salle en musique, intervalles et montées.','2026-10-30 17:00:00','2026-10-30 18:15:00',15,30.00,'debutant','active',8,7,6),(87,'Stretching express','Étirements guidés pour récupérer et prévenir les blessures.','2026-10-31 18:00:00','2026-10-31 18:45:00',15,12.00,'intermediaire','active',3,6,1),(88,'Zumba express','Fitness dansé sur des rythmes latins.','2026-10-22 15:00:00','2026-10-22 16:00:00',12,18.00,'debutant','active',10,5,2),(89,'Cardio du matin','Endurance et dépense énergétique sur des formats courts.','2026-10-31 18:30:00','2026-10-31 19:30:00',15,25.00,'avance','active',7,NULL,9),(90,'Zumba express','Fitness dansé sur des rythmes latins.','2026-10-20 16:00:00','2026-10-20 16:45:00',10,30.00,'avance','active',10,5,NULL),(91,'Pilates du matin','Renforcement des muscles profonds et travail de la posture.','2026-11-02 06:30:00','2026-11-02 08:00:00',12,20.00,'avance','active',2,9,NULL),(92,'CrossFit avancé','Circuits fonctionnels variés, à haute intensité.','2026-10-27 11:30:00','2026-10-27 12:30:00',12,25.00,'intermediaire','active',4,8,NULL),(93,'Cardio du matin','Endurance et dépense énergétique sur des formats courts.','2026-10-31 11:00:00','2026-10-31 12:15:00',15,20.00,'intermediaire','active',7,3,2),(94,'Yoga découverte','Postures, respiration et relaxation pour gagner en souplesse et en calme.','2026-10-31 16:00:00','2026-10-31 17:30:00',15,30.00,'debutant','active',1,10,9),(95,'Spinning intensif','Vélo en salle en musique, intervalles et montées.','2026-10-20 10:30:00','2026-10-20 12:00:00',12,20.00,'intermediaire','active',8,7,6),(96,'Cardio en groupe','Endurance et dépense énergétique sur des formats courts.','2026-11-04 17:00:00','2026-11-04 18:15:00',12,10.00,'debutant','active',7,7,3),(97,'Stretching intensif','Étirements guidés pour récupérer et prévenir les blessures.','2026-10-24 17:30:00','2026-10-24 18:45:00',8,10.00,'avance','active',3,7,5),(98,'Cardio intensif','Endurance et dépense énergétique sur des formats courts.','2026-10-24 17:30:00','2026-10-24 18:30:00',8,30.00,'intermediaire','active',7,4,13),(99,'Musculation intensif','Travail guidé aux charges libres et aux machines.','2026-10-17 16:00:00','2026-10-17 17:30:00',15,25.00,'intermediaire','active',5,10,15),(100,'Yoga avancé','Postures, respiration et relaxation pour gagner en souplesse et en calme.','2026-10-30 18:30:00','2026-10-30 19:15:00',20,15.00,'debutant','active',1,7,11);
/*!40000 ALTER TABLE `session` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `roles` json NOT NULL,
  `is_verified` tinyint NOT NULL DEFAULT '0',
  `is_blocked` tinyint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_8D93D649E7927C74` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (1,'admin@fitbook.test','$2y$13$wMy/NiHKWJbJLwx/5ODsC.ojpu.PPkgxAiTeOYEde5DJOX/vBvqpm','Admin','FitBook','01 697 25 17',NULL,'[\"ROLE_ADMIN\"]',1,0,'2026-07-01 19:14:42'),(2,'gestionnaire1@fitbook.test','$2y$13$p0CGxOS/XaOT2bQBG25yPuuBLPTy23In/RE5QZQeDfw3fL8uRrn2a','Liam','Bodart','09 6813791',NULL,'[\"ROLE_GESTIONNAIRE\"]',1,0,'2026-09-13 03:32:52'),(3,'gestionnaire2@fitbook.test','$2y$13$p0CGxOS/XaOT2bQBG25yPuuBLPTy23In/RE5QZQeDfw3fL8uRrn2a','Kobe','Gillet','+32(0)6 3525734',NULL,'[\"ROLE_GESTIONNAIRE\"]',1,0,'2026-07-26 20:49:59'),(4,'gestionnaire3@fitbook.test','$2y$13$p0CGxOS/XaOT2bQBG25yPuuBLPTy23In/RE5QZQeDfw3fL8uRrn2a','Mathys','Van den Broeck','0050 700866',NULL,'[\"ROLE_GESTIONNAIRE\"]',1,0,'2026-05-07 04:25:08'),(5,'gestionnaire4@fitbook.test','$2y$13$p0CGxOS/XaOT2bQBG25yPuuBLPTy23In/RE5QZQeDfw3fL8uRrn2a','Nina','Lejeune','00 6217887',NULL,'[\"ROLE_GESTIONNAIRE\"]',1,0,'2026-07-18 03:19:41'),(6,'gestionnaire5@fitbook.test','$2y$13$p0CGxOS/XaOT2bQBG25yPuuBLPTy23In/RE5QZQeDfw3fL8uRrn2a','David','Simons','+32(0)678 850033',NULL,'[\"ROLE_GESTIONNAIRE\"]',1,0,'2026-10-02 05:04:08'),(7,'coach1@fitbook.test','$2y$13$Opfz5KZcbgM2D2re.N8DiudUOmcSoO4HXqlCt3IIe0gFKyq6zBjg.','Florian','Van den Broeck','0148 58 95 28','https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72','[\"ROLE_COACH\"]',1,0,'2026-06-20 15:12:34'),(8,'coach2@fitbook.test','$2y$13$Opfz5KZcbgM2D2re.N8DiudUOmcSoO4HXqlCt3IIe0gFKyq6zBjg.','Maxime','Desmedt','06 2796411','https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72','[\"ROLE_COACH\"]',1,0,'2026-09-30 03:16:17'),(9,'coach3@fitbook.test','$2y$13$Opfz5KZcbgM2D2re.N8DiudUOmcSoO4HXqlCt3IIe0gFKyq6zBjg.','Hanne','Wouters','03 357 36 23','https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72','[\"ROLE_COACH\"]',1,0,'2026-10-03 22:47:02'),(10,'coach4@fitbook.test','$2y$13$Opfz5KZcbgM2D2re.N8DiudUOmcSoO4HXqlCt3IIe0gFKyq6zBjg.','Quinten','Vandamme','003 76 48 32','https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72','[\"ROLE_COACH\"]',1,0,'2026-08-06 17:31:52'),(11,'coach5@fitbook.test','$2y$13$Opfz5KZcbgM2D2re.N8DiudUOmcSoO4HXqlCt3IIe0gFKyq6zBjg.','Alicia','Van Hoof','+32(0)6 8342213','https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72','[\"ROLE_COACH\"]',1,0,'2026-08-20 19:43:29'),(12,'coach6@fitbook.test','$2y$13$Opfz5KZcbgM2D2re.N8DiudUOmcSoO4HXqlCt3IIe0gFKyq6zBjg.','Emma','Bogaerts','+32(0)819 832442','https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72','[\"ROLE_COACH\"]',1,0,'2026-06-28 06:33:44'),(13,'coach7@fitbook.test','$2y$13$Opfz5KZcbgM2D2re.N8DiudUOmcSoO4HXqlCt3IIe0gFKyq6zBjg.','Jonas','Bastin','0424816762','https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72','[\"ROLE_COACH\"]',1,0,'2026-09-14 01:31:20'),(14,'coach8@fitbook.test','$2y$13$Opfz5KZcbgM2D2re.N8DiudUOmcSoO4HXqlCt3IIe0gFKyq6zBjg.','Lena','Verlinden','0877 83 23 19','https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72','[\"ROLE_COACH\"]',1,0,'2026-08-02 05:02:14'),(15,'coach9@fitbook.test','$2y$13$Opfz5KZcbgM2D2re.N8DiudUOmcSoO4HXqlCt3IIe0gFKyq6zBjg.','Kato','Verbruggen','001 893875','https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72','[\"ROLE_COACH\"]',1,0,'2026-08-31 06:20:17'),(16,'coach10@fitbook.test','$2y$13$Opfz5KZcbgM2D2re.N8DiudUOmcSoO4HXqlCt3IIe0gFKyq6zBjg.','Ines','Vermeulen','+32(0)5 2184235','https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72','[\"ROLE_COACH\"]',1,0,'2026-05-19 02:21:46'),(17,'coach11@fitbook.test','$2y$13$Opfz5KZcbgM2D2re.N8DiudUOmcSoO4HXqlCt3IIe0gFKyq6zBjg.','Niels','André','02 230 07 75','https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72','[\"ROLE_COACH\"]',1,0,'2026-07-20 10:31:11'),(18,'coach12@fitbook.test','$2y$13$Opfz5KZcbgM2D2re.N8DiudUOmcSoO4HXqlCt3IIe0gFKyq6zBjg.','Diego','Roland','0741 396 659','https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72','[\"ROLE_COACH\"]',1,0,'2026-07-14 16:11:06'),(19,'coach13@fitbook.test','$2y$13$Opfz5KZcbgM2D2re.N8DiudUOmcSoO4HXqlCt3IIe0gFKyq6zBjg.','Noor','Urbain','+32(0)706 728506','https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72','[\"ROLE_COACH\"]',1,0,'2026-10-04 01:56:47'),(20,'coach14@fitbook.test','$2y$13$Opfz5KZcbgM2D2re.N8DiudUOmcSoO4HXqlCt3IIe0gFKyq6zBjg.','Rania','De Cock','021 24 81 63','https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72','[\"ROLE_COACH\"]',1,0,'2026-08-24 21:53:14'),(21,'coach15@fitbook.test','$2y$13$Opfz5KZcbgM2D2re.N8DiudUOmcSoO4HXqlCt3IIe0gFKyq6zBjg.','Nina','Vandenberghe','+32(0)62117730','https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72','[\"ROLE_COACH\"]',1,0,'2026-09-12 23:20:02'),(22,'sportif1@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Eva','Simons','00 221 22 39',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-05-21 00:55:05'),(23,'sportif2@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Elias','De Clercq','+32(0)42867107',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-08-28 12:14:03'),(24,'sportif3@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Océane','Lemmens','07 908 14 71',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-08-05 13:52:41'),(25,'sportif4@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Nolan','Raes','0093 420 555',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-09-15 13:10:50'),(26,'sportif5@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Zoé','Leclercq','010 65 36 84',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-07-14 04:24:44'),(27,'sportif6@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Romane','Louis','0486 073 888',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-07-04 23:27:55'),(28,'sportif7@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Amy','Wuyts','0138 446 497',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-05-08 12:56:10'),(29,'sportif8@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Lola','Diallo','0298 055 322',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-08-19 00:12:15'),(30,'sportif9@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Janne','Pauwels','08 231 44 31',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-06-24 21:13:28'),(31,'sportif10@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Leon','Goffin','0336928950',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-06-02 19:33:22'),(32,'sportif11@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Merel','Gilson','08 2306858',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-05-09 18:01:07'),(33,'sportif12@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Pauline','Dubois','+32(0)9 5518884',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-07-10 17:22:42'),(34,'sportif13@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Margaux','Thomas','+32(0)54457063',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-05-31 16:01:56'),(35,'sportif14@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Eva','Van den Bossche','06 131 05 18',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-07-13 21:43:18'),(36,'sportif15@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Nathan','Vercammen','0084577035',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-07-13 00:38:50'),(37,'sportif16@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Senne','Etienne','0813730370',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-06-26 18:27:09'),(38,'sportif17@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Nicolas','Van Acker','+32(0)04904629',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-07-23 08:52:57'),(39,'sportif18@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Tristan','Antoine','+32(0)7 1893686',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-07-08 00:50:42'),(40,'sportif19@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Finn','Maréchal','0313 963337',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-08-08 12:35:57'),(41,'sportif20@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Noémie','Lecomte','+32(0)440 433302',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-05-12 00:50:52'),(42,'sportif21@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Mathys','Verbruggen','0401602367',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-09-04 02:49:31'),(43,'sportif22@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Elena','Deprez','0681 74 87 94',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-09-22 04:25:29'),(44,'sportif23@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Elias','El Amrani','0405636624',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-06-07 01:14:58'),(45,'sportif24@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Olivia','Simon','001 287576',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-09-26 07:31:29'),(46,'sportif25@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Alexandre','Fontaine','0322 12 19 52',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-08-24 00:21:37'),(47,'sportif26@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Jasper','Lacroix','0706 164285',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-05-11 16:18:35'),(48,'sportif27@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Alexander','Denis','0020 040 464',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-07-02 21:02:20'),(49,'sportif28@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Théo','Guillaume','06 6021747',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-08-10 08:39:04'),(50,'sportif29@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Simon','Borremans','0370 910 959',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-06-02 04:54:25'),(51,'sportif30@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Estelle','Deprez','06 009 01 23',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-08-31 20:37:18'),(52,'sportif31@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Inès','Aerts','+32(0)7 3532854',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-09-11 23:07:51'),(53,'sportif32@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Inès','Lambrechts','0962 64 37 84',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-07-06 16:24:05'),(54,'sportif33@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Linde','Christiaens','070 02 08 21',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-07-06 15:01:55'),(55,'sportif34@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Roos','Verlinden','0977542186',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-06-22 15:44:44'),(56,'sportif35@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Noé','Nicolas','0753119419',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-08-13 06:39:22'),(57,'sportif36@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Jelle','Fontaine','0193 009 951',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-06-04 06:46:22'),(58,'sportif37@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Diego','Charlier','0972 262265',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-09-19 03:31:30'),(59,'sportif38@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Mehdi','Léonard','0764 18 90 71',NULL,'[\"ROLE_SPORTIF\"]',1,0,'2026-06-06 15:16:54'),(60,'sportif39@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Lucie','Ceulemans','0485 39 77 66',NULL,'[\"ROLE_SPORTIF\"]',0,0,'2026-05-31 07:08:28'),(61,'sportif40@fitbook.test','$2y$13$quVSkDywkL7rc9BIP6Gg6OYMIUrOyc58w0C.dX.9vVOlDFVs7pbDW','Arthur','Verhoeven','003 82 64 59',NULL,'[\"ROLE_SPORTIF\"]',1,1,'2026-06-07 04:25:34');
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'fitbook'
--
/*!50003 DROP PROCEDURE IF EXISTS `sp_annuler_reservation` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`fitbook_user`@`localhost` PROCEDURE `sp_annuler_reservation`(
    IN  p_reservation_id INT,
    IN  p_user_id        INT,
    OUT p_code           TINYINT
)
    MODIFIES SQL DATA
BEGIN
    DECLARE v_owner_id INT         DEFAULT NULL;
    DECLARE v_status   VARCHAR(20) DEFAULT NULL;
    DECLARE v_start_at DATETIME    DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SET p_code = NULL;

    START TRANSACTION;

    SELECT r.user_id, r.status, s.start_at
      INTO v_owner_id, v_status, v_start_at
      FROM reservation r
      JOIN `session` s ON s.id = r.session_id
     WHERE r.id = p_reservation_id
       FOR UPDATE;

    IF v_owner_id IS NULL THEN
        SET p_code = 1;
        ROLLBACK;

    ELSEIF v_owner_id <> p_user_id THEN
        SET p_code = 2;
        ROLLBACK;

    ELSEIF v_status <> 'confirmee' THEN
        SET p_code = 4;
        ROLLBACK;

    ELSEIF v_start_at <= UTC_TIMESTAMP() + INTERVAL 2 HOUR THEN
        SET p_code = 3;
        ROLLBACK;

    ELSE
        UPDATE reservation
           SET status = 'annulee',
               cancelled_at = UTC_TIMESTAMP()
         WHERE id = p_reservation_id;

        SET p_code = 0;
        COMMIT;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_reserver_seance` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`fitbook_user`@`localhost` PROCEDURE `sp_reserver_seance`(
    IN  p_user_id        INT,
    IN  p_session_id     INT,
    OUT p_code           TINYINT,
    OUT p_reservation_id INT
)
    MODIFIES SQL DATA
BEGIN
    DECLARE v_status          VARCHAR(20) DEFAULT NULL;
    DECLARE v_start_at        DATETIME    DEFAULT NULL;
    DECLARE v_capacity        INT         DEFAULT 0;
    DECLARE v_taken           INT         DEFAULT 0;
    DECLARE v_existing_id     INT         DEFAULT NULL;
    DECLARE v_existing_status VARCHAR(20) DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SET p_code = NULL;
    SET p_reservation_id = NULL;

    START TRANSACTION;

    SELECT status, start_at, capacity
      INTO v_status, v_start_at, v_capacity
      FROM `session`
     WHERE id = p_session_id
       FOR UPDATE;

    IF v_status IS NULL THEN
        SET p_code = 1;
        ROLLBACK;

    ELSEIF v_status <> 'active' OR v_start_at <= UTC_TIMESTAMP() THEN
        SET p_code = 2;
        ROLLBACK;

    ELSE
        SELECT id, status
          INTO v_existing_id, v_existing_status
          FROM reservation
         WHERE user_id = p_user_id
           AND session_id = p_session_id
           FOR UPDATE;

        IF v_existing_status = 'confirmee' THEN
            SET p_code = 3;
            ROLLBACK;

        ELSE
            SELECT COUNT(*)
              INTO v_taken
              FROM reservation
             WHERE session_id = p_session_id
               AND status = 'confirmee';

            IF v_taken >= v_capacity THEN
                SET p_code = 4;
                ROLLBACK;

            ELSEIF v_existing_id IS NOT NULL THEN
                UPDATE reservation
                   SET status = 'confirmee',
                       created_at = UTC_TIMESTAMP(),
                       cancelled_at = NULL
                 WHERE id = v_existing_id;

                SET p_reservation_id = v_existing_id;
                SET p_code = 0;
                COMMIT;

            ELSE
                INSERT INTO reservation (user_id, session_id, status, created_at)
                VALUES (p_user_id, p_session_id, 'confirmee', UTC_TIMESTAMP());

                SET p_reservation_id = LAST_INSERT_ID();
                SET p_code = 0;
                COMMIT;
            END IF;
        END IF;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_stats_salle` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`fitbook_user`@`localhost` PROCEDURE `sp_stats_salle`(
    IN p_gym_id INT
)
    READS SQL DATA
BEGIN
    DECLARE v_sessions     INT           DEFAULT 0;
    DECLARE v_capacity     INT           DEFAULT 0;
    DECLARE v_reservations INT           DEFAULT 0;
    DECLARE v_rating       DECIMAL(3, 2) DEFAULT NULL;
    DECLARE v_reviews      INT           DEFAULT 0;

    SELECT COUNT(*), COALESCE(SUM(capacity), 0)
      INTO v_sessions, v_capacity
      FROM `session`
     WHERE gym_id = p_gym_id
       AND status = 'active';

    SELECT COUNT(*)
      INTO v_reservations
      FROM reservation r
      JOIN `session` s ON s.id = r.session_id
     WHERE s.gym_id = p_gym_id
       AND s.status = 'active'
       AND r.status IN ('confirmee', 'terminee');

    SELECT ROUND(AVG(rating), 2), COUNT(*)
      INTO v_rating, v_reviews
      FROM review
     WHERE gym_id = p_gym_id
       AND is_visible = 1;

    SELECT p_gym_id                                              AS gym_id,
           v_sessions                                            AS nb_seances,
           v_capacity                                            AS capacite_totale,
           v_reservations                                        AS nb_reservations,
           ROUND(100 * v_reservations / NULLIF(v_capacity, 0), 1) AS taux_remplissage,
           v_rating                                              AS note_moyenne,
           v_reviews                                             AS nb_avis;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
