-- Apply only to the central manager database, never to manager_NNNN databases.
-- Remove the legacy cap that forced every tenant's online-user limit back to one.
-- Preserve other validation rules and any deliberately configured higher cap.
UPDATE system_setting_definitions
SET validation_rules = JSON_REMOVE(validation_rules, '$.max'),
    modified = CURRENT_TIMESTAMP
WHERE key_name = 'max_online_users'
  AND value_type = 'integer'
  AND JSON_UNQUOTE(JSON_EXTRACT(validation_rules, '$.min')) = '1'
  AND JSON_UNQUOTE(JSON_EXTRACT(validation_rules, '$.max')) = '1';
