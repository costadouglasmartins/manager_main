<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Address;
use App\Services\ZipCodeSearchService;

class AddressController extends Controller
	{

	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('address.index');

		$address = new Address();

		$filters = [
			'id'          => isset($_GET['id'])          ? trim($_GET['id'])          : '',
			'street'      => isset($_GET['street'])      ? trim($_GET['street'])      : '',
			'zip_code'    => isset($_GET['zip_code'])    ? trim($_GET['zip_code'])    : '',
			'person_id'   => isset($_GET['person_id'])   ? trim($_GET['person_id'])   : '',
			'person_name' => isset($_GET['person_name']) ? trim($_GET['person_name']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$sort = sortRequest(Address::sortableColumns(), 'id', 'desc');

		$addresses = $address->search($filters, $perPage, $offset, $sort);
		$total = $address->count($filters);
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
				'data'       => $addresses,
			]);
			}

		$this->view('addresses/index', [
			'addresses'  => $addresses,
			'title'      => 'Lista de endereços',
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
		$this->requirePermission('address.create');
		$this->view('addresses/create', ['title' => 'Novo endereço']);
		}

	/**
	 * Looks up an address by Brazilian ZIP code.
	 *
	 * This endpoint wraps ViaCEP in the application backend and returns a
	 * normalized JSON payload. When the returned neighborhood/city/state already
	 * exists locally, the response includes the matching `neighborhood_id`.
	 *
	 * @return void
	 */
	public function lookupZipCode(): void
		{
		$this->requireAuth();

		$zipCode = (string) ($_GET['zip_code'] ?? ($_GET['cep'] ?? ''));
		$service = new ZipCodeSearchService();
		$result = $service->searchAndSave($zipCode);

		if (empty($result['success']))
			{
			$this->json([
				'error'   => $result['error'] ?? 'zip_code_lookup_failed',
				'message' => $result['message'] ?? 'Não foi possível consultar o CEP.',
			], (int) ($result['status'] ?? 502));
			}

		$data = $result['data'] ?? [];
		$savedNeighborhood = $data['saved']['neighborhood'] ?? null;
		$neighborhoodId = is_array($savedNeighborhood) && isset($savedNeighborhood['id'])
			? (int) $savedNeighborhood['id']
			: null;
		$neighborhoodLabel = (string) ($data['neighborhood'] ?? '');

		if ($neighborhoodId === null)
			{
			$address = new Address();
			$neighborhood = $address->findNeighborhoodByLocation(
				(string) ($data['neighborhood'] ?? ''),
				(string) ($data['city'] ?? ''),
				(string) ($data['state'] ?? '')
			);

			$neighborhoodId = $neighborhood ? (int) $neighborhood['id'] : null;
			$neighborhoodLabel = $neighborhood
				? $neighborhood['name'] . ' - ' . $neighborhood['city_name']
				: (string) ($data['neighborhood'] ?? '');
			}
		else
			{
			$neighborhoodLabel = (string) ($savedNeighborhood['name'] ?? $neighborhoodLabel);
			}

		$data['neighborhood_id'] = $neighborhoodId;
		$data['neighborhood_label'] = $neighborhoodLabel;

		$this->json(['data' => $data]);
		}

	/**
	 * Store method
	 */
	public function store()
		{
		$this->requirePermission('address.store');
		$address = new Address();
		$address->create($_POST);
		$this->redirect('address');
		}

	/**
	 * Edit method
	 * @param Integer $id Address id
	 */
	public function edit($id)
		{
		$this->requirePermission('address.edit');
		$address = new Address();
		$data = $address->find($id);
		$this->view('addresses/edit', ['title' => 'Editar endereço', 'address' => $data]);
		}

	/**
	 * Update method
	 * @param Integer $id Address id
	 */
	public function update($id)
		{
		$this->requirePermission('address.update');
		$address = new Address();
		$address->update($id, $_POST);
		$this->redirect('address');
		}

	/**
	 * Informations method
	 * @param Integer $id Address id
	 */
	public function informations($id)
		{
		$this->requirePermission('address.informations');
		$address = new Address();
		$data = $address->findAllByAddressId($id);
		$this->view('addresses/informations', ['title' => 'Informações sobre o endereço', 'address' => $data['address']]);
		}

	/**
	 * Delete method
	 * @param Integer $id Record id
	 */
	public function delete($id)
		{
		$this->requirePermission('address.delete');

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$address = new Address();
			try {
				$address->delete($id);
				$this->redirect('address');
			} catch (\Exception $e) {
				$this->redirect('address');
			}
		}
		}
	}
?>
