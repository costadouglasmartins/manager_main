<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Controller;
use App\Models\Tenant;
use App\Services\TenantDeletionService;

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

		$this->view('tenants/edit', [
			'title'       => 'Editar tenant',
			'tenant'      => $tenant,
			'tenantUsers' => $tenantUsers,
		]);
		}

	/**
	 * Update method
	 */
	public function update($id)
		{
		$this->requirePermission('tenant.update');

		$tenantModel = new Tenant();
		$tenantId = (int) $id;
		$data = $_POST;

		if ($this->hasLinkedPersonUserInput($data) && !Authorization::can('tenant.addUser'))
			{
			setFlashMessage('danger', 'Você não tem permissão para adicionar pessoa e usuário ao tenant.');
			$this->redirect('tenant/edit/' . $tenantId);
			return;
			}

		$this->filterTenantUserPermissions($data);

		try
			{
			$result = $tenantModel->updateWithRelations($tenantId, $data);
			$message = !empty($result['user_id'])
				? 'Tenant atualizado e nova pessoa vinculada com sucesso.'
				: 'Tenant atualizado com sucesso.';
			setFlashMessage('success', $message);
			$this->redirect('tenant/edit/' . $tenantId);
			}
		catch (\InvalidArgumentException $e)
			{
			setFlashMessage('danger', $e->getMessage());
			$this->redirect('tenant/edit/' . $tenantId);
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível atualizar o tenant. Verifique os dados e tente novamente.');
			$this->redirect('tenant/edit/' . $tenantId);
			}
		}

	/**
	 * Detect whether the edit form is trying to add a new linked person and user.
	 * @param array<string, mixed> $data Posted tenant edit data.
	 * @return bool
	 */
	private function hasLinkedPersonUserInput(array $data): bool
		{
		$person = is_array($data['linked_person'] ?? null) ? $data['linked_person'] : [];
		$user = is_array($data['linked_user'] ?? null) ? $data['linked_user'] : [];
		$email = is_array($data['linked_email'] ?? null) ? $data['linked_email'] : [];

		foreach ([$person['name'] ?? '', $person['document'] ?? '', $user['username'] ?? '', $user['password'] ?? '', $email['email'] ?? ''] as $value)
			{
			if (trim((string) $value) !== '')
				{
				return true;
				}
			}

		return false;
		}

	/**
	 * Remove relationship fields the current user is not allowed to change.
	 * @param array<string, mixed> $data Posted tenant edit data.
	 * @return void
	 */
	private function filterTenantUserPermissions(array &$data): void
		{
		if (empty($data['tenant_users']) || !is_array($data['tenant_users']))
			{
			return;
			}

		$canUpdateUser = Authorization::can('tenant.updateUser');
		$canRemoveUser = Authorization::can('tenant.removeUser');

		if (!$canUpdateUser && !$canRemoveUser)
			{
			unset($data['tenant_users']);
			return;
			}

		foreach ($data['tenant_users'] as $index => $row)
			{
			if (!is_array($row))
				{
				unset($data['tenant_users'][$index]);
				continue;
				}

			if (!$canUpdateUser)
				{
				unset($data['tenant_users'][$index]['role']);
				}

			if (!$canRemoveUser)
				{
				unset($data['tenant_users'][$index]['_delete']);
				}
			}
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
				setFlashMessage('warning', 'Este usuário já está vinculado a este tenant.');
				}
			else
				{
				$tenantModel->linkUser($tenantId, $userId, $role);
				setFlashMessage('success', 'Usuário vinculado ao tenant com sucesso.');
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
					setFlashMessage('danger', 'Não é possível rebaixar o último owner do tenant.');
					$this->redirect('tenant/edit/' . $tenantId);
					return;
					}
				}

			$tenantModel->updateUserLink($tenantId, $userId, $role);
			setFlashMessage('success', 'Vínculo atualizado com sucesso.');
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
				setFlashMessage('danger', 'Não é possível remover o último owner do tenant.');
				}
			else
				{
				$tenantModel->unlinkUser($tenantId, $userId);
				setFlashMessage('success', 'Usuário removido do tenant.');
				}
			}

		$this->redirect('tenant/edit/' . $tenantId);
		}

	/**
	 * Returns the row-by-row deletion manifest used by the confirmation modal.
	 *
	 * Route: GET tenant/deletionImpact/{tenantId}
	 * @param Integer $id Tenant id.
	 * @return void
	 */
	public function deletionImpact($id): void
		{
		$this->requirePermission('tenant.delete');

		if ($_SERVER['REQUEST_METHOD'] !== 'GET')
			{
			$this->json(['error' => 'method_not_allowed', 'message' => 'Método não permitido.'], 405, ['Allow' => 'GET']);
			}

		$tenantId = (int) $id;
		if ($tenantId <= 0)
			{
			$this->json(['error' => 'invalid_tenant', 'message' => 'Tenant inválido.'], 422);
			}
		if ($tenantId === TenantDeletionService::PROTECTED_TENANT_ID)
			{
			$this->json([
				'error' => 'protected_tenant',
				'message' => 'O tenant principal não pode ser excluído.',
			], 403);
			}

		try
			{
			$impact = (new TenantDeletionService())->impact($tenantId);
			$this->json($impact, 200, ['Cache-Control' => 'no-store, no-cache, must-revalidate']);
			}
		catch (\RuntimeException $e)
			{
			$status = $e->getMessage() === 'Tenant não encontrado.' ? 404 : 500;
			$this->json([
				'error' => $status === 404 ? 'tenant_not_found' : 'impact_unavailable',
				'message' => $status === 404 ? 'Tenant não encontrado.' : 'Não foi possível consultar os registros associados ao tenant.',
			], $status);
			}
		catch (\Throwable $e)
			{
			error_log('TenantController::deletionImpact error: ' . $e->getMessage());
			$this->json([
				'error' => 'impact_unavailable',
				'message' => 'Não foi possível consultar os registros associados ao tenant.',
			], 500);
			}
		}

	/**
	 * Delete method
	 * @param Integer $id Record id
	 */
	public function delete($id)
		{
		$this->requirePermission('tenant.delete');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->redirect('tenant');
			}

		$tenantId = (int) $id;
		if ($tenantId === TenantDeletionService::PROTECTED_TENANT_ID)
			{
			setFlashMessage('danger', 'O tenant principal não pode ser excluído.');
			$this->redirect('tenant');
			}

		if ($tenantId <= 0 || (string) ($_POST['confirm_full_deletion'] ?? '') !== '1')
			{
			setFlashMessage('danger', 'A exclusão integral do tenant não foi confirmada.');
			$this->redirect('tenant');
			}

		try
			{
			(new TenantDeletionService())->delete($tenantId);
			setFlashMessage('success', 'Tenant, base de dados, permissões e registros associados excluídos com sucesso.');
			}
		catch (\Throwable $e)
			{
			error_log('TenantController::delete error for tenant ' . $tenantId . ': ' . $e->getMessage());
			setFlashMessage(
				'danger',
				'Não foi possível concluir a exclusão do tenant. A base dedicada pode já ter sido removida; tente novamente para concluir a limpeza central.'
			);
			}

		$this->redirect('tenant');
		}
	}

?>
