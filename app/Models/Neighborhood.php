<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Neighborhood extends Model
	{

	/**
	 * Get all method
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT n.*, c.name AS city_name FROM neighborhoods n LEFT JOIN cities c ON c.id = n.city_id ORDER BY n.id DESC");
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
		$stmt = $this->db->prepare("SELECT n.*, c.name AS city_name FROM neighborhoods n LEFT JOIN cities c ON c.id = n.city_id WHERE n.id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 */
	public function update($id, $data)
		{
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
		}

	/**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = "SELECT n.*, c.name AS city_name FROM neighborhoods n LEFT JOIN cities c ON c.id = n.city_id WHERE 1=1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND n.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND n.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['city_id']) && $filters['city_id'] !== '')
			{
			$sql .= " AND n.city_id = :city_id";
			$params['city_id'] = (int) $filters['city_id'];
			}

		$sql .= " ORDER BY n.id DESC LIMIT :limit OFFSET :offset";

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
		$sql = "SELECT COUNT(*) AS total FROM neighborhoods WHERE 1=1";
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

		if (!empty($filters['city_id']))
			{
			$sql .= " AND city_id = :city_id";
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
		$stmt = $this->db->prepare("SELECT n.*, c.name AS city_name FROM neighborhoods n LEFT JOIN cities c ON c.id = n.city_id WHERE n.id = :id");
		$stmt->execute(['id' => $id]);
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		return ['neighborhood' => $data];
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

		$sql = "SELECT id, `name` FROM neighborhoods WHERE `name` LIKE :t ORDER BY `name` ASC LIMIT {$limit}";

		$stmt = $this->db->prepare($sql);
		$stmt->execute([':t' => '%' . $term . '%']);

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
			$this->db->beginTransaction();

			// Delete dependent addresses
			$stmt = $this->db->prepare("DELETE FROM addresses WHERE neighborhood_id = :id");
			$stmt->execute(['id' => $id]);

			// Delete neighborhood
			$stmt = $this->db->prepare("DELETE FROM neighborhoods WHERE id = :id");
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
