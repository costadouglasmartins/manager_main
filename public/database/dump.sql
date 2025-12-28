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

-- Dumping data for table manager_main.companies: ~7 rows (approximately)
INSERT INTO `companies` (`id`, `name`, `fantasy`, `document`, `database`, `active`, `created`, `modified`) VALUES
	(3, 'Company 1', 'This is the company one', '23452345000123', 'manager-0001', 1, '2025-12-04 23:36:11', '2025-12-04 23:36:11'),
	(4, 'Empresa Xpto', 'Xpto Empreendimentos', '19817234000149', 'manager-0003', 1, '2025-12-06 18:52:44', '2025-12-20 22:56:30'),
	(7, 'Company 2', 'This is the company two', '20934528000182', 'manager-0002', 1, '2025-12-14 00:03:31', '2025-12-14 00:03:31'),
	(9, 'asdfasd', 'oisadfaosjdf', '20934923452345', 'masdoajd', 1, '2025-12-14 16:41:25', '2025-12-14 16:41:25'),
	(11, 'Philips', 'Philips do Brasil', '45626567000106', 'main00000', 1, '2025-12-14 16:44:09', '2025-12-20 17:40:36'),
	(12, 'Compania fantasia', 'Fantasia companhia', '02983428903482', 'main-0123', 1, '2025-12-14 16:51:07', '2025-12-20 22:56:23'),
	(13, 'Empresa nome', 'Fantasia nome', '02183286000113', 'main-0008', 1, '2025-12-20 17:34:43', '2025-12-20 22:56:07');

-- Dumping data for table manager_main.people: ~14 rows (approximately)
INSERT INTO `people` (`id`, `company_id`, `name`, `type`, `document`, `active`, `birthdate`, `created`, `modified`) VALUES
	(3, 3, 'Izzidoro', 'individual', '03599033986', 1, '2000-11-10', '2025-12-04 23:36:11', '2025-12-04 23:36:11'),
	(4, 4, 'Fulano de Tal', 'individual', '00000000000', 1, '1983-12-01', '2025-12-06 18:52:44', '2025-12-06 18:52:44'),
	(5, 7, 'LKSDFJDSAJ', 'company', '09238402384', 1, '1980-11-20', '2025-12-14 00:03:31', '2025-12-14 00:03:31'),
	(6, 9, 'asdkfjasf', 'individual', '34985239457', 0, '1970-02-01', '2025-12-14 16:41:25', '2025-12-14 16:41:25'),
	(7, 11, 'lalalalal', 'company', '20948423582', 0, '2000-04-05', '2025-12-14 16:44:09', '2025-12-14 16:44:09'),
	(8, 12, 'teste', 'company', '09823042348', 1, '1999-09-12', '2025-12-14 16:51:07', '2025-12-14 16:51:07'),
	(9, 13, 'João Carlos da Silva', 'individual', '34822424880', 1, '1970-08-01', '2025-12-20 17:34:43', '2025-12-20 17:34:43'),
	(10, 4, 'Beltrano Cicrano', 'individual', '11111111111', 1, '2025-12-20', '2025-12-20 16:37:26', '2025-12-20 16:37:30'),
	(12, 4, 'Jeremias da Silva', 'individual', '00000001000', 1, '2025-12-20', '2025-12-20 16:38:28', '2025-12-20 16:38:30'),
	(14, 4, 'Lorem Ipsum Dolor', 'individual', '04519775647', 1, '1983-11-20', '2025-12-21 11:18:33', '2025-12-21 11:18:33'),
	(15, 4, 'asddaf', 'company', '12341251232534', 1, NULL, '2025-12-21 17:20:06', '2025-12-21 17:20:06'),
	(17, 4, 'xxxxxx', 'company', '14253678776542', 1, NULL, '2025-12-21 17:26:30', '2025-12-21 17:26:30'),
	(19, 13, 'Fernando da Silva', 'company', '12341234123423', 1, '1922-08-19', '2025-12-23 10:01:33', '2025-12-23 10:01:33'),
	(20, 13, 'Rafaela de Souza', 'individual', '32454544322', 1, '1967-08-09', '2025-12-23 10:08:20', '2025-12-23 10:08:20');

