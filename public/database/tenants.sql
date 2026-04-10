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


-- Dumping database structure for manager_0001
DROP DATABASE IF EXISTS `manager_0001`;
CREATE DATABASE IF NOT EXISTS `manager_0001` /*!40100 DEFAULT CHARACTER SET utf8mb3 */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `manager_0001`;

-- Dumping structure for table manager_0001.schema_versions
CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL COMMENT 'Version number (e.g.: 1.0.0)',
  `description` varchar(255) DEFAULT NULL,
  `applied_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0001.schema_versions: ~1 rows (approximately)
INSERT INTO `schema_versions` (`id`, `version`, `description`, `applied_at`) VALUES
	(1, '1.0.0', 'Initial tenant schema', '2026-04-07 00:10:53');


-- Dumping database structure for manager_0002
DROP DATABASE IF EXISTS `manager_0002`;
CREATE DATABASE IF NOT EXISTS `manager_0002` /*!40100 DEFAULT CHARACTER SET utf8mb3 */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `manager_0002`;

-- Dumping structure for table manager_0002.schema_versions
CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL COMMENT 'Version number (e.g.: 1.0.0)',
  `description` varchar(255) DEFAULT NULL,
  `applied_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0002.schema_versions: ~1 rows (approximately)
INSERT INTO `schema_versions` (`id`, `version`, `description`, `applied_at`) VALUES
	(1, '1.0.0', 'Initial tenant schema', '2026-04-09 23:29:28');


-- Dumping database structure for manager_0003
DROP DATABASE IF EXISTS `manager_0003`;
CREATE DATABASE IF NOT EXISTS `manager_0003` /*!40100 DEFAULT CHARACTER SET utf8mb3 */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `manager_0003`;

-- Dumping structure for table manager_0003.schema_versions
CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL COMMENT 'Version number (e.g.: 1.0.0)',
  `description` varchar(255) DEFAULT NULL,
  `applied_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0003.schema_versions: ~1 rows (approximately)
INSERT INTO `schema_versions` (`id`, `version`, `description`, `applied_at`) VALUES
	(1, '1.0.0', 'Initial tenant schema', '2026-04-09 23:49:36');


-- Dumping database structure for manager_0004
DROP DATABASE IF EXISTS `manager_0004`;
CREATE DATABASE IF NOT EXISTS `manager_0004` /*!40100 DEFAULT CHARACTER SET utf8mb3 */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `manager_0004`;

-- Dumping structure for table manager_0004.schema_versions
CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL COMMENT 'Version number (e.g.: 1.0.0)',
  `description` varchar(255) DEFAULT NULL,
  `applied_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0004.schema_versions: ~1 rows (approximately)
INSERT INTO `schema_versions` (`id`, `version`, `description`, `applied_at`) VALUES
	(1, '1.0.0', 'Initial tenant schema', '2026-04-10 00:03:18');


-- Dumping database structure for manager_0005
DROP DATABASE IF EXISTS `manager_0005`;
CREATE DATABASE IF NOT EXISTS `manager_0005` /*!40100 DEFAULT CHARACTER SET utf8mb3 */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `manager_0005`;

-- Dumping structure for table manager_0005.schema_versions
CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL COMMENT 'Version number (e.g.: 1.0.0)',
  `description` varchar(255) DEFAULT NULL,
  `applied_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0005.schema_versions: ~1 rows (approximately)
INSERT INTO `schema_versions` (`id`, `version`, `description`, `applied_at`) VALUES
	(1, '1.0.0', 'Initial tenant schema', '2026-04-10 00:15:44');


-- Dumping database structure for manager_0006
DROP DATABASE IF EXISTS `manager_0006`;
CREATE DATABASE IF NOT EXISTS `manager_0006` /*!40100 DEFAULT CHARACTER SET utf8mb3 */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `manager_0006`;

-- Dumping structure for table manager_0006.schema_versions
CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL COMMENT 'Version number (e.g.: 1.0.0)',
  `description` varchar(255) DEFAULT NULL,
  `applied_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0006.schema_versions: ~1 rows (approximately)
INSERT INTO `schema_versions` (`id`, `version`, `description`, `applied_at`) VALUES
	(1, '1.0.0', 'Initial tenant schema', '2026-04-10 00:20:52');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
