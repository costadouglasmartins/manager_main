<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class FinancialOperationPayment extends Model
	{
	/**
	 * Returns sortable columns for list queries.
	 *
	 * @return array<string, string>
	 */
	public static function sortableColumns(): array
		{
		return [
			'id'                     => 'FinancialOperationPayment.id',
			'financial_operation_id' => 'FinancialOperationPayment.financial_operation_id',
			'type'                   => 'FinancialOperationPayment.`type`',
			'value'                  => 'FinancialOperationPayment.`value`',
			'payment_date'           => 'FinancialOperationPayment.`payment_date`',
			'due_date'               => 'FinancialOperationPayment.`due_date`',
		];
		}

	/**
	 * Returns the payment type labels.
	 *
	 * @return array<string, string>
	 */
	public static function typeOptions(): array
		{
		return [
			'CASH'      => 'Dinheiro',
			'CHECK'     => 'Cheque',
			'PIX'       => 'PIX',
			'CARD'      => 'Cartão',
			'BANK_SLIP' => 'Boleto',
		];
		}

	/**
	 * Get all method
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getAll(): array
		{
		$stmt = $this->db->query($this->baseSelect() . " ORDER BY FinancialOperationPayment.id DESC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Create method
	 *
	 * @param array<string, mixed> $data Data to save.
	 * @return array{financial_operation_payment_id: int|string}
	 * @throws \Exception
	 */
	public function create($data): array
		{
		try
			{
			$this->db->beginTransaction();

			$sql = "INSERT INTO financial_operations_payments
						(`financial_operation_id`, `type`, `value`, `payment_date`, `due_date`, `created`, `modified`)
					VALUES
						(:financial_operation_id, :type, :value, :payment_date, :due_date, :created, :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'financial_operation_id' => (int) $data['financial_operation_id'],
				'type'                   => $data['type'],
				'value'                  => $this->normalizeMoney($data['value'] ?? '0'),
				'payment_date'           => $this->normalizeDateTime($data['payment_date'] ?? null),
				'due_date'               => $this->normalizeDate($data['due_date'] ?? null),
				'created'                => date('Y-m-d H:i:s'),
				'modified'               => date('Y-m-d H:i:s'),
			]);

			$paymentId = $this->db->lastInsertId();
			$payment = $this->find($paymentId);
			$this->auditCreate('financial_operation_payment', 'financial_operations_payments', $paymentId, $payment);
			$this->db->commit();

			return ['financial_operation_payment_id' => $paymentId];
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
	 * @param int|string $id Payment ID.
	 * @return array<string, mixed>|false
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelect() . " WHERE FinancialOperationPayment.id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Finds payments linked to a financial operation.
	 *
	 * @param int|string $financialOperationId Financial operation ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function findByFinancialOperationId($financialOperationId): array
		{
		$stmt = $this->db->prepare($this->baseSelect() . "
			WHERE FinancialOperationPayment.financial_operation_id = :financial_operation_id
			ORDER BY FinancialOperationPayment.due_date ASC, FinancialOperationPayment.id ASC
		");
		$stmt->execute(['financial_operation_id' => $financialOperationId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 *
	 * @param int|string $id Payment ID.
	 * @param array<string, mixed> $data Data to save.
	 * @return void
	 */
	public function update($id, $data): void
		{
		$beforeData = $this->find($id);
		$stmt = $this->db->prepare("
			UPDATE financial_operations_payments
			SET
				`financial_operation_id` = :financial_operation_id,
				`type`                   = :type,
				`value`                  = :value,
				`payment_date`           = :payment_date,
				`due_date`               = :due_date,
				`modified`               = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'financial_operation_id' => (int) $data['financial_operation_id'],
			'type'                   => $data['type'],
			'value'                  => $this->normalizeMoney($data['value'] ?? '0'),
			'payment_date'           => $this->normalizeDateTime($data['payment_date'] ?? null),
			'due_date'               => $this->normalizeDate($data['due_date'] ?? null),
			'modified'               => date('Y-m-d H:i:s'),
			'id'                     => $id,
		]);
		$afterData = $this->find($id);
		$this->auditUpdate('financial_operation_payment', 'financial_operations_payments', $id, $beforeData, $afterData);
		}

	/**
	 * Search method
	 *
	 * @param array<string, mixed> $filters Search filters.
	 * @param int $limit Rows per page.
	 * @param int $offset Query offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?array $sort = null): array
		{
		$sql = $this->baseSelect() . " WHERE 1 = 1";
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
	 * Count method
	 *
	 * @param array<string, mixed> $filters Search filters.
	 * @return int
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(FinancialOperationPayment.id) AS total
		        FROM financial_operations_payments AS FinancialOperationPayment
		        INNER JOIN financial_operations AS FinancialOperation ON FinancialOperation.id = FinancialOperationPayment.financial_operation_id
		        INNER JOIN users AS User ON User.id = FinancialOperation.user_id
		        LEFT JOIN people AS Person ON Person.id = User.person_id
		        WHERE 1 = 1";
		$params = [];

		$this->applyFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Find all by payment id.
	 *
	 * @param int|string $id Payment ID.
	 * @return array{financial_operation_payment: array<string, mixed>|false}
	 */
	public function findAllByFinancialOperationPaymentId($id): array
		{
		return ['financial_operation_payment' => $this->find($id)];
		}

	/**
	 * Delete method
	 *
	 * @param int|string $id Payment ID.
	 * @return void
	 * @throws \Exception
	 */
	public function delete($id): void
		{
		try
			{
			$beforeData = $this->find($id);
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("DELETE FROM financial_operations_payments WHERE id = :id");
			$stmt->execute(['id' => $id]);
			$this->auditDelete('financial_operation_payment', 'financial_operations_payments', $id, $beforeData);

			$this->db->commit();
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Returns the joined select used by list and details queries.
	 *
	 * @return string
	 */
	private function baseSelect(): string
		{
		return "SELECT FinancialOperationPayment.id,
		               FinancialOperationPayment.financial_operation_id,
		               FinancialOperationPayment.type,
		               FinancialOperationPayment.value,
		               FinancialOperationPayment.payment_date,
		               FinancialOperationPayment.due_date,
		               FinancialOperationPayment.created,
		               FinancialOperationPayment.modified,
		               FinancialOperation.type AS financial_operation_type,
		               FinancialOperation.total_value AS financial_operation_total_value,
		               Category.name AS financial_operation_category_name,
		               User.username,
		               Person.name AS user_name
		        FROM financial_operations_payments AS FinancialOperationPayment
		        INNER JOIN financial_operations AS FinancialOperation ON FinancialOperation.id = FinancialOperationPayment.financial_operation_id
		        LEFT JOIN financial_operations_categories AS Category ON Category.id = FinancialOperation.financial_operation_category_id
		        INNER JOIN users AS User ON User.id = FinancialOperation.user_id
		        LEFT JOIN people AS Person ON Person.id = User.person_id";
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
			$sql .= " AND FinancialOperationPayment.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['financial_operation_id']) && $filters['financial_operation_id'] !== '')
			{
			$sql .= " AND FinancialOperationPayment.financial_operation_id = :financial_operation_id";
			$params['financial_operation_id'] = (int) $filters['financial_operation_id'];
			}

		if (isset($filters['financial_operation_type']) && $filters['financial_operation_type'] !== '')
			{
			$sql .= " AND FinancialOperation.`type` = :financial_operation_type";
			$params['financial_operation_type'] = $filters['financial_operation_type'];
			}

		if (isset($filters['type']) && $filters['type'] !== '')
			{
			$sql .= " AND FinancialOperationPayment.`type` = :type";
			$params['type'] = $filters['type'];
			}

		if (isset($filters['payment_date']) && $filters['payment_date'] !== '')
			{
			$sql .= " AND DATE(FinancialOperationPayment.payment_date) = :payment_date";
			$params['payment_date'] = $this->normalizeDate($filters['payment_date']);
			}

		if (isset($filters['due_date_start']) && $filters['due_date_start'] !== '')
			{
			$sql .= " AND FinancialOperationPayment.due_date >= :due_date_start";
			$params['due_date_start'] = $this->normalizeDate($filters['due_date_start']);
			}

		if (isset($filters['due_date_end']) && $filters['due_date_end'] !== '')
			{
			$sql .= " AND FinancialOperationPayment.due_date <= :due_date_end";
			$params['due_date_end'] = $this->normalizeDate($filters['due_date_end']);
			}

		if (
			(!isset($filters['due_date_start']) || $filters['due_date_start'] === '')
			&& (!isset($filters['due_date_end']) || $filters['due_date_end'] === '')
			&& isset($filters['due_date'])
			&& $filters['due_date'] !== ''
		)
			{
			$sql .= " AND FinancialOperationPayment.due_date = :due_date";
			$params['due_date'] = $this->normalizeDate($filters['due_date']);
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

	/**
	 * Normalizes date input to `Y-m-d`.
	 *
	 * @param mixed $value Raw date value.
	 * @return string|null
	 */
	private function normalizeDate($value): ?string
		{
		$value = trim((string) $value);
		if ($value === '')
			{
			return null;
			}

		foreach (['!Y-m-d', '!d/m/Y'] as $format)
			{
			$date = \DateTimeImmutable::createFromFormat($format, $value);
			$errors = \DateTimeImmutable::getLastErrors();
			$hasErrors = is_array($errors) && (
				($errors['warning_count'] ?? 0) > 0
				|| ($errors['error_count'] ?? 0) > 0
			);
			if ($date !== false && !$hasErrors)
				{
				return $date->format('Y-m-d');
				}
			}

		return $value;
		}

	/**
	 * Normalizes datetime-local input to `Y-m-d H:i:s`.
	 *
	 * @param mixed $value Raw datetime value.
	 * @return string|null
	 */
	private function normalizeDateTime($value): ?string
		{
		$value = trim((string) $value);
		if ($value === '')
			{
			return null;
			}

		$value = str_replace('T', ' ', $value);
		if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value))
			{
			$value .= ':00';
			}

		return $value;
		}

	}

?>
