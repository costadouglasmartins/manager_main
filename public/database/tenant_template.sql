CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL COMMENT 'Version number (e.g.: 1.0.0)',
  `description` varchar(255) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schema_versions_version_unique` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


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
        ON SourcePermission.`user_id` = 44
INNER JOIN `manager_main`.`permissions` AS PermissionCatalog
        ON PermissionCatalog.`id` = SourcePermission.`permission_id`
       AND PermissionCatalog.`system` = 'manager'
WHERE CAST(Tenant.`database_number` AS CHAR) = SUBSTRING(DATABASE(), CHAR_LENGTH('manager_') + 1)
  AND TenantUser.`role` = 'owner';


CREATE TABLE IF NOT EXISTS `banks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `number` varchar(4) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `banks_number_unique` (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `agency` varchar(6) NOT NULL,
  `account` varchar(50) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bank_accounts_bank_id` (`bank_id`),
  CONSTRAINT `fk_bank_accounts_banks`
    FOREIGN KEY (`bank_id`)
    REFERENCES `banks` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `financial_operations_subcategories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_operations_subcategories_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `financial_operations_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_subcategory_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_operations_categories_name_unique` (`name`),
  KEY `idx_financial_operations_categories_subcategory_id` (`financial_operation_subcategory_id`),
  CONSTRAINT `fk_financial_operations_categories_subcategories`
    FOREIGN KEY (`financial_operation_subcategory_id`)
    REFERENCES `financial_operations_subcategories` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `financial_operations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_financial_operations_category_id` (`financial_operation_category_id`),
  CONSTRAINT `fk_financial_operations_categories`
    FOREIGN KEY (`financial_operation_category_id`)
    REFERENCES `financial_operations_categories` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;