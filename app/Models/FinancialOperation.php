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
	 * Returns the month labels used by annual reports.
	 *
	 * @return array<int, string>
	 */
	public static function monthOptions(): array
		{
		return [
			1  => 'Janeiro',
			2  => 'Fevereiro',
			3  => 'Março',
			4  => 'Abril',
			5  => 'Maio',
			6  => 'Junho',
			7  => 'Julho',
			8  => 'Agosto',
			9  => 'Setembro',
			10 => 'Outubro',
			11 => 'Novembro',
			12 => 'Dezembro',
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
			       Category.description AS category_description,
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
			$userId = (int) ($data['user_id'] ?? 0);
			if ($userId <= 0)
				{
				throw new \InvalidArgumentException('Authenticated user is required to create a financial operation.');
				}

			$installments = $this->buildMonthlyInstallments($data);
			$totalValue = !empty($installments)
				? $this->calculateInstallmentTotal($installments)
				: $this->normalizeMoney($data['total_value'] ?? '0');

			$sql = "INSERT INTO financial_operations
						(`financial_operation_category_id`, `type`, `total_value`, `user_id`, `created`, `modified`)
					VALUES
						(:financial_operation_category_id, :type, :total_value, :user_id, :created, :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'financial_operation_category_id' => (int) $data['financial_operation_category_id'],
				'type'                            => $data['type'],
				'total_value'                     => $totalValue,
				'user_id'                         => $userId,
				'created'                         => date('Y-m-d H:i:s'),
				'modified'                        => date('Y-m-d H:i:s'),
			]);

			$financialOperationId = $this->db->lastInsertId();
			$this->insertInstallments((int) $financialOperationId, $installments);
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
			       Category.description AS category_description,
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
		$userId = (int) ($data['user_id'] ?? 0);
		if ($userId <= 0)
			{
			throw new \InvalidArgumentException('Authenticated user is required to update a financial operation.');
			}

		try
			{
			$this->db->beginTransaction();
			$installments = $this->buildMonthlyInstallments($data);
			$totalValue = !empty($installments)
				? $this->calculateInstallmentTotal($installments)
				: $this->normalizeMoney($data['total_value'] ?? '0');

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
				'total_value'                     => $totalValue,
				'user_id'                         => $userId,
				'modified'                        => date('Y-m-d H:i:s'),
				'id'                              => $id,
			]);

			if (!empty($installments))
				{
				$this->replaceInstallments((int) $id, $installments);
				}

			$this->db->commit();
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Updates an operation and, when present, the payment selected in the annual sheet.
	 *
	 * @param int|string $id Financial operation ID.
	 * @param array<string, mixed> $data Data to save.
	 * @return void
	 */
	public function updateFromSheetEntry($id, array $data): void
		{
		$userId = (int) ($data['user_id'] ?? 0);
		if ($userId <= 0)
			{
			throw new \InvalidArgumentException('Authenticated user is required to update a financial operation.');
			}

		$categoryId = (int) ($data['financial_operation_category_id'] ?? 0);
		if ($categoryId <= 0)
			{
			throw new \InvalidArgumentException('Financial operation category is required.');
			}

		$operationType = (string) ($data['type'] ?? '');
		if (!array_key_exists($operationType, self::typeOptions()))
			{
			throw new \InvalidArgumentException('Financial operation type is invalid.');
			}

		try
			{
			$this->db->beginTransaction();

			$paymentId = (int) ($data['payment_id'] ?? 0);
			$installments = $this->buildMonthlyInstallments($data);
			$totalValue = !empty($installments)
				? $this->calculateInstallmentTotal($installments)
				: $this->normalizeMoney($data['total_value'] ?? '0');

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
				'financial_operation_category_id' => $categoryId,
				'type'                            => $operationType,
				'total_value'                     => $totalValue,
				'user_id'                         => $userId,
				'modified'                        => date('Y-m-d H:i:s'),
				'id'                              => $id,
			]);

			if (!empty($installments))
				{
				$this->replaceInstallments((int) $id, $installments);
				$this->db->commit();
				return;
				}

			if ($paymentId > 0)
				{
				$this->updateSheetPayment((int) $id, $paymentId, $data);
				$this->syncOperationTotalFromPayments((int) $id);
				}

			$this->db->commit();
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
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
		               Category.description AS category_description,
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
	 * Returns the annual spreadsheet data grouped by month and category.
	 *
	 * @param int $year Reference year.
	 * @return array<string, mixed>
	 */
	public function getAnnualSheet(int $year): array
		{
		$year = max(2000, min(2100, $year));
		$months = self::monthOptions();
		$incomeRows = [];
		$expenseRows = [];
		$incomeTotals = $this->emptyMonthCents();
		$expenseTotals = $this->emptyMonthCents();

		$stmt = $this->db->prepare("
			SELECT fo.type,
			       fo.financial_operation_category_id AS category_id,
			       Category.name AS category_name,
			       Category.description AS category_description,
			       MONTH(fop.due_date) AS operation_month,
			       fop.value AS amount
			FROM financial_operations AS fo
			INNER JOIN financial_operations_payments AS fop ON fop.financial_operation_id = fo.id
			LEFT JOIN financial_operations_categories AS Category ON Category.id = fo.financial_operation_category_id
			WHERE fop.due_date IS NOT NULL
			  AND YEAR(fop.due_date) = :payment_year

			UNION ALL

			SELECT fo.type,
			       fo.financial_operation_category_id AS category_id,
			       Category.name AS category_name,
			       Category.description AS category_description,
			       MONTH(COALESCE(fo.created, fo.modified)) AS operation_month,
			       fo.total_value AS amount
			FROM financial_operations AS fo
			LEFT JOIN financial_operations_categories AS Category ON Category.id = fo.financial_operation_category_id
			WHERE NOT EXISTS (
			        SELECT 1
			        FROM financial_operations_payments AS fop
			        WHERE fop.financial_operation_id = fo.id
			      )
			  AND COALESCE(fo.created, fo.modified) IS NOT NULL
			  AND YEAR(COALESCE(fo.created, fo.modified)) = :operation_year
			ORDER BY type ASC, category_name ASC, category_description ASC, operation_month ASC
		");
		$stmt->execute([
			'payment_year'   => $year,
			'operation_year' => $year,
		]);

		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$type = (string) ($row['type'] ?? '');
			$month = (int) ($row['operation_month'] ?? 0);
			if (!in_array($type, ['CREDIT', 'DEBIT'], true) || $month < 1 || $month > 12)
				{
				continue;
				}

			$cents = $this->moneyToCents($row['amount'] ?? 0);
			if ($cents === 0)
				{
				continue;
				}

			$targetRows =& $incomeRows;
			$targetTotals =& $incomeTotals;
			if ($type === 'DEBIT')
				{
				$targetRows =& $expenseRows;
				$targetTotals =& $expenseTotals;
				}

			$key = $this->buildSheetCategoryKey($row);
			if (!isset($targetRows[$key]))
				{
				$targetRows[$key] = [
					'category_id' => (int) ($row['category_id'] ?? 0),
					'label'       => $this->buildSheetCategoryLabel($row),
					'months'      => $this->emptyMonthCents(),
					'total'       => 0,
				];
				}

			$targetRows[$key]['months'][$month] += $cents;
			$targetRows[$key]['total'] += $cents;
			$targetTotals[$month] += $cents;
			}

		$balanceTotals = $this->emptyMonthCents();
		$expensePercentages = [];
		foreach (array_keys($months) as $month)
			{
			$balanceTotals[$month] = $incomeTotals[$month] - $expenseTotals[$month];
			$expensePercentages[$month] = $incomeTotals[$month] > 0
				? ($expenseTotals[$month] / $incomeTotals[$month]) * 100
				: null;
			}

		uasort($incomeRows, static fn ($a, $b) => strcasecmp($a['label'], $b['label']));
		uasort($expenseRows, static fn ($a, $b) => strcasecmp($a['label'], $b['label']));

		$incomeTotalCents = array_sum($incomeTotals);
		$expenseTotalCents = array_sum($expenseTotals);

		return [
			'year'               => $year,
			'months'             => $months,
			'incomeRows'         => array_values($incomeRows),
			'expenseRows'        => array_values($expenseRows),
			'incomeTotals'       => ['months' => $incomeTotals, 'total' => $incomeTotalCents],
			'expenseTotals'      => ['months' => $expenseTotals, 'total' => $expenseTotalCents],
			'balanceTotals'      => ['months' => $balanceTotals, 'total' => $incomeTotalCents - $expenseTotalCents],
			'expensePercentages' => [
				'months' => $expensePercentages,
				'total'  => $incomeTotalCents > 0 ? ($expenseTotalCents / $incomeTotalCents) * 100 : null,
			],
		];
		}

	/**
	 * Finds the operations represented by an annual sheet cell.
	 *
	 * @param int $year Reference year.
	 * @param string $type Operation type.
	 * @param int $categoryId Category ID.
	 * @param int|null $month Optional month number.
	 * @return array<int, array<string, mixed>>
	 */
	public function findAnnualSheetEntries(int $year, string $type, int $categoryId, ?int $month = null): array
		{
		$year = max(2000, min(2100, $year));
		$categoryId = max(0, $categoryId);
		$month = $month !== null && $month >= 1 && $month <= 12 ? $month : null;

		if (!array_key_exists($type, self::typeOptions()) || $categoryId <= 0)
			{
			return [];
			}

		$paymentMonthFilter = $month !== null ? ' AND MONTH(fop.due_date) = :payment_month' : '';
		$operationMonthFilter = $month !== null ? ' AND MONTH(COALESCE(fo.created, fo.modified)) = :operation_month' : '';

		$stmt = $this->db->prepare("
			SELECT fo.id AS operation_id,
			       fo.financial_operation_category_id AS category_id,
			       fo.type AS operation_type,
			       fo.total_value AS operation_total_value,
			       fo.user_id,
			       fo.created AS operation_created,
			       fo.modified AS operation_modified,
			       Category.name AS category_name,
			       Category.description AS category_description,
			       User.username,
			       Person.name AS user_name,
			       fop.id AS payment_id,
			       fop.type AS payment_type,
			       fop.value AS payment_value,
			       fop.payment_date,
			       fop.due_date,
			       fop.due_date AS sheet_date,
			       'payment' AS entry_source
			FROM financial_operations AS fo
			INNER JOIN financial_operations_payments AS fop ON fop.financial_operation_id = fo.id
			LEFT JOIN financial_operations_categories AS Category ON Category.id = fo.financial_operation_category_id
			INNER JOIN users AS User ON User.id = fo.user_id
			LEFT JOIN people AS Person ON Person.id = User.person_id
			WHERE fop.due_date IS NOT NULL
			  AND YEAR(fop.due_date) = :payment_year
			  AND fo.type = :payment_type_filter
			  AND fo.financial_operation_category_id = :payment_category_id
			  {$paymentMonthFilter}

			UNION ALL

			SELECT fo.id AS operation_id,
			       fo.financial_operation_category_id AS category_id,
			       fo.type AS operation_type,
			       fo.total_value AS operation_total_value,
			       fo.user_id,
			       fo.created AS operation_created,
			       fo.modified AS operation_modified,
			       Category.name AS category_name,
			       Category.description AS category_description,
			       User.username,
			       Person.name AS user_name,
			       NULL AS payment_id,
			       NULL AS payment_type,
			       NULL AS payment_value,
			       NULL AS payment_date,
			       NULL AS due_date,
			       DATE(COALESCE(fo.created, fo.modified)) AS sheet_date,
			       'operation' AS entry_source
			FROM financial_operations AS fo
			LEFT JOIN financial_operations_categories AS Category ON Category.id = fo.financial_operation_category_id
			INNER JOIN users AS User ON User.id = fo.user_id
			LEFT JOIN people AS Person ON Person.id = User.person_id
			WHERE NOT EXISTS (
			        SELECT 1
			        FROM financial_operations_payments AS fop
			        WHERE fop.financial_operation_id = fo.id
			      )
			  AND COALESCE(fo.created, fo.modified) IS NOT NULL
			  AND YEAR(COALESCE(fo.created, fo.modified)) = :operation_year
			  AND fo.type = :operation_type_filter
			  AND fo.financial_operation_category_id = :operation_category_id
			  {$operationMonthFilter}
			ORDER BY sheet_date ASC, operation_id ASC, payment_id ASC
		");

		$params = [
			'payment_year'           => $year,
			'payment_type_filter'    => $type,
			'payment_category_id'    => $categoryId,
			'operation_year'         => $year,
			'operation_type_filter'  => $type,
			'operation_category_id'  => $categoryId,
		];
		if ($month !== null)
			{
			$params['payment_month'] = $month;
			$params['operation_month'] = $month;
			}

		$stmt->execute($params);

		return array_map(
			fn ($row) => $this->formatAnnualSheetEntry($row),
			$stmt->fetchAll(PDO::FETCH_ASSOC)
		);
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
	 * Builds monthly installment rows for a date range of up to five years.
	 *
	 * @param array<string, mixed> $data Operation form data.
	 * @return array<int, array{type: string, value: string, due_date: string}>
	 */
	private function buildMonthlyInstallments(array $data): array
		{
		if (empty($data['generate_installments']))
			{
			return [];
			}

		$startDate = $this->parseDate($data['recurrence_start_date'] ?? null);
		$endDate = $this->parseDate($data['recurrence_end_date'] ?? null);
		if ($startDate === null || $endDate === null || $endDate < $startDate)
			{
			return [];
			}

		$maxEndDate = $startDate->modify('+5 years');
		if ($endDate > $maxEndDate)
			{
			$endDate = $maxEndDate;
			}

		$paymentType = (string) ($data['payment_type'] ?? 'CASH');
		if (!array_key_exists($paymentType, FinancialOperationPayment::typeOptions()))
			{
			$paymentType = 'CASH';
			}

		$installmentValue = $this->normalizeMoney($data['installment_value'] ?? '0');
		if ($this->moneyToCents($installmentValue) <= 0)
			{
			return [];
			}

		$installments = [];
		$preferredDay = (int) $startDate->format('d');

		for ($monthOffset = 0; $monthOffset <= 60; $monthOffset++)
			{
			$candidateDate = $this->addMonthsKeepingPreferredDay($startDate, $monthOffset, $preferredDay);
			if ($candidateDate > $endDate)
				{
				break;
				}

			$dueDate = $this->moveToBusinessDay($candidateDate);
			$installments[] = [
				'type'     => $paymentType,
				'value'    => $installmentValue,
				'due_date' => $dueDate->format('Y-m-d'),
			];
			}

		return $installments;
		}

	/**
	 * Inserts generated installments for a financial operation.
	 *
	 * @param int $financialOperationId Financial operation ID.
	 * @param array<int, array{type: string, value: string, due_date: string}> $installments Installments to save.
	 * @return void
	 */
	private function insertInstallments(int $financialOperationId, array $installments): void
		{
		if (empty($installments))
			{
			return;
			}

		$stmt = $this->db->prepare("
			INSERT INTO financial_operations_payments
				(`financial_operation_id`, `type`, `value`, `payment_date`, `due_date`, `created`, `modified`)
			VALUES
				(:financial_operation_id, :type, :value, :payment_date, :due_date, :created, :modified)
		");

		$now = date('Y-m-d H:i:s');
		foreach ($installments as $installment)
			{
			$stmt->execute([
				'financial_operation_id' => $financialOperationId,
				'type'                   => $installment['type'],
				'value'                  => $installment['value'],
				'payment_date'           => null,
				'due_date'               => $installment['due_date'],
				'created'                => $now,
				'modified'               => $now,
			]);
			}
		}

	/**
	 * Replaces all payments linked to a financial operation.
	 *
	 * @param int $financialOperationId Financial operation ID.
	 * @param array<int, array{type: string, value: string, due_date: string}> $installments New installments.
	 * @return void
	 */
	private function replaceInstallments(int $financialOperationId, array $installments): void
		{
		$stmt = $this->db->prepare("DELETE FROM financial_operations_payments WHERE financial_operation_id = :id");
		$stmt->execute(['id' => $financialOperationId]);

		$this->insertInstallments($financialOperationId, $installments);
		}

	/**
	 * Updates one payment selected from the annual sheet modal.
	 *
	 * @param int $financialOperationId Financial operation ID.
	 * @param int $paymentId Payment ID.
	 * @param array<string, mixed> $data Data to save.
	 * @return void
	 */
	private function updateSheetPayment(int $financialOperationId, int $paymentId, array $data): void
		{
		$stmt = $this->db->prepare("
			SELECT COUNT(*)
			FROM financial_operations_payments
			WHERE id = :id
			  AND financial_operation_id = :financial_operation_id
		");
		$stmt->execute([
			'id'                     => $paymentId,
			'financial_operation_id' => $financialOperationId,
		]);
		if ((int) $stmt->fetchColumn() < 1)
			{
			throw new \InvalidArgumentException('Payment was not found for this financial operation.');
			}

		$paymentType = (string) ($data['payment_type'] ?? '');
		if (!array_key_exists($paymentType, FinancialOperationPayment::typeOptions()))
			{
			throw new \InvalidArgumentException('Payment type is invalid.');
			}

		$dueDate = $this->normalizeDate($data['due_date'] ?? null);
		if ($dueDate === null)
			{
			throw new \InvalidArgumentException('Payment due date is required.');
			}

		$stmt = $this->db->prepare("
			UPDATE financial_operations_payments
			SET
				`type`         = :type,
				`value`        = :value,
				`payment_date` = :payment_date,
				`due_date`     = :due_date,
				`modified`     = :modified
			WHERE id = :id
			  AND financial_operation_id = :financial_operation_id
		");
		$stmt->execute([
			'type'                   => $paymentType,
			'value'                  => $this->normalizeMoney($data['payment_value'] ?? '0'),
			'payment_date'           => $this->normalizeDateTime($data['payment_date'] ?? null),
			'due_date'               => $dueDate,
			'modified'               => date('Y-m-d H:i:s'),
			'id'                     => $paymentId,
			'financial_operation_id' => $financialOperationId,
		]);
		}

	/**
	 * Synchronizes operation total with its saved payments.
	 *
	 * @param int $financialOperationId Financial operation ID.
	 * @return void
	 */
	private function syncOperationTotalFromPayments(int $financialOperationId): void
		{
		$stmt = $this->db->prepare("
			SELECT COALESCE(SUM(value), 0)
			FROM financial_operations_payments
			WHERE financial_operation_id = :financial_operation_id
		");
		$stmt->execute(['financial_operation_id' => $financialOperationId]);
		$total = number_format((float) $stmt->fetchColumn(), 2, '.', '');

		$stmt = $this->db->prepare("
			UPDATE financial_operations
			SET total_value = :total_value,
			    modified = :modified
			WHERE id = :id
		");
		$stmt->execute([
			'total_value' => $total,
			'modified'    => date('Y-m-d H:i:s'),
			'id'          => $financialOperationId,
		]);
		}

	/**
	 * Formats an annual sheet entry for JSON responses.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function formatAnnualSheetEntry(array $row): array
		{
		$paymentDate = $row['payment_date'] ?? null;
		$paymentDateInput = '';
		if (!empty($paymentDate))
			{
			$paymentDateInput = date('Y-m-d\TH:i', strtotime((string) $paymentDate));
			}

		return [
			'operation_id'                    => (int) ($row['operation_id'] ?? 0),
			'financial_operation_category_id' => (int) ($row['category_id'] ?? 0),
			'category_name'                   => (string) ($row['category_name'] ?? ''),
			'category_description'            => (string) ($row['category_description'] ?? ''),
			'operation_type'                  => (string) ($row['operation_type'] ?? ''),
			'operation_total_value'           => number_format((float) ($row['operation_total_value'] ?? 0), 2, '.', ''),
			'user_id'                         => (int) ($row['user_id'] ?? 0),
			'username'                        => (string) ($row['username'] ?? ''),
			'user_name'                       => (string) ($row['user_name'] ?? ''),
			'payment_id'                      => isset($row['payment_id']) ? (int) $row['payment_id'] : null,
			'payment_type'                    => $row['payment_type'] !== null ? (string) $row['payment_type'] : '',
			'payment_value'                   => $row['payment_value'] !== null ? number_format((float) $row['payment_value'], 2, '.', '') : '',
			'payment_date'                    => $paymentDate !== null ? (string) $paymentDate : '',
			'payment_date_input'              => $paymentDateInput,
			'due_date'                        => $row['due_date'] !== null ? (string) $row['due_date'] : '',
			'sheet_date'                      => (string) ($row['sheet_date'] ?? ''),
			'entry_source'                    => (string) ($row['entry_source'] ?? ''),
		];
		}

	/**
	 * Calculates the operation total from generated installments.
	 *
	 * @param array<int, array{type: string, value: string, due_date: string}> $installments Installments.
	 * @return string
	 */
	private function calculateInstallmentTotal(array $installments): string
		{
		$totalCents = 0;
		foreach ($installments as $installment)
			{
			$totalCents += $this->moneyToCents($installment['value']);
			}

		return number_format($totalCents / 100, 2, '.', '');
		}

	/**
	 * Returns an empty month map in cents.
	 *
	 * @return array<int, int>
	 */
	private function emptyMonthCents(): array
		{
		return array_fill_keys(array_keys(self::monthOptions()), 0);
		}

	/**
	 * Builds a stable spreadsheet category key.
	 *
	 * @param array<string, mixed> $row Aggregated row.
	 * @return string
	 */
	private function buildSheetCategoryKey(array $row): string
		{
		$categoryId = (int) ($row['category_id'] ?? 0);
		if ($categoryId > 0)
			{
			return (string) $categoryId;
			}

		return 'empty-' . md5($this->buildSheetCategoryLabel($row));
		}

	/**
	 * Builds the spreadsheet category label.
	 *
	 * @param array<string, mixed> $row Aggregated row.
	 * @return string
	 */
	private function buildSheetCategoryLabel(array $row): string
		{
		$name = trim((string) ($row['category_name'] ?? ''));
		$description = trim((string) ($row['category_description'] ?? ''));

		if ($name === '')
			{
			return 'Sem categoria';
			}

		return $description !== '' ? $name . ' - ' . $description : $name;
		}

	/**
	 * Parses a `Y-m-d` date input.
	 *
	 * @param mixed $value Raw date value.
	 * @return \DateTimeImmutable|null
	 */
	private function parseDate($value): ?\DateTimeImmutable
		{
		$value = trim((string) $value);
		if ($value === '')
			{
			return null;
			}

		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		$errors = \DateTimeImmutable::getLastErrors();
		$hasErrors = is_array($errors) && (
			($errors['warning_count'] ?? 0) > 0
			|| ($errors['error_count'] ?? 0) > 0
		);
		if ($date === false || $hasErrors)
			{
			return null;
			}

		return $date;
		}

	/**
	 * Adds months while preserving the preferred day when the month allows it.
	 *
	 * @param \DateTimeImmutable $date Base date.
	 * @param int $months Number of months to add.
	 * @param int $preferredDay Preferred day of month.
	 * @return \DateTimeImmutable
	 */
	private function addMonthsKeepingPreferredDay(\DateTimeImmutable $date, int $months, int $preferredDay): \DateTimeImmutable
		{
		$monthIndex = ((int) $date->format('n')) + $months;
		$year = ((int) $date->format('Y')) + intdiv($monthIndex - 1, 12);
		$month = (($monthIndex - 1) % 12) + 1;
		$firstDayOfMonth = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
		$day = min($preferredDay, (int) $firstDayOfMonth->format('t'));

		return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
		}

	/**
	 * Moves a date forward until it lands on a business day.
	 *
	 * @param \DateTimeImmutable $date Candidate date.
	 * @return \DateTimeImmutable
	 */
	private function moveToBusinessDay(\DateTimeImmutable $date): \DateTimeImmutable
		{
		while (!$this->isBusinessDay($date))
			{
			$date = $date->modify('+1 day');
			}

		return $date;
		}

	/**
	 * Checks weekdays and Brazilian national holidays.
	 *
	 * @param \DateTimeImmutable $date Date to check.
	 * @return bool
	 */
	private function isBusinessDay(\DateTimeImmutable $date): bool
		{
		if ((int) $date->format('N') >= 6)
			{
			return false;
			}

		return !in_array($date->format('Y-m-d'), $this->getBrazilianNationalHolidays((int) $date->format('Y')), true);
		}

	/**
	 * Returns Brazilian national holidays and common movable holidays.
	 *
	 * @param int $year Year.
	 * @return array<int, string>
	 */
	private function getBrazilianNationalHolidays(int $year): array
		{
		$fixedHolidays = [
			'01-01',
			'04-21',
			'05-01',
			'09-07',
			'10-12',
			'11-02',
			'11-15',
			'11-20',
			'12-25',
		];

		$holidays = array_map(
			static fn ($dayMonth) => sprintf('%04d-%s', $year, $dayMonth),
			$fixedHolidays
		);

		$easter = $this->getEasterDate($year);
		foreach ([-48, -47, -2, 60] as $offset)
			{
			$holidays[] = $easter->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('Y-m-d');
			}

		return $holidays;
		}

	/**
	 * Calculates Gregorian Easter Sunday.
	 *
	 * @param int $year Year.
	 * @return \DateTimeImmutable
	 */
	private function getEasterDate(int $year): \DateTimeImmutable
		{
		$a = $year % 19;
		$b = intdiv($year, 100);
		$c = $year % 100;
		$d = intdiv($b, 4);
		$e = $b % 4;
		$f = intdiv($b + 8, 25);
		$g = intdiv($b - $f + 1, 3);
		$h = (19 * $a + $b - $d - $g + 15) % 30;
		$i = intdiv($c, 4);
		$k = $c % 4;
		$l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
		$m = intdiv($a + 11 * $h + 22 * $l, 451);
		$month = intdiv($h + $l - 7 * $m + 114, 31);
		$day = (($h + $l - 7 * $m + 114) % 31) + 1;

		return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
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

	/**
	 * Normalizes date input to `Y-m-d`.
	 *
	 * @param mixed $value Raw date value.
	 * @return string|null
	 */
	private function normalizeDate($value): ?string
		{
		$value = trim((string) $value);
		return $value === '' ? null : $value;
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

		return str_replace('T', ' ', $value) . (strlen($value) === 16 ? ':00' : '');
		}

	/**
	 * Converts a money value to cents.
	 *
	 * @param mixed $value Money value.
	 * @return int
	 */
	private function moneyToCents($value): int
		{
		return (int) round((float) $this->normalizeMoney($value) * 100);
		}
	}

?>
