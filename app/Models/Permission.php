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
	/**
	 * Valores válidos para a coluna `permissions.system`.
	 */
	public const SYSTEMS = ['manager_main', 'manager'];
	public const DEFAULT_SYSTEM = 'manager_main';

	/**
	 * Garante que o valor recebido está no enum de sistemas suportados.
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

	public function getAll(): array
		{
		$stmt = $this->db->query("SELECT id, ability, `system`, description FROM permissions ORDER BY `system` ASC, ability ASC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Retorna todas as permissions EXCETO algumas específicas (ex: permission.manage).
	 * Útil para não exibir permissões administrativas/internas na interface.
	 *
	 * @param array $excludeAbilities Array de abilities a excluir
	 * @return array
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
	 * Returns true if inserted, false if it already existed or ability is invalid.
	 *
	 * @param string $ability     Ability name (ex: "report.index")
	 * @param string $description Optional description
	 * @param string $system      Sistema de origem ('manager_main' ou 'manager')
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
	 * @param string $controllersDir Diretório a ser varrido (.../app/Controllers)
	 * @param string $system         Sistema ao qual as abilities descobertas pertencem
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
	 * @return array
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
	 * Retorna também `tenant_id` (para derivar o sistema de origem do usuário)
	 * e um campo computado `origin_system`:
	 *   - tenant_id IS NULL  -> 'manager_main'
	 *   - tenant_id IS NOT NULL -> 'manager'
	 *
	 * @param int $userId
	 * @return array|null
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

	/**
	 * Retorna permissões que estão no banco mas não existem mais nos controllers
	 * do sistema ao qual pertencem (match por `system` + `ability`).
	 *
	 * Ex.: uma ability marcada como `system='manager'` só é considerada viva
	 * se existir em `manager/app/Controllers`. Se o mesmo nome existir em
	 * `manager_main/app/Controllers`, continua sendo órfã para o sistema
	 * `manager` (pois cada sistema tem seu próprio conjunto).
	 *
	 * @return array Array com permissões órfãs: [['id' => 1, 'ability' => 'report.index', 'system' => 'manager', ...], ...]
	 */
	public function getOrphanedPermissions(): array
		{
		// Descobrir abilities de cada sistema separadamente
		$discovered = [
			'manager_main' => $this->discoverAbilitiesFromControllers(ROOT_PATH . '/app/Controllers'),
			'manager'      => $this->discoverAbilitiesFromControllers(dirname(ROOT_PATH) . '/manager/app/Controllers'),
		];

		// Pegar todas as permissions do banco
		$stmt = $this->db->query("SELECT id, ability, `system`, description, created, modified FROM permissions ORDER BY `system` ASC, ability ASC");
		$allPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

		// Filtrar apenas as que não existem mais no sistema correspondente
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
	 * Retorna permissões órfãs EXCETO algumas específicas.
	 * Útil para não exibir permissões administrativas/internas na interface.
	 *
	 * @param array $excludeAbilities Array de abilities a excluir
	 * @return array
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
	 * Retorna estatísticas das permissões órfãs.
	 * Útil para exibir na interface quantos usuários têm essas permissões.
	 *
	 * @return array Formato: [
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

			// Contar quantos usuários têm essa permission
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
	 * Retorna estatísticas das permissões órfãs EXCETO algumas específicas.
	 * Útil para não exibir permissões administrativas/internas na interface.
	 *
	 * @param array $excludeAbilities Array de abilities a excluir
	 * @return array Mesmo formato que getOrphanedPermissionStats()
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

			// Contar quantos usuários têm essa permission
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
	 * Retorna lista de usuários que possuem uma permissão específica.
	 * Útil para verificar quem será afetado antes de deletar.
	 *
	 * @param int $permissionId ID da permission
	 * @return array Array com usuários: [['id' => 1, 'username' => 'joao', 'name' => 'João Silva', 'email' => 'joao@...'], ...]
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
	 * Deleta uma permissão órfã e remove de todos os usuários.
	 * Usa transação para garantir integridade.
	 *
	 * FLUXO:
	 * 1. Verifica se é realmente órfã
	 * 2. Obtém lista de usuários afetados
	 * 3. Remove de user_permissions (cascata automática)
	 * 4. Deleta a permission
	 *
	 * @param int $permissionId ID da permission a deletar
	 * @return array ['success' => bool, 'users_affected' => count, 'users' => [...]]
	 * @throws \Exception
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

			// Verificar se a permission é realmente órfã
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

			// Obter usuários que têm essa permission (ANTES de deletar)
			$affectedUsers = $this->getUsersWithPermission($permissionId);
			$usersCount = count($affectedUsers);

			// Deletar de user_permissions (remove de todos os usuários)
			$stmt = $this->db->prepare("DELETE FROM user_permissions WHERE permission_id = :id");
			$stmt->execute(['id' => $permissionId]);

			// Deletar a permission
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
	 * Delete method - Deletes user_permissions before deleting permission
	 * @param Integer $id Permission id
	 * @throws \Exception
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
