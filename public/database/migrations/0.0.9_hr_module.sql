SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Apply to each existing manager_NNNN database. Idempotent; never deletes data.

CREATE TABLE IF NOT EXISTS `hr_departments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_departments_name` (`name`),
  KEY `idx_hr_departments_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_positions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_positions_name` (`name`),
  KEY `idx_hr_positions_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_employees` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `person_id` INT NOT NULL,
  `user_id` INT DEFAULT NULL,
  `hr_department_id` INT DEFAULT NULL,
  `hr_position_id` INT DEFAULT NULL,
  `manager_hr_employee_id` INT DEFAULT NULL,
  `registration_number` VARCHAR(40) DEFAULT NULL,
  `admission_date` DATE DEFAULT NULL,
  `termination_date` DATE DEFAULT NULL,
  `employment_type` VARCHAR(40) NOT NULL DEFAULT 'clt',
  `status` VARCHAR(40) NOT NULL DEFAULT 'active',
  `salary` DECIMAL(12,2) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_employees_person` (`person_id`),
  KEY `idx_hr_employees_user` (`user_id`),
  KEY `idx_hr_employees_department` (`hr_department_id`),
  KEY `idx_hr_employees_position` (`hr_position_id`),
  KEY `idx_hr_employees_manager` (`manager_hr_employee_id`),
  KEY `idx_hr_employees_status` (`status`, `active`),
  CONSTRAINT `fk_hr_employees_hr_departments` FOREIGN KEY (`hr_department_id`) REFERENCES `hr_departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_hr_employees_hr_positions` FOREIGN KEY (`hr_position_id`) REFERENCES `hr_positions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_hr_employees_hr_employees_manager` FOREIGN KEY (`manager_hr_employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_hr_employees_manager_main_people` FOREIGN KEY (`person_id`) REFERENCES `manager_main`.`people` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_hr_employees_manager_main_users` FOREIGN KEY (`user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `schema_versions` (`version`, `description`, `created`, `modified`)
VALUES
  ('0.0.9', 'HR Module - Departments, Positions and Employees', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `description` = VALUES(`description`),
  `modified` = NOW();

INSERT INTO `hr_departments` (`id`, `name`, `description`, `active`, `created`, `modified`)
VALUES
  (1, 'Administrativo', 'Rotinas administrativas e apoio à gestão.', 1, NOW(), NOW()),
  (2, 'Financeiro', 'Contas a pagar, contas a receber e controle financeiro.', 1, NOW(), NOW()),
  (3, 'Comercial', 'Vendas, relacionamento e prospecção de clientes.', 1, NOW(), NOW()),
  (4, 'Operacional', 'Execução das atividades operacionais da empresa.', 1, NOW(), NOW()),
  (5, 'Recursos Humanos', 'Gestão de pessoas, admissões e benefícios.', 1, NOW(), NOW()),
  (6, 'Tecnologia', 'Sistemas, infraestrutura e suporte técnico.', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

INSERT INTO `hr_positions` (`id`, `name`, `description`, `active`, `created`, `modified`)
VALUES
  (1, 'Diretor', 'Responsável pela direção estratégica da empresa.', 1, NOW(), NOW()),
  (2, 'Gerente', 'Responsável pela gestão de uma área.', 1, NOW(), NOW()),
  (3, 'Coordenador', 'Coordena equipes e processos.', 1, NOW(), NOW()),
  (4, 'Analista', 'Executa atividades técnicas e analíticas.', 1, NOW(), NOW()),
  (5, 'Assistente', 'Apoia as rotinas da área.', 1, NOW(), NOW()),
  (6, 'Auxiliar', 'Executa tarefas de apoio operacional.', 1, NOW(), NOW()),
  (7, 'Estagiário', 'Profissional em período de estágio.', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;
