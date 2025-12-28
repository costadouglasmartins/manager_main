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
		$users = $permissionModel->getUsers();

		$selectedUserId = (int) ($_GET['user_id'] ?? 0);

		$userPermissionIds = [];
		$effectiveAbilities = [];
		if ($selectedUserId > 0)
			{
			$userPermissionIds = $permissionModel->getPermissionIdsByUser($selectedUserId);
			$effectiveAbilities = $permissionModel->getAbilitiesForUser($selectedUserId);
			}

		$this->view('permissions/index', [
			'permissions' => $permissions,
			'users' => $users,
			'selectedUserId' => $selectedUserId,
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
		$added = $permissionModel->syncFromControllers(ROOT_PATH . '/app/Controllers');

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
	}

?>
