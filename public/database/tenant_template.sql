CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `version` VARCHAR(20) NOT NULL COMMENT 'Version number (e.g.: 1.5.0)',
  `description` VARCHAR(255) DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schema_versions_version_unique` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT IGNORE INTO `schema_versions` (
  `version`,
  `description`,
  `created`,
  `modified`
) VALUES (
  '1.0.0',
  'Initial tenant schema',
  NOW(),
  NOW()
);


INSERT IGNORE INTO `manager_main`.`user_permissions` (
  `user_id`,
  `permission_id`,
  `created`,
  `modified`
)
SELECT 
  TenantUser.`user_id`,
  SourcePermission.`permission_id`,
  NOW(),
  NOW()
FROM `manager_main`.`tenants` AS Tenant
INNER JOIN `manager_main`.`tenant_users` AS TenantUser
        ON TenantUser.`tenant_id` = Tenant.`id`
INNER JOIN `manager_main`.`user_permissions` AS SourcePermission
        ON SourcePermission.`user_id` = 2
INNER JOIN `manager_main`.`permissions` AS PermissionCatalog
        ON PermissionCatalog.`id` = SourcePermission.`permission_id`
       AND PermissionCatalog.`system` = 'manager'
WHERE DATABASE() REGEXP '^manager_[0-9]{4}$'
  AND LPAD(CAST(Tenant.`database_number` AS UNSIGNED), 4, '0') = SUBSTRING(DATABASE(), CHAR_LENGTH('manager_') + 1)
  AND TenantUser.`role` = 'owner';


CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `bank_id` INT NOT NULL,
  `name` VARCHAR(50) NOT NULL,
  `agency` VARCHAR(6) NOT NULL,
  `account` VARCHAR(50) NOT NULL,
  `pix_key_type` ENUM('CPF','CNPJ','EMAIL','PHONE','RANDOM') NULL DEFAULT NULL,
  `pix_key` VARCHAR(255) NULL DEFAULT NULL,
  `bank_slip_instructions_line_1` VARCHAR(255) NULL DEFAULT NULL,
  `bank_slip_instructions_line_2` VARCHAR(255) NULL DEFAULT NULL,
  `bank_slip_instructions_line_3` VARCHAR(255) NULL DEFAULT NULL,
  `bank_slip_instructions_line_4` VARCHAR(255) NULL DEFAULT NULL,
  `bank_slip_protest` TINYINT(1) NULL DEFAULT NULL,
  `bank_slip_protest_number_of_days` TINYINT(2) NULL DEFAULT NULL,
  `bank_slip_wallet` VARCHAR(50) NULL DEFAULT NULL,
  `bank_slip_species_title` VARCHAR(50) NULL DEFAULT NULL,
  `bank_slip_interest_type` ENUM('DAILY','MONTHLY') NULL DEFAULT NULL,
  `bank_slip_interest_amount_type` ENUM('PERCENTAGE','FIXED_AMOUNT') NULL DEFAULT NULL,
  `bank_slip_interest` DECIMAL(10,2) NULL DEFAULT NULL,
  `bank_slip_late_fee_amount` DECIMAL(10,2) NULL DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  INDEX `fk_bank_accounts_manager_main_banks_idx` (`bank_id`),

  CONSTRAINT `fk_bank_accounts_manager_main_banks`
    FOREIGN KEY (`bank_id`)
    REFERENCES `manager_main`.`banks` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `financial_operations_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `financial_operations_categories_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `financial_operations_subcategories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `financial_operations_subcategories_category_name_unique` (
    `financial_operation_category_id`,
    `name`
  ),

  INDEX `fk_financial_operations_subcategories_categories_idx` (
    `financial_operation_category_id`
  ),

  CONSTRAINT `fk_financial_operations_subcategories_categories`
    FOREIGN KEY (`financial_operation_category_id`)
    REFERENCES `financial_operations_categories` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `financial_operations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` INT NOT NULL,
  `financial_operation_subcategory_id` INT DEFAULT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  INDEX `fk_financial_operations_categories_idx` (
    `financial_operation_category_id`
  ),

  INDEX `fk_financial_operations_subcategories_idx` (
    `financial_operation_subcategory_id`
  ),

  CONSTRAINT `fk_financial_operations_categories`
    FOREIGN KEY (`financial_operation_category_id`)
    REFERENCES `financial_operations_categories` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

  CONSTRAINT `fk_financial_operations_subcategories`
    FOREIGN KEY (`financial_operation_subcategory_id`)
    REFERENCES `financial_operations_subcategories` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `financial_operations_payments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `financial_operation_id` INT NOT NULL,
  `value` DECIMAL(10,2) NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  INDEX `fk_financial_operations_payments_operations_idx` (
    `financial_operation_id`
  ),

  CONSTRAINT `fk_financial_operations_payments_operations`
    FOREIGN KEY (`financial_operation_id`)
    REFERENCES `financial_operations` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `bank_files` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `bank_account_id` INT DEFAULT NULL,
  `file_type` ENUM('REMITTANCE','RETURN_FILE') NOT NULL,
  `file_name` VARCHAR(150) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_hash` CHAR(64) DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_bank_files_path` (`file_path`),

  INDEX `idx_bank_files_bank_account_id` (`bank_account_id`),
  INDEX `idx_bank_files_file_type` (`file_type`),

  CONSTRAINT `fk_bank_files_bank_accounts`
    FOREIGN KEY (`bank_account_id`)
    REFERENCES `bank_accounts` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `bank_slips` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `financial_operation_payment_id` INT DEFAULT NULL,
  `bank_account_id` INT NOT NULL,
  `bank_file_id` INT DEFAULT NULL,

  `status` ENUM(
    'DRAFT',
    'READY',
    'REMITTANCE_GENERATED',
    'SENT',
    'REGISTERED',
    'REJECTED',
    'PAID',
    'CANCELED',
    'WRITTEN_OFF'
  ) NOT NULL DEFAULT 'DRAFT',

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
  `interest_amount_per_day` DECIMAL(12,2) NOT NULL DEFAULT 0.00,

  `discount_date` DATE DEFAULT NULL,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,

  `fine_date` DATE DEFAULT NULL,
  `fine_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,

  `protest_code` VARCHAR(2) DEFAULT NULL,
  `protest_days` TINYINT UNSIGNED DEFAULT NULL,

  `write_off_code` VARCHAR(2) DEFAULT NULL,
  `write_off_days` TINYINT UNSIGNED DEFAULT NULL,

  `last_occurrence_code` VARCHAR(2) DEFAULT NULL,
  `last_occurrence_message` VARCHAR(255) DEFAULT NULL,

  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_bank_slips_financial_operation_payment` (
    `financial_operation_payment_id`
  ),

  UNIQUE KEY `uq_bank_slips_account_our_number` (
    `bank_account_id`,
    `our_number`
  ),

  INDEX `idx_bank_slips_bank_account_id` (`bank_account_id`),
  INDEX `idx_bank_slips_bank_file_id` (`bank_file_id`),
  INDEX `idx_bank_slips_status` (`status`),
  INDEX `idx_bank_slips_due_date` (`due_date`),
  INDEX `idx_bank_slips_document_number` (`document_number`),
  INDEX `idx_bank_slips_payer_document` (`payer_document`),

  CONSTRAINT `fk_bank_slips_bank_accounts`
    FOREIGN KEY (`bank_account_id`)
    REFERENCES `bank_accounts` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

  CONSTRAINT `fk_bank_slips_bank_files`
    FOREIGN KEY (`bank_file_id`)
    REFERENCES `bank_files` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,

  CONSTRAINT `fk_bank_slips_financial_operations_payments`
    FOREIGN KEY (`financial_operation_payment_id`)
    REFERENCES `financial_operations_payments` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- Store module
-- products pertence a manager_main.users
-- manager_main.users has many products
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS `products_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_products_categories_name` (`name`),
  KEY `idx_products_categories_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `products` (
  `id` INT NOT NULL AUTO_INCREMENT,

  `user_id` INT NOT NULL,

  `product_category_id` INT DEFAULT NULL,
  `sku` VARCHAR(60) DEFAULT NULL,
  `barcode` VARCHAR(80) DEFAULT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `sale_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `minimum_stock` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `unit` VARCHAR(20) NOT NULL DEFAULT 'UN',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
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

  CONSTRAINT `fk_products_manager_main_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `manager_main`.`users` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

  CONSTRAINT `fk_products_categories`
    FOREIGN KEY (`product_category_id`)
    REFERENCES `products_categories` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `products_images` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `original_name` VARCHAR(255) DEFAULT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `thumb_path` VARCHAR(255) DEFAULT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` INT UNSIGNED DEFAULT NULL,
  `width` INT UNSIGNED DEFAULT NULL,
  `height` INT UNSIGNED DEFAULT NULL,
  `position` INT NOT NULL DEFAULT 0,
  `is_main` TINYINT(1) NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_products_images_file_path` (`file_path`),
  KEY `idx_products_images_product` (`product_id`),
  KEY `idx_products_images_main` (`product_id`, `is_main`),

  CONSTRAINT `fk_products_images_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `products_stocks_movements` (
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

  KEY `idx_products_stocks_movements_product` (`product_id`),
  KEY `idx_products_stocks_movements_type` (`movement_type`),
  KEY `idx_products_stocks_movements_date` (`movement_date`),

  CONSTRAINT `fk_products_stocks_movements_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `schema_versions` (
  `version`,
  `description`,
  `created`,
  `modified`
)
VALUES (
  'store-001',
  'Store products, images and stock module',
  NOW(),
  NOW()
)
ON DUPLICATE KEY UPDATE
  `description` = VALUES(`description`),
  `modified` = NOW();