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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `schema_versions` (`version`, `description`, `applied_at`)
VALUES ('1.0.0', 'Initial tenant schema', NOW());

-- --------------------------------------------------------
-- Add tenant business tables below
-- (products, orders, inventory, finance, etc.)
-- --------------------------------------------------------
