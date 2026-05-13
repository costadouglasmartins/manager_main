-- --------------------------------------------------------
-- Schema template for tenant databases
-- This SQL is executed automatically when provisioning
-- a new tenant (manager_0001, manager_0002, ...).
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL COMMENT 'Version number (e.g.: 1.0.0)',
  `description` varchar(255) DEFAULT NULL,
  `applied_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

INSERT INTO `schema_versions` (`version`, `description`, `applied_at`)
VALUES ('1.0.0', 'Initial tenant schema', NOW());

-- --------------------------------------------------------
-- Seed central manager permissions for the new tenant owner.
-- User, tenant and permission data lives in manager_main.
-- The source profile is user 44 and only manager-system permissions are copied.
-- --------------------------------------------------------

INSERT IGNORE INTO `manager_main`.`user_permissions` (`user_id`, `permission_id`, `created`, `modified`)
SELECT TenantUser.`user_id`,
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
WHERE Tenant.`database_number` = SUBSTRING(DATABASE(), CHAR_LENGTH('manager_') + 1)
  AND TenantUser.`role` = 'owner';

-- --------------------------------------------------------
-- Add tenant business tables below
-- (products, orders, inventory, finance, etc.)
-- --------------------------------------------------------

-- Dumping structure for table manager_0001.banks
CREATE TABLE IF NOT EXISTS `banks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '0',
  `number` varchar(4) NOT NULL DEFAULT '0',
  `active` tinyint NOT NULL DEFAULT (0),
  `created` datetime NOT NULL DEFAULT (0),
  `modified` datetime NOT NULL DEFAULT (0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0001.banks: ~0 rows (approximately)

-- Dumping structure for table manager_0001.bank_accounts
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `agency` varchar(6) NOT NULL,
  `account` varchar(50) NOT NULL,
  `active` tinyint(1) NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`,`bank_id`),
  KEY `fk_bank_accounts_banks_idx` (`bank_id`),
  CONSTRAINT `fk_bank_accounts_banks` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0001.bank_accounts: ~0 rows (approximately)

-- Dumping structure for table manager_0001.schema_versions
CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL COMMENT 'Version number (e.g.: 1.0.0)',
  `description` varchar(255) DEFAULT NULL,
  `applied_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0001.schema_versions: ~0 rows (approximately)
INSERT INTO `schema_versions` (`id`, `version`, `description`, `applied_at`) VALUES
	(2, '1.0.0', 'Initial tenant schema', NOW());

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;