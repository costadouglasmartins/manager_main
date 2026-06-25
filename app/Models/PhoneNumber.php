<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class PhoneNumber extends Model
	{
	/**
	 * Returns sortable columns for list queries.
	 *
	 * @return array<string, string>
	 */
	public static function sortableColumns(): array
		{
		return [
			'id'           => 'PhoneNumber.id',
			'phone_number' => 'PhoneNumber.`phone_number`',
			'person_name'  => 'Person.`name`',
			'observations' => 'PhoneNumber.`observations`',
		];
		}

	/**
	 * Get all method
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT PhoneNumber.id, PhoneNumber.phone_number, PhoneNumber.observations, PhoneNumber.person_id, PhoneNumber.created, PhoneNumber.modified, Person.name AS person_name FROM phone_numbers AS PhoneNumber LEFT JOIN people AS Person ON Person.id = PhoneNumber.person_id ORDER BY PhoneNumber.id DESC");
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

			$sql = "INSERT INTO phone_numbers (`phone_number`, `observations`, `person_id`, `created`, `modified`) VALUES (:phone_number, :observations, :person_id, :created, :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'phone_number' => $data['phone_number'],
				'observations' => $data['observations'] ?? null,
				'person_id'    => !empty($data['person_id']) ? (int) $data['person_id'] : null,
				'created'      => date('Y-m-d H:i:s'),
				'modified'     => date('Y-m-d H:i:s'),
			]);
			$phoneId = $this->db->lastInsertId();
			$phoneNumber = $this->find($phoneId);
			$this->auditCreate('phone_number', 'phone_numbers', $phoneId, $phoneNumber);

			$this->db->commit();

			return ['phone_number_id' => $phoneId];
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
		$stmt = $this->db->prepare("SELECT PhoneNumber.id, PhoneNumber.phone_number, PhoneNumber.observations, PhoneNumber.person_id, PhoneNumber.created, PhoneNumber.modified, Person.name AS person_name FROM phone_numbers AS PhoneNumber LEFT JOIN people AS Person ON Person.id = PhoneNumber.person_id WHERE PhoneNumber.id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 */
	public function update($id, $data)
		{
		$beforeData = $this->find($id);
		$stmt = $this->db->prepare("
			UPDATE phone_numbers
			SET
				`phone_number` = :phone_number,
				`observations` = :observations,
				`person_id`    = :person_id,
				`modified`     = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'phone_number' => $data['phone_number'],
			'observations' => $data['observations'] ?? null,
			'person_id'    => !empty($data['person_id']) ? (int) $data['person_id'] : null,
			'modified'     => date('Y-m-d H:i:s'),
			'id'           => $id,
		]);
		$afterData = $this->find($id);
		$this->auditUpdate('phone_number', 'phone_numbers', $id, $beforeData, $afterData);
		}

	/**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?array $sort = null): array
		{
		$sql = "SELECT PhoneNumber.id, PhoneNumber.phone_number, PhoneNumber.observations, PhoneNumber.person_id, PhoneNumber.created, PhoneNumber.modified, Person.name AS person_name FROM phone_numbers AS PhoneNumber LEFT JOIN people AS Person ON Person.id = PhoneNumber.person_id WHERE 1 = 1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND PhoneNumber.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['phone_number']) && $filters['phone_number'] !== '')
			{
			$sql .= " AND PhoneNumber.`phone_number` LIKE :phone_number";
			$params['phone_number'] = '%' . $filters['phone_number'] . '%';
			}

		if (isset($filters['person_id']) && $filters['person_id'] !== '')
			{
			$sql .= " AND PhoneNumber.person_id = :person_id";
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
		$sql = "SELECT COUNT(PhoneNumber.id) AS total FROM phone_numbers AS PhoneNumber WHERE 1 = 1";
		$params = [];

		if (!empty($filters['id']))
			{
			$sql .= " AND PhoneNumber.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (!empty($filters['phone_number']))
			{
			$sql .= " AND PhoneNumber.`phone_number` LIKE :phone_number";
			$params['phone_number'] = '%' . $filters['phone_number'] . '%';
			}

		if (!empty($filters['person_id']))
			{
			$sql .= " AND PhoneNumber.person_id = :person_id";
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
	 * Find all by phone number id
	 */
	public function findAllByPhoneNumberId($id)
		{
		$stmt = $this->db->prepare("SELECT PhoneNumber.id, PhoneNumber.phone_number, PhoneNumber.observations, PhoneNumber.person_id, PhoneNumber.created, PhoneNumber.modified, Person.name AS person_name FROM phone_numbers AS PhoneNumber LEFT JOIN people AS Person ON Person.id = PhoneNumber.person_id WHERE PhoneNumber.id = :id");
		$stmt->execute(['id' => $id]);
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		return ['phone_number' => $data];
		}

	/**
	 * Delete method
	 * @param Integer $id PhoneNumber id
	 * @throws \Exception
	 */
	public function delete($id): void
		{
		try
			{
			$beforeData = $this->find($id);
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("DELETE FROM phone_numbers WHERE id = :id");
			$stmt->execute(['id' => $id]);
			$this->auditDelete('phone_number', 'phone_numbers', $id, $beforeData);

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
