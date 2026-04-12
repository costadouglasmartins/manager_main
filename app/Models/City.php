<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class City extends Model
	{

	/**
	 * Get all method
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT c.*, s.name AS state_name FROM cities c LEFT JOIN states s ON s.id = c.state_id ORDER BY c.id DESC");
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

			$sql = "INSERT INTO cities (`name`, `description`, `state_id`, `created`, `modified`) VALUES (:name, :description, :state_id, :created, :modified)";
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
		$stmt = $this->db->prepare("SELECT c.*, s.name AS state_name FROM cities c LEFT JOIN states s ON s.id = c.state_id WHERE c.id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 */
	public function update($id, $data)
		{
		$stmt = $this->db->prepare("
			UPDATE cities
			SET
				`name`        = :name,
				`description` = :description,
				`state_id`    = :state_id,
				`modified`    = :modified
			WHERE id = :id
		");

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
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = "SELECT c.*, s.name AS state_name FROM cities c LEFT JOIN states s ON s.id = c.state_id WHERE 1=1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND c.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND c.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['state_id']) && $filters['state_id'] !== '')
			{
			$sql .= " AND c.state_id = :state_id";
			$params['state_id'] = (int) $filters['state_id'];
			}

		$sql .= " ORDER BY c.id DESC LIMIT :limit OFFSET :offset";

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
		$sql = "SELECT COUNT(*) AS total FROM cities WHERE 1=1";
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

		if (!empty($filters['state_id']))
			{
			$sql .= " AND state_id = :state_id";
			$params['state_id'] = (int) $filters['state_id'];
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
		$stmt = $this->db->prepare("SELECT c.*, s.name AS state_name FROM cities c LEFT JOIN states s ON s.id = c.state_id WHERE c.id = :id");
		$stmt->execute(['id' => $id]);
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		return ['city' => $data];
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

		$sql = "SELECT c.id, c.`name`, s.acronym AS state_acronym
			FROM cities c
			LEFT JOIN states s ON s.id = c.state_id
			WHERE c.`name` LIKE :t
			ORDER BY c.`name` ASC
			LIMIT {$limit}";

		$stmt = $this->db->prepare($sql);
		$stmt->execute([':t' => '%' . $term . '%']);

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
			$stmt = $this->db->prepare("SELECT id FROM neighborhoods WHERE city_id = :id");
			$stmt->execute(['id' => $id]);
			$neighborhoods = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// Delete addresses for each neighborhood
			foreach ($neighborhoods as $neighborhood)
				{
				$stmt = $this->db->prepare("DELETE FROM addresses WHERE neighborhood_id = :neighborhood_id");
				$stmt->execute(['neighborhood_id' => $neighborhood['id']]);
				}

			// Delete neighborhoods
			$stmt = $this->db->prepare("DELETE FROM neighborhoods WHERE city_id = :id");
			$stmt->execute(['id' => $id]);

			// Delete city
			$stmt = $this->db->prepare("DELETE FROM cities WHERE id = :id");
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
