<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Company extends Model
	{

	/**
	 * Get all method
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT * FROM companies ORDER BY id DESC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = "SELECT * FROM companies WHERE 1=1";
		$params = [];

		// Id 
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		// Name
		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND name LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		// Fantasy
		if (isset($filters['fantasy']) && $filters['fantasy'] !== '')
			{
			$sql .= " AND fantasy LIKE :fantasy";
			$params['fantasy'] = '%' . $filters['fantasy'] . '%';
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
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total FROM companies WHERE 1=1";
		$params = [];

		// Filtro por ID (igual)
		if (!empty($filters['id']))
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		// Filtro por Nome (LIKE)
		if (!empty($filters['name']))
			{
			$sql .= " AND name LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		// Filtro por Fantasia (LIKE)
		if (!empty($filters['fantasy']))
			{
			$sql .= " AND fantasy LIKE :fantasy";
			$params['fantasy'] = '%' . $filters['fantasy'] . '%';
			}

		// Filtro por CNPJ/Documento (LIKE)
		if (!empty($filters['document']))
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
	 * Create method
	 */
	public function create($data)
		{
		try
			{
			// Início da transação
			$this->db->beginTransaction();

			// Save a company
			$sqlCompany = "INSERT INTO companies 
                        (`name`, `fantasy`, `document`, `database`, `active`, `created`, `modified`) 
                       VALUES 
                        (:name, :fantasy, :document, :database, :active, :created, :modified)";
			$stmtCompany = $this->db->prepare($sqlCompany);
			$stmtCompany->execute([
				'name' => $data['company']['name'],
				'fantasy' => $data['company']['fantasy'],
				'document' => onlyDigits($data['company']['document']),
				'database' => $data['company']['database'],
				'active' => intval($data['company']['active']),
				'created' => date('Y-m-d H:i:s'),
				'modified' => date('Y-m-d H:i:s')
			]);
			// Store company id
			$companyId = $this->db->lastInsertId();

			// Save one person
			$sqlPerson = "INSERT INTO people 
                        (`company_id`, `name`, `type`, `document`, `active`, `birthdate`, `created`, `modified`) 
                      VALUES 
                        (:company_id, :name, :type, :document, :active, :birthdate, :created, :modified)";
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
				'company_id' => $companyId,
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
	 * Find by id method
	 */
	public function findById($id)
		{
		$stmt = $this->db->prepare("SELECT * FROM companies WHERE id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Find all information by company id
	 */
	public function findAllByCompanyId($id)
		{
		$companyStmt = $this->db->prepare("SELECT * FROM companies WHERE id = :id");
		$companyStmt->execute(['id' => $id]);
		$companyData = $companyStmt->fetch(PDO::FETCH_ASSOC);

		$peopleStmt = $this->db->prepare("SELECT  Person.id         AS person_id,
                                              Person.name       AS person_name,
                                              Person.type       AS person_type,
                                              Person.document   AS person_document,
                                              Person.active     AS person_active,
                                              Person.birthdate  AS person_birthdate,
                                              Person.created    AS person_created,
                                              Person.modified   AS person_modified,  
                                              User.id           AS user_id,
                                              User.person_id    AS user_person_id, 
                                              User.username     AS user_name,  
                                              User.email        AS user_email,
                                              User.type         AS user_type,
                                              User.active       AS user_active,
                                              User.created      AS user_created, 
                                              User.modified     AS user_modified
                                      FROM people      AS Person
                                      LEFT JOIN users  AS User ON User.person_id = Person.id
                                      WHERE Person.company_id = :company_id");
		$peopleStmt->execute(['company_id' => $id]);
		$personData = $peopleStmt->fetchAll(PDO::FETCH_ASSOC);
		return ['company' => $companyData,
			'people' => $personData];
		}

	/**
	 * Update method
	 */
	public function update($id, $data)
		{
		$stmt = $this->db->prepare("UPDATE companies 
                                SET 
                                 `name`     = :name, 
                                 `fantasy`  = :fantasy, 
                                 `document` = :document, 
                                 `database` = :database, 
                                 `active`   = :active, 
                                 `modified` = :modified 
                                WHERE id = :id");
		$stmt->execute(['name' => $data['company']['name'],
			'fantasy' => $data['company']['fantasy'],
			'document' => onlyDigits($data['company']['document']),
			'database' => $data['company']['database'],
			'active' => $data['company']['active'],
			'modified' => date('Y-m-d H:i:s'),
			'id' => $id]);
		}
	}

?>