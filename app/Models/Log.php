<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Log extends Model
	{
	/**
	 * Returns sortable columns for list queries.
	 *
	 * @return array<string, string>
	 */
	public static function sortableColumns(): array
		{
		return [
			'id' => 'Log.id',
			'occurred_at' => 'Log.occurred_at',
			'module' => 'Log.module',
			'action' => 'Log.action',
			'entity_name' => 'Log.entity_name',
			'performed_by_name' => 'Log.performed_by_name',
			'tenant_name_snapshot' => 'Log.tenant_name_snapshot',
		];
		}

	/**
	 * Returns the available module values used by the filter screen.
	 *
	 * @return array<int, string>
	 */
	public function getModules(): array
		{
		$stmt = $this->db->query("SELECT DISTINCT Log.module
		                          FROM logs AS Log
		                          WHERE Log.module IS NOT NULL
		                            AND Log.module <> ''
		                          ORDER BY Log.module ASC");

		return array_map(static fn(array $row): string => (string) $row['module'], $stmt->fetchAll(PDO::FETCH_ASSOC));
		}

	/**
	 * Searches log records using optional filters and pagination.
	 *
	 * @param array<string, mixed> $filters Search filters.
	 * @param int $limit Rows per page.
	 * @param int $offset Query offset.
	 * @param array<string, string>|null $sort Sort state.
	 * @return array<int, array<string, mixed>>
	 */
	public function search(array $filters = [], int $limit = 20, int $offset = 0, ?array $sort = null): array
		{
		$sql = "SELECT Log.id,
		               Log.event_uuid,
		               Log.tenant_id,
		               Log.tenant_database,
		               Log.tenant_name_snapshot,
		               Log.module,
		               Log.action,
		               Log.entity_name,
		               Log.entity_id,
		               Log.performed_by_user_id,
		               Log.performed_by_username,
		               Log.performed_by_name,
		               Log.request_method,
		               Log.route,
		               Log.ip_address,
		               Log.user_agent,
		               Log.occurred_at,
		               Log.before_data,
		               Log.after_data,
		               Log.changed_fields,
		               Log.context_json
		        FROM logs AS Log
		        WHERE 1 = 1";
		$params = [];

		$this->applyFilters($sql, $params, $filters);

		$orderBy = sortOrderBy($sort, self::sortableColumns(), 'occurred_at', 'desc');
		$sql .= " ORDER BY {$orderBy}, Log.id DESC LIMIT :limit OFFSET :offset";

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Counts logs matching the provided filters.
	 *
	 * @param array<string, mixed> $filters Search filters.
	 * @return int
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(Log.id) AS total
		        FROM logs AS Log
		        WHERE 1 = 1";
		$params = [];

		$this->applyFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Finds a single log entry by ID.
	 *
	 * @param int|string $id Log ID.
	 * @return array<string, mixed>|null
	 */
	public function find($id): ?array
		{
		$stmt = $this->db->prepare("SELECT Log.id,
		                                   Log.event_uuid,
		                                   Log.tenant_id,
		                                   Log.tenant_database,
		                                   Log.tenant_name_snapshot,
		                                   Log.module,
		                                   Log.action,
		                                   Log.entity_name,
		                                   Log.entity_id,
		                                   Log.performed_by_user_id,
		                                   Log.performed_by_username,
		                                   Log.performed_by_name,
		                                   Log.request_method,
		                                   Log.route,
		                                   Log.ip_address,
		                                   Log.user_agent,
		                                   Log.occurred_at,
		                                   Log.before_data,
		                                   Log.after_data,
		                                   Log.changed_fields,
		                                   Log.context_json,
		                                   Log.created,
		                                   Log.modified
		                            FROM logs AS Log
		                            WHERE Log.id = :id
		                            LIMIT 1");
		$stmt->execute(['id' => (int) $id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ?: null;
		}

	/**
	 * Applies list filters to a SQL statement.
	 *
	 * @param string $sql SQL statement passed by reference.
	 * @param array<string, mixed> $params Bound parameters passed by reference.
	 * @param array<string, mixed> $filters Search filters.
	 * @return void
	 */
	private function applyFilters(string &$sql, array &$params, array $filters): void
		{
		if (!empty($filters['id']))
			{
			$sql .= " AND Log.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (!empty($filters['tenant']))
			{
			$sql .= " AND (Log.tenant_name_snapshot LIKE :tenant OR Log.tenant_database LIKE :tenant_database)";
			$params['tenant'] = '%' . trim((string) $filters['tenant']) . '%';
			$params['tenant_database'] = '%' . trim((string) $filters['tenant']) . '%';
			}

		if (!empty($filters['module']))
			{
			$sql .= " AND Log.module = :module";
			$params['module'] = trim((string) $filters['module']);
			}

		if (!empty($filters['action']))
			{
			$sql .= " AND Log.action = :action";
			$params['action'] = trim((string) $filters['action']);
			}

		if (!empty($filters['entity_name']))
			{
			$sql .= " AND Log.entity_name LIKE :entity_name";
			$params['entity_name'] = '%' . trim((string) $filters['entity_name']) . '%';
			}

		if (!empty($filters['entity_id']))
			{
			$sql .= " AND Log.entity_id = :entity_id";
			$params['entity_id'] = trim((string) $filters['entity_id']);
			}

		if (!empty($filters['performed_by']))
			{
			$sql .= " AND (Log.performed_by_name LIKE :performed_by_name OR Log.performed_by_username LIKE :performed_by_username)";
			$params['performed_by_name'] = '%' . trim((string) $filters['performed_by']) . '%';
			$params['performed_by_username'] = '%' . trim((string) $filters['performed_by']) . '%';
			}

		if (!empty($filters['route']))
			{
			$sql .= " AND Log.route LIKE :route";
			$params['route'] = '%' . trim((string) $filters['route']) . '%';
			}

		if (!empty($filters['date_from']))
			{
			$sql .= " AND Log.occurred_at >= :date_from";
			$params['date_from'] = trim((string) $filters['date_from']) . ' 00:00:00';
			}

		if (!empty($filters['date_to']))
			{
			$sql .= " AND Log.occurred_at <= :date_to";
			$params['date_to'] = trim((string) $filters['date_to']) . ' 23:59:59';
			}
		}

	/**
	 * Binds named parameters to a prepared statement.
	 *
	 * @param \PDOStatement $stmt Prepared statement.
	 * @param array<string, mixed> $params Bound parameters.
	 * @return void
	 */
	private function bindParams(\PDOStatement $stmt, array $params): void
		{
		foreach ($params as $key => $value)
			{
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue(':' . $key, $value, $paramType);
			}
		}
	}
?>
