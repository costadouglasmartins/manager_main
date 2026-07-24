<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Neighborhood extends Model
	{
	/**
	 * Returns sortable columns for list queries.
	 *
	 * @return array<string, string>
	 */
	public static function sortableColumns(): array
		{
		return [
			'id'         => 'Neighborhood.id',
			'name'       => 'Neighborhood.`name`',
			'city_name'  => 'City.`name`',
			'state_name' => 'State.`name`',
		];
		}

	/**
	 * Get all method
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT Neighborhood.id, Neighborhood.name, Neighborhood.city_id, Neighborhood.created, Neighborhood.modified, City.name AS city_name, State.name AS state_name FROM neighborhoods AS Neighborhood LEFT JOIN cities AS City ON City.id = Neighborhood.city_id LEFT JOIN states AS State ON State.id = City.state_id ORDER BY Neighborhood.id DESC");
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

			$sql = "INSERT INTO neighborhoods (`name`, `city_id`, `created`, `modified`) VALUES (:name, :city_id, :created, :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'name'     => $data['name'],
				'city_id'  => (int) $data['city_id'],
				'created'  => date('Y-m-d H:i:s'),
				'modified' => date('Y-m-d H:i:s'),
			]);
			$neighborhoodId = $this->db->lastInsertId();
			$neighborhood = $this->find($neighborhoodId);
			$this->auditCreate('neighborhood', 'neighborhoods', $neighborhoodId, $neighborhood);

			$this->db->commit();

			return ['neighborhood_id' => $neighborhoodId];
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
		$stmt = $this->db->prepare("SELECT Neighborhood.id, Neighborhood.name, Neighborhood.city_id, Neighborhood.created, Neighborhood.modified, City.name AS city_name, State.name AS state_name FROM neighborhoods AS Neighborhood LEFT JOIN cities AS City ON City.id = Neighborhood.city_id LEFT JOIN states AS State ON State.id = City.state_id WHERE Neighborhood.id = :id");
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
			UPDATE neighborhoods
			SET
				`name`     = :name,
				`city_id`  = :city_id,
				`modified` = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'name'     => $data['name'],
			'city_id'  => (int) $data['city_id'],
			'modified' => date('Y-m-d H:i:s'),
			'id'       => $id,
		]);
		$afterData = $this->find($id);
		$this->auditUpdate('neighborhood', 'neighborhoods', $id, $beforeData, $afterData);
		}

	/**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?array $sort = null): array
		{
		$sql = "SELECT Neighborhood.id, Neighborhood.name, Neighborhood.city_id, Neighborhood.created, Neighborhood.modified, City.name AS city_name, State.name AS state_name FROM neighborhoods AS Neighborhood LEFT JOIN cities AS City ON City.id = Neighborhood.city_id LEFT JOIN states AS State ON State.id = City.state_id LEFT JOIN countries AS Country ON Country.id = State.country_id WHERE 1 = 1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Neighborhood.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND Neighborhood.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['country_id']) && $filters['country_id'] !== '')
			{
			$sql .= " AND Country.id = :country_id";
			$params['country_id'] = (int) $filters['country_id'];
			}

		if (isset($filters['state_id']) && $filters['state_id'] !== '')
			{
			$sql .= " AND State.id = :state_id";
			$params['state_id'] = (int) $filters['state_id'];
			}

		if (isset($filters['city_id']) && $filters['city_id'] !== '')
			{
			$sql .= " AND Neighborhood.city_id = :city_id";
			$params['city_id'] = (int) $filters['city_id'];
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
		$sql = "SELECT COUNT(Neighborhood.id) AS total FROM neighborhoods AS Neighborhood LEFT JOIN cities AS City ON City.id = Neighborhood.city_id LEFT JOIN states AS State ON State.id = City.state_id LEFT JOIN countries AS Country ON Country.id = State.country_id WHERE 1 = 1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Neighborhood.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND Neighborhood.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['country_id']) && $filters['country_id'] !== '')
			{
			$sql .= " AND Country.id = :country_id";
			$params['country_id'] = (int) $filters['country_id'];
			}

		if (isset($filters['state_id']) && $filters['state_id'] !== '')
			{
			$sql .= " AND State.id = :state_id";
			$params['state_id'] = (int) $filters['state_id'];
			}

		if (isset($filters['city_id']) && $filters['city_id'] !== '')
			{
			$sql .= " AND Neighborhood.city_id = :city_id";
			$params['city_id'] = (int) $filters['city_id'];
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
	 * Find all by neighborhood id
	 */
	public function findAllByNeighborhoodId($id)
		{
		$stmt = $this->db->prepare("SELECT Neighborhood.id, Neighborhood.name, Neighborhood.city_id, Neighborhood.created, Neighborhood.modified, City.name AS city_name, State.name AS state_name FROM neighborhoods AS Neighborhood LEFT JOIN cities AS City ON City.id = Neighborhood.city_id LEFT JOIN states AS State ON State.id = City.state_id WHERE Neighborhood.id = :id");
		$stmt->execute(['id' => $id]);
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		return ['neighborhood' => $data];
		}

	/**
	 * Autocomplete neighborhoods, optionally narrowing by city and state.
	 * @param string $term Search term typed by the user.
	 * @param int $limit Maximum number of rows returned.
	 * @param array<string, mixed> $filters Optional city/state filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function autocomplete(string $term, int $limit = 100, array $filters = []): array
		{
		$term = trim($term);
		if ($term === '')
			{
			return [];
			}

		$limit = max(1, min(50, (int) $limit));
		$params = [':t' => '%' . $term . '%'];

		$sql = "SELECT  Neighborhood.id, 
                    Neighborhood.`name`, 
                    City.`name` AS city_name, 
                    State.acronym AS state_acronym
            FROM neighborhoods AS Neighborhood
            LEFT JOIN cities AS City ON City.id = Neighborhood.city_id
            LEFT JOIN states AS State ON State.id = City.state_id
            WHERE Neighborhood.`name` LIKE :t";

		$cityName = trim((string) ($filters['city_name'] ?? ''));
		if ($cityName !== '')
			{
			$sql .= " AND LOWER(TRIM(City.`name`)) = LOWER(TRIM(:city_name))";
			$params[':city_name'] = $cityName;
			}

		$stateAcronym = strtoupper(trim((string) ($filters['state_acronym'] ?? '')));
		if ($stateAcronym !== '')
			{
			$sql .= " AND UPPER(TRIM(State.acronym)) = :state_acronym";
			$params[':state_acronym'] = $stateAcronym;
			}

		$sql .= " ORDER BY Neighborhood.`name` ASC
            LIMIT {$limit}";

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Delete method - Deletes neighborhood and dependent addresses
	 * @param Integer $id Neighborhood id
	 * @throws \Exception
	 */
	public function delete($id): void
		{
		try
			{
			$beforeData = $this->find($id);
			$this->db->beginTransaction();

			// Delete dependent addresses
			$stmt = $this->db->prepare("DELETE FROM addresses WHERE neighborhood_id = :id");
			$stmt->execute(['id' => $id]);

			// Delete neighborhood
			$stmt = $this->db->prepare("DELETE FROM neighborhoods WHERE id = :id");
			$stmt->execute(['id' => $id]);
			$this->auditDelete('neighborhood', 'neighborhoods', $id, $beforeData, [
				'affected_tables' => ['neighborhoods', 'addresses'],
			]);

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
