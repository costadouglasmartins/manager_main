<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Company;

class CompanyController extends Controller
	{

	/**
	 * Indext method
	 */
	public function index()
		{
		$this->requirePermission('company.index');

		$company = new Company();

		// Filtros vindos da query string
		$filters = [
			'id'				=> isset($_GET['id'])				? trim($_GET['id'])				: '',
			'name'			=> isset($_GET['name'])			? trim($_GET['name'])			: '',
			'fantasy'		=> isset($_GET['fantasy'])	? trim($_GET['fantasy'])	: '',
			'document'	=> isset($_GET['document']) ? trim($_GET['document']) : '',
			'active'		=> isset($_GET['active'])		? trim($_GET['active'])		: '',
		];

		// Página atual (?page=2)
		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		// Registros por página
		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		// Busca paginada com filtros
		$companies = $company->search($filters, $perPage, $offset);

		// Total para paginação (com os mesmos filtros)
		$total = $company->count($filters);
		$totalPages = (int) ceil($total / $perPage);
		if ($totalPages < 1)
			{
			$totalPages = 1;
			}

		$this->view('companies/index', [
			'companies'		=> $companies,
			'title'				=> 'Lista de empresas',
			'filters'			=> $filters,
			'page'				=> $page,
			'totalPages'	=> $totalPages,
			'total'				=> $total,
		]);
		}

	/**
	 * Create method 
	 */
	public function create()
		{
		$this->requirePermission('company.create');
		$this->view('companies/create', ['title' => 'Nova empresa']);
		}

	/**
	 * Store method
	 */
	public function store()
		{
		$this->requirePermission('company.store');
		$company = new Company();
		$company->create($_POST);
		$this->redirect('company');
		}

	/**
	 * Edit method
	 * @param Integer $id Company id
	 */
	public function edit($id)
		{
		$this->requirePermission('company.edit');
		$company = new Company();
		$data = $company->findById($id);
		$this->view('companies/edit', ['company' => $data, 'title' => 'Editar empresa']);
		}

	/**
	 * Informations method
	 * @param Integer $id Company id
	 */
	public function informations($id)
		{
		$this->requirePermission('company.informations');
		$company = new Company();
		$data = $company->findAllByCompanyId($id);
		$this->view('companies/informations', ['title'		=> 'Informações sobre a empresa',
																					 'company'	=> $data['company'],
																					 'people'		=> $data['people']]);
		}

	/**
	 * Update method
	 * @param Integer $id Company id
	 */
	public function update($id)
		{
		$this->requirePermission('company.update');
		$company = new Company();
		$company->update($id, $_POST);
		$this->redirect('company');
		}
	}

?>