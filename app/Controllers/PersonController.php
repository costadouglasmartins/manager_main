<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Person;
use App\Models\Neighborhood;
use App\Models\City;

class PersonController extends Controller
	{

	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('person.index');
		
		$person = new Person();

		// Filtros vindos da query string
		$filters = [
			'id' => isset($_GET['id']) ? trim($_GET['id']) : '',
			'name' => isset($_GET['name']) ? trim($_GET['name']) : '',
			'type' => isset($_GET['type']) ? trim($_GET['type']) : '',
			'document' => isset($_GET['document']) ? trim($_GET['document']) : '',
			'birthdate' => isset($_GET['birthdate']) ? trim($_GET['birthdate']) : '',
			'active' => isset($_GET['active']) ? trim($_GET['active']) : '',
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
		$people = $person->search($filters, $perPage, $offset);
		// Total para paginação (com os mesmos filtros)
		$total = $person->count($filters);
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
				'data' => $people,
			]);
			}

      // padrão HTML
      $this->view('people/index', [
        'people' => $people,
        'title' => 'Lista de empresas',
        'filters' => $filters,
        'page' => $page,
        'totalPages' => $totalPages,
        'total' => $total,
      ]);
		}

	/**
	 * Created method
	 */
	public function create()
		{
		$this->requirePermission('person.create');
		$this->view('people/create', ['title' => 'Nova pessoa']);
		}

	/**
	 * Neighborhood search — used by the address autocomplete on the person create form.
	 * Requires only person.create permission (no separate neighborhood permission needed).
	 */
	public function neighborhoodSearch()
		{
		$this->requirePermission('person.create');

		$term  = trim($_GET['term'] ?? '');
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

		$neighborhood = new Neighborhood();
		$rows = $neighborhood->autocomplete($term, $limit);

		$items = array_map(function ($r)
			{
			return [
				'id'    => (int) $r['id'],
				'label' => $r['name'],
				'name'  => $r['name'],
			];
			}, $rows);

		$this->json(['items' => $items]);
		}

	/**
	 * Neighborhood create — creates a neighborhood inline during person registration.
	 * Requires only person.create permission.
	 */
	public function neighborhoodCreate()
		{
		$this->requirePermission('person.create');

		$name   = trim($_POST['name'] ?? '');
		$cityId = (int) ($_POST['city_id'] ?? 0);

		if ($name === '' || $cityId === 0)
			{
			$this->json(['error' => 'Nome e cidade são obrigatórios.'], 422);
			}

		$neighborhood = new Neighborhood();

		try
			{
			$result = $neighborhood->create([
				'name'    => $name,
				'city_id' => $cityId,
			]);
			$this->json([
				'id'   => (int) $result['neighborhood_id'],
				'name' => $name,
			]);
			}
		catch (\Exception $e)
			{
			$this->json(['error' => 'Erro ao cadastrar bairro: ' . $e->getMessage()], 500);
			}
		}

	/**
	 * City search — used by the neighborhood inline create form.
	 * Requires only person.create permission.
	 */
	public function citySearch()
		{
		$this->requirePermission('person.create');

		$term  = trim($_GET['term'] ?? '');
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

		$city = new City();
		$rows = $city->autocomplete($term, $limit);

		$items = array_map(function ($r)
			{
			return [
				'id'    => (int) $r['id'],
				'label' => $r['name'],
				'name'  => $r['name'],
			];
			}, $rows);

		$this->json(['items' => $items]);
		}
  
	/**
	 * Store method
	 */
	public function store()
		{
		$this->requirePermission('person.store');
		$person = new Person();
		$person->create($_POST);
		$this->redirect('person');
		}

	/**
	 * Edit method
	 * @param Integer $id Person id
	 */
	public function edit($id)
		{
		$this->requirePermission('person.edit');
		$person = new Person();
		$data = $person->find($id);
		$this->view('people/edit', ['title' => 'Editar pessoa', 'person' => $data]);
		}

	/**
	 * Update method
	 * @param Integer $id Person id
	 */
	public function update($id)
		{
		$this->requirePermission('person.update');
		$person = new Person();
		$person->update($id, $_POST);
		$this->redirect('person');
		}

	/**
	 * Information method
	 * @param Integer $id Person id
	 */
	public function informations($id)
		{
		$this->requirePermission('person.informations');
		$person = new Person();
		$data = $person->findAllByPersonId($id);
		$this->view('people/informations', [
			'title'  => 'Informações sobre a pessoa',
			'person' => $data['person'],
			'user'   => $data['user'],
			'phones' => $data['phones'],
			'emails' => $data['emails'],
		]);
		}

	/**
	 * Autocomplete method
	 */
	public function autocomplete()
		{
		$this->requirePermission('person.autocomplete');

		$term = $_GET['term'] ?? '';
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;

		$person = new Person();
		$rows = $person->autocomplete((string) $term, $limit);

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
	}

?>