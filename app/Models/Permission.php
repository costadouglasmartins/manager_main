<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * User-only permissions model (no groups/roles).
 *
 * The permission catalog is split by source system so the central manager and
 * tenant manager can keep separate ability sets even when action names match.
 *
 * Tables:
 * - permissions (catalog)
 * - user_permissions (pivot: user_id x permission_id)
 */
class Permission extends Model
	{
	/**
	 * Supported values for the `permissions.system` column.
	 */
	public const SYSTEMS = ['manager_main', 'manager'];

	/**
	 * Fallback source system used when an incoming value is missing or invalid.
	 */
	public const DEFAULT_SYSTEM = 'manager_main';

	/**
	 * Normalizes a source system value to one of the supported systems.
	 *
	 * @param string|null $system Incoming source system.
	 * @return string Supported source system.
	 */
	public static function normalizeSystem(?string $system): string
		{
		$system = (string) $system;
		if (in_array($system, self::SYSTEMS, true))
			{
			return $system;
			}
		return self::DEFAULT_SYSTEM;
		}

	/**
	 * Returns every permission in the catalog ordered by system and ability.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getAll(): array
		{
		$stmt = $this->db->query("SELECT id, ability, `system`, description FROM permissions ORDER BY `system` ASC, ability ASC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns all permissions except the provided abilities.
	 *
	 * Useful for hiding administrative or internal permissions from the UI.
	 *
	 * @param array<int, string> $excludeAbilities Abilities to exclude.
	 * @return array<int, array<string, mixed>>
	 */
	public function getAllExcept(array $excludeAbilities = []): array
		{
		$all = $this->getAll();

		if (empty($excludeAbilities))
			{
			return $all;
			}

		$filtered = [];
		foreach ($all as $perm)
			{
			if (!in_array($perm['ability'], $excludeAbilities, true))
				{
				$filtered[] = $perm;
				}
			}

		return $filtered;
		}

	/**
	 * Creates a permission in the catalog.
	 *
	 * Returns true if inserted, false if it already existed or ability is invalid.
	 *
	 * @param string $ability     Ability name (ex: "report.index")
	 * @param string $description Optional description
	 * @param string $system      Source system (`manager_main` or `manager`).
	 * @return bool
	 */
	public function createPermission(string $ability, string $description = '', string $system = self::DEFAULT_SYSTEM): bool
		{
		$ability = trim($ability);
		$description = trim($description);
		$system = self::normalizeSystem($system);

		if ($ability === '')
			{
			return false;
			}

		$stmt = $this->db->prepare("INSERT IGNORE INTO permissions (ability, `system`, description, created, modified) VALUES (:ability, :system, :description, :created, :modified)");
		$stmt->execute([
			'ability' => $ability,
			'system' => $system,
			'description' => ($description === '' ? null : $description),
			'created' => date('Y-m-d H:i:s'),
			'modified' => date('Y-m-d H:i:s')
			]);

		return ($stmt->rowCount() > 0);
		}

	/**
	 * Sync permission catalog by discovering controller actions.
	 * It will INSERT missing abilities into permissions table (INSERT IGNORE).
	 *
	 * Returns the count of new permissions inserted.
	 *
	 * @param string $controllersDir Controllers directory to scan.
	 * @param string $system         Source system assigned to discovered abilities.
	 * @return int Number of inserted permissions.
	 */
	public function syncFromControllers(string $controllersDir, string $system = self::DEFAULT_SYSTEM): int
		{
		$system = self::normalizeSystem($system);
		$discovered = $this->discoverAbilitiesFromControllers($controllersDir);

		$added = 0;
		$stmt = $this->db->prepare("INSERT IGNORE INTO permissions (ability, `system`, description, created, modified) VALUES (:ability, :system, :description, :created, :modified)");

		foreach ($discovered as $ability => $description)
			{
			$stmt->execute([
				'ability' => $ability,
				'system' => $system,
				'description' => $description,
				'created' => date('Y-m-d H:i:s'),
				'modified' => date('Y-m-d H:i:s')
				]);

			if ($stmt->rowCount() > 0)
				{
				$added++;
				}
			}

		return $added;
		}

	/**
	 * Discovers abilities by parsing controllers and their public methods.
	 * Ability format: <module>.<action>
	 * - module: controller name lowercased without "Controller"
	 * - action: public method name
	 *
	 * @param string $controllersDir Controllers directory to scan.
	 * @return array<string, string> Map of ability names to descriptions.
	 */
	private function discoverAbilitiesFromControllers(string $controllersDir): array
		{
		$controllersDir = rtrim($controllersDir, '/');

		$abilities = [];

		$files = glob($controllersDir . '/*Controller.php');
		if (!is_array($files))
			{
			return $abilities;
			}

		foreach ($files as $file)
			{
			$base = basename($file);

			// Ignore internal controllers (keep catalog focused on business modules)
			if ($base === 'PermissionController.php')
				{
				continue;
				}

			$controllerName = preg_replace('/Controller\.php$/', '', $base);
			$module = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', (string) $controllerName));

			$code = @file_get_contents($file);
			if (!is_string($code) || $code === '')
				{
				continue;
				}

			if (preg_match_all('/public\s+function\s+([a-zA-Z0-9_]+)\s*\(/', $code, $m))
				{
				foreach ($m[1] as $method)
					{
					$method = (string) $method;

					if ($method === '__construct')
						{
						continue;
						}

					if (strpos($method, '_') === 0)
						{
						continue;
						}

					$ability = $module . '.' . $method;

					// Simple default description
					$description = 'Acesso: ' . $module . ' -> ' . $method;

					$abilities[$ability] = $description;
					}
				}
			}

		ksort($abilities);
		return $abilities;
		}

	/**
	 * Returns active users with their login email.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getUsers(): array
		{
		$stmt = $this->db->query("SELECT 	User.id,
                                      User.username,
                                      Email.email
                              FROM users AS User
                              INNER JOIN people AS Person ON Person.id = User.person_id
                              INNER JOIN emails AS Email ON Email.person_id = Person.id
                              WHERE User.active = '1' 
                              AND Email.is_login = '1'");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns permission IDs currently assigned to a user.
	 *
	 * @param int $userId User ID.
	 * @return array<int, int>
	 */
	public function getPermissionIdsByUser(int $userId): array
		{
		$stmt = $this->db->prepare("
			SELECT permission_id
			FROM user_permissions
			WHERE user_id = :user_id
		");
		$stmt->execute(['user_id' => $userId]);

		$ids = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
			{
			$ids[] = (int) $row['permission_id'];
			}
		return $ids;
		}

	/**
	 * Search users by name or email (for autocomplete).
	 * Returns up to $limit results matching the query.
	 *
	 * @param string $query Search term
	 * @param int $limit Max results
	 * @return array<int, array<string, mixed>>
	 */
	public function searchUsers(string $query, int $limit = 15): array
		{
		$query = trim($query);

		if ($query === '')
			{
			return [];
			}

		$stmt = $this->db->prepare("
			SELECT  User.id,
			        User.username,
			        User.type,
			        Email.email,
			        Person.name
			FROM users AS User
			INNER JOIN people AS Person ON Person.id = User.person_id
			INNER JOIN emails AS Email ON Email.person_id = Person.id
			WHERE User.active = '1'
			  AND Email.is_login = '1'
			  AND (
			    Person.name LIKE :q
			    OR Email.email LIKE :q2
			    OR User.username LIKE :q3
			  )
			ORDER BY Person.name ASC
			LIMIT :lim
		");

		$like = '%' . $query . '%';
		$stmt->bindValue(':q',   $like, PDO::PARAM_STR);
		$stmt->bindValue(':q2',  $like, PDO::PARAM_STR);
		$stmt->bindValue(':q3',  $like, PDO::PARAM_STR);
		$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Find a single user by ID (for displaying selected user info).
	 *
	 * Also returns `tenant_id` to derive the user's source system and adds the
	 * computed `origin_system` field:
	 *   - tenant_id IS NULL  -> 'manager_main'
	 *   - tenant_id IS NOT NULL -> 'manager'
	 *
	 * @param int $userId User ID.
	 * @return array<string, mixed>|null
	 */
	public function findUser(int $userId): ?array
		{
		$stmt = $this->db->prepare("
			SELECT  User.id,
			        User.username,
			        User.type,
			        User.tenant_id,
			        Email.email,
			        Person.name
			FROM users AS User
			INNER JOIN people AS Person ON Person.id = User.person_id
			INNER JOIN emails AS Email ON Email.person_id = Person.id
			WHERE User.id = :id
			  AND Email.is_login = '1'
			LIMIT 1
		");
		$stmt->execute(['id' => $userId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$row)
			{
			return null;
			}

		$row['origin_system'] = (empty($row['tenant_id']) ? 'manager_main' : 'manager');
		return $row;
		}

	/**
	 * Sets the permissions for a user (full replace).
	 *
	 * @param int $userId User ID.
	 * @param array<int, int|string> $permissionIds Permission IDs to assign.
	 * @return void
	 * @throws \Throwable If the transaction fails.
	 */
	public function setPermissionsForUser(int $userId, array $permissionIds): void
		{
		if ($userId <= 0)
			{
			return;
			}

		$this->db->beginTransaction();

		try
			{
			$del = $this->db->prepare("DELETE FROM user_permissions WHERE user_id = :user_id");
			$del->execute(['user_id' => $userId]);

			if (!empty($permissionIds))
				{
				$ins = $this->db->prepare("
					INSERT INTO user_permissions (user_id, permission_id, created, modified)
					VALUES (:user_id, :permission_id, :created, :modified)
				");

				foreach ($permissionIds as $pid)
					{
					$pid = (int) $pid;
					if ($pid > 0)
						{
						$ins->execute([
							'user_id' => $userId,
							'permission_id' => $pid,
              'created' => date('Y-m-d H:i:s'),
              'modified' => date('Y-m-d H:i:s')
							]);
						}
					}
				}

			$this->db->commit();
			}
		catch (\Throwable $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Backwards-compatible alias (older controller name).
	 *
	 * @param int $userId User ID.
	 * @param array<int, int|string> $permissionIds Permission IDs to assign.
	 * @return void
	 */
	public function setExtraPermissionsForUser(int $userId, array $permissionIds): void
		{
		$this->setPermissionsForUser($userId, $permissionIds);
		}

	/**
	 * Returns the user's abilities (strings) based only on user_permissions.
	 *
	 * @param int $userId User ID.
	 * @return array<int, string>
	 */
	public function getAbilitiesForUser(int $userId): array
		{
		$stmt = $this->db->prepare("
			SELECT DISTINCT p.ability
			FROM user_permissions up
			INNER JOIN permissions p ON p.id = up.permission_id
			WHERE up.user_id = :user_id
			ORDER BY p.ability ASC
		");
		$stmt->execute(['user_id' => $userId]);

		$abilities = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
			{
			$abilities[] = (string) $row['ability'];
			}
		return $abilities;
		}

	/**
	 * Backwards-compatible alias used by Authorization in earlier versions.
	 *
	 * @param int $userId User ID.
	 * @return array<int, string>
	 */
	public function getEffectiveAbilitiesForUser(int $userId): array
		{
		return $this->getAbilitiesForUser($userId);
		}

	/**
	 * Returns permissions stored in the database but no longer present in controllers.
	 *
	 * Matching is done by `system` and `ability`. For example, an ability marked
	 * as `system='manager'` is considered alive only when it exists in
	 * `manager/app/Controllers`; the same ability name in `manager_main` does not
	 * make the `manager` permission valid because each system owns its catalog.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getOrphanedPermissions(): array
		{
		// Discover abilities for each source system separately.
		$discovered = [
			'manager_main' => $this->discoverAbilitiesFromControllers(ROOT_PATH . '/app/Controllers'),
			'manager'      => $this->discoverAbilitiesFromControllers(dirname(ROOT_PATH) . '/manager/app/Controllers'),
		];

		// Load every permission stored in the catalog.
		$stmt = $this->db->query("SELECT id, ability, `system`, description, created, modified FROM permissions ORDER BY `system` ASC, ability ASC");
		$allPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

		// Keep only permissions that no longer exist in their own source system.
		$orphaned = [];
		foreach ($allPermissions as $perm)
			{
			$sys = (string) ($perm['system'] ?? self::DEFAULT_SYSTEM);
			$set = $discovered[$sys] ?? [];

			if (!isset($set[$perm['ability']]))
				{
				$orphaned[] = $perm;
				}
			}

		return $orphaned;
		}

	/**
	 * Returns orphaned permissions except the provided abilities.
	 *
	 * Useful for hiding administrative or internal permissions from the UI.
	 *
	 * @param array<int, string> $excludeAbilities Abilities to exclude.
	 * @return array<int, array<string, mixed>>
	 */
	public function getOrphanedPermissionsExcept(array $excludeAbilities = []): array
		{
		$orphaned = $this->getOrphanedPermissions();

		if (empty($excludeAbilities))
			{
			return $orphaned;
			}

		$filtered = [];
		foreach ($orphaned as $perm)
			{
			if (!in_array($perm['ability'], $excludeAbilities, true))
				{
				$filtered[] = $perm;
				}
			}

		return $filtered;
		}

	/**
	 * Returns statistics for orphaned permissions.
	 *
	 * Used by the UI to show how many users still have each orphaned permission.
	 *
	 * @return array{total_orphaned: int, permissions: array<int, array<string, mixed>>} Format:
	 * [
	 *   'total_orphaned' => 2,
	 *   'permissions' => [
	 *     1 => ['ability' => 'report.index', 'user_count' => 3],
	 *     5 => ['ability' => 'dashboard.old', 'user_count' => 0]
	 *   ]
	 * ]
	 */
	public function getOrphanedPermissionStats(): array
		{
		$orphaned = $this->getOrphanedPermissions();

		$stats = [
			'total_orphaned' => count($orphaned),
			'permissions' => []
		];

		foreach ($orphaned as $perm)
			{
			$permId = (int) $perm['id'];

			// Count how many users still have this permission.
			$stmt = $this->db->prepare("
				SELECT COUNT(*) as cnt
				FROM user_permissions
				WHERE permission_id = :pid
			");
			$stmt->execute(['pid' => $permId]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$userCount = (int) ($row['cnt'] ?? 0);

			$stats['permissions'][$permId] = [
				'ability' => $perm['ability'],
				'system' => $perm['system'] ?? self::DEFAULT_SYSTEM,
				'description' => $perm['description'],
				'user_count' => $userCount
			];
			}

		return $stats;
		}

	/**
	 * Returns orphaned permission statistics except the provided abilities.
	 *
	 * Useful for hiding administrative or internal permissions from the UI.
	 *
	 * @param array<int, string> $excludeAbilities Abilities to exclude.
	 * @return array{total_orphaned: int, permissions: array<int, array<string, mixed>>} Same format as getOrphanedPermissionStats().
	 */
	public function getOrphanedPermissionStatsExcept(array $excludeAbilities = []): array
		{
		$orphaned = $this->getOrphanedPermissionsExcept($excludeAbilities);

		$stats = [
			'total_orphaned' => count($orphaned),
			'permissions' => []
		];

		foreach ($orphaned as $perm)
			{
			$permId = (int) $perm['id'];

			// Count how many users still have this permission.
			$stmt = $this->db->prepare("
				SELECT COUNT(*) as cnt
				FROM user_permissions
				WHERE permission_id = :pid
			");
			$stmt->execute(['pid' => $permId]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$userCount = (int) ($row['cnt'] ?? 0);

			$stats['permissions'][$permId] = [
				'ability' => $perm['ability'],
				'system' => $perm['system'] ?? self::DEFAULT_SYSTEM,
				'description' => $perm['description'],
				'user_count' => $userCount
			];
			}

		return $stats;
		}

	/**
	 * Returns users that currently have a specific permission.
	 *
	 * Useful for previewing who will be affected before deleting an orphaned
	 * permission.
	 *
	 * @param int $permissionId Permission ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function getUsersWithPermission(int $permissionId): array
		{
		if ($permissionId <= 0)
			{
			return [];
			}

		$stmt = $this->db->prepare("
			SELECT DISTINCT
				u.id,
				u.username,
				p.name,
				e.email
			FROM user_permissions up
			INNER JOIN users u ON u.id = up.user_id
			INNER JOIN people p ON p.id = u.person_id
			INNER JOIN emails e ON e.person_id = p.id
			WHERE up.permission_id = :pid
			  AND e.is_login = '1'
			ORDER BY p.name ASC
		");
		$stmt->execute(['pid' => $permissionId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Deletes an orphaned permission and removes it from every user.
	 *
	 * Uses a transaction to verify the permission is truly orphaned, capture the
	 * affected users, remove `user_permissions` rows and delete the catalog entry
	 * as a single operation.
	 *
	 * @param int $permissionId Permission ID to delete.
	 * @return array{success: bool, users_affected: int, users: array<int, array<string, mixed>>}
	 * @throws \Exception If the transaction fails.
	 */
	public function deleteOrphanedPermission(int $permissionId): array
		{
		if ($permissionId <= 0)
			{
			return ['success' => false, 'users_affected' => 0, 'users' => []];
			}

		try
			{
			$this->db->beginTransaction();

			// Verify that the permission is still orphaned before deleting it.
			$orphaned = $this->getOrphanedPermissions();
			$isOrphaned = false;
			foreach ($orphaned as $perm)
				{
				if ((int) $perm['id'] === $permissionId)
					{
					$isOrphaned = true;
					break;
					}
				}

			if (!$isOrphaned)
				{
				$this->db->rollBack();
				return ['success' => false, 'users_affected' => 0, 'users' => []];
				}

			// Capture affected users before removing the permission.
			$affectedUsers = $this->getUsersWithPermission($permissionId);
			$usersCount = count($affectedUsers);

			// Remove the permission from every user.
			$stmt = $this->db->prepare("DELETE FROM user_permissions WHERE permission_id = :id");
			$stmt->execute(['id' => $permissionId]);

			// Delete the catalog entry.
			$stmt = $this->db->prepare("DELETE FROM permissions WHERE id = :id");
			$stmt->execute(['id' => $permissionId]);

			$this->db->commit();

			return [
				'success' => true,
				'users_affected' => $usersCount,
				'users' => $affectedUsers
			];
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Deletes a permission and its user assignments.
	 *
	 * @param int|string $id Permission ID.
	 * @return void
	 * @throws \Exception If the transaction fails.
	 */
	public function delete($id): void
		{
		try
			{
			$this->db->beginTransaction();

			// Delete user permissions
			$stmt = $this->db->prepare("DELETE FROM user_permissions WHERE permission_id = :id");
			$stmt->execute(['id' => $id]);

			// Delete permission
			$stmt = $this->db->prepare("DELETE FROM permissions WHERE id = :id");
			$stmt->execute(['id' => $id]);

			$this->db->commit();
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}
	}

?>
