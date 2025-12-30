<?php

namespace App\Models;

use App\Core\Model;
use App\Models\Company;
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
	 * Create method
	 * @param Array $data Data to save
	 * @return array
	 * @throws \Exception
	 */
	public function create($data)
		{

		try
			{
			// Início da transação
			$this->db->beginTransaction();

			// Store company id
			$companyId = $data['person']['company_id'];

			// Save one person
			$sqlPerson = "INSERT INTO people 
                        (`company_id`, `name`, `type`, `document`, `active`, `birthdate`, `created`, `modified`) 
                      VALUES 
                        (:company_id, :name, :type, :document, :active, :birthdate, :created, :modified)";
			if (!empty($data['person']['birthdate']))
				{
				$data['person']['birthdate'] = dateBrToUs($data['person']['birthdate']);
				}
			else
				{
				$data['person']['birthdate'] = NULL;
				}
			$stmtPerson = $this->db->prepare($sqlPerson);
			$stmtPerson->execute([
				'company_id' => $companyId,
				'name' => $data['person']['name'],
				'type' => $data['person']['type'],
				'document' => onlyDigits($data['person']['document']),
				'active' => intval($data['person']['active']),
				'birthdate' => $data['person']['birthdate'],
				'created' => date('Y-m-d H:i:s'),
				'modified' => date('Y-m-d H:i:s')
			]);
			// Store person id
			$personId = $this->db->lastInsertId();

			// Save one user
			$sqlUser = "INSERT INTO users
                      (`person_id`, `username`, `password`, `email`, `type`, `active`, `created`, `modified`)
                    VALUES
                      (:person_id, :username, :password, :email, :type, :active, :created, :modified)";
			$stmtUser = $this->db->prepare($sqlUser);
			$stmtUser->execute([
				'person_id' => $personId,
				'username' => $data['user']['username'],
				'password' => password_hash($data['user']['password'], PASSWORD_DEFAULT),
				'email' => $data['user']['email'],
				'type' => $data['user']['type'],
				'active' => intval($data['user']['active']),
				'created' => date('Y-m-d H:i:s'),
				'modified' => date('Y-m-d H:i:s')
			]);
			// Store user id
			$userId = $this->db->lastInsertId();

			// Confirms the transaction
			$this->db->commit();

			// Retorna os IDs criados
			return [
				'person_id' => $personId,
				'user_id' => $userId
			];
			}
		catch (\Exception $e)
			{
			// Reverts in case of failure
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
            `type`      = :type,
            `document`  = :document,
            `active`    = :active,
            `birthdate` = :birthdate,
            `modified`  = :modified
        WHERE id = :id
    ");

    $stmt->execute([
        'name'      => $data['name'],
        'type'      => $data['type'],
        'document'  => onlyDigits($data['document']),
        'active'    => $data['active'],
        'birthdate' => (!empty($data['birthdate'] && $data['birthdate'] !== '') ? dateBrToUs($data['birthdate']) : NULL),
        'modified'  => date('Y-m-d H:i:s'),
        'id'        => $id
    ]);
    }

	/**
	 * Search method 
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = "SELECT * FROM people WHERE 1=1";
		$params = [];

		// Id 
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		// Company id
		if (isset($filters['company_id']) && $filters['company_id'] !== '')
			{
			$sql .= " AND company_id = :company_id";
			$params['company_id'] = (int) $filters['company_id'];
			}

		// Name
		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND `name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		// Type
		if (isset($filters['type']) && $filters['type'] !== '')
			{
			$sql .= " AND `type` = :type";
			$params['type'] = $filters['type'];
			}

		// Document
		if (isset($filters['document']) && $filters['document'] !== '')
			{
			$sql .= " AND document LIKE :document";
			$params['document'] = onlyDigits($filters['document']);
			}

		// Active
		if (isset($filters['active']) && $filters['active'] !== '')
			{
			$active = (int) $filters['active'];

			if (in_array($active, [0, 1], true))
				{
				$sql .= " AND active = :active";
				$params['active'] = $active;
				}
			}

		// Document
		if (isset($filters['birthdate']) && $filters['birthdate'] !== '')
			{
			$sql .= " AND birthdate = :birthdate";

			$params['birthdate'] = dateBrToUs($filters['birthdate']);
			}

		$sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

		$stmt = $this->db->prepare($sql);

		// Bind dos filtros
		foreach ($params as $key => $value)
			{
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue(':' . $key, $value, $paramType);
			}

		// Bind da paginação
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

		// Filtro por ID (igual)
		if (!empty($filters['id']))
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		// Filtro por company ID (igual)
		if (!empty($filters['company_id']))
			{
			$sql .= " AND company_id = :company_id";
			$params['company_id'] = (int) $filters['company_id'];
			}

		// Filtro por Nome (LIKE)
		if (!empty($filters['name']))
			{
			$sql .= " AND name LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		// Filtro por Tipo
		if (!empty($filters['type']))
			{
			$sql .= " AND `type` = :type";
			$params['type'] = $filters['type'];
			}

		// Filtro por CNPJ/Documento 
		if (!empty($filters['document']))
			{
			$sql .= " AND document LIKE :document";
			$params['document'] = onlyDigits($filters['document']);
			}

		// Filtro por Data de nascimento
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
	 * Find all by person id
	 */
	public function findAllByPersonId($id)
		{
		$personStmt = $this->db->prepare("SELECT * FROM people WHERE id = :id");
		$personStmt->execute(['id' => $id]);
		$personData = $personStmt->fetch(PDO::FETCH_ASSOC);
		return ['people' => $personData];
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