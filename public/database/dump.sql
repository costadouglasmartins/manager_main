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
DROP DATABASE IF EXISTS `manager_main`;
CREATE DATABASE IF NOT EXISTS `manager_main` /*!40100 DEFAULT CHARACTER SET utf8mb3 */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `manager_main`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_main.emails: ~0 rows (approximately)

-- Dumping structure for table manager_main.people
CREATE TABLE IF NOT EXISTS `people` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` enum('individual','company') NOT NULL,
  `document` varchar(14) NOT NULL,
  `active` tinyint(1) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_UNIQUE` (`document`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_main.people: ~15 rows (approximately)
INSERT INTO `people` (`id`, `name`, `type`, `document`, `active`, `birthdate`, `created`, `modified`) VALUES
	(3, 'Izzidoro', 'individual', '03599033986', 1, '2000-11-10', '2025-12-04 23:36:11', '2025-12-04 23:36:11'),
	(4, 'Fulano de Tal', 'individual', '00000000000', 1, '1983-12-01', '2025-12-06 18:52:44', '2025-12-06 18:52:44'),
	(5, 'nome sobrenome', 'individual', '09238402384', 1, '1980-11-20', '2025-12-14 00:03:31', '2025-12-29 09:55:36'),
	(6, 'Keanu Reevs', 'individual', '34985239457', 1, '1970-02-01', '2025-12-14 16:41:25', '2025-12-31 09:57:50'),
	(7, 'lalalalal', 'company', '20948423582', 0, NULL, '2025-12-14 16:44:09', '2025-12-30 12:39:08'),
	(8, 'teste dois', 'company', '09823042348', 1, NULL, '2025-12-14 16:51:07', '2025-12-30 12:39:15'),
	(9, 'João Carlos da Silva', 'individual', '34822424880', 1, '1970-08-01', '2025-12-20 17:34:43', '2025-12-20 17:34:43'),
	(10, 'Beltrano Cicrano', 'individual', '11111111111', 1, '2025-12-20', '2025-12-20 16:37:26', '2025-12-20 16:37:30'),
	(12, 'Jeremias da Silva', 'individual', '00000001000', 1, '2025-12-20', '2025-12-20 16:38:28', '2025-12-20 16:38:30'),
	(14, 'Lorem Ipsum Dolor', 'individual', '04519775647', 1, '1983-11-20', '2025-12-21 11:18:33', '2025-12-21 11:18:33'),
	(15, 'asddaf', 'company', '12341251232534', 1, NULL, '2025-12-21 17:20:06', '2025-12-21 17:20:06'),
	(17, 'xxxxxx', 'company', '14253678776542', 1, NULL, '2025-12-21 17:26:30', '2025-12-21 17:26:30'),
	(19, 'Fernando da Silva', 'company', '12341234123423', 1, NULL, '2025-12-23 10:01:33', '2025-12-30 12:38:46'),
	(20, 'Rafaela de Souza', 'individual', '32454544322', 1, '1967-08-09', '2025-12-23 10:08:20', '2025-12-23 10:08:20'),
	(21, 'Sit Amet', 'individual', '92433114179', 1, '1983-04-07', '2025-12-23 22:19:08', '2025-12-29 11:18:48');

-- Dumping structure for table manager_main.permissions
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ability` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_ability` (`ability`)
) ENGINE=InnoDB AUTO_INCREMENT=227 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_main.permissions: ~21 rows (approximately)
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
  `email` varchar(45) NOT NULL,
  `type` enum('root','admin','user') NOT NULL,
  `active` tinyint(1) NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username_UNIQUE` (`username`),
  UNIQUE KEY `email_UNIQUE` (`email`),
  KEY `fk_users_people_idx` (`person_id`),
  CONSTRAINT `fk_users_people` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_main.users: ~13 rows (approximately)
INSERT INTO `users` (`id`, `person_id`, `username`, `password`, `email`, `type`, `active`, `created`, `modified`) VALUES
	(1, 3, 'admin', '$2y$12$l/qPtkZTlH5gdEUUgq7XHe0NQpJfu2OUWuwJ.xvyHnrHRt2PUsKH6', 'costadouglasmartins@gmail.com', 'root', 1, '2025-12-04 23:36:11', '2025-12-04 23:36:11'),
	(2, 4, 'user', '$2y$12$/VKu4lUvnuBuom0iGHH5Z.KpHyMF4XU2jhuN7VxrpcJxw3hecI2/y', 'user@user.com', 'root', 1, '2025-12-06 18:52:45', '2025-12-06 18:52:45'),
	(3, 5, 'SDFSDF', '$2y$12$9FHpylWArtjm0GxYW4eHn.u5HK4s52JwZ.1pMa07660Zpr1VXq9by', 'aa@aa.com', 'admin', 1, '2025-12-14 00:03:31', '2025-12-14 00:03:31'),
	(4, 6, 'lalalal', '$2y$12$SpIPcsCqZ89pRcRMLGoapuqHrEIM5dhu2dlAgzNjZH5TEtYp5f6ba', 'eu@nois.com', 'user', 1, '2025-12-14 16:41:25', '2025-12-14 16:41:25'),
	(5, 7, 'usuario', '$2y$12$4/z.zII7641F1PWVJMIUveEZQJmFqyk1Nr7KX4SG.pvxoGSNpoGXy', 'usuario@usuario.com', 'user', 1, '2025-12-14 16:44:09', '2025-12-14 16:44:09'),
	(6, 8, 'teste', '$2y$12$l/qPtkZTlH5gdEUUgq7XHe0NQpJfu2OUWuwJ.xvyHnrHRt2PUsKH6', 'tes@te.com', 'admin', 1, '2025-12-14 16:51:07', '2025-12-14 16:51:07'),
	(7, 9, 'joao.silva', '$2y$12$a18G0SKx01n23r3GHJMf7.BfqIUhl4YYi/hnhk3M9vj1ouxVkF8O2', 'joao@gmail.com', 'admin', 1, '2025-12-20 17:34:43', '2025-12-20 17:34:43'),
	(11, 14, 'lorem.ipsum', '$2y$12$//XwrIYXiy4ElfttXrH7feiLC3pQK/2aK97FJ1erDrVpiCWDWaRo2', 'lorem@ipsum.com', 'admin', 1, '2025-12-21 11:18:34', '2025-12-21 11:18:34'),
	(12, 15, 'asdfas', '$2y$12$1rx1ctYfgV1kbpWV88S3Kel8xQVXF0dirYIqcSl6Nb..Bt5MTa2GO', 'a@c.com', 'admin', 1, '2025-12-21 17:20:06', '2025-12-21 17:20:06'),
	(14, 17, 'jjjjj', '$2y$12$SF9xgQALuiJuoDW2FiaH3.gVXbp7rV8X41NsFAvkvU2VwbHUxzwi6', 'jjj@jjj.com', 'admin', 1, '2025-12-21 17:26:31', '2025-12-21 17:26:31'),
	(16, 19, 'oasdpfjoasi', '$2y$12$O1jkwJUddRJANraXNAqgkucGEFLKPU87we0H7Ignv6rnYPWBN4rmC', 'lala@lele.com', 'user', 1, '2025-12-23 10:01:33', '2025-12-23 10:01:33'),
	(17, 20, 'ras09diasd9', '$2y$12$Mic3DP80dwJ0.Eiz523QqO140jLWiFol4rlS66o2PoO9cmNiY0Rve', 'kkk@kk.com', 'user', 1, '2025-12-23 10:08:20', '2025-12-23 10:08:20'),
	(18, 21, 'usuario.teste', '$2y$12$23D/TKIZGlkZ34khiPOhFuw.Z6OuBZDz5G1/IYegW7YylSPgGiUre', 'teste@teste.com', 'user', 1, '2025-12-23 22:19:08', '2025-12-23 22:19:08');

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

-- Dumping data for table manager_main.user_permissions: ~29 rows (approximately)
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
	(1, 25, '2025-12-28 22:24:26'),
	(5, 7, '2025-12-28 10:35:06'),
	(5, 14, '2025-12-28 10:35:06');

-- Dumping structure for table manager_main.user_type_permissions
CREATE TABLE IF NOT EXISTS `user_type_permissions` (
  `user_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_id` int unsigned NOT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`user_type`,`permission_id`),
  KEY `fk_utp_permission` (`permission_id`),
  CONSTRAINT `fk_utp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_main.user_type_permissions: ~18 rows (approximately)
INSERT INTO `user_type_permissions` (`user_type`, `permission_id`, `created`) VALUES
	('root', 7, '2025-12-28 11:49:39'),
	('root', 8, '2025-12-28 11:49:39'),
	('root', 9, '2025-12-28 11:49:39'),
	('root', 10, '2025-12-28 11:49:39'),
	('root', 11, '2025-12-28 11:49:39'),
	('root', 12, '2025-12-28 11:49:39'),
	('root', 13, '2025-12-28 11:49:39'),
	('root', 14, '2025-12-28 11:49:39'),
	('root', 15, '2025-12-28 11:49:39'),
	('root', 16, '2025-12-28 11:49:39'),
	('root', 17, '2025-12-28 11:49:39'),
	('root', 18, '2025-12-28 11:49:39');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
