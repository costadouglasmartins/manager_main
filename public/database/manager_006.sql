-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.45 - MySQL Community Server - GPL
-- Server OS:                    Linux
-- HeidiSQL Version:             12.16.1.1
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for manager_0006
DROP DATABASE IF EXISTS `manager_0006`;
CREATE DATABASE IF NOT EXISTS `manager_0006` /*!40100 DEFAULT CHARACTER SET utf8mb3 */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `manager_0006`;

-- Dumping structure for table manager_0006.addresses
CREATE TABLE IF NOT EXISTS `addresses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `zip_code` varchar(45) NOT NULL,
  `street` varchar(255) NOT NULL,
  `number` varchar(10) NOT NULL,
  `observations` varchar(255) DEFAULT NULL,
  `person_id` int NOT NULL,
  `neighborhood_id` int DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_addresses_people1_idx` (`person_id`),
  CONSTRAINT `fk_addresses_people1` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0006.addresses: ~0 rows (approximately)

-- Dumping structure for table manager_0006.emails
CREATE TABLE IF NOT EXISTS `emails` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `observations` varchar(500) DEFAULT NULL,
  `person_id` int DEFAULT NULL,
  `is_login` tinyint(1) NOT NULL DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_emails_people1_idx` (`person_id`),
  CONSTRAINT `fk_emails_people1` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0006.emails: ~0 rows (approximately)

-- Dumping structure for table manager_0006.people
CREATE TABLE IF NOT EXISTS `people` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `fantasy` varchar(255) NOT NULL,
  `type` enum('individual','company') NOT NULL,
  `document` varchar(14) NOT NULL,
  `active` tinyint(1) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_UNIQUE` (`document`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0006.people: ~0 rows (approximately)

-- Dumping structure for table manager_0006.phone_numbers
CREATE TABLE IF NOT EXISTS `phone_numbers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `phone_number` varchar(255) NOT NULL,
  `observations` varchar(500) DEFAULT NULL,
  `person_id` int DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_phone_numbers_people1_idx` (`person_id`),
  CONSTRAINT `fk_phone_numbers_people1` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0006.phone_numbers: ~0 rows (approximately)

-- Dumping structure for table manager_0006.schema_versions
CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL COMMENT 'Version number (e.g.: 1.0.0)',
  `description` varchar(255) DEFAULT NULL,
  `applied_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0006.schema_versions: ~0 rows (approximately)
INSERT INTO `schema_versions` (`id`, `version`, `description`, `applied_at`) VALUES
	(1, '1.00.00', 'Curabitur rhoncus suscipit', '2026-04-10 00:20:52');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
