-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.1.0 - MySQL Community Server - GPL
-- Server OS:                    Linux
-- HeidiSQL Version:             12.14.1.1
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for manager_main
CREATE DATABASE IF NOT EXISTS `manager_main` /*!40100 DEFAULT CHARACTER SET utf8mb3 */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `manager_main`;

-- Dumping structure for table manager_main.addresses
CREATE TABLE IF NOT EXISTS `addresses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `street` varchar(255) NOT NULL,
  `number` varchar(10) NOT NULL,
  `observations` varchar(255) DEFAULT NULL,
  `person_id` int NOT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_addresses_people1_idx` (`person_id`),
  CONSTRAINT `fk_addresses_people1` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_main.addresses: ~0 rows (approximately)

-- Dumping structure for table manager_main.emails
CREATE TABLE IF NOT EXISTS `emails` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `observations` varchar(500) DEFAULT NULL,
  `person_id` int DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_emails_people1_idx` (`person_id`),
  CONSTRAINT `fk_emails_people1` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_main.emails: ~1 rows (approximately)
INSERT INTO `emails` (`id`, `email`, `observations`, `person_id`, `created`) VALUES
	(1, 'costadouglasmartins@gmail.com', NULL, 3, '2026-01-06 18:00:02');

-- Dumping structure for table manager_main.people
CREATE TABLE IF NOT EXISTS `people` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `fantasy` varchar(255) NOT NULL,
  `type` enum('individual','company') NOT NULL,
  `document` varchar(14) NOT NULL,
  `active` tinyint(1) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_UNIQUE` (`document`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_main.people: ~1 rows (approximately)
INSERT INTO `people` (`id`, `name`, `fantasy`, `type`, `document`, `active`, `birthdate`, `created`, `modified`) VALUES
	(3, 'Douglas M. Costa', '', 'individual', '03599033986', 1, '2000-11-10', '2025-12-04 23:36:11', '2025-12-04 23:36:11');

-- Dumping structure for table manager_main.permissions
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ability` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_ability` (`ability`)
) ENGINE=InnoDB AUTO_INCREMENT=241 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_main.permissions: ~15 rows (approximately)
INSERT INTO `permissions` (`id`, `ability`, `description`, `created`, `modified`) VALUES
	(7, 'person.index', 'Listar pessoas', '2025-12-28 09:23:21', '2025-12-28 09:23:21'),
	(8, 'person.create', 'Exibir formulário de criação de pessoa', '2025-12-28 09:23:21', '2025-12-28 09:23:21'),
	(9, 'person.store', 'Criar pessoa', '2025-12-28 09:23:21', '2025-12-28 09:23:21'),
	(10, 'person.edit', 'Exibir formulário de edição de pessoa', '2025-12-28 09:23:21', '2025-12-28 09:23:21'),
	(11, 'person.update', 'Atualizar pessoa', '2025-12-28 09:23:21', '2025-12-28 09:23:21'),
	(12, 'person.informations', 'Visualizar detalhes da pessoa', '2025-12-28 09:23:21', '2025-12-28 09:23:21'),
	(13, 'person.autocomplete', 'Usar autocomplete de pessoa', '2025-12-28 09:23:21', '2025-12-28 09:23:21'),
	(14, 'user.index', 'Listar usuários', '2025-12-28 09:23:21', '2025-12-28 09:23:21'),
	(15, 'user.edit', 'Exibir formulário de edição de usuário', '2025-12-28 09:23:21', '2025-12-28 09:23:21'),
	(16, 'user.update', 'Atualizar usuário', '2025-12-28 09:23:21', '2025-12-28 09:23:21'),
	(17, 'user.informations', 'Visualizar detalhes do usuário', '2025-12-28 09:23:21', '2025-12-28 09:23:21'),
	(18, 'permission.manage', 'Gerenciar permissões do sistema', '2025-12-28 09:38:59', '2025-12-28 09:38:59'),
	(25, 'home.index', 'Acesso: home -> index', '2025-12-28 19:26:28', '2025-12-28 19:26:28'),
	(36, 'user.login', 'Acesso: user -> login', '2025-12-28 19:26:28', '2025-12-28 19:26:28'),
	(37, 'user.logout', 'Acesso: user -> logout', '2025-12-28 19:26:28', '2025-12-28 19:26:28');

-- Dumping structure for table manager_main.phone_numbers
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_main.phone_numbers: ~0 rows (approximately)

-- Dumping structure for table manager_main.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `person_id` int NOT NULL,
  `username` varchar(45) NOT NULL,
  `password` varchar(255) NOT NULL,
  `type` enum('root','admin','user') NOT NULL,
  `active` tinyint(1) NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username_UNIQUE` (`username`),
  KEY `fk_users_people_idx` (`person_id`),
  CONSTRAINT `fk_users_people` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_main.users: ~1 rows (approximately)
INSERT INTO `users` (`id`, `person_id`, `username`, `password`, `type`, `active`, `created`, `modified`) VALUES
	(1, 3, 'admin', '$2y$12$l/qPtkZTlH5gdEUUgq7XHe0NQpJfu2OUWuwJ.xvyHnrHRt2PUsKH6', 'root', 1, '2025-12-04 23:36:11', '2025-12-04 23:36:11');

-- Dumping structure for table manager_main.user_permissions
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `user_id` int NOT NULL,
  `permission_id` int unsigned NOT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`,`permission_id`),
  KEY `fk_up_permission` (`permission_id`),
  CONSTRAINT `fk_up_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_main.user_permissions: ~15 rows (approximately)
INSERT INTO `user_permissions` (`user_id`, `permission_id`, `created`) VALUES
	(1, 7, '2025-12-28 22:24:26'),
	(1, 8, '2025-12-28 22:24:26'),
	(1, 9, '2025-12-28 22:24:26'),
	(1, 10, '2025-12-28 22:24:26'),
	(1, 11, '2025-12-28 22:24:26'),
	(1, 12, '2025-12-28 22:24:26'),
	(1, 13, '2025-12-28 22:24:26'),
	(1, 14, '2025-12-28 22:24:26'),
	(1, 15, '2025-12-28 22:24:26'),
	(1, 16, '2025-12-28 22:24:26'),
	(1, 17, '2025-12-28 22:24:26'),
	(1, 18, '2025-12-28 22:24:26'),
	(1, 25, '2025-12-28 22:24:26');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
