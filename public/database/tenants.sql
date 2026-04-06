-- --------------------------------------------------------
-- Multi-tenant: tenants table + tenant_id column on users
-- Execute this script on the manager_main database
-- --------------------------------------------------------

-- 1. Tenants table
CREATE TABLE IF NOT EXISTS `tenants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Tenant name',
  `fantasy` varchar(255) DEFAULT NULL COMMENT 'Trade name',
  `document` varchar(20) DEFAULT NULL COMMENT 'CPF or CNPJ',
  `database_number` varchar(10) NOT NULL COMMENT 'Formatted database number (0001, 0002...)',
  `status` enum('active','suspended','pending') NOT NULL DEFAULT 'pending' COMMENT 'Tenant status',
  `observations` text DEFAULT NULL COMMENT 'General observations',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenants_database_number` (`database_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- 2. Add tenant_id column to users table (automatic association)
--    Each user belongs to exactly one tenant.
--    Root users (no tenant) may have tenant_id = NULL.
ALTER TABLE `users`
  ADD COLUMN `tenant_id` int DEFAULT NULL COMMENT 'Associated tenant' AFTER `person_id`,
  ADD KEY `fk_users_tenants1_idx` (`tenant_id`),
  ADD CONSTRAINT `fk_users_tenants1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);
