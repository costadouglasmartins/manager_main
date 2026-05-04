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
			'id'                     => isset($_GET['id'])                     ? trim($_GET['id'])                     : '',
			'financial_operation_id' => isset($_GET['financial_operation_id']) ? trim($_GET['financial_operation_id']) : '',
			'type'                   => isset($_GET['type'])                   ? trim($_GET['type'])                   : '',
			'payment_date'           => isset($_GET['payment_date'])           ? trim($_GET['payment_date'])           : '',
			'due_date'               => isset($_GET['due_date'])               ? trim($_GET['due_date'])               : '',
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
		$payment->create($_POST);
		$this->redirect('financial_operation_payment');
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
		$payment->update($id, $_POST);
		$this->redirect('financial_operation_payment');
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
				$this->redirect($redirectPath);
				}
			catch (\Exception $e)
				{
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
	}

?>
