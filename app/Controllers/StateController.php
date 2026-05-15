<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\State;

class StateController extends Controller
	{

	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('state.index');

		$state = new State();

		$filters = [
			'id'           => isset($_GET['id'])           ? trim($_GET['id'])           : '',
			'name'         => isset($_GET['name'])         ? trim($_GET['name'])         : '',
			'acronym'      => isset($_GET['acronym'])      ? trim($_GET['acronym'])      : '',
			'country_id'   => isset($_GET['country_id'])   ? trim($_GET['country_id'])   : '',
			'country_name' => isset($_GET['country_name']) ? trim($_GET['country_name']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$sort = sortRequest(State::sortableColumns(), 'id', 'desc');

		$states = $state->search($filters, $perPage, $offset, $sort);
		$total = $state->count($filters);
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
				'data'       => $states,
			]);
			}

		$this->view('states/index', [
			'states'     => $states,
			'title'      => 'Lista de estados',
			'filters'    => $filters,
			'page'       => $page,
			'totalPages' => $totalPages,
			'total'      => $total,
			'sort'       => $sort,
		]);
		}

	/**
	 * Create method
	 */
	public function create()
		{
		$this->requirePermission('state.create');
		$this->view('states/create', ['title' => 'Novo estado']);
		}

	/**
	 * Store method
	 */
	public function store()
		{
		$this->requirePermission('state.store');
		$state = new State();
		$state->create($_POST);
		$this->redirect('state');
		}

	/**
	 * Edit method
	 * @param Integer $id State id
	 */
	public function edit($id)
		{
		$this->requirePermission('state.edit');
		$state = new State();
		$data = $state->find($id);
		$this->view('states/edit', ['title' => 'Editar estado', 'state' => $data]);
		}

	/**
	 * Update method
	 * @param Integer $id State id
	 */
	public function update($id)
		{
		$this->requirePermission('state.update');
		$state = new State();
		$state->update($id, $_POST);
		$this->redirect('state');
		}

	/**
	 * Informations method
	 * @param Integer $id State id
	 */
	public function informations($id)
		{
		$this->requirePermission('state.informations');
		$state = new State();
		$data = $state->findAllByStateId($id);
		$this->view('states/informations', ['title' => 'Informações sobre o estado', 'state' => $data['state']]);
		}

	/**
	 * Autocomplete method
	 */
	public function autocomplete()
		{
		$this->requirePermission('state.autocomplete');

		$term = $_GET['term'] ?? '';
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;

		$state = new State();
		$rows = $state->autocomplete((string) $term, $limit);

		$items = array_map(function ($r)
			{
			$label = trim($r['name'] ?? '');
			if ($label === '')
				{
				$label = (string) ($r['id'] ?? '');
				}
			return [
				'id'      => isset($r['id']) ? (int) $r['id'] : null,
				'label'   => $label,
				'name'    => $r['name'] ?? null,
				'acronym' => $r['acronym'] ?? null,
			];
			}, $rows);

		$this->json(['items' => $items]);
		}

	/**
	 * Delete method
	 * @param Integer $id Record id
	 */
	public function delete($id)
		{
		$this->requirePermission('state.delete');

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$state = new State();
			try {
				$state->delete($id);
				$this->redirect('state');
			} catch (\Exception $e) {
				$this->redirect('state');
			}
		}
		}
	}

?>
