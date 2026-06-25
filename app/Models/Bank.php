<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Bank extends Model
	{
	/**
	 * Returns sortable columns for list queries.
	 *
	 * @return array<string, string>
	 */
	public static function sortableColumns(): array
		{
		return [
			'id'     => 'Bank.id',
			'number' => 'Bank.`number`',
			'name'   => 'Bank.`name`',
			'active' => 'Bank.`active`',
		];
		}

	/**
	 * Returns the base select query for banks.
	 */
	private function baseSelectSql(): string
		{
		return "SELECT Bank.id,
		               Bank.`name`,
		               Bank.`number`,
		               Bank.`active`,
		               Bank.created,
		               Bank.modified
		        FROM banks AS Bank";
		}

	/**
	 * Get all method.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getAll(): array
		{
		$stmt = $this->db->query($this->baseSelectSql() . "
			ORDER BY Bank.`name` ASC,
			         Bank.`number` ASC");

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Create method.
	 *
	 * @param array<string, mixed> $data Data to save.
	 * @return array{bank_id: int}
	 * @throws \Exception
	 */
	public function create($data): array
		{
		try
			{
			$this->db->beginTransaction();

			$sql = "INSERT INTO banks
			            (`name`, `number`, `active`, `created`, `modified`)
			        VALUES
			            (:name, :number, :active, :created, :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'name'     => trim((string) ($data['name'] ?? '')),
				'number'   => trim((string) ($data['number'] ?? '')),
				'active'   => $this->normalizeActive($data['active'] ?? '1'),
				'created'  => date('Y-m-d H:i:s'),
				'modified' => date('Y-m-d H:i:s'),
			]);

			$bankId = (int) $this->db->lastInsertId();
			$bank = $this->find($bankId);
			$this->auditCreate('bank', 'banks', $bankId, $bank);

			$this->db->commit();

			return ['bank_id' => $bankId];
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
	 * @param int|string $id Bank ID.
	 * @return array<string, mixed>|false
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE Bank.id = :id");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method.
	 *
	 * @param int|string $id Bank ID.
	 * @param array<string, mixed> $data Data to save.
	 * @return void
	 */
	public function update($id, $data): void
		{
		$beforeData = $this->find($id);
		$stmt = $this->db->prepare("
			UPDATE banks
			SET
				`name`     = :name,
				`number`   = :number,
				`active`   = :active,
				`modified` = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'name'     => trim((string) ($data['name'] ?? '')),
			'number'   => trim((string) ($data['number'] ?? '')),
			'active'   => $this->normalizeActive($data['active'] ?? '0'),
			'modified' => date('Y-m-d H:i:s'),
			'id'       => (int) $id,
		]);
		$afterData = $this->find($id);
		$this->auditUpdate('bank', 'banks', $id, $beforeData, $afterData);
		}

	/**
	 * Search method.
	 *
	 * @param array<string, mixed> $filters Search filters.
	 * @param int $limit Rows per page.
	 * @param int $offset Query offset.
	 * @param array<string, string>|null $sort Sort state.
	 * @return array<int, array<string, mixed>>
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?array $sort = null): array
		{
		$sql = $this->baseSelectSql() . "
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
		$sql = "SELECT COUNT(Bank.id) AS total
		        FROM banks AS Bank
		        WHERE 1 = 1";
		$params = [];

		$this->applyFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Find all by bank id.
	 *
	 * @param int|string $id Bank ID.
	 * @return array{bank: array<string, mixed>|false}
	 */
	public function findAllByBankId($id): array
		{
		return ['bank' => $this->find($id)];
		}

	/**
	 * Delete method.
	 *
	 * @param int|string $id Bank ID.
	 * @return void
	 * @throws \Exception
	 */
	public function delete($id): void
		{
		try
			{
			$beforeData = $this->find($id);
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("DELETE FROM banks WHERE id = :id");
			$stmt->execute(['id' => (int) $id]);
			$this->auditDelete('bank', 'banks', $id, $beforeData);

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
			$sql .= " AND Bank.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['number']) && $filters['number'] !== '')
			{
			$sql .= " AND Bank.`number` LIKE :number";
			$params['number'] = '%' . $filters['number'] . '%';
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND Bank.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['active']) && in_array((string) $filters['active'], ['0', '1'], true))
			{
			$sql .= " AND Bank.`active` = :active";
			$params['active'] = (int) $filters['active'];
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
	 * Normalizes status input to tinyint.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private function normalizeActive($value): int
		{
		return ((string) $value === '1') ? 1 : 0;
		}
	}

?>
