<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use RuntimeException;

class SystemSettingDefinition extends Model
	{
	private const VALUE_TYPES = ['string', 'integer', 'decimal', 'boolean', 'json'];

	/**
	 * Returns sortable columns for list queries.
	 *
	 * @return array<string, string>
	 */
	public static function sortableColumns(): array
		{
		return [
			'id'          => 'SystemSettingDefinition.id',
			'module_name' => 'SystemSettingModule.name',
			'key_name'    => 'SystemSettingDefinition.key_name',
			'label'       => 'SystemSettingDefinition.label',
			'value_type'  => 'SystemSettingDefinition.value_type',
			'active'      => 'SystemSettingDefinition.active',
			'sort_order'  => 'SystemSettingDefinition.sort_order',
		];
		}

	/**
	 * Returns supported value types.
	 *
	 * @return array<int, string>
	 */
	public static function valueTypes(): array
		{
		return self::VALUE_TYPES;
		}

	/**
	 * Creates a definition.
	 *
	 * @param array<string, mixed> $data Submitted data.
	 * @return array{system_setting_definition_id: int|string}
	 */
	public function create(array $data): array
		{
		$payload = $this->payload($data);

		try
			{
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("INSERT INTO system_setting_definitions
				(module_id, key_name, label, description, value_type, default_value, validation_rules, active, sort_order, created, modified)
				VALUES
				(:module_id, :key_name, :label, :description, :value_type, :default_value, :validation_rules, :active, :sort_order, :created, :modified)");
			$stmt->execute([
				'module_id'        => $payload['module_id'],
				'key_name'         => $payload['key_name'],
				'label'            => $payload['label'],
				'description'      => $payload['description'],
				'value_type'       => $payload['value_type'],
				'default_value'    => $payload['default_value'],
				'validation_rules' => $payload['validation_rules'],
				'active'           => $payload['active'],
				'sort_order'       => $payload['sort_order'],
				'created'          => date('Y-m-d H:i:s'),
				'modified'         => date('Y-m-d H:i:s'),
			]);

			$definitionId = $this->db->lastInsertId();
			$this->auditCreate('system_setting_definition', 'system_setting_definitions', $definitionId, $this->find($definitionId));
			$this->db->commit();

			return ['system_setting_definition_id' => $definitionId];
			}
		catch (\Throwable $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Finds a definition.
	 *
	 * @param int|string $id Definition identifier.
	 * @return array<string, mixed>|false
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare("SELECT SystemSettingDefinition.id,
		                                   SystemSettingDefinition.module_id,
		                                   SystemSettingDefinition.key_name,
		                                   SystemSettingDefinition.label,
		                                   SystemSettingDefinition.description,
		                                   SystemSettingDefinition.value_type,
		                                   SystemSettingDefinition.default_value,
		                                   SystemSettingDefinition.validation_rules,
		                                   SystemSettingDefinition.active,
		                                   SystemSettingDefinition.sort_order,
		                                   SystemSettingDefinition.created,
		                                   SystemSettingDefinition.modified,
		                                   SystemSettingModule.slug AS module_slug,
		                                   SystemSettingModule.name AS module_name,
		                                   (SELECT COUNT(*)
		                                    FROM tenant_setting_values AS TenantSettingValue
		                                    WHERE TenantSettingValue.setting_definition_id = SystemSettingDefinition.id) AS tenant_values_count
		                            FROM system_setting_definitions AS SystemSettingDefinition
		                            INNER JOIN system_setting_modules AS SystemSettingModule ON SystemSettingModule.id = SystemSettingDefinition.module_id
		                            WHERE SystemSettingDefinition.id = :id");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates a definition.
	 *
	 * @param int|string $id Definition identifier.
	 * @param array<string, mixed> $data Submitted data.
	 * @return void
	 */
	public function update($id, array $data): void
		{
		$payload = $this->payload($data);
		$beforeData = $this->find($id);

		$stmt = $this->db->prepare("UPDATE system_setting_definitions
		                            SET module_id = :module_id,
		                                key_name = :key_name,
		                                label = :label,
		                                description = :description,
		                                value_type = :value_type,
		                                default_value = :default_value,
		                                validation_rules = :validation_rules,
		                                active = :active,
		                                sort_order = :sort_order,
		                                modified = :modified
		                            WHERE id = :id");
		$stmt->execute([
			'module_id'        => $payload['module_id'],
			'key_name'         => $payload['key_name'],
			'label'            => $payload['label'],
			'description'      => $payload['description'],
			'value_type'       => $payload['value_type'],
			'default_value'    => $payload['default_value'],
			'validation_rules' => $payload['validation_rules'],
			'active'           => $payload['active'],
			'sort_order'       => $payload['sort_order'],
			'modified'         => date('Y-m-d H:i:s'),
			'id'               => (int) $id,
		]);

		$this->auditUpdate('system_setting_definition', 'system_setting_definitions', $id, $beforeData, $this->find($id));
		}

	/**
	 * Searches definitions.
	 *
	 * @param array<string, mixed> $filters List filters.
	 * @param int $limit Rows per page.
	 * @param int $offset Offset.
	 * @param array<string, mixed>|null $sort Sort state.
	 * @return array<int, array<string, mixed>>
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?array $sort = null): array
		{
		$sql = "SELECT SystemSettingDefinition.id,
		               SystemSettingDefinition.module_id,
		               SystemSettingDefinition.key_name,
		               SystemSettingDefinition.label,
		               SystemSettingDefinition.description,
		               SystemSettingDefinition.value_type,
		               SystemSettingDefinition.default_value,
		               SystemSettingDefinition.validation_rules,
		               SystemSettingDefinition.active,
		               SystemSettingDefinition.sort_order,
		               SystemSettingDefinition.created,
		               SystemSettingDefinition.modified,
		               SystemSettingModule.slug AS module_slug,
		               SystemSettingModule.name AS module_name,
		               (SELECT COUNT(*)
		                FROM tenant_setting_values AS TenantSettingValue
		                WHERE TenantSettingValue.setting_definition_id = SystemSettingDefinition.id) AS tenant_values_count
		        FROM system_setting_definitions AS SystemSettingDefinition
		        INNER JOIN system_setting_modules AS SystemSettingModule ON SystemSettingModule.id = SystemSettingDefinition.module_id
		        WHERE 1 = 1";
		$params = [];

		$this->applyFilters($sql, $params, $filters);

		$sql .= " ORDER BY " . sortOrderBy($sort, self::sortableColumns(), 'sort_order', 'asc');
		$sql .= " LIMIT :limit OFFSET :offset";

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Counts definitions matching filters.
	 *
	 * @param array<string, mixed> $filters List filters.
	 * @return int
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(SystemSettingDefinition.id)
		        FROM system_setting_definitions AS SystemSettingDefinition
		        INNER JOIN system_setting_modules AS SystemSettingModule ON SystemSettingModule.id = SystemSettingDefinition.module_id
		        WHERE 1 = 1";
		$params = [];

		$this->applyFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Deletes a definition.
	 *
	 * @param int|string $id Definition identifier.
	 * @return void
	 */
	public function delete($id): void
		{
		try
			{
			$beforeData = $this->find($id);
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("DELETE FROM system_setting_definitions WHERE id = :id");
			$stmt->execute(['id' => (int) $id]);
			$this->auditDelete('system_setting_definition', 'system_setting_definitions', $id, $beforeData);

			$this->db->commit();
			}
		catch (\Throwable $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Applies filters to a list query.
	 *
	 * @param string $sql SQL statement.
	 * @param array<string, mixed> $params Bound parameters.
	 * @param array<string, mixed> $filters List filters.
	 * @return void
	 */
	private function applyFilters(string &$sql, array &$params, array $filters): void
		{
		if (($filters['id'] ?? '') !== '')
			{
			$sql .= " AND SystemSettingDefinition.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (($filters['module_id'] ?? '') !== '')
			{
			$sql .= " AND SystemSettingDefinition.module_id = :module_id";
			$params['module_id'] = (int) $filters['module_id'];
			}

		if (($filters['key_name'] ?? '') !== '')
			{
			$sql .= " AND SystemSettingDefinition.key_name LIKE :key_name";
			$params['key_name'] = '%' . trim((string) $filters['key_name']) . '%';
			}

		if (($filters['label'] ?? '') !== '')
			{
			$sql .= " AND SystemSettingDefinition.label LIKE :label";
			$params['label'] = '%' . trim((string) $filters['label']) . '%';
			}

		if (($filters['value_type'] ?? '') !== '')
			{
			$sql .= " AND SystemSettingDefinition.value_type = :value_type";
			$params['value_type'] = (string) $filters['value_type'];
			}

		if (($filters['active'] ?? '') !== '')
			{
			$sql .= " AND SystemSettingDefinition.active = :active";
			$params['active'] = (int) $filters['active'] === 1 ? 1 : 0;
			}
		}

	/**
	 * Normalizes form payload.
	 *
	 * @param array<string, mixed> $data Submitted data.
	 * @return array<string, mixed>
	 */
	private function payload(array $data): array
		{
		$moduleId = (int) ($data['module_id'] ?? 0);
		$keyName = $this->normalizeKeyName($data['key_name'] ?? '');
		$label = trim((string) ($data['label'] ?? ''));
		$valueType = (string) ($data['value_type'] ?? 'string');

		if ($moduleId <= 0 || $keyName === '' || $label === '')
			{
			throw new RuntimeException('Module, key name and label are required.');
			}

		if (!in_array($valueType, self::VALUE_TYPES, true))
			{
			throw new RuntimeException('Invalid value type.');
			}

		return [
			'module_id'        => $moduleId,
			'key_name'         => $keyName,
			'label'            => $label,
			'description'      => $this->nullableText($data['description'] ?? null),
			'value_type'       => $valueType,
			'default_value'    => $this->nullableText($data['default_value'] ?? null),
			'validation_rules' => $this->normalizeJson($data['validation_rules'] ?? null),
			'active'           => isset($data['active']) ? 1 : 0,
			'sort_order'       => (int) ($data['sort_order'] ?? 0),
		];
		}

	/**
	 * Normalizes setting key names.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function normalizeKeyName($value): string
		{
		$value = strtolower(trim((string) $value));
		$value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
		$value = trim($value, '_');

		return substr($value, 0, 120);
		}

	/**
	 * Validates and normalizes JSON text.
	 *
	 * @param mixed $value Raw JSON.
	 * @return string|null
	 */
	private function normalizeJson($value): ?string
		{
		$value = trim((string) $value);
		if ($value === '')
			{
			return null;
			}

		$decoded = json_decode($value, true);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded))
			{
			throw new RuntimeException('Validation rules must be a valid JSON object.');
			}

		return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

	/**
	 * Binds prepared statement parameters.
	 *
	 * @param \PDOStatement $stmt Prepared statement.
	 * @param array<string, mixed> $params Bound parameters.
	 * @return void
	 */
	private function bindParams(\PDOStatement $stmt, array $params): void
		{
		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}
		}

	/**
	 * Converts empty text to null.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private function nullableText($value): ?string
		{
		$value = trim((string) $value);
		return $value === '' ? null : $value;
		}
	}

?>
