<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class State extends Model
	{
	/**
	 * Returns sortable columns for list queries.
	 *
	 * @return array<string, string>
	 */
	public static function sortableColumns(): array
		{
		return [
			'id'           => 'State.id',
			'name'         => 'State.`name`',
			'acronym'      => 'State.`acronym`',
			'country_name' => 'Country.`name`',
		];
		}

	/**
	 * Get all method
	 * @return associative array
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT State.id, State.name, State.acronym, State.country_id, State.created, State.modified, Country.name AS country_name FROM states AS State LEFT JOIN countries AS Country ON Country.id = State.country_id ORDER BY State.id DESC");
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
			$this->db->beginTransaction();

			$sql = "INSERT INTO states (`name`, `acronym`, `country_id`, `created`, `modified`) VALUES (:name, :acronym, :country_id, :created, :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'name'       => $data['name'],
				'acronym'    => $data['acronym'],
				'country_id' => (int) $data['country_id'],
				'created'    => date('Y-m-d H:i:s'),
				'modified'   => date('Y-m-d H:i:s'),
			]);
			$stateId = $this->db->lastInsertId();

			$this->db->commit();

			return ['state_id' => $stateId];
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Find method
	 * @param Integer $id State id
	 * @return array
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare("SELECT State.id, State.name, State.acronym, State.country_id, State.created, State.modified, Country.name AS country_name FROM states AS State LEFT JOIN countries AS Country ON Country.id = State.country_id WHERE State.id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 * @param Integer $id State id
	 * @param array $data
	 */
	public function update($id, $data)
		{
		$stmt = $this->db->prepare("
			UPDATE states
			SET
				`name`       = :name,
				`acronym`    = :acronym,
				`country_id` = :country_id,
				`modified`   = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'name'       => $data['name'],
			'acronym'    => $data['acronym'],
			'country_id' => (int) $data['country_id'],
			'modified'   => date('Y-m-d H:i:s'),
			'id'         => $id,
		]);
		}

	/**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?array $sort = null): array
		{
		$sql = "SELECT State.id, State.name, State.acronym, State.country_id, State.created, State.modified, Country.name AS country_name FROM states AS State LEFT JOIN countries AS Country ON Country.id = State.country_id WHERE 1 = 1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND State.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND State.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['acronym']) && $filters['acronym'] !== '')
			{
			$sql .= " AND State.`acronym` = :acronym";
			$params['acronym'] = $filters['acronym'];
			}

		if (isset($filters['country_id']) && $filters['country_id'] !== '')
			{
			$sql .= " AND State.country_id = :country_id";
			$params['country_id'] = (int) $filters['country_id'];
			}
		elseif (isset($filters['country_name']) && $filters['country_name'] !== '')
			{
			$sql .= " AND Country.`name` LIKE :country_name";
			$params['country_name'] = '%' . $filters['country_name'] . '%';
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
		$sql = "SELECT COUNT(State.id) AS total FROM states AS State LEFT JOIN countries AS Country ON Country.id = State.country_id WHERE 1 = 1";
		$params = [];

		if (!empty($filters['id']))
			{
			$sql .= " AND State.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (!empty($filters['name']))
			{
			$sql .= " AND State.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (!empty($filters['acronym']))
			{
			$sql .= " AND State.`acronym` = :acronym";
			$params['acronym'] = $filters['acronym'];
			}

		if (!empty($filters['country_id']))
			{
			$sql .= " AND State.country_id = :country_id";
			$params['country_id'] = (int) $filters['country_id'];
			}
		elseif (!empty($filters['country_name']))
			{
			$sql .= " AND Country.`name` LIKE :country_name";
			$params['country_name'] = '%' . $filters['country_name'] . '%';
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
	 * Find all by state id
	 */
	public function findAllByStateId($id)
		{
		$stmt = $this->db->prepare("SELECT State.id, State.name, State.acronym, State.country_id, State.created, State.modified, Country.name AS country_name FROM states AS State LEFT JOIN countries AS Country ON Country.id = State.country_id WHERE State.id = :id");
		$stmt->execute(['id' => $id]);
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		return ['state' => $data];
		}

	/**
	 * Autocomplete method
	 */
	public function autocomplete(string $term, int $limit = 100, ?int $countryId = null, string $countryName = ''): array
		{
		$term = trim($term);
		if ($term === '')
			{
			return [];
			}

		$limit = max(1, min(50, (int) $limit));

		$sql = "SELECT State.id, State.`name`, s.acronym, s.country_id, Country.`name` AS country_name
			FROM states AS State
			LEFT JOIN countries AS Country ON Country.id = State.country_id
			WHERE State.`name` LIKE :t";
		$params = [':t' => '%' . $term . '%'];

		if ($countryId !== null && $countryId > 0)
			{
			$sql .= " AND State.country_id = :country_id";
			$params[':country_id'] = $countryId;
			}
		elseif (trim($countryName) !== '')
			{
			$sql .= " AND Country.`name` LIKE :country_name";
			$params[':country_name'] = '%' . trim($countryName) . '%';
			}

		$sql .= " ORDER BY State.`name` ASC LIMIT {$limit}";

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Get all by country id method
	 * @param Integer $countryId Country id
	 * @return array
	 */
	public function getAllByCountryId($countryId)
		{
		$stmt = $this->db->prepare("SELECT State.id,
		                                   State.name,
		                                   State.acronym
		                            FROM states AS State
		                            WHERE State.country_id = :country_id
		                            ORDER BY State.name ASC");
		$stmt->execute(['country_id' => $countryId]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Delete method - Deletes cities, neighborhoods, and addresses before deleting state
	 * @param Integer $id State id
	 * @throws \Exception
	 */
	public function delete($id): void
		{
		try
			{
			$this->db->beginTransaction();

			// Get all cities for this state
			$stmt = $this->db->prepare("SELECT id FROM cities WHERE state_id = :id");
			$stmt->execute(['id' => $id]);
			$cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// For each city, delete its neighborhoods and addresses
			foreach ($cities as $city)
				{
				$stmt = $this->db->prepare("SELECT id FROM neighborhoods WHERE city_id = :city_id");
				$stmt->execute(['city_id' => $city['id']]);
				$neighborhoods = $stmt->fetchAll(PDO::FETCH_ASSOC);

				foreach ($neighborhoods as $neighborhood)
					{
					$stmt = $this->db->prepare("DELETE FROM addresses WHERE neighborhood_id = :neighborhood_id");
					$stmt->execute(['neighborhood_id' => $neighborhood['id']]);
					}

				$stmt = $this->db->prepare("DELETE FROM neighborhoods WHERE city_id = :city_id");
				$stmt->execute(['city_id' => $city['id']]);
				}

			// Delete cities
			$stmt = $this->db->prepare("DELETE FROM cities WHERE state_id = :id");
			$stmt->execute(['id' => $id]);

			// Delete state
			$stmt = $this->db->prepare("DELETE FROM states WHERE id = :id");
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
