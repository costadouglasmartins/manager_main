<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class FinancialOperation extends Model
	{
	/**
	 * Returns the operation type labels.
	 *
	 * @return array<string, string>
	 */
	public static function typeOptions(): array
		{
		return [
			'CREDIT' => 'Crédito',
			'DEBIT'  => 'Débito',
		];
		}

	/**
	 * Get all method
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getAll(): array
		{
		$stmt = $this->db->query("
			SELECT fo.*,
			       Category.name AS category_name,
			       User.username,
			       Person.name AS user_name
			FROM financial_operations AS fo
			LEFT JOIN financial_operations_categories AS Category ON Category.id = fo.financial_operation_category_id
			INNER JOIN users AS User ON User.id = fo.user_id
			LEFT JOIN people AS Person ON Person.id = User.person_id
			ORDER BY fo.id DESC
		");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns user options used by financial operation forms.
	 *
	 * @param int $limit Maximum number of users returned.
	 * @return array<int, array<string, mixed>>
	 */
	public function getUserOptions(int $limit = 200): array
		{
		$limit = max(1, min(500, $limit));

		$stmt = $this->db->prepare("
			SELECT User.id,
			       User.username,
			       Person.name AS person_name
			FROM users AS User
			LEFT JOIN people AS Person ON Person.id = User.person_id
			ORDER BY Person.name ASC, User.username ASC
			LIMIT :limit
		");
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Create method
	 *
	 * @param array<string, mixed> $data Data to save.
	 * @return array{financial_operation_id: int|string}
	 * @throws \Exception
	 */
	public function create($data): array
		{
		try
			{
			$this->db->beginTransaction();

			$sql = "INSERT INTO financial_operations
						(`financial_operation_category_id`, `type`, `total_value`, `user_id`, `created`, `modified`)
					VALUES
						(:financial_operation_category_id, :type, :total_value, :user_id, :created, :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'financial_operation_category_id' => (int) $data['financial_operation_category_id'],
				'type'                            => $data['type'],
				'total_value'                     => $this->normalizeMoney($data['total_value'] ?? '0'),
				'user_id'                         => (int) $data['user_id'],
				'created'                         => date('Y-m-d H:i:s'),
				'modified'                        => date('Y-m-d H:i:s'),
			]);

			$financialOperationId = $this->db->lastInsertId();
			$this->db->commit();

			return ['financial_operation_id' => $financialOperationId];
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Find method
	 *
	 * @param int|string $id Financial operation ID.
	 * @return array<string, mixed>|false
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare("
			SELECT fo.*,
			       Category.name AS category_name,
			       User.username,
			       Person.name AS user_name
			FROM financial_operations AS fo
			LEFT JOIN financial_operations_categories AS Category ON Category.id = fo.financial_operation_category_id
			INNER JOIN users AS User ON User.id = fo.user_id
			LEFT JOIN people AS Person ON Person.id = User.person_id
			WHERE fo.id = :id
		");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 *
	 * @param int|string $id Financial operation ID.
	 * @param array<string, mixed> $data Data to save.
	 * @return void
	 */
	public function update($id, $data): void
		{
		$stmt = $this->db->prepare("
			UPDATE financial_operations
			SET
				`financial_operation_category_id` = :financial_operation_category_id,
				`type`                            = :type,
				`total_value`                     = :total_value,
				`user_id`                         = :user_id,
				`modified`                        = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'financial_operation_category_id' => (int) $data['financial_operation_category_id'],
			'type'                            => $data['type'],
			'total_value'                     => $this->normalizeMoney($data['total_value'] ?? '0'),
			'user_id'                         => (int) $data['user_id'],
			'modified'                        => date('Y-m-d H:i:s'),
			'id'                              => $id,
		]);
		}

	/**
	 * Search method
	 *
	 * @param array<string, mixed> $filters Search filters.
	 * @param int $limit Rows per page.
	 * @param int $offset Query offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = "SELECT fo.*,
		               Category.name AS category_name,
		               User.username,
		               Person.name AS user_name
		        FROM financial_operations AS fo
		        LEFT JOIN financial_operations_categories AS Category ON Category.id = fo.financial_operation_category_id
		        INNER JOIN users AS User ON User.id = fo.user_id
		        LEFT JOIN people AS Person ON Person.id = User.person_id
		        WHERE 1=1";
		$params = [];

		$this->applyFilters($sql, $params, $filters);

		$sql .= " ORDER BY fo.id DESC LIMIT :limit OFFSET :offset";

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Count method
	 *
	 * @param array<string, mixed> $filters Search filters.
	 * @return int
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM financial_operations AS fo
		        LEFT JOIN financial_operations_categories AS Category ON Category.id = fo.financial_operation_category_id
		        INNER JOIN users AS User ON User.id = fo.user_id
		        LEFT JOIN people AS Person ON Person.id = User.person_id
		        WHERE 1=1";
		$params = [];

		$this->applyFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Find all by financial operation id.
	 *
	 * @param int|string $id Financial operation ID.
	 * @return array{financial_operation: array<string, mixed>|false}
	 */
	public function findAllByFinancialOperationId($id): array
		{
		return ['financial_operation' => $this->find($id)];
		}

	/**
	 * Delete method
	 *
	 * @param int|string $id Financial operation ID.
	 * @return void
	 * @throws \Exception
	 */
	public function delete($id): void
		{
		try
			{
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("DELETE FROM financial_operations_payments WHERE financial_operation_id = :id");
			$stmt->execute(['id' => $id]);

			$stmt = $this->db->prepare("DELETE FROM financial_operations WHERE id = :id");
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
	 */
	private function applyFilters(string &$sql, array &$params, array $filters): void
		{
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND fo.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['user_id']) && $filters['user_id'] !== '')
			{
			$sql .= " AND fo.user_id = :user_id";
			$params['user_id'] = (int) $filters['user_id'];
			}

		if (isset($filters['financial_operation_category_id']) && $filters['financial_operation_category_id'] !== '')
			{
			$sql .= " AND fo.financial_operation_category_id = :financial_operation_category_id";
			$params['financial_operation_category_id'] = (int) $filters['financial_operation_category_id'];
			}

		if (isset($filters['category_name']) && $filters['category_name'] !== '')
			{
			$sql .= " AND Category.name LIKE :category_name";
			$params['category_name'] = '%' . $filters['category_name'] . '%';
			}

		if (isset($filters['user_name']) && $filters['user_name'] !== '')
			{
			$sql .= " AND (Person.name LIKE :user_name OR User.username LIKE :user_name)";
			$params['user_name'] = '%' . $filters['user_name'] . '%';
			}

		if (isset($filters['type']) && $filters['type'] !== '')
			{
			$sql .= " AND fo.`type` = :type";
			$params['type'] = $filters['type'];
			}

		if (isset($filters['total_value']) && $filters['total_value'] !== '')
			{
			$sql .= " AND fo.total_value = :total_value";
			$params['total_value'] = $this->normalizeMoney($filters['total_value']);
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
	 * Normalizes a money input to a decimal string accepted by MySQL.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function normalizeMoney($value): string
		{
		$value = trim((string) $value);
		if ($value === '')
			{
			return '0.00';
			}

		$value = str_replace(['R$', ' '], '', $value);
		if (str_contains($value, ',') && str_contains($value, '.'))
			{
			$value = str_replace('.', '', $value);
			}
		$value = str_replace(',', '.', $value);

		return number_format((float) $value, 2, '.', '');
		}
	}

?>
