<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class FinancialOperationCategory extends Model
	{
	/**
	 * Returns sortable columns for list queries.
	 *
	 * @return array<string, string>
	 */
	public static function sortableColumns(): array
		{
		return [
			'id'          => 'id',
			'name'        => '`name`',
			'description' => '`description`',
		];
		}

	/**
	 * Get all method.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getAll(): array
		{
		$stmt = $this->db->query("SELECT FinancialOperationCategory.id,
								 FinancialOperationCategory.name,
								 FinancialOperationCategory.description,
								 FinancialOperationCategory.created,
								 FinancialOperationCategory.modified
							  FROM financial_operations_categories AS FinancialOperationCategory
							  ORDER BY FinancialOperationCategory.name ASC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Create method.
	 *
	 * @param array<string, mixed> $data Data to save.
	 * @return array{financial_operation_category_id: int|string}
	 * @throws \Exception
	 */
	public function create($data): array
		{
		try
			{
			$this->db->beginTransaction();

			$sql = "INSERT INTO financial_operations_categories
						(`name`, `description`, `created`, `modified`)
					VALUES
						(:name, :description, :created, :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'name'        => trim((string) ($data['name'] ?? '')),
				'description' => $this->nullableText($data['description'] ?? null),
				'created'     => date('Y-m-d H:i:s'),
				'modified'    => date('Y-m-d H:i:s'),
			]);

			$categoryId = $this->db->lastInsertId();
			$category = $this->find($categoryId);
			$this->auditCreate('financial_operation_category', 'financial_operations_categories', $categoryId, $category);

			$this->db->commit();

			return ['financial_operation_category_id' => $categoryId];
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Find method.
	 *
	 * @param int|string $id Category ID.
	 * @return array<string, mixed>|false
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare("SELECT FinancialOperationCategory.id,
								   FinancialOperationCategory.name,
								   FinancialOperationCategory.description,
								   FinancialOperationCategory.created,
								   FinancialOperationCategory.modified
							    FROM financial_operations_categories AS FinancialOperationCategory
							    WHERE FinancialOperationCategory.id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method.
	 *
	 * @param int|string $id Category ID.
	 * @param array<string, mixed> $data Data to save.
	 * @return void
	 */
	public function update($id, $data): void
		{
		$beforeData = $this->find($id);
		$stmt = $this->db->prepare("
			UPDATE financial_operations_categories
			SET
				`name`        = :name,
				`description` = :description,
				`modified`    = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'name'        => trim((string) ($data['name'] ?? '')),
			'description' => $this->nullableText($data['description'] ?? null),
			'modified'    => date('Y-m-d H:i:s'),
			'id'          => $id,
		]);
		$afterData = $this->find($id);
		$this->auditUpdate('financial_operation_category', 'financial_operations_categories', $id, $beforeData, $afterData);
		}

	/**
	 * Search method.
	 *
	 * @param array<string, mixed> $filters Search filters.
	 * @param int $limit Rows per page.
	 * @param int $offset Query offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?array $sort = null): array
		{
		$sql = "SELECT FinancialOperationCategory.id,
							  FinancialOperationCategory.name,
							  FinancialOperationCategory.description,
							  FinancialOperationCategory.created,
							  FinancialOperationCategory.modified
						   FROM financial_operations_categories AS FinancialOperationCategory
						   WHERE 1 = 1";
		$params = [];

		$this->applyFilters($sql, $params, $filters);

		$orderBy = sortOrderBy($sort, self::sortableColumns(), 'id', 'desc');
		$sql .= " ORDER BY {$orderBy} LIMIT :limit OFFSET :offset";

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Count method.
	 *
	 * @param array<string, mixed> $filters Search filters.
	 * @return int
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(FinancialOperationCategory.id) AS total
		        FROM financial_operations_categories AS FinancialOperationCategory
		        WHERE 1 = 1";
		$params = [];

		$this->applyFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Find all by financial operation category id.
	 *
	 * @param int|string $id Category ID.
	 * @return array{financial_operation_category: array<string, mixed>|false}
	 */
	public function findAllByFinancialOperationCategoryId($id): array
		{
		return ['financial_operation_category' => $this->find($id)];
		}

	/**
	 * Delete method.
	 *
	 * @param int|string $id Category ID.
	 * @return void
	 * @throws \Exception
	 */
	public function delete($id): void
		{
		try
			{
			$beforeData = $this->find($id);
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("DELETE FROM financial_operations_categories WHERE id = :id");
			$stmt->execute(['id' => $id]);
			$this->auditDelete('financial_operation_category', 'financial_operations_categories', $id, $beforeData);

			$this->db->commit();
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
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
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND FinancialOperationCategory.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND FinancialOperationCategory.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['description']) && $filters['description'] !== '')
			{
			$sql .= " AND FinancialOperationCategory.`description` LIKE :description";
			$params['description'] = '%' . $filters['description'] . '%';
			}
		}

	/**
	 * Binds parameters using PDO types.
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

	/**
	 * Converts empty strings to null.
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
