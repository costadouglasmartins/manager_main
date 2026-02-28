<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class User extends Model
	{

  /**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = "SELECT * FROM users WHERE 1=1";
		$params = [];

		// Id 
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		// Username
		if (isset($filters['username']) && $filters['username'] !== '')
			{
			$sql .= " AND username LIKE :username";
			$params['username'] = '%' . $filters['username'] . '%';
			}

		// Email
		if (isset($filters['email']) && $filters['email'] !== '')
			{
			$sql .= " AND email LIKE :email";
			$params['email'] = '%' . $filters['email'] . '%';
			}

		// Type
		if (isset($filters['type']) && $filters['type'] !== '')
			{
			$sql .= " AND `type` = :type";
			$params['type'] = $filters['type'];
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
		$sql = "SELECT COUNT(*) AS total FROM users WHERE 1=1";
		$params = [];

		// Filtro por ID (igual)
		if (!empty($filters['id']))
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		// Filtro por Username (LIKE)
		if (!empty($filters['username']))
			{
			$sql .= " AND username LIKE :username";
			$params['username'] = '%' . $filters['username'] . '%';
			}

		// Filtro por Fantasia (LIKE)
		if (!empty($filters['email']))
			{
			$sql .= " AND email LIKE :email";
			$params['email'] = '%' . $filters['email'] . '%';
			}

    // Filtro por Fantasia (LIKE)
		if (!empty($filters['type']))
			{
			$sql .= " AND `type` = :type";
			$params['type'] = $filters['type'];
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
	 * Get all method
	 * @return array
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT * FROM users ORDER BY id DESC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Find method
	 * @param Integer $id User id
	 * @return array
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare("SELECT 	User.id,
                                        User.username,
                                        User.type,
                                        User.active,
                                        Email.email
                                  FROM users AS User
                                  INNER JOIN people AS Person ON Person.id = User.person_id
                                  INNER JOIN emails AS Email ON Email.person_id = Person.id
                                  WHERE User.active = 1 
                                  AND User.id = :id
                                  LIMIT 1");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 * @param Integer $id User id
	 * @param array $data
	 */
	public function update($id, $data)
		{
		$stmt = $this->db->prepare("UPDATE users SET `username` = :username, 
                                                 `password` = :password,
                                                 `email`    = :email,
                                                 `type`     = :type,
                                                 `active`   = :active
                                                 WHERE id = :id");
		$stmt->execute(['username' => $data['username'], 
                    'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                    'email'    => $data['email'],
                    'type'     => $data['type'],
                    'active'   => $data['active'], 
                    'id'       => $id]);
		}

	/**
	 * Find by email method
	 * @param String $email User e-mail
	 * @return array
	 */
	public function findByEmail(string $email): ?array
    {
    $sql = "SELECT  User.id,
                    User.username,
                    User.password,
                    User.active,
                    Email.email,
                    User.type,
                    Person.name,
                    Person.type AS person_type,
                    Person.document
            FROM users AS User
            INNER JOIN people AS Person ON Person.id = User.person_id
            INNER JOIN emails  AS Email ON Email.person_id = Person.id
            WHERE User.active = 1
              AND User.type = 'root'
              AND Email.email = :email
            LIMIT 1";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([':email' => trim($email)]);

    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    return $row ?: null;
    }

	/**
	 * Authenticate method
	 * @param String $email User email
	 * @param String $password User password
	 * @return boolean
	 */	
	public function authenticate($email, $password)
		{
		$user = $this->findByEmail($email);
		if (!$user)
			{
			return false;
			}

		if (!$user['active'])
			{
			return false;
			}

		if (password_verify($password, $user['password']))
			{
			// Opcional: rehash if needed
			if (password_needs_rehash($user['password'], PASSWORD_DEFAULT))
				{
				$newHash = password_hash($password, PASSWORD_DEFAULT);
				$stmt = $this->db->prepare("UPDATE users SET password = :password WHERE id = :id");
				$stmt->execute(['password' => $newHash, 'id' => $user['id']]);
				}
			// Remover senha antes de retornar
			unset($user['password']);
			return $user;
			}

		return false;
		}
	}

?>