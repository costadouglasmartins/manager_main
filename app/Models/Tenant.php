<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Tenant extends Model
	{

	/**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = "SELECT Tenant.id,
		               Tenant.name,
		               Tenant.fantasy,
		               Tenant.document,
		               Tenant.database_number,
		               Tenant.status,
		               Tenant.observations,
		               Tenant.created,
		               Tenant.modified
		        FROM tenants AS Tenant
		        WHERE 1=1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Tenant.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND Tenant.name LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['database_number']) && $filters['database_number'] !== '')
			{
			$sql .= " AND Tenant.database_number LIKE :database_number";
			$params['database_number'] = '%' . $filters['database_number'] . '%';
			}

		if (isset($filters['status']) && $filters['status'] !== '')
			{
			$sql .= " AND Tenant.status = :status";
			$params['status'] = $filters['status'];
			}

		$sql .= " ORDER BY Tenant.id DESC LIMIT :limit OFFSET :offset";

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
		$sql = "SELECT COUNT(*) AS total FROM tenants AS Tenant WHERE 1=1";
		$params = [];

		if (!empty($filters['id']))
			{
			$sql .= " AND Tenant.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (!empty($filters['name']))
			{
			$sql .= " AND Tenant.name LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (!empty($filters['database_number']))
			{
			$sql .= " AND Tenant.database_number LIKE :database_number";
			$params['database_number'] = '%' . $filters['database_number'] . '%';
			}

		if (!empty($filters['status']))
			{
			$sql .= " AND Tenant.status = :status";
			$params['status'] = $filters['status'];
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
	 * Find method
	 */
	public function find(int $id): ?array
		{
		$stmt = $this->db->prepare("SELECT Tenant.id,
		                                   Tenant.name,
		                                   Tenant.fantasy,
		                                   Tenant.document,
		                                   Tenant.database_number,
		                                   Tenant.status,
		                                   Tenant.observations,
		                                   Tenant.created,
		                                   Tenant.modified
		                            FROM tenants AS Tenant
		                            WHERE Tenant.id = :id
		                            LIMIT 1");
		$stmt->execute(['id' => $id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
		}

	/**
	 * Next database number method
	 */
	public function nextDatabaseNumber(): string
		{
		$stmt = $this->db->query("SELECT MAX(CAST(Tenant.database_number AS UNSIGNED)) AS max_num FROM tenants AS Tenant");
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$next = ((int) ($row['max_num'] ?? 0)) + 1;
		return str_pad($next, 4, '0', STR_PAD_LEFT);
		}

	/**
	 * Create method
	 */
	public function create(array $data): int
		{
		$now = date('Y-m-d H:i:s');
		$stmt = $this->db->prepare("INSERT INTO tenants (name, fantasy, document, database_number, status, observations, created, modified)
		                            VALUES (:name, :fantasy, :document, :database_number, :status, :observations, :created, :modified)");

		$stmt->execute([
			'name'            => $data['name'],
			'fantasy'         => $data['fantasy'] ?? null,
			'document'        => $data['document'] ?? null,
			'database_number' => $data['database_number'],
			'status'          => $data['status'] ?? 'pending',
			'observations'    => $data['observations'] ?? null,
			'created'         => $now,
			'modified'        => $now,
		]);

		return (int) $this->db->lastInsertId();
		}

	/**
	 * Update method
	 */
	public function update(int $id, array $data): void
		{
		$stmt = $this->db->prepare("UPDATE tenants AS Tenant
		                            SET Tenant.name         = :name,
		                                Tenant.fantasy      = :fantasy,
		                                Tenant.document     = :document,
		                                Tenant.status       = :status,
		                                Tenant.observations = :observations,
		                                Tenant.modified     = :modified
		                            WHERE Tenant.id = :id");

		$stmt->execute([
			'name'         => $data['name'],
			'fantasy'      => $data['fantasy'] ?? null,
			'document'     => $data['document'] ?? null,
			'status'       => $data['status'] ?? 'pending',
			'observations' => $data['observations'] ?? null,
			'modified'     => date('Y-m-d H:i:s'),
			'id'           => $id,
		]);
		}

	/**
	 * Get all active method
	 * Used by TenantConfig to resolve tenant at login (only active tenants can log in).
	 */
	public function getAllActive(): array
		{
		$stmt = $this->db->query("SELECT Tenant.id,
		                                 Tenant.name,
		                                 Tenant.database_number
		                          FROM tenants AS Tenant
		                          WHERE Tenant.status = 'active'
		                          ORDER BY Tenant.name ASC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Get all method
	 * Returns all tenants regardless of status.
	 * Used to populate dropdowns in user edit form.
	 */
	public function getAll(): array
		{
		$stmt = $this->db->query("SELECT Tenant.id,
		                                 Tenant.name,
		                                 Tenant.fantasy,
		                                 Tenant.database_number,
		                                 Tenant.status
		                          FROM tenants AS Tenant
		                          ORDER BY Tenant.name ASC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}
	}

?>
