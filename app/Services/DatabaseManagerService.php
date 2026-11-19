<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use PDOException;

class DatabaseManagerService
	{

	private string $dbPrefix = 'manager_';
	private int $maxResultRows = 200;

	/**
	 * Retrieve the catalog of tenant databases and linked tenant metadata.
	 * @return array Available tenant databases with tenant information.
	 **/
	public function getCatalog(): array
		{
		$pdo = $this->getAdminConnection();
		$prefixStart = strlen($this->dbPrefix) + 1;
		$managerDatabase = $this->quoteIdentifier(Database::getManagerDatabaseName());

		/** Compare database identifiers as bytes because metadata and tenant collations can differ. **/
		$sql = "SELECT s.SCHEMA_NAME AS database_name,
		               SUBSTRING(s.SCHEMA_NAME, {$prefixStart}) AS database_number,
		               CAST(SUBSTRING(s.SCHEMA_NAME, {$prefixStart}) AS UNSIGNED) AS database_order,
		               t.id AS tenant_id,
		               (SELECT p.name
		                  FROM {$managerDatabase}.tenant_users AS tu
		                  INNER JOIN {$managerDatabase}.users AS u ON u.id = tu.user_id
		                  INNER JOIN {$managerDatabase}.people AS p ON p.id = u.person_id
		                  WHERE tu.tenant_id = t.id
		                  ORDER BY tu.role = 'owner' DESC,
		                           tu.created ASC,
		                           tu.user_id ASC
		                  LIMIT 1) AS tenant_name,
		               (SELECT p.fantasy
		                  FROM {$managerDatabase}.tenant_users AS tu
		                  INNER JOIN {$managerDatabase}.users AS u ON u.id = tu.user_id
		                  INNER JOIN {$managerDatabase}.people AS p ON p.id = u.person_id
		                  WHERE tu.tenant_id = t.id
		                  ORDER BY tu.role = 'owner' DESC,
		                           tu.created ASC,
		                           tu.user_id ASC
		                  LIMIT 1) AS tenant_fantasy,
		               t.status AS tenant_status
		        FROM information_schema.SCHEMATA AS s
		        LEFT JOIN {$managerDatabase}.tenants AS t
		          ON CAST(t.database_number AS BINARY) = CAST(SUBSTRING(s.SCHEMA_NAME, {$prefixStart}) AS BINARY)
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

	/**
	 * Summarize the tenant catalog counts used by the management screen.
	 * @param array $catalog Tenant catalog returned by the service.
	 * @return array Aggregated counters for the informed catalog.
	 **/
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

	/**
	 * Resolve the tenant databases targeted by the selected execution scope.
	 * @param string $scope Execution scope selected by the caller.
	 * @param string|null $databaseStart Initial database number for range execution.
	 * @param string|null $databaseEnd Final database number for range execution.
	 * @return array Catalog rows that match the selected scope.
	 **/
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

	/**
	 * Normalize a database number into the project four-digit format.
	 * @param string|null $value Raw database number informed by the caller.
	 * @return string|null Normalized database number or null when invalid.
	 **/
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

	/**
	 * Validate the SQL script before it is executed against tenant databases.
	 * @param string $sql SQL script informed by the caller.
	 * @return string|null Validation error message or null when the script is valid.
	 **/
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

	/**
	 * Build a compact preview of the SQL script for confirmations and logs.
	 * @param string $sql SQL script informed by the caller.
	 * @return string Short single-line preview of the SQL script.
	 **/
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

	/**
	 * Execute a SQL script against the selected tenant database.
	 * @param string $databaseName Database name that will receive the execution.
	 * @param string $sql SQL script to be executed.
	 * @return array Execution result payload with result sets and diagnostics.
	 **/
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
	 * Fetch a single SQL result set while limiting rows sent back to the browser.
	 * @param \PDOStatement $stmt Executed statement positioned on a rowset.
	 * @param int $statementIndex Statement index inside the submitted SQL script.
	 * @return array Result set payload prepared for the browser response.
	 **/
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
	 * Normalize database values so they can be streamed as JSON safely.
	 * @param array $row Database result row.
	 * @return array Normalized database result row.
	 **/
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
	 * Build the execution message shown in the real-time stream.
	 * @param array $resultSets SELECT-like result sets.
	 * @param int $affectedRows Number of affected rows for write statements.
	 * @return string Message summarizing the script execution.
	 **/
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

	/**
	 * Create the administrative connection used to inspect tenant databases.
	 * @return PDO Administrative database connection.
	 **/
	private function getAdminConnection(): PDO
		{
		return Database::getServerConnection();
		}

	/**
	 * Create a connection for the informed tenant database.
	 * @param string $databaseName Database name that should be connected.
	 * @return PDO Tenant database connection.
	 **/
	private function getDatabaseConnection(string $databaseName): PDO
		{
		return Database::getTenantConnection($databaseName, true);
		}

	/**
	 * Quote a MySQL identifier used in administrative queries.
	 * @param string $identifier Identifier to quote.
	 * @return string Quoted identifier.
	 **/
	private function quoteIdentifier(string $identifier): string
		{
		return '`' . str_replace('`', '``', $identifier) . '`';
		}
	}

?>
