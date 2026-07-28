SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `version` VARCHAR(20) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schema_versions_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `schema_versions` (`version`, `description`, `created`, `modified`)
VALUES
  ('0.0.1', 'Initial tenant schema', NOW(), NOW()),
  ('0.0.2', 'Store products, images and stock module', NOW(), NOW()),
  ('0.0.3', 'Tasks categories, assignments, executions and comments module', NOW(), NOW()),
  ('0.0.4', 'Product variations and conditional product attributes', NOW(), NOW()),
  ('0.0.5', 'Advanced product catalog, generated variations, pricing, imports and variation inventory', NOW(), NOW()),
  ('0.0.6', 'CRM Module', NOW(), NOW()),
  ('0.0.7', 'CRM Module - Contacts, Opportunities, Activities and Statuses', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `description` = VALUES(`description`),
  `modified` = NOW();

INSERT INTO `manager_main`.`user_permissions` (`user_id`, `permission_id`, `created`, `modified`)
SELECT DISTINCT
  TenantUser.`user_id`,
  SourcePermission.`permission_id`,
  NOW(),
  NOW()
FROM `manager_main`.`tenants` AS Tenant
INNER JOIN `manager_main`.`tenant_users` AS TenantUser ON TenantUser.`tenant_id` = Tenant.`id`
INNER JOIN `manager_main`.`user_permissions` AS SourcePermission ON SourcePermission.`user_id` = 2
INNER JOIN `manager_main`.`permissions` AS PermissionCatalog ON PermissionCatalog.`id` = SourcePermission.`permission_id` AND PermissionCatalog.`system` = 'manager'
WHERE DATABASE() REGEXP '^manager_[0-9]{4}$'
  AND Tenant.`database_number` IS NOT NULL
  AND LPAD(CAST(Tenant.`database_number` AS UNSIGNED), 4, '0') = SUBSTRING(DATABASE(), CHAR_LENGTH('manager_') + 1)
  AND TenantUser.`role` = 'owner'
  AND NOT EXISTS (
    SELECT 1
    FROM `manager_main`.`user_permissions` AS ExistingPermission
    WHERE ExistingPermission.`user_id` = TenantUser.`user_id`
      AND ExistingPermission.`permission_id` = SourcePermission.`permission_id`
  );

CREATE TABLE IF NOT EXISTS `financial_operations_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_operations_categories_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `financial_operations_subcategories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_operations_subcategories_category_name_unique` (`financial_operation_category_id`, `name`),
  KEY `fk_financial_operations_subcategories_categories_idx` (`financial_operation_category_id`),
  CONSTRAINT `fk_financial_operations_subcategories_categories` FOREIGN KEY (`financial_operation_category_id`) REFERENCES `financial_operations_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `financial_operations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` INT NOT NULL,
  `financial_operation_subcategory_id` INT DEFAULT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_financial_operations_categories_idx` (`financial_operation_category_id`),
  KEY `fk_financial_operations_subcategories_idx` (`financial_operation_subcategory_id`),
  CONSTRAINT `fk_financial_operations_categories` FOREIGN KEY (`financial_operation_category_id`) REFERENCES `financial_operations_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_financial_operations_subcategories` FOREIGN KEY (`financial_operation_subcategory_id`) REFERENCES `financial_operations_subcategories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `financial_operations_payments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `financial_operation_id` INT NOT NULL,
  `value` DECIMAL(10,2) NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_financial_operations_payments_operations_idx` (`financial_operation_id`),
  CONSTRAINT `fk_financial_operations_payments_operations` FOREIGN KEY (`financial_operation_id`) REFERENCES `financial_operations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `bank_id` INT NOT NULL,
  `name` VARCHAR(50) NOT NULL,
  `agency` VARCHAR(6) NOT NULL,
  `account` VARCHAR(50) NOT NULL,
  `pix_key_type` ENUM('CPF','CNPJ','EMAIL','PHONE','RANDOM') DEFAULT NULL,
  `pix_key` VARCHAR(255) DEFAULT NULL,
  `bank_slip_instructions_line_1` VARCHAR(255) DEFAULT NULL,
  `bank_slip_instructions_line_2` VARCHAR(255) DEFAULT NULL,
  `bank_slip_instructions_line_3` VARCHAR(255) DEFAULT NULL,
  `bank_slip_instructions_line_4` VARCHAR(255) DEFAULT NULL,
  `bank_slip_protest` TINYINT(1) DEFAULT NULL,
  `bank_slip_protest_number_of_days` TINYINT UNSIGNED DEFAULT NULL,
  `bank_slip_wallet` VARCHAR(50) DEFAULT NULL,
  `bank_slip_species_title` VARCHAR(50) DEFAULT NULL,
  `bank_slip_interest_type` ENUM('DAILY','MONTHLY') DEFAULT NULL,
  `bank_slip_interest_amount_type` ENUM('PERCENTAGE','FIXED_AMOUNT') DEFAULT NULL,
  `bank_slip_interest` DECIMAL(10,2) DEFAULT NULL,
  `bank_slip_late_fee_amount` DECIMAL(10,2) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_bank_accounts_manager_main_banks_idx` (`bank_id`),
  CONSTRAINT `fk_bank_accounts_manager_main_banks` FOREIGN KEY (`bank_id`) REFERENCES `manager_main`.`banks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_files` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `bank_account_id` INT DEFAULT NULL,
  `file_type` ENUM('REMITTANCE','RETURN_FILE') NOT NULL,
  `file_name` VARCHAR(150) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_hash` CHAR(64) DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_files_path` (`file_path`),
  KEY `idx_bank_files_bank_account_id` (`bank_account_id`),
  KEY `idx_bank_files_file_type` (`file_type`),
  CONSTRAINT `fk_bank_files_bank_accounts` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_slips` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `financial_operation_payment_id` INT DEFAULT NULL,
  `bank_account_id` INT NOT NULL,
  `bank_file_id` INT DEFAULT NULL,
  `status` ENUM('DRAFT','READY','REMITTANCE_GENERATED','SENT','REGISTERED','REJECTED','PAID','CANCELED','WRITTEN_OFF') NOT NULL DEFAULT 'DRAFT',
  `document_number` VARCHAR(20) NOT NULL,
  `our_number` VARCHAR(20) DEFAULT NULL,
  `our_number_digit` VARCHAR(2) DEFAULT NULL,
  `portfolio` VARCHAR(10) NOT NULL,
  `payer_document_type` ENUM('CPF','CNPJ') NOT NULL,
  `payer_document` VARCHAR(14) NOT NULL,
  `payer_name` VARCHAR(100) NOT NULL,
  `payer_street` VARCHAR(120) NOT NULL,
  `payer_neighborhood` VARCHAR(60) DEFAULT NULL,
  `payer_city` VARCHAR(60) NOT NULL,
  `payer_state` CHAR(2) NOT NULL,
  `payer_zip_code` VARCHAR(8) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `due_date` DATE NOT NULL,
  `issue_date` DATE NOT NULL,
  `payment_date` DATE DEFAULT NULL,
  `paid_amount` DECIMAL(12,2) DEFAULT NULL,
  `credited_amount` DECIMAL(12,2) DEFAULT NULL,
  `credit_date` DATE DEFAULT NULL,
  `interest_date` DATE DEFAULT NULL,
  `interest_amount_per_day` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `discount_date` DATE DEFAULT NULL,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `fine_date` DATE DEFAULT NULL,
  `fine_amount` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `protest_code` VARCHAR(2) DEFAULT NULL,
  `protest_days` TINYINT UNSIGNED DEFAULT NULL,
  `write_off_code` VARCHAR(2) DEFAULT NULL,
  `write_off_days` TINYINT UNSIGNED DEFAULT NULL,
  `last_occurrence_code` VARCHAR(2) DEFAULT NULL,
  `last_occurrence_message` VARCHAR(255) DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_slips_financial_operation_payment` (`financial_operation_payment_id`),
  UNIQUE KEY `uq_bank_slips_account_our_number` (`bank_account_id`, `our_number`),
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

CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_categories_name` (`name`),
  KEY `idx_product_categories_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_units` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(20) NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `symbol` VARCHAR(20) NOT NULL,
  `measurement_type` VARCHAR(20) NOT NULL,
  `factor_to_base` DECIMAL(20,10) NOT NULL DEFAULT '1.0000000000',
  `decimal_places` TINYINT NOT NULL DEFAULT '3',
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_units_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_attributes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(80) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `data_type` VARCHAR(20) NOT NULL,
  `measurement_type` VARCHAR(20) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_attributes_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_attribute_options` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_attribute_id` INT NOT NULL,
  `value` VARCHAR(120) NOT NULL,
  `label` VARCHAR(120) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT '0',
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_attribute_options_value` (`product_attribute_id`, `value`),
  CONSTRAINT `fk_product_attribute_options_attribute` FOREIGN KEY (`product_attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_category_attributes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_category_id` INT NOT NULL,
  `product_attribute_id` INT NOT NULL,
  `product_unit_id` INT DEFAULT NULL,
  `required` TINYINT(1) NOT NULL DEFAULT '0',
  `filterable` TINYINT(1) NOT NULL DEFAULT '0',
  `show_on_list` TINYINT(1) NOT NULL DEFAULT '0',
  `variation_axis` TINYINT(1) NOT NULL DEFAULT '0',
  `variation_specific` TINYINT(1) NOT NULL DEFAULT '0',
  `sort_order` INT NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_category_attributes` (`product_category_id`, `product_attribute_id`),
  KEY `fk_product_category_attributes_attribute` (`product_attribute_id`),
  KEY `fk_product_category_attributes_unit` (`product_unit_id`),
  CONSTRAINT `fk_product_category_attributes_attribute` FOREIGN KEY (`product_attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_category_attributes_category` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_category_attributes_unit` FOREIGN KEY (`product_unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_category_attribute_conditions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_category_id` INT NOT NULL,
  `target_product_attribute_id` INT NOT NULL,
  `source_product_attribute_id` INT NOT NULL,
  `operator` ENUM('EQUALS','NOT_EQUALS','FILLED','EMPTY') NOT NULL DEFAULT 'EQUALS',
  `value_text` VARCHAR(190) DEFAULT NULL,
  `source_product_attribute_option_id` INT DEFAULT NULL,
  `action` ENUM('SHOW','REQUIRE') NOT NULL DEFAULT 'SHOW',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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

CREATE TABLE IF NOT EXISTS `product_category_templates` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `schema_json` JSON NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by_user_id` INT DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_category_templates_name` (`name`),
  KEY `idx_product_category_templates_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `product_category_id` INT DEFAULT NULL,
  `sku` VARCHAR(60) DEFAULT NULL,
  `barcode` VARCHAR(80) DEFAULT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT,
  `cost_price` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `sale_price` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `stock_quantity` DECIMAL(12,3) NOT NULL DEFAULT '0.000',
  `minimum_stock` DECIMAL(12,3) NOT NULL DEFAULT '0.000',
  `unit` VARCHAR(20) NOT NULL DEFAULT 'UN',
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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

CREATE TABLE IF NOT EXISTS `product_images` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `original_name` VARCHAR(255) DEFAULT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `thumb_path` VARCHAR(255) DEFAULT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` INT UNSIGNED DEFAULT NULL,
  `width` INT UNSIGNED DEFAULT NULL,
  `height` INT UNSIGNED DEFAULT NULL,
  `position` INT NOT NULL DEFAULT '0',
  `is_main` TINYINT(1) NOT NULL DEFAULT '0',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_images_file_path` (`file_path`),
  KEY `idx_product_images_product` (`product_id`),
  KEY `idx_product_images_main` (`product_id`, `is_main`),
  CONSTRAINT `fk_product_images_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_stocks_movements` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `movement_type` ENUM('IN','OUT','ADJUSTMENT') NOT NULL,
  `quantity` DECIMAL(12,3) NOT NULL,
  `previous_quantity` DECIMAL(12,3) NOT NULL,
  `new_quantity` DECIMAL(12,3) NOT NULL,
  `unit_cost` DECIMAL(12,2) DEFAULT NULL,
  `unit_price` DECIMAL(12,2) DEFAULT NULL,
  `reference` VARCHAR(120) DEFAULT NULL,
  `observations` VARCHAR(255) DEFAULT NULL,
  `movement_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_stocks_movements_product` (`product_id`),
  KEY `idx_product_stocks_movements_type` (`movement_type`),
  KEY `idx_product_stocks_movements_date` (`movement_date`),
  CONSTRAINT `fk_product_stocks_movements_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_items` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_category_id` INT NOT NULL,
  `sku` VARCHAR(60) DEFAULT NULL,
  `barcode` VARCHAR(80) DEFAULT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT,
  `cost_price` DECIMAL(15,2) NOT NULL DEFAULT '0.00',
  `sale_price` DECIMAL(15,2) NOT NULL DEFAULT '0.00',
  `sale_unit_id` INT NOT NULL,
  `sale_quantity_step` DECIMAL(18,6) NOT NULL DEFAULT '1.000000',
  `track_stock` TINYINT(1) NOT NULL DEFAULT '1',
  `stock_quantity` DECIMAL(18,6) NOT NULL DEFAULT '0.000000',
  `minimum_stock` DECIMAL(18,6) NOT NULL DEFAULT '0.000000',
  `length_value` DECIMAL(18,6) DEFAULT NULL,
  `width_value` DECIMAL(18,6) DEFAULT NULL,
  `height_value` DECIMAL(18,6) DEFAULT NULL,
  `dimension_unit_id` INT DEFAULT NULL,
  `weight_value` DECIMAL(18,6) DEFAULT NULL,
  `weight_unit_id` INT DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_items_sku` (`sku`),
  UNIQUE KEY `uq_product_items_barcode` (`barcode`),
  KEY `idx_product_items_category` (`product_category_id`),
  KEY `fk_product_items_sale_unit` (`sale_unit_id`),
  KEY `fk_product_items_dimension_unit` (`dimension_unit_id`),
  KEY `fk_product_items_weight_unit` (`weight_unit_id`),
  CONSTRAINT `fk_product_items_category` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product_items_dimension_unit` FOREIGN KEY (`dimension_unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_product_items_sale_unit` FOREIGN KEY (`sale_unit_id`) REFERENCES `product_units` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product_items_weight_unit` FOREIGN KEY (`weight_unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_item_attribute_values` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_item_id` INT NOT NULL,
  `product_attribute_id` INT NOT NULL,
  `product_attribute_option_id` INT DEFAULT NULL,
  `product_unit_id` INT DEFAULT NULL,
  `value_text` TEXT,
  `value_integer` INT DEFAULT NULL,
  `value_decimal` DECIMAL(18,6) DEFAULT NULL,
  `value_boolean` TINYINT(1) DEFAULT NULL,
  `value_date` DATE DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_item_attribute_values` (`product_item_id`, `product_attribute_id`),
  KEY `fk_product_item_values_attribute` (`product_attribute_id`),
  KEY `fk_product_item_values_option` (`product_attribute_option_id`),
  KEY `fk_product_item_values_unit` (`product_unit_id`),
  CONSTRAINT `fk_product_item_values_attribute` FOREIGN KEY (`product_attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product_item_values_item` FOREIGN KEY (`product_item_id`) REFERENCES `product_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_item_values_option` FOREIGN KEY (`product_attribute_option_id`) REFERENCES `product_attribute_options` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_product_item_values_unit` FOREIGN KEY (`product_unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_item_variations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_item_id` INT NOT NULL,
  `sku` VARCHAR(60) DEFAULT NULL,
  `barcode` VARCHAR(80) DEFAULT NULL,
  `name` VARCHAR(160) NOT NULL,
  `combination_key` VARCHAR(255) DEFAULT NULL,
  `cost_price` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `sale_price` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `track_stock` TINYINT(1) NOT NULL DEFAULT '0',
  `stock_quantity` DECIMAL(14,6) NOT NULL DEFAULT '0.000000',
  `minimum_stock` DECIMAL(14,6) NOT NULL DEFAULT '0.000000',
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_item_variations_sku` (`sku`),
  UNIQUE KEY `uq_product_item_variations_barcode` (`barcode`),
  UNIQUE KEY `uq_product_item_variations_combination` (`product_item_id`, `combination_key`),
  KEY `idx_product_item_variations_product` (`product_item_id`),
  KEY `idx_product_item_variations_name` (`name`),
  KEY `idx_product_item_variations_active` (`active`),
  KEY `idx_product_item_variations_stock` (`track_stock`, `stock_quantity`),
  CONSTRAINT `fk_product_item_variations_product` FOREIGN KEY (`product_item_id`) REFERENCES `product_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_item_images` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_item_id` INT NOT NULL,
  `product_item_variation_id` INT DEFAULT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(180) DEFAULT NULL,
  `mime_type` VARCHAR(80) DEFAULT NULL,
  `file_size` INT DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT '0',
  `is_main` TINYINT(1) NOT NULL DEFAULT '0',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_product_item_images_item` (`product_item_id`),
  KEY `idx_product_item_images_variation` (`product_item_variation_id`),
  CONSTRAINT `fk_product_item_images_item` FOREIGN KEY (`product_item_id`) REFERENCES `product_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_item_images_variation` FOREIGN KEY (`product_item_variation_id`) REFERENCES `product_item_variations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_item_price_rules` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_item_id` INT NOT NULL,
  `product_item_variation_id` INT DEFAULT NULL,
  `product_unit_id` INT NOT NULL,
  `minimum_quantity` DECIMAL(18,6) NOT NULL DEFAULT '1.000000',
  `maximum_quantity` DECIMAL(18,6) DEFAULT NULL,
  `sale_price` DECIMAL(15,2) NOT NULL,
  `available` TINYINT(1) NOT NULL DEFAULT '1',
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_item_price_rules_range` (`product_item_id`, `product_item_variation_id`, `product_unit_id`, `minimum_quantity`),
  KEY `idx_product_item_price_rules_item` (`product_item_id`),
  KEY `idx_product_item_price_rules_variation` (`product_item_variation_id`),
  KEY `idx_product_item_price_rules_unit` (`product_unit_id`),
  KEY `idx_product_item_price_rules_active` (`active`, `available`),
  CONSTRAINT `fk_product_item_price_rules_item` FOREIGN KEY (`product_item_id`) REFERENCES `product_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_item_price_rules_unit` FOREIGN KEY (`product_unit_id`) REFERENCES `product_units` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product_item_price_rules_variation` FOREIGN KEY (`product_item_variation_id`) REFERENCES `product_item_variations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_product_item_price_rules_range` CHECK (`maximum_quantity` IS NULL OR `maximum_quantity` >= `minimum_quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_item_variation_attribute_values` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_item_variation_id` INT NOT NULL,
  `product_attribute_id` INT NOT NULL,
  `product_attribute_option_id` INT DEFAULT NULL,
  `product_unit_id` INT DEFAULT NULL,
  `value_text` TEXT,
  `value_integer` INT DEFAULT NULL,
  `value_decimal` DECIMAL(18,6) DEFAULT NULL,
  `value_boolean` TINYINT(1) DEFAULT NULL,
  `value_date` DATE DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_variation_attribute_values` (`product_item_variation_id`, `product_attribute_id`),
  KEY `idx_product_variation_values_attribute` (`product_attribute_id`),
  KEY `idx_product_variation_values_option` (`product_attribute_option_id`),
  KEY `idx_product_variation_values_unit` (`product_unit_id`),
  CONSTRAINT `fk_product_variation_values_attribute` FOREIGN KEY (`product_attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product_variation_values_option` FOREIGN KEY (`product_attribute_option_id`) REFERENCES `product_attribute_options` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_product_variation_values_unit` FOREIGN KEY (`product_unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_product_variation_values_variation` FOREIGN KEY (`product_item_variation_id`) REFERENCES `product_item_variations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_stock_movements` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_item_id` INT NOT NULL,
  `product_item_variation_id` INT DEFAULT NULL,
  `movement_type` VARCHAR(20) NOT NULL,
  `quantity` DECIMAL(18,6) NOT NULL,
  `previous_quantity` DECIMAL(18,6) NOT NULL,
  `new_quantity` DECIMAL(18,6) NOT NULL,
  `reference` VARCHAR(120) DEFAULT NULL,
  `notes` TEXT,
  `movement_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_stock_movements_item` (`product_item_id`),
  KEY `idx_product_stock_movements_variation` (`product_item_variation_id`),
  CONSTRAINT `fk_product_stock_movements_item` FOREIGN KEY (`product_item_id`) REFERENCES `product_items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product_stock_movements_variation` FOREIGN KEY (`product_item_variation_id`) REFERENCES `product_item_variations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_categories_name` (`name`),
  KEY `idx_task_categories_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `task_category_id` INT DEFAULT NULL,
  `title` VARCHAR(160) NOT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'open',
  `third_party_person_id` INT DEFAULT NULL,
  `third_party_name` VARCHAR(160) DEFAULT NULL,
  `description` MEDIUMTEXT NOT NULL,
  `created_by_user_id` INT NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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

CREATE TABLE IF NOT EXISTS `task_assignees` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `task_id` INT NOT NULL,
  `person_id` INT NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_assignees_task_person` (`task_id`, `person_id`),
  KEY `idx_task_assignees_task_active` (`task_id`, `active`),
  KEY `idx_task_assignees_person` (`person_id`),
  CONSTRAINT `fk_task_assignees_manager_main_people` FOREIGN KEY (`person_id`) REFERENCES `manager_main`.`people` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_task_assignees_tasks` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_comments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `task_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `comment` TEXT NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_comments_task_created` (`task_id`, `created`),
  KEY `idx_task_comments_user` (`user_id`),
  CONSTRAINT `fk_task_comments_manager_main_users` FOREIGN KEY (`user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_task_comments_tasks` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_executions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `task_assignee_id` INT NOT NULL,
  `started_by_user_id` INT NOT NULL,
  `paused_by_user_id` INT DEFAULT NULL,
  `started_at` DATETIME NOT NULL,
  `paused_at` DATETIME DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_executions_assignee` (`task_assignee_id`),
  KEY `idx_task_executions_open` (`task_assignee_id`, `paused_at`),
  KEY `idx_task_executions_started_by_user` (`started_by_user_id`),
  KEY `idx_task_executions_paused_by_user` (`paused_by_user_id`),
  CONSTRAINT `fk_task_executions_manager_main_users_paused_by` FOREIGN KEY (`paused_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_task_executions_manager_main_users_started_by` FOREIGN KEY (`started_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_task_executions_task_assignees` FOREIGN KEY (`task_assignee_id`) REFERENCES `task_assignees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_statuses` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(140) NOT NULL,
  `description` TEXT,
  `bootstrap_class` VARCHAR(120) NOT NULL DEFAULT 'text-bg-secondary',
  `sort_order` INT NOT NULL DEFAULT '0',
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by_user_id` INT DEFAULT NULL,
  `updated_by_user_id` INT DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_statuses_slug` (`slug`),
  KEY `idx_crm_statuses_active` (`active`, `sort_order`),
  KEY `idx_crm_statuses_created_by` (`created_by_user_id`),
  KEY `idx_crm_statuses_updated_by` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_statuses_manager_main_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_statuses_manager_main_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_activity_statuses` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(140) NOT NULL,
  `description` TEXT,
  `bootstrap_class` VARCHAR(120) NOT NULL DEFAULT 'text-bg-secondary',
  `sort_order` INT NOT NULL DEFAULT '0',
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `is_final` TINYINT(1) NOT NULL DEFAULT '0',
  `created_by_user_id` INT DEFAULT NULL,
  `updated_by_user_id` INT DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_activity_statuses_slug` (`slug`),
  KEY `idx_crm_activity_statuses_active` (`active`, `sort_order`),
  KEY `idx_crm_activity_statuses_final` (`is_final`),
  KEY `idx_crm_activity_statuses_created_by` (`created_by_user_id`),
  KEY `idx_crm_activity_statuses_updated_by` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_activity_statuses_manager_main_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_activity_statuses_manager_main_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_activity_types` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(140) NOT NULL,
  `description` TEXT,
  `bootstrap_class` VARCHAR(120) NOT NULL DEFAULT 'text-bg-secondary',
  `icon` VARCHAR(80) NOT NULL DEFAULT 'bi-sticky',
  `sort_order` INT NOT NULL DEFAULT '0',
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by_user_id` INT DEFAULT NULL,
  `updated_by_user_id` INT DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_activity_types_slug` (`slug`),
  KEY `idx_crm_activity_types_active` (`active`, `sort_order`),
  KEY `idx_crm_activity_types_created_by` (`created_by_user_id`),
  KEY `idx_crm_activity_types_updated_by` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_activity_types_manager_main_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_activity_types_manager_main_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_inactivity_reasons` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(140) NOT NULL,
  `description` TEXT,
  `bootstrap_class` VARCHAR(120) NOT NULL DEFAULT 'text-bg-secondary',
  `sort_order` INT NOT NULL DEFAULT '0',
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by_user_id` INT DEFAULT NULL,
  `updated_by_user_id` INT DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_inactivity_reasons_slug` (`slug`),
  KEY `idx_crm_inactivity_reasons_active` (`active`, `sort_order`),
  KEY `fk_crm_inactivity_reasons_created_by_user` (`created_by_user_id`),
  KEY `fk_crm_inactivity_reasons_updated_by_user` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_inactivity_reasons_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_inactivity_reasons_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_loss_reasons` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(140) NOT NULL,
  `description` TEXT,
  `bootstrap_class` VARCHAR(120) NOT NULL DEFAULT 'text-bg-secondary',
  `sort_order` INT NOT NULL DEFAULT '0',
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by_user_id` INT DEFAULT NULL,
  `updated_by_user_id` INT DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_loss_reasons_slug` (`slug`),
  KEY `idx_crm_loss_reasons_active` (`active`, `sort_order`),
  KEY `fk_crm_loss_reasons_created_by_user` (`created_by_user_id`),
  KEY `fk_crm_loss_reasons_updated_by_user` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_loss_reasons_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_loss_reasons_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_opportunity_types` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(140) NOT NULL,
  `description` TEXT,
  `bootstrap_class` VARCHAR(120) NOT NULL DEFAULT 'text-bg-secondary',
  `sort_order` INT NOT NULL DEFAULT '0',
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by_user_id` INT DEFAULT NULL,
  `updated_by_user_id` INT DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_opportunity_types_slug` (`slug`),
  KEY `idx_crm_opportunity_types_active` (`active`, `sort_order`),
  KEY `fk_crm_opportunity_types_created_by_user` (`created_by_user_id`),
  KEY `fk_crm_opportunity_types_updated_by_user` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_opportunity_types_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_opportunity_types_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_pipeline_stages` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(140) NOT NULL,
  `description` TEXT,
  `bootstrap_class` VARCHAR(120) NOT NULL DEFAULT 'text-bg-secondary',
  `default_probability` INT NOT NULL DEFAULT '0',
  `is_won` TINYINT(1) NOT NULL DEFAULT '0',
  `is_lost` TINYINT(1) NOT NULL DEFAULT '0',
  `sort_order` INT NOT NULL DEFAULT '0',
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by_user_id` INT DEFAULT NULL,
  `updated_by_user_id` INT DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_pipeline_stages_slug` (`slug`),
  KEY `idx_crm_pipeline_stages_active` (`active`, `sort_order`),
  KEY `idx_crm_pipeline_stages_result` (`is_won`, `is_lost`),
  KEY `fk_crm_pipeline_stages_created_by_user` (`created_by_user_id`),
  KEY `fk_crm_pipeline_stages_updated_by_user` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_pipeline_stages_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_pipeline_stages_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_sources` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(140) NOT NULL,
  `description` TEXT,
  `bootstrap_class` VARCHAR(120) NOT NULL DEFAULT 'text-bg-secondary',
  `sort_order` INT NOT NULL DEFAULT '0',
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by_user_id` INT DEFAULT NULL,
  `updated_by_user_id` INT DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_sources_slug` (`slug`),
  KEY `idx_crm_sources_active` (`active`, `sort_order`),
  KEY `fk_crm_sources_created_by_user` (`created_by_user_id`),
  KEY `fk_crm_sources_updated_by_user` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_sources_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_sources_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_activities` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_person_id` INT NOT NULL,
  `person_id` INT NOT NULL,
  `activity_type_id` INT NOT NULL,
  `activity_status_id` INT NOT NULL,
  `title` VARCHAR(180) NOT NULL,
  `description` TEXT,
  `scheduled_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `responsible_user_id` INT DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by_user_id` INT DEFAULT NULL,
  `updated_by_user_id` INT DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_crm_activities_tenant_person` (`tenant_person_id`),
  KEY `idx_crm_activities_person` (`person_id`),
  KEY `idx_crm_activities_type` (`activity_type_id`),
  KEY `idx_crm_activities_status` (`activity_status_id`),
  KEY `idx_crm_activities_responsible` (`responsible_user_id`),
  KEY `idx_crm_activities_schedule` (`active`, `scheduled_at`),
  KEY `idx_crm_activities_completed` (`completed_at`),
  KEY `idx_crm_activities_created_by` (`created_by_user_id`),
  KEY `idx_crm_activities_updated_by` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_activities_manager_main_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_activities_manager_main_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_activities_person` FOREIGN KEY (`person_id`) REFERENCES `manager_main`.`people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_activities_responsible_user` FOREIGN KEY (`responsible_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_activities_status` FOREIGN KEY (`activity_status_id`) REFERENCES `crm_activity_statuses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_activities_tenant_person` FOREIGN KEY (`tenant_person_id`) REFERENCES `manager_main`.`tenant_people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_activities_type` FOREIGN KEY (`activity_type_id`) REFERENCES `crm_activity_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_opportunities` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_person_id` INT NOT NULL,
  `person_id` INT NOT NULL,
  `crm_source_id` INT DEFAULT NULL,
  `opportunity_type_id` INT DEFAULT NULL,
  `pipeline_stage_id` INT NOT NULL,
  `loss_reason_id` INT DEFAULT NULL,
  `title` VARCHAR(180) NOT NULL,
  `description` TEXT,
  `estimated_value` DECIMAL(15,2) NOT NULL DEFAULT '0.00',
  `probability` INT NOT NULL DEFAULT '0',
  `expected_close_date` DATE DEFAULT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  `responsible_user_id` INT DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by_user_id` INT DEFAULT NULL,
  `updated_by_user_id` INT DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_crm_opportunities_tenant_person` (`tenant_person_id`),
  KEY `idx_crm_opportunities_person` (`person_id`),
  KEY `idx_crm_opportunities_source` (`crm_source_id`),
  KEY `idx_crm_opportunities_type` (`opportunity_type_id`),
  KEY `idx_crm_opportunities_stage` (`pipeline_stage_id`),
  KEY `idx_crm_opportunities_loss_reason` (`loss_reason_id`),
  KEY `idx_crm_opportunities_expected` (`active`, `expected_close_date`),
  KEY `fk_crm_opportunities_responsible_user` (`responsible_user_id`),
  KEY `fk_crm_opportunities_created_by_user` (`created_by_user_id`),
  KEY `fk_crm_opportunities_updated_by_user` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_opportunities_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_opportunities_loss_reason` FOREIGN KEY (`loss_reason_id`) REFERENCES `crm_loss_reasons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_opportunities_person` FOREIGN KEY (`person_id`) REFERENCES `manager_main`.`people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_opportunities_responsible_user` FOREIGN KEY (`responsible_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_opportunities_source` FOREIGN KEY (`crm_source_id`) REFERENCES `crm_sources` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_opportunities_stage` FOREIGN KEY (`pipeline_stage_id`) REFERENCES `crm_pipeline_stages` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_opportunities_tenant_person` FOREIGN KEY (`tenant_person_id`) REFERENCES `manager_main`.`tenant_people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_opportunities_type` FOREIGN KEY (`opportunity_type_id`) REFERENCES `crm_opportunity_types` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_opportunities_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schedule_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `background_color` CHAR(7) NOT NULL DEFAULT '#0d6efd',
  `description_color` CHAR(7) NOT NULL DEFAULT '#ffffff',
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schedule_categories_name` (`name`),
  KEY `idx_schedule_categories_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schedule_events_recurrences` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `schedule_category_id` INT DEFAULT NULL,
  `title` VARCHAR(160) NOT NULL,
  `description` MEDIUMTEXT,
  `location` VARCHAR(160) DEFAULT NULL,
  `all_day` TINYINT(1) NOT NULL DEFAULT '0',
  `start_time` TIME DEFAULT NULL,
  `end_time` TIME DEFAULT NULL,
  `frequency` ENUM('daily','weekly','monthly') NOT NULL,
  `interval_value` INT UNSIGNED NOT NULL DEFAULT '1',
  `period_start` DATE NOT NULL,
  `period_end` DATE NOT NULL,
  `allow_weekends` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by_user_id` INT NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_schedule_events_recurrences_category` (`schedule_category_id`),
  KEY `idx_schedule_events_recurrences_period` (`period_start`, `period_end`),
  KEY `idx_schedule_events_recurrences_created_by_user` (`created_by_user_id`),
  KEY `idx_schedule_events_recurrences_active` (`active`),
  CONSTRAINT `fk_schedule_events_recurrences_categories` FOREIGN KEY (`schedule_category_id`) REFERENCES `schedule_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_schedule_events_recurrences_manager_main_users` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schedule_events` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `schedule_category_id` INT DEFAULT NULL,
  `schedule_events_recurrence_id` INT DEFAULT NULL,
  `recurrence_sequence` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(160) NOT NULL,
  `description` MEDIUMTEXT,
  `location` VARCHAR(160) DEFAULT NULL,
  `all_day` TINYINT(1) NOT NULL DEFAULT '0',
  `starts_at` DATETIME NOT NULL,
  `ends_at` DATETIME DEFAULT NULL,
  `status` ENUM('scheduled','completed','canceled') NOT NULL DEFAULT 'scheduled',
  `created_by_user_id` INT NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schedule_events_recurrence_sequence` (`schedule_events_recurrence_id`, `recurrence_sequence`),
  KEY `idx_schedule_events_category` (`schedule_category_id`),
  KEY `idx_schedule_events_recurrence` (`schedule_events_recurrence_id`),
  KEY `idx_schedule_events_starts_at` (`starts_at`),
  KEY `idx_schedule_events_status` (`status`),
  KEY `idx_schedule_events_created_by_user` (`created_by_user_id`),
  CONSTRAINT `fk_schedule_events_categories` FOREIGN KEY (`schedule_category_id`) REFERENCES `schedule_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_schedule_events_manager_main_users` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_schedule_events_recurrences` FOREIGN KEY (`schedule_events_recurrence_id`) REFERENCES `schedule_events_recurrences` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schedule_events_participants` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `schedule_event_id` INT NOT NULL,
  `person_id` INT NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schedule_events_participants_event_person` (`schedule_event_id`, `person_id`),
  KEY `idx_schedule_events_participants_event_active` (`schedule_event_id`, `active`),
  KEY `idx_schedule_events_participants_person` (`person_id`),
  CONSTRAINT `fk_schedule_events_participants_events` FOREIGN KEY (`schedule_event_id`) REFERENCES `schedule_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_schedule_events_participants_manager_main_people` FOREIGN KEY (`person_id`) REFERENCES `manager_main`.`people` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `crm_activity_statuses` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `sort_order`, `active`, `is_final`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`)
VALUES
  (1, 'Pendente', 'pending', 'Atividade criada e ainda não executada.', 'text-bg-warning', 10, 1, 0, 2, 2, NOW(), NOW()),
  (2, 'Em andamento', 'in_progress', 'Atividade iniciada e ainda em execução.', 'text-bg-primary', 20, 1, 0, 2, 2, NOW(), NOW()),
  (3, 'Concluída', 'completed', 'Atividade finalizada com sucesso.', 'text-bg-success', 30, 1, 1, 2, 2, NOW(), NOW()),
  (4, 'Reagendada', 'rescheduled', 'Atividade adiada para nova data.', 'text-bg-info', 40, 1, 0, 2, 2, NOW(), NOW()),
  (5, 'Cancelada', 'canceled', 'Atividade encerrada sem execução.', 'text-bg-secondary', 50, 1, 1, 2, 2, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `bootstrap_class` = VALUES(`bootstrap_class`),
  `sort_order` = VALUES(`sort_order`),
  `active` = VALUES(`active`),
  `is_final` = VALUES(`is_final`),
  `updated_by_user_id` = VALUES(`updated_by_user_id`),
  `modified` = NOW();

INSERT INTO `crm_activity_types` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `icon`, `sort_order`, `active`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`)
VALUES
  (1, 'Ligação', 'phone_call', 'Contato por telefone com o lead, prospect ou cliente.', 'text-bg-primary', 'bi-telephone', 10, 1, 2, 2, NOW(), NOW()),
  (2, 'WhatsApp', 'whatsapp', 'Contato realizado por WhatsApp.', 'text-bg-success', 'bi-whatsapp', 20, 1, 2, 2, NOW(), NOW()),
  (3, 'E-mail', 'email', 'Envio ou resposta por e-mail.', 'text-bg-info', 'bi-envelope', 30, 1, 2, 2, NOW(), NOW()),
  (4, 'Reunião', 'meeting', 'Reunião presencial ou por videoconferência.', 'text-bg-primary', 'bi-people', 40, 1, 2, 2, NOW(), NOW()),
  (5, 'Anotação', 'note', 'Registro interno de informação comercial.', 'text-bg-secondary', 'bi-journal-text', 50, 1, 2, 2, NOW(), NOW()),
  (6, 'Tarefa', 'task', 'Ação operacional vinculada ao contato CRM.', 'text-bg-warning', 'bi-list-check', 60, 1, 2, 2, NOW(), NOW()),
  (7, 'Visita', 'visit', 'Visita presencial ao cliente ou prospect.', 'text-bg-dark', 'bi-geo-alt', 70, 1, 2, 2, NOW(), NOW()),
  (8, 'Follow-up', 'follow_up', 'Retorno programado para continuidade da negociação.', 'text-bg-danger', 'bi-arrow-repeat', 80, 1, 2, 2, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `bootstrap_class` = VALUES(`bootstrap_class`),
  `icon` = VALUES(`icon`),
  `sort_order` = VALUES(`sort_order`),
  `active` = VALUES(`active`),
  `updated_by_user_id` = VALUES(`updated_by_user_id`),
  `modified` = NOW();

INSERT INTO `crm_inactivity_reasons` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `sort_order`, `active`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`)
VALUES
  (1, 'Sem relacionamento ativo', 'sem_relacionamento_ativo', 'Contato sem relação comercial ativa no momento.', 'text-bg-secondary', 10, 1, 2, 2, NOW(), NOW()),
  (2, 'Contato inválido', 'contato_invalido', 'Telefone, e-mail ou dados de contato não permitem continuidade.', 'text-bg-danger', 20, 1, 2, 2, NOW(), NOW()),
  (3, 'Projeto pausado', 'projeto_pausado', 'Cliente pausou a iniciativa ou projeto.', 'text-bg-warning', 30, 1, 2, 2, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `bootstrap_class` = VALUES(`bootstrap_class`),
  `sort_order` = VALUES(`sort_order`),
  `active` = VALUES(`active`),
  `updated_by_user_id` = VALUES(`updated_by_user_id`),
  `modified` = NOW();

INSERT INTO `crm_loss_reasons` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `sort_order`, `active`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`)
VALUES
  (1, 'Preço', 'preco', 'Cliente considerou o preço incompatível.', 'text-bg-warning', 10, 1, 2, 2, NOW(), NOW()),
  (2, 'Sem orçamento', 'sem_orcamento', 'Cliente não possui orçamento no momento.', 'text-bg-secondary', 20, 1, 2, 2, NOW(), NOW()),
  (3, 'Prazo incompatível', 'prazo_incompativel', 'Prazo desejado não pôde ser atendido.', 'text-bg-info', 30, 1, 2, 2, NOW(), NOW()),
  (4, 'Concorrente', 'concorrente', 'Cliente escolheu uma solução concorrente.', 'text-bg-danger', 40, 1, 2, 2, NOW(), NOW()),
  (5, 'Sem retorno', 'sem_retorno', 'Cliente não retornou após tentativas de contato.', 'text-bg-dark', 50, 1, 2, 2, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `bootstrap_class` = VALUES(`bootstrap_class`),
  `sort_order` = VALUES(`sort_order`),
  `active` = VALUES(`active`),
  `updated_by_user_id` = VALUES(`updated_by_user_id`),
  `modified` = NOW();

INSERT INTO `crm_opportunity_types` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `sort_order`, `active`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`)
VALUES
  (1, 'Venda nova', 'venda_nova', 'Primeira venda para o contato.', 'text-bg-primary', 10, 1, 2, 2, NOW(), NOW()),
  (2, 'Renovação', 'renovacao', 'Renovação de contrato ou compra recorrente.', 'text-bg-success', 20, 1, 2, 2, NOW(), NOW()),
  (3, 'Upsell', 'upsell', 'Ampliação de escopo, plano ou valor contratado.', 'text-bg-info', 30, 1, 2, 2, NOW(), NOW()),
  (4, 'Reativação', 'reativacao', 'Retomada de contato ou cliente inativo.', 'text-bg-warning', 40, 1, 2, 2, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `bootstrap_class` = VALUES(`bootstrap_class`),
  `sort_order` = VALUES(`sort_order`),
  `active` = VALUES(`active`),
  `updated_by_user_id` = VALUES(`updated_by_user_id`),
  `modified` = NOW();

INSERT INTO `crm_pipeline_stages` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `default_probability`, `is_won`, `is_lost`, `sort_order`, `active`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`)
VALUES
  (1, 'Novo', 'novo', 'Oportunidade recém-criada.', 'text-bg-primary', 10, 0, 0, 10, 1, 2, 2, NOW(), NOW()),
  (2, 'Qualificação', 'qualificacao', 'Necessidade, orçamento e prazo em validação.', 'text-bg-info', 25, 0, 0, 20, 1, 2, 2, NOW(), NOW()),
  (3, 'Proposta', 'proposta', 'Proposta comercial enviada.', 'text-bg-warning', 50, 0, 0, 30, 1, 2, 2, NOW(), NOW()),
  (4, 'Negociação', 'negociacao', 'Condições comerciais em negociação.', 'text-bg-dark', 75, 0, 0, 40, 1, 2, 2, NOW(), NOW()),
  (5, 'Ganha', 'ganha', 'Oportunidade convertida com sucesso.', 'text-bg-success', 100, 1, 0, 50, 1, 2, 2, NOW(), NOW()),
  (6, 'Perdida', 'perdida', 'Oportunidade encerrada sem conversão.', 'text-bg-danger', 0, 0, 1, 60, 1, 2, 2, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `bootstrap_class` = VALUES(`bootstrap_class`),
  `default_probability` = VALUES(`default_probability`),
  `is_won` = VALUES(`is_won`),
  `is_lost` = VALUES(`is_lost`),
  `sort_order` = VALUES(`sort_order`),
  `active` = VALUES(`active`),
  `updated_by_user_id` = VALUES(`updated_by_user_id`),
  `modified` = NOW();

INSERT INTO `crm_sources` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `sort_order`, `active`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`)
VALUES
  (1, 'Indicação', 'indicacao', 'Contato indicado por cliente, parceiro ou relacionamento.', 'text-bg-success', 10, 1, 2, 2, NOW(), NOW()),
  (2, 'Site', 'site', 'Contato originado pelo site institucional ou landing page.', 'text-bg-primary', 20, 1, 2, 2, NOW(), NOW()),
  (3, 'WhatsApp', 'whatsapp', 'Contato iniciado por WhatsApp.', 'text-bg-success', 30, 1, 2, 2, NOW(), NOW()),
  (4, 'Instagram', 'instagram', 'Contato vindo de rede social Instagram.', 'text-bg-danger', 40, 1, 2, 2, NOW(), NOW()),
  (5, 'Google Ads', 'google_ads', 'Contato gerado por campanha paga no Google.', 'text-bg-warning', 50, 1, 2, 2, NOW(), NOW()),
  (6, 'Prospecção ativa', 'prospeccao_ativa', 'Contato gerado por abordagem comercial ativa.', 'text-bg-info', 60, 1, 2, 2, NOW(), NOW()),
  (7, 'Cliente antigo', 'cliente_antigo', 'Reativação ou nova demanda de cliente antigo.', 'text-bg-secondary', 70, 1, 2, 2, NOW(), NOW()),
  (8, 'Evento', 'evento', 'Contato captado em evento, feira ou encontro comercial.', 'text-bg-dark', 80, 1, 2, 2, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `bootstrap_class` = VALUES(`bootstrap_class`),
  `sort_order` = VALUES(`sort_order`),
  `active` = VALUES(`active`),
  `updated_by_user_id` = VALUES(`updated_by_user_id`),
  `modified` = NOW();

INSERT INTO `crm_statuses` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `sort_order`, `active`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`)
VALUES
  (1, 'Lead', 'lead', 'Contato inicial recebido, ainda sem qualificação comercial.', 'text-bg-primary', 10, 1, 2, 2, NOW(), NOW()),
  (2, 'Prospect', 'prospect', 'Contato com potencial comercial identificado.', 'text-bg-info', 20, 1, 2, 2, NOW(), NOW()),
  (3, 'Cliente', 'customer', 'Pessoa ou empresa que já possui relacionamento comercial ativo.', 'text-bg-success', 60, 1, 2, 2, NOW(), NOW()),
  (4, 'Inativo', 'inactive', 'Contato sem relacionamento comercial ativo no momento.', 'text-bg-secondary', 80, 1, 2, 2, NOW(), NOW()),
  (5, 'Qualificado', 'qualified', 'Contato validado e pronto para abordagem comercial estruturada.', 'text-bg-info', 30, 1, 2, 2, NOW(), NOW()),
  (6, 'Proposta enviada', 'proposal_sent', 'Proposta comercial enviada e aguardando retorno.', 'text-bg-warning', 40, 1, 2, 2, NOW(), NOW()),
  (7, 'Negociação', 'negotiation', 'Contato em negociação ativa de condições comerciais.', 'text-bg-dark', 50, 1, 2, 2, NOW(), NOW()),
  (8, 'Perdido', 'lost', 'Oportunidade encerrada sem conversão em cliente.', 'text-bg-danger', 70, 1, 2, 2, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `bootstrap_class` = VALUES(`bootstrap_class`),
  `sort_order` = VALUES(`sort_order`),
  `active` = VALUES(`active`),
  `updated_by_user_id` = VALUES(`updated_by_user_id`),
  `modified` = NOW();

INSERT INTO `product_units` (`id`, `code`, `name`, `symbol`, `measurement_type`, `factor_to_base`, `decimal_places`, `active`, `created`, `modified`)
VALUES
  (1, 'un', 'Unidade', 'un', 'UNIT', 1.0000000000, 0, 1, NOW(), NULL),
  (2, 'mm', 'Milímetro', 'mm', 'LENGTH', 0.0010000000, 3, 1, NOW(), NULL),
  (3, 'cm', 'Centímetro', 'cm', 'LENGTH', 0.0100000000, 2, 1, NOW(), NULL),
  (4, 'm', 'Metro', 'm', 'LENGTH', 1.0000000000, 3, 1, NOW(), NULL),
  (5, 'km', 'Quilômetro', 'km', 'LENGTH', 1000.0000000000, 3, 1, NOW(), NULL),
  (6, 'g', 'Grama', 'g', 'WEIGHT', 1.0000000000, 0, 1, NOW(), NULL),
  (7, 'kg', 'Quilograma', 'kg', 'WEIGHT', 1000.0000000000, 3, 1, NOW(), NULL)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `symbol` = VALUES(`symbol`),
  `measurement_type` = VALUES(`measurement_type`),
  `factor_to_base` = VALUES(`factor_to_base`),
  `decimal_places` = VALUES(`decimal_places`),
  `active` = VALUES(`active`),
  `modified` = NOW();