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


-- Dumping database structure for manager_0001
DROP DATABASE IF EXISTS `manager_0001`;
CREATE DATABASE IF NOT EXISTS `manager_0001` /*!40100 DEFAULT CHARACTER SET utf8mb3 */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `manager_0001`;

-- Dumping structure for table manager_0001.bank_accounts
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pix_key_type` enum('CPF','CNPJ','EMAIL','PHONE','RANDOM') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pix_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_3` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_instructions_line_4` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_protest` tinyint(1) DEFAULT NULL,
  `bank_slip_protest_number_of_days` tinyint DEFAULT NULL,
  `bank_slip_wallet` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_species_title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_interest_type` enum('DAILY','MONTHLY') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_interest_amount_type` enum('PERCENTAGE','FIXED_AMOUNT') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_slip_interest` decimal(10,2) DEFAULT NULL,
  `bank_slip_late_fee_amount` decimal(10,2) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_bank_accounts_manager_main_banks_idx` (`bank_id`),
  CONSTRAINT `fk_bank_accounts_manager_main_banks` FOREIGN KEY (`bank_id`) REFERENCES `manager_main`.`banks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.bank_accounts: ~0 rows (approximately)

-- Dumping structure for table manager_0001.bank_files
CREATE TABLE IF NOT EXISTS `bank_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_account_id` int DEFAULT NULL,
  `file_type` enum('REMITTANCE','RETURN_FILE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_files_path` (`file_path`),
  KEY `idx_bank_files_bank_account_id` (`bank_account_id`),
  KEY `idx_bank_files_file_type` (`file_type`),
  CONSTRAINT `fk_bank_files_bank_accounts` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.bank_files: ~0 rows (approximately)

-- Dumping structure for table manager_0001.bank_slips
CREATE TABLE IF NOT EXISTS `bank_slips` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_payment_id` int DEFAULT NULL,
  `bank_account_id` int NOT NULL,
  `bank_file_id` int DEFAULT NULL,
  `status` enum('DRAFT','READY','REMITTANCE_GENERATED','SENT','REGISTERED','REJECTED','PAID','CANCELED','WRITTEN_OFF') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `document_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `our_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `our_number_digit` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portfolio` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_document_type` enum('CPF','CNPJ') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_document` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_street` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_neighborhood` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payer_city` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_state` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_zip_code` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `protest_code` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `protest_days` tinyint unsigned DEFAULT NULL,
  `write_off_code` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `write_off_days` tinyint unsigned DEFAULT NULL,
  `last_occurrence_code` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_occurrence_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_slips_financial_operation_payment` (`financial_operation_payment_id`),
  UNIQUE KEY `uq_bank_slips_account_our_number` (`bank_account_id`,`our_number`),
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

-- Dumping data for table manager_0001.bank_slips: ~0 rows (approximately)

-- Dumping structure for table manager_0001.crm_activities
CREATE TABLE IF NOT EXISTS `crm_activities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_person_id` int NOT NULL,
  `person_id` int NOT NULL,
  `activity_type_id` int NOT NULL,
  `activity_status_id` int NOT NULL,
  `title` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `scheduled_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `responsible_user_id` int DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` int DEFAULT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_crm_activities_tenant_person` (`tenant_person_id`),
  KEY `idx_crm_activities_person` (`person_id`),
  KEY `idx_crm_activities_type` (`activity_type_id`),
  KEY `idx_crm_activities_status` (`activity_status_id`),
  KEY `idx_crm_activities_responsible` (`responsible_user_id`),
  KEY `idx_crm_activities_schedule` (`active`,`scheduled_at`),
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

-- Dumping data for table manager_0001.crm_activities: ~0 rows (approximately)

-- Dumping structure for table manager_0001.crm_activity_statuses
CREATE TABLE IF NOT EXISTS `crm_activity_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bootstrap_class` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text-bg-secondary',
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_final` tinyint(1) NOT NULL DEFAULT '0',
  `created_by_user_id` int DEFAULT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_activity_statuses_slug` (`slug`),
  KEY `idx_crm_activity_statuses_active` (`active`,`sort_order`),
  KEY `idx_crm_activity_statuses_final` (`is_final`),
  KEY `idx_crm_activity_statuses_created_by` (`created_by_user_id`),
  KEY `idx_crm_activity_statuses_updated_by` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_activity_statuses_manager_main_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_activity_statuses_manager_main_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.crm_activity_statuses: ~5 rows (approximately)
INSERT INTO `crm_activity_statuses` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `sort_order`, `active`, `is_final`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`) VALUES
	(1, 'Pendente', 'pending', 'Atividade criada e ainda não executada.', 'text-bg-warning', 10, 1, 0, 2, 2, '2026-07-28 18:27:39', '2026-07-28 18:52:13'),
	(2, 'Em andamento', 'in_progress', 'Atividade iniciada e ainda em execução.', 'text-bg-primary', 20, 1, 0, 2, 2, '2026-07-28 18:27:39', '2026-07-28 18:52:13'),
	(3, 'Concluída', 'completed', 'Atividade finalizada com sucesso.', 'text-bg-success', 30, 1, 1, 2, 2, '2026-07-28 18:27:39', '2026-07-28 18:52:13'),
	(4, 'Reagendada', 'rescheduled', 'Atividade adiada para nova data.', 'text-bg-info', 40, 1, 0, 2, 2, '2026-07-28 18:27:39', '2026-07-28 18:52:13'),
	(5, 'Cancelada', 'canceled', 'Atividade encerrada sem execução.', 'text-bg-secondary', 50, 1, 1, 2, 2, '2026-07-28 18:27:39', '2026-07-28 18:52:13');

-- Dumping structure for table manager_0001.crm_activity_types
CREATE TABLE IF NOT EXISTS `crm_activity_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bootstrap_class` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text-bg-secondary',
  `icon` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bi-sticky',
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` int DEFAULT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_activity_types_slug` (`slug`),
  KEY `idx_crm_activity_types_active` (`active`,`sort_order`),
  KEY `idx_crm_activity_types_created_by` (`created_by_user_id`),
  KEY `idx_crm_activity_types_updated_by` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_activity_types_manager_main_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_activity_types_manager_main_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.crm_activity_types: ~8 rows (approximately)
