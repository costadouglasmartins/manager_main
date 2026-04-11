<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Tenant;
use App\Services\TenantProvisioner;

class TenantController extends Controller
	{

	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('tenant.index');

		$tenant = new Tenant();

		$filters = [
			'id'              => isset($_GET['id'])              ? trim($_GET['id'])              : '',
			'name'            => isset($_GET['name'])            ? trim($_GET['name'])            : '',
			'database_number' => isset($_GET['database_number']) ? trim($_GET['database_number']) : '',
			'status'          => isset($_GET['status'])          ? trim($_GET['status'])          : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1) { $page = 1; }

		$perPage    = 10;
		$offset     = ($page - 1) * $perPage;
		$tenants    = $tenant->search($filters, $perPage, $offset);
		$total      = $tenant->count($filters);
		$totalPages = (int) ceil($total / $perPage);
		if ($totalPages < 1) { $totalPages = 1; }

		$this->view('tenants/index', [
			'tenants'    => $tenants,
			'title'      => 'Lista de tenants',
			'filters'    => $filters,
			'page'       => $page,
			'totalPages' => $totalPages,
			'total'      => $total,
		]);
		}

	/**
	 * Edit method
	 */
	public function edit($id)
		{
		$this->requirePermission('tenant.edit');

		$tenantModel = new Tenant();
		$tenant = $tenantModel->find((int) $id);

		if (!$tenant)
			{
			$this->redirect('tenant');
			return;
			}

		$this->view('tenants/edit', [
			'title'  => 'Editar tenant',
			'tenant' => $tenant,
		]);
		}

	/**
	 * Update method
	 */
	public function update($id)
		{
		$this->requirePermission('tenant.update');

		$tenantModel = new Tenant();

		$data = [
			'name'         => isset($_POST['name'])         ? trim($_POST['name'])         : '',
			'fantasy'      => isset($_POST['fantasy'])      ? trim($_POST['fantasy'])      : null,
			'document'     => isset($_POST['document'])     ? trim($_POST['document'])     : null,
			'status'       => isset($_POST['status'])       ? trim($_POST['status'])       : 'pending',
			'observations' => isset($_POST['observations']) ? trim($_POST['observations']) : null,
		];

		$tenantModel->update((int) $id, $data);

		$this->redirect('tenant');
		}

	/**
	 * Informations method
	 */
	public function informations($id)
		{
		$this->requirePermission('tenant.informations');

		$tenantModel = new Tenant();
		$tenant = $tenantModel->find((int) $id);

		if (!$tenant)
			{
			$this->redirect('tenant');
			return;
			}

		$this->view('tenants/informations', [
			'title'  => 'Informacoes do tenant',
			'tenant' => $tenant,
		]);
		}

	/**
	 * Delete method
	 * @param Integer $id Record id
	 */
	public function delete($id)
		{
		$this->requirePermission('tenant.delete');

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$tenant = new Tenant();
			try {
				$tenant->delete($id);
				$this->redirect('tenant');
			} catch (\Exception $e) {
				$this->redirect('tenant');
			}
		}
		}
	}

?>