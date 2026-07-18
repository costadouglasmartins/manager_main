<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use RuntimeException;

class SystemSetting extends Model
	{
	/**
	 * Returns active setting definitions grouped by module.
	 * @return array<int, array<string, mixed>> Module rows with nested definitions.
	 **/
	public function groupedDefinitions(): array
		{
		$stmt = $this->db->query("SELECT SystemSettingModule.id AS module_id,
		                                 SystemSettingModule.slug AS module_slug,
		                                 SystemSettingModule.name AS module_name,
		                                 SystemSettingModule.description AS module_description,
		                                 SystemSettingDefinition.id AS definition_id,
		                                 SystemSettingDefinition.key_name,
		                                 SystemSettingDefinition.label,
		                                 SystemSettingDefinition.description,
		                                 SystemSettingDefinition.value_type,
		                                 SystemSettingDefinition.default_value,
		                                 SystemSettingDefinition.validation_rules
		                          FROM system_setting_modules AS SystemSettingModule
		                          INNER JOIN system_setting_definitions AS SystemSettingDefinition ON SystemSettingDefinition.module_id = SystemSettingModule.id
		                          WHERE SystemSettingModule.active = 1
		                            AND SystemSettingDefinition.active = 1
		                          ORDER BY SystemSettingModule.sort_order ASC,
		                                   SystemSettingModule.name ASC,
		                                   SystemSettingDefinition.sort_order ASC,
		                                   SystemSettingDefinition.label ASC");

		$modules = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$moduleId = (int) $row['module_id'];
			if (!isset($modules[$moduleId]))
				{
				$modules[$moduleId] = [
					'id' => $moduleId,
					'slug' => (string) $row['module_slug'],
					'name' => (string) $row['module_name'],
					'description' => (string) ($row['module_description'] ?? ''),
					'definitions' => [],
				];
				}

			$modules[$moduleId]['definitions'][] = [
				'id' => (int) $row['definition_id'],
				'key_name' => (string) $row['key_name'],
				'label' => (string) $row['label'],
				'description' => (string) ($row['description'] ?? ''),
				'value_type' => (string) $row['value_type'],
				'default_value' => (string) ($row['default_value'] ?? ''),
				'validation_rules' => $this->decodeRules($row['validation_rules'] ?? null),
			];
			}

		return array_values($modules);
		}

	/**
	 * Returns tenant override values keyed by definition identifier.
	 * @param int $tenantId Tenant identifier.
	 * @return array<int, string> Values keyed by definition identifier.
	 **/
	public function tenantValues(int $tenantId): array
		{
		$stmt = $this->db->prepare("SELECT TenantSettingValue.setting_definition_id,
		                                   TenantSettingValue.value
		                            FROM tenant_setting_values AS TenantSettingValue
		                            WHERE TenantSettingValue.tenant_id = :tenant_id");
		$stmt->execute(['tenant_id' => $tenantId]);

		$values = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$values[(int) $row['setting_definition_id']] = (string) ($row['value'] ?? '');
			}

		return $values;
		}

	/**
	 * Saves tenant setting overrides.
	 * @param int $tenantId Tenant identifier.
	 * @param array<int|string, mixed> $values Values keyed by definition identifier.
	 * @return void
	 **/
	public function saveTenantValues(int $tenantId, array $values): void
		{
		$definitions = $this->definitionsById();
		$now = date('Y-m-d H:i:s');

		$this->db->beginTransaction();
		try
			{
			$stmt = $this->db->prepare("INSERT INTO tenant_setting_values (tenant_id, setting_definition_id, value, created, modified)
			                            VALUES (:tenant_id, :setting_definition_id, :value, :created, :modified)
			                            ON DUPLICATE KEY UPDATE value = VALUES(value),
			                                                    modified = VALUES(modified)");

			foreach ($values as $definitionId => $rawValue)
				{
				$definitionId = (int) $definitionId;
				if (!isset($definitions[$definitionId]))
					{
					continue;
					}

				$value = $this->normalizeValue($definitions[$definitionId], $rawValue);
				$stmt->execute([
					'tenant_id' => $tenantId,
					'setting_definition_id' => $definitionId,
					'value' => $value,
					'created' => $now,
					'modified' => $now,
				]);
				}

			$this->db->commit();
			}
		catch (\Throwable $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Returns setting definitions keyed by identifier.
	 * @return array<int, array<string, mixed>> Definitions keyed by identifier.
	 **/
	private function definitionsById(): array
		{
		$stmt = $this->db->query("SELECT SystemSettingDefinition.id,
		                                 SystemSettingDefinition.key_name,
		                                 SystemSettingDefinition.value_type,
		                                 SystemSettingDefinition.validation_rules
		                          FROM system_setting_definitions AS SystemSettingDefinition
		                          WHERE SystemSettingDefinition.active = 1
		                          ORDER BY SystemSettingDefinition.id ASC");

		$definitions = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$row['validation_rules'] = $this->decodeRules($row['validation_rules'] ?? null);
			$definitions[(int) $row['id']] = $row;
			}

		return $definitions;
		}

	/**
	 * Normalizes a setting value according to its definition.
	 * @param array<string, mixed> $definition Setting definition.
	 * @param mixed $rawValue Raw submitted value.
	 * @return string Normalized value.
	 **/
	private function normalizeValue(array $definition, $rawValue): string
		{
		$type = (string) ($definition['value_type'] ?? 'string');
		$rules = is_array($definition['validation_rules'] ?? null) ? $definition['validation_rules'] : [];

		if ($type === 'integer')
			{
			$value = (int) $rawValue;
			if (isset($rules['min']))
				{
				$value = max((int) $rules['min'], $value);
				}
			if (isset($rules['max']))
				{
				$value = min((int) $rules['max'], $value);
				}
			return (string) $value;
			}

		if ($type === 'boolean')
			{
			return !empty($rawValue) ? '1' : '0';
			}

		if ($type === 'json')
			{
			$decoded = json_decode((string) $rawValue, true);
			if (json_last_error() !== JSON_ERROR_NONE)
				{
				throw new RuntimeException('Invalid JSON setting value.');
				}
			return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			}

		return trim((string) $rawValue);
		}

	/**
	 * Decodes validation rules.
	 * @param mixed $rules Encoded rule payload.
	 * @return array<string, mixed> Decoded rules.
	 **/
	private function decodeRules($rules): array
		{
		if (!is_string($rules) || trim($rules) === '')
			{
			return [];
			}

		$decoded = json_decode($rules, true);
		return is_array($decoded) ? $decoded : [];
		}
	}
?>
