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

		// Bind filters
		foreach ($params as $key => $value)
			{
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue(':' . $key, $value, $paramType);
			}

		// Bind pagination
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

		// Filter by ID (exact)
		if (!empty($filters['id']))
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		// Filter by username (LIKE)
		if (!empty($filters['username']))
			{
			$sql .= " AND username LIKE :username";
			$params['username'] = '%' . $filters['username'] . '%';
			}

		// Filter by display name (LIKE)
		if (!empty($filters['email']))
			{
			$sql .= " AND email LIKE :email";
			$params['email'] = '%' . $filters['email'] . '%';
			}

		// Filter by type (LIKE)
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
		$stmt = $this->db->prepare("SELECT User.id,
		                                   User.username,
		                                   User.type,
		                                   User.active,
		                                   User.tenant_id,
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
		// tenant_id: null if empty/0, otherwise cast to int
		$tenantId = (isset($data['tenant_id']) && $data['tenant_id'] !== '' && (int) $data['tenant_id'] > 0)
			? (int) $data['tenant_id']
			: null;

		// Only update password if a new one was provided
		$newPassword = isset($data['password']) ? trim($data['password']) : '';

		if ($newPassword !== '')
			{
			$stmt = $this->db->prepare("UPDATE users
			                            SET `username`  = :username,
			                                `password`  = :password,
			                                `type`      = :type,
			                                `active`    = :active,
			                                `tenant_id` = :tenant_id
			                            WHERE id = :id");
			$stmt->execute([
				'username'  => $data['username'],
				'password'  => password_hash($newPassword, PASSWORD_DEFAULT),
				'type'      => $data['type'],
				'active'    => $data['active'],
				'tenant_id' => $tenantId,
				'id'        => $id,
				]);
			}
		else
			{
			$stmt = $this->db->prepare("UPDATE users
			                            SET `username`  = :username,
			                                `type`      = :type,
			                                `active`    = :active,
			                                `tenant_id` = :tenant_id
			                            WHERE id = :id");
			$stmt->execute([
				'username'  => $data['username'],
				'type'      => $data['type'],
				'active'    => $data['active'],
				'tenant_id' => $tenantId,
				'id'        => $id,
				]);
			}
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
              AND Email.is_login = '1'
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
			// Optional: rehash if needed
			if (password_needs_rehash($user['password'], PASSWORD_DEFAULT))
				{
				$newHash = password_hash($password, PASSWORD_DEFAULT);
				$stmt = $this->db->prepare("UPDATE users SET password = :password WHERE id = :id");
				$stmt->execute(['password' => $newHash, 'id' => $user['id']]);
				}
			// Remove password before returning
			unset($user['password']);
			return $user;
			}

		return false;
		}

	/**
	 * Delete method - Deletes user_permissions, tenant_users before deleting user
	 * @param Integer $id User id
	 * @throws \Exception
	 */
	public function delete($id): void
		{
		try
			{
			$this->db->beginTransaction();

			// Delete user permissions
			$stmt = $this->db->prepare("DELETE FROM user_permissions WHERE user_id = :id");
			$stmt->execute(['id' => $id]);

			// Delete tenant users
			$stmt = $this->db->prepare("DELETE FROM tenant_users WHERE user_id = :id");
			$stmt->execute(['id' => $id]);

			// Delete user
			$stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
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
