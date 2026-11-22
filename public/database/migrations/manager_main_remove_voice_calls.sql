-- Voice-call rollback. Run after deploying the code without voice calls
-- and restarting the chat WebSocket server.
-- Applies only to the central database; does not change manager_NNNN databases.
-- Removing voice_calls permanently deletes call history.
-- Safe to run again. DROP TABLE cannot be undone with ROLLBACK.

USE `manager_main`;

START TRANSACTION;

-- The original migration created the module and definition together using NOW().
-- Only consider removing a module with its original metadata and the same
-- creation time as the voice setting. Preserve older or customized modules:
-- the original migration did not record whether it created the module.
SET @voice_calls_module_id = (
    SELECT `Module`.`id`
    FROM `system_setting_modules` AS `Module`
    INNER JOIN `system_setting_definitions` AS `Definition`
        ON `Definition`.`module_id` = `Module`.`id`
    WHERE `Definition`.`key_name` = 'voice_calls_enabled'
      AND `Module`.`slug` = 'communication'
      AND BINARY `Module`.`name` = 'Communication'
      AND BINARY `Module`.`description` = 'Chat and voice call features.'
      AND `Module`.`active` = 1
      AND `Module`.`sort_order` = 1
      AND `Module`.`created` = `Definition`.`created`
    LIMIT 1
);

-- Remove tenant values before their definition to satisfy its foreign key.
DELETE `Value`
FROM `tenant_setting_values` AS `Value`
INNER JOIN `system_setting_definitions` AS `Definition`
    ON `Definition`.`id` = `Value`.`setting_definition_id`
WHERE `Definition`.`key_name` = 'voice_calls_enabled';

DELETE FROM `system_setting_definitions`
WHERE `key_name` = 'voice_calls_enabled';

-- Never remove a module that contains another setting.
DELETE `Module`
FROM `system_setting_modules` AS `Module`
LEFT JOIN `system_setting_definitions` AS `Definition`
    ON `Definition`.`module_id` = `Module`.`id`
WHERE `Module`.`id` = @voice_calls_module_id
  AND `Definition`.`id` IS NULL;

COMMIT;

SET @voice_calls_module_id = NULL;

-- Only outgoing foreign keys exist; foreign-key checks can remain enabled.
DROP TABLE IF EXISTS `voice_calls`;
