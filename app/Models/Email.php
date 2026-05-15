<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Email extends Model
	{
	/**
	 * Returns sortable columns for list queries.
	 *
	 * @return array<string, string>
	 */
	public static function sortableColumns(): array
		{
		return [
			'id'           => 'Email.id',
			'email'        => 'Email.`email`',
			'person_name'  => 'Person.`name`',
			'observations' => 'Email.`observations`',
		];
		}

	/**
	 * Get all method
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT Email.id,
		                               Email.email,
		                               Email.is_login,
		                               Email.observations,
		                               Email.person_id,
		                               Email.created,
		                               Person.name AS person_name
		                        FROM emails AS Email
		                        LEFT JOIN people AS Person ON Person.id = Email.person_id
		                        ORDER BY Email.id DESC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Create method
	 */
	public function create($data)
		{
		try
			{
			$this->db->beginTransaction();

			$sql = "INSERT INTO emails (`email`, `is_login`, `observations`, `person_id`, `created`) VALUES (:email, :is_login, :observations, :person_id, :created)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'email'        => $data['email'],
				'is_login'     => $data['type'] ?? null,
				'observations' => $data['observations'] ?? null,
				'person_id'    => !empty($data['person_id']) ? (int) $data['person_id'] : null,
				'created'      => date('Y-m-d H:i:s'),
			]);
			$emailId = $this->db->lastInsertId();

			$this->db->commit();

			return ['email_id' => $emailId];
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Find method
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare("SELECT Email.id,
		                                   Email.email,
		                                   Email.is_login,
		                                   Email.observations,
		                                   Email.person_id,
		                                   Email.created,
		                                   Person.name AS person_name
		                           FROM emails AS Email
		                           LEFT JOIN people AS Person ON Person.id = Email.person_id
		                           WHERE Email.id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 */
	public function update($id, $data)
		{
		$stmt = $this->db->prepare("UPDATE emails AS Email
                                SET Email.`email` = :email,
                                    Email.`is_login` = :is_login,
                                    Email.`observations` = :observations,
                                    Email.`person_id` = :person_id
                                WHERE Email.id = :id");

		$stmt->execute([
			'email'        => $data['email'],
			'is_login'     => intval($data['type'] ?? 0),
			'observations' => $data['observations'] ?? null,
			'person_id'    => !empty($data['person_id']) ? (int) $data['person_id'] : null,
			'id'           => $id,
		]);
		}

	/**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?array $sort = null): array
		{
		$sql = "SELECT Email.id,
		               Email.email,
		               Email.is_login,
		               Email.observations,
		               Email.person_id,
		               Email.created,
		               Person.name AS person_name
		        FROM emails AS Email
		        LEFT JOIN people AS Person ON Person.id = Email.person_id
		        WHERE 1 = 1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Email.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['email']) && $filters['email'] !== '')
			{
			$sql .= " AND Email.`email` LIKE :email";
			$params['email'] = '%' . $filters['email'] . '%';
			}

		if (isset($filters['person_id']) && $filters['person_id'] !== '')
			{
			$sql .= " AND Email.person_id = :person_id";
			$params['person_id'] = (int) $filters['person_id'];
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
		$sql = "SELECT COUNT(Email.id) AS total
		        FROM emails AS Email
		        WHERE 1 = 1";
		$params = [];

		if (!empty($filters['id']))
			{
			$sql .= " AND Email.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (!empty($filters['email']))
			{
			$sql .= " AND Email.`email` LIKE :email";
			$params['email'] = '%' . $filters['email'] . '%';
			}

		if (!empty($filters['person_id']))
			{
			$sql .= " AND Email.person_id = :person_id";
			$params['person_id'] = (int) $filters['person_id'];
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
	 * Find all by email id
	 */
	public function findAllByEmailId($id)
		{
		$stmt = $this->db->prepare("SELECT Email.id,
		                                   Email.email,
		                                   Email.is_login,
		                                   Email.observations,
		                                   Email.person_id,
		                                   Email.created,
		                                   Person.name AS person_name
		                           FROM emails AS Email
		                           LEFT JOIN people AS Person ON Person.id = Email.person_id
		                           WHERE Email.id = :id");
		$stmt->execute(['id' => $id]);
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		return ['email' => $data];
		}

	/**
	 * Delete method
	 * @param Integer $id Email id
	 * @throws \Exception
	 */
	public function delete($id): void
		{
		try
			{
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("DELETE FROM emails WHERE id = :id");
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
