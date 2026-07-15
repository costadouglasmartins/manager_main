<?php

namespace App\Models;

use App\Core\Model;
use App\Services\TenantDeletionService;
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

		$tenantId = (int) $this->db->lastInsertId();
		$tenant = $this->find($tenantId);
		$this->auditCreate('tenant', 'tenants', $tenantId, $tenant, [
			'tenant_id' => $tenantId,
		]);

		return $tenantId;
		}

	/**
	 * Update method
	 */
	public function update(int $id, array $data): void
		{
		$beforeData = $this->find($id);
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

		if (!$this->db->inTransaction())
			{
			$afterData = $this->find($id);
			$this->auditUpdate('tenant', 'tenants', $id, $beforeData, $afterData, [
				'tenant_id' => $id,
			]);
			}
		}

	/**
	 * Update tenant data and every editable relationship submitted by the edit form.
	 * @param int $id Tenant id.
	 * @param array<string, mixed> $data Posted tenant edit data.
	 * @return array<string, int|null>
	 * @throws \Exception When the tenant aggregate cannot be saved.
	 **/
	public function updateWithRelations(int $id, array $data): array
		{
		$beforeData = $this->findAllByTenantId($id);
		try
			{
			$this->db->beginTransaction();

			$this->update($id, [
				'status'       => $data['status'] ?? 'pending',
				'observations' => $data['observations'] ?? null,
			]);

			$result = [
				'person_id' => null,
				'user_id'   => null,
			];

			if ($this->hasLinkedPersonUserInput($data))
				{
				$result = $this->createLinkedPersonUser($id, $data);
				}

			if (!empty($data['tenant_users']) && is_array($data['tenant_users']))
				{
				$this->saveTenantUserRows($id, $data['tenant_users']);
				}

			$this->db->commit();
			$afterData = $this->findAllByTenantId($id);
			$this->auditUpdate('tenant', 'tenants', $id, $beforeData, $afterData, [
				'tenant_id' => $id,
				'affected_tables' => ['tenants', 'tenant_users', 'users', 'people', 'emails'],
				'created_person_id' => $result['person_id'],
				'created_user_id' => $result['user_id'],
				'source' => 'update_with_relations',
			]);
			return $result;
			}
		catch (\Exception $e)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}
			throw $e;
			}
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
		                                   User.tenant_id,
		                                   User.username,
		                                   User.type AS user_type,
		                                   User.active AS user_active,
		                                   Person.id AS person_id,
		                                   Person.name,
		                                   Person.fantasy,
		                                   Person.type AS person_type,
		                                   Person.document,
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
	 * Returns a tenant aggregate snapshot for audit logging.
	 *
	 * @param int $tenantId Tenant ID.
	 * @return array<string, mixed>
	 */
	public function findAllByTenantId(int $tenantId): array
		{
		return [
			'tenant' => $this->find($tenantId),
			'users' => $this->getTenantUsers($tenantId),
		];
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
	 * Synchronize editable tenant-user rows submitted by the tenant form.
	 * @param int $tenantId Tenant id.
	 * @param array<int, mixed> $rows Posted tenant user rows.
	 * @return void
	 */
	private function saveTenantUserRows(int $tenantId, array $rows): void
		{
		$currentRows = $this->getTenantUsers($tenantId);
		$current = [];
		foreach ($currentRows as $row)
			{
			$current[(int) $row['user_id']] = (string) $row['role'];
			}

		$changes = [];
		$final = $current;

		foreach ($rows as $row)
			{
			if (!is_array($row))
				{
				continue;
				}

			$userId = (int) ($row['user_id'] ?? 0);
			if ($userId <= 0 || !array_key_exists($userId, $current))
				{
				continue;
				}

			$remove = !empty($row['_delete']) && (string) $row['_delete'] === '1';
			$role = array_key_exists('role', $row)
				? $this->normalizeTenantRole((string) $row['role'])
				: $current[$userId];

			$changes[$userId] = [
				'remove' => $remove,
				'role'   => $role,
			];

			if ($remove)
				{
				unset($final[$userId]);
				continue;
				}

			$final[$userId] = $role;
			}

		if (!empty($current) && !$this->hasOwnerRole($final))
			{
			throw new \InvalidArgumentException('Mantenha ao menos um owner vinculado ao tenant.');
			}

		foreach ($changes as $userId => $change)
			{
			if (!empty($change['remove']))
				{
				$this->unlinkUser($tenantId, (int) $userId);
				$this->clearPrimaryTenantIfMatches((int) $userId, $tenantId);
				continue;
				}

			$this->updateUserLink($tenantId, (int) $userId, (string) $change['role']);
			$this->ensureUserPrimaryTenantWhenMissing((int) $userId, $tenantId);
			}
		}

	/**
	 * Detect whether the post contains a new linked person/user section.
	 * @param array<string, mixed> $data Posted tenant edit data.
	 * @return bool
	 */
	private function hasLinkedPersonUserInput(array $data): bool
		{
		$person = is_array($data['linked_person'] ?? null) ? $data['linked_person'] : [];
		$user = is_array($data['linked_user'] ?? null) ? $data['linked_user'] : [];
		$email = is_array($data['linked_email'] ?? null) ? $data['linked_email'] : [];

		$fields = [
			$person['name'] ?? '',
			$person['document'] ?? '',
			$user['username'] ?? '',
			$user['password'] ?? '',
			$email['email'] ?? '',
		];

		foreach ($fields as $field)
			{
			if (trim((string) $field) !== '')
				{
				return true;
				}
			}

		return false;
		}

	/**
	 * Create a person, user, optional login e-mail and tenant link.
	 * @param int $tenantId Tenant id.
	 * @param array<string, mixed> $data Posted tenant edit data.
	 * @return array{person_id: int, user_id: int}
	 */
	private function createLinkedPersonUser(int $tenantId, array $data): array
		{
		$person = is_array($data['linked_person'] ?? null) ? $data['linked_person'] : [];
		$user = is_array($data['linked_user'] ?? null) ? $data['linked_user'] : [];
		$email = is_array($data['linked_email'] ?? null) ? $data['linked_email'] : [];
		$link = is_array($data['linked_tenant_user'] ?? null) ? $data['linked_tenant_user'] : [];

		$name = trim((string) ($person['name'] ?? ''));
		$fantasy = trim((string) ($person['fantasy'] ?? ''));
		$personType = $this->normalizePersonType((string) ($person['type'] ?? ''));
		$document = onlyAlphanumeric($person['document'] ?? '');
		$birthdate = trim((string) ($person['birthdate'] ?? ''));
		$username = strtolower(trim((string) ($user['username'] ?? '')));
		$password = trim((string) ($user['password'] ?? ''));
		$passwordConfirm = trim((string) ($user['password_confirm'] ?? ''));
		$userType = $this->normalizeUserType((string) ($user['type'] ?? ''));
		$userActive = (int) ($user['active'] ?? 1);
		$emailAddress = strtolower(trim((string) ($email['email'] ?? '')));
		$emailObservations = trim((string) ($email['observations'] ?? ''));
		$hasCurrentOwner = $this->countOwners($tenantId) > 0;
		$role = $this->normalizeTenantRole((string) ($link['role'] ?? ($hasCurrentOwner ? 'user' : 'owner')));
		if (!$hasCurrentOwner)
			{
			$role = 'owner';
			}

		if ($name === '' || $personType === '' || $document === '')
			{
			throw new \InvalidArgumentException('Preencha os dados obrigatórios da pessoa vinculada.');
			}

		if ($username === '' || $password === '' || $userType === '')
			{
			throw new \InvalidArgumentException('Preencha os dados obrigatórios do usuário vinculado.');
			}

		if (strlen($password) < 8)
			{
			throw new \InvalidArgumentException('A senha do usuário vinculado deve ter pelo menos 8 caracteres.');
			}

		if ($password !== $passwordConfirm)
			{
			throw new \InvalidArgumentException('A confirmação de senha do usuário vinculado não confere.');
			}

		if ($this->documentExists($document))
			{
			throw new \InvalidArgumentException('O documento informado para a pessoa vinculada já está cadastrado.');
			}

		if ($this->usernameExists($username))
			{
			throw new \InvalidArgumentException('O nome de usuário informado já está cadastrado.');
			}

		if ($emailAddress !== '' && $this->emailExists($emailAddress))
			{
			throw new \InvalidArgumentException('O e-mail informado para a pessoa vinculada já está cadastrado.');
			}

		$now = date('Y-m-d H:i:s');
		$stmtPerson = $this->db->prepare("INSERT INTO people
			(`name`, `fantasy`, `type`, `document`, `active`, `birthdate`, `created`, `modified`)
			VALUES
			(:name, :fantasy, :type, :document, :active, :birthdate, :created, :modified)");
		$stmtPerson->execute([
			'name'      => $name,
			'fantasy'   => $fantasy !== '' ? $fantasy : $name,
			'type'      => $personType,
			'document'  => $document,
			'active'    => (int) ($person['active'] ?? 1),
			'birthdate' => $birthdate !== '' ? dateBrToUs($birthdate) : null,
			'created'   => $now,
			'modified'  => $now,
		]);
		$personId = (int) $this->db->lastInsertId();

		$stmtUser = $this->db->prepare("INSERT INTO users
			(`person_id`, `tenant_id`, `username`, `password`, `type`, `active`, `created`, `modified`)
			VALUES
			(:person_id, :tenant_id, :username, :password, :type, :active, :created, :modified)");
		$stmtUser->execute([
			'person_id' => $personId,
			'tenant_id' => $tenantId,
			'username'  => $username,
			'password'  => password_hash($password, PASSWORD_DEFAULT),
			'type'      => $userType,
			'active'    => in_array($userActive, [0, 1], true) ? $userActive : 1,
			'created'   => $now,
			'modified'  => $now,
		]);
		$userId = (int) $this->db->lastInsertId();

		if ($emailAddress !== '')
			{
			$stmtEmail = $this->db->prepare("INSERT INTO emails
				(`email`, `observations`, `is_login`, `person_id`, `created`, `modified`)
				VALUES
				(:email, :observations, :is_login, :person_id, :created, :modified)");
			$stmtEmail->execute([
				'email'        => $emailAddress,
				'observations' => $emailObservations !== '' ? $emailObservations : null,
				'is_login'     => 1,
				'person_id'    => $personId,
				'created'      => $now,
				'modified'     => $now,
			]);
			}

		$this->linkUser($tenantId, $userId, $role);

		return [
			'person_id' => $personId,
			'user_id'   => $userId,
		];
		}

	/**
	 * Check whether at least one submitted tenant user remains owner.
	 * @param array<int, string> $roles Tenant roles keyed by user id.
	 * @return bool
	 */
	private function hasOwnerRole(array $roles): bool
		{
		foreach ($roles as $role)
			{
			if ($role === 'owner')
				{
				return true;
				}
			}

		return false;
		}

	/**
	 * Normalize a tenant role value.
	 * @param string $role Raw role value.
	 * @return string
	 */
	private function normalizeTenantRole(string $role): string
		{
		return in_array($role, ['owner', 'admin', 'user'], true) ? $role : 'user';
		}

	/**
	 * Normalize a user type value.
	 * @param string $type Raw user type value.
	 * @return string
	 */
	private function normalizeUserType(string $type): string
		{
		return in_array($type, ['root', 'admin', 'user'], true) ? $type : 'user';
		}

	/**
	 * Normalize a person type value.
	 * @param string $type Raw person type value.
	 * @return string
	 */
	private function normalizePersonType(string $type): string
		{
		return in_array($type, ['individual', 'company'], true) ? $type : '';
		}

	/**
	 * Check whether a document is already used by another person.
	 * @param string $document Normalized document.
	 * @return bool
	 */
	private function documentExists(string $document): bool
		{
		$stmt = $this->db->prepare("SELECT 1 FROM people WHERE document = :document LIMIT 1");
		$stmt->execute(['document' => $document]);
		return (bool) $stmt->fetchColumn();
		}

	/**
	 * Check whether a username is already used by another user.
	 * @param string $username Normalized username.
	 * @return bool
	 */
	private function usernameExists(string $username): bool
		{
		$stmt = $this->db->prepare("SELECT 1 FROM users WHERE LOWER(username) = :username LIMIT 1");
		$stmt->execute(['username' => strtolower($username)]);
		return (bool) $stmt->fetchColumn();
		}

	/**
	 * Check whether an e-mail address is already used by another person.
	 * @param string $email Normalized e-mail address.
	 * @return bool
	 */
	private function emailExists(string $email): bool
		{
		$stmt = $this->db->prepare("SELECT 1 FROM emails WHERE LOWER(email) = :email LIMIT 1");
		$stmt->execute(['email' => strtolower($email)]);
		return (bool) $stmt->fetchColumn();
		}

	/**
	 * Set the edited tenant as primary only when the user has none.
	 * @param int $userId User id.
	 * @param int $tenantId Tenant id.
	 * @return void
	 */
	private function ensureUserPrimaryTenantWhenMissing(int $userId, int $tenantId): void
		{
		$stmt = $this->db->prepare("UPDATE users
		                            SET tenant_id = :tenant_id,
		                                modified = :modified
		                            WHERE id = :id
		                              AND tenant_id IS NULL");
		$stmt->execute([
			'tenant_id' => $tenantId,
			'modified'  => date('Y-m-d H:i:s'),
			'id'        => $userId,
		]);
		}

	/**
	 * Clear the primary tenant when the removed link points to it.
	 * @param int $userId User id.
	 * @param int $tenantId Tenant id.
	 * @return void
	 */
	private function clearPrimaryTenantIfMatches(int $userId, int $tenantId): void
		{
		$stmt = $this->db->prepare("UPDATE users
		                            SET tenant_id = NULL,
		                                modified = :modified
		                            WHERE id = :id
		                              AND tenant_id = :tenant_id");
		$stmt->execute([
			'modified'  => date('Y-m-d H:i:s'),
			'id'        => $userId,
			'tenant_id' => $tenantId,
		]);
		}

	/**
	 * Delete the complete tenant aggregate and its dedicated database.
	 * @param Integer $id Tenant id
	 * @throws \Exception
	 */
	public function delete($id): void
		{
		(new TenantDeletionService($this->db))->delete((int) $id);
		}
	}

?>
