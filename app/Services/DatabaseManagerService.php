<?php

namespace App\Services;

use PDO;
use PDOException;

class DatabaseManagerService
	{

	private string $dbHost = 'mysql';
	private string $dbUser = 'root';
	private string $dbPassword = 'rootpassword';
	private string $dbCharset = 'utf8';
	private string $dbPrefix = 'manager_';

	public function getCatalog(): array
		{
		$pdo = $this->getAdminConnection();
		$prefixStart = strlen($this->dbPrefix) + 1;

		$sql = "SELECT s.SCHEMA_NAME AS database_name,
		               SUBSTRING(s.SCHEMA_NAME, {$prefixStart}) AS database_number,
		               CAST(SUBSTRING(s.SCHEMA_NAME, {$prefixStart}) AS UNSIGNED) AS database_order,
		               t.id AS tenant_id,
		               t.name AS tenant_name,
		               t.fantasy AS tenant_fantasy,
		               t.status AS tenant_status
		        FROM information_schema.SCHEMATA AS s
		        LEFT JOIN manager_main.tenants AS t
		          ON t.database_number = SUBSTRING(s.SCHEMA_NAME, {$prefixStart})
		        WHERE s.SCHEMA_NAME REGEXP '^manager_[0-9]+$'
		        ORDER BY CAST(SUBSTRING(s.SCHEMA_NAME, {$prefixStart}) AS UNSIGNED) ASC";

		$stmt = $pdo->prepare($sql);
		$stmt->execute();

		$catalog = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
			{
			$catalog[] = [
				'database_name' => (string) $row['database_name'],
				'database_number' => (string) $row['database_number'],
				'database_order' => (int) $row['database_order'],
				'tenant_id' => ($row['tenant_id'] !== null ? (int) $row['tenant_id'] : null),
				'tenant_name' => ($row['tenant_name'] !== null ? (string) $row['tenant_name'] : null),
				'tenant_fantasy' => ($row['tenant_fantasy'] !== null ? (string) $row['tenant_fantasy'] : null),
				'tenant_status' => ($row['tenant_status'] !== null ? (string) $row['tenant_status'] : null),
			];
			}

		return $catalog;
		}

	public function summarizeCatalog(array $catalog): array
		{
		$linkedTenants = 0;
		$activeTenants = 0;
		$unlinkedDatabases = 0;

		foreach ($catalog as $row)
			{
			if (!empty($row['tenant_id']))
				{
				$linkedTenants++;
				}
			else
				{
				$unlinkedDatabases++;
				}

			if (($row['tenant_status'] ?? null) === 'active')
				{
				$activeTenants++;
				}
			}

		return [
			'total_databases' => count($catalog),
			'linked_tenants' => $linkedTenants,
			'active_tenants' => $activeTenants,
			'unlinked_databases' => $unlinkedDatabases,
		];
		}

	public function resolveTargets(string $scope, ?string $databaseStart = null, ?string $databaseEnd = null): array
		{
		$catalog = $this->getCatalog();

		if ($scope === 'all_tenants')
			{
			return $catalog;
			}

		if ($scope !== 'range')
			{
			throw new \InvalidArgumentException('Escopo de seleção inválido.');
			}

		$start = $this->normalizeDatabaseNumber($databaseStart);
		$end = $this->normalizeDatabaseNumber($databaseEnd);

		if ($start === null)
			{
			throw new \InvalidArgumentException('Informe o banco inicial para executar por intervalo.');
			}

		if ($end === null)
			{
			$end = $start;
			}

		$startOrder = (int) $start;
		$endOrder = (int) $end;

		if ($endOrder < $startOrder)
			{
			$tmp = $startOrder;
			$startOrder = $endOrder;
			$endOrder = $tmp;
			}

		$targets = [];
		foreach ($catalog as $database)
			{
			if ($database['database_order'] < $startOrder || $database['database_order'] > $endOrder)
				{
				continue;
				}

			$targets[] = $database;
			}

		return $targets;
		}

	public function normalizeDatabaseNumber(?string $value): ?string
		{
		$digits = preg_replace('/\D+/', '', (string) $value);

		if ($digits === '')
			{
			return null;
			}

		$trimmed = ltrim($digits, '0');
		if ($trimmed === '')
			{
			$trimmed = '0';
			}

		return str_pad($trimmed, 4, '0', STR_PAD_LEFT);
		}

	public function validateSql(string $sql): ?string
		{
		$sql = trim($sql);

		if ($sql === '')
			{
			return 'Informe um comando SQL para executar.';
			}

		if (preg_match('/\bDELIMITER\b/i', $sql))
			{
			return 'Comandos com DELIMITER não são suportados neste módulo.';
			}

		if (preg_match('/\bUSE\b\s+/i', $sql))
			{
			return 'Não use o comando USE. O banco é escolhido automaticamente para cada execução.';
			}

		return null;
		}

	public function buildSqlPreview(string $sql): string
		{
		$preview = preg_replace('/\s+/', ' ', trim($sql));
		if (!is_string($preview))
			{
			return '';
			}

		if (strlen($preview) > 180)
			{
			return substr($preview, 0, 180) . '...';
			}

		return $preview;
		}

	public function executeScript(string $databaseName, string $sql): array
		{
		$startedAt = microtime(true);

		try
			{
			$pdo = $this->getDatabaseConnection($databaseName);
			$pdo->exec($sql);

			return [
				'success' => true,
				'message' => 'Script executado com sucesso.',
				'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
			];
			}
		catch (PDOException $e)
			{
			return [
				'success' => false,
				'message' => $e->getMessage(),
				'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
			];
			}
		}

	private function getAdminConnection(): PDO
		{
		$dsn = sprintf('mysql:host=%s;charset=%s', $this->dbHost, $this->dbCharset);
		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		];

		if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS'))
			{
			$options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
			}

		return new PDO($dsn, $this->dbUser, $this->dbPassword, $options);
		}

	private function getDatabaseConnection(string $databaseName): PDO
		{
		$dsn = sprintf(
			'mysql:host=%s;dbname=%s;charset=%s',
			$this->dbHost,
			$databaseName,
			$this->dbCharset
		);

		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		];

		if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS'))
			{
			$options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
			}

		return new PDO($dsn, $this->dbUser, $this->dbPassword, $options);
		}
	}

?>
