<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\FinancialOperation;
use App\Models\FinancialOperationCategory;
use App\Models\FinancialOperationPayment;
use App\Models\FinancialOperationSubcategory;

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
		$subcategory = new FinancialOperationSubcategory();

		$filters = [
			'id'                                 => isset($_GET['id'])                                 ? trim($_GET['id'])                                 : '',
			'financial_operation_category_id'    => isset($_GET['financial_operation_category_id'])    ? trim($_GET['financial_operation_category_id'])    : '',
			'category_name'                      => isset($_GET['category_name'])                      ? trim($_GET['category_name'])                      : '',
			'financial_operation_subcategory_id' => isset($_GET['financial_operation_subcategory_id']) ? trim($_GET['financial_operation_subcategory_id']) : '',
			'subcategory_name'                   => isset($_GET['subcategory_name'])                   ? trim($_GET['subcategory_name'])                   : '',
			'user_id'                            => isset($_GET['user_id'])                            ? trim($_GET['user_id'])                            : '',
			'user_name'                          => isset($_GET['user_name'])                          ? trim($_GET['user_name'])                          : '',
			'type'                               => isset($_GET['type'])                               ? trim($_GET['type'])                               : '',
			'total_value'                        => isset($_GET['total_value'])                        ? trim($_GET['total_value'])                        : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$sort = sortRequest(FinancialOperation::sortableColumns(), 'id', 'desc');

		$financialOperations = $financialOperation->search($filters, $perPage, $offset, $sort);
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
				'sort'       => $sort,
				'data'       => $financialOperations,
			]);
			}

		$this->view('financial_operations/index', [
			'financial_operations' => $financialOperations,
			'title'                => 'Lista de operações financeiras',
			'filters'              => $filters,
			'categories'           => $category->getAll(),
			'subcategories'        => $subcategory->getAll(),
			'types'                => FinancialOperation::typeOptions(),
			'page'                 => $page,
			'totalPages'           => $totalPages,
			'total'                => $total,
			'sort'                 => $sort,
		]);
		}

	/**
	 * Create method
	 */
	public function create()
		{
		$this->requirePermission('financial_operation.create');

		$category = new FinancialOperationCategory();
		$subcategory = new FinancialOperationSubcategory();
		$this->view('financial_operations/create', [
			'title'         => 'Nova operação financeira',
			'categories'    => $category->getAll(),
			'subcategories' => $subcategory->getAll(),
			'types'         => FinancialOperation::typeOptions(),
			'paymentTypes'  => FinancialOperationPayment::typeOptions(),
			'sessionUser'   => $_SESSION['manager_main']['user'] ?? [],
		]);
		}

	/**
	 * Annual sheet method.
	 */
	public function sheet()
		{
		$this->requirePermission('financial_operation.index');

		$minYear = 2026;
		$maxYear = $minYear + 5;
		$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
		if ($year < $minYear)
			{
			$year = $minYear;
			}
		if ($year > $maxYear)
			{
			$year = $maxYear;
			}

		$financialOperation = new FinancialOperation();
		$category = new FinancialOperationCategory();
		$subcategory = new FinancialOperationSubcategory();
		$sheet = $financialOperation->getAnnualSheet($year);

		if ($this->wantsJson())
			{
			$this->json($sheet);
			}

		$this->view('financial_operations/sheet', [
			'title'       => 'Planilha financeira anual',
			'sheet'       => $sheet,
			'yearOptions' => range($minYear, $maxYear),
			'categories'  => $category->getAll(),
			'subcategories' => $subcategory->getAll(),
			'types'       => FinancialOperation::typeOptions(),
			'paymentTypes' => FinancialOperationPayment::typeOptions(),
			'sessionUser' => $_SESSION['manager_main']['user'] ?? [],
		]);
		}

	/**
	 * Returns operations represented by an annual sheet cell.
	 */
	public function sheetOperations()
		{
		$this->requirePermission('financial_operation.edit');

		$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
		$type = isset($_GET['type']) ? (string) $_GET['type'] : '';
		$categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
		$month = isset($_GET['month']) && $_GET['month'] !== '' ? (int) $_GET['month'] : null;

		if (!array_key_exists($type, FinancialOperation::typeOptions()) || $categoryId <= 0)
			{
			$this->json([
				'error' => 'invalid_filters',
				'message' => 'Invalid annual sheet filters.',
			], 422);
			}

		if ($month !== null && ($month < 1 || $month > 12))
			{
			$this->json([
				'error' => 'invalid_month',
				'message' => 'Invalid annual sheet month.',
			], 422);
			}

		$financialOperation = new FinancialOperation();
		$this->json([
			'data' => $financialOperation->findAnnualSheetEntries($year, $type, $categoryId, $month),
		]);
		}

	/**
	 * Updates an operation from the annual sheet modal.
	 *
	 * @param int|string $id Financial operation ID.
	 */
	public function sheetUpdateOperation($id)
		{
		$this->requirePermission('financial_operation.update');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->json([
				'error' => 'method_not_allowed',
				'message' => 'Method not allowed.',
			], 405);
			}

		if ((int) ($_POST['payment_id'] ?? 0) > 0)
			{
			$this->requirePermission('financial_operation_payment.update');
			}

		$financialOperation = new FinancialOperation();
		$data = $_POST;
		$data['user_id'] = (int) ($_SESSION['manager_main']['user']['id'] ?? 0);

		try
			{
			$financialOperation->updateFromSheetEntry($id, $data);
			$this->json([
				'success' => true,
				'message' => 'Financial operation updated.',
			]);
			}
		catch (\InvalidArgumentException $e)
			{
			$this->json([
				'error' => 'validation_error',
				'message' => $e->getMessage(),
			], 422);
			}
		catch (\Exception $e)
			{
			$this->json([
				'error' => 'update_failed',
				'message' => 'Unable to update the financial operation.',
			], 500);
			}
		}

	/**
	 * Store method
	 */
	public function store()
		{
		$this->requirePermission('financial_operation.store');
		$financialOperation = new FinancialOperation();
		$data = $_POST;
		$data['user_id'] = (int) ($_SESSION['manager_main']['user']['id'] ?? 0);

		try
			{
			$financialOperation->create($data);
			setFlashMessage('success', 'Operação financeira cadastrada com sucesso.');
			$this->redirect('financial_operation');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível cadastrar a operação financeira. Verifique os dados e tente novamente.');
			$this->redirect('financial_operation/create');
			}
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
		$subcategory = new FinancialOperationSubcategory();
		$data = $financialOperation->find($id);

		$this->view('financial_operations/edit', [
			'title'               => 'Editar operação financeira',
			'financial_operation' => $data,
			'categories'          => $category->getAll(),
			'subcategories'       => $subcategory->getAll(),
			'types'               => FinancialOperation::typeOptions(),
			'paymentTypes'        => FinancialOperationPayment::typeOptions(),
			'sessionUser'         => $_SESSION['manager_main']['user'] ?? [],
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
		$data = $_POST;
		$data['user_id'] = (int) ($_SESSION['manager_main']['user']['id'] ?? 0);

		try
			{
			$financialOperation->update($id, $data);
			setFlashMessage('success', 'Operação financeira atualizada com sucesso.');
			$this->redirect('financial_operation');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível atualizar a operação financeira. Verifique os dados e tente novamente.');
			$this->redirect('financial_operation/edit/' . $id);
			}
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
	 * Recalculates the operation total from its linked payments.
	 *
	 * @param int|string $id Financial operation ID.
	 */
	public function recalculateTotal($id)
		{
		$this->requirePermission('financial_operation.update');

		$operationId = (int) $id;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $operationId <= 0)
			{
			$this->redirect($operationId > 0 ? 'financial_operation/informations/' . $operationId : 'financial_operation');
			}

		$financialOperation = new FinancialOperation();
		try
			{
			$financialOperation->recalculateTotalFromPayments($operationId);
			}
		catch (\Exception $e)
			{
			// Keep the user on the operation screen even if the recalculation fails.
			}

		$this->redirect('financial_operation/informations/' . $operationId);
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
				setFlashMessage('success', 'Operação financeira excluída com sucesso.');
				$this->redirect('financial_operation');
				}
			catch (\Exception $e)
				{
				setFlashMessage('danger', 'Não foi possível excluir a operação financeira.');
				$this->redirect('financial_operation');
				}
			}
		}

	}

?>
