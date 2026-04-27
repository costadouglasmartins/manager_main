<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Authorization;
use App\Models\Permission;

/**
 * Handles the user permission management screens and actions.
 *
 * This controller manages permission catalog synchronization, manual catalog
 * creation, user permission assignment, user lookup for the permission screen
 * and orphaned permission reporting/removal.
 */
class PermissionController extends Controller
	{
	/**
	 * Displays the permission management screen.
	 *
	 * Loads the permission catalog and, when a `user_id` is provided, loads the
	 * selected user with their assigned and effective abilities.
	 *
	 * @return void
	 */
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

	/**
	 * Saves the selected permissions for a user.
	 *
	 * Normalizes the posted permission IDs, replaces the user's permission set,
	 * clears the authorization cache and redirects back to the selected user.
	 *
	 * @return void
	 */
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

	/**
	 * Synchronizes the permission catalog from controller actions.
	 *
	 * Scans both the manager_main and tenant manager controllers, inserts any
	 * missing abilities into the catalog, clears authorization cache and
	 * redirects back with the number of newly added permissions.
	 *
	 * @return void
	 */
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

		// Sync from both: manager_main (own) and manager (tenant) controllers.
		// Each call records the source system for the discovered abilities.
		$added  = $permissionModel->syncFromControllers(ROOT_PATH . '/app/Controllers', 'manager_main');
		$added += $permissionModel->syncFromControllers(dirname(ROOT_PATH) . '/manager/app/Controllers', 'manager');

		Authorization::clearCache();

		$qs = 'permission?synced=' . (int) $added;
		if ($backUserId > 0)
			{
			$qs .= '&user_id=' . (int) $backUserId;
			}

		$this->redirect($qs);
		}

	/**
	 * Creates a permission manually in the catalog.
	 *
	 * Reads the posted ability, description and source system, attempts to add
	 * the catalog entry, clears authorization cache and redirects with feedback.
	 *
	 * @return void
	 */
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
		$system = Permission::normalizeSystem($_POST['system'] ?? null);

		$permissionModel = new Permission();
		$ok = $permissionModel->createPermission($ability, $description, $system);

		Authorization::clearCache();

		$qs = 'permission?created=' . ($ok ? '1' : '0');
		if ($backUserId > 0)
			{
			$qs .= '&user_id=' . (int) $backUserId;
			}

		$this->redirect($qs);
		}


	/**
	 * Searches users by name, email or username for the permission screen.
	 *
	 * Route: `GET /permission/searchUsers?q=...`
	 *
	 * @return void
	 */
	public function searchUsers(): void
		{
		$this->requireAuth();

		$q = isset($_GET['q']) ? trim($_GET['q']) : '';

		$permissionModel = new Permission();
		$results = $permissionModel->searchUsers($q, 15);

		$this->json($results);
		}

	/**
	 * Displays the orphaned permission report.
	 *
	 * Route: `GET /permission/orphaned`
	 *
	 * Excludes the internal `permission.manage` ability from the report.
	 *
	 * @return void
	 */
	public function orphaned(): void
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

		$permissionModel = new Permission();
		// Load orphaned permissions except the internal administration ability.
		$stats = $permissionModel->getOrphanedPermissionStatsExcept(['permission.manage']);
		$orphaned = $permissionModel->getOrphanedPermissionsExcept(['permission.manage']);

		$this->view('permissions/orphaned', [
			'orphaned' => $orphaned,
			'stats' => $stats,
			'title' => 'Permissões Órfãs'
			]);
		}

	/**
	 * Deletes an orphaned permission and removes it from every user.
	 *
	 * Route: `POST /permission/deleteOrphaned/{id}`
	 *
	 * The model verifies that the permission is truly orphaned, removes related
	 * `user_permissions` rows, deletes the catalog entry and reports how many
	 * users were affected.
	 *
	 * @param int|string $id Permission ID.
	 * @return void
	 */
	public function deleteOrphaned($id): void
		{
		$this->requirePermission('permission.manage');

		if ($_SERVER['REQUEST_METHOD'] === 'POST')
			{
			$permissionModel = new Permission();

			try
				{
				$result = $permissionModel->deleteOrphanedPermission((int) $id);

				if ($result['success'])
					{
					$qs = 'permission/orphaned?deleted=1&users_affected=' . $result['users_affected'];
					$this->redirect($qs);
					}
				else
					{
					$this->redirect('permission/orphaned?deleted=0&error=not_orphaned');
					}
				}
			catch (\Exception $e)
				{
				$this->redirect('permission/orphaned?deleted=0&error=exception');
				}
			}
		}

	/**
	 * Normalizes posted permission IDs.
	 *
	 * Keeps only positive integer IDs and removes duplicates while preserving the
	 * first occurrence order.
	 *
	 * @param mixed $raw Raw posted `permission_ids` value.
	 * @return array<int, int>
	 */
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
