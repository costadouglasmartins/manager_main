<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\City;

class CityController extends Controller
	{

	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('city.index');

		$city = new City();

		$filters = [
			'id'           => isset($_GET['id'])           ? trim($_GET['id'])           : '',
			'name'         => isset($_GET['name'])         ? trim($_GET['name'])         : '',
			'country_id'   => isset($_GET['country_id'])   ? trim($_GET['country_id'])   : '',
			'country_name' => isset($_GET['country_name']) ? trim($_GET['country_name']) : '',
			'state_id'     => isset($_GET['state_id'])     ? trim($_GET['state_id'])     : '',
			'state_name'   => isset($_GET['state_name'])   ? trim($_GET['state_name'])   : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$sort = sortRequest(City::sortableColumns(), 'id', 'desc');

		$cities = $city->search($filters, $perPage, $offset, $sort);
		$total = $city->count($filters);
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
				'data'       => $cities,
			]);
			}

		$this->view('cities/index', [
			'cities'     => $cities,
			'title'      => 'Lista de cidades',
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
		$this->requirePermission('city.create');
		$this->view('cities/create', ['title' => 'Nova cidade']);
		}

	/**
	 * Store method
	 */
	public function store()
		{
		$this->requirePermission('city.store');
		$city = new City();
		$city->create($_POST);
		$this->redirect('city');
		}

	/**
	 * Edit method
	 * @param Integer $id City id
	 */
	public function edit($id)
		{
		$this->requirePermission('city.edit');
		$city = new City();
		$data = $city->find($id);
		$this->view('cities/edit', ['title' => 'Editar cidade', 'city' => $data]);
		}

	/**
	 * Update method
	 * @param Integer $id City id
	 */
	public function update($id)
		{
		$this->requirePermission('city.update');
		$city = new City();
		$city->update($id, $_POST);
		$this->redirect('city');
		}

	/**
	 * Informations method
	 * @param Integer $id City id
	 */
	public function informations($id)
		{
		$this->requirePermission('city.informations');
		$city = new City();
		$data = $city->findAllByCityId($id);
		$this->view('cities/informations', ['title' => 'Informações sobre a cidade', 'city' => $data['city']]);
		}

	/**
	 * Autocomplete method
	 */
	public function autocomplete()
		{
		$this->requirePermission('city.autocomplete');

		$term = $_GET['term'] ?? '';
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
		$countryId = isset($_GET['country_id']) && trim((string) $_GET['country_id']) !== '' ? (int) $_GET['country_id'] : null;
		$stateId = isset($_GET['state_id']) && trim((string) $_GET['state_id']) !== '' ? (int) $_GET['state_id'] : null;
		$countryName = isset($_GET['country_name']) ? trim((string) $_GET['country_name']) : '';
		$stateName = isset($_GET['state_name']) ? trim((string) $_GET['state_name']) : '';

		$city = new City();
		$rows = $city->autocomplete((string) $term, $limit, $stateId, $countryId, $stateName, $countryName);

		$items = array_map(function ($r)
			{
			$label = trim($r['name'] ?? '');
			if ($label === '')
				{
				$label = (string) ($r['id'] ?? '');
				}
			if (!empty($r['state_acronym']))
				{
				$label .= ' — ' . $r['state_acronym'];
				}
			return [
				'id'           => isset($r['id']) ? (int) $r['id'] : null,
				'label'        => $label,
				'name'         => $r['name'] ?? null,
				'state_id'     => isset($r['state_id']) ? (int) $r['state_id'] : null,
				'state_name'   => $r['state_name'] ?? null,
				'country_id'   => isset($r['country_id']) ? (int) $r['country_id'] : null,
				'country_name' => $r['country_name'] ?? null,
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
		$this->requirePermission('city.delete');

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$city = new City();
			try {
				$city->delete($id);
				$this->redirect('city');
			} catch (\Exception $e) {
				$this->redirect('city');
			}
		}
		}
	}

?>
