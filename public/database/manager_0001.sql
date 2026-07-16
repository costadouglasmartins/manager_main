-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.46 - MySQL Community Server - GPL
-- Server OS:                    Linux
-- HeidiSQL Version:             12.20.1.1
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

-- Dumping structure for table manager_0001.bank_accounts
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pix_key_type` enum('CPF','CNPJ','EMAIL','PHONE','RANDOM') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pix_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_3` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_4` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_protest` tinyint(1) DEFAULT NULL,
  `bank_slip_protest_number_of_days` tinyint DEFAULT NULL,
  `bank_slip_wallet` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_species_title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_interest_type` enum('DAILY','MONTHLY') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_interest_amount_type` enum('PERCENTAGE','FIXED_AMOUNT') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_interest` decimal(10,2) DEFAULT NULL,
  `bank_slip_late_fee_amount` decimal(10,2) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_bank_accounts_manager_main_banks_idx` (`bank_id`),
  CONSTRAINT `fk_bank_accounts_manager_main_banks` FOREIGN KEY (`bank_id`) REFERENCES `manager_main`.`banks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.bank_accounts: ~0 rows (approximately)
INSERT INTO `bank_accounts` (`id`, `bank_id`, `name`, `agency`, `account`, `pix_key_type`, `pix_key`, `bank_slip_instructions_line_1`, `bank_slip_instructions_line_2`, `bank_slip_instructions_line_3`, `bank_slip_instructions_line_4`, `bank_slip_protest`, `bank_slip_protest_number_of_days`, `bank_slip_wallet`, `bank_slip_species_title`, `bank_slip_interest_type`, `bank_slip_interest_amount_type`, `bank_slip_interest`, `bank_slip_late_fee_amount`, `active`, `created`, `modified`) VALUES
	(3, 46, 'Banco Itaú', '0001-2', '234560-7', 'CPF', '03599033986', 'AAA', 'BBB', 'CCC', 'DDD', 1, 5, '109', 'DMI', 'DAILY', 'PERCENTAGE', 10.00, 40.00, 1, '2026-07-16 23:21:26', '2026-07-16 23:21:26');

-- Dumping structure for table manager_0001.bank_files
CREATE TABLE IF NOT EXISTS `bank_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_account_id` int DEFAULT NULL,
  `file_type` enum('REMITTANCE','RETURN_FILE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_files_path` (`file_path`),
  KEY `idx_bank_files_bank_account_id` (`bank_account_id`),
  KEY `idx_bank_files_file_type` (`file_type`),
  CONSTRAINT `fk_bank_files_bank_accounts` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.bank_files: ~0 rows (approximately)

