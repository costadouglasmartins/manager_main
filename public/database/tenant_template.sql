-- =====================================================
-- SCHEMA VERSIONS
-- =====================================================

CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `version` VARCHAR(20) NOT NULL COMMENT 'Version number (e.g.: 1.5.0)',
  `description` VARCHAR(255) DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_schema_versions_version` (`version`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


INSERT INTO `schema_versions` (
  `version`,
  `description`,
  `created`,
  `modified`
)
VALUES (
  '1.0.0',
  'Initial tenant schema',
  NOW(),
  NOW()
)
ON DUPLICATE KEY UPDATE
  `description` = 'Initial tenant schema',
  `modified` = NOW();


-- =====================================================
-- USER PERMISSIONS
-- Copia para os owners as permissões do usuário modelo 2
-- =====================================================

INSERT INTO `manager_main`.`user_permissions` (
  `user_id`,
  `permission_id`,
  `created`,
  `modified`
)
SELECT DISTINCT
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

  AND Tenant.`database_number` IS NOT NULL

  AND LPAD(
        CAST(Tenant.`database_number` AS UNSIGNED),
        4,
        '0'
      ) = SUBSTRING(
        DATABASE(),
        CHAR_LENGTH('manager_') + 1
      )

  AND TenantUser.`role` = 'owner'

  AND NOT EXISTS (
    SELECT 1
    FROM `manager_main`.`user_permissions` AS ExistingPermission
    WHERE ExistingPermission.`user_id` = TenantUser.`user_id`
      AND ExistingPermission.`permission_id` = SourcePermission.`permission_id`
  );


-- =====================================================
-- BANK ACCOUNTS
-- =====================================================

CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `bank_id` INT NOT NULL,
  `name` VARCHAR(50) NOT NULL,
  `agency` VARCHAR(6) NOT NULL,
  `account` VARCHAR(50) NOT NULL,

  `pix_key_type` ENUM(
    'CPF',
    'CNPJ',
    'EMAIL',
    'PHONE',
    'RANDOM'
  ) DEFAULT NULL,

  `pix_key` VARCHAR(255) DEFAULT NULL,

  `bank_slip_instructions_line_1` VARCHAR(255) DEFAULT NULL,
  `bank_slip_instructions_line_2` VARCHAR(255) DEFAULT NULL,
  `bank_slip_instructions_line_3` VARCHAR(255) DEFAULT NULL,
  `bank_slip_instructions_line_4` VARCHAR(255) DEFAULT NULL,

  `bank_slip_protest` TINYINT(1) DEFAULT NULL,
  `bank_slip_protest_number_of_days` TINYINT UNSIGNED DEFAULT NULL,
  `bank_slip_wallet` VARCHAR(50) DEFAULT NULL,
  `bank_slip_species_title` VARCHAR(50) DEFAULT NULL,

  `bank_slip_interest_type` ENUM(
    'DAILY',
    'MONTHLY'
  ) DEFAULT NULL,

  `bank_slip_interest_amount_type` ENUM(
    'PERCENTAGE',
    'FIXED_AMOUNT'
  ) DEFAULT NULL,

  `bank_slip_interest` DECIMAL(10,2) DEFAULT NULL,
  `bank_slip_late_fee_amount` DECIMAL(10,2) DEFAULT NULL,

  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  KEY `idx_bank_accounts_bank_id` (`bank_id`),
  KEY `idx_bank_accounts_active` (`active`),

  CONSTRAINT `fk_bank_accounts_manager_main_banks`
    FOREIGN KEY (`bank_id`)
    REFERENCES `manager_main`.`banks` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- FINANCIAL OPERATION CATEGORIES
-- =====================================================

CREATE TABLE IF NOT EXISTS `financial_operations_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_financial_operations_categories_name` (`name`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- FINANCIAL OPERATION SUBCATEGORIES
-- =====================================================

CREATE TABLE IF NOT EXISTS `financial_operations_subcategories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_financial_operations_subcategories_category_name` (
    `financial_operation_category_id`,
    `name`
  ),

  UNIQUE KEY `uq_financial_operations_subcategories_id_category` (
    `id`,
    `financial_operation_category_id`
  ),

  KEY `idx_financial_operations_subcategories_category_id` (
    `financial_operation_category_id`
  ),

  CONSTRAINT `fk_financial_operations_subcategories_categories`
    FOREIGN KEY (`financial_operation_category_id`)
    REFERENCES `financial_operations_categories` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- FINANCIAL OPERATIONS
-- =====================================================

CREATE TABLE IF NOT EXISTS `financial_operations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` INT NOT NULL,
  `financial_operation_subcategory_id` INT DEFAULT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  KEY `idx_financial_operations_category_id` (
    `financial_operation_category_id`
  ),

  KEY `idx_financial_operations_subcategory_category` (
    `financial_operation_subcategory_id`,
    `financial_operation_category_id`
  ),

  CONSTRAINT `fk_financial_operations_categories`
    FOREIGN KEY (`financial_operation_category_id`)
    REFERENCES `financial_operations_categories` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

  CONSTRAINT `fk_financial_operations_subcategory_category`
    FOREIGN KEY (
      `financial_operation_subcategory_id`,
      `financial_operation_category_id`
    )
    REFERENCES `financial_operations_subcategories` (
      `id`,
      `financial_operation_category_id`
    )
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- FINANCIAL OPERATION PAYMENTS
-- =====================================================

CREATE TABLE IF NOT EXISTS `financial_operations_payments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `financial_operation_id` INT NOT NULL,
  `value` DECIMAL(12,2) NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  KEY `idx_financial_operations_payments_operation_id` (
    `financial_operation_id`
  ),

  CONSTRAINT `fk_financial_operations_payments_operations`
    FOREIGN KEY (`financial_operation_id`)
    REFERENCES `financial_operations` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- BANK FILES
