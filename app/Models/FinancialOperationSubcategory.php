<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class FinancialOperationSubcategory extends Model
	{
	/**
	 * Returns sortable columns for list queries.
	 *
	 * @return array<string, string>
	 **/
	public static function sortableColumns(): array
		{
		return [
			'id'                              => 'FinancialOperationSubcategory.id',
			'financial_operation_category_id' => 'FinancialOperationSubcategory.financial_operation_category_id',
			'category_name'                   => 'Category.`name`',
			'name'                            => 'FinancialOperationSubcategory.`name`',
		];
		}

	/**
	 * Get all method.
	 *
	 * @return array<int, array<string, mixed>>
	 **/
	public function getAll(?int $categoryId = null): array
		{
		$sql = "SELECT FinancialOperationSubcategory.id,
		               FinancialOperationSubcategory.financial_operation_category_id,
		               FinancialOperationSubcategory.name,
		               FinancialOperationSubcategory.created,
		               FinancialOperationSubcategory.modified,
		               Category.name AS category_name,
		               Category.description AS category_description
		        FROM financial_operations_subcategories AS FinancialOperationSubcategory
		        INNER JOIN financial_operations_categories AS Category ON Category.id = FinancialOperationSubcategory.financial_operation_category_id";
		$params = [];

		if ($categoryId !== null && $categoryId > 0)
			{
			$sql .= " WHERE FinancialOperationSubcategory.financial_operation_category_id = :financial_operation_category_id";
			$params['financial_operation_category_id'] = $categoryId;
			}

		$sql .= " ORDER BY Category.name ASC, FinancialOperationSubcategory.name ASC";

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Create method.
	 *
	 * @param array<string, mixed> $data Data to save.
	 * @return array{financial_operation_subcategory_id: int|string}
	 * @throws \Exception
	 **/
	public function create($data): array
		{
		try
			{
			$this->db->beginTransaction();
			$categoryId = $this->categoryIdFromData($data);

			$sql = "INSERT INTO financial_operations_subcategories
						(`financial_operation_category_id`, `name`, `created`, `modified`)
					VALUES
						(:financial_operation_category_id, :name, :created, :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'financial_operation_category_id' => $categoryId,
				'name'                            => trim((string) ($data['name'] ?? '')),
				'created'                         => date('Y-m-d H:i:s'),
				'modified'                        => date('Y-m-d H:i:s'),
			]);

			$subcategoryId = $this->db->lastInsertId();

			$this->db->commit();

			return ['financial_operation_subcategory_id' => $subcategoryId];
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
	 * @param int|string $id Subcategory ID.
	 * @return array<string, mixed>|false
	 **/
	public function find($id)
		{
		$stmt = $this->db->prepare("SELECT FinancialOperationSubcategory.id,
								   FinancialOperationSubcategory.financial_operation_category_id,
								   FinancialOperationSubcategory.name,
								   FinancialOperationSubcategory.created,
								   FinancialOperationSubcategory.modified,
								   Category.name AS category_name,
								   Category.description AS category_description
								FROM financial_operations_subcategories AS FinancialOperationSubcategory
								INNER JOIN financial_operations_categories AS Category ON Category.id = FinancialOperationSubcategory.financial_operation_category_id
								WHERE FinancialOperationSubcategory.id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method.
	 *
	 * @param int|string $id Subcategory ID.
	 * @param array<string, mixed> $data Data to save.
	 * @return void
	 **/
	public function update($id, $data): void
		{
		$categoryId = $this->categoryIdFromData($data);
		$stmt = $this->db->prepare("
			UPDATE financial_operations_subcategories
			SET
				`financial_operation_category_id` = :financial_operation_category_id,
				`name`                            = :name,
				`modified`                        = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'financial_operation_category_id' => $categoryId,
			'name'                            => trim((string) ($data['name'] ?? '')),
			'modified'                        => date('Y-m-d H:i:s'),
			'id'                              => $id,
		]);
		}

	/**
	 * Search method.
	 *
	 * @param array<string, mixed> $filters Search filters.
	 * @param int $limit Rows per page.
	 * @param int $offset Query offset.
	 * @param array<string, string>|null $sort Sort definition.
	 * @return array<int, array<string, mixed>>
	 **/
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?array $sort = null): array
		{
		$sql = "SELECT FinancialOperationSubcategory.id,
							  FinancialOperationSubcategory.financial_operation_category_id,
							  FinancialOperationSubcategory.name,
							  FinancialOperationSubcategory.created,
							  FinancialOperationSubcategory.modified,
							  Category.name AS category_name,
							  Category.description AS category_description
						   FROM financial_operations_subcategories AS FinancialOperationSubcategory
						   INNER JOIN financial_operations_categories AS Category ON Category.id = FinancialOperationSubcategory.financial_operation_category_id
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
	 **/
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(FinancialOperationSubcategory.id) AS total
		        FROM financial_operations_subcategories AS FinancialOperationSubcategory
		        INNER JOIN financial_operations_categories AS Category ON Category.id = FinancialOperationSubcategory.financial_operation_category_id
		        WHERE 1 = 1";
		$params = [];

		$this->applyFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Find all by financial operation subcategory id.
	 *
	 * @param int|string $id Subcategory ID.
	 * @return array{financial_operation_subcategory: array<string, mixed>|false}
	 **/
	public function findAllByFinancialOperationSubcategoryId($id): array
		{
		return ['financial_operation_subcategory' => $this->find($id)];
		}

	/**
	 * Delete method.
	 *
	 * @param int|string $id Subcategory ID.
	 * @return void
	 * @throws \Exception
	 **/
	public function delete($id): void
		{
		try
			{
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("DELETE FROM financial_operations_subcategories WHERE id = :id");
			$stmt->execute(['id' => $id]);

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
	 **/
	private function applyFilters(string &$sql, array &$params, array $filters): void
		{
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND FinancialOperationSubcategory.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['financial_operation_category_id']) && $filters['financial_operation_category_id'] !== '')
			{
			$sql .= " AND FinancialOperationSubcategory.financial_operation_category_id = :financial_operation_category_id";
			$params['financial_operation_category_id'] = (int) $filters['financial_operation_category_id'];
			}

		if (isset($filters['category_name']) && $filters['category_name'] !== '')
			{
			$sql .= " AND Category.`name` LIKE :category_name";
			$params['category_name'] = '%' . $filters['category_name'] . '%';
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND FinancialOperationSubcategory.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}
		}

	/**
	 * Binds parameters using PDO types.
	 *
	 * @param \PDOStatement $stmt Prepared statement.
	 * @param array<string, mixed> $params Bound parameters.
	 * @return void
	 **/
	private function bindParams(\PDOStatement $stmt, array $params): void
		{
		foreach ($params as $key => $value)
			{
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue(':' . $key, $value, $paramType);
			}
		}

	/**
	 * Extracts and validates the parent category id.
	 *
	 * @param array<string, mixed> $data Data to save.
	 * @return int
	 */
	private function categoryIdFromData(array $data): int
		{
		$categoryId = (int) ($data['financial_operation_category_id'] ?? 0);
		if ($categoryId <= 0)
			{
			throw new \InvalidArgumentException('Financial operation category is required.');
			}

		return $categoryId;
		}
	}

?>
