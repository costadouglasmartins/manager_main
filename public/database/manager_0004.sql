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


-- Dumping database structure for manager_0004
DROP DATABASE IF EXISTS `manager_0004`;
CREATE DATABASE IF NOT EXISTS `manager_0004` /*!40100 DEFAULT CHARACTER SET utf8mb3 */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `manager_0004`;

-- Dumping structure for table manager_0004.bank_accounts
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_id` int NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pix_key_type` enum('CPF','CNPJ','EMAIL','PHONE','RANDOM') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pix_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_protest` tinyint(1) DEFAULT NULL,
  `bank_slip_protest_number_of_days` tinyint unsigned DEFAULT NULL,
  `bank_slip_wallet` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_species_title` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_interest_type` enum('DAILY','MONTHLY') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_interest_amount_type` enum('PERCENTAGE','FIXED_AMOUNT') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_interest` decimal(12,2) DEFAULT NULL,
  `bank_slip_late_fee_amount` decimal(12,2) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bank_accounts_bank_id` (`bank_id`),
  KEY `idx_bank_accounts_active` (`active`),
  CONSTRAINT `fk_bank_accounts_manager_main_banks` FOREIGN KEY (`bank_id`) REFERENCES `manager_main`.`banks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.bank_accounts: ~0 rows (approximately)

-- Dumping structure for table manager_0004.bank_files
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

-- Dumping data for table manager_0004.bank_files: ~0 rows (approximately)

-- Dumping structure for table manager_0004.bank_slips
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
  KEY `idx_bank_slips_bank_file_id` (`bank_file_id`),
  KEY `idx_bank_slips_status` (`status`),
  KEY `idx_bank_slips_due_date` (`due_date`),
  KEY `idx_bank_slips_document_number` (`document_number`),
  KEY `idx_bank_slips_payer_document` (`payer_document`),
  CONSTRAINT `fk_bank_slips_bank_accounts` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bank_slips_bank_files` FOREIGN KEY (`bank_file_id`) REFERENCES `bank_files` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_bank_slips_financial_operations_payments` FOREIGN KEY (`financial_operation_payment_id`) REFERENCES `financial_operations_payments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.bank_slips: ~0 rows (approximately)

-- Dumping structure for table manager_0004.financial_operations
CREATE TABLE IF NOT EXISTS `financial_operations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` int NOT NULL,
  `financial_operation_subcategory_id` int DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_financial_operations_category` (`financial_operation_category_id`),
  KEY `idx_financial_operations_subcategory` (`financial_operation_subcategory_id`),
  CONSTRAINT `fk_financial_operations_categories` FOREIGN KEY (`financial_operation_category_id`) REFERENCES `financial_operations_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_financial_operations_subcategories` FOREIGN KEY (`financial_operation_subcategory_id`) REFERENCES `financial_operations_subcategories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.financial_operations: ~0 rows (approximately)

-- Dumping structure for table manager_0004.financial_operations_categories
CREATE TABLE IF NOT EXISTS `financial_operations_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_financial_operations_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.financial_operations_categories: ~0 rows (approximately)

-- Dumping structure for table manager_0004.financial_operations_payments
CREATE TABLE IF NOT EXISTS `financial_operations_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_id` int NOT NULL,
  `value` decimal(12,2) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_financial_operations_payments_operation` (`financial_operation_id`),
  CONSTRAINT `fk_financial_operations_payments_operations` FOREIGN KEY (`financial_operation_id`) REFERENCES `financial_operations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.financial_operations_payments: ~0 rows (approximately)

-- Dumping structure for table manager_0004.financial_operations_subcategories
CREATE TABLE IF NOT EXISTS `financial_operations_subcategories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_financial_operations_subcategories_category_name` (`financial_operation_category_id`,`name`),
  KEY `idx_financial_operations_subcategories_category` (`financial_operation_category_id`),
  CONSTRAINT `fk_financial_operations_subcategories_categories` FOREIGN KEY (`financial_operation_category_id`) REFERENCES `financial_operations_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.financial_operations_subcategories: ~0 rows (approximately)

