<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Tenant extends Model
	{

	/**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = "SELECT Tenant.id,
		               Tenant.name,
		               Tenant.fantasy,
		               Tenant.document,
		               Tenant.database_number,
		               Tenant.status,
		               Tenant.observations,
		               Tenant.created,
		               Tenant.modified
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
			$sql .= " AND Tenant.name LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
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

		$sql .= " ORDER BY Tenant.id DESC LIMIT :limit OFFSET :offset";

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
			$sql .= " AND Tenant.name LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
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
		$stmt = $this->db->prepare("SELECT Tenant.id,
		                                   Tenant.name,
		                                   Tenant.fantasy,
		                                   Tenant.document,
		                                   Tenant.database_number,
		                                   Tenant.status,
		                                   Tenant.observations,
		                                   Tenant.created,
		                                   Tenant.modified
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
		$stmt = $this->db->prepare("INSERT INTO tenants (name, fantasy, document, database_number, status, observations, created, modified)
		                            VALUES (:name, :fantasy, :document, :database_number, :status, :observations, :created, :modified)");

		$stmt->execute([
			'name'            => $data['name'],
			'fantasy'         => $data['fantasy'] ?? null,
			'document'        => $data['document'] ?? null,
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
		                            SET Tenant.name         = :name,
		                                Tenant.fantasy      = :fantasy,
		                                Tenant.document     = :document,
		                                Tenant.status       = :status,
		                                Tenant.observations = :observations,
		                                Tenant.modified     = :modified
		                            WHERE Tenant.id = :id");

		$stmt->execute([
			'name'         => $data['name'],
			'fantasy'      => $data['fantasy'] ?? null,
			'document'     => $data['document'] ?? null,
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
		$stmt = $this->db->query("SELECT Tenant.id,
		                                 Tenant.name,
		                                 Tenant.database_number
		                          FROM tenants AS Tenant
		                          WHERE Tenant.status = 'active'
		                          ORDER BY Tenant.name ASC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Get all method
	 * Returns all tenants regardless of status.
	 * Used to populate dropdowns in user edit form.
	 */
	public function getAll(): array
		{
		$stmt = $this->db->query("SELECT Tenant.id,
		                                 Tenant.name,
		                                 Tenant.fantasy,
		                                 Tenant.database_number,
		                                 Tenant.status
		                          FROM tenants AS Tenant
		                          ORDER BY Tenant.name ASC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Link user to tenant in tenant_users table
	 * Uses INSERT ... ON DUPLICATE KEY UPDATE so repeated calls update the role/active instead of failing.
	 * @param int $tenantId
	 * @param int $userId
	 * @param string $role (owner, admin, user)
	 * @param int $active
	 * @return void
	 */
	public function linkUser(int $tenantId, int $userId, string $role = 'user', int $active = 1): void
		{
		$stmt = $this->db->prepare("INSERT INTO tenant_users (tenant_id, user_id, role, active, created, modified)
		                            VALUES (:tenant_id, :user_id, :role, :active, :created, :modified)
		                            ON DUPLICATE KEY UPDATE role = VALUES(role),
		                                                    active = VALUES(active),
		                                                    modified = VALUES(modified)");

		$stmt->execute([
			'tenant_id' => $tenantId,
			'user_id'   => $userId,
			'role'      => $role,
			'active'    => $active,
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
	 * Update tenant user link (role / active)
	 * @param int $tenantId
	 * @param int $userId
	 * @param string $role
	 * @param int $active
	 * @return void
	 */
	public function updateUserLink(int $tenantId, int $userId, string $role, int $active): void
		{
		$stmt = $this->db->prepare("UPDATE tenant_users
		                            SET role     = :role,
		                                active   = :active,
		                                modified = :modified
		                            WHERE tenant_id = :tenant_id
		                              AND user_id   = :user_id");

		$stmt->execute([
			'role'      => $role,
			'active'    => $active,
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
		                                   TenantUser.active,
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
