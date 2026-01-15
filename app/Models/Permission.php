<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * User-only permissions model (no groups/roles).
 *
 * Tables:
 * - permissions (catalog)
 * - user_permissions (pivot: user_id x permission_id)
 */
class Permission extends Model
	{
	public function getAll(): array
		{
		$stmt = $this->db->query("SELECT id, ability, description FROM permissions ORDER BY ability ASC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Creates a permission in the catalog.
	 * Returns true if inserted, false if it already existed or ability is invalid.
	 */
	public function createPermission(string $ability, string $description = ''): bool
		{
		$ability = trim($ability);
		$description = trim($description);

		if ($ability === '')
			{
			return false;
			}

		$stmt = $this->db->prepare("INSERT IGNORE INTO permissions (ability, description) VALUES (:ability, :description)");
		$stmt->execute([
			'ability' => $ability,
			'description' => ($description === '' ? null : $description)
			]);

		return ($stmt->rowCount() > 0);
		}

	/**
	 * Sync permission catalog by discovering controller actions.
	 * It will INSERT missing abilities into permissions table (INSERT IGNORE).
	 *
	 * Returns the count of new permissions inserted.
	 */
	public function syncFromControllers(string $controllersDir): int
		{
		$discovered = $this->discoverAbilitiesFromControllers($controllersDir);

		$added = 0;
		$stmt = $this->db->prepare("INSERT IGNORE INTO permissions (ability, description) VALUES (:ability, :description)");

		foreach ($discovered as $ability => $description)
			{
			$stmt->execute([
				'ability' => $ability,
				'description' => $description
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
			$module = strtolower((string) $controllerName);

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


	public function getUsers(): array
		{
		$stmt = $this->db->query("SELECT 	User.id,
                                      User.username,
                                      Email.email
                              FROM users AS User
                              INNER JOIN people AS Person ON Person.id = User.person_id
                              INNER JOIN emails AS Email ON Email.person_id = Person.id
                              WHERE User.active = '1'
                              LIMIT 1");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

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
	 * Sets the permissions for a user (full replace).
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
					INSERT INTO user_permissions (user_id, permission_id)
					VALUES (:user_id, :permission_id)
				");

				foreach ($permissionIds as $pid)
					{
					$pid = (int) $pid;
					if ($pid > 0)
						{
						$ins->execute([
							'user_id' => $userId,
							'permission_id' => $pid
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
	 */
	public function setExtraPermissionsForUser(int $userId, array $permissionIds): void
		{
		$this->setPermissionsForUser($userId, $permissionIds);
		}

	/**
	 * Returns the user's abilities (strings) based only on user_permissions.
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
	 */
	public function getEffectiveAbilitiesForUser(int $userId): array
		{
		return $this->getAbilitiesForUser($userId);
		}
	}

?>
