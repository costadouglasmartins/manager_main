<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Address;

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
			'id'        => isset($_GET['id'])        ? trim($_GET['id'])        : '',
			'street'    => isset($_GET['street'])    ? trim($_GET['street'])    : '',
			'zip_code'  => isset($_GET['zip_code'])  ? trim($_GET['zip_code'])  : '',
			'person_id' => isset($_GET['person_id']) ? trim($_GET['person_id']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		$addresses = $address->search($filters, $perPage, $offset);
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
	}

?>
