<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Address extends Model
	{
	/**
	 * Returns sortable columns for list queries.
	 *
	 * @return array<string, string>
	 */
	public static function sortableColumns(): array
		{
		return [
			'id'          => 'Address.id',
			'zip_code'    => 'Address.`zip_code`',
			'street'      => 'Address.`street`',
			'number'      => 'Address.`number`',
			'person_name' => 'Person.`name`',
		];
		}

	/**
	 * Get all method
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT Address.id,
		                                 Address.zip_code,
		                                 Address.street,
		                                 Address.number,
		                                 Address.observations,
		                                 Address.person_id,
		                                 Address.neighborhood_id,
		                                 Address.created,
		                                 Address.modified,
		                                 Person.name AS person_name,
		                                 Neighborhood.name AS neighborhood_name
		                          FROM addresses AS Address
		                          LEFT JOIN people AS Person ON Person.id = Address.person_id
		                          LEFT JOIN neighborhoods AS Neighborhood ON Neighborhood.id = Address.neighborhood_id
		                          ORDER BY Address.id DESC");
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

			$sql = "INSERT INTO addresses
			             (`zip_code`,
			              `street`,
			              `number`,
			              `observations`,
			              `person_id`,
			              `neighborhood_id`,
			              `created`,
			              `modified`)
			        VALUES
			             (:zip_code,
			              :street,
			              :number,
			              :observations,
			              :person_id,
			              :neighborhood_id,
			              :created,
			              :modified)";
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
			$address = $this->find($addressId);
			$this->auditCreate('address', 'addresses', $addressId, $address);

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
		$stmt = $this->db->prepare("SELECT Address.id,
		                                   Address.zip_code,
		                                   Address.street,
		                                   Address.number,
		                                   Address.observations,
		                                   Address.person_id,
		                                   Address.neighborhood_id,
		                                   Address.created,
		                                   Address.modified,
		                                   Person.name AS person_name,
		                                   Neighborhood.name AS neighborhood_name
		                            FROM addresses AS Address
		                            LEFT JOIN people AS Person ON Person.id = Address.person_id
		                            LEFT JOIN neighborhoods AS Neighborhood ON Neighborhood.id = Address.neighborhood_id
		                            WHERE Address.id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 */
	public function update($id, $data)
		{
		$beforeData = $this->find($id);
		$stmt = $this->db->prepare("UPDATE addresses
		                            SET `zip_code`        = :zip_code,
		                                `street`          = :street,
		                                `number`          = :number,
		                                `observations`    = :observations,
		                                `person_id`       = :person_id,
		                                `neighborhood_id` = :neighborhood_id,
		                                `modified`        = :modified
		                            WHERE id = :id");

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
		$afterData = $this->find($id);
		$this->auditUpdate('address', 'addresses', $id, $beforeData, $afterData);
		}

	/**
	 * Finds a neighborhood by ViaCEP address parts.
	 *
	 * The lookup matches neighborhood name, city name and state acronym so the
	 * address form can fill the hidden `neighborhood_id` when local data already
	 * contains the returned location.
	 *
	 * @param string $neighborhood Neighborhood name.
	 * @param string $city City name.
	 * @param string $stateAcronym State acronym.
	 * @return array<string, mixed>|null
	 */
	public function findNeighborhoodByLocation(string $neighborhood, string $city, string $stateAcronym): ?array
		{
		$neighborhood = trim($neighborhood);
		$city = trim($city);
		$stateAcronym = strtoupper(trim($stateAcronym));

		if ($neighborhood === '' || $city === '' || $stateAcronym === '')
			{
			return null;
			}

		$stmt = $this->db->prepare("SELECT Neighborhood.id,
		                                   Neighborhood.name,
		                                   City.name AS city_name,
		                                   State.acronym AS state_acronym
		                            FROM neighborhoods AS Neighborhood
		                            INNER JOIN cities AS City ON City.id = Neighborhood.city_id
		                            INNER JOIN states AS State ON State.id = City.state_id
		                            WHERE Neighborhood.name = :neighborhood
		                              AND City.name = :city
		                              AND UPPER(State.acronym) = :state_acronym
		                            ORDER BY Neighborhood.id ASC
		                            LIMIT 1");
		$stmt->execute([
			'neighborhood'  => $neighborhood,
			'city'          => $city,
			'state_acronym' => $stateAcronym,
		]);

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
		}

	/**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?array $sort = null): array
		{
		$sql = "SELECT Address.id,
		               Address.zip_code,
		               Address.street,
		               Address.number,
		               Address.observations,
		               Address.person_id,
		               Address.neighborhood_id,
		               Address.created,
		               Address.modified,
		               Person.name AS person_name,
		               Neighborhood.name AS neighborhood_name
		        FROM addresses AS Address
		        LEFT JOIN people AS Person ON Person.id = Address.person_id
		        LEFT JOIN neighborhoods AS Neighborhood ON Neighborhood.id = Address.neighborhood_id
		        WHERE 1 = 1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Address.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['street']) && $filters['street'] !== '')
			{
			$sql .= " AND Address.`street` LIKE :street";
			$params['street'] = '%' . $filters['street'] . '%';
			}

		if (isset($filters['zip_code']) && $filters['zip_code'] !== '')
			{
			$sql .= " AND Address.`zip_code` LIKE :zip_code";
			$params['zip_code'] = '%' . $filters['zip_code'] . '%';
			}

		if (isset($filters['person_id']) && $filters['person_id'] !== '')
			{
			$sql .= " AND Address.person_id = :person_id";
			$params['person_id'] = (int) $filters['person_id'];
			}

		$orderBy = sortOrderBy($sort, self::sortableColumns(), 'id', 'desc');
		$sql .= " ORDER BY {$orderBy}
		          LIMIT :limit
		          OFFSET :offset";

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
		$sql = "SELECT COUNT(Address.id) AS total
		        FROM addresses AS Address
		        WHERE 1 = 1";
		$params = [];

		if (!empty($filters['id']))
			{
			$sql .= " AND Address.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (!empty($filters['street']))
			{
			$sql .= " AND Address.`street` LIKE :street";
			$params['street'] = '%' . $filters['street'] . '%';
			}

		if (!empty($filters['zip_code']))
			{
			$sql .= " AND Address.`zip_code` LIKE :zip_code";
			$params['zip_code'] = '%' . $filters['zip_code'] . '%';
			}

		if (!empty($filters['person_id']))
			{
			$sql .= " AND Address.person_id = :person_id";
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
		$stmt = $this->db->prepare("SELECT Address.id,
		                                   Address.zip_code,
		                                   Address.street,
		                                   Address.number,
		                                   Address.observations,
		                                   Address.person_id,
		                                   Address.neighborhood_id,
		                                   Address.created,
		                                   Address.modified,
		                                   Person.name AS person_name,
		                                   Neighborhood.name AS neighborhood_name
		                            FROM addresses AS Address
		                            LEFT JOIN people AS Person ON Person.id = Address.person_id
		                            LEFT JOIN neighborhoods AS Neighborhood ON Neighborhood.id = Address.neighborhood_id
		                            WHERE Address.id = :id");
		$stmt->execute(['id' => $id]);
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		return ['address' => $data];
		}

	/**
	 * Delete method
	 * @param Integer $id Address id
	 * @throws \Exception
	 */
	public function delete($id): void
		{
		try
			{
			$beforeData = $this->find($id);
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("DELETE FROM addresses
			                            WHERE id = :id");
			$stmt->execute(['id' => $id]);
			$this->auditDelete('address', 'addresses', $id, $beforeData);

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
