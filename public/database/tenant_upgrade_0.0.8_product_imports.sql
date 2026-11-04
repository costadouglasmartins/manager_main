-- Tenant schema upgrade 0.0.8: product import batches and errors tables.
--
-- Purpose: restore `product_import_batches` and `product_import_errors`, used by the
-- client ProductImportService, in tenant databases provisioned while the template
-- lacked them, and bring older copies of these tables up to the template structure
-- (creator index, foreign key to `manager_main`.`users` and the batch/row index).
--
-- Usage: select one existing tenant database (`manager_NNNN`) as the default schema
-- and run this whole file; repeat it for every tenant database. New tenants receive
-- the same structure from tenant_template.sql, which the manager_main deployment
-- publishes. This upgrade file is not deployed: run it against each existing tenant
-- database separately.
--
-- Stop at the first error: the guards in this file raise errors on purpose and only
-- protect the database when the client aborts at the first failing statement. Feed
-- the file to the client through input redirection (batch mode stops at the first
-- error), or run it with `source` only in a client started with
-- --abort-source-on-error; an interactive `source` without that option keeps going
-- after errors. Never use --force, and set any other tool to stop at the first error.
-- One guard stops before any table is touched when the selected schema is not a
-- tenant database; another stops before the version is recorded when an existing
-- creator foreign key does not use ON DELETE SET NULL.
--
-- Idempotency: tables are created only when missing, each index or foreign key is
-- added only when information_schema shows it is absent, orphan creator ids are
-- cleared before the foreign key is added, and the version row is an upsert. Running
-- the file again on an upgraded database changes nothing but schema_versions.modified.
--
-- No rollback: MySQL commits every CREATE TABLE and ALTER TABLE implicitly, so a
-- failure cannot be undone with ROLLBACK. Fix the reported cause and run the file
-- again; completed steps are skipped. Adding the foreign key rebuilds
-- `product_import_batches`, which blocks writes to that table while it runs.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Bound metadata lock waits to 10 seconds in this session. Creating or adding the
-- foreign key to `manager_main`.`users` needs a metadata lock on that central table;
-- an unbounded wait behind a long transaction would also queue later statements on
-- users, such as logins. After a lock wait timeout error, run the file again later;
-- completed steps are skipped.
SET SESSION lock_wait_timeout = 10;

-- Abort unless the selected schema is a tenant database such as manager_0001.
-- A NULL DATABASE() also fails the test, so the invalid column is prepared and the
-- error message names the requirement.
SET @tenant_upgrade_sql = (
  SELECT IF(
    DATABASE() REGEXP '^manager_[0-9]{4}$',
    'DO 0',
    'SELECT `run_this_upgrade_only_with_a_manager_NNNN_database_selected` FROM DUAL'
  )
);
PREPARE tenant_upgrade_statement FROM @tenant_upgrade_sql;
EXECUTE tenant_upgrade_statement;
DEALLOCATE PREPARE tenant_upgrade_statement;

