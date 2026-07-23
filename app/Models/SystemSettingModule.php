<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use RuntimeException;

class SystemSettingModule extends Model
	{
	/**
	 * Returns sortable columns for list queries.
	 *
	 * @return array<string, string>
	 */
	public static function sortableColumns(): array
		{
		return [
			'id'                => 'SystemSettingModule.id',
			'slug'              => 'SystemSettingModule.slug',
			'name'              => 'SystemSettingModule.name',
			'active'            => 'SystemSettingModule.active',
			'sort_order'        => 'SystemSettingModule.sort_order',
			'definitions_count' => 'definitions_count',
		];
		}

	/**
	 * Returns all modules for selects.
	 *
	 * @param bool $activeOnly Whether to return only active modules.
	 * @return array<int, array<string, mixed>>
	 */
	public function getAll(bool $activeOnly = false): array
		{
		$sql = "SELECT SystemSettingModule.id,
		               SystemSettingModule.slug,
		               SystemSettingModule.name,
		               SystemSettingModule.description,
		               SystemSettingModule.active,
		               SystemSettingModule.sort_order,
		               SystemSettingModule.created,
		               SystemSettingModule.modified
		        FROM system_setting_modules AS SystemSettingModule
		        WHERE 1 = 1";

		if ($activeOnly)
			{
			$sql .= " AND SystemSettingModule.active = 1";
			}

		$sql .= " ORDER BY SystemSettingModule.sort_order ASC,
		                  SystemSettingModule.name ASC";

		return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Creates a module.
	 *
	 * @param array<string, mixed> $data Submitted data.
	 * @return array{system_setting_module_id: int|string}
	 */
	public function create(array $data): array
		{
		$payload = $this->payload($data);

		try
			{
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("INSERT INTO system_setting_modules
				(slug, name, description, active, sort_order, created, modified)
				VALUES
				(:slug, :name, :description, :active, :sort_order, :created, :modified)");
			$stmt->execute([
				'slug'        => $payload['slug'],
				'name'        => $payload['name'],
				'description' => $payload['description'],
				'active'      => $payload['active'],
				'sort_order'  => $payload['sort_order'],
				'created'     => date('Y-m-d H:i:s'),
				'modified'    => date('Y-m-d H:i:s'),
			]);

			$moduleId = $this->db->lastInsertId();
			$this->auditCreate('system_setting_module', 'system_setting_modules', $moduleId, $this->find($moduleId));
			$this->db->commit();

			return ['system_setting_module_id' => $moduleId];
			}
		catch (\Throwable $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Finds a module.
	 *
	 * @param int|string $id Module identifier.
	 * @return array<string, mixed>|false
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare("SELECT SystemSettingModule.id,
		                                   SystemSettingModule.slug,
		                                   SystemSettingModule.name,
		                                   SystemSettingModule.description,
		                                   SystemSettingModule.active,
		                                   SystemSettingModule.sort_order,
		                                   SystemSettingModule.created,
		                                   SystemSettingModule.modified,
		                                   (SELECT COUNT(*)
		                                    FROM system_setting_definitions AS SystemSettingDefinition
		                                    WHERE SystemSettingDefinition.module_id = SystemSettingModule.id) AS definitions_count
		                            FROM system_setting_modules AS SystemSettingModule
		                            WHERE SystemSettingModule.id = :id");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates a module.
	 *
	 * @param int|string $id Module identifier.
	 * @param array<string, mixed> $data Submitted data.
	 * @return void
	 */
	public function update($id, array $data): void
		{
		$payload = $this->payload($data);
		$beforeData = $this->find($id);

		$stmt = $this->db->prepare("UPDATE system_setting_modules
		                            SET slug = :slug,
		                                name = :name,
		                                description = :description,
		                                active = :active,
		                                sort_order = :sort_order,
		                                modified = :modified
		                            WHERE id = :id");
		$stmt->execute([
			'slug'        => $payload['slug'],
			'name'        => $payload['name'],
			'description' => $payload['description'],
			'active'      => $payload['active'],
			'sort_order'  => $payload['sort_order'],
			'modified'    => date('Y-m-d H:i:s'),
			'id'          => (int) $id,
		]);

		$this->auditUpdate('system_setting_module', 'system_setting_modules', $id, $beforeData, $this->find($id));
		}

	/**
	 * Searches modules.
	 *
	 * @param array<string, mixed> $filters List filters.
	 * @param int $limit Rows per page.
	 * @param int $offset Offset.
	 * @param array<string, mixed>|null $sort Sort state.
	 * @return array<int, array<string, mixed>>
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?array $sort = null): array
		{
		$sql = "SELECT SystemSettingModule.id,
		               SystemSettingModule.slug,
		               SystemSettingModule.name,
		               SystemSettingModule.description,
		               SystemSettingModule.active,
		               SystemSettingModule.sort_order,
		               SystemSettingModule.created,
		               SystemSettingModule.modified,
		               (SELECT COUNT(*)
		                FROM system_setting_definitions AS SystemSettingDefinition
		                WHERE SystemSettingDefinition.module_id = SystemSettingModule.id) AS definitions_count
		        FROM system_setting_modules AS SystemSettingModule
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
	 * Counts modules matching filters.
	 *
	 * @param array<string, mixed> $filters List filters.
	 * @return int
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(SystemSettingModule.id)
		        FROM system_setting_modules AS SystemSettingModule
		        WHERE 1 = 1";
		$params = [];

		$this->applyFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Deletes a module.
	 *
	 * @param int|string $id Module identifier.
	 * @return void
	 */
	public function delete($id): void
		{
		try
			{
			$beforeData = $this->find($id);
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("DELETE FROM system_setting_modules WHERE id = :id");
			$stmt->execute(['id' => (int) $id]);
			$this->auditDelete('system_setting_module', 'system_setting_modules', $id, $beforeData);

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
			$sql .= " AND SystemSettingModule.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (($filters['slug'] ?? '') !== '')
			{
			$sql .= " AND SystemSettingModule.slug LIKE :slug";
			$params['slug'] = '%' . trim((string) $filters['slug']) . '%';
			}

		if (($filters['name'] ?? '') !== '')
			{
			$sql .= " AND SystemSettingModule.name LIKE :name";
			$params['name'] = '%' . trim((string) $filters['name']) . '%';
			}

		if (($filters['active'] ?? '') !== '')
			{
			$sql .= " AND SystemSettingModule.active = :active";
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
		$slug = $this->normalizeSlug($data['slug'] ?? '');
		$name = trim((string) ($data['name'] ?? ''));

		if ($slug === '' || $name === '')
			{
			throw new RuntimeException('Slug and name are required.');
			}

		return [
			'slug'        => $slug,
			'name'        => $name,
			'description' => $this->nullableText($data['description'] ?? null),
			'active'      => isset($data['active']) ? 1 : 0,
			'sort_order'  => (int) ($data['sort_order'] ?? 0),
		];
		}

	/**
	 * Normalizes a slug.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function normalizeSlug($value): string
		{
		$value = strtolower(trim((string) $value));
		$value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
		$value = trim($value, '_');

		return substr($value, 0, 80);
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
