-- Apply only to the central manager database (manager_main), never to manager_NNNN databases.
-- Voice calls between tenant users and with support: the call log and the
-- per-tenant switch. Safe to run more than once.

CREATE TABLE IF NOT EXISTS `voice_calls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `call_uid` char(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `kind` enum('internal','support') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `support_chat_id` int(11) DEFAULT NULL,
  `caller_user_id` int(11) NOT NULL,
  `caller_system` enum('manager','manager_main') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `callee_user_id` int(11) NOT NULL,
  `callee_system` enum('manager','manager_main') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('ringing','active','completed','rejected','missed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ringing',
  `end_reason` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `started_at` datetime NOT NULL,
  `answered_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `duration_seconds` int(11) NOT NULL DEFAULT 0,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `modified` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_voice_calls_call_uid` (`call_uid`),
  KEY `idx_voice_calls_tenant_started` (`tenant_id`,`started_at`),
  KEY `idx_voice_calls_caller_started` (`caller_user_id`,`started_at`),
  KEY `idx_voice_calls_callee_started` (`callee_user_id`,`started_at`),
  KEY `idx_voice_calls_support_chat` (`support_chat_id`),
  CONSTRAINT `fk_voice_calls_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_voice_calls_support_chat` FOREIGN KEY (`support_chat_id`) REFERENCES `support_chats` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_voice_calls_caller_user` FOREIGN KEY (`caller_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_voice_calls_callee_user` FOREIGN KEY (`callee_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_setting_modules` (`slug`, `name`, `description`, `active`, `sort_order`, `created`, `modified`)
SELECT 'communication', 'Communication', 'Chat and voice call features.', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `system_setting_modules` WHERE `slug` = 'communication');

-- Enabled by default once this migration runs; turn it off per tenant in the system settings.
INSERT INTO `system_setting_definitions` (`module_id`, `key_name`, `label`, `description`, `value_type`, `default_value`, `validation_rules`, `active`, `sort_order`, `created`, `modified`)
SELECT `Module`.`id`, 'voice_calls_enabled', 'Chamadas de voz', 'Allows voice calls between tenant users and with support.', 'boolean', '1', NULL, 1, 0, NOW(), NOW()
FROM `system_setting_modules` AS `Module`
WHERE `Module`.`slug` = 'communication'
  AND NOT EXISTS (SELECT 1 FROM `system_setting_definitions` WHERE `key_name` = 'voice_calls_enabled');
