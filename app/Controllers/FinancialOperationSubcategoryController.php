<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\FinancialOperationCategory;
use App\Models\FinancialOperationSubcategory;

class FinancialOperationSubcategoryController extends Controller
	{
	/**
	 * Index method.
	 **/
	public function index()
		{
		$this->requirePermission('financial_operation_subcategory.index');

		$category = new FinancialOperationCategory();
		$subcategory = new FinancialOperationSubcategory();

		$filters = [
			'id'                              => isset($_GET['id'])                              ? trim($_GET['id'])                              : '',
			'financial_operation_category_id' => isset($_GET['financial_operation_category_id']) ? trim($_GET['financial_operation_category_id']) : '',
			'category_name'                   => isset($_GET['category_name'])                   ? trim($_GET['category_name'])                   : '',
			'name'                            => isset($_GET['name'])                            ? trim($_GET['name'])                            : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$sort = sortRequest(FinancialOperationSubcategory::sortableColumns(), 'id', 'desc');

		$subcategories = $subcategory->search($filters, $perPage, $offset, $sort);
		$total = $subcategory->count($filters);
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
				'data'       => $subcategories,
			]);
			}

		$this->view('financial_operations_subcategories/index', [
			'financial_operations_subcategories' => $subcategories,
			'title'                              => 'Lista de subcategorias financeiras',
			'filters'                            => $filters,
			'categories'                         => $category->getAll(),
			'page'                               => $page,
			'totalPages'                         => $totalPages,
			'total'                              => $total,
			'sort'                               => $sort,
		]);
		}

	/**
	 * Create method.
	 **/
	public function create()
		{
		$this->requirePermission('financial_operation_subcategory.create');
		$category = new FinancialOperationCategory();
		$this->view('financial_operations_subcategories/create', [
			'title'      => 'Nova subcategoria financeira',
			'categories' => $category->getAll(),
		]);
		}

	/**
	 * Store method.
	 **/
	public function store()
		{
		$this->requirePermission('financial_operation_subcategory.store');
		$subcategory = new FinancialOperationSubcategory();

		try
			{
			$subcategory->create($_POST);
			setFlashMessage('success', 'Subcategoria financeira cadastrada com sucesso.');
			$this->redirect('financial_operation_subcategory');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível cadastrar a subcategoria financeira. Verifique os dados e tente novamente.');
			$this->redirect('financial_operation_subcategory/create');
			}
		}

	/**
	 * Edit method.
	 *
	 * @param int|string $id Subcategory ID.
	 **/
	public function edit($id)
		{
		$this->requirePermission('financial_operation_subcategory.edit');
		$category = new FinancialOperationCategory();
		$subcategory = new FinancialOperationSubcategory();
		$data = $subcategory->find($id);
		$this->view('financial_operations_subcategories/edit', [
			'title'                           => 'Editar subcategoria financeira',
			'financial_operation_subcategory' => $data,
			'categories'                      => $category->getAll(),
		]);
		}

	/**
	 * Update method.
	 *
	 * @param int|string $id Subcategory ID.
	 **/
	public function update($id)
		{
		$this->requirePermission('financial_operation_subcategory.update');
		$subcategory = new FinancialOperationSubcategory();

		try
			{
			$subcategory->update($id, $_POST);
			setFlashMessage('success', 'Subcategoria financeira atualizada com sucesso.');
			$this->redirect('financial_operation_subcategory');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível atualizar a subcategoria financeira. Verifique os dados e tente novamente.');
			$this->redirect('financial_operation_subcategory/edit/' . $id);
			}
		}

	/**
	 * Informations method.
	 *
	 * @param int|string $id Subcategory ID.
	 **/
	public function informations($id)
		{
		$this->requirePermission('financial_operation_subcategory.informations');
		$subcategory = new FinancialOperationSubcategory();
		$data = $subcategory->findAllByFinancialOperationSubcategoryId($id);
		$this->view('financial_operations_subcategories/informations', [
			'title'                           => 'Informações sobre a subcategoria financeira',
			'financial_operation_subcategory' => $data['financial_operation_subcategory'],
		]);
		}

	/**
	 * Delete method.
	 *
	 * @param int|string $id Subcategory ID.
	 **/
	public function delete($id)
		{
		$this->requirePermission('financial_operation_subcategory.delete');

		if ($_SERVER['REQUEST_METHOD'] === 'POST')
			{
			$subcategory = new FinancialOperationSubcategory();
			try
				{
				$subcategory->delete($id);
				setFlashMessage('success', 'Subcategoria financeira excluída com sucesso.');
				$this->redirect('financial_operation_subcategory');
				}
			catch (\Exception $e)
				{
				setFlashMessage('danger', 'Não foi possível excluir a subcategoria financeira.');
				$this->redirect('financial_operation_subcategory');
				}
			}
		}
	}

?>
