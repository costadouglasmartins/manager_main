-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.46 - MySQL Community Server - GPL
-- Server OS:                    Linux
-- HeidiSQL Version:             12.18.1.1
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for manager_0002
DROP DATABASE IF EXISTS `manager_0002`;
CREATE DATABASE IF NOT EXISTS `manager_0002` /*!40100 DEFAULT CHARACTER SET utf8mb3 */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `manager_0002`;

-- Dumping structure for table manager_0002.bank_accounts
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_id` int NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pix_key_type` enum('CPF','CNPJ','EMAIL','PHONE','RANDOM') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pix_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_protest` tinyint(1) DEFAULT NULL,
  `bank_slip_protest_number_of_days` tinyint DEFAULT NULL,
  `bank_slip_wallet` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_species_title` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_interest_type` enum('DAILY','MONTHLY') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_interest_amount_type` enum('PERCENTAGE','FIXED_AMOUNT') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_interest` decimal(10,2) DEFAULT NULL,
  `bank_slip_late_fee_amount` decimal(10,2) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_bank_accounts_manager_main_banks_idx` (`bank_id`),
  CONSTRAINT `fk_bank_accounts_manager_main_banks` FOREIGN KEY (`bank_id`) REFERENCES `manager_main`.`banks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0002.bank_accounts: ~0 rows (approximately)

-- Dumping structure for table manager_0002.bank_files
CREATE TABLE IF NOT EXISTS `bank_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_account_id` int DEFAULT NULL,
  `file_type` enum('REMITTANCE','RETURN_FILE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_files_path` (`file_path`),
  KEY `idx_bank_files_bank_account_id` (`bank_account_id`),
  KEY `idx_bank_files_file_type` (`file_type`),
  CONSTRAINT `fk_bank_files_bank_accounts` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0002.bank_files: ~0 rows (approximately)

-- Dumping structure for table manager_0002.bank_slips
CREATE TABLE IF NOT EXISTS `bank_slips` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_payment_id` int DEFAULT NULL,
  `bank_account_id` int NOT NULL,
  `bank_file_id` int DEFAULT NULL,
  `status` enum('DRAFT','READY','REMITTANCE_GENERATED','SENT','REGISTERED','REJECTED','PAID','CANCELED','WRITTEN_OFF') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `document_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `our_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `our_number_digit` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portfolio` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_document_type` enum('CPF','CNPJ') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_document` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_street` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_neighborhood` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payer_city` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_state` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_zip_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `protest_code` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `protest_days` tinyint unsigned DEFAULT NULL,
  `write_off_code` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `write_off_days` tinyint unsigned DEFAULT NULL,
  `last_occurrence_code` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_occurrence_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0002.bank_slips: ~0 rows (approximately)

-- Dumping structure for table manager_0002.financial_operations
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0002.financial_operations: ~0 rows (approximately)

-- Dumping structure for table manager_0002.financial_operations_categories
CREATE TABLE IF NOT EXISTS `financial_operations_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_operations_categories_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0002.financial_operations_categories: ~0 rows (approximately)

-- Dumping structure for table manager_0002.financial_operations_payments
CREATE TABLE IF NOT EXISTS `financial_operations_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_id` int NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_financial_operations_payments_operations_idx` (`financial_operation_id`),
  CONSTRAINT `fk_financial_operations_payments_operations` FOREIGN KEY (`financial_operation_id`) REFERENCES `financial_operations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0002.financial_operations_payments: ~0 rows (approximately)

-- Dumping structure for table manager_0002.financial_operations_subcategories
CREATE TABLE IF NOT EXISTS `financial_operations_subcategories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_operations_subcategories_category_name_unique` (`financial_operation_category_id`,`name`),
  KEY `fk_financial_operations_subcategories_categories_idx` (`financial_operation_category_id`),
  CONSTRAINT `fk_financial_operations_subcategories_categories` FOREIGN KEY (`financial_operation_category_id`) REFERENCES `financial_operations_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0002.financial_operations_subcategories: ~0 rows (approximately)

-- Dumping structure for table manager_0002.products
CREATE TABLE IF NOT EXISTS `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_category_id` int DEFAULT NULL,
  `sku` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `cost_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sale_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `stock_quantity` decimal(12,3) NOT NULL DEFAULT '0.000',
  `minimum_stock` decimal(12,3) NOT NULL DEFAULT '0.000',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UN',
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
  CONSTRAINT `fk_products_categories` FOREIGN KEY (`product_category_id`) REFERENCES `products_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_products_manager_main_users` FOREIGN KEY (`user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0002.products: ~0 rows (approximately)

-- Dumping structure for table manager_0002.products_categories
CREATE TABLE IF NOT EXISTS `products_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_categories_name` (`name`),
  KEY `idx_products_categories_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0002.products_categories: ~0 rows (approximately)

-- Dumping structure for table manager_0002.products_images
CREATE TABLE IF NOT EXISTS `products_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumb_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0002.products_images: ~0 rows (approximately)

-- Dumping structure for table manager_0002.products_stocks_movements
CREATE TABLE IF NOT EXISTS `products_stocks_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `movement_type` enum('IN','OUT','ADJUSTMENT') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `previous_quantity` decimal(12,3) NOT NULL,
  `new_quantity` decimal(12,3) NOT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `unit_price` decimal(12,2) DEFAULT NULL,
  `reference` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observations` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `movement_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_stocks_movements_product` (`product_id`),
  KEY `idx_products_stocks_movements_type` (`movement_type`),
  KEY `idx_products_stocks_movements_date` (`movement_date`),
  CONSTRAINT `fk_products_stocks_movements_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0002.products_stocks_movements: ~0 rows (approximately)

-- Dumping structure for table manager_0002.schema_versions
CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Version number (e.g.: 1.5.0)',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schema_versions_version_unique` (`version`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0002.schema_versions: ~2 rows (approximately)
INSERT INTO `schema_versions` (`id`, `version`, `description`, `created`, `modified`) VALUES
	(1, '1.0.0', 'Initial tenant schema', '2026-06-23 00:14:54', '2026-06-23 00:14:54'),
	(2, 'store-001', 'Store products, images and stock module', '2026-06-23 00:14:54', '2026-06-23 00:14:54');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