-- =====================================================

CREATE TABLE IF NOT EXISTS `bank_files` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `bank_account_id` INT DEFAULT NULL,

  `file_type` ENUM(
    'REMITTANCE',
    'RETURN_FILE'
  ) NOT NULL,

  `file_name` VARCHAR(150) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_hash` CHAR(64) DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_bank_files_path` (`file_path`),

  KEY `idx_bank_files_bank_account_id` (`bank_account_id`),
  KEY `idx_bank_files_file_type` (`file_type`),
  KEY `idx_bank_files_file_hash` (`file_hash`),

  CONSTRAINT `fk_bank_files_bank_accounts`
    FOREIGN KEY (`bank_account_id`)
    REFERENCES `bank_accounts` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- BANK SLIPS
-- =====================================================

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

  `payer_document_type` ENUM(
    'CPF',
    'CNPJ'
  ) NOT NULL,

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
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_bank_slips_financial_operation_payment` (
    `financial_operation_payment_id`
  ),

  UNIQUE KEY `uq_bank_slips_account_our_number` (
    `bank_account_id`,
    `our_number`
  ),

  KEY `idx_bank_slips_bank_account_id` (`bank_account_id`),
  KEY `idx_bank_slips_bank_file_id` (`bank_file_id`),
  KEY `idx_bank_slips_status` (`status`),
  KEY `idx_bank_slips_due_date` (`due_date`),
  KEY `idx_bank_slips_document_number` (`document_number`),
  KEY `idx_bank_slips_payer_document` (`payer_document`),

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
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- PRODUCT CATEGORIES
-- =====================================================

CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_product_categories_name` (`name`),

  KEY `idx_product_categories_active` (`active`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- PRODUCTS
-- manager_main.users has many products
-- =====================================================

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
  KEY `idx_products_product_category` (`product_category_id`),
  KEY `idx_products_name` (`name`),
  KEY `idx_products_active` (`active`),
  KEY `idx_products_stock_quantity` (`stock_quantity`),

  CONSTRAINT `fk_products_manager_main_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `manager_main`.`users` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

  CONSTRAINT `fk_products_product_categories`
    FOREIGN KEY (`product_category_id`)
    REFERENCES `product_categories` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- PRODUCT IMAGES
-- =====================================================

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
  `position` INT NOT NULL DEFAULT 0,
  `is_main` TINYINT(1) NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_product_images_file_path` (`file_path`),

  KEY `idx_product_images_product` (`product_id`),
  KEY `idx_product_images_main` (`product_id`, `is_main`),
  KEY `idx_product_images_position` (`product_id`, `position`),

  CONSTRAINT `fk_product_images_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- PRODUCT STOCK MOVEMENTS
-- =====================================================

CREATE TABLE IF NOT EXISTS `product_stocks_movements` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_id` INT NOT NULL,

  `movement_type` ENUM(
    'IN',
    'OUT',
    'ADJUSTMENT'
  ) NOT NULL,

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

  CONSTRAINT `fk_product_stocks_movements_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


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
  `description` = 'Store products, images and stock module',
  `modified` = NOW();


-- =====================================================
-- TASK CATEGORIES
-- =====================================================

CREATE TABLE IF NOT EXISTS `task_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_task_categories_name` (`name`),

  KEY `idx_task_categories_active` (`active`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- TASKS
-- =====================================================

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `task_category_id` INT DEFAULT NULL,
  `title` VARCHAR(160) NOT NULL,

  `status` ENUM(
    'open',
    'in_progress',
    'paused',
    'completed',
    'canceled'
  ) NOT NULL DEFAULT 'open',

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

  CONSTRAINT `fk_tasks_task_categories`
    FOREIGN KEY (`task_category_id`)
    REFERENCES `task_categories` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,

  CONSTRAINT `fk_tasks_manager_main_people_third_party`
    FOREIGN KEY (`third_party_person_id`)
    REFERENCES `manager_main`.`people` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,

  CONSTRAINT `fk_tasks_manager_main_users_created_by`
    FOREIGN KEY (`created_by_user_id`)
    REFERENCES `manager_main`.`users` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- TASK ASSIGNEES
-- =====================================================

CREATE TABLE IF NOT EXISTS `task_assignees` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `task_id` INT NOT NULL,
  `person_id` INT NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_task_assignees_task_person` (
    `task_id`,
    `person_id`
  ),

  KEY `idx_task_assignees_task_active` (
    `task_id`,
    `active`
  ),

  KEY `idx_task_assignees_person` (`person_id`),

  CONSTRAINT `fk_task_assignees_tasks`
    FOREIGN KEY (`task_id`)
    REFERENCES `tasks` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT `fk_task_assignees_manager_main_people`
    FOREIGN KEY (`person_id`)
    REFERENCES `manager_main`.`people` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- TASK COMMENTS
-- =====================================================

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

  CONSTRAINT `fk_task_comments_tasks`
    FOREIGN KEY (`task_id`)
    REFERENCES `tasks` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT `fk_task_comments_manager_main_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `manager_main`.`users` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- TASK EXECUTIONS
-- =====================================================

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

  KEY `idx_task_executions_open` (
    `task_assignee_id`,
    `paused_at`
  ),

  KEY `idx_task_executions_started_by_user` (`started_by_user_id`),
  KEY `idx_task_executions_paused_by_user` (`paused_by_user_id`),

  CONSTRAINT `fk_task_executions_task_assignees`
    FOREIGN KEY (`task_assignee_id`)
    REFERENCES `task_assignees` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT `fk_task_executions_manager_main_users_started_by`
    FOREIGN KEY (`started_by_user_id`)
    REFERENCES `manager_main`.`users` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

  CONSTRAINT `fk_task_executions_manager_main_users_paused_by`
    FOREIGN KEY (`paused_by_user_id`)
    REFERENCES `manager_main`.`users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


INSERT INTO `schema_versions` (
  `version`,
  `description`,
  `created`,
  `modified`
)
VALUES (
  'tasks-001',
  'Tasks, assignees, comments and executions module',
  NOW(),
  NOW()
)
ON DUPLICATE KEY UPDATE
  `description` = 'Tasks, assignees, comments and executions module',
  `modified` = NOW();


-- =====================================================
-- SCHEDULE CATEGORIES
-- =====================================================

CREATE TABLE IF NOT EXISTS `schedule_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `background_color` CHAR(7) NOT NULL DEFAULT '#0d6efd',
  `description_color` CHAR(7) NOT NULL DEFAULT '#ffffff',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_schedule_categories_name` (`name`),

  KEY `idx_schedule_categories_active` (`active`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- SCHEDULE EVENT RECURRENCES
-- =====================================================

CREATE TABLE IF NOT EXISTS `schedule_events_recurrences` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `schedule_category_id` INT DEFAULT NULL,
  `title` VARCHAR(160) NOT NULL,
  `description` MEDIUMTEXT DEFAULT NULL,
  `location` VARCHAR(160) DEFAULT NULL,
  `all_day` TINYINT(1) NOT NULL DEFAULT 0,
  `start_time` TIME DEFAULT NULL,
  `end_time` TIME DEFAULT NULL,

  `frequency` ENUM(
    'daily',
    'weekly',
    'monthly'
  ) NOT NULL,

  `interval_value` INT UNSIGNED NOT NULL DEFAULT 1,
  `period_start` DATE NOT NULL,
  `period_end` DATE NOT NULL,
  `allow_weekends` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by_user_id` INT NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  KEY `idx_schedule_events_recurrences_category` (
    `schedule_category_id`
  ),

  KEY `idx_schedule_events_recurrences_period` (
    `period_start`,
    `period_end`
  ),

  KEY `idx_schedule_events_recurrences_created_by_user` (
    `created_by_user_id`
  ),

  KEY `idx_schedule_events_recurrences_active` (`active`),

  CONSTRAINT `fk_schedule_events_recurrences_categories`
    FOREIGN KEY (`schedule_category_id`)
    REFERENCES `schedule_categories` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,

  CONSTRAINT `fk_schedule_events_recurrences_manager_main_users`
    FOREIGN KEY (`created_by_user_id`)
    REFERENCES `manager_main`.`users` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- SCHEDULE EVENTS
-- =====================================================

CREATE TABLE IF NOT EXISTS `schedule_events` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `schedule_category_id` INT DEFAULT NULL,
  `schedule_events_recurrence_id` INT DEFAULT NULL,
  `recurrence_sequence` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(160) NOT NULL,
  `description` MEDIUMTEXT DEFAULT NULL,
  `location` VARCHAR(160) DEFAULT NULL,
  `all_day` TINYINT(1) NOT NULL DEFAULT 0,
  `starts_at` DATETIME NOT NULL,
  `ends_at` DATETIME DEFAULT NULL,

  `status` ENUM(
    'scheduled',
    'completed',
    'canceled'
  ) NOT NULL DEFAULT 'scheduled',

  `created_by_user_id` INT NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_schedule_events_recurrence_sequence` (
    `schedule_events_recurrence_id`,
    `recurrence_sequence`
  ),

  KEY `idx_schedule_events_category` (`schedule_category_id`),
  KEY `idx_schedule_events_recurrence` (`schedule_events_recurrence_id`),
  KEY `idx_schedule_events_starts_at` (`starts_at`),
  KEY `idx_schedule_events_status` (`status`),
  KEY `idx_schedule_events_created_by_user` (`created_by_user_id`),

  CONSTRAINT `fk_schedule_events_categories`
    FOREIGN KEY (`schedule_category_id`)
    REFERENCES `schedule_categories` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,

  CONSTRAINT `fk_schedule_events_recurrences`
    FOREIGN KEY (`schedule_events_recurrence_id`)
    REFERENCES `schedule_events_recurrences` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,

  CONSTRAINT `fk_schedule_events_manager_main_users`
    FOREIGN KEY (`created_by_user_id`)
    REFERENCES `manager_main`.`users` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- SCHEDULE EVENT PARTICIPANTS
-- =====================================================

CREATE TABLE IF NOT EXISTS `schedule_events_participants` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `schedule_event_id` INT NOT NULL,
  `person_id` INT NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_schedule_events_participants_event_person` (
    `schedule_event_id`,
    `person_id`
  ),

  KEY `idx_schedule_events_participants_event_active` (
    `schedule_event_id`,
    `active`
  ),

  KEY `idx_schedule_events_participants_person` (`person_id`),

  CONSTRAINT `fk_schedule_events_participants_events`
    FOREIGN KEY (`schedule_event_id`)
    REFERENCES `schedule_events` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT `fk_schedule_events_participants_manager_main_people`
    FOREIGN KEY (`person_id`)
    REFERENCES `manager_main`.`people` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


INSERT INTO `schema_versions` (
  `version`,
  `description`,
  `created`,
  `modified`
)
VALUES (
  'schedule-001',
  'Schedule categories, events, recurrences and participants module',
  NOW(),
  NOW()
)
ON DUPLICATE KEY UPDATE
  `description` = 'Schedule categories, events, recurrences and participants module',
  `modified` = NOW();