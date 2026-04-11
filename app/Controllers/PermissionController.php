<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Authorization;
use App\Models\Permission;

class PermissionController extends Controller
	{
	public function index(): void
		{
		// User-only permissions: allow only users that can manage permissions (or root failsafe).
		if (method_exists($this, 'requirePermission'))
			{
			$this->requirePermission('permission.manage');
			}
		else
			{
			$this->requireAuth();
			if (!Authorization::can('permission.manage'))
				{
				header('HTTP/1.1 403 Forbidden');
				echo '403';
				exit;
				}
			}

		$permissionModel = new Permission();

		$permissions = $permissionModel->getAll();

		$selectedUserId = (int) ($_GET['user_id'] ?? 0);

		$selectedUser = null;
		$userPermissionIds = [];
		$effectiveAbilities = [];
		if ($selectedUserId > 0)
			{
			$selectedUser = $permissionModel->findUser($selectedUserId);
			$userPermissionIds = $permissionModel->getPermissionIdsByUser($selectedUserId);
			$effectiveAbilities = $permissionModel->getAbilitiesForUser($selectedUserId);
			}

		$this->view('permissions/index', [
			'permissions' => $permissions,
			'selectedUserId' => $selectedUserId,
			'selectedUser' => $selectedUser,
			'userPermissionIds' => $userPermissionIds,
			'effectiveAbilities' => $effectiveAbilities,
			'title' => 'Permissões'
			]);
		}

	public function saveUser(): void
		{
		if (method_exists($this, 'requirePermission'))
			{
			$this->requirePermission('permission.manage');
			}
		else
			{
			$this->requireAuth();
			if (!Authorization::can('permission.manage'))
				{
				header('HTTP/1.1 403 Forbidden');
				echo '403';
				exit;
				}
			}

		$userId = (int) ($_POST['user_id'] ?? 0);
		$permissionIds = $this->normalizePermissionIds($_POST['permission_ids'] ?? []);

		$permissionModel = new Permission();
		$permissionModel->setPermissionsForUser($userId, $permissionIds);

		Authorization::clearCache();

		$this->redirect('permission?user_id=' . (int) $userId);
		}

	
	public function sync(): void
		{
		if (method_exists($this, 'requirePermission'))
			{
			$this->requirePermission('permission.manage');
			}
		else
			{
			$this->requireAuth();
			if (!Authorization::can('permission.manage'))
				{
				header('HTTP/1.1 403 Forbidden');
				echo '403';
				exit;
				}
			}

		$backUserId = (int) ($_POST['selected_user_id'] ?? 0);

		$permissionModel = new Permission();

		// Sync from both: manager_main (own) and manager (tenant) controllers
		$added  = $permissionModel->syncFromControllers(ROOT_PATH . '/app/Controllers');
		$added += $permissionModel->syncFromControllers(dirname(ROOT_PATH) . '/manager/app/Controllers');

		Authorization::clearCache();

		$qs = 'permission?synced=' . (int) $added;
		if ($backUserId > 0)
			{
			$qs .= '&user_id=' . (int) $backUserId;
			}

		$this->redirect($qs);
		}

	public function create(): void
		{
		if (method_exists($this, 'requirePermission'))
			{
			$this->requirePermission('permission.manage');
			}
		else
			{
			$this->requireAuth();
			if (!Authorization::can('permission.manage'))
				{
				header('HTTP/1.1 403 Forbidden');
				echo '403';
				exit;
				}
			}

		$backUserId = (int) ($_POST['selected_user_id'] ?? 0);

		$ability = (string) ($_POST['ability'] ?? '');
		$description = (string) ($_POST['description'] ?? '');

		$permissionModel = new Permission();
		$ok = $permissionModel->createPermission($ability, $description);

		Authorization::clearCache();

		$qs = 'permission?created=' . ($ok ? '1' : '0');
		if ($backUserId > 0)
			{
			$qs .= '&user_id=' . (int) $backUserId;
			}

		$this->redirect($qs);
		}


	/**
	 * AJAX endpoint: search users by name or email.
	 * GET /permission/searchUsers?q=...
	 */
	public function searchUsers(): void
		{
		$this->requireAuth();

		$q = isset($_GET['q']) ? trim($_GET['q']) : '';

		$permissionModel = new Permission();
		$results = $permissionModel->searchUsers($q, 15);

		$this->json($results);
		}

	private function normalizePermissionIds($raw): array
		{
		if (!is_array($raw))
			{
			return [];
			}

		$out = [];
		foreach ($raw as $v)
			{
			$iv = (int) $v;
			if ($iv > 0)
				{
				$out[] = $iv;
				}
			}
		return array_values(array_unique($out));
		}

	/**
	 * Delete method
	 * @param Integer $id Record id
	 */
	public function delete($id)
		{
		$this->requirePermission('permission.delete');

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$permission = new Permission();
			try {
				$permission->delete($id);
				$this->redirect('permission');
			} catch (\Exception $e) {
				$this->redirect('permission');
			}
		}
		}
	}

?>
