<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Address extends Model
	{

	/**
	 * Get all method
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT a.*, p.name AS person_name, n.name AS neighborhood_name FROM addresses a LEFT JOIN people p ON p.id = a.person_id LEFT JOIN neighborhoods n ON n.id = a.neighborhood_id ORDER BY a.id DESC");
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

			$sql = "INSERT INTO addresses (`zip_code`, `street`, `number`, `observations`, `person_id`, `neighborhood_id`, `created`, `modified`) VALUES (:zip_code, :street, :number, :observations, :person_id, :neighborhood_id, :created, :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'zip_code'        => $data['zip_code'],
				'street'          => $data['street'],
				'number'          => $data['number'],
				'observations'    => $data['observations'] ?? null,
				'person_id'       => (int) $data['person_id'],
				'neighborhood_id' => (int) $data['neighborhood_id'],
				'created'         => date('Y-m-d H:i:s'),
				'modified'        => date('Y-m-d H:i:s'),
			]);
			$addressId = $this->db->lastInsertId();

			$this->db->commit();

			return ['address_id' => $addressId];
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
		$stmt = $this->db->prepare("SELECT a.*, p.name AS person_name, n.name AS neighborhood_name FROM addresses a LEFT JOIN people p ON p.id = a.person_id LEFT JOIN neighborhoods n ON n.id = a.neighborhood_id WHERE a.id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 */
	public function update($id, $data)
		{
		$stmt = $this->db->prepare("
			UPDATE addresses
			SET
				`zip_code`        = :zip_code,
				`street`          = :street,
				`number`          = :number,
				`observations`    = :observations,
				`person_id`       = :person_id,
				`neighborhood_id` = :neighborhood_id,
				`modified`        = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'zip_code'        => $data['zip_code'],
			'street'          => $data['street'],
			'number'          => $data['number'],
			'observations'    => $data['observations'] ?? null,
			'person_id'       => (int) $data['person_id'],
			'neighborhood_id' => (int) $data['neighborhood_id'],
			'modified'        => date('Y-m-d H:i:s'),
			'id'              => $id,
		]);
		}

	/**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = "SELECT a.*, p.name AS person_name, n.name AS neighborhood_name FROM addresses a LEFT JOIN people p ON p.id = a.person_id LEFT JOIN neighborhoods n ON n.id = a.neighborhood_id WHERE 1=1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND a.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['street']) && $filters['street'] !== '')
			{
			$sql .= " AND a.`street` LIKE :street";
			$params['street'] = '%' . $filters['street'] . '%';
			}

		if (isset($filters['zip_code']) && $filters['zip_code'] !== '')
			{
			$sql .= " AND a.`zip_code` LIKE :zip_code";
			$params['zip_code'] = '%' . $filters['zip_code'] . '%';
			}

		if (isset($filters['person_id']) && $filters['person_id'] !== '')
			{
			$sql .= " AND a.person_id = :person_id";
			$params['person_id'] = (int) $filters['person_id'];
			}

		$sql .= " ORDER BY a.id DESC LIMIT :limit OFFSET :offset";

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
		$sql = "SELECT COUNT(*) AS total FROM addresses WHERE 1=1";
		$params = [];

		if (!empty($filters['id']))
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (!empty($filters['street']))
			{
			$sql .= " AND `street` LIKE :street";
			$params['street'] = '%' . $filters['street'] . '%';
			}

		if (!empty($filters['zip_code']))
			{
			$sql .= " AND `zip_code` LIKE :zip_code";
			$params['zip_code'] = '%' . $filters['zip_code'] . '%';
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
	 * Find all by address id
	 */
	public function findAllByAddressId($id)
		{
		$stmt = $this->db->prepare("SELECT a.*, p.name AS person_name, n.name AS neighborhood_name FROM addresses a LEFT JOIN people p ON p.id = a.person_id LEFT JOIN neighborhoods n ON n.id = a.neighborhood_id WHERE a.id = :id");
		$stmt->execute(['id' => $id]);
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		return ['address' => $data];
		}
	}

?>
