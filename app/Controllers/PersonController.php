<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Authorization;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\Neighborhood;
use App\Models\City;

/**
 * Handles person management screens and supporting JSON endpoints.
 */
class PersonController extends Controller
	{

	/**
	 * Requires authentication and at least one of the provided permissions.
	 *
	 * Returns a JSON 403 response for JSON requests or renders the HTML 403 page
	 * for regular requests.
	 *
	 * @param array<int, string> $abilities Accepted permission abilities.
	 * @return void
	 */
	private function requireAnyPermission(array $abilities): void
		{
		$this->requireAuth();

		foreach ($abilities as $ability)
			{
			if (Authorization::can($ability))
				{
				return;
				}
			}

		if ($this->wantsJson())
			{
			$this->json([
				'error'     => 'forbidden',
				'message'   => 'You do not have permission to access this resource',
				'abilities' => $abilities,
			], 403);
			}

		http_response_code(403);
		$this->view('errors/403', ['title' => 'Acesso negado', 'ability' => implode(', ', $abilities)]);
		exit;
		}

	/**
	 * Displays the paginated person list.
	 *
	 * Reads filters from the query string, searches people, calculates
	 * pagination totals and returns either JSON or the HTML list view.
	 *
	 * @return void
	 */
	public function index()
		{
		$this->requirePermission('person.index');
		
		$person = new Person();

		// Filters from the query string
		$filters = [
			'id' => isset($_GET['id']) ? trim($_GET['id']) : '',
			'name' => isset($_GET['name']) ? trim($_GET['name']) : '',
			'type' => isset($_GET['type']) ? trim($_GET['type']) : '',
			'document' => isset($_GET['document']) ? trim($_GET['document']) : '',
			'birthdate' => isset($_GET['birthdate']) ? trim($_GET['birthdate']) : '',
			'active' => isset($_GET['active']) ? trim($_GET['active']) : '',
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
		$sort = sortRequest(Person::sortableColumns(), 'id', 'desc');

		// Paginated search with filters
		$people = $person->search($filters, $perPage, $offset, $sort);
		// Total for pagination using the same filters
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
				'sort' => $sort,
				'data' => $people,
			]);
			}

      // Default HTML response
      $this->view('people/index', [
        'people' => $people,
        'title' => 'Lista de empresas',
        'filters' => $filters,
        'page' => $page,
        'totalPages' => $totalPages,
        'total' => $total,
        'sort' => $sort,
      ]);
		}

	/**
	 * Displays the person creation form.
	 *
	 * Loads tenant data needed by the form, including the next database number
	 * suggestion for a newly provisioned tenant.
	 *
	 * @return void
	 */
	public function create()
		{
		$this->requirePermission('person.create');

		$tenantModel = new Tenant();
		$nextDatabaseNumber = $tenantModel->nextDatabaseNumber();
		$tenants = $tenantModel->getAll();

		$this->view('people/create', [
			'title'              => 'Nova pessoa',
			'nextDatabaseNumber' => $nextDatabaseNumber,
			'tenants'            => $tenants,
		]);
		}

	/**
	 * Searches neighborhoods for person form address autocomplete.
	 *
	 * Requires either person creation or edit permission because it is used
	 * inline by both forms.
	 *
	 * @return void
	 */
	public function neighborhoodSearch()
		{
		$this->requireAnyPermission(['person.create', 'person.edit']);

		$term  = trim($_GET['term'] ?? '');
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
		$cityName = trim($_GET['city_name'] ?? ($_GET['city'] ?? ''));
		$stateAcronym = trim($_GET['state_acronym'] ?? ($_GET['state'] ?? ''));

		$neighborhood = new Neighborhood();
		$rows = $neighborhood->autocomplete($term, $limit, [
			'city_name'     => $cityName,
			'state_acronym' => $stateAcronym,
		]);

		$items = array_map(function ($r)
			{
			$label = $r['name'];
			if (!empty($r['city_name']))
				{
				$label .= ' — ' . $r['city_name'];
				}
			if (!empty($r['state_acronym']))
				{
				$label .= ' — ' . $r['state_acronym'];
				}
			return [
				'id'            => (int) $r['id'],
				'label'         => $label,
				'name'          => $r['name'],
				'city_name'     => $r['city_name'] ?? '',
				'state_acronym' => $r['state_acronym'] ?? '',
			];
			}, $rows);

		$this->json(['items' => $items]);
		}

	/**
	 * Creates a neighborhood inline from the person form.
	 *
	 * Validates the posted neighborhood name and city ID, creates the record and
	 * returns the new item as JSON.
	 *
	 * @return void
	 */
	public function neighborhoodCreate()
		{
		$this->requireAnyPermission(['person.create', 'person.edit']);

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
	 * Searches cities for the inline neighborhood creation form.
	 *
	 * Requires either person creation or edit permission and returns city options
	 * formatted for autocomplete controls.
	 *
	 * @return void
	 */
	public function citySearch()
		{
		$this->requireAnyPermission(['person.create', 'person.edit']);

		$term  = trim($_GET['term'] ?? '');
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

		$city = new City();
		$rows = $city->autocomplete($term, $limit);

		$items = array_map(function ($r)
			{
			$label = $r['name'];
			if (!empty($r['state_acronym']))
				{
				$label .= ' — ' . $r['state_acronym'];
				}
			return [
				'id'    => (int) $r['id'],
				'label' => $label,
				'name'  => $r['name'],
			];
			}, $rows);

		$this->json(['items' => $items]);
		}

	/**
	 * Checks whether a person document is already registered.
	 *
	 * Used by the create form to enforce the unique document rule before submit.
	 *
	 * @return void
	 */
	public function documentExists()
		{
		$this->requireAnyPermission(['person.create', 'person.store']);

		$document = trim((string) ($_GET['document'] ?? $_POST['document'] ?? ''));
		$person = new Person();
		$exists = $person->documentExists($document);

		$this->json([
			'exists'    => $exists,
			'available' => !$exists,
			'document'  => onlyAlphanumeric($document),
		]);
		}

	/**
	 * Checks whether a username is already registered.
	 *
	 * Used by the create form to enforce the unique username rule before submit.
	 *
	 * @return void
	 */
	public function usernameExists()
		{
		$this->requireAnyPermission(['person.create', 'person.store']);

		$username = trim((string) ($_GET['username'] ?? $_POST['username'] ?? ''));
		$person = new Person();
		$exists = $person->usernameExists($username);

		$this->json([
			'exists'    => $exists,
			'available' => !$exists,
			'username'  => strtolower($username),
		]);
		}

	/**
	 * Checks whether a phone number is already registered.
	 *
	 * Used by the create form to enforce the unique phone rule before submit.
	 *
	 * @return void
	 */
	public function phoneNumberExists()
		{
		$this->requireAnyPermission(['person.create', 'person.store']);

		$phoneNumber = trim((string) ($_GET['phone_number'] ?? $_POST['phone_number'] ?? ''));
		$person = new Person();
		$exists = $person->phoneNumberExists($phoneNumber);

		$this->json([
			'exists'       => $exists,
			'available'    => !$exists,
			'phone_number' => onlyDigits($phoneNumber),
		]);
		}

	/**
	 * Checks whether an e-mail address is already registered.
	 *
	 * Used by the create form to enforce the unique e-mail rule before submit.
	 *
	 * @return void
	 */
	public function emailExists()
		{
		$this->requireAnyPermission(['person.create', 'person.store']);

		$email = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
		$person = new Person();
		$exists = $person->emailExists($email);

		$this->json([
			'exists'    => $exists,
			'available' => !$exists,
			'email'     => strtolower($email),
		]);
		}
  
	/**
	 * Persists a new person from posted form data.
	 *
	 * Delegates creation to the person model and redirects back to the person
	 * list after a successful save.
	 *
	 * @return void
	 */
	public function store()
		{
		$this->requirePermission('person.store');
		$person = new Person();

		try
			{
			$person->create($_POST);
			setFlashMessage('success', 'Pessoa cadastrada com sucesso.');
			}
		catch (\InvalidArgumentException $e)
			{
			if ($this->wantsJson())
				{
				$this->json([
					'error'   => 'validation_error',
					'message' => $e->getMessage(),
				], 422);
				}

			setFlashMessage('danger', $e->getMessage());
			$this->redirect('person/create');
			return;
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível cadastrar a pessoa. Verifique os dados e tente novamente.');
			$this->redirect('person/create');
			return;
			}

		$this->redirect('person');
		}

	/**
	 * Creates a new person linked to an existing tenant and returns JSON.
	 *
	 * This endpoint is intended for inline creation flows (for example, modal
	 * forms) where the UI must remain on the current screen.
	 *
	 * @return void
	 */
	public function storeLinkedTenant()
		{
		$this->requirePermission('person.store');

		$tenantId = (int) ($_POST['tenant_id'] ?? 0);
		if ($tenantId <= 0)
			{
			$this->json([
				'error' => 'validation_error',
				'message' => 'Tenant inválido para associação.',
			], 422);
			}

		if (empty($_POST['person']['name']) || empty($_POST['person']['type']) || empty($_POST['person']['document']))
			{
			$this->json([
				'error' => 'validation_error',
				'message' => 'Preencha os campos obrigatórios da pessoa.',
			], 422);
			}

		if (empty($_POST['user']['username']) || empty($_POST['user']['type']) || empty($_POST['user']['password']))
			{
			$this->json([
				'error' => 'validation_error',
				'message' => 'Preencha os campos obrigatórios do usuário.',
			], 422);
			}

		$payload = $_POST;
		$payload['tenant'] = [
			'mode' => 'existing',
			'existing_id' => $tenantId,
		];

		$person = new Person();

		try
			{
			$result = $person->create($payload);
			$this->json([
				'success' => true,
				'message' => 'Pessoa criada e vinculada ao tenant com sucesso.',
				'data' => [
					'person_id' => (int) ($result['person_id'] ?? 0),
					'user_id' => (int) ($result['user_id'] ?? 0),
					'tenant_id' => (int) ($result['tenant_id'] ?? 0),
				],
			], 201);
			}
		catch (\Throwable $e)
			{
			if ($e instanceof \InvalidArgumentException)
				{
				$this->json([
					'error' => 'validation_error',
					'message' => $e->getMessage(),
				], 422);
				}

			$this->json([
				'error' => 'create_failed',
				'message' => 'Erro ao cadastrar pessoa vinculada ao tenant.',
			], 500);
			}
		}

	/**
	 * Displays the person edit form.
	 *
	 * Loads the person, user, phones, emails, addresses, tenant links and the
	 * full tenant list used by the form.
	 *
	 * @param int|string $id Person ID.
	 * @return void
	 */
	public function edit($id)
		{
		$this->requirePermission('person.edit');
		$person = new Person();
		$data = $person->findAllByPersonId($id);

		$tenantModel = new Tenant();
		$allTenants = $tenantModel->getAll();

		$this->view('people/edit', [
			'title'      => 'Editar pessoa',
			'person'     => $data['person'],
			'user'       => $data['user'],
			'phones'     => $data['phones'],
			'emails'     => $data['emails'],
			'addresses'  => $data['addresses'],
			'tenants'    => $data['tenants'],
			'database_tenants' => $data['database_tenants'] ?? [],
			'database_tenant_members' => $data['database_tenant_members'] ?? [],
			'allTenants' => $allTenants,
		]);
		}

	/**
	 * Updates an existing person from posted form data.
	 *
	 * Delegates the full update flow to the person model and redirects back to
	 * the person list after saving.
	 *
	 * @param int|string $id Person ID.
	 * @return void
	 */
	public function update($id)
		{
		$this->requirePermission('person.update');
		$person = new Person();

		try
			{
			$person->update($id, $_POST);
			setFlashMessage('success', 'Pessoa atualizada com sucesso.');
			$this->redirect('person');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível atualizar a pessoa. Verifique os dados e tente novamente.');
			$this->redirect('person/edit/' . $id);
			}
		}

	/**
	 * Displays read-only person information.
	 *
	 * Loads the same aggregate person data used by the edit screen and renders
	 * the information view.
	 *
	 * @param int|string $id Person ID.
	 * @return void
	 */
	public function informations($id)
		{
		$this->requirePermission('person.informations');
		$person = new Person();
		$data = $person->findAllByPersonId($id);
		$this->view('people/informations', [
			'title'     => 'Informações sobre a pessoa',
			'person'    => $data['person'],
			'user'      => $data['user'],
			'phones'    => $data['phones'],
			'emails'    => $data['emails'],
			'addresses' => $data['addresses'],
			'tenants'   => $data['tenants'],
		]);
		}

	/**
	 * Searches people by name for autocomplete controls.
	 *
	 * Reads the search term and optional limit from the query string, then
	 * returns normalized JSON items.
	 *
	 * @return void
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

	/**
	 * Deletes a person through a POST request.
	 *
	 * Ignores non-POST requests. On POST, delegates deletion to the person model
	 * and redirects back to the person list regardless of deletion outcome.
	 *
	 * @param int|string $id Person ID.
	 * @return void
	 */
	public function delete($id)
		{
		$this->requirePermission('person.delete');

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$person = new Person();
			try {
				$person->delete($id);
				setFlashMessage('success', 'Pessoa excluída com sucesso.');
				$this->redirect('person');
			} catch (\Exception $e) {
				setFlashMessage('danger', 'Não foi possível excluir a pessoa.');
				$this->redirect('person');
			}
			}
		}

	}
?>