-- Dumping structure for table manager_0001.bank_slips
CREATE TABLE IF NOT EXISTS `bank_slips` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_payment_id` int DEFAULT NULL,
  `bank_account_id` int NOT NULL,
  `bank_file_id` int DEFAULT NULL,
  `status` enum('DRAFT','READY','REMITTANCE_GENERATED','SENT','REGISTERED','REJECTED','PAID','CANCELED','WRITTEN_OFF') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `document_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `our_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `our_number_digit` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portfolio` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_document_type` enum('CPF','CNPJ') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_document` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_street` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_neighborhood` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payer_city` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_state` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_zip_code` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `due_date` date NOT NULL,
  `issue_date` date NOT NULL,
  `payment_date` date DEFAULT NULL,
  `paid_amount` decimal(12,2) DEFAULT NULL,
  `credited_amount` decimal(12,2) DEFAULT NULL,
  `credit_date` date DEFAULT NULL,
  `interest_date` date DEFAULT NULL,
  `interest_amount_per_day` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_date` date DEFAULT NULL,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `fine_date` date DEFAULT NULL,
  `fine_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `protest_code` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `protest_days` tinyint unsigned DEFAULT NULL,
  `write_off_code` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `write_off_days` tinyint unsigned DEFAULT NULL,
  `last_occurrence_code` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_occurrence_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_slips_financial_operation_payment` (`financial_operation_payment_id`),
  UNIQUE KEY `uq_bank_slips_account_our_number` (`bank_account_id`,`our_number`),
  KEY `idx_bank_slips_bank_account_id` (`bank_account_id`),
  KEY `idx_bank_slips_bank_file_id` (`bank_file_id`),
  KEY `idx_bank_slips_status` (`status`),
  KEY `idx_bank_slips_due_date` (`due_date`),
  KEY `idx_bank_slips_document_number` (`document_number`),
  KEY `idx_bank_slips_payer_document` (`payer_document`),
  CONSTRAINT `fk_bank_slips_bank_accounts` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bank_slips_bank_files` FOREIGN KEY (`bank_file_id`) REFERENCES `bank_files` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_bank_slips_financial_operations_payments` FOREIGN KEY (`financial_operation_payment_id`) REFERENCES `financial_operations_payments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.bank_slips: ~0 rows (approximately)
INSERT INTO `bank_slips` (`id`, `financial_operation_payment_id`, `bank_account_id`, `bank_file_id`, `status`, `document_number`, `our_number`, `our_number_digit`, `portfolio`, `payer_document_type`, `payer_document`, `payer_name`, `payer_street`, `payer_neighborhood`, `payer_city`, `payer_state`, `payer_zip_code`, `amount`, `due_date`, `issue_date`, `payment_date`, `paid_amount`, `credited_amount`, `credit_date`, `interest_date`, `interest_amount_per_day`, `discount_date`, `discount_amount`, `fine_date`, `fine_amount`, `protest_code`, `protest_days`, `write_off_code`, `write_off_days`, `last_occurrence_code`, `last_occurrence_message`, `created`, `modified`) VALUES
	(5, NULL, 3, NULL, 'DRAFT', '1', '1', '2', '109', 'CPF', '03599033986', 'Nome do Pagador', 'Rua Brescia', 'Pagani', 'Palhoça', 'SC', '88132241', 1.50, '2026-11-09', '2026-07-16', NULL, NULL, NULL, NULL, NULL, 0.00, NULL, 0.00, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-16 23:39:13', '2026-07-16 23:39:13');

-- Dumping structure for table manager_0001.financial_operations
CREATE TABLE IF NOT EXISTS `financial_operations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` int NOT NULL,
  `financial_operation_subcategory_id` int DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_financial_operations_categories_idx` (`financial_operation_category_id`),
  KEY `fk_financial_operations_subcategories_idx` (`financial_operation_subcategory_id`),
  CONSTRAINT `fk_financial_operations_categories` FOREIGN KEY (`financial_operation_category_id`) REFERENCES `financial_operations_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_financial_operations_subcategories` FOREIGN KEY (`financial_operation_subcategory_id`) REFERENCES `financial_operations_subcategories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.financial_operations: ~2 rows (approximately)
INSERT INTO `financial_operations` (`id`, `financial_operation_category_id`, `financial_operation_subcategory_id`, `total_amount`, `created`, `modified`) VALUES
	(1, 7, NULL, 5100.00, '2026-06-01 12:00:00', '2026-06-15 23:54:09'),
	(2, 1, NULL, 766.50, '2026-06-01 12:00:00', '2026-06-03 00:52:09');

-- Dumping structure for table manager_0001.financial_operations_categories
CREATE TABLE IF NOT EXISTS `financial_operations_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_operations_categories_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.financial_operations_categories: ~9 rows (approximately)
INSERT INTO `financial_operations_categories` (`id`, `name`, `created`, `modified`) VALUES
	(1, 'Ajuda financeira', '2026-06-02 23:21:58', '2026-06-02 23:21:58'),
	(2, 'Alimentação', '2026-06-02 23:22:18', '2026-06-02 23:22:18'),
	(3, 'Cartão de crédito', '2026-06-02 23:23:00', '2026-06-02 23:23:00'),
	(4, 'Moradia', '2026-06-02 23:23:30', '2026-06-02 23:23:30'),
	(5, 'Saúde', '2026-06-02 23:23:45', '2026-06-02 23:23:45'),
	(6, 'Transporte', '2026-06-02 23:23:57', '2026-06-02 23:23:57'),
	(7, 'Salário', '2026-06-02 23:24:13', '2026-06-02 23:24:13'),
	(8, 'Financiamento', '2026-06-02 23:28:29', '2026-06-02 23:28:29'),
	(9, 'Mercado', '2026-06-02 23:29:12', '2026-06-02 23:29:12');

-- Dumping structure for table manager_0001.financial_operations_payments
CREATE TABLE IF NOT EXISTS `financial_operations_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_id` int NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_financial_operations_payments_operations_idx` (`financial_operation_id`),
  CONSTRAINT `fk_financial_operations_payments_operations` FOREIGN KEY (`financial_operation_id`) REFERENCES `financial_operations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.financial_operations_payments: ~2 rows (approximately)