INSERT INTO `crm_activity_types` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `icon`, `sort_order`, `active`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`) VALUES
	(1, 'Ligação', 'phone_call', 'Contato por telefone com o lead, prospect ou cliente.', 'text-bg-primary', 'bi-telephone', 10, 1, 2, 2, '2026-07-28 18:27:39', '2026-07-28 18:52:13'),
	(2, 'WhatsApp', 'whatsapp', 'Contato realizado por WhatsApp.', 'text-bg-success', 'bi-whatsapp', 20, 1, 2, 2, '2026-07-28 18:27:39', '2026-07-28 18:52:13'),
	(3, 'E-mail', 'email', 'Envio ou resposta por e-mail.', 'text-bg-info', 'bi-envelope', 30, 1, 2, 2, '2026-07-28 18:27:39', '2026-07-28 18:52:13'),
	(4, 'Reunião', 'meeting', 'Reunião presencial ou por videoconferência.', 'text-bg-primary', 'bi-people', 40, 1, 2, 2, '2026-07-28 18:27:39', '2026-07-28 18:52:13'),
	(5, 'Anotação', 'note', 'Registro interno de informação comercial.', 'text-bg-secondary', 'bi-journal-text', 50, 1, 2, 2, '2026-07-28 18:27:39', '2026-07-28 18:52:13'),
	(6, 'Tarefa', 'task', 'Ação operacional vinculada ao contato CRM.', 'text-bg-warning', 'bi-list-check', 60, 1, 2, 2, '2026-07-28 18:27:39', '2026-07-28 18:52:13'),
	(7, 'Visita', 'visit', 'Visita presencial ao cliente ou prospect.', 'text-bg-dark', 'bi-geo-alt', 70, 1, 2, 2, '2026-07-28 18:27:39', '2026-07-28 18:52:13'),
	(8, 'Follow-up', 'follow_up', 'Retorno programado para continuidade da negociação.', 'text-bg-danger', 'bi-arrow-repeat', 80, 1, 2, 2, '2026-07-28 18:27:39', '2026-07-28 18:52:13');

-- Dumping structure for table manager_0001.crm_inactivity_reasons
CREATE TABLE IF NOT EXISTS `crm_inactivity_reasons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bootstrap_class` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text-bg-secondary',
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` int DEFAULT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_inactivity_reasons_slug` (`slug`),
  KEY `idx_crm_inactivity_reasons_active` (`active`,`sort_order`),
  KEY `fk_crm_inactivity_reasons_created_by_user` (`created_by_user_id`),
  KEY `fk_crm_inactivity_reasons_updated_by_user` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_inactivity_reasons_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_inactivity_reasons_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.crm_inactivity_reasons: ~3 rows (approximately)
INSERT INTO `crm_inactivity_reasons` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `sort_order`, `active`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`) VALUES
	(1, 'Sem relacionamento ativo', 'sem_relacionamento_ativo', 'Contato sem relação comercial ativa no momento.', 'text-bg-secondary', 10, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(2, 'Contato inválido', 'contato_invalido', 'Telefone, e-mail ou dados de contato não permitem continuidade.', 'text-bg-danger', 20, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(3, 'Projeto pausado', 'projeto_pausado', 'Cliente pausou a iniciativa ou projeto.', 'text-bg-warning', 30, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30');

-- Dumping structure for table manager_0001.crm_loss_reasons
CREATE TABLE IF NOT EXISTS `crm_loss_reasons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bootstrap_class` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text-bg-secondary',
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` int DEFAULT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_loss_reasons_slug` (`slug`),
  KEY `idx_crm_loss_reasons_active` (`active`,`sort_order`),
  KEY `fk_crm_loss_reasons_created_by_user` (`created_by_user_id`),
  KEY `fk_crm_loss_reasons_updated_by_user` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_loss_reasons_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_loss_reasons_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.crm_loss_reasons: ~5 rows (approximately)
INSERT INTO `crm_loss_reasons` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `sort_order`, `active`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`) VALUES
	(1, 'Preço', 'preco', 'Cliente considerou o preço incompatível.', 'text-bg-warning', 10, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(2, 'Sem orçamento', 'sem_orcamento', 'Cliente não possui orçamento no momento.', 'text-bg-secondary', 20, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(3, 'Prazo incompatível', 'prazo_incompativel', 'Prazo desejado não pôde ser atendido.', 'text-bg-info', 30, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(4, 'Concorrente', 'concorrente', 'Cliente escolheu uma solução concorrente.', 'text-bg-danger', 40, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(5, 'Sem retorno', 'sem_retorno', 'Cliente não retornou após tentativas de contato.', 'text-bg-dark', 50, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30');

-- Dumping structure for table manager_0001.crm_opportunities
CREATE TABLE IF NOT EXISTS `crm_opportunities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_person_id` int NOT NULL,
  `person_id` int NOT NULL,
  `crm_source_id` int DEFAULT NULL,
  `opportunity_type_id` int DEFAULT NULL,
  `pipeline_stage_id` int NOT NULL,
  `loss_reason_id` int DEFAULT NULL,
  `title` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `estimated_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `probability` int NOT NULL DEFAULT '0',
  `expected_close_date` date DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `responsible_user_id` int DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` int DEFAULT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_crm_opportunities_tenant_person` (`tenant_person_id`),
  KEY `idx_crm_opportunities_person` (`person_id`),
  KEY `idx_crm_opportunities_source` (`crm_source_id`),
  KEY `idx_crm_opportunities_type` (`opportunity_type_id`),
  KEY `idx_crm_opportunities_stage` (`pipeline_stage_id`),
  KEY `idx_crm_opportunities_loss_reason` (`loss_reason_id`),
  KEY `idx_crm_opportunities_expected` (`active`,`expected_close_date`),
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

-- Dumping data for table manager_0001.crm_opportunities: ~0 rows (approximately)

-- Dumping structure for table manager_0001.crm_opportunity_types
CREATE TABLE IF NOT EXISTS `crm_opportunity_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bootstrap_class` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text-bg-secondary',
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` int DEFAULT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_opportunity_types_slug` (`slug`),
  KEY `idx_crm_opportunity_types_active` (`active`,`sort_order`),
  KEY `fk_crm_opportunity_types_created_by_user` (`created_by_user_id`),
  KEY `fk_crm_opportunity_types_updated_by_user` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_opportunity_types_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_opportunity_types_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.crm_opportunity_types: ~4 rows (approximately)
INSERT INTO `crm_opportunity_types` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `sort_order`, `active`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`) VALUES
	(1, 'Venda nova', 'venda_nova', 'Primeira venda para o contato.', 'text-bg-primary', 10, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(2, 'Renovação', 'renovacao', 'Renovação de contrato ou compra recorrente.', 'text-bg-success', 20, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(3, 'Upsell', 'upsell', 'Ampliação de escopo, plano ou valor contratado.', 'text-bg-info', 30, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(4, 'Reativação', 'reativacao', 'Retomada de contato ou cliente inativo.', 'text-bg-warning', 40, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30');

-- Dumping structure for table manager_0001.crm_pipeline_stages
CREATE TABLE IF NOT EXISTS `crm_pipeline_stages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bootstrap_class` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text-bg-secondary',
  `default_probability` int NOT NULL DEFAULT '0',
  `is_won` tinyint(1) NOT NULL DEFAULT '0',
  `is_lost` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` int DEFAULT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_pipeline_stages_slug` (`slug`),
  KEY `idx_crm_pipeline_stages_active` (`active`,`sort_order`),
  KEY `idx_crm_pipeline_stages_result` (`is_won`,`is_lost`),
  KEY `fk_crm_pipeline_stages_created_by_user` (`created_by_user_id`),
  KEY `fk_crm_pipeline_stages_updated_by_user` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_pipeline_stages_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_pipeline_stages_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.crm_pipeline_stages: ~6 rows (approximately)
INSERT INTO `crm_pipeline_stages` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `default_probability`, `is_won`, `is_lost`, `sort_order`, `active`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`) VALUES
	(1, 'Novo', 'novo', 'Oportunidade recém-criada.', 'text-bg-primary', 10, 0, 0, 10, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(2, 'Qualificação', 'qualificacao', 'Necessidade, orçamento e prazo em validação.', 'text-bg-info', 25, 0, 0, 20, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(3, 'Proposta', 'proposta', 'Proposta comercial enviada.', 'text-bg-warning', 50, 0, 0, 30, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(4, 'Negociação', 'negociacao', 'Condições comerciais em negociação.', 'text-bg-dark', 75, 0, 0, 40, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(5, 'Ganha', 'ganha', 'Oportunidade convertida com sucesso.', 'text-bg-success', 100, 1, 0, 50, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(6, 'Perdida', 'perdida', 'Oportunidade encerrada sem conversão.', 'text-bg-danger', 0, 0, 1, 60, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30');

-- Dumping structure for table manager_0001.crm_sources
CREATE TABLE IF NOT EXISTS `crm_sources` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bootstrap_class` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text-bg-secondary',
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` int DEFAULT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_sources_slug` (`slug`),
  KEY `idx_crm_sources_active` (`active`,`sort_order`),
  KEY `fk_crm_sources_created_by_user` (`created_by_user_id`),
  KEY `fk_crm_sources_updated_by_user` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_sources_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_sources_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.crm_sources: ~8 rows (approximately)
INSERT INTO `crm_sources` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `sort_order`, `active`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`) VALUES
	(1, 'Indicação', 'indicacao', 'Contato indicado por cliente, parceiro ou relacionamento.', 'text-bg-success', 10, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(2, 'Site', 'site', 'Contato originado pelo site institucional ou landing page.', 'text-bg-primary', 20, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(3, 'WhatsApp', 'whatsapp', 'Contato iniciado por WhatsApp.', 'text-bg-success', 30, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(4, 'Instagram', 'instagram', 'Contato vindo de rede social Instagram.', 'text-bg-danger', 40, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(5, 'Google Ads', 'google_ads', 'Contato gerado por campanha paga no Google.', 'text-bg-warning', 50, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(6, 'Prospecção ativa', 'prospeccao_ativa', 'Contato gerado por abordagem comercial ativa.', 'text-bg-info', 60, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(7, 'Cliente antigo', 'cliente_antigo', 'Reativação ou nova demanda de cliente antigo.', 'text-bg-secondary', 70, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30'),
	(8, 'Evento', 'evento', 'Contato captado em evento, feira ou encontro comercial.', 'text-bg-dark', 80, 1, 2, 2, '2026-07-28 19:18:40', '2026-07-28 19:19:30');

-- Dumping structure for table manager_0001.crm_statuses
CREATE TABLE IF NOT EXISTS `crm_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bootstrap_class` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text-bg-secondary',
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` int DEFAULT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_statuses_slug` (`slug`),
  KEY `idx_crm_statuses_active` (`active`,`sort_order`),
  KEY `idx_crm_statuses_created_by` (`created_by_user_id`),
  KEY `idx_crm_statuses_updated_by` (`updated_by_user_id`),
  CONSTRAINT `fk_crm_statuses_manager_main_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_crm_statuses_manager_main_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.crm_statuses: ~8 rows (approximately)
INSERT INTO `crm_statuses` (`id`, `name`, `slug`, `description`, `bootstrap_class`, `sort_order`, `active`, `created_by_user_id`, `updated_by_user_id`, `created`, `modified`) VALUES
	(1, 'Lead', 'lead', 'Contato inicial recebido, ainda sem qualificacao comercial.', 'text-bg-primary', 10, 1, 2, 2, '2026-07-28 18:02:16', '2026-07-28 18:02:16'),
	(2, 'Prospect', 'prospect', 'Contato com potencial comercial identificado.', 'text-bg-info', 20, 1, 2, 2, '2026-07-28 18:02:16', '2026-07-28 18:02:16'),
	(3, 'Cliente', 'customer', 'Pessoa ou empresa que ja possui relacionamento comercial ativo.', 'text-bg-success', 60, 1, 2, 2, '2026-07-28 18:02:16', '2026-07-28 18:02:16'),
	(4, 'Inativo', 'inactive', 'Contato sem relacionamento comercial ativo no momento.', 'text-bg-secondary', 80, 1, 2, 2, '2026-07-28 18:02:16', '2026-07-28 18:02:16'),
	(5, 'Qualificado', 'qualified', 'Contato validado e pronto para abordagem comercial estruturada.', 'text-bg-info', 30, 1, 2, 2, '2026-07-28 18:02:16', '2026-07-28 18:02:16'),
	(6, 'Proposta enviada', 'proposal_sent', 'Proposta comercial enviada e aguardando retorno.', 'text-bg-warning', 40, 1, 2, 2, '2026-07-28 18:02:16', '2026-07-28 18:02:16'),
	(7, 'Negociacao', 'negotiation', 'Contato em negociacao ativa de condicoes comerciais.', 'text-bg-dark', 50, 1, 2, 2, '2026-07-28 18:02:16', '2026-07-28 18:02:16'),
	(8, 'Perdido', 'lost', 'Oportunidade encerrada sem conversao em cliente.', 'text-bg-danger', 70, 1, 2, 2, '2026-07-28 18:02:16', '2026-07-28 18:02:16');

-- Dumping structure for table manager_0001.financial_operations
CREATE TABLE IF NOT EXISTS `financial_operations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` int NOT NULL,
  `financial_operation_subcategory_id` int DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_financial_operations_categories_idx` (`financial_operation_category_id`),
  KEY `fk_financial_operations_subcategories_idx` (`financial_operation_subcategory_id`),
  CONSTRAINT `fk_financial_operations_categories` FOREIGN KEY (`financial_operation_category_id`) REFERENCES `financial_operations_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_financial_operations_subcategories` FOREIGN KEY (`financial_operation_subcategory_id`) REFERENCES `financial_operations_subcategories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.financial_operations: ~0 rows (approximately)

-- Dumping structure for table manager_0001.financial_operations_categories
CREATE TABLE IF NOT EXISTS `financial_operations_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_operations_categories_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.financial_operations_categories: ~0 rows (approximately)

-- Dumping structure for table manager_0001.financial_operations_payments
CREATE TABLE IF NOT EXISTS `financial_operations_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_id` int NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_financial_operations_payments_operations_idx` (`financial_operation_id`),
  CONSTRAINT `fk_financial_operations_payments_operations` FOREIGN KEY (`financial_operation_id`) REFERENCES `financial_operations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.financial_operations_payments: ~0 rows (approximately)

-- Dumping structure for table manager_0001.financial_operations_subcategories
CREATE TABLE IF NOT EXISTS `financial_operations_subcategories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_operations_subcategories_category_name_unique` (`financial_operation_category_id`,`name`),
  KEY `fk_financial_operations_subcategories_categories_idx` (`financial_operation_category_id`),
  CONSTRAINT `fk_financial_operations_subcategories_categories` FOREIGN KEY (`financial_operation_category_id`) REFERENCES `financial_operations_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.financial_operations_subcategories: ~0 rows (approximately)

-- Dumping structure for table manager_0001.products
CREATE TABLE IF NOT EXISTS `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_category_id` int DEFAULT NULL,
  `sku` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cost_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sale_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `stock_quantity` decimal(12,3) NOT NULL DEFAULT '0.000',
  `minimum_stock` decimal(12,3) NOT NULL DEFAULT '0.000',
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UN',
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

-- Dumping data for table manager_0001.products: ~0 rows (approximately)

-- Dumping structure for table manager_0001.product_attributes
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
  UNIQUE KEY `uq_product_attributes_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_attributes: ~20 rows (approximately)
INSERT INTO `product_attributes` (`id`, `code`, `name`, `data_type`, `measurement_type`, `active`, `created`, `modified`) VALUES
	(1, 'category_1_tonalidade', 'Tonalidade', 'OPTION', NULL, 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(2, 'category_1_acabamento', 'Acabamento', 'OPTION', NULL, 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(3, 'category_1_composicao', 'Composicao', 'TEXT', NULL, 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(4, 'category_1_largura_util', 'Largura util', 'DECIMAL', 'LENGTH', 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(5, 'category_1_gramatura', 'Gramatura', 'DECIMAL', 'WEIGHT', 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(6, 'category_1_impermeavel', 'Impermeavel', 'BOOLEAN', NULL, 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(7, 'category_1_observacao_de_resinagem', 'Observacao de resinagem', 'TEXT', NULL, 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(8, 'category_2_ambiente_indicado', 'Ambiente indicado', 'OPTION', NULL, 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(9, 'category_2_metodo_de_aplicacao', 'Metodo de aplicacao', 'OPTION', NULL, 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(10, 'category_2_lavavel', 'Lavavel', 'BOOLEAN', NULL, 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(11, 'category_2_repeticao_da_estampa', 'Repeticao da estampa', 'DECIMAL', 'LENGTH', 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(12, 'category_2_comprimento_do_rolo', 'Comprimento do rolo', 'DECIMAL', 'LENGTH', 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(13, 'category_2_instrucao_de_limpeza', 'Instrucao de limpeza', 'TEXT', NULL, 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(14, 'category_3_grau_de_uso', 'Grau de uso', 'OPTION', NULL, 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(15, 'category_3_granulometria', 'Granulometria', 'DECIMAL', 'LENGTH', 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(16, 'category_3_pureza', 'Pureza', 'DECIMAL', NULL, 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(17, 'category_3_requer_laudo', 'Requer laudo', 'BOOLEAN', NULL, 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(18, 'category_3_codigo_do_laudo', 'Codigo do laudo', 'TEXT', NULL, 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(19, 'category_3_validade', 'Validade', 'DATE', NULL, 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(20, 'category_3_embalagem', 'Embalagem', 'OPTION', NULL, 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39');

-- Dumping structure for table manager_0001.product_attribute_options
CREATE TABLE IF NOT EXISTS `product_attribute_options` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_attribute_id` int NOT NULL,
  `value` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_attribute_options_value` (`product_attribute_id`,`value`),
  CONSTRAINT `fk_product_attribute_options_attribute` FOREIGN KEY (`product_attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_attribute_options: ~14 rows (approximately)
INSERT INTO `product_attribute_options` (`id`, `product_attribute_id`, `value`, `label`, `sort_order`, `active`) VALUES
	(1, 1, 'preto_grafite', 'Preto grafite', 0, 1),
	(2, 1, 'chumbo', 'Chumbo', 1, 1),
	(3, 2, 'natural', 'Natural', 0, 1),
	(4, 2, 'resinado', 'Resinado', 1, 1),
	(5, 8, 'sala', 'Sala', 0, 1),
	(6, 8, 'quarto', 'Quarto', 1, 1),
	(7, 8, 'escritorio', 'Escritorio', 2, 1),
	(8, 9, 'autoadesivo', 'Autoadesivo', 0, 1),
	(9, 9, 'cola_branca', 'Cola branca', 1, 1),
	(10, 14, 'cosmetico', 'Cosmetico', 0, 1),
	(11, 14, 'industrial', 'Industrial', 1, 1),
	(12, 14, 'artesanal', 'Artesanal', 2, 1),
	(13, 20, 'saco_kraft', 'Saco kraft', 0, 1),
	(14, 20, 'balde_lacrado', 'Balde lacrado', 1, 1);

-- Dumping structure for table manager_0001.product_categories
CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_categories_name` (`name`),
  KEY `idx_products_categories_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_categories: ~3 rows (approximately)
INSERT INTO `product_categories` (`id`, `name`, `description`, `active`, `created`, `modified`) VALUES
	(1, 'Tecidos por metro - imagem escura', '<p>Categoria de exemplo para a primeira imagem: amostra escura com lettering discreto, vendida por metro linear.</p>', 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(2, 'Papeis de parede - imagem media', '<p>Categoria de exemplo para a segunda imagem: padrao textual cinza, vendido como rolo/unidade.</p>', 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(3, 'Insumos por peso - imagem clara', '<p>Categoria de exemplo para a terceira imagem: amostra clara, cadastrada como insumo vendido por peso.</p>', 1, '2026-07-22 23:52:39', '2026-07-22 23:52:39');

-- Dumping structure for table manager_0001.product_category_attributes
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
  KEY `fk_product_category_attributes_attribute` (`product_attribute_id`),
  KEY `fk_product_category_attributes_unit` (`product_unit_id`),
  CONSTRAINT `fk_product_category_attributes_attribute` FOREIGN KEY (`product_attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_category_attributes_category` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_category_attributes_unit` FOREIGN KEY (`product_unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_category_attributes: ~20 rows (approximately)
INSERT INTO `product_category_attributes` (`id`, `product_category_id`, `product_attribute_id`, `product_unit_id`, `required`, `filterable`, `show_on_list`, `variation_axis`, `variation_specific`, `sort_order`) VALUES
	(1, 1, 1, NULL, 1, 1, 1, 1, 1, 0),
	(2, 1, 2, NULL, 1, 1, 1, 1, 1, 1),
	(3, 1, 3, NULL, 1, 1, 1, 0, 0, 2),
	(4, 1, 4, 2, 1, 1, 0, 0, 0, 3),
	(5, 1, 5, 6, 1, 1, 0, 0, 0, 4),
	(6, 1, 6, NULL, 1, 1, 0, 0, 0, 5),
	(7, 1, 7, NULL, 0, 0, 0, 0, 0, 6),
	(8, 2, 8, NULL, 1, 1, 1, 0, 0, 0),
	(9, 2, 9, NULL, 1, 1, 1, 0, 0, 1),
	(10, 2, 10, NULL, 1, 1, 0, 0, 0, 2),
	(11, 2, 11, 2, 1, 1, 0, 0, 0, 3),
	(12, 2, 12, 2, 1, 0, 0, 0, 0, 4),
	(13, 2, 13, NULL, 0, 0, 0, 0, 0, 5),
	(14, 3, 14, NULL, 1, 1, 1, 0, 0, 0),
	(15, 3, 15, 2, 1, 1, 1, 0, 0, 1),
	(16, 3, 16, NULL, 1, 1, 0, 0, 0, 2),
	(17, 3, 17, NULL, 1, 1, 0, 0, 0, 3),
	(18, 3, 18, NULL, 0, 0, 0, 0, 0, 4),
	(19, 3, 19, NULL, 1, 0, 0, 0, 0, 5),
	(20, 3, 20, NULL, 1, 1, 0, 0, 0, 6);

-- Dumping structure for table manager_0001.product_category_attribute_conditions
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_category_attribute_conditions: ~6 rows (approximately)
INSERT INTO `product_category_attribute_conditions` (`id`, `product_category_id`, `target_product_attribute_id`, `source_product_attribute_id`, `operator`, `value_text`, `source_product_attribute_option_id`, `action`, `created`, `modified`) VALUES
	(1, 1, 7, 6, 'EQUALS', 'Sim', NULL, 'SHOW', '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(2, 1, 7, 6, 'EQUALS', 'Sim', NULL, 'REQUIRE', '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(3, 2, 13, 10, 'EQUALS', 'Sim', NULL, 'SHOW', '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(4, 2, 13, 10, 'EQUALS', 'Sim', NULL, 'REQUIRE', '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(5, 3, 18, 17, 'EQUALS', 'Sim', NULL, 'SHOW', '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(6, 3, 18, 17, 'EQUALS', 'Sim', NULL, 'REQUIRE', '2026-07-22 23:52:39', '2026-07-22 23:52:39');

-- Dumping structure for table manager_0001.product_category_templates
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
  KEY `idx_product_category_templates_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_category_templates: ~0 rows (approximately)

-- Dumping structure for table manager_0001.product_images
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumb_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `width` int unsigned DEFAULT NULL,
  `height` int unsigned DEFAULT NULL,
  `position` int NOT NULL DEFAULT '0',
  `is_main` tinyint(1) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_images_file_path` (`file_path`),
  KEY `idx_products_images_product` (`product_id`),
  KEY `idx_products_images_main` (`product_id`,`is_main`),
  CONSTRAINT `fk_products_images_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_images: ~0 rows (approximately)

-- Dumping structure for table manager_0001.product_import_batches
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
  CONSTRAINT `fk_product_import_batches_category` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_import_batches: ~0 rows (approximately)

-- Dumping structure for table manager_0001.product_import_errors
CREATE TABLE IF NOT EXISTS `product_import_errors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_import_batch_id` int NOT NULL,
  `row_number` int unsigned NOT NULL,
  `message` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `row_json` json DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_import_errors_batch` (`product_import_batch_id`),
  CONSTRAINT `fk_product_import_errors_batch` FOREIGN KEY (`product_import_batch_id`) REFERENCES `product_import_batches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_import_errors: ~0 rows (approximately)

-- Dumping structure for table manager_0001.product_items
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
  KEY `fk_product_items_sale_unit` (`sale_unit_id`),
  KEY `fk_product_items_dimension_unit` (`dimension_unit_id`),
  KEY `fk_product_items_weight_unit` (`weight_unit_id`),
  CONSTRAINT `fk_product_items_category` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`),
  CONSTRAINT `fk_product_items_dimension_unit` FOREIGN KEY (`dimension_unit_id`) REFERENCES `product_units` (`id`),
  CONSTRAINT `fk_product_items_sale_unit` FOREIGN KEY (`sale_unit_id`) REFERENCES `product_units` (`id`),
  CONSTRAINT `fk_product_items_weight_unit` FOREIGN KEY (`weight_unit_id`) REFERENCES `product_units` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_items: ~3 rows (approximately)
INSERT INTO `product_items` (`id`, `product_category_id`, `sku`, `barcode`, `name`, `description`, `cost_price`, `sale_price`, `sale_unit_id`, `sale_quantity_step`, `track_stock`, `stock_quantity`, `minimum_stock`, `length_value`, `width_value`, `height_value`, `dimension_unit_id`, `weight_value`, `weight_unit_id`, `active`, `created`, `modified`) VALUES
	(1, 1, 'IMG1-TECIDO-SCRIPT-ESC', '7890000010010', 'Tecido Black Script Premium - imagem 1', '<p>Produto de exemplo baseado na primeira imagem anexada: fundo escuro com escrita textual. Vendido por metro, com estoque controlado nas variacoes.</p>', 38.50, 64.90, 4, 0.500000, 0, 0.000000, 0.000000, 100.000000, 140.000000, 0.800000, 3, 280.000000, 6, 1, '2026-07-22 23:52:39', '2026-07-22 23:55:22'),
	(2, 2, 'IMG2-PAPEL-SCRIPT-CIN', '7890000010027', 'Papel de parede Script Cinza - imagem 2', '<p>Produto de exemplo baseado na segunda imagem anexada: textura textual cinza, vendido por rolo fechado.</p>', 92.00, 179.90, 1, 1.000000, 1, 18.000000, 4.000000, 1000.000000, 53.000000, 0.200000, 3, 1.200000, 7, 1, '2026-07-22 23:52:39', '2026-07-22 23:55:05'),
	(3, 3, 'IMG3-INSUMO-SCRIPT-CLA', '7890000010034', 'Pigmento mineral Script Claro - imagem 3', '<p>Produto de exemplo baseado na terceira imagem anexada: visual claro, vendido por kg, com controle de lote/laudo.</p>', 18.40, 36.90, 7, 0.100000, 1, 35.000000, 5.000000, 30.000000, 20.000000, 12.000000, 3, 1.000000, 7, 1, '2026-07-22 23:52:39', '2026-07-22 23:54:53');

-- Dumping structure for table manager_0001.product_item_attribute_values
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
  KEY `fk_product_item_values_attribute` (`product_attribute_id`),
  KEY `fk_product_item_values_option` (`product_attribute_option_id`),
  KEY `fk_product_item_values_unit` (`product_unit_id`),
  CONSTRAINT `fk_product_item_values_attribute` FOREIGN KEY (`product_attribute_id`) REFERENCES `product_attributes` (`id`),
  CONSTRAINT `fk_product_item_values_item` FOREIGN KEY (`product_item_id`) REFERENCES `product_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_item_values_option` FOREIGN KEY (`product_attribute_option_id`) REFERENCES `product_attribute_options` (`id`),
  CONSTRAINT `fk_product_item_values_unit` FOREIGN KEY (`product_unit_id`) REFERENCES `product_units` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_item_attribute_values: ~18 rows (approximately)
INSERT INTO `product_item_attribute_values` (`id`, `product_item_id`, `product_attribute_id`, `product_attribute_option_id`, `product_unit_id`, `value_text`, `value_integer`, `value_decimal`, `value_boolean`, `value_date`) VALUES
	(19, 3, 14, 10, NULL, NULL, NULL, NULL, NULL, NULL),
	(20, 3, 15, NULL, 2, NULL, NULL, 0.800000, NULL, NULL),
	(21, 3, 16, NULL, NULL, NULL, NULL, 98.500000, NULL, NULL),
	(22, 3, 17, NULL, NULL, NULL, NULL, NULL, 1, NULL),
	(23, 3, 18, NULL, NULL, 'LAU-CLARO-2026-001', NULL, NULL, NULL, NULL),
	(24, 3, 19, NULL, NULL, NULL, NULL, NULL, NULL, '2027-12-31'),
	(25, 3, 20, 13, NULL, NULL, NULL, NULL, NULL, NULL),
	(26, 2, 8, 7, NULL, NULL, NULL, NULL, NULL, NULL),
	(27, 2, 9, 8, NULL, NULL, NULL, NULL, NULL, NULL),
	(28, 2, 10, NULL, NULL, NULL, NULL, NULL, 1, NULL),
	(29, 2, 11, NULL, 3, NULL, NULL, 64.000000, NULL, NULL),
	(30, 2, 12, NULL, 4, NULL, NULL, 10.000000, NULL, NULL),
	(31, 2, 13, NULL, NULL, 'Limpar com pano macio levemente umido, sem solventes.', NULL, NULL, NULL, NULL),
	(32, 1, 3, NULL, NULL, '70% poliester / 30% algodao', NULL, NULL, NULL, NULL),
	(33, 1, 4, NULL, 3, NULL, NULL, 140.000000, NULL, NULL),
	(34, 1, 5, NULL, 6, NULL, NULL, 280.000000, NULL, NULL),
	(35, 1, 6, NULL, NULL, NULL, NULL, NULL, 1, NULL),
	(36, 1, 7, NULL, NULL, 'Resina leve para uso decorativo interno.', NULL, NULL, NULL, NULL);

-- Dumping structure for table manager_0001.product_item_images
CREATE TABLE IF NOT EXISTS `product_item_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_item_id` int NOT NULL,
  `product_item_variation_id` int DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_main` tinyint(1) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_product_item_images_item` (`product_item_id`),
  KEY `idx_product_item_images_variation` (`product_item_variation_id`),
  CONSTRAINT `fk_product_item_images_item` FOREIGN KEY (`product_item_id`) REFERENCES `product_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_item_images_variation` FOREIGN KEY (`product_item_variation_id`) REFERENCES `product_item_variations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_item_images: ~2 rows (approximately)
INSERT INTO `product_item_images` (`id`, `product_item_id`, `product_item_variation_id`, `file_path`, `original_name`, `mime_type`, `file_size`, `sort_order`, `is_main`, `created`) VALUES
	(1, 3, NULL, 'tenant_files/manager_0001/products/3/product_3_ee93138b12913802.png', 'white_240x400.png', 'image/png', 15955, 0, 1, '2026-07-22 23:54:53'),
	(2, 2, NULL, 'tenant_files/manager_0001/products/2/product_2_b755696bbb2c3e32.png', 'grey_240x400.png', 'image/png', 15577, 0, 1, '2026-07-22 23:55:05'),
	(3, 1, NULL, 'tenant_files/manager_0001/products/1/product_1_1cedbbfee94e987e.png', 'black_240x400.png', 'image/png', 15361, 0, 1, '2026-07-22 23:55:22');

-- Dumping structure for table manager_0001.product_item_price_rules
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
  CONSTRAINT `chk_product_item_price_rules_range` CHECK (((`maximum_quantity` is null) or (`maximum_quantity` >= `minimum_quantity`)))
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_item_price_rules: ~6 rows (approximately)
INSERT INTO `product_item_price_rules` (`id`, `product_item_id`, `product_item_variation_id`, `product_unit_id`, `minimum_quantity`, `maximum_quantity`, `sale_price`, `available`, `active`, `created`, `modified`) VALUES
	(7, 3, NULL, 7, 5.000000, 19.990000, 31.90, 1, 1, '2026-07-22 23:54:53', '2026-07-22 23:54:53'),
	(8, 3, NULL, 7, 20.000000, NULL, 28.90, 1, 1, '2026-07-22 23:54:53', '2026-07-22 23:54:53'),
	(9, 2, NULL, 1, 6.000000, 11.000000, 164.90, 1, 1, '2026-07-22 23:55:05', '2026-07-22 23:55:05'),
	(10, 2, NULL, 1, 12.000000, NULL, 149.90, 1, 1, '2026-07-22 23:55:05', '2026-07-22 23:55:05'),
	(11, 1, NULL, 4, 10.000000, 49.990000, 59.90, 1, 1, '2026-07-22 23:55:22', '2026-07-22 23:55:22'),
	(12, 1, NULL, 4, 50.000000, NULL, 54.90, 1, 1, '2026-07-22 23:55:22', '2026-07-22 23:55:22');

-- Dumping structure for table manager_0001.product_item_variations
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_item_variations: ~4 rows (approximately)
INSERT INTO `product_item_variations` (`id`, `product_item_id`, `sku`, `barcode`, `name`, `combination_key`, `cost_price`, `sale_price`, `track_stock`, `stock_quantity`, `minimum_stock`, `active`, `created`, `modified`) VALUES
	(1, 1, 'IMG1-TEC-PRE-NAT', NULL, 'Preto grafite / Natural', 'fa20770d6572ed40c862affc90e5fc1709e2c781118961833249e6b4139e66d0', 38.50, 64.90, 1, 32.000000, 6.000000, 1, '2026-07-22 23:52:39', '2026-07-22 23:55:22'),
	(2, 1, 'IMG1-TEC-PRE-RES', NULL, 'Preto grafite / Resinado', 'c06880e29b3dc1a3b3c8f959764c224ae7246c38e82d1ea11cc791a4e8427d3f', 38.50, 69.90, 1, 18.000000, 4.000000, 1, '2026-07-22 23:52:39', '2026-07-22 23:55:22'),
	(3, 1, 'IMG1-TEC-CHU-NAT', NULL, 'Chumbo / Natural', 'a72e7d22234c44c8719c8f1418f043dd0c7b8c131ab3d77494365221ca2cf7ad', 38.50, 62.90, 1, 24.000000, 5.000000, 1, '2026-07-22 23:52:39', '2026-07-22 23:55:22'),
	(4, 1, 'IMG1-TEC-CHU-RES', NULL, 'Chumbo / Resinado', 'c4b57c6638d874267aa886c4760ea4dd815cb3304e58694042ce50dec032dc37', 38.50, 67.90, 1, 16.000000, 4.000000, 1, '2026-07-22 23:52:39', '2026-07-22 23:55:22');

-- Dumping structure for table manager_0001.product_item_variation_attribute_values
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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_item_variation_attribute_values: ~8 rows (approximately)
INSERT INTO `product_item_variation_attribute_values` (`id`, `product_item_variation_id`, `product_attribute_id`, `product_attribute_option_id`, `product_unit_id`, `value_text`, `value_integer`, `value_decimal`, `value_boolean`, `value_date`) VALUES
	(9, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
	(10, 1, 2, 3, NULL, NULL, NULL, NULL, NULL, NULL),
	(11, 2, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
	(12, 2, 2, 4, NULL, NULL, NULL, NULL, NULL, NULL),
	(13, 3, 1, 2, NULL, NULL, NULL, NULL, NULL, NULL),
	(14, 3, 2, 3, NULL, NULL, NULL, NULL, NULL, NULL),
	(15, 4, 1, 2, NULL, NULL, NULL, NULL, NULL, NULL),
	(16, 4, 2, 4, NULL, NULL, NULL, NULL, NULL, NULL);

-- Dumping structure for table manager_0001.product_stocks_movements
CREATE TABLE IF NOT EXISTS `product_stocks_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `movement_type` enum('IN','OUT','ADJUSTMENT') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `previous_quantity` decimal(12,3) NOT NULL,
  `new_quantity` decimal(12,3) NOT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `unit_price` decimal(12,2) DEFAULT NULL,
  `reference` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observations` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `movement_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_stocks_movements_product` (`product_id`),
  KEY `idx_products_stocks_movements_type` (`movement_type`),
  KEY `idx_products_stocks_movements_date` (`movement_date`),
  CONSTRAINT `fk_products_stocks_movements_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_stocks_movements: ~0 rows (approximately)

-- Dumping structure for table manager_0001.product_stock_movements
CREATE TABLE IF NOT EXISTS `product_stock_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_item_id` int NOT NULL,
  `product_item_variation_id` int DEFAULT NULL,
  `movement_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  CONSTRAINT `fk_product_stock_movements_item` FOREIGN KEY (`product_item_id`) REFERENCES `product_items` (`id`),
  CONSTRAINT `fk_product_stock_movements_variation` FOREIGN KEY (`product_item_variation_id`) REFERENCES `product_item_variations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_stock_movements: ~6 rows (approximately)
INSERT INTO `product_stock_movements` (`id`, `product_item_id`, `product_item_variation_id`, `movement_type`, `quantity`, `previous_quantity`, `new_quantity`, `reference`, `notes`, `movement_date`, `created`) VALUES
	(3, 1, 1, 'IN', 32.000000, 0.000000, 32.000000, 'Estoque inicial da variação', NULL, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(4, 1, 2, 'IN', 18.000000, 0.000000, 18.000000, 'Estoque inicial da variação', NULL, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(5, 1, 3, 'IN', 24.000000, 0.000000, 24.000000, 'Estoque inicial da variação', NULL, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(6, 1, 4, 'IN', 16.000000, 0.000000, 16.000000, 'Estoque inicial da variação', NULL, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(7, 2, NULL, 'IN', 18.000000, 0.000000, 18.000000, 'Estoque inicial', NULL, '2026-07-22 23:52:39', '2026-07-22 23:52:39'),
	(8, 3, NULL, 'IN', 35.000000, 0.000000, 35.000000, 'Estoque inicial', NULL, '2026-07-22 23:52:39', '2026-07-22 23:52:39');

-- Dumping structure for table manager_0001.product_units
CREATE TABLE IF NOT EXISTS `product_units` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `measurement_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `factor_to_base` decimal(20,10) NOT NULL DEFAULT '1.0000000000',
  `decimal_places` tinyint NOT NULL DEFAULT '3',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_units_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.product_units: ~7 rows (approximately)
INSERT INTO `product_units` (`id`, `code`, `name`, `symbol`, `measurement_type`, `factor_to_base`, `decimal_places`, `active`, `created`, `modified`) VALUES
	(1, 'un', 'Unidade', 'un', 'UNIT', 1.0000000000, 0, 1, '2026-07-21 23:07:35', NULL),
	(2, 'mm', 'Milímetro', 'mm', 'LENGTH', 0.0010000000, 3, 1, '2026-07-21 23:07:35', NULL),
	(3, 'cm', 'Centímetro', 'cm', 'LENGTH', 0.0100000000, 2, 1, '2026-07-21 23:07:35', NULL),
	(4, 'm', 'Metro', 'm', 'LENGTH', 1.0000000000, 3, 1, '2026-07-21 23:07:35', NULL),
	(5, 'km', 'Quilômetro', 'km', 'LENGTH', 1000.0000000000, 3, 1, '2026-07-21 23:07:35', NULL),
	(6, 'g', 'Grama', 'g', 'WEIGHT', 1.0000000000, 0, 1, '2026-07-21 23:07:35', NULL),
	(7, 'kg', 'Quilograma', 'kg', 'WEIGHT', 1000.0000000000, 3, 1, '2026-07-21 23:07:35', NULL);

-- Dumping structure for table manager_0001.schedule_categories
CREATE TABLE IF NOT EXISTS `schedule_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `background_color` char(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#0d6efd',
  `description_color` char(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#ffffff',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schedule_categories_name` (`name`),
  KEY `idx_schedule_categories_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.schedule_categories: ~0 rows (approximately)

-- Dumping structure for table manager_0001.schedule_events
CREATE TABLE IF NOT EXISTS `schedule_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `schedule_category_id` int DEFAULT NULL,
  `schedule_events_recurrence_id` int DEFAULT NULL,
  `recurrence_sequence` int unsigned DEFAULT NULL,
  `title` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `all_day` tinyint(1) NOT NULL DEFAULT '0',
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `status` enum('scheduled','completed','canceled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
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
  CONSTRAINT `fk_schedule_events_recurrences` FOREIGN KEY (`schedule_events_recurrence_id`) REFERENCES `schedule_events_recurrences` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.schedule_events: ~0 rows (approximately)

-- Dumping structure for table manager_0001.schedule_events_participants
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

-- Dumping data for table manager_0001.schedule_events_participants: ~0 rows (approximately)

-- Dumping structure for table manager_0001.schedule_events_recurrences
CREATE TABLE IF NOT EXISTS `schedule_events_recurrences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `schedule_category_id` int DEFAULT NULL,
  `title` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `all_day` tinyint(1) NOT NULL DEFAULT '0',
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `frequency` enum('daily','weekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  CONSTRAINT `fk_schedule_events_recurrences_manager_main_users` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.schedule_events_recurrences: ~0 rows (approximately)

-- Dumping structure for table manager_0001.schema_versions
CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Version number (e.g.: 1.5.0)',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schema_versions_version_unique` (`version`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.schema_versions: ~5 rows (approximately)
INSERT INTO `schema_versions` (`id`, `version`, `description`, `created`, `modified`) VALUES
	(1, '0.0.1', 'Initial tenant schema', '2026-06-01 22:34:47', '2026-07-22 17:18:51'),
	(2, '0.0.2', 'Store products, images and stock module', '2026-06-01 22:34:48', '2026-07-22 17:18:58'),
	(3, '0.0.3', 'Tasks categories, assignments, executions and comments module', '2026-07-03 12:00:56', '2026-07-22 17:19:11'),
	(4, '0.0.4', 'Product variations and conditional product attributes', '2026-07-22 17:02:29', '2026-07-22 17:19:21'),
	(5, '0.0.5', 'Advanced product catalog, generated variations, pricing, imports and variation inventory', '2026-07-22 18:13:01', '2026-07-22 18:32:27');

-- Dumping structure for table manager_0001.tasks
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_category_id` int DEFAULT NULL,
  `title` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `third_party_person_id` int DEFAULT NULL,
  `third_party_name` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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

-- Dumping data for table manager_0001.tasks: ~0 rows (approximately)

-- Dumping structure for table manager_0001.task_assignees
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

-- Dumping data for table manager_0001.task_assignees: ~0 rows (approximately)

-- Dumping structure for table manager_0001.task_categories
CREATE TABLE IF NOT EXISTS `task_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_categories_name` (`name`),
  KEY `idx_task_categories_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.task_categories: ~0 rows (approximately)

-- Dumping structure for table manager_0001.task_comments
CREATE TABLE IF NOT EXISTS `task_comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_comments_task_created` (`task_id`,`created`),
  KEY `idx_task_comments_user` (`user_id`),
  CONSTRAINT `fk_task_comments_manager_main_users` FOREIGN KEY (`user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_task_comments_tasks` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.task_comments: ~0 rows (approximately)

-- Dumping structure for table manager_0001.task_executions
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
  CONSTRAINT `fk_task_executions_task_assignees` FOREIGN KEY (`task_assignee_id`) REFERENCES `task_assignees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table manager_0001.task_executions: ~0 rows (approximately)

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
