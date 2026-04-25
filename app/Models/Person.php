<?php

namespace App\Models;

use App\Core\Model;
use App\Services\TenantProvisioner;
use PDO;

class Person extends Model
	{

	/**
	 * Get all method
	 * @return associative array
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT * FROM people ORDER BY id DESC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Regroups a flat form array produced by PHP's auto-indexing of name="arr[][key]".
	 *
	 * PHP turns  arr[][a], arr[][b], arr[][c]  into:
	 *   [0 => ['a'=>v], 1 => ['b'=>v], 2 => ['c'=>v]]
	 * This method re-zips them by key into:
	 *   [0 => ['a'=>v, 'b'=>v, 'c'=>v]]
	 *
	 * If the array is already properly indexed (each entry has multiple keys), it is returned as-is.
	 */
	private function regroupFormArray(array $items): array
		{
		// Detect: if any entry has more than one key, assume already grouped
		foreach ($items as $entry)
			{
			if (is_array($entry) && count($entry) > 1)
				{
				return $items;
				}
			}

		// Collect values per key, preserving order
		$byKey = [];
		foreach ($items as $entry)
			{
			if (!is_array($entry))
				{
				continue;
				}
			foreach ($entry as $key => $value)
				{
				$byKey[$key][] = $value;
				}
			}

		if (empty($byKey))
			{
			return [];
			}

		// Zip: the first key determines how many records there are
		$keys  = array_keys($byKey);
		$count = count(reset($byKey));
		$result = [];

		for ($i = 0; $i < $count; $i++)
			{
			$row = [];
			foreach ($keys as $key)
				{
				$row[$key] = $byKey[$key][$i] ?? null;
				}
			$result[] = $row;
			}

		return $result;
		}

	/**
	 * Create method - saves person, user, phones and emails in a single transaction
	 * @param Array $data Data to save
	 * @return array
	 * @throws \Exception
	 */
	public function create($data)
		{

		try
			{
			$this->db->beginTransaction();

			// Save person
			$sqlPerson = "INSERT INTO people
                        (`name`, `fantasy`, `type`, `document`, `active`, `birthdate`, `created`, `modified`)
                      VALUES
                        (:name, :fantasy, :type, :document, :active, :birthdate, :created, :modified)";

			$birthdate = (!empty($data['person']['birthdate'])) ? dateBrToUs($data['person']['birthdate']) : null;

			$stmtPerson = $this->db->prepare($sqlPerson);
			$stmtPerson->execute([
				'name'      => $data['person']['name'],
				'fantasy'   => $data['person']['fantasy'] ?? '',
				'type'      => $data['person']['type'],
				'document'  => onlyDigits($data['person']['document']),
				'active'    => intval($data['person']['active'] ?? 1),
				'birthdate' => $birthdate,
				'created'   => date('Y-m-d H:i:s'),
				'modified'  => date('Y-m-d H:i:s'),
			]);
			$personId = $this->db->lastInsertId();

			// Save user
			$sqlUser = "INSERT INTO users
                      (`person_id`, `username`, `password`, `type`, `active`, `created`, `modified`)
                    VALUES
                      (:person_id, :username, :password, :type, :active, :created, :modified)";
			$stmtUser = $this->db->prepare($sqlUser);
			$stmtUser->execute([
				'person_id' => $personId,
				'username'  => $data['user']['username'],
				'password'  => password_hash($data['user']['password'], PASSWORD_DEFAULT),
				'type'      => $data['user']['type'],
				'active'    => intval($data['user']['active'] ?? 1),
				'created'   => date('Y-m-d H:i:s'),
				'modified'  => date('Y-m-d H:i:s'),
			]);
			$userId = $this->db->lastInsertId();

			// Handle tenant association
			$tenantId = null;
			$tenantMode = $data['tenant']['mode'] ?? 'none';

			if ($tenantMode === 'new')
				{
					$tenantModel = new Tenant();
					$databaseNumber = $tenantModel->nextDatabaseNumber();
					$tenantStatus = 'active';

					$tenantId = $tenantModel->create([
						'name'            => $data['tenant']['name'],
						'fantasy'         => $data['tenant']['fantasy'] ?? null,
						'document'        => $data['tenant']['document'] ?? null,
						'database_number' => $databaseNumber,
						'status'          => 'pending',
						'observations'    => $data['tenant']['observations'] ?? null,
					]);

					$provisioner = new TenantProvisioner();
					$provisioned = $provisioner->provision($databaseNumber);

					if ($provisioned)
						{
						$tenantModel->update($tenantId, [
							'name'         => $data['tenant']['name'],
							'fantasy'      => $data['tenant']['fantasy'] ?? null,
							'document'     => $data['tenant']['document'] ?? null,
							'status'       => $tenantStatus,
							'observations' => $data['tenant']['observations'] ?? null,
						]);
						}
					}
			elseif ($tenantMode === 'existing' && !empty($data['tenant']['existing_id']))
				{
					$tenantId = (int) $data['tenant']['existing_id'];
				}

			// Link user to tenant
			if ($tenantId)
				{
				$stmtTenantLink = $this->db->prepare("UPDATE users SET tenant_id = :tenant_id, modified = :modified WHERE id = :id");
				$stmtTenantLink->execute([
					'tenant_id' => $tenantId,
					'modified'  => date('Y-m-d H:i:s'),
					'id'        => $userId,
				]);

				// Link user to tenant_users with role='owner'
				$tenantModel = new Tenant();
				$tenantModel->linkUser($tenantId, $userId, 'owner', 1);
				}

			// Save phones
			if (!empty($data['phones']) && is_array($data['phones']))
				{
				$data['phones'] = $this->regroupFormArray($data['phones']);
				$sqlPhone = "INSERT INTO phone_numbers (`phone_number`, `observations`, `person_id`, `created`, `modified`)
                             VALUES (:phone_number, :observations, :person_id, :created, :modified)";
				$stmtPhone = $this->db->prepare($sqlPhone);
				foreach ($data['phones'] as $phone)
					{
					if (empty($phone['number']))
						{
						continue;
						}
					$stmtPhone->execute([
						'phone_number' => onlyDigits($phone['number']),
						'observations' => $phone['notes'] ?? null,
						'person_id'    => $personId,
						'created'      => date('Y-m-d H:i:s'),
						'modified'     => date('Y-m-d H:i:s'),
					]);
					}
				}

			// Save addresses
			if (!empty($data['addresses']) && is_array($data['addresses']))
				{
				$data['addresses'] = $this->regroupFormArray($data['addresses']);
				$sqlAddress = "INSERT INTO addresses
								(`zip_code`, `street`, `number`, `observations`, `person_id`, `neighborhood_id`, `created`, `modified`)
							  VALUES
								(:zip_code, :street, :number, :observations, :person_id, :neighborhood_id, :created, :modified)";
				$stmtAddress = $this->db->prepare($sqlAddress);
				foreach ($data['addresses'] as $address)
					{
					if (empty($address['street']) || empty($address['neighborhood_id']))
						{
						continue;
						}
					$stmtAddress->execute([
						'zip_code'        => $address['zip_code'] ?? '',
						'street'          => $address['street'],
						'number'          => $address['number'] ?? '',
						'observations'    => $address['observations'] ?? null,
						'person_id'       => $personId,
						'neighborhood_id' => (int) $address['neighborhood_id'],
						'created'         => date('Y-m-d H:i:s'),
						'modified'        => date('Y-m-d H:i:s'),
					]);
					}
				}

			// Save emails
			if (!empty($data['emails']) && is_array($data['emails']))
				{
				$data['emails'] = $this->regroupFormArray($data['emails']);
				$sqlEmail = "INSERT INTO emails (`email`, `observations`, `is_login`, `person_id`, `created`, `modified`)
                             VALUES (:email, :observations, :is_login, :person_id, :created, :modified)";
				$stmtEmail = $this->db->prepare($sqlEmail);
				foreach ($data['emails'] as $email)
					{
					if (empty($email['address']))
						{
						continue;
						}
					$stmtEmail->execute([
						'email'        => $email['address'],
						'observations' => $email['description'] ?? null,
						'is_login'     => intval($email['is_login'] ?? 0),
						'person_id'    => $personId,
						'created'      => date('Y-m-d H:i:s'),
						'modified'     => date('Y-m-d H:i:s'),
					]);
					}
				}

			$this->db->commit();

			return [
				'person_id' => $personId,
				'user_id'   => $userId,
				'tenant_id' => $tenantId,
			];
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Find method
	 * @param Integer $id Person id
	 * @return array
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare("SELECT * FROM people WHERE id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 * @param Integer $id Person id
	 * @param array $data
	 */
	public function update($id, $data)
		{
		$personId = (int) $id;
		$personData = (isset($data['person']) && is_array($data['person'])) ? $data['person'] : $data;

		try
			{
			$this->db->beginTransaction();

			$this->updatePersonRecord($personId, $personData);

			$userId = $this->upsertPersonUser($personId, $data['user'] ?? null);

			if ($userId !== null && !empty($data['tenant_links']) && is_array($data['tenant_links']))
				{
				$this->saveTenantLinks($userId, $data['tenant_links']);
				}

			if ($userId !== null && isset($data['user']['tenant_id']) && (int) $data['user']['tenant_id'] > 0)
				{
				$this->ensurePrimaryTenantLink($userId, (int) $data['user']['tenant_id']);
				}

			if (!empty($data['addresses']) && is_array($data['addresses']))
				{
				$this->saveAddresses($personId, $data['addresses']);
				}

			if (!empty($data['phones']) && is_array($data['phones']))
				{
				$this->savePhones($personId, $data['phones']);
				}

			if (!empty($data['emails']) && is_array($data['emails']))
				{
				$this->saveEmails($personId, $data['emails']);
				}

			$this->db->commit();
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	private function updatePersonRecord(int $personId, array $data): void
		{
		$stmt = $this->db->prepare("
			UPDATE people
			SET
				`name`      = :name,
				`fantasy`   = :fantasy,
				`type`      = :type,
				`document`  = :document,
				`active`    = :active,
				`birthdate` = :birthdate,
				`modified`  = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'name'      => trim((string) ($data['name'] ?? '')),
			'fantasy'   => $data['fantasy'] ?? '',
			'type'      => $data['type'] ?? 'individual',
			'document'  => onlyDigits($data['document'] ?? ''),
			'active'    => (int) ($data['active'] ?? 0),
			'birthdate' => (!empty($data['birthdate']) ? dateBrToUs($data['birthdate']) : null),
			'modified'  => date('Y-m-d H:i:s'),
			'id'        => $personId,
		]);
		}

	private function upsertPersonUser(int $personId, $data): ?int
		{
		if (!is_array($data))
			{
			return null;
			}

		$userId = (int) ($data['id'] ?? 0);
		$username = trim((string) ($data['username'] ?? ''));
		$password = trim((string) ($data['password'] ?? ''));
		$passwordConfirm = trim((string) ($data['password_confirm'] ?? ''));

		if ($username === '' && $userId === 0)
			{
			return null;
			}

		if ($password !== '' && $password !== $passwordConfirm)
			{
			throw new \InvalidArgumentException('A confirmação de senha não confere.');
			}

		$type = $data['type'] ?? 'user';
		if (!in_array($type, ['root', 'admin', 'user'], true))
			{
			$type = 'user';
			}

		$active = (int) ($data['active'] ?? 1);
		$tenantId = (isset($data['tenant_id']) && $data['tenant_id'] !== '' && (int) $data['tenant_id'] > 0)
			? (int) $data['tenant_id']
			: null;

		if ($userId > 0)
			{
			if ($username === '')
				{
				throw new \InvalidArgumentException('Informe o nome de usuário.');
				}

			$params = [
				'username'  => $username,
				'type'      => $type,
				'active'    => $active,
				'tenant_id' => $tenantId,
				'modified'  => date('Y-m-d H:i:s'),
				'id'        => $userId,
				'person_id' => $personId,
			];

			$passwordSql = '';
			if ($password !== '')
				{
				$passwordSql = ", `password` = :password";
				$params['password'] = password_hash($password, PASSWORD_DEFAULT);
				}

			$stmt = $this->db->prepare("UPDATE users
			                            SET `username`  = :username,
			                                `type`      = :type,
			                                `active`    = :active,
			                                `tenant_id` = :tenant_id,
			                                `modified`  = :modified
			                                {$passwordSql}
			                            WHERE id = :id
			                              AND person_id = :person_id");
			$stmt->execute($params);
			return $userId;
			}

		if ($password === '')
			{
			throw new \InvalidArgumentException('Informe uma senha para criar o usuário.');
			}

		$stmt = $this->db->prepare("INSERT INTO users
		                            (`person_id`, `tenant_id`, `username`, `password`, `type`, `active`, `created`, `modified`)
		                            VALUES
		                            (:person_id, :tenant_id, :username, :password, :type, :active, :created, :modified)");
		$stmt->execute([
			'person_id' => $personId,
			'tenant_id' => $tenantId,
			'username'  => $username,
			'password'  => password_hash($password, PASSWORD_DEFAULT),
			'type'      => $type,
			'active'    => $active,
			'created'   => date('Y-m-d H:i:s'),
			'modified'  => date('Y-m-d H:i:s'),
		]);

		return (int) $this->db->lastInsertId();
		}

	private function ensurePrimaryTenantLink(int $userId, int $tenantId): void
		{
		$tenantModel = new Tenant();
		if (!$tenantModel->hasUser($tenantId, $userId))
			{
			$tenantModel->linkUser($tenantId, $userId, 'user', 1);
			}
		}

	private function saveTenantLinks(int $userId, array $links): void
		{
		$links = $this->regroupFormArray($links);
		$tenantModel = new Tenant();

		foreach ($links as $link)
			{
			if (!is_array($link))
				{
				continue;
				}

			$tenantId = (int) ($link['tenant_id'] ?? 0);
			if ($tenantId <= 0)
				{
				continue;
				}

			if ($this->isDeleteRequested($link))
				{
				$tenantModel->unlinkUser($tenantId, $userId);
				continue;
				}

			if (isset($link['name']) && trim((string) $link['name']) !== '')
				{
				$tenantModel->update($tenantId, [
					'name'         => trim((string) $link['name']),
					'fantasy'      => $link['fantasy'] ?? null,
					'document'     => $link['document'] ?? null,
					'status'       => $link['status'] ?? 'pending',
					'observations' => $link['observations'] ?? null,
				]);
				}

			$role = $link['role'] ?? 'user';
			if (!in_array($role, ['owner', 'admin', 'user'], true))
				{
				$role = 'user';
				}

			$active = (int) ($link['active'] ?? 1);
			if ($tenantModel->hasUser($tenantId, $userId))
				{
				$tenantModel->updateUserLink($tenantId, $userId, $role, $active);
				}
			else
				{
				$tenantModel->linkUser($tenantId, $userId, $role, $active);
				}
			}
		}

	private function saveAddresses(int $personId, array $addresses): void
		{
		$addresses = $this->regroupFormArray($addresses);

		foreach ($addresses as $address)
			{
			if (!is_array($address))
				{
				continue;
				}

			$addressId = (int) ($address['id'] ?? 0);

			if ($this->isDeleteRequested($address))
				{
				if ($addressId > 0)
					{
					$stmt = $this->db->prepare("DELETE FROM addresses WHERE id = :id AND person_id = :person_id");
					$stmt->execute(['id' => $addressId, 'person_id' => $personId]);
					}
				continue;
				}

			$street = trim((string) ($address['street'] ?? ''));
			$neighborhoodId = (int) ($address['neighborhood_id'] ?? 0);
			if ($street === '' || $neighborhoodId <= 0)
				{
				continue;
				}

			$params = [
				'zip_code'        => $address['zip_code'] ?? '',
				'street'          => $street,
				'number'          => $address['number'] ?? '',
				'observations'    => $address['observations'] ?? null,
				'person_id'       => $personId,
				'neighborhood_id' => $neighborhoodId,
				'modified'        => date('Y-m-d H:i:s'),
			];

			if ($addressId > 0)
				{
				$params['id'] = $addressId;
				$stmt = $this->db->prepare("UPDATE addresses
				                            SET `zip_code`        = :zip_code,
				                                `street`          = :street,
				                                `number`          = :number,
				                                `observations`    = :observations,
				                                `neighborhood_id` = :neighborhood_id,
				                                `modified`        = :modified
				                            WHERE id = :id
				                              AND person_id = :person_id");
				$stmt->execute($params);
				continue;
				}

			$params['created'] = date('Y-m-d H:i:s');
			$stmt = $this->db->prepare("INSERT INTO addresses
			                            (`zip_code`, `street`, `number`, `observations`, `person_id`, `neighborhood_id`, `created`, `modified`)
			                            VALUES
			                            (:zip_code, :street, :number, :observations, :person_id, :neighborhood_id, :created, :modified)");
			$stmt->execute($params);
			}
		}

	private function savePhones(int $personId, array $phones): void
		{
		$phones = $this->regroupFormArray($phones);

		foreach ($phones as $phone)
			{
			if (!is_array($phone))
				{
				continue;
				}

			$phoneId = (int) ($phone['id'] ?? 0);

			if ($this->isDeleteRequested($phone))
				{
				if ($phoneId > 0)
					{
					$stmt = $this->db->prepare("DELETE FROM phone_numbers WHERE id = :id AND person_id = :person_id");
					$stmt->execute(['id' => $phoneId, 'person_id' => $personId]);
					}
				continue;
				}

			$number = onlyDigits($phone['number'] ?? ($phone['phone_number'] ?? ''));
			if ($number === '')
				{
				continue;
				}

			$params = [
				'phone_number' => $number,
				'observations' => $phone['notes'] ?? ($phone['observations'] ?? null),
				'person_id'    => $personId,
				'modified'     => date('Y-m-d H:i:s'),
			];

			if ($phoneId > 0)
				{
				$params['id'] = $phoneId;
				$stmt = $this->db->prepare("UPDATE phone_numbers
				                            SET `phone_number` = :phone_number,
				                                `observations` = :observations,
				                                `modified`     = :modified
				                            WHERE id = :id
				                              AND person_id = :person_id");
				$stmt->execute($params);
				continue;
				}

			$params['created'] = date('Y-m-d H:i:s');
			$stmt = $this->db->prepare("INSERT INTO phone_numbers
			                            (`phone_number`, `observations`, `person_id`, `created`, `modified`)
			                            VALUES
			                            (:phone_number, :observations, :person_id, :created, :modified)");
			$stmt->execute($params);
			}
		}

	private function saveEmails(int $personId, array $emails): void
		{
		$emails = $this->regroupFormArray($emails);

		foreach ($emails as $email)
			{
			if (!is_array($email))
				{
				continue;
				}

			$emailId = (int) ($email['id'] ?? 0);

			if ($this->isDeleteRequested($email))
				{
				if ($emailId > 0)
					{
					$stmt = $this->db->prepare("DELETE FROM emails WHERE id = :id AND person_id = :person_id");
					$stmt->execute(['id' => $emailId, 'person_id' => $personId]);
					}
				continue;
				}

			$emailAddress = trim((string) ($email['address'] ?? ($email['email'] ?? '')));
			if ($emailAddress === '')
				{
				continue;
				}

			$params = [
				'email'        => $emailAddress,
				'observations' => $email['description'] ?? ($email['observations'] ?? null),
				'is_login'     => (int) ($email['is_login'] ?? 0),
				'person_id'    => $personId,
				'modified'     => date('Y-m-d H:i:s'),
			];

			if ($emailId > 0)
				{
				$params['id'] = $emailId;
				$stmt = $this->db->prepare("UPDATE emails
				                            SET `email`        = :email,
				                                `observations` = :observations,
				                                `is_login`     = :is_login,
				                                `modified`     = :modified
				                            WHERE id = :id
				                              AND person_id = :person_id");
				$stmt->execute($params);
				continue;
				}

			$params['created'] = date('Y-m-d H:i:s');
			$stmt = $this->db->prepare("INSERT INTO emails
			                            (`email`, `observations`, `is_login`, `person_id`, `created`, `modified`)
			                            VALUES
			                            (:email, :observations, :is_login, :person_id, :created, :modified)");
			$stmt->execute($params);
			}
		}

	private function isDeleteRequested(array $row): bool
		{
		return isset($row['_delete']) && (string) $row['_delete'] === '1';
		}

	/**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = "SELECT * FROM people WHERE 1=1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND `name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['type']) && $filters['type'] !== '')
			{
			$sql .= " AND `type` = :type";
			$params['type'] = $filters['type'];
			}

		if (isset($filters['document']) && $filters['document'] !== '')
			{
			$sql .= " AND document LIKE :document";
			$params['document'] = onlyDigits($filters['document']);
			}

		if (isset($filters['active']) && $filters['active'] !== '')
			{
			$active = (int) $filters['active'];
			if (in_array($active, [0, 1], true))
				{
				$sql .= " AND active = :active";
				$params['active'] = $active;
				}
			}

		if (isset($filters['birthdate']) && $filters['birthdate'] !== '')
			{
			$sql .= " AND birthdate = :birthdate";
			$params['birthdate'] = dateBrToUs($filters['birthdate']);
			}

		$sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

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
	 * @param Array $filters
	 * @return int
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total FROM people WHERE 1=1";
		$params = [];

		if (!empty($filters['id']))
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (!empty($filters['name']))
			{
			$sql .= " AND name LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (!empty($filters['type']))
			{
			$sql .= " AND `type` = :type";
			$params['type'] = $filters['type'];
			}

		if (!empty($filters['document']))
			{
			$sql .= " AND document LIKE :document";
			$params['document'] = onlyDigits($filters['document']);
			}

		if (!empty($filters['birthdate']))
			{
			$sql .= " AND birthdate = :birthdate";
			$params['birthdate'] = dateBrToUs($filters['birthdate']);
			}

		if (isset($filters['active']) && $filters['active'] !== '')
			{
			$active = (int) $filters['active'];
			if (in_array($active, [0, 1], true))
				{
				$sql .= " AND active = :active";
				$params['active'] = $active;
				}
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
	 * Find all by person id - returns person, user, phones, emails, addresses and tenants
	 * @param Integer $id Person id
	 * @return array
	 */
	public function findAllByPersonId($id)
		{
		$personStmt = $this->db->prepare("SELECT * FROM people WHERE id = :id");
		$personStmt->execute(['id' => $id]);
		$person = $personStmt->fetch(PDO::FETCH_ASSOC);

		$userStmt = $this->db->prepare("SELECT id, person_id, tenant_id, username, type, active, created, modified FROM users WHERE person_id = :id");
		$userStmt->execute(['id' => $id]);
		$user = $userStmt->fetch(PDO::FETCH_ASSOC);

		$phonesStmt = $this->db->prepare("SELECT * FROM phone_numbers WHERE person_id = :id ORDER BY id ASC");
		$phonesStmt->execute(['id' => $id]);
		$phones = $phonesStmt->fetchAll(PDO::FETCH_ASSOC);

		$emailsStmt = $this->db->prepare("SELECT * FROM emails WHERE person_id = :id ORDER BY id ASC");
		$emailsStmt->execute(['id' => $id]);
		$emails = $emailsStmt->fetchAll(PDO::FETCH_ASSOC);

		$addressesStmt = $this->db->prepare("SELECT Address.id,
		                                            Address.zip_code,
		                                            Address.street,
		                                            Address.number,
		                                            Address.observations,
		                                            Address.neighborhood_id,
		                                            Neighborhood.name AS neighborhood_name,
		                                            City.name AS city_name,
		                                            State.name AS state_name
		                                     FROM addresses AS Address
		                                     LEFT JOIN neighborhoods AS Neighborhood ON Neighborhood.id = Address.neighborhood_id
		                                     LEFT JOIN cities AS City ON City.id = Neighborhood.city_id
		                                     LEFT JOIN states AS State ON State.id = City.state_id
		                                     WHERE Address.person_id = :id
		                                     ORDER BY Address.id ASC");
		$addressesStmt->execute(['id' => $id]);
		$addresses = $addressesStmt->fetchAll(PDO::FETCH_ASSOC);

		$tenants = [];
		if (!empty($user['id']))
			{
			$tenantsStmt = $this->db->prepare("SELECT Tenant.id,
			                                          Tenant.name,
			                                          Tenant.fantasy,
			                                          Tenant.document,
			                                          Tenant.database_number,
			                                          Tenant.status,
			                                          Tenant.observations,
			                                          TenantUser.role,
			                                          TenantUser.active
			                                   FROM tenant_users AS TenantUser
			                                   INNER JOIN tenants AS Tenant ON Tenant.id = TenantUser.tenant_id
			                                   WHERE TenantUser.user_id = :user_id
			                                   ORDER BY Tenant.name ASC");
			$tenantsStmt->execute(['user_id' => $user['id']]);
			$tenants = $tenantsStmt->fetchAll(PDO::FETCH_ASSOC);
			}

		return [
			'person'    => $person,
			'user'      => $user,
			'phones'    => $phones,
			'emails'    => $emails,
			'addresses' => $addresses,
			'tenants'   => $tenants,
		];
		}

	/**
	 * Get all for select method
	 * Returns all active people with the fields needed to pre-fill the tenant form.
	 * @return array
	 */
	public function getAllForSelect(): array
		{
		$stmt = $this->db->query("SELECT Person.id,
		                                 Person.name,
		                                 Person.fantasy,
		                                 Person.document,
		                                 Person.type
		                          FROM people AS Person
		                          WHERE Person.active = 1
		                          ORDER BY Person.name ASC");
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
		}

	/**
	 * Autocomplete method
	 * @param string $term
	 * @param int $limit
	 * @return array
	 */
	public function autocomplete(string $term, int $limit = 100): array
		{
		$term = trim($term);
		if ($term === '')
			{
			return [];
			}

		$limit = max(1, min(50, (int) $limit));

		$sql = "SELECT id, `name`
						FROM people
						WHERE `name` LIKE :t
						ORDER BY COALESCE(`name`) ASC
						LIMIT {$limit}";

		$stmt = $this->db->prepare($sql);
		$stmt->execute([':t' => '%' . $term . '%']);

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
		}

	/**
	 * Delete method - Deletes tenant_users, user_permissions for all users of person, then users, addresses, emails, phone_numbers before deleting person
	 * @param Integer $id Person id
	 * @throws \Exception
	 */
	public function delete($id): void
		{
		try
			{
			$this->db->beginTransaction();

			// Get all users for this person
			$stmt = $this->db->prepare("SELECT id FROM users WHERE person_id = :id");
			$stmt->execute(['id' => $id]);
			$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// For each user, delete their tenant_users, and user_permissions
			foreach ($users as $user)
				{
				$userId = $user['id'];

				// Delete user_permissions (no dependencies)
				$stmt = $this->db->prepare("DELETE FROM user_permissions WHERE user_id = :user_id");
				$stmt->execute(['user_id' => $userId]);

				// Delete tenant_users (references tenant_id and user_id)
				$stmt = $this->db->prepare("DELETE FROM tenant_users WHERE user_id = :user_id");
				$stmt->execute(['user_id' => $userId]);
				}

			// Delete users
			$stmt = $this->db->prepare("DELETE FROM users WHERE person_id = :id");
			$stmt->execute(['id' => $id]);

			// Delete addresses
			$stmt = $this->db->prepare("DELETE FROM addresses WHERE person_id = :id");
			$stmt->execute(['id' => $id]);

			// Delete emails
			$stmt = $this->db->prepare("DELETE FROM emails WHERE person_id = :id");
			$stmt->execute(['id' => $id]);

			// Delete phone numbers
			$stmt = $this->db->prepare("DELETE FROM phone_numbers WHERE person_id = :id");
			$stmt->execute(['id' => $id]);

			// Delete person
			$stmt = $this->db->prepare("DELETE FROM people WHERE id = :id");
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
