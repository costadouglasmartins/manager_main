<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\PhoneNumber;

class PhoneNumberController extends Controller
	{

	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('phone_number.index');

		$phoneNumber = new PhoneNumber();

		$filters = [
			'id'           => isset($_GET['id'])           ? trim($_GET['id'])           : '',
			'phone_number' => isset($_GET['phone_number']) ? trim($_GET['phone_number']) : '',
			'person_id'    => isset($_GET['person_id'])    ? trim($_GET['person_id'])    : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		$phoneNumbers = $phoneNumber->search($filters, $perPage, $offset);
		$total = $phoneNumber->count($filters);
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
				'data'       => $phoneNumbers,
			]);
			}

		$this->view('phone_numbers/index', [
			'phone_numbers' => $phoneNumbers,
			'title'         => 'Lista de telefones',
			'filters'       => $filters,
			'page'          => $page,
			'totalPages'    => $totalPages,
			'total'         => $total,
		]);
		}

	/**
	 * Create method
	 */
	public function create()
		{
		$this->requirePermission('phone_number.create');
		$this->view('phone_numbers/create', ['title' => 'Novo telefone']);
		}

	/**
	 * Store method
	 */
	public function store()
		{
		$this->requirePermission('phone_number.store');
		$phoneNumber = new PhoneNumber();
		$phoneNumber->create($_POST);
		$this->redirect('phone_number');
		}

	/**
	 * Edit method
	 * @param Integer $id PhoneNumber id
	 */
	public function edit($id)
		{
		$this->requirePermission('phone_number.edit');
		$phoneNumber = new PhoneNumber();
		$data = $phoneNumber->find($id);
		$this->view('phone_numbers/edit', ['title' => 'Editar telefone', 'phone_number' => $data]);
		}

	/**
	 * Update method
	 * @param Integer $id PhoneNumber id
	 */
	public function update($id)
		{
		$this->requirePermission('phone_number.update');
		$phoneNumber = new PhoneNumber();
		$phoneNumber->update($id, $_POST);
		$this->redirect('phone_number');
		}

	/**
	 * Informations method
	 * @param Integer $id PhoneNumber id
	 */
	public function informations($id)
		{
		$this->requirePermission('phone_number.informations');
		$phoneNumber = new PhoneNumber();
		$data = $phoneNumber->findAllByPhoneNumberId($id);
		$this->view('phone_numbers/informations', ['title' => 'Informações sobre o telefone', 'phone_number' => $data['phone_number']]);
		}

	/**
	 * Delete method
	 * @param Integer $id Record id
	 */
	public function delete($id)
		{
		$this->requirePermission('phone_number.delete');

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$phoneNumber = new PhoneNumber();
			try {
				$phoneNumber->delete($id);
				$this->redirect('phone_number');
			} catch (\Exception $e) {
				$this->redirect('phone_number');
			}
		}
		}
	}

?>
