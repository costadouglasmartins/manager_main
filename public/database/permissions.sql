-- manager_main permissions layer (USER-ONLY)
-- Creates: permissions, user_permissions
-- Seeds: abilities for company/person/user modules + permission.manage
--
-- Notes:
-- - This version does NOT use user_type_permissions (roles/groups).
-- - user_permissions does not enforce a FK to users(id) to avoid signed/unsigned mismatches.
-- - Grant permissions using INSERT into user_permissions.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS permissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ability VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_ability (ability)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permissions (
  user_id INT NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, permission_id),
  CONSTRAINT fk_up_permission
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ability catalog
INSERT IGNORE INTO permissions (ability, description) VALUES
  ('permission.manage',    'Gerenciar permissões do sistema'),

  ('company.index',        'Listar empresas'),
  ('company.create',       'Exibir formulário de criação de empresa'),
  ('company.store',        'Criar empresa'),
  ('company.edit',         'Exibir formulário de edição de empresa'),
  ('company.update',       'Atualizar empresa'),
  ('company.informations', 'Visualizar detalhes da empresa'),

  ('person.index',         'Listar pessoas'),
  ('person.create',        'Exibir formulário de criação de pessoa'),
  ('person.store',         'Criar pessoa'),
  ('person.edit',          'Exibir formulário de edição de pessoa'),
  ('person.update',        'Atualizar pessoa'),
  ('person.informations',  'Visualizar detalhes da pessoa'),
  ('person.autocomplete',  'Usar autocomplete de pessoa'),

  ('user.index',           'Listar usuários'),
  ('user.edit',            'Exibir formulário de edição de usuário'),
  ('user.update',          'Atualizar usuário'),
  ('user.informations',    'Visualizar detalhes do usuário');

SET FOREIGN_KEY_CHECKS = 1;

-- Example grants (optional):
-- Give ALL permissions to user_id = 1:
-- INSERT IGNORE INTO user_permissions (user_id, permission_id)
-- SELECT 1, id FROM permissions;
--
-- Give permission.manage to user_id = 1:
-- INSERT IGNORE INTO user_permissions (user_id, permission_id)
-- SELECT 1, id FROM permissions WHERE ability = 'permission.manage';
