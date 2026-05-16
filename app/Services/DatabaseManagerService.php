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
	private int $maxResultRows = 200;

	public function getCatalog(): array
		{
		$pdo = $this->getAdminConnection();
		$prefixStart = strlen($this->dbPrefix) + 1;

		$sql = "SELECT s.SCHEMA_NAME AS database_name,
		               SUBSTRING(s.SCHEMA_NAME, {$prefixStart}) AS database_number,
		               CAST(SUBSTRING(s.SCHEMA_NAME, {$prefixStart}) AS UNSIGNED) AS database_order,
		               t.id AS tenant_id,
		               (SELECT p.name
		                  FROM manager_main.tenant_users AS tu
		                  INNER JOIN manager_main.users AS u ON u.id = tu.user_id
		                  INNER JOIN manager_main.people AS p ON p.id = u.person_id
		                  WHERE tu.tenant_id = t.id
		                  ORDER BY tu.role = 'owner' DESC,
		                           tu.created ASC,
		                           tu.user_id ASC
		                  LIMIT 1) AS tenant_name,
		               (SELECT p.fantasy
		                  FROM manager_main.tenant_users AS tu
		                  INNER JOIN manager_main.users AS u ON u.id = tu.user_id
		                  INNER JOIN manager_main.people AS p ON p.id = u.person_id
		                  WHERE tu.tenant_id = t.id
		                  ORDER BY tu.role = 'owner' DESC,
		                           tu.created ASC,
		                           tu.user_id ASC
		                  LIMIT 1) AS tenant_fantasy,
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
			$stmt = $pdo->query($sql);
			if ($stmt === false)
				{
				throw new PDOException('Não foi possível executar o script SQL.');
				}

			$resultSets = [];
			$affectedRows = 0;
			$statementIndex = 1;

			do
				{
				if ($stmt->columnCount() > 0)
					{
					$resultSets[] = $this->fetchResultSet($stmt, $statementIndex);
					}
				else
					{
					$rowCount = $stmt->rowCount();
					if ($rowCount > 0)
						{
						$affectedRows += $rowCount;
						}
					}

				$statementIndex++;
				}
			while ($stmt->nextRowset());

			return [
				'success' => true,
				'message' => $this->buildSuccessMessage($resultSets, $affectedRows),
				'affected_rows' => $affectedRows,
				'result_sets' => $resultSets,
				'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
			];
			}
		catch (PDOException $e)
			{
			return [
				'success' => false,
				'message' => $e->getMessage(),
				'affected_rows' => 0,
				'result_sets' => [],
				'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
			];
			}
		}

	/**
	 * Fetches a single SQL result set while limiting rows sent back to the browser.
	 *
	 * @param \PDOStatement $stmt Executed statement positioned on a rowset.
	 * @param int $statementIndex Statement index inside the submitted SQL script.
	 * @return array<string, mixed>
	 */
	private function fetchResultSet(\PDOStatement $stmt, int $statementIndex): array
		{
		$columns = [];
		$columnCount = $stmt->columnCount();
		for ($index = 0; $index < $columnCount; $index++)
			{
			$meta = $stmt->getColumnMeta($index);
			$name = is_array($meta) && isset($meta['name']) ? (string) $meta['name'] : '';
			$columns[] = $name !== '' ? $name : 'column_' . ($index + 1);
			}

		$rows = [];
		$totalRows = 0;
		while (($row = $stmt->fetch(PDO::FETCH_NUM)) !== false)
			{
			$totalRows++;
			if (count($rows) < $this->maxResultRows)
				{
				$rows[] = $this->normalizeResultRow($row);
				}
			}

		return [
			'statement_index' => $statementIndex,
			'columns' => $columns,
			'rows' => $rows,
			'row_count' => $totalRows,
			'displayed_rows' => count($rows),
			'truncated' => $totalRows > $this->maxResultRows,
			'max_rows' => $this->maxResultRows,
		];
		}

	/**
	 * Normalizes database values for JSON streaming.
	 *
	 * @param array<int, mixed> $row Database result row.
	 * @return array<int, mixed>
	 */
	private function normalizeResultRow(array $row): array
		{
		foreach ($row as $key => $value)
			{
			if ($value === null || is_scalar($value))
				{
				continue;
				}

			$encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$row[$key] = $encoded !== false ? $encoded : '[unserializable value]';
			}

		return $row;
		}

	/**
	 * Builds the execution message shown in the real-time stream.
	 *
	 * @param array<int, array<string, mixed>> $resultSets SELECT-like result sets.
	 * @param int $affectedRows Number of affected rows for write statements.
	 */
	private function buildSuccessMessage(array $resultSets, int $affectedRows): string
		{
		if (!empty($resultSets))
			{
			$totalRows = 0;
			foreach ($resultSets as $resultSet)
				{
				$totalRows += (int) ($resultSet['row_count'] ?? 0);
				}

			return sprintf(
				'Consulta executada com sucesso. %d conjunto(s) de resultado, %d linha(s) retornada(s).',
				count($resultSets),
				$totalRows
			);
			}

		return sprintf('Script executado com sucesso. %d linha(s) afetada(s).', $affectedRows);
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
