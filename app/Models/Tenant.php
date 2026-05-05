<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Tenant extends Model
	{
	/**
	 * Returns a subquery that selects one representative person for a tenant.
	 *
	 * The owner is preferred, then the oldest link.
	 *
	 * @param string $expression SQL expression from the associated person scope.
	 * @return string
	 */
	private function associatedPersonSql(string $expression): string
		{
		return "(SELECT {$expression}
		          FROM tenant_users AS TenantUserAssoc
		          INNER JOIN users AS UserAssoc ON UserAssoc.id = TenantUserAssoc.user_id
		          INNER JOIN people AS PersonAssoc ON PersonAssoc.id = UserAssoc.person_id
		          WHERE TenantUserAssoc.tenant_id = Tenant.id
		          ORDER BY TenantUserAssoc.role = 'owner' DESC,
		                   TenantUserAssoc.created ASC,
		                   TenantUserAssoc.user_id ASC
		          LIMIT 1)";
		}

	/**
	 * Applies a person-name filter through tenant_users.
	 *
	 * @param string $sql SQL query being built.
	 * @param array<string, mixed> $params Bound parameters.
	 * @param mixed $value Search term.
	 * @return void
	 */
	private function addAssociatedPersonFilter(string &$sql, array &$params, $value): void
		{
		$term = trim((string) $value);
		if ($term === '')
			{
			return;
			}

		$sql .= " AND EXISTS (
		            SELECT 1
		            FROM tenant_users AS TenantUserFilter
		            INNER JOIN users AS UserFilter ON UserFilter.id = TenantUserFilter.user_id
		            INNER JOIN people AS PersonFilter ON PersonFilter.id = UserFilter.person_id
		            WHERE TenantUserFilter.tenant_id = Tenant.id
		              AND (
		                PersonFilter.name LIKE :name
		                OR PersonFilter.fantasy LIKE :name
		                OR PersonFilter.document LIKE :name
		              )
		          )";
		$params['name'] = '%' . $term . '%';
		}

	/**
	 * Select list for tenant rows plus their associated person.
	 *
	 * @return string
	 */
	private function tenantSelectColumns(): string
		{
		$personId = $this->associatedPersonSql('PersonAssoc.id');
		$personName = $this->associatedPersonSql('PersonAssoc.name');
		$personFantasy = $this->associatedPersonSql('PersonAssoc.fantasy');
		$personDocument = $this->associatedPersonSql('PersonAssoc.document');

		return "Tenant.id,
		        {$personId} AS associated_person_id,
		        {$personName} AS associated_person_name,
		        {$personFantasy} AS associated_person_fantasy,
		        {$personDocument} AS associated_person_document,
		        Tenant.database_number,
		        Tenant.status,
		        Tenant.observations,
		        Tenant.created,
		        Tenant.modified";
		}

	/**
	 * Returns sortable columns for list queries.
	 *
	 * @return array<string, string>
	 */
	public static function sortableColumns(): array
		{
		return [
			'id'              => 'Tenant.id',
			'name'            => 'associated_person_name',
			'database_number' => 'Tenant.`database_number`',
			'status'          => 'Tenant.`status`',
		];
		}

	/**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?array $sort = null): array
		{
		$sql = "SELECT " . $this->tenantSelectColumns() . "
		        FROM tenants AS Tenant
		        WHERE 1=1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Tenant.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$this->addAssociatedPersonFilter($sql, $params, $filters['name']);
			}

		if (isset($filters['database_number']) && $filters['database_number'] !== '')
			{
			$sql .= " AND Tenant.database_number LIKE :database_number";
			$params['database_number'] = '%' . $filters['database_number'] . '%';
			}

		if (isset($filters['status']) && $filters['status'] !== '')
			{
			$sql .= " AND Tenant.status = :status";
			$params['status'] = $filters['status'];
			}

		$orderBy = sortOrderBy($sort, self::sortableColumns(), 'id', 'desc');
		$sql .= " ORDER BY {$orderBy} LIMIT :limit OFFSET :offset";

		$stmt = $this->db->prepare($sql);

		foreach ($params as $key => $value)
			{
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue(':' . $key, $value, $paramType);
			}

		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Count method
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total FROM tenants AS Tenant WHERE 1=1";
		$params = [];

		if (!empty($filters['id']))
			{
			$sql .= " AND Tenant.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (!empty($filters['name']))
			{
			$this->addAssociatedPersonFilter($sql, $params, $filters['name']);
			}

		if (!empty($filters['database_number']))
			{
			$sql .= " AND Tenant.database_number LIKE :database_number";
			$params['database_number'] = '%' . $filters['database_number'] . '%';
			}

		if (!empty($filters['status']))
			{
			$sql .= " AND Tenant.status = :status";
			$params['status'] = $filters['status'];
			}

		$stmt = $this->db->prepare($sql);

		foreach ($params as $key => $value)
			{
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue(':' . $key, $value, $paramType);
			}

		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Find method
	 */
	public function find(int $id): ?array
		{
		$stmt = $this->db->prepare("SELECT " . $this->tenantSelectColumns() . "
		                            FROM tenants AS Tenant
		                            WHERE Tenant.id = :id
		                            LIMIT 1");
		$stmt->execute(['id' => $id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
		}

	/**
	 * Next database number method
	 */
	public function nextDatabaseNumber(): string
		{
		$stmt = $this->db->query("SELECT MAX(CAST(Tenant.database_number AS UNSIGNED)) AS max_num FROM tenants AS Tenant");
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$next = ((int) ($row['max_num'] ?? 0)) + 1;
		return str_pad($next, 4, '0', STR_PAD_LEFT);
		}

	/**
	 * Create method
	 */
	public function create(array $data): int
		{
		$now = date('Y-m-d H:i:s');
		$stmt = $this->db->prepare("INSERT INTO tenants (database_number, status, observations, created, modified)
		                            VALUES (:database_number, :status, :observations, :created, :modified)");

		$stmt->execute([
			'database_number' => $data['database_number'],
			'status'          => $data['status'] ?? 'pending',
			'observations'    => $data['observations'] ?? null,
			'created'         => $now,
			'modified'        => $now,
		]);

		return (int) $this->db->lastInsertId();
		}

	/**
	 * Update method
	 */
	public function update(int $id, array $data): void
		{
		$stmt = $this->db->prepare("UPDATE tenants AS Tenant
		                            SET Tenant.status       = :status,
		                                Tenant.observations = :observations,
		                                Tenant.modified     = :modified
		                            WHERE Tenant.id = :id");

		$stmt->execute([
			'status'       => $data['status'] ?? 'pending',
			'observations' => $data['observations'] ?? null,
			'modified'     => date('Y-m-d H:i:s'),
			'id'           => $id,
		]);
		}

	/**
	 * Get all active method
	 * Used by TenantConfig to resolve tenant at login (only active tenants can log in).
	 */
	public function getAllActive(): array
		{
		$stmt = $this->db->query("SELECT " . $this->tenantSelectColumns() . "
		                          FROM tenants AS Tenant
		                          WHERE Tenant.status = 'active'
		                          ORDER BY associated_person_name ASC, Tenant.id ASC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Get all method
	 * Returns all tenants regardless of status.
	 * Used to populate dropdowns in user edit form.
	 */
	public function getAll(): array
		{
		$stmt = $this->db->query("SELECT " . $this->tenantSelectColumns() . "
		                          FROM tenants AS Tenant
		                          ORDER BY associated_person_name ASC, Tenant.id ASC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Link user to tenant in tenant_users table
	 * Uses INSERT ... ON DUPLICATE KEY UPDATE so repeated calls update the role instead of failing.
	 * @param int $tenantId
	 * @param int $userId
	 * @param string $role (owner, admin, user)
	 * @return void
	 */
	public function linkUser(int $tenantId, int $userId, string $role = 'user'): void
		{
		$stmt = $this->db->prepare("INSERT INTO tenant_users (tenant_id, user_id, role, created, modified)
		                            VALUES (:tenant_id, :user_id, :role, :created, :modified)
		                            ON DUPLICATE KEY UPDATE role = VALUES(role),
		                                                    modified = VALUES(modified)");

		$stmt->execute([
			'tenant_id' => $tenantId,
			'user_id'   => $userId,
			'role'      => $role,
			'created'   => date('Y-m-d H:i:s'),
			'modified'  => date('Y-m-d H:i:s'),
		]);
		}

	/**
	 * Unlink user from tenant
	 * @param int $tenantId
	 * @param int $userId
	 * @return void
	 */
	public function unlinkUser(int $tenantId, int $userId): void
		{
		$stmt = $this->db->prepare("DELETE FROM tenant_users
		                            WHERE tenant_id = :tenant_id
		                              AND user_id   = :user_id");

		$stmt->execute([
			'tenant_id' => $tenantId,
			'user_id'   => $userId,
		]);
		}

	/**
	 * Update tenant user link role.
	 * @param int $tenantId
	 * @param int $userId
	 * @param string $role
	 * @return void
	 */
	public function updateUserLink(int $tenantId, int $userId, string $role): void
		{
		$stmt = $this->db->prepare("UPDATE tenant_users
		                            SET role     = :role,
		                                modified = :modified
		                            WHERE tenant_id = :tenant_id
		                              AND user_id   = :user_id");

		$stmt->execute([
			'role'      => $role,
			'modified'  => date('Y-m-d H:i:s'),
			'tenant_id' => $tenantId,
			'user_id'   => $userId,
		]);
		}

	/**
	 * Get tenant users with their roles
	 * @param int $tenantId
	 * @return array
	 */
	public function getTenantUsers(int $tenantId): array
		{
		$stmt = $this->db->prepare("SELECT TenantUser.user_id,
		                                   TenantUser.role,
		                                   TenantUser.created,
		                                   TenantUser.modified,
		                                   User.username,
		                                   User.type AS user_type,
		                                   User.active AS user_active,
		                                   Person.name,
		                                   (SELECT Email.email
		                                      FROM emails AS Email
		                                      WHERE Email.person_id = Person.id
		                                        AND Email.is_login = 1
		                                      ORDER BY Email.id ASC
		                                      LIMIT 1) AS email
		                            FROM tenant_users AS TenantUser
		                            INNER JOIN users AS User ON TenantUser.user_id = User.id
		                            INNER JOIN people AS Person ON User.person_id = Person.id
		                            WHERE TenantUser.tenant_id = :tenant_id
		                            ORDER BY TenantUser.role = 'owner' DESC,
		                                     TenantUser.role = 'admin' DESC,
		                                     Person.name ASC");

		$stmt->execute(['tenant_id' => $tenantId]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Get users that are NOT yet linked to the given tenant
	 * Useful to populate the "add user" dropdown in the tenant edit page.
	 * @param int $tenantId
	 * @return array
	 */
	public function getAvailableUsers(int $tenantId): array
		{
		$stmt = $this->db->prepare("SELECT User.id,
		                                   User.username,
		                                   User.type,
		                                   User.active,
		                                   Person.name
		                            FROM users AS User
		                            INNER JOIN people AS Person ON User.person_id = Person.id
		                            WHERE User.active = 1
		                              AND User.id NOT IN (
		                                SELECT TenantUser.user_id
		                                FROM tenant_users AS TenantUser
		                                WHERE TenantUser.tenant_id = :tenant_id
		                              )
		                            ORDER BY Person.name ASC");

		$stmt->execute(['tenant_id' => $tenantId]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Check if a tenant_users record exists
	 * @param int $tenantId
	 * @param int $userId
	 * @return bool
	 */
	public function hasUser(int $tenantId, int $userId): bool
		{
		$stmt = $this->db->prepare("SELECT 1 FROM tenant_users
		                            WHERE tenant_id = :tenant_id
		                              AND user_id   = :user_id
		                            LIMIT 1");

		$stmt->execute([
			'tenant_id' => $tenantId,
			'user_id'   => $userId,
		]);

		return (bool) $stmt->fetchColumn();
		}

	/**
	 * Count owners of a tenant
	 * Useful to prevent removing the last owner.
	 * @param int $tenantId
	 * @return int
	 */
	public function countOwners(int $tenantId): int
		{
		$stmt = $this->db->prepare("SELECT COUNT(*) FROM tenant_users
		                            WHERE tenant_id = :tenant_id
		                              AND role = 'owner'");

		$stmt->execute(['tenant_id' => $tenantId]);
		return (int) $stmt->fetchColumn();
		}

	/**
	 * Delete method - Deletes tenant_users, then updates users (set tenant_id to NULL) before deleting tenant
	 * @param Integer $id Tenant id
	 * @throws \Exception
	 */
	public function delete($id): void
		{
		try
			{
			$this->db->beginTransaction();

			// Delete tenant_users
			$stmt = $this->db->prepare("DELETE FROM tenant_users WHERE tenant_id = :id");
			$stmt->execute(['id' => $id]);

			// Update users to set tenant_id to NULL
			$stmt = $this->db->prepare("UPDATE users SET tenant_id = NULL WHERE tenant_id = :id");
			$stmt->execute(['id' => $id]);

			// Delete tenant
			$stmt = $this->db->prepare("DELETE FROM tenants WHERE id = :id");
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
