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
		$sort       = sortRequest(Tenant::sortableColumns(), 'id', 'desc');
		$tenants    = $tenant->search($filters, $perPage, $offset, $sort);
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
			'sort'       => $sort,
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

		$tenantUsers    = $tenantModel->getTenantUsers((int) $id);
		$availableUsers = $tenantModel->getAvailableUsers((int) $id);

		$this->view('tenants/edit', [
			'title'          => 'Editar tenant',
			'tenant'         => $tenant,
			'tenantUsers'    => $tenantUsers,
			'availableUsers' => $availableUsers,
			'flash'          => $_SESSION['manager_main']['flash'] ?? null,
		]);

		unset($_SESSION['manager_main']['flash']);
		}

	/**
	 * Update method
	 */
	public function update($id)
		{
		$this->requirePermission('tenant.update');

		$tenantModel = new Tenant();

		$data = [
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
	 * Add a user to the tenant (tenant_users link)
	 * Route: POST tenant/addUser/{tenantId}
	 * @param Integer $id Tenant id
	 */
	public function addUser($id)
		{
		$this->requirePermission('tenant.addUser');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->redirect('tenant/edit/' . (int) $id);
			return;
			}

		$tenantId = (int) $id;
		$userId   = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
		$role     = isset($_POST['role'])    ? trim($_POST['role'])    : 'user';

		$allowedRoles = ['owner', 'admin', 'user'];
		if (!in_array($role, $allowedRoles, true))
			{
			$role = 'user';
			}

		if ($tenantId > 0 && $userId > 0)
			{
			$tenantModel = new Tenant();

			if ($tenantModel->hasUser($tenantId, $userId))
				{
				$this->flash('warning', 'Este usuario ja esta vinculado a este tenant.');
				}
			else
				{
				$tenantModel->linkUser($tenantId, $userId, $role);
				$this->flash('success', 'Usuario vinculado ao tenant com sucesso.');
				}
			}

		$this->redirect('tenant/edit/' . $tenantId);
		}

	/**
	 * Update an existing tenant_users link role.
	 * Route: POST tenant/updateUser/{tenantId}/{userId}
	 * @param Integer $id     Tenant id
	 * @param Integer $userId User id
	 */
	public function updateUser($id, $userId = null)
		{
		$this->requirePermission('tenant.updateUser');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->redirect('tenant/edit/' . (int) $id);
			return;
			}

		$tenantId = (int) $id;
		$userId   = (int) ($userId ?? ($_POST['user_id'] ?? 0));
		$role     = isset($_POST['role'])   ? trim($_POST['role'])   : 'user';

		$allowedRoles = ['owner', 'admin', 'user'];
		if (!in_array($role, $allowedRoles, true))
			{
			$role = 'user';
			}

		if ($tenantId > 0 && $userId > 0)
			{
			$tenantModel = new Tenant();

			// Prevent demoting the last owner
			if ($role !== 'owner')
				{
				$current = array_values(array_filter(
					$tenantModel->getTenantUsers($tenantId),
					function ($u) use ($userId) { return (int) $u['user_id'] === $userId; }
				));

				if (!empty($current) && $current[0]['role'] === 'owner' && $tenantModel->countOwners($tenantId) <= 1)
					{
					$this->flash('danger', 'Nao e possivel rebaixar o ultimo owner do tenant.');
					$this->redirect('tenant/edit/' . $tenantId);
					return;
					}
				}

			$tenantModel->updateUserLink($tenantId, $userId, $role);
			$this->flash('success', 'Vinculo atualizado com sucesso.');
			}

		$this->redirect('tenant/edit/' . $tenantId);
		}

	/**
	 * Remove a user from the tenant (tenant_users link)
	 * Route: POST tenant/removeUser/{tenantId}/{userId}
	 * @param Integer $id     Tenant id
	 * @param Integer $userId User id
	 */
	public function removeUser($id, $userId = null)
		{
		$this->requirePermission('tenant.removeUser');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->redirect('tenant/edit/' . (int) $id);
			return;
			}

		$tenantId = (int) $id;
		$userId   = (int) ($userId ?? ($_POST['user_id'] ?? 0));

		if ($tenantId > 0 && $userId > 0)
			{
			$tenantModel = new Tenant();

			// Prevent removing the last owner
			$users = $tenantModel->getTenantUsers($tenantId);
			$target = null;
			foreach ($users as $u)
				{
				if ((int) $u['user_id'] === $userId)
					{
					$target = $u;
					break;
					}
				}

			if ($target && $target['role'] === 'owner' && $tenantModel->countOwners($tenantId) <= 1)
				{
				$this->flash('danger', 'Nao e possivel remover o ultimo owner do tenant.');
				}
			else
				{
				$tenantModel->unlinkUser($tenantId, $userId);
				$this->flash('success', 'Usuario removido do tenant.');
				}
			}

		$this->redirect('tenant/edit/' . $tenantId);
		}

	/**
	 * Tiny flash helper (stored in session and read on the next request)
	 * @param string $type
	 * @param string $message
	 */
	private function flash(string $type, string $message): void
		{
		if (!isset($_SESSION['manager_main']))
			{
			$_SESSION['manager_main'] = [];
			}
		$_SESSION['manager_main']['flash'] = ['type' => $type, 'message' => $message];
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
