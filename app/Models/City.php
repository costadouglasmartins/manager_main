<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class City extends Model
	{
	/**
	 * Returns sortable columns for list queries.
	 *
	 * @return array<string, string>
	 */
	public static function sortableColumns(): array
		{
		return [
			'id'           => 'City.id',
			'name'         => 'City.`name`',
			'state_name'   => 'State.`name`',
			'country_name' => 'Country.`name`',
		];
		}

	/**
	 * Get all method
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT City.id,
		                                 City.name,
		                                 City.description,
		                                 City.state_id,
		                                 City.created,
		                                 City.modified,
		                                 State.name AS state_name
		                          FROM cities AS City
		                          LEFT JOIN states AS State ON State.id = City.state_id
		                          ORDER BY City.id DESC");
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

			$sql = "INSERT INTO cities
			             (`name`,
			              `description`,
			              `state_id`,
			              `created`,
			              `modified`)
			        VALUES
			             (:name,
			              :description,
			              :state_id,
			              :created,
			              :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'name'        => $data['name'],
				'description' => $data['description'] ?? null,
				'state_id'    => (int) $data['state_id'],
				'created'     => date('Y-m-d H:i:s'),
				'modified'    => date('Y-m-d H:i:s'),
			]);
			$cityId = $this->db->lastInsertId();

			$this->db->commit();

			return ['city_id' => $cityId];
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
		$stmt = $this->db->prepare("SELECT City.id,
		                                   City.name,
		                                   City.description,
		                                   City.state_id,
		                                   City.created,
		                                   City.modified,
		                                   State.name AS state_name
		                            FROM cities AS City
		                            LEFT JOIN states AS State ON State.id = City.state_id
		                            WHERE City.id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 */
	public function update($id, $data)
		{
		$stmt = $this->db->prepare("UPDATE cities
		                            SET `name`        = :name,
		                                `description` = :description,
		                                `state_id`    = :state_id,
		                                `modified`    = :modified
		                            WHERE id = :id");

		$stmt->execute([
			'name'        => $data['name'],
			'description' => $data['description'] ?? null,
			'state_id'    => (int) $data['state_id'],
			'modified'    => date('Y-m-d H:i:s'),
			'id'          => $id,
		]);
		}

	/**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?array $sort = null): array
		{
		$sql = "SELECT City.id,
		               City.name,
		               City.description,
		               City.state_id,
		               City.created,
		               City.modified,
		               State.name AS state_name,
		               Country.name AS country_name
		        FROM cities AS City
		        LEFT JOIN states AS State ON State.id = City.state_id
		        LEFT JOIN countries AS Country ON Country.id = State.country_id
		        WHERE 1=1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND City.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND City.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['state_id']) && $filters['state_id'] !== '')
			{
			$sql .= " AND City.state_id = :state_id";
			$params['state_id'] = (int) $filters['state_id'];
			}
		elseif (isset($filters['state_name']) && $filters['state_name'] !== '')
			{
			$sql .= " AND State.`name` LIKE :state_name";
			$params['state_name'] = '%' . $filters['state_name'] . '%';
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
		$sql = "SELECT COUNT(*) AS total
		        FROM cities AS City
		        LEFT JOIN states AS State ON State.id = City.state_id
		        LEFT JOIN countries AS Country ON Country.id = State.country_id
		        WHERE 1=1";
		$params = [];

		if (!empty($filters['id']))
			{
			$sql .= " AND City.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (!empty($filters['name']))
			{
			$sql .= " AND City.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (!empty($filters['state_id']))
			{
			$sql .= " AND City.state_id = :state_id";
			$params['state_id'] = (int) $filters['state_id'];
			}
		elseif (!empty($filters['state_name']))
			{
			$sql .= " AND State.`name` LIKE :state_name";
			$params['state_name'] = '%' . $filters['state_name'] . '%';
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
	 * Find all by city id
	 */
	public function findAllByCityId($id)
		{
		$stmt = $this->db->prepare("SELECT City.id,
		                                   City.name,
		                                   City.description,
		                                   City.state_id,
		                                   City.created,
		                                   City.modified,
		                                   State.name AS state_name
		                            FROM cities AS City
		                            LEFT JOIN states AS State ON State.id = City.state_id
		                            WHERE City.id = :id");
		$stmt->execute(['id' => $id]);
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		return ['city' => $data];
		}

	/**
	 * Autocomplete method
	 */
	public function autocomplete(string $term, int $limit = 100, ?int $stateId = null, ?int $countryId = null, string $stateName = '', string $countryName = ''): array
		{
		$term = trim($term);
		if ($term === '')
			{
			return [];
			}

		$limit = max(1, min(50, (int) $limit));

		$sql = "SELECT City.id,
		               City.`name`,
		               City.state_id,
		               State.`name` AS state_name,
		               State.acronym AS state_acronym,
		               State.country_id,
		               Country.`name` AS country_name
		        FROM cities AS City
		        LEFT JOIN states AS State ON State.id = City.state_id
		        LEFT JOIN countries AS Country ON Country.id = State.country_id
		        WHERE City.`name` LIKE :t";
		$params = [':t' => '%' . $term . '%'];

		if ($stateId !== null && $stateId > 0)
			{
			$sql .= " AND City.state_id = :state_id";
			$params[':state_id'] = $stateId;
			}
		elseif (trim($stateName) !== '')
			{
			$sql .= " AND State.`name` LIKE :state_name";
			$params[':state_name'] = '%' . trim($stateName) . '%';
			}

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

		$sql .= " ORDER BY City.`name` ASC
		          LIMIT {$limit}";

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Delete method - Deletes neighborhoods and their addresses before deleting city
	 * @param Integer $id City id
	 * @throws \Exception
	 */
	public function delete($id): void
		{
		try
			{
			$this->db->beginTransaction();

			// Get all neighborhoods for this city
			$stmt = $this->db->prepare("SELECT Neighborhood.id
			                            FROM neighborhoods AS Neighborhood
			                            WHERE Neighborhood.city_id = :id");
			$stmt->execute(['id' => $id]);
			$neighborhoods = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// Delete addresses for each neighborhood
			foreach ($neighborhoods as $neighborhood)
				{
				$stmt = $this->db->prepare("DELETE FROM addresses
				                            WHERE neighborhood_id = :neighborhood_id");
				$stmt->execute(['neighborhood_id' => $neighborhood['id']]);
				}

			// Delete neighborhoods
			$stmt = $this->db->prepare("DELETE FROM neighborhoods
			                            WHERE city_id = :id");
			$stmt->execute(['id' => $id]);

			// Delete city
			$stmt = $this->db->prepare("DELETE FROM cities
			                            WHERE id = :id");
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
