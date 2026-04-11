<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Country extends Model
	{

	/**
	 * Get all method
	 * @return associative array
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT * FROM countries ORDER BY id DESC");
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
			// Início da transação
			$this->db->beginTransaction();

			// Save one person
			$sql = "INSERT INTO countries 
                        (`name`, `acronym`, `created`, `modified`) 
                      VALUES 
                        ( :name, :acronym, :created, :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'name' => $data['name'],
				'acronym' => $data['acronym'],
				'created' => date('Y-m-d H:i:s'),
				'modified' => date('Y-m-d H:i:s')
			]);
			// Store person id
			$countryId = $this->db->lastInsertId();

			// Confirms the transaction
			$this->db->commit();

			// Retorna os IDs criados
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
		$stmt = $this->db->prepare("SELECT * FROM countries WHERE id = :id");
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
    $stmt = $this->db->prepare("
        UPDATE countries
        SET
            `name`      = :name,
            `acronym`   = :acronym,
            `modified`  = :modified
        WHERE id = :id
    ");

    $stmt->execute([
        'name'      => $data['name'],
        'acronym'   => $data['acronym'],
        'modified'  => date('Y-m-d H:i:s'),
        'id'        => $id
    ]);
    }

	/**
	 * Search method 
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = "SELECT * FROM countries WHERE 1=1";
		$params = [];

		// Id 
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		// Name
		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND `name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		// Type
		if (isset($filters['acronym']) && $filters['acronym'] !== '')
			{
			$sql .= " AND `acronym` = :acronym";
			$params['acronym'] = $filters['acronym'];
			}

		$sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

		$stmt = $this->db->prepare($sql);

		// Bind dos filtros
		foreach ($params as $key => $value)
			{
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue(':' . $key, $value, $paramType);
			}

		// Bind da paginação
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
		$sql = "SELECT COUNT(*) AS total FROM countries WHERE 1=1";
		$params = [];

		// Filtro por ID (igual)
		if (!empty($filters['id']))
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		// Filtro por Nome (LIKE)
		if (!empty($filters['name']))
			{
			$sql .= " AND name LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		// Filtro por Tipo
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
	 * Find all by person id
	 */
	public function findAllByCountryId($id)
		{
		$personStmt = $this->db->prepare("SELECT * FROM countries WHERE id = :id");
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

		$sql = "SELECT id, `name`, acronym
						FROM countries
						WHERE `name` LIKE :t
						ORDER BY COALESCE(`name`) ASC
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
			$this->db->beginTransaction();

			// Get all states for this country
			$stmt = $this->db->prepare("SELECT id FROM states WHERE country_id = :id");
			$stmt->execute(['id' => $id]);
			$states = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// For each state, delete its cities, neighborhoods, and addresses
			foreach ($states as $state)
				{
				$stmt = $this->db->prepare("SELECT id FROM cities WHERE state_id = :state_id");
				$stmt->execute(['state_id' => $state['id']]);
				$cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

				$stmt = $this->db->prepare("DELETE FROM cities WHERE state_id = :state_id");
				$stmt->execute(['state_id' => $state['id']]);
				}

			// Delete states
			$stmt = $this->db->prepare("DELETE FROM states WHERE country_id = :id");
			$stmt->execute(['id' => $id]);

			// Delete country
			$stmt = $this->db->prepare("DELETE FROM countries WHERE id = :id");
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