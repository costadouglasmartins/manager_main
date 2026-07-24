<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\City;
use App\Models\Country;
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
		$country = new Country();

		$filters = [
			'id'           => isset($_GET['id'])           ? trim($_GET['id'])           : '',
			'name'         => isset($_GET['name'])         ? trim($_GET['name'])         : '',
			'acronym'      => isset($_GET['acronym'])      ? trim($_GET['acronym'])      : '',
			'country_id'   => isset($_GET['country_id'])   ? trim($_GET['country_id'])   : '',
			'country_name' => isset($_GET['country_name']) ? trim($_GET['country_name']) : '',
			'state_id'     => isset($_GET['state_id'])     ? trim($_GET['state_id'])     : '',
			'city_id'      => isset($_GET['city_id'])      ? trim($_GET['city_id'])      : '',
			'city_name'    => isset($_GET['city_name'])    ? trim($_GET['city_name'])    : '',
			'_location_invalid' => isset($_GET['_location_invalid']) ? trim($_GET['_location_invalid']) : '',
		];
		$filters = $state->normalizeListFilters($filters);

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

		$countries = $country->getAll();
		usort($countries, static function (array $left, array $right): int
			{
			return strnatcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
			});

		$countryStates = [];
		if ($filters['country_id'] !== '')
			{
			$countryStates = $state->getAllByCountryId((int) $filters['country_id']);
			}

		$this->view('states/index', [
			'states'        => $states,
			'countries'     => $countries,
			'countryStates' => $countryStates,
			'title'         => 'Lista de estados',
			'filters'       => $filters,
			'page'          => $page,
			'totalPages'    => $totalPages,
			'total'         => $total,
			'sort'          => $sort,
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

		try
			{
			$state->create($_POST);
			setFlashMessage('success', 'Estado cadastrado com sucesso.');
			$this->redirect('state');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível cadastrar o estado. Verifique os dados e tente novamente.');
			$this->redirect('state/create');
			}
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

		try
			{
			$state->update($id, $_POST);
			setFlashMessage('success', 'Estado atualizado com sucesso.');
			$this->redirect('state');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível atualizar o estado. Verifique os dados e tente novamente.');
			$this->redirect('state/edit/' . $id);
			}
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
		$countryId = isset($_GET['country_id']) && trim((string) $_GET['country_id']) !== '' ? (int) $_GET['country_id'] : null;
		$countryName = isset($_GET['country_name']) ? trim((string) $_GET['country_name']) : '';

		$state = new State();
		$rows = $state->autocomplete((string) $term, $limit, $countryId, $countryName);

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
				'country_id' => isset($r['country_id']) ? (int) $r['country_id'] : null,
				'country_name' => $r['country_name'] ?? null,
			];
			}, $rows);

		$this->json(['items' => $items]);
		}

	/**
	 * Returns states that belong to the selected country.
	 */
	public function getStatesByCountry(): void
		{
		$this->requirePermission('state.index');

		$countryId = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;
		if ($countryId <= 0)
			{
			$this->json(['states' => []]);
			return;
			}

		$states = (new State())->getAllByCountryId($countryId);
		$this->json(['states' => $states]);
		}

	/**
	 * Returns city autocomplete items scoped to the selected state.
	 */
	public function cityAutocomplete(): void
		{
		$this->requirePermission('state.index');

		$term = isset($_GET['term']) ? trim((string) $_GET['term']) : '';
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
		$stateId = isset($_GET['state_id']) ? (int) $_GET['state_id'] : 0;
		$countryId = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;

		if ($stateId <= 0 || $term === '')
			{
			$this->json(['items' => []]);
			return;
			}

		$rows = (new City())->autocomplete(
			$term,
			$limit,
			$stateId,
			$countryId > 0 ? $countryId : null
		);

		$items = array_map(static function (array $row): array
			{
			$label = trim((string) ($row['name'] ?? ''));
			if ($label === '')
				{
				$label = (string) ($row['id'] ?? '');
				}

			return [
				'id' => isset($row['id']) ? (int) $row['id'] : null,
				'label' => $label,
				'name' => $row['name'] ?? null,
				'state_id' => isset($row['state_id']) ? (int) $row['state_id'] : null,
				'state_name' => $row['state_name'] ?? null,
				'country_id' => isset($row['country_id']) ? (int) $row['country_id'] : null,
				'country_name' => $row['country_name'] ?? null,
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
				setFlashMessage('success', 'Estado excluído com sucesso.');
				$this->redirect('state');
			} catch (\Exception $e) {
				setFlashMessage('danger', 'Não foi possível excluir o estado.');
				$this->redirect('state');
			}
		}
		}
	}

?>
