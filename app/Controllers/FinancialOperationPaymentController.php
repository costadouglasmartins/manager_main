<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\FinancialOperation;
use App\Models\FinancialOperationPayment;

class FinancialOperationPaymentController extends Controller
	{
	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('financial_operation_payment.index');

		$payment = new FinancialOperationPayment();

		$filters = [
			'id'                       => isset($_GET['id'])                       ? trim($_GET['id'])                       : '',
			'financial_operation_id'   => isset($_GET['financial_operation_id'])   ? trim($_GET['financial_operation_id'])   : '',
			'financial_operation_type' => isset($_GET['financial_operation_type']) ? trim($_GET['financial_operation_type']) : '',
			'type'                     => isset($_GET['type'])                     ? trim($_GET['type'])                     : '',
			'payment_date'             => $this->normalizeDateFilter($_GET['payment_date'] ?? ''),
			'due_date_start'           => $this->normalizeDateFilter($_GET['due_date_start'] ?? ''),
			'due_date_end'             => $this->normalizeDateFilter($_GET['due_date_end'] ?? ''),
			'due_date'                 => $this->normalizeDateFilter($_GET['due_date'] ?? ''),
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$sort = sortRequest(FinancialOperationPayment::sortableColumns(), 'id', 'desc');

		$payments = $payment->search($filters, $perPage, $offset, $sort);
		$total = $payment->count($filters);
		$totalPages = (int) ceil($total / $perPage);
		if ($totalPages < 1)
			{
			$totalPages = 1;
			}

		if ($this->wantsJson())
			{
			$this->json([
				'filters'    => $filters,
				'page'       => $page,
				'perPage'    => $perPage,
				'total'      => $total,
				'totalPages' => $totalPages,
				'sort'       => $sort,
				'data'       => $payments,
			]);
			}

		$this->view('financial_operations_payments/index', [
			'financial_operations_payments' => $payments,
			'title'                         => 'Lista de pagamentos financeiros',
			'filters'                       => $filters,
			'operationTypes'                => FinancialOperation::typeOptions(),
			'types'                         => FinancialOperationPayment::typeOptions(),
			'page'                          => $page,
			'totalPages'                    => $totalPages,
			'total'                         => $total,
			'sort'                          => $sort,
		]);
		}

	/**
	 * Create method
	 */
	public function create()
		{
		$this->requirePermission('financial_operation_payment.create');

		$financialOperation = new FinancialOperation();
		$this->view('financial_operations_payments/create', [
			'title'                => 'Novo pagamento financeiro',
			'financialOperations'  => $financialOperation->getAll(),
			'operationTypes'       => FinancialOperation::typeOptions(),
			'types'                => FinancialOperationPayment::typeOptions(),
		]);
		}

	/**
	 * Store method
	 */
	public function store()
		{
		$this->requirePermission('financial_operation_payment.store');
		$payment = new FinancialOperationPayment();

		try
			{
			$payment->create($_POST);
			setFlashMessage('success', 'Pagamento financeiro cadastrado com sucesso.');
			$this->redirect('financial_operation_payment');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível cadastrar o pagamento financeiro. Verifique os dados e tente novamente.');
			$this->redirect('financial_operation_payment/create');
			}
		}

	/**
	 * Edit method
	 *
	 * @param int|string $id Payment ID.
	 */
	public function edit($id)
		{
		$this->requirePermission('financial_operation_payment.edit');

		$payment = new FinancialOperationPayment();
		$financialOperation = new FinancialOperation();

		$this->view('financial_operations_payments/edit', [
			'title'                       => 'Editar pagamento financeiro',
			'financial_operation_payment' => $payment->find($id),
			'financialOperations'         => $financialOperation->getAll(),
			'operationTypes'              => FinancialOperation::typeOptions(),
			'types'                       => FinancialOperationPayment::typeOptions(),
		]);
		}

	/**
	 * Update method
	 *
	 * @param int|string $id Payment ID.
	 */
	public function update($id)
		{
		$this->requirePermission('financial_operation_payment.update');
		$payment = new FinancialOperationPayment();

		try
			{
			$payment->update($id, $_POST);
			setFlashMessage('success', 'Pagamento financeiro atualizado com sucesso.');
			$this->redirect('financial_operation_payment');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível atualizar o pagamento financeiro. Verifique os dados e tente novamente.');
			$this->redirect('financial_operation_payment/edit/' . $id);
			}
		}

	/**
	 * Informations method
	 *
	 * @param int|string $id Payment ID.
	 */
	public function informations($id)
		{
		$this->requirePermission('financial_operation_payment.informations');
		$payment = new FinancialOperationPayment();
		$data = $payment->findAllByFinancialOperationPaymentId($id);
		$this->view('financial_operations_payments/informations', [
			'title'                       => 'Informações sobre o pagamento financeiro',
			'financial_operation_payment' => $data['financial_operation_payment'],
			'operationTypes'              => FinancialOperation::typeOptions(),
			'types'                       => FinancialOperationPayment::typeOptions(),
		]);
		}

	/**
	 * Delete method
	 *
	 * @param int|string $id Payment ID.
	 */
	public function delete($id)
		{
		$this->requirePermission('financial_operation_payment.delete');
		$redirectPath = $this->deleteRedirectPath();

		if ($_SERVER['REQUEST_METHOD'] === 'POST')
			{
			$payment = new FinancialOperationPayment();
			try
				{
				$payment->delete($id);
				setFlashMessage('success', 'Pagamento financeiro excluído com sucesso.');
				$this->redirect($redirectPath);
				}
			catch (\Exception $e)
				{
				setFlashMessage('danger', 'Não foi possível excluir o pagamento financeiro.');
				$this->redirect($redirectPath);
				}
			}
		}

	/**
	 * Returns the safe redirect path to use after deleting a payment.
	 *
	 * @return string
	 */
	private function deleteRedirectPath(): string
		{
		$redirectPath = isset($_POST['redirect_to']) ? trim((string) $_POST['redirect_to']) : '';
		if ($redirectPath === '')
			{
			return 'financial_operation_payment';
			}

		if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $redirectPath) || strpos($redirectPath, '/') === 0 || strpos($redirectPath, '\\') !== false)
			{
			return 'financial_operation_payment';
			}

		return $redirectPath;
		}

	/**
	 * Normalizes date filters for HTML date inputs and SQL comparisons.
	 *
	 * @param mixed $value Date filter value.
	 * @return string
	 */
	private function normalizeDateFilter($value): string
		{
		$value = trim((string) $value);
		if ($value === '')
			{
			return '';
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

		return '';
		}
	}

?>
