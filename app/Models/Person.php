<?php

namespace App\Models;

use App\Core\Model;
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

			// Save phones
			if (!empty($data['phones']) && is_array($data['phones']))
				{
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

			// Save emails
			if (!empty($data['emails']) && is_array($data['emails']))
				{
				$sqlEmail = "INSERT INTO emails (`email`, `observations`, `person_id`, `created`, `modified`)
                             VALUES (:email, :observations, :person_id, :created, :modified)";
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
			'name'      => $data['name'],
			'fantasy'   => $data['fantasy'] ?? '',
			'type'      => $data['type'],
			'document'  => onlyDigits($data['document']),
			'active'    => $data['active'],
			'birthdate' => (!empty($data['birthdate']) ? dateBrToUs($data['birthdate']) : null),
			'modified'  => date('Y-m-d H:i:s'),
			'id'        => $id,
		]);
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
	 * Find all by person id - returns person, user, phones and emails
	 * @param Integer $id Person id
	 * @return array
	 */
	public function findAllByPersonId($id)
		{
		$personStmt = $this->db->prepare("SELECT * FROM people WHERE id = :id");
		$personStmt->execute(['id' => $id]);
		$person = $personStmt->fetch(PDO::FETCH_ASSOC);

		$userStmt = $this->db->prepare("SELECT id, person_id, username, type, active, created, modified FROM users WHERE person_id = :id");
		$userStmt->execute(['id' => $id]);
		$user = $userStmt->fetch(PDO::FETCH_ASSOC);

		$phonesStmt = $this->db->prepare("SELECT * FROM phone_numbers WHERE person_id = :id ORDER BY id ASC");
		$phonesStmt->execute(['id' => $id]);
		$phones = $phonesStmt->fetchAll(PDO::FETCH_ASSOC);

		$emailsStmt = $this->db->prepare("SELECT * FROM emails WHERE person_id = :id ORDER BY id ASC");
		$emailsStmt->execute(['id' => $id]);
		$emails = $emailsStmt->fetchAll(PDO::FETCH_ASSOC);

		return [
			'person' => $person,
			'user'   => $user,
			'phones' => $phones,
			'emails' => $emails,
		];
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
	}

?>