-- Same definitions as tenant_template.sql; existing tables are left untouched here
-- and completed by the conditional steps below.
CREATE TABLE IF NOT EXISTS `product_import_batches` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_category_id` INT NOT NULL,
  `file_name` VARCHAR(180) NOT NULL,
  `status` ENUM('PROCESSING','COMPLETED','PARTIAL','FAILED') NOT NULL DEFAULT 'PROCESSING',
  `total_rows` INT UNSIGNED NOT NULL DEFAULT '0',
  `imported_rows` INT UNSIGNED NOT NULL DEFAULT '0',
  `failed_rows` INT UNSIGNED NOT NULL DEFAULT '0',
  `created_by_user_id` INT DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_import_batches_category` (`product_category_id`),
  KEY `idx_product_import_batches_status` (`status`),
  KEY `idx_product_import_batches_created_by_user` (`created_by_user_id`),
  CONSTRAINT `fk_product_import_batches_category` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product_import_batches_manager_main_users` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_import_errors` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_import_batch_id` INT NOT NULL,
  `row_number` INT UNSIGNED NOT NULL,
  `message` VARCHAR(500) NOT NULL,
  `row_json` JSON DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_import_errors_batch` (`product_import_batch_id`),
  KEY `idx_product_import_errors_row` (`product_import_batch_id`, `row_number`),
  CONSTRAINT `fk_product_import_errors_batch` FOREIGN KEY (`product_import_batch_id`) REFERENCES `product_import_batches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Older copies lack the index that backs the creator foreign key.
SET @tenant_upgrade_sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'product_import_batches'
        AND INDEX_NAME = 'idx_product_import_batches_created_by_user'
    ),
    'DO 0',
    'ALTER TABLE `product_import_batches` ADD KEY `idx_product_import_batches_created_by_user` (`created_by_user_id`)'
  )
);
PREPARE tenant_upgrade_statement FROM @tenant_upgrade_sql;
EXECUTE tenant_upgrade_statement;
DEALLOCATE PREPARE tenant_upgrade_statement;

-- Without the constraint, deleted users may have left dangling creator ids. Clear
-- them as ON DELETE SET NULL would have done, otherwise the foreign key is refused.
-- Assigning `modified` to itself keeps ON UPDATE CURRENT_TIMESTAMP from touching it.
UPDATE `product_import_batches` AS Batch
LEFT JOIN `manager_main`.`users` AS UserRow ON UserRow.`id` = Batch.`created_by_user_id`
SET Batch.`created_by_user_id` = NULL,
    Batch.`modified` = Batch.`modified`
WHERE Batch.`created_by_user_id` IS NOT NULL
  AND UserRow.`id` IS NULL;

-- Add the cross-database foreign key when this column does not reference
-- manager_main.users yet, whatever an existing constraint is named. A reference with
-- another delete rule stops the script before the version is recorded: replacing it
-- is an operator decision, since user deletion would be blocked or would no longer
-- clear the creator. Drop that constraint deliberately and run the file again.
SET @tenant_upgrade_sql = (
  SELECT CASE
    WHEN EXISTS (
      SELECT 1
      FROM information_schema.KEY_COLUMN_USAGE AS KeyColumn
      INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS AS ReferentialRule ON ReferentialRule.CONSTRAINT_SCHEMA = KeyColumn.CONSTRAINT_SCHEMA AND ReferentialRule.CONSTRAINT_NAME = KeyColumn.CONSTRAINT_NAME
      WHERE KeyColumn.TABLE_SCHEMA = DATABASE()
        AND KeyColumn.TABLE_NAME = 'product_import_batches'
        AND KeyColumn.COLUMN_NAME = 'created_by_user_id'
        AND KeyColumn.REFERENCED_TABLE_SCHEMA = 'manager_main'
        AND KeyColumn.REFERENCED_TABLE_NAME = 'users'
        AND ReferentialRule.DELETE_RULE <> 'SET NULL'
    ) THEN 'SELECT `creator_fk_to_manager_main_users_must_be_on_delete_set_null` FROM DUAL'
    WHEN EXISTS (
      SELECT 1
      FROM information_schema.KEY_COLUMN_USAGE AS KeyColumn
      INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS AS ReferentialRule ON ReferentialRule.CONSTRAINT_SCHEMA = KeyColumn.CONSTRAINT_SCHEMA AND ReferentialRule.CONSTRAINT_NAME = KeyColumn.CONSTRAINT_NAME
      WHERE KeyColumn.TABLE_SCHEMA = DATABASE()
        AND KeyColumn.TABLE_NAME = 'product_import_batches'
        AND KeyColumn.COLUMN_NAME = 'created_by_user_id'
        AND KeyColumn.REFERENCED_TABLE_SCHEMA = 'manager_main'
        AND KeyColumn.REFERENCED_TABLE_NAME = 'users'
        AND ReferentialRule.DELETE_RULE = 'SET NULL'
    ) THEN 'DO 0'
    ELSE 'ALTER TABLE `product_import_batches` ADD CONSTRAINT `fk_product_import_batches_manager_main_users` FOREIGN KEY (`created_by_user_id`) REFERENCES `manager_main`.`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'
  END
);
PREPARE tenant_upgrade_statement FROM @tenant_upgrade_sql;
EXECUTE tenant_upgrade_statement;
DEALLOCATE PREPARE tenant_upgrade_statement;

-- Older copies lack the index used to list a batch's errors by spreadsheet row.
-- `row_number` is a reserved word in MySQL 8 and must stay quoted.
SET @tenant_upgrade_sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'product_import_errors'
        AND INDEX_NAME = 'idx_product_import_errors_row'
    ),
    'DO 0',
    'ALTER TABLE `product_import_errors` ADD KEY `idx_product_import_errors_row` (`product_import_batch_id`, `row_number`)'
  )
);
PREPARE tenant_upgrade_statement FROM @tenant_upgrade_sql;
EXECUTE tenant_upgrade_statement;
DEALLOCATE PREPARE tenant_upgrade_statement;

-- Record the version last, only after every structural step above succeeded.
INSERT INTO `schema_versions` (`version`, `description`, `created`, `modified`)
VALUES
  ('0.0.8', 'Product import batches and errors tables', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `description` = VALUES(`description`),
  `modified` = NOW();

SET @tenant_upgrade_sql = NULL;
