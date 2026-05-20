<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Bank;

class BankController extends Controller
	{
	/**
	 * Index method.
	 */
	public function index()
		{
		$this->requirePermission('bank.index');

		$bank = new Bank();

		$filters = [
			'id'     => isset($_GET['id'])     ? trim($_GET['id'])     : '',
			'number' => isset($_GET['number']) ? trim($_GET['number']) : '',
			'name'   => isset($_GET['name'])   ? trim($_GET['name'])   : '',
			'active' => isset($_GET['active']) ? trim($_GET['active']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$sort = sortRequest(Bank::sortableColumns(), 'id', 'desc');

		$banks = $bank->search($filters, $perPage, $offset, $sort);
		$total = $bank->count($filters);
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
				'data'       => $banks,
			]);
			}

		$this->view('banks/index', [
			'banks'      => $banks,
			'title'      => 'Lista de bancos',
			'filters'    => $filters,
			'page'       => $page,
			'totalPages' => $totalPages,
			'total'      => $total,
			'sort'       => $sort,
		]);
		}

	/**
	 * Create method.
	 */
	public function create()
		{
		$this->requirePermission('bank.create');
		$this->view('banks/create', ['title' => 'Novo banco']);
		}

	/**
	 * Store method.
	 */
	public function store()
		{
		$this->requirePermission('bank.store');
		$bank = new Bank();
		$bank->create($_POST);
		$this->redirect('bank');
		}

	/**
	 * Edit method.
	 *
	 * @param int|string $id Bank ID.
	 */
	public function edit($id)
		{
		$this->requirePermission('bank.edit');
		$bank = new Bank();
		$data = $bank->find($id);
		if (!$data)
			{
			$this->redirect('bank');
			}

		$this->view('banks/edit', ['title' => 'Editar banco', 'bank' => $data]);
		}

	/**
	 * Update method.
	 *
	 * @param int|string $id Bank ID.
	 */
	public function update($id)
		{
		$this->requirePermission('bank.update');
		$bank = new Bank();
		$bank->update($id, $_POST);
		$this->redirect('bank');
		}

	/**
	 * Informations method.
	 *
	 * @param int|string $id Bank ID.
	 */
	public function informations($id)
		{
		$this->requirePermission('bank.informations');
		$bank = new Bank();
		$data = $bank->findAllByBankId($id);
		if (empty($data['bank']))
			{
			$this->redirect('bank');
			}

		$this->view('banks/informations', ['title' => 'Informações sobre o banco', 'bank' => $data['bank']]);
		}

	/**
	 * Delete method.
	 *
	 * @param int|string $id Bank ID.
	 */
	public function delete($id)
		{
		$this->requirePermission('bank.delete');

		if ($_SERVER['REQUEST_METHOD'] === 'POST')
			{
			$bank = new Bank();
			try
				{
				$bank->delete($id);
				$this->redirect('bank');
				}
			catch (\Exception $e)
				{
				$this->redirect('bank');
				}
			}
		}
	}

?>
