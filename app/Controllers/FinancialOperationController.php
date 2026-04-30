<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\FinancialOperation;
use App\Models\FinancialOperationCategory;
use App\Models\FinancialOperationPayment;

class FinancialOperationController extends Controller
	{
	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('financial_operation.index');

		$financialOperation = new FinancialOperation();
		$category = new FinancialOperationCategory();

		$filters = [
			'id'                              => isset($_GET['id'])                              ? trim($_GET['id'])                              : '',
			'financial_operation_category_id' => isset($_GET['financial_operation_category_id']) ? trim($_GET['financial_operation_category_id']) : '',
			'category_name'                   => isset($_GET['category_name'])                   ? trim($_GET['category_name'])                   : '',
			'user_id'                         => isset($_GET['user_id'])                         ? trim($_GET['user_id'])                         : '',
			'user_name'                       => isset($_GET['user_name'])                       ? trim($_GET['user_name'])                       : '',
			'type'                            => isset($_GET['type'])                            ? trim($_GET['type'])                            : '',
			'total_value'                     => isset($_GET['total_value'])                     ? trim($_GET['total_value'])                     : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		$financialOperations = $financialOperation->search($filters, $perPage, $offset);
		$total = $financialOperation->count($filters);
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
				'data'       => $financialOperations,
			]);
			}

		$this->view('financial_operations/index', [
			'financial_operations' => $financialOperations,
			'title'                => 'Lista de operações financeiras',
			'filters'              => $filters,
			'categories'           => $category->getAll(),
			'types'                => FinancialOperation::typeOptions(),
			'page'                 => $page,
			'totalPages'           => $totalPages,
			'total'                => $total,
		]);
		}

	/**
	 * Create method
	 */
	public function create()
		{
		$this->requirePermission('financial_operation.create');

		$financialOperation = new FinancialOperation();
		$category = new FinancialOperationCategory();
		$this->view('financial_operations/create', [
			'title'      => 'Nova operação financeira',
			'categories' => $category->getAll(),
			'types'      => FinancialOperation::typeOptions(),
			'users'      => $financialOperation->getUserOptions(),
		]);
		}

	/**
	 * Store method
	 */
	public function store()
		{
		$this->requirePermission('financial_operation.store');
		$financialOperation = new FinancialOperation();
		$financialOperation->create($_POST);
		$this->redirect('financial_operation');
		}

	/**
	 * Edit method
	 *
	 * @param int|string $id Financial operation ID.
	 */
	public function edit($id)
		{
		$this->requirePermission('financial_operation.edit');

		$financialOperation = new FinancialOperation();
		$category = new FinancialOperationCategory();
		$data = $financialOperation->find($id);

		$this->view('financial_operations/edit', [
			'title'               => 'Editar operação financeira',
			'financial_operation' => $data,
			'categories'          => $category->getAll(),
			'types'               => FinancialOperation::typeOptions(),
			'users'               => $financialOperation->getUserOptions(),
		]);
		}

	/**
	 * Update method
	 *
	 * @param int|string $id Financial operation ID.
	 */
	public function update($id)
		{
		$this->requirePermission('financial_operation.update');
		$financialOperation = new FinancialOperation();
		$financialOperation->update($id, $_POST);
		$this->redirect('financial_operation');
		}

	/**
	 * Informations method
	 *
	 * @param int|string $id Financial operation ID.
	 */
	public function informations($id)
		{
		$this->requirePermission('financial_operation.informations');
		$financialOperation = new FinancialOperation();
		$payment = new FinancialOperationPayment();
		$data = $financialOperation->findAllByFinancialOperationId($id);
		$this->view('financial_operations/informations', [
			'title'                         => 'Informações sobre a operação financeira',
			'financial_operation'           => $data['financial_operation'],
			'financial_operation_payments'  => $payment->findByFinancialOperationId($id),
			'types'                         => FinancialOperation::typeOptions(),
			'paymentTypes'                  => FinancialOperationPayment::typeOptions(),
		]);
		}

	/**
	 * Delete method
	 *
	 * @param int|string $id Financial operation ID.
	 */
	public function delete($id)
		{
		$this->requirePermission('financial_operation.delete');

		if ($_SERVER['REQUEST_METHOD'] === 'POST')
			{
			$financialOperation = new FinancialOperation();
			try
				{
				$financialOperation->delete($id);
				$this->redirect('financial_operation');
				}
			catch (\Exception $e)
				{
				$this->redirect('financial_operation');
				}
			}
		}
	}

?>