-- Dumping data for table manager_main.users: ~9 rows (approximately)
INSERT INTO `users` (`id`, `person_id`, `username`, `password`, `email`, `type`, `active`, `created`, `modified`) VALUES
	(1, 3, 'admin', '$2y$12$l/qPtkZTlH5gdEUUgq7XHe0NQpJfu2OUWuwJ.xvyHnrHRt2PUsKH6', 'costadouglasmartins@gmail.com', 'root', 1, '2025-12-04 23:36:11', '2025-12-04 23:36:11'),
	(2, 4, 'user', '$2y$12$9FHpylWArtjm0GxYW4eHn.u5HK4s52JwZ.1pMa07660Zpr1VXq9by', 'user@user.com', 'root', 1, '2025-12-06 18:52:45', '2025-12-06 18:52:45'),
	(3, 5, 'SDFSDF', '$2y$12$nLyA6HsNeB7o./RKlHdIQeJx3SQiho.AzqtuAVIhB/NTjrHX1aqYa', 'aa@aa.com', 'admin', 1, '2025-12-14 00:03:31', '2025-12-14 00:03:31'),
	(4, 6, 'lalalal', '$2y$12$SpIPcsCqZ89pRcRMLGoapuqHrEIM5dhu2dlAgzNjZH5TEtYp5f6ba', 'eu@nois.com', 'user', 1, '2025-12-14 16:41:25', '2025-12-14 16:41:25'),
	(5, 7, 'usuario', '$2y$12$4/z.zII7641F1PWVJMIUveEZQJmFqyk1Nr7KX4SG.pvxoGSNpoGXy', 'usuario@usuario.com', 'user', 1, '2025-12-14 16:44:09', '2025-12-14 16:44:09'),
	(6, 8, 'teste', '$2y$12$hYHbDHwP5nQxF2MjSnmS0e1i7OG7y4ccoYgnIjloVBIatKReyc.d6', 'tes@te.com', 'admin', 1, '2025-12-14 16:51:07', '2025-12-14 16:51:07'),
	(7, 9, 'joao.silva', '$2y$12$8pOjNxHN7hpLoWaKzoOa9OtihnrpL9G6I3wlgmazJz1zllLQsikki', 'joao@gmail.com', 'admin', 1, '2025-12-20 17:34:43', '2025-12-20 17:34:43'),
	(9, 10, 'asdfaf', 'asdfas', 'bbb@bbb.com', 'admin', 1, '2025-12-20 17:30:57', '2025-12-20 17:30:59'),
	(10, 12, 'sdaf', 'sdfgsdfgsd', 'cc@cc.com', 'user', 1, '2025-12-20 17:32:36', '2025-12-20 17:32:43'),
	(11, 14, 'lorem.ipsum', '$2y$12$//XwrIYXiy4ElfttXrH7feiLC3pQK/2aK97FJ1erDrVpiCWDWaRo2', 'lorem@ipsum.com', 'admin', 1, '2025-12-21 11:18:34', '2025-12-21 11:18:34'),
	(12, 15, 'asdfas', '$2y$12$1rx1ctYfgV1kbpWV88S3Kel8xQVXF0dirYIqcSl6Nb..Bt5MTa2GO', 'a@c.com', 'admin', 1, '2025-12-21 17:20:06', '2025-12-21 17:20:06'),
	(14, 17, 'jjjjj', '$2y$12$SF9xgQALuiJuoDW2FiaH3.gVXbp7rV8X41NsFAvkvU2VwbHUxzwi6', 'jjj@jjj.com', 'admin', 1, '2025-12-21 17:26:31', '2025-12-21 17:26:31'),
	(16, 19, 'oasdpfjoasi', '$2y$12$O1jkwJUddRJANraXNAqgkucGEFLKPU87we0H7Ignv6rnYPWBN4rmC', 'lala@lele.com', 'user', 1, '2025-12-23 10:01:33', '2025-12-23 10:01:33'),
	(17, 20, 'ras09diasd9', '$2y$12$Mic3DP80dwJ0.Eiz523QqO140jLWiFol4rlS66o2PoO9cmNiY0Rve', 'kkk@kk.com', 'user', 1, '2025-12-23 10:08:20', '2025-12-23 10:08:20');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
