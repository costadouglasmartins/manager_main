<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Country;

class CountryController extends Controller
	{

	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('country.index');
		
		$country = new Country();

		// Filters from query string
		$filters = [
			'id' => isset($_GET['id']) ? trim($_GET['id']) : '',
			'name' => isset($_GET['name']) ? trim($_GET['name']) : '',
      'acronym' => isset($_GET['acronym']) ? trim($_GET['acronym']) : '',
		];

		// Current page (?page=2)
		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		// Records per page
		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$sort = sortRequest(Country::sortableColumns(), 'id', 'desc');

		// Paginated search with filters
		$countries = $country->search($filters, $perPage, $offset, $sort);
		// Total records for pagination with the same filters
		$total = $country->count($filters);
		$totalPages = (int) ceil($total / $perPage);
		if ($totalPages < 1)
			{
			$totalPages = 1;
			}

		if ($this->wantsJson())
			{
			$this->json([
				'filters' => $filters,
				'page' => $page,
				'perPage' => $perPage,
				'total' => $total,
				'totalPages' => $totalPages,
				'sort' => $sort,
				'data' => $countries,
			]);
			}

		// Default HTML response
      $this->view('countries/index', [
        'countries' => $countries,
        'title' => 'Lista de países',
        'filters' => $filters,
        'page' => $page,
        'totalPages' => $totalPages,
        'total' => $total,
        'sort' => $sort,
      ]);
		}

	/**
	 * Created method
	 */
	public function create()
		{
		$this->requirePermission('country.create');
		$this->view('countries/create', ['title' => 'Novo país']);
		}

	/**
	 * Store method
	 */
	public function store()
		{
		$this->requirePermission('country.store');
		$country = new Country();

		try
			{
			$country->create($_POST);
			setFlashMessage('success', 'País cadastrado com sucesso.');
			$this->redirect('country');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível cadastrar o país. Verifique os dados e tente novamente.');
			$this->redirect('country/create');
			}
		}

	/**
	 * Edit method
	 * @param Integer $id Person id
	 */
	public function edit($id)
		{
		$this->requirePermission('country.edit');
		$country = new Country();
		$data = $country->find($id);
		$this->view('countries/edit', ['title' => 'Editar país', 'country' => $data]);
		}

	/**
	 * Update method
	 * @param Integer $id Person id
	 */
	public function update($id)
		{
		$this->requirePermission('country.update');
		$country = new Country();

		try
			{
			$country->update($id, $_POST);
			setFlashMessage('success', 'País atualizado com sucesso.');
			$this->redirect('country');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível atualizar o país. Verifique os dados e tente novamente.');
			$this->redirect('country/edit/' . $id);
			}
		}

	/**
	 * Information method
	 * @param Integer $id Person id
	 */
	public function informations($id)
		{
		$this->requirePermission('country.informations');
		$country = new Country();
		$data = $country->findAllByCountryId($id);
		$this->view('countries/informations', ['title' => 'Informações sobre a país',
			'countries' => $data['countries']]);
		}

	/**
	 * Autocomplete method
	 */
	public function autocomplete()
		{
		$this->requirePermission('country.autocomplete');

		$term = $_GET['term'] ?? '';
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;

		$country = new Country();
		$rows = $country->autocomplete((string) $term, $limit);

		$items = array_map(function ($r)
			{
			$label = trim($r['name'] ?? '');
      if ($label === '')
        {
				$label = (string) ($r['id'] ?? '');
        }
			return [
				'id' => isset($r['id']) ? (int) $r['id'] : null,
				'label' => $label,
				'name' => $r['name'] ?? null
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
		$this->requirePermission('country.delete');

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$country = new Country();
			try {
				$country->delete($id);
				setFlashMessage('success', 'País excluído com sucesso.');
				$this->redirect('country');
			} catch (\Exception $e) {
				setFlashMessage('danger', 'Não foi possível excluir o país.');
				$this->redirect('country');
			}
		}
		}
	}

?>