INSERT INTO `financial_operations_payments` (`id`, `financial_operation_id`, `value`, `created`, `modified`) VALUES
	(1, 1, 6000.00, '2026-06-01 12:00:00', '2026-06-03 00:51:42'),
	(2, 2, 766.50, '2026-06-01 12:00:00', '2026-06-03 00:52:09');

-- Dumping structure for table manager_0001.financial_operations_subcategories
CREATE TABLE IF NOT EXISTS `financial_operations_subcategories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_operations_subcategories_category_name_unique` (`financial_operation_category_id`,`name`),
  KEY `fk_financial_operations_subcategories_categories_idx` (`financial_operation_category_id`),
  CONSTRAINT `fk_financial_operations_subcategories_categories` FOREIGN KEY (`financial_operation_category_id`) REFERENCES `financial_operations_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.financial_operations_subcategories: ~22 rows (approximately)
INSERT INTO `financial_operations_subcategories` (`id`, `financial_operation_category_id`, `name`, `created`, `modified`) VALUES
	(1, 2, 'Café da manhã fins de semana', '2026-06-02 23:24:42', '2026-06-02 23:24:42'),
	(2, 2, 'Café da manhã segunda à sexta', '2026-06-02 23:25:11', '2026-06-02 23:25:11'),
	(3, 2, 'Almoço fins de semana', '2026-06-02 23:25:54', '2026-06-02 23:25:54'),
	(4, 2, 'Almoço de segunda à sexta', '2026-06-02 23:26:37', '2026-06-02 23:26:37'),
	(5, 2, 'Janta fins de semana', '2026-06-02 23:26:57', '2026-06-02 23:26:57'),
	(6, 2, 'Janta de segunda à sexta', '2026-06-02 23:27:14', '2026-06-02 23:27:14'),
	(7, 3, 'Nubank', '2026-06-02 23:27:40', '2026-06-02 23:27:40'),
	(8, 8, 'Nubank', '2026-06-02 23:28:50', '2026-06-02 23:28:50'),
	(9, 4, 'Água', '2026-06-02 23:29:31', '2026-06-02 23:29:31'),
	(10, 4, 'Aluguel', '2026-06-02 23:29:44', '2026-06-02 23:29:44'),
	(11, 4, 'Condomínio', '2026-06-02 23:30:14', '2026-06-02 23:30:14'),
	(12, 4, 'Energia', '2026-06-02 23:30:29', '2026-06-02 23:30:29'),
	(13, 4, 'Gás', '2026-06-02 23:30:42', '2026-06-02 23:30:42'),
	(14, 4, 'Internet', '2026-06-02 23:30:54', '2026-06-02 23:30:54'),
	(15, 4, 'IPTU', '2026-06-02 23:31:06', '2026-06-02 23:31:06'),
	(16, 4, 'Taxa de lixo', '2026-06-02 23:31:20', '2026-06-02 23:31:20'),
	(17, 4, 'Telefone', '2026-06-02 23:31:37', '2026-06-02 23:31:37'),
	(18, 6, 'Combustível', '2026-06-02 23:32:21', '2026-06-02 23:32:21'),
	(19, 6, 'IPVA', '2026-06-02 23:32:39', '2026-06-02 23:32:39'),
	(20, 6, 'Licenciamento', '2026-06-02 23:32:51', '2026-06-02 23:32:51'),
	(21, 6, 'Seguro', '2026-06-02 23:33:01', '2026-06-02 23:33:01'),
	(22, 6, 'Manutenção', '2026-06-02 23:33:18', '2026-06-02 23:33:18');

-- Dumping structure for table manager_0001.products
CREATE TABLE IF NOT EXISTS `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_category_id` int DEFAULT NULL,
  `sku` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cost_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sale_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `stock_quantity` decimal(12,3) NOT NULL DEFAULT '0.000',
  `minimum_stock` decimal(12,3) NOT NULL DEFAULT '0.000',
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UN',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  UNIQUE KEY `uq_products_barcode` (`barcode`),
  KEY `idx_products_user_id` (`user_id`),
  KEY `idx_products_category` (`product_category_id`),
  KEY `idx_products_name` (`name`),
  KEY `idx_products_active` (`active`),
  KEY `idx_products_stock_quantity` (`stock_quantity`),
  CONSTRAINT `fk_products_categories` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_products_manager_main_users` FOREIGN KEY (`user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.products: ~0 rows (approximately)

-- Dumping structure for table manager_0001.product_categories
CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_categories_name` (`name`),
  KEY `idx_products_categories_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_categories: ~0 rows (approximately)
INSERT INTO `product_categories` (`id`, `name`, `description`, `active`, `created`, `modified`) VALUES
	(1, 'Carros', NULL, 1, '2026-06-08 22:31:24', '2026-06-08 22:31:24');

-- Dumping structure for table manager_0001.product_images
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumb_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `width` int unsigned DEFAULT NULL,
  `height` int unsigned DEFAULT NULL,
  `position` int NOT NULL DEFAULT '0',
  `is_main` tinyint(1) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_images_file_path` (`file_path`),
  KEY `idx_products_images_product` (`product_id`),
  KEY `idx_products_images_main` (`product_id`,`is_main`),
  CONSTRAINT `fk_products_images_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_images: ~0 rows (approximately)

-- Dumping structure for table manager_0001.product_stocks_movements
CREATE TABLE IF NOT EXISTS `product_stocks_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `movement_type` enum('IN','OUT','ADJUSTMENT') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `previous_quantity` decimal(12,3) NOT NULL,
  `new_quantity` decimal(12,3) NOT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `unit_price` decimal(12,2) DEFAULT NULL,
  `reference` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observations` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `movement_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_stocks_movements_product` (`product_id`),
  KEY `idx_products_stocks_movements_type` (`movement_type`),
  KEY `idx_products_stocks_movements_date` (`movement_date`),
  CONSTRAINT `fk_products_stocks_movements_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_stocks_movements: ~0 rows (approximately)

-- Dumping structure for table manager_0001.schedule_categories
CREATE TABLE IF NOT EXISTS `schedule_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `background_color` char(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#0d6efd',
  `description_color` char(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#ffffff',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schedule_categories_name` (`name`),
  KEY `idx_schedule_categories_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.schedule_categories: ~0 rows (approximately)

-- Dumping structure for table manager_0001.schedule_events
CREATE TABLE IF NOT EXISTS `schedule_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `schedule_category_id` int DEFAULT NULL,
  `schedule_events_recurrence_id` int DEFAULT NULL,
  `recurrence_sequence` int unsigned DEFAULT NULL,
  `title` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `all_day` tinyint(1) NOT NULL DEFAULT '0',
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `status` enum('scheduled','completed','canceled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `created_by_user_id` int NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schedule_events_recurrence_sequence` (`schedule_events_recurrence_id`,`recurrence_sequence`),
  KEY `idx_schedule_events_category` (`schedule_category_id`),
  KEY `idx_schedule_events_recurrence` (`schedule_events_recurrence_id`),
  KEY `idx_schedule_events_starts_at` (`starts_at`),
  KEY `idx_schedule_events_status` (`status`),
  KEY `idx_schedule_events_created_by_user` (`created_by_user_id`),
  CONSTRAINT `fk_schedule_events_categories` FOREIGN KEY (`schedule_category_id`) REFERENCES `schedule_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_schedule_events_manager_main_users` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_schedule_events_recurrences` FOREIGN KEY (`schedule_events_recurrence_id`) REFERENCES `schedule_events_recurrences` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.schedule_events: ~0 rows (approximately)

-- Dumping structure for table manager_0001.schedule_events_participants
CREATE TABLE IF NOT EXISTS `schedule_events_participants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `schedule_event_id` int NOT NULL,
  `person_id` int NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schedule_events_participants_event_person` (`schedule_event_id`,`person_id`),
  KEY `idx_schedule_events_participants_event_active` (`schedule_event_id`,`active`),
  KEY `idx_schedule_events_participants_person` (`person_id`),
  CONSTRAINT `fk_schedule_events_participants_events` FOREIGN KEY (`schedule_event_id`) REFERENCES `schedule_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_schedule_events_participants_manager_main_people` FOREIGN KEY (`person_id`) REFERENCES `manager_main`.`people` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.schedule_events_participants: ~0 rows (approximately)

-- Dumping structure for table manager_0001.schedule_events_recurrences
CREATE TABLE IF NOT EXISTS `schedule_events_recurrences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `schedule_category_id` int DEFAULT NULL,
  `title` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `all_day` tinyint(1) NOT NULL DEFAULT '0',
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `frequency` enum('daily','weekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `interval_value` int unsigned NOT NULL DEFAULT '1',
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `allow_weekends` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` int NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_schedule_events_recurrences_category` (`schedule_category_id`),
  KEY `idx_schedule_events_recurrences_period` (`period_start`,`period_end`),
  KEY `idx_schedule_events_recurrences_created_by_user` (`created_by_user_id`),
  KEY `idx_schedule_events_recurrences_active` (`active`),
  CONSTRAINT `fk_schedule_events_recurrences_categories` FOREIGN KEY (`schedule_category_id`) REFERENCES `schedule_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_schedule_events_recurrences_manager_main_users` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.schedule_events_recurrences: ~0 rows (approximately)

-- Dumping structure for table manager_0001.schema_versions
CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Version number (e.g.: 1.5.0)',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schema_versions_version_unique` (`version`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.schema_versions: ~3 rows (approximately)
INSERT INTO `schema_versions` (`id`, `version`, `description`, `created`, `modified`) VALUES
	(1, '1.0.0', 'Initial tenant schema', '2026-06-01 22:34:47', '2026-06-01 22:34:47'),
	(2, 'store-001', 'Store products, images and stock module', '2026-06-01 22:34:48', '2026-06-01 22:34:48'),
	(3, 'tasks-001', 'Tasks categories, assignments, executions and comments module', '2026-07-03 12:00:56', '2026-07-03 12:00:56');

-- Dumping structure for table manager_0001.tasks
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_category_id` int DEFAULT NULL,
  `title` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `third_party_person_id` int DEFAULT NULL,
  `third_party_name` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by_user_id` int NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_category` (`task_category_id`),
  KEY `idx_tasks_status` (`status`),
  KEY `idx_tasks_third_party_person` (`third_party_person_id`),
  KEY `idx_tasks_created_by_user` (`created_by_user_id`),
  KEY `idx_tasks_created` (`created`),
  CONSTRAINT `fk_tasks_manager_main_people_third_party` FOREIGN KEY (`third_party_person_id`) REFERENCES `manager_main`.`people` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tasks_manager_main_users_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_tasks_task_categories` FOREIGN KEY (`task_category_id`) REFERENCES `task_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.tasks: ~0 rows (approximately)

-- Dumping structure for table manager_0001.task_assignees
CREATE TABLE IF NOT EXISTS `task_assignees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_id` int NOT NULL,
  `person_id` int NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_assignees_task_person` (`task_id`,`person_id`),
  KEY `idx_task_assignees_task_active` (`task_id`,`active`),
  KEY `idx_task_assignees_person` (`person_id`),
  CONSTRAINT `fk_task_assignees_manager_main_people` FOREIGN KEY (`person_id`) REFERENCES `manager_main`.`people` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_task_assignees_tasks` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.task_assignees: ~0 rows (approximately)

-- Dumping structure for table manager_0001.task_categories
CREATE TABLE IF NOT EXISTS `task_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_categories_name` (`name`),
  KEY `idx_task_categories_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.task_categories: ~0 rows (approximately)
INSERT INTO `task_categories` (`id`, `name`, `description`, `active`, `created`, `modified`) VALUES
	(1, 'Atendimento ao cliente', 'Categoria de atendimento ao cliente para associar nas tarefas.', 1, '2026-07-03 12:10:28', '2026-07-03 12:10:28');

-- Dumping structure for table manager_0001.task_comments
CREATE TABLE IF NOT EXISTS `task_comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_comments_task_created` (`task_id`,`created`),
  KEY `idx_task_comments_user` (`user_id`),
  CONSTRAINT `fk_task_comments_manager_main_users` FOREIGN KEY (`user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_task_comments_tasks` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.task_comments: ~0 rows (approximately)

-- Dumping structure for table manager_0001.task_executions
CREATE TABLE IF NOT EXISTS `task_executions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_assignee_id` int NOT NULL,
  `started_by_user_id` int NOT NULL,
  `paused_by_user_id` int DEFAULT NULL,
  `started_at` datetime NOT NULL,
  `paused_at` datetime DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_executions_assignee` (`task_assignee_id`),
  KEY `idx_task_executions_open` (`task_assignee_id`,`paused_at`),
  KEY `idx_task_executions_started_by_user` (`started_by_user_id`),
  KEY `idx_task_executions_paused_by_user` (`paused_by_user_id`),
  CONSTRAINT `fk_task_executions_manager_main_users_paused_by` FOREIGN KEY (`paused_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_task_executions_manager_main_users_started_by` FOREIGN KEY (`started_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_task_executions_task_assignees` FOREIGN KEY (`task_assignee_id`) REFERENCES `task_assignees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.task_executions: ~0 rows (approximately)

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
