<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Neighborhood;

class NeighborhoodController extends Controller
	{

	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('neighborhood.index');

		$neighborhood = new Neighborhood();

		$filters = [
			'id'         => isset($_GET['id'])         ? trim($_GET['id'])         : '',
			'name'       => isset($_GET['name'])       ? trim($_GET['name'])       : '',
			'country_id' => isset($_GET['country_id']) ? trim($_GET['country_id']) : '',
			'state_id'   => isset($_GET['state_id'])   ? trim($_GET['state_id'])   : '',
			'city_id'    => isset($_GET['city_id'])    ? trim($_GET['city_id'])    : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$sort = sortRequest(Neighborhood::sortableColumns(), 'id', 'desc');

		$neighborhoods = $neighborhood->search($filters, $perPage, $offset, $sort);
		$total = $neighborhood->count($filters);
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
				'data'       => $neighborhoods,
			]);
			}

		$this->view('neighborhoods/index', [
			'neighborhoods' => $neighborhoods,
			'title'         => 'Lista de bairros',
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
		$this->requirePermission('neighborhood.create');
		$this->view('neighborhoods/create', ['title' => 'Novo bairro']);
		}

	/**
	 * Store method
	 */
	public function store()
		{
		$this->requirePermission('neighborhood.store');
		$neighborhood = new Neighborhood();
		$neighborhood->create($_POST);
		$this->redirect('neighborhood');
		}

	/**
	 * Edit method
	 * @param Integer $id Neighborhood id
	 */
	public function edit($id)
		{
		$this->requirePermission('neighborhood.edit');
		$neighborhood = new Neighborhood();
		$data = $neighborhood->find($id);
		$this->view('neighborhoods/edit', ['title' => 'Editar bairro', 'neighborhood' => $data]);
		}

	/**
	 * Update method
	 * @param Integer $id Neighborhood id
	 */
	public function update($id)
		{
		$this->requirePermission('neighborhood.update');
		$neighborhood = new Neighborhood();
		$neighborhood->update($id, $_POST);
		$this->redirect('neighborhood');
		}

	/**
	 * Informations method
	 * @param Integer $id Neighborhood id
	 */
	public function informations($id)
		{
		$this->requirePermission('neighborhood.informations');
		$neighborhood = new Neighborhood();
		$data = $neighborhood->findAllByNeighborhoodId($id);
		$this->view('neighborhoods/informations', ['title' => 'Informações sobre o bairro', 'neighborhood' => $data['neighborhood']]);
		}

	/**
	 * Autocomplete method
	 */
	public function autocomplete()
		{
		$this->requirePermission('neighborhood.autocomplete');

		$term = $_GET['term'] ?? '';
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;

		$neighborhood = new Neighborhood();
		$rows = $neighborhood->autocomplete((string) $term, $limit);

		$items = array_map(function ($r)
			{
			$label = trim($r['name'] ?? '');
			if ($label === '')
				{
				$label = (string) ($r['id'] ?? '');
				}
			if (!empty($r['city_name']))
				{
				$label .= ' — ' . $r['city_name'];
				}
			return [
				'id'    => isset($r['id']) ? (int) $r['id'] : null,
				'label' => $label,
				'name'  => $r['name'] ?? null,
			];
			}, $rows);

		$this->json(['items' => $items]);
		}

	/**
	 * Get states by country method
	 */
	public function getStatesByCountry()
		{
		$this->requirePermission('neighborhood.index');

		$countryId = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;

		if ($countryId <= 0)
			{
			$this->json(['states' => []]);
			return;
			}

		$state = new \App\Models\State();
		$states = $state->getAllByCountryId($countryId);

		$this->json(['states' => $states]);
		}

	/**
	 * Get cities by state method
	 */
	public function getCitiesByState()
		{
		$this->requirePermission('neighborhood.index');

		$stateId = isset($_GET['state_id']) ? (int) $_GET['state_id'] : 0;

		if ($stateId <= 0)
			{
			$this->json(['cities' => []]);
			return;
			}

		$city = new \App\Models\City();
		$cities = $city->getAllByStateId($stateId);

		$this->json(['cities' => $cities]);
		}

	/**
	 * Get all countries method
	 */
	public function getAllCountries()
		{
		$this->requirePermission('neighborhood.index');

		$country = new \App\Models\Country();
		$countries = $country->getAll();

		$this->json(['countries' => $countries]);
		}

	/**
	 * Delete method
	 * @param Integer $id Record id
	 */
	public function delete($id)
		{
		$this->requirePermission('neighborhood.delete');

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$neighborhood = new Neighborhood();
			try {
				$neighborhood->delete($id);
				$this->redirect('neighborhood');
			} catch (\Exception $e) {
				$this->redirect('neighborhood');
			}
		}
		}
	}

?>
