<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Country extends Model
	{
	/**
	 * Returns sortable columns for list queries.
	 *
	 * @return array<string, string>
	 */
	public static function sortableColumns(): array
		{
		return [
			'id'      => 'Country.id',
			'name'    => 'Country.`name`',
			'acronym' => 'Country.`acronym`',
		];
		}

	/**
	 * Get all method
	 * @return associative array
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT Country.id,
		                                 Country.name,
		                                 Country.acronym,
		                                 Country.created,
		                                 Country.modified
		                          FROM countries AS Country
		                          ORDER BY Country.id DESC");
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
			// Start transaction
			$this->db->beginTransaction();

			// Save one person
			$sql = "INSERT INTO countries
			             (`name`,
			              `acronym`,
			              `created`,
			              `modified`)
			        VALUES
			             (:name,
			              :acronym,
			              :created,
			              :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'name' => $data['name'],
				'acronym' => $data['acronym'],
				'created' => date('Y-m-d H:i:s'),
				'modified' => date('Y-m-d H:i:s')
			]);
			// Store person id
			$countryId = $this->db->lastInsertId();
			$country = $this->find($countryId);
			$this->auditCreate('country', 'countries', $countryId, $country);

			// Confirms the transaction
			$this->db->commit();

			// Return created IDs
			return [
				'country_id' => $countryId,
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
	 * Find method
	 * @param Integer $id Person id
	 * @return array
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare("SELECT Country.id,
		                                   Country.name,
		                                   Country.acronym,
		                                   Country.created,
		                                   Country.modified
		                            FROM countries AS Country
		                            WHERE Country.id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 * @param Integer $id Person id
	 * @param array $data
	 */
	public function update($id, $data)
    {
    $beforeData = $this->find($id);
    $stmt = $this->db->prepare("UPDATE countries
                                SET `name`     = :name,
                                    `acronym`  = :acronym,
                                    `modified` = :modified
                                WHERE id = :id");

    $stmt->execute([
        'name'      => $data['name'],
        'acronym'   => $data['acronym'],
        'modified'  => date('Y-m-d H:i:s'),
        'id'        => $id
    ]);
    $afterData = $this->find($id);
    $this->auditUpdate('country', 'countries', $id, $beforeData, $afterData);
    }

	/**
	 * Search method 
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?array $sort = null): array
		{
		$sql = "SELECT Country.id,
		               Country.name,
		               Country.acronym,
		               Country.created,
		               Country.modified
		        FROM countries AS Country
		        WHERE 1 = 1";
		$params = [];

		// Id 
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Country.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		// Name
		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND Country.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		// Type
		if (isset($filters['acronym']) && $filters['acronym'] !== '')
			{
			$sql .= " AND Country.`acronym` = :acronym";
			$params['acronym'] = $filters['acronym'];
			}

		$orderBy = sortOrderBy($sort, self::sortableColumns(), 'id', 'desc');
		$sql .= " ORDER BY {$orderBy}
		          LIMIT :limit
		          OFFSET :offset";

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
	 * @param Array $filters
	 * @return int
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(Country.id) AS total
		        FROM countries AS Country
		        WHERE 1 = 1";
		$params = [];

		// Filter by ID (exact)
		if (!empty($filters['id']))
			{
			$sql .= " AND Country.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		// Filter by name (LIKE)
		if (!empty($filters['name']))
			{
			$sql .= " AND Country.name LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		// Filter by type
		if (!empty($filters['acronym']))
			{
			$sql .= " AND Country.`acronym` = :acronym";
			$params['acronym'] = $filters['acronym'];
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
	 * Find all by person id
	 */
	public function findAllByCountryId($id)
		{
		$personStmt = $this->db->prepare("SELECT Country.id,
		                                         Country.name,
		                                         Country.acronym,
		                                         Country.created,
		                                         Country.modified
		                                  FROM countries AS Country
		                                  WHERE Country.id = :id");
		$personStmt->execute(['id' => $id]);
		$personData = $personStmt->fetch(PDO::FETCH_ASSOC);
		return ['countries' => $personData];
		}

	/**
	 * Autocomplete method
	 * @param string $term
	 * @param int $limit
	 * @return array
	 */
	public function autocomplete(string $term, int $limit = 100): array
		{
		$term = trim($term);
		if ($term === '')
			{
			return [];
			}

		$limit = max(1, min(50, (int) $limit));

		$sql = "SELECT Country.id,
		               Country.`name`,
		               Country.acronym
		        FROM countries AS Country
		        WHERE Country.`name` LIKE :t
		        ORDER BY COALESCE(Country.`name`) ASC
		        LIMIT {$limit}";

		$stmt = $this->db->prepare($sql);
		$stmt->execute([':t' => '%' . $term . '%']);

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
		}

	/**
	 * Delete method - Deletes states, cities, neighborhoods, and addresses before deleting country
	 * @param Integer $id Country id
	 * @throws \Exception
	 */
	public function delete($id): void
		{
		try
			{
			$beforeData = $this->find($id);
			$this->db->beginTransaction();

			// Get all states for this country
			$stmt = $this->db->prepare("SELECT State.id
			                            FROM states AS State
			                            WHERE State.country_id = :id");
			$stmt->execute(['id' => $id]);
			$states = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// For each state, delete its cities, neighborhoods, and addresses
			foreach ($states as $state)
				{
				$stmt = $this->db->prepare("SELECT City.id
				                            FROM cities AS City
				                            WHERE City.state_id = :state_id");
				$stmt->execute(['state_id' => $state['id']]);
				$cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

				foreach ($cities as $city)
					{
					$stmt = $this->db->prepare("SELECT Neighborhood.id
					                            FROM neighborhoods AS Neighborhood
					                            WHERE Neighborhood.city_id = :city_id");
					$stmt->execute(['city_id' => $city['id']]);
					$neighborhoods = $stmt->fetchAll(PDO::FETCH_ASSOC);

					foreach ($neighborhoods as $neighborhood)
						{
						$stmt = $this->db->prepare("DELETE FROM addresses
						                            WHERE neighborhood_id = :neighborhood_id");
						$stmt->execute(['neighborhood_id' => $neighborhood['id']]);
						}

					$stmt = $this->db->prepare("DELETE FROM neighborhoods
					                            WHERE city_id = :city_id");
					$stmt->execute(['city_id' => $city['id']]);
					}

				$stmt = $this->db->prepare("DELETE FROM cities
				                            WHERE state_id = :state_id");
				$stmt->execute(['state_id' => $state['id']]);
				}

			// Delete states
			$stmt = $this->db->prepare("DELETE FROM states
			                            WHERE country_id = :id");
			$stmt->execute(['id' => $id]);

			// Delete country
			$stmt = $this->db->prepare("DELETE FROM countries
			                            WHERE id = :id");
			$stmt->execute(['id' => $id]);
			$this->auditDelete('country', 'countries', $id, $beforeData, [
				'affected_tables' => ['countries', 'states', 'cities', 'neighborhoods', 'addresses'],
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
