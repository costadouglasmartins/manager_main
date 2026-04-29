<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\FinancialOperationCategory;

class FinancialOperationCategoryController extends Controller
	{
	/**
	 * Index method.
	 */
	public function index()
		{
		$this->requirePermission('financial_operation_category.index');

		$category = new FinancialOperationCategory();

		$filters = [
			'id'          => isset($_GET['id'])          ? trim($_GET['id'])          : '',
			'name'        => isset($_GET['name'])        ? trim($_GET['name'])        : '',
			'description' => isset($_GET['description']) ? trim($_GET['description']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		$categories = $category->search($filters, $perPage, $offset);
		$total = $category->count($filters);
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
				'data'       => $categories,
			]);
			}

		$this->view('financial_operations_categories/index', [
			'financial_operations_categories' => $categories,
			'title'                           => 'Lista de categorias financeiras',
			'filters'                         => $filters,
			'page'                            => $page,
			'totalPages'                      => $totalPages,
			'total'                           => $total,
		]);
		}

	/**
	 * Create method.
	 */
	public function create()
		{
		$this->requirePermission('financial_operation_category.create');
		$this->view('financial_operations_categories/create', ['title' => 'Nova categoria financeira']);
		}

	/**
	 * Store method.
	 */
	public function store()
		{
		$this->requirePermission('financial_operation_category.store');
		$category = new FinancialOperationCategory();
		$category->create($_POST);
		$this->redirect('financial_operation_category');
		}

	/**
	 * Edit method.
	 *
	 * @param int|string $id Category ID.
	 */
	public function edit($id)
		{
		$this->requirePermission('financial_operation_category.edit');
		$category = new FinancialOperationCategory();
		$data = $category->find($id);
		$this->view('financial_operations_categories/edit', [
			'title'                        => 'Editar categoria financeira',
			'financial_operation_category' => $data,
		]);
		}

	/**
	 * Update method.
	 *
	 * @param int|string $id Category ID.
	 */
	public function update($id)
		{
		$this->requirePermission('financial_operation_category.update');
		$category = new FinancialOperationCategory();
		$category->update($id, $_POST);
		$this->redirect('financial_operation_category');
		}

	/**
	 * Informations method.
	 *
	 * @param int|string $id Category ID.
	 */
	public function informations($id)
		{
		$this->requirePermission('financial_operation_category.informations');
		$category = new FinancialOperationCategory();
		$data = $category->findAllByFinancialOperationCategoryId($id);
		$this->view('financial_operations_categories/informations', [
			'title'                        => 'Informações sobre a categoria financeira',
			'financial_operation_category' => $data['financial_operation_category'],
		]);
		}

	/**
	 * Delete method.
	 *
	 * @param int|string $id Category ID.
	 */
	public function delete($id)
		{
		$this->requirePermission('financial_operation_category.delete');

		if ($_SERVER['REQUEST_METHOD'] === 'POST')
			{
			$category = new FinancialOperationCategory();
			try
				{
				$category->delete($id);
				$this->redirect('financial_operation_category');
				}
			catch (\Exception $e)
				{
				$this->redirect('financial_operation_category');
				}
			}
		}
	}

?>
