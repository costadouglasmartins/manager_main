<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class State extends Model
	{

	/**
	 * Get all method
	 * @return associative array
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT s.*, c.name AS country_name FROM states s LEFT JOIN countries c ON c.id = s.country_id ORDER BY s.id DESC");
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
		$stmt = $this->db->prepare("SELECT s.*, c.name AS country_name FROM states s LEFT JOIN countries c ON c.id = s.country_id WHERE s.id = :id");
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
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = "SELECT s.*, c.name AS country_name FROM states s LEFT JOIN countries c ON c.id = s.country_id WHERE 1=1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND s.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND s.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['acronym']) && $filters['acronym'] !== '')
			{
			$sql .= " AND s.`acronym` = :acronym";
			$params['acronym'] = $filters['acronym'];
			}

		$sql .= " ORDER BY s.id DESC LIMIT :limit OFFSET :offset";

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
		$sql = "SELECT COUNT(*) AS total FROM states WHERE 1=1";
		$params = [];

		if (!empty($filters['id']))
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (!empty($filters['name']))
			{
			$sql .= " AND `name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (!empty($filters['acronym']))
			{
			$sql .= " AND `acronym` = :acronym";
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
	 * Find all by state id
	 */
	public function findAllByStateId($id)
		{
		$stmt = $this->db->prepare("SELECT s.*, c.name AS country_name FROM states s LEFT JOIN countries c ON c.id = s.country_id WHERE s.id = :id");
		$stmt->execute(['id' => $id]);
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		return ['state' => $data];
		}

	/**
	 * Autocomplete method
	 */
	public function autocomplete(string $term, int $limit = 100): array
		{
		$term = trim($term);
		if ($term === '')
			{
			return [];
			}

		$limit = max(1, min(50, (int) $limit));

		$sql = "SELECT id, `name`, acronym FROM states WHERE `name` LIKE :t ORDER BY `name` ASC LIMIT {$limit}";

		$stmt = $this->db->prepare($sql);
		$stmt->execute([':t' => '%' . $term . '%']);

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