-- Dumping structure for table manager_0004.products
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
  CONSTRAINT `fk_products_categories` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_products_manager_main_users` FOREIGN KEY (`user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.products: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_attributes
CREATE TABLE IF NOT EXISTS `product_attributes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `measurement_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_attributes_code` (`code`),
  KEY `idx_product_attributes_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_attributes: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_attribute_options
CREATE TABLE IF NOT EXISTS `product_attribute_options` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_attribute_id` int NOT NULL,
  `value` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_attribute_options_value` (`product_attribute_id`,`value`),
  KEY `idx_product_attribute_options_attribute` (`product_attribute_id`),
  CONSTRAINT `fk_product_attribute_options_attribute` FOREIGN KEY (`product_attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_attribute_options: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_categories
CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_categories_name` (`name`),
  KEY `idx_product_categories_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_categories: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_category_attributes
CREATE TABLE IF NOT EXISTS `product_category_attributes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_category_id` int NOT NULL,
  `product_attribute_id` int NOT NULL,
  `product_unit_id` int DEFAULT NULL,
  `required` tinyint(1) NOT NULL DEFAULT '0',
  `filterable` tinyint(1) NOT NULL DEFAULT '0',
  `show_on_list` tinyint(1) NOT NULL DEFAULT '0',
  `variation_axis` tinyint(1) NOT NULL DEFAULT '0',
  `variation_specific` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_category_attributes` (`product_category_id`,`product_attribute_id`),
  KEY `idx_product_category_attributes_attribute` (`product_attribute_id`),
  KEY `idx_product_category_attributes_unit` (`product_unit_id`),
  KEY `idx_product_category_attributes_variation_axis` (`product_category_id`,`variation_axis`),
  CONSTRAINT `fk_product_category_attributes_attribute` FOREIGN KEY (`product_attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_category_attributes_category` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_category_attributes_unit` FOREIGN KEY (`product_unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_category_attributes: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_category_attribute_conditions
CREATE TABLE IF NOT EXISTS `product_category_attribute_conditions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_category_id` int NOT NULL,
  `target_product_attribute_id` int NOT NULL,
  `source_product_attribute_id` int NOT NULL,
  `operator` enum('EQUALS','NOT_EQUALS','FILLED','EMPTY') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EQUALS',
  `value_text` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_product_attribute_option_id` int DEFAULT NULL,
  `action` enum('SHOW','REQUIRE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SHOW',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_attr_conditions_category` (`product_category_id`),
  KEY `idx_product_attr_conditions_target` (`target_product_attribute_id`),
  KEY `idx_product_attr_conditions_source` (`source_product_attribute_id`),
  KEY `idx_product_attr_conditions_option` (`source_product_attribute_option_id`),
  CONSTRAINT `fk_product_attr_conditions_category` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_attr_conditions_option` FOREIGN KEY (`source_product_attribute_option_id`) REFERENCES `product_attribute_options` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_product_attr_conditions_source` FOREIGN KEY (`source_product_attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_attr_conditions_target` FOREIGN KEY (`target_product_attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_category_attribute_conditions: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_category_templates
CREATE TABLE IF NOT EXISTS `product_category_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_json` json NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` int DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_category_templates_name` (`name`),
  KEY `idx_product_category_templates_active` (`active`),
  KEY `idx_product_category_templates_created_by_user` (`created_by_user_id`),
  CONSTRAINT `fk_product_category_templates_manager_main_users` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_category_templates: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_images
CREATE TABLE IF NOT EXISTS `product_images` (
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
  UNIQUE KEY `uq_product_images_file_path` (`file_path`),
  KEY `idx_product_images_product` (`product_id`),
  KEY `idx_product_images_main` (`product_id`,`is_main`),
  CONSTRAINT `fk_product_images_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_images: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_import_batches
CREATE TABLE IF NOT EXISTS `product_import_batches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_category_id` int NOT NULL,
  `file_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('PROCESSING','COMPLETED','PARTIAL','FAILED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PROCESSING',
  `total_rows` int unsigned NOT NULL DEFAULT '0',
  `imported_rows` int unsigned NOT NULL DEFAULT '0',
  `failed_rows` int unsigned NOT NULL DEFAULT '0',
  `created_by_user_id` int DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_import_batches_category` (`product_category_id`),
  KEY `idx_product_import_batches_status` (`status`),
  KEY `idx_product_import_batches_created_by_user` (`created_by_user_id`),
  CONSTRAINT `fk_product_import_batches_category` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product_import_batches_manager_main_users` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_import_batches: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_import_errors
CREATE TABLE IF NOT EXISTS `product_import_errors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_import_batch_id` int NOT NULL,
  `row_number` int unsigned NOT NULL,
  `message` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `row_json` json DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_import_errors_batch` (`product_import_batch_id`),
  KEY `idx_product_import_errors_row` (`product_import_batch_id`,`row_number`),
  CONSTRAINT `fk_product_import_errors_batch` FOREIGN KEY (`product_import_batch_id`) REFERENCES `product_import_batches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_import_errors: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_items
CREATE TABLE IF NOT EXISTS `product_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_category_id` int NOT NULL,
  `sku` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `cost_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `sale_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `sale_unit_id` int NOT NULL,
  `sale_quantity_step` decimal(18,6) NOT NULL DEFAULT '1.000000',
  `track_stock` tinyint(1) NOT NULL DEFAULT '1',
  `stock_quantity` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `minimum_stock` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `length_value` decimal(18,6) DEFAULT NULL,
  `width_value` decimal(18,6) DEFAULT NULL,
  `height_value` decimal(18,6) DEFAULT NULL,
  `dimension_unit_id` int DEFAULT NULL,
  `weight_value` decimal(18,6) DEFAULT NULL,
  `weight_unit_id` int DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_items_sku` (`sku`),
  UNIQUE KEY `uq_product_items_barcode` (`barcode`),
  KEY `idx_product_items_category` (`product_category_id`),
  KEY `idx_product_items_sale_unit` (`sale_unit_id`),
  KEY `idx_product_items_dimension_unit` (`dimension_unit_id`),
  KEY `idx_product_items_weight_unit` (`weight_unit_id`),
  KEY `idx_product_items_name` (`name`),
  KEY `idx_product_items_active` (`active`),
  CONSTRAINT `fk_product_items_category` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product_items_dimension_unit` FOREIGN KEY (`dimension_unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_product_items_sale_unit` FOREIGN KEY (`sale_unit_id`) REFERENCES `product_units` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product_items_weight_unit` FOREIGN KEY (`weight_unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_product_items_sale_quantity_step` CHECK ((`sale_quantity_step` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_items: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_item_attribute_values
CREATE TABLE IF NOT EXISTS `product_item_attribute_values` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_item_id` int NOT NULL,
  `product_attribute_id` int NOT NULL,
  `product_attribute_option_id` int DEFAULT NULL,
  `product_unit_id` int DEFAULT NULL,
  `value_text` text COLLATE utf8mb4_unicode_ci,
  `value_integer` int DEFAULT NULL,
  `value_decimal` decimal(18,6) DEFAULT NULL,
  `value_boolean` tinyint(1) DEFAULT NULL,
  `value_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_item_attribute_values` (`product_item_id`,`product_attribute_id`),
  KEY `idx_product_item_values_attribute` (`product_attribute_id`),
  KEY `idx_product_item_values_option` (`product_attribute_option_id`),
  KEY `idx_product_item_values_unit` (`product_unit_id`),
  CONSTRAINT `fk_product_item_values_attribute` FOREIGN KEY (`product_attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product_item_values_item` FOREIGN KEY (`product_item_id`) REFERENCES `product_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_item_values_option` FOREIGN KEY (`product_attribute_option_id`) REFERENCES `product_attribute_options` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_product_item_values_unit` FOREIGN KEY (`product_unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_item_attribute_values: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_item_images
CREATE TABLE IF NOT EXISTS `product_item_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_item_id` int NOT NULL,
  `product_item_variation_id` int DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_main` tinyint(1) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_item_images_file_path` (`file_path`),
  KEY `idx_product_item_images_item` (`product_item_id`),
  KEY `idx_product_item_images_variation` (`product_item_variation_id`),
  KEY `idx_product_item_images_main` (`product_item_id`,`product_item_variation_id`,`is_main`),
  CONSTRAINT `fk_product_item_images_item` FOREIGN KEY (`product_item_id`) REFERENCES `product_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_item_images_variation` FOREIGN KEY (`product_item_variation_id`) REFERENCES `product_item_variations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_item_images: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_item_price_rules
CREATE TABLE IF NOT EXISTS `product_item_price_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_item_id` int NOT NULL,
  `product_item_variation_id` int DEFAULT NULL,
  `product_unit_id` int NOT NULL,
  `minimum_quantity` decimal(18,6) NOT NULL DEFAULT '1.000000',
  `maximum_quantity` decimal(18,6) DEFAULT NULL,
  `sale_price` decimal(15,2) NOT NULL,
  `available` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_item_price_rules_range` (`product_item_id`,`product_item_variation_id`,`product_unit_id`,`minimum_quantity`),
  KEY `idx_product_item_price_rules_item` (`product_item_id`),
  KEY `idx_product_item_price_rules_variation` (`product_item_variation_id`),
  KEY `idx_product_item_price_rules_unit` (`product_unit_id`),
  KEY `idx_product_item_price_rules_active` (`active`,`available`),
  CONSTRAINT `fk_product_item_price_rules_item` FOREIGN KEY (`product_item_id`) REFERENCES `product_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_item_price_rules_unit` FOREIGN KEY (`product_unit_id`) REFERENCES `product_units` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product_item_price_rules_variation` FOREIGN KEY (`product_item_variation_id`) REFERENCES `product_item_variations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_product_item_price_rules_minimum` CHECK ((`minimum_quantity` > 0)),
  CONSTRAINT `chk_product_item_price_rules_range` CHECK (((`maximum_quantity` is null) or (`maximum_quantity` >= `minimum_quantity`))),
  CONSTRAINT `chk_product_item_price_rules_sale_price` CHECK ((`sale_price` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_item_price_rules: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_item_variations
CREATE TABLE IF NOT EXISTS `product_item_variations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_item_id` int NOT NULL,
  `sku` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `combination_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sale_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `track_stock` tinyint(1) NOT NULL DEFAULT '0',
  `stock_quantity` decimal(14,6) NOT NULL DEFAULT '0.000000',
  `minimum_stock` decimal(14,6) NOT NULL DEFAULT '0.000000',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_item_variations_sku` (`sku`),
  UNIQUE KEY `uq_product_item_variations_barcode` (`barcode`),
  UNIQUE KEY `uq_product_item_variations_combination` (`product_item_id`,`combination_key`),
  KEY `idx_product_item_variations_product` (`product_item_id`),
  KEY `idx_product_item_variations_name` (`name`),
  KEY `idx_product_item_variations_active` (`active`),
  KEY `idx_product_item_variations_stock` (`track_stock`,`stock_quantity`),
  CONSTRAINT `fk_product_item_variations_product` FOREIGN KEY (`product_item_id`) REFERENCES `product_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_item_variations: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_item_variation_attribute_values
CREATE TABLE IF NOT EXISTS `product_item_variation_attribute_values` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_item_variation_id` int NOT NULL,
  `product_attribute_id` int NOT NULL,
  `product_attribute_option_id` int DEFAULT NULL,
  `product_unit_id` int DEFAULT NULL,
  `value_text` text COLLATE utf8mb4_unicode_ci,
  `value_integer` int DEFAULT NULL,
  `value_decimal` decimal(18,6) DEFAULT NULL,
  `value_boolean` tinyint(1) DEFAULT NULL,
  `value_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_variation_attribute_values` (`product_item_variation_id`,`product_attribute_id`),
  KEY `idx_product_variation_values_attribute` (`product_attribute_id`),
  KEY `idx_product_variation_values_option` (`product_attribute_option_id`),
  KEY `idx_product_variation_values_unit` (`product_unit_id`),
  CONSTRAINT `fk_product_variation_values_attribute` FOREIGN KEY (`product_attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product_variation_values_option` FOREIGN KEY (`product_attribute_option_id`) REFERENCES `product_attribute_options` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_product_variation_values_unit` FOREIGN KEY (`product_unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_product_variation_values_variation` FOREIGN KEY (`product_item_variation_id`) REFERENCES `product_item_variations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_item_variation_attribute_values: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_stocks_movements
CREATE TABLE IF NOT EXISTS `product_stocks_movements` (
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
  KEY `idx_product_stocks_movements_product` (`product_id`),
  KEY `idx_product_stocks_movements_type` (`movement_type`),
  KEY `idx_product_stocks_movements_date` (`movement_date`),
  CONSTRAINT `fk_product_stocks_movements_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_stocks_movements: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_stock_movements
CREATE TABLE IF NOT EXISTS `product_stock_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_item_id` int NOT NULL,
  `product_item_variation_id` int DEFAULT NULL,
  `movement_type` enum('IN','OUT','ADJUSTMENT') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(18,6) NOT NULL,
  `previous_quantity` decimal(18,6) NOT NULL,
  `new_quantity` decimal(18,6) NOT NULL,
  `reference` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `movement_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_stock_movements_item` (`product_item_id`),
  KEY `idx_product_stock_movements_variation` (`product_item_variation_id`),
  KEY `idx_product_stock_movements_type` (`movement_type`),
  KEY `idx_product_stock_movements_date` (`movement_date`),
  CONSTRAINT `fk_product_stock_movements_item` FOREIGN KEY (`product_item_id`) REFERENCES `product_items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product_stock_movements_variation` FOREIGN KEY (`product_item_variation_id`) REFERENCES `product_item_variations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_stock_movements: ~0 rows (approximately)

-- Dumping structure for table manager_0004.product_units
CREATE TABLE IF NOT EXISTS `product_units` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `measurement_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `factor_to_base` decimal(20,10) NOT NULL DEFAULT '1.0000000000',
  `decimal_places` tinyint unsigned NOT NULL DEFAULT '3',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_units_code` (`code`),
  KEY `idx_product_units_measurement_type` (`measurement_type`),
  KEY `idx_product_units_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.product_units: ~0 rows (approximately)
INSERT INTO `product_units` (`id`, `code`, `name`, `symbol`, `measurement_type`, `factor_to_base`, `decimal_places`, `active`, `created`, `modified`) VALUES
	(1, 'un', 'Unidade', 'un', 'UNIT', 1.0000000000, 0, 1, '2026-07-21 23:07:35', NULL),
	(2, 'mm', 'Milímetro', 'mm', 'LENGTH', 0.0010000000, 3, 1, '2026-07-21 23:07:35', NULL),
	(3, 'cm', 'Centímetro', 'cm', 'LENGTH', 0.0100000000, 2, 1, '2026-07-21 23:07:35', NULL),
	(4, 'm', 'Metro', 'm', 'LENGTH', 1.0000000000, 3, 1, '2026-07-21 23:07:35', NULL),
	(5, 'km', 'Quilômetro', 'km', 'LENGTH', 1000.0000000000, 3, 1, '2026-07-21 23:07:35', NULL),
	(6, 'g', 'Grama', 'g', 'WEIGHT', 1.0000000000, 0, 1, '2026-07-21 23:07:35', NULL),
	(7, 'kg', 'Quilograma', 'kg', 'WEIGHT', 1000.0000000000, 3, 1, '2026-07-21 23:07:35', NULL);

-- Dumping structure for table manager_0004.schedule_categories
CREATE TABLE IF NOT EXISTS `schedule_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `background_color` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#0d6efd',
  `description_color` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#ffffff',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schedule_categories_name` (`name`),
  KEY `idx_schedule_categories_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.schedule_categories: ~0 rows (approximately)

-- Dumping structure for table manager_0004.schedule_events
CREATE TABLE IF NOT EXISTS `schedule_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `schedule_category_id` int DEFAULT NULL,
  `schedule_events_recurrence_id` int DEFAULT NULL,
  `recurrence_sequence` int unsigned DEFAULT NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext COLLATE utf8mb4_unicode_ci,
  `location` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `all_day` tinyint(1) NOT NULL DEFAULT '0',
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `status` enum('scheduled','completed','canceled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
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
  CONSTRAINT `fk_schedule_events_recurrences` FOREIGN KEY (`schedule_events_recurrence_id`) REFERENCES `schedule_events_recurrences` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_schedule_events_period` CHECK (((`ends_at` is null) or (`ends_at` >= `starts_at`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.schedule_events: ~0 rows (approximately)

-- Dumping structure for table manager_0004.schedule_events_participants
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

-- Dumping data for table manager_0004.schedule_events_participants: ~0 rows (approximately)

-- Dumping structure for table manager_0004.schedule_events_recurrences
CREATE TABLE IF NOT EXISTS `schedule_events_recurrences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `schedule_category_id` int DEFAULT NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext COLLATE utf8mb4_unicode_ci,
  `location` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `all_day` tinyint(1) NOT NULL DEFAULT '0',
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `frequency` enum('daily','weekly','monthly') COLLATE utf8mb4_unicode_ci NOT NULL,
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
  CONSTRAINT `fk_schedule_events_recurrences_manager_main_users` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_schedule_events_recurrences_interval` CHECK ((`interval_value` > 0)),
  CONSTRAINT `chk_schedule_events_recurrences_period` CHECK ((`period_end` >= `period_start`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.schedule_events_recurrences: ~0 rows (approximately)

-- Dumping structure for table manager_0004.schema_versions
CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schema_versions_version` (`version`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.schema_versions: ~5 rows (approximately)
INSERT INTO `schema_versions` (`id`, `version`, `description`, `created`, `modified`) VALUES
	(1, '0.0.1', 'Initial tenant schema', '2026-07-22 18:43:21', '2026-07-22 18:43:21'),
	(2, '0.0.2', 'Store products, images and stock module', '2026-07-22 18:43:21', '2026-07-22 18:43:21'),
	(3, '0.0.3', 'Tasks categories, assignments, executions and comments module', '2026-07-22 18:43:21', '2026-07-22 18:43:21'),
	(4, '0.0.4', 'Product variations and conditional product attributes', '2026-07-22 18:43:21', '2026-07-22 18:43:21'),
	(5, '0.0.5', 'Advanced product catalog, generated variations, pricing, imports and variation inventory', '2026-07-22 18:43:21', '2026-07-22 18:43:21');

-- Dumping structure for table manager_0004.tasks
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_category_id` int DEFAULT NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `third_party_person_id` int DEFAULT NULL,
  `third_party_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.tasks: ~0 rows (approximately)

-- Dumping structure for table manager_0004.task_assignees
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.task_assignees: ~0 rows (approximately)

-- Dumping structure for table manager_0004.task_categories
CREATE TABLE IF NOT EXISTS `task_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_categories_name` (`name`),
  KEY `idx_task_categories_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.task_categories: ~0 rows (approximately)

-- Dumping structure for table manager_0004.task_comments
CREATE TABLE IF NOT EXISTS `task_comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_comments_task_created` (`task_id`,`created`),
  KEY `idx_task_comments_user` (`user_id`),
  CONSTRAINT `fk_task_comments_manager_main_users` FOREIGN KEY (`user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_task_comments_tasks` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.task_comments: ~0 rows (approximately)

-- Dumping structure for table manager_0004.task_executions
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
  CONSTRAINT `fk_task_executions_task_assignees` FOREIGN KEY (`task_assignee_id`) REFERENCES `task_assignees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_task_executions_period` CHECK (((`paused_at` is null) or (`paused_at` >= `started_at`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0004.task_executions: ~0 rows (approximately)

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
