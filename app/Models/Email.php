<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Email extends Model
	{

	/**
	 * Get all method
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT e.*, p.name AS person_name FROM emails e LEFT JOIN people p ON p.id = e.person_id ORDER BY e.id DESC");
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
		$stmt = $this->db->prepare("SELECT e.*, p.name AS person_name FROM emails e LEFT JOIN people p ON p.id = e.person_id WHERE e.id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 */
	public function update($id, $data)
		{
		$stmt = $this->db->prepare("
			UPDATE emails
			SET
				`email`        = :email,
				`observations` = :observations,
				`person_id`    = :person_id
			WHERE id = :id
		");

		$stmt->execute([
			'email'        => $data['email'],
			'observations' => $data['observations'] ?? null,
			'person_id'    => !empty($data['person_id']) ? (int) $data['person_id'] : null,
			'id'           => $id,
		]);
		}

	/**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = "SELECT e.*, p.name AS person_name FROM emails e LEFT JOIN people p ON p.id = e.person_id WHERE 1=1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND e.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['email']) && $filters['email'] !== '')
			{
			$sql .= " AND e.`email` LIKE :email";
			$params['email'] = '%' . $filters['email'] . '%';
			}

		if (isset($filters['person_id']) && $filters['person_id'] !== '')
			{
			$sql .= " AND e.person_id = :person_id";
			$params['person_id'] = (int) $filters['person_id'];
			}

		$sql .= " ORDER BY e.id DESC LIMIT :limit OFFSET :offset";

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
		$sql = "SELECT COUNT(*) AS total FROM emails WHERE 1=1";
		$params = [];

		if (!empty($filters['id']))
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (!empty($filters['email']))
			{
			$sql .= " AND `email` LIKE :email";
			$params['email'] = '%' . $filters['email'] . '%';
			}

		if (!empty($filters['person_id']))
			{
			$sql .= " AND person_id = :person_id";
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
		$stmt = $this->db->prepare("SELECT e.*, p.name AS person_name FROM emails e LEFT JOIN people p ON p.id = e.person_id WHERE e.id = :id");
		$stmt->execute(['id' => $id]);
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		return ['email' => $data];
		}
	}

?>
