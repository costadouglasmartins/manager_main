-- --------------------------------------------------------
-- Schema template for tenant databases
-- This SQL is executed automatically when provisioning
-- a new tenant (manager_0001, manager_0002, ...).
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `people` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `fantasy` varchar(255) NOT NULL,
  `type` enum('individual','company') NOT NULL,
  `document` varchar(14) NOT NULL,
  `active` tinyint(1) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_UNIQUE` (`document`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `addresses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `zip_code` varchar(45) NOT NULL,
  `street` varchar(255) NOT NULL,
  `number` varchar(10) NOT NULL,
  `observations` varchar(255) DEFAULT NULL,
  `person_id` int NOT NULL,
  `neighborhood_id` int DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_addresses_people1_idx` (`person_id`),
  CONSTRAINT `fk_addresses_people1` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `emails` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `observations` varchar(500) DEFAULT NULL,
  `person_id` int DEFAULT NULL,
  `is_login` tinyint(1) NOT NULL DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_emails_people1_idx` (`person_id`),
  CONSTRAINT `fk_emails_people1` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `phone_numbers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `phone_number` varchar(255) NOT NULL,
  `observations` varchar(500) DEFAULT NULL,
  `person_id` int DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_phone_numbers_people1_idx` (`person_id`),
  CONSTRAINT `fk_phone_numbers_people1` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb3;

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
-- Add tenant business tables below
-- (products, orders, inventory, finance, etc.)
-- --------------------------------------------------------
