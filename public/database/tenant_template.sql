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
