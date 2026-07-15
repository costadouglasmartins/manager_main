<?php

namespace App\Services;

use PDO;

/**
 * TenantProvisioner - Creates tenant databases.
 *
 * Database naming: manager_0001, manager_0002, ...
 * Same user and password for all tenants.
 */
class TenantProvisioner
	{
	/**
	 * Tables in a tenant database grouped by the module shown before deletion.
	 * Field labels are deliberately concise: the confirmation modal identifies
	 * every row without exposing long comments, descriptions or file contents.
	 *
	 * @var array<string, array<string, array{label: string, fields: array<string, string>}>>
	 */
	private const DATABASE_MODULES = [
		'Controle do schema' => [
			'schema_versions' => ['label' => 'Versão do schema', 'fields' => ['version' => 'versão', 'description' => 'descrição']],
		],
		'Contas bancárias' => [
			'bank_accounts' => ['label' => 'Conta bancária', 'fields' => ['name' => 'nome', 'agency' => 'agência', 'account' => 'conta']],
		],
		'Financeiro' => [
			'financial_operations_categories' => ['label' => 'Categoria financeira', 'fields' => ['name' => 'nome']],
			'financial_operations_subcategories' => ['label' => 'Subcategoria financeira', 'fields' => ['name' => 'nome', 'financial_operation_category_id' => 'categoria']],
			'financial_operations' => ['label' => 'Operação financeira', 'fields' => ['total_amount' => 'valor total', 'financial_operation_category_id' => 'categoria']],
			'financial_operations_payments' => ['label' => 'Pagamento', 'fields' => ['financial_operation_id' => 'operação', 'value' => 'valor']],
		],
		'Arquivos e boletos bancários' => [
			'bank_files' => ['label' => 'Arquivo bancário', 'fields' => ['file_name' => 'arquivo', 'file_type' => 'tipo']],
			'bank_slips' => ['label' => 'Boleto', 'fields' => ['document_number' => 'documento', 'payer_name' => 'pagador', 'amount' => 'valor', 'status' => 'status']],
		],
		'Produtos e estoque' => [
			'product_categories' => ['label' => 'Categoria de produto', 'fields' => ['name' => 'nome']],
			'products' => ['label' => 'Produto', 'fields' => ['name' => 'nome', 'sku' => 'SKU']],
			'product_images' => ['label' => 'Imagem de produto', 'fields' => ['product_id' => 'produto', 'original_name' => 'arquivo']],
			'product_stocks_movements' => ['label' => 'Movimento de estoque', 'fields' => ['product_id' => 'produto', 'movement_type' => 'tipo', 'quantity' => 'quantidade']],
		],
		'Tarefas' => [
			'task_categories' => ['label' => 'Categoria de tarefa', 'fields' => ['name' => 'nome']],
			'tasks' => ['label' => 'Tarefa', 'fields' => ['title' => 'título', 'status' => 'status']],
			'task_assignees' => ['label' => 'Responsável por tarefa', 'fields' => ['task_id' => 'tarefa', 'person_id' => 'pessoa']],
			'task_comments' => ['label' => 'Comentário de tarefa', 'fields' => ['task_id' => 'tarefa', 'user_id' => 'usuário']],
			'task_executions' => ['label' => 'Execução de tarefa', 'fields' => ['task_assignee_id' => 'responsável', 'started_at' => 'início']],
		],
		'Agenda' => [
			'schedule_categories' => ['label' => 'Categoria da agenda', 'fields' => ['name' => 'nome']],
			'schedule_events_recurrences' => ['label' => 'Recorrência da agenda', 'fields' => ['title' => 'título', 'frequency' => 'frequência']],
			'schedule_events' => ['label' => 'Evento da agenda', 'fields' => ['title' => 'título', 'starts_at' => 'início', 'status' => 'status']],
			'schedule_events_participants' => ['label' => 'Participante de evento', 'fields' => ['schedule_event_id' => 'evento', 'person_id' => 'pessoa']],
		],
	];

	private $dbHost     = 'mysql';
	private $dbUser     = 'root';
	private $dbPassword = 'rootpassword';
	private $dbCharset  = 'utf8';
	private $dbPrefix   = 'manager_';

	/**
	 * Provision a tenant database from the template schema file.
	 * @param string $databaseNumber Formatted database number such as 0001.
	 * @return bool True when the tenant database is provisioned successfully.
	 **/
	public function provision(string $databaseNumber): bool
		{
		$dbName = $this->databaseName($databaseNumber);
		$dsn = sprintf('mysql:host=%s;charset=%s', $this->dbHost, $this->dbCharset);

		try
			{
			$pdo = $this->createConnection($dsn);

			$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb3");
			$pdo->exec("USE `{$dbName}`");

			$templateFile = ROOT_PATH . '/public/database/tenant_template.sql';
			if (!file_exists($templateFile))
				{
				throw new \RuntimeException('Template file not found: ' . $templateFile);
				}

			$sql = file_get_contents($templateFile);
			if ($sql === false)
				{
				throw new \RuntimeException('Unable to read template file: ' . $templateFile);
				}

			if (trim($sql) === '')
				{
				throw new \RuntimeException('Template file is empty: ' . $templateFile);
				}

			$pdo->exec($sql);

			return true;
			}
		catch (\Throwable $e)
			{
			error_log(sprintf('TenantProvisioner::provision error for %s: %s', $dbName, $e->getMessage()));
			return false;
			}
		}

	/**
	 * Check whether a tenant database already exists.
	 * @param string $databaseNumber Formatted database number such as 0001.
	 * @return bool True when the tenant database exists.
	 **/
	public function databaseExists(string $databaseNumber): bool
		{
		$dbName = $this->databaseName($databaseNumber);
		$dsn = sprintf('mysql:host=%s;charset=%s', $this->dbHost, $this->dbCharset);

		try
			{
			$pdo = $this->createConnection($dsn);

			$stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :dbname");
			$stmt->execute(['dbname' => $dbName]);

			return $stmt->fetchColumn() !== false;
			}
		catch (\Throwable $e)
			{
			return false;
			}
		}

	/**
	 * List every row that will disappear with the tenant database.
	 *
	 * @param string $databaseNumber Formatted database number such as 0001.
	 * @return array<string, mixed>
	 * @throws \RuntimeException When the database cannot be inspected.
	 */
	public function deletionImpact(string $databaseNumber): array
		{
		$dbName = $this->databaseName($databaseNumber);
		$impact = [
			'database' => $dbName,
			'exists' => false,
			'table_count' => 0,
			'record_count' => 0,
			'modules' => [],
		];

		try
			{
			$pdo = $this->createConnection(sprintf('mysql:host=%s;charset=%s', $this->dbHost, $this->dbCharset));
			$stmt = $pdo->prepare("SELECT TABLE_NAME
			                       FROM information_schema.TABLES
			                       WHERE TABLE_SCHEMA = :database
			                         AND TABLE_TYPE = 'BASE TABLE'");
			$stmt->execute(['database' => $dbName]);
			$tables = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);

			if ($tables === [])
				{
				return $impact;
				}

			$impact['exists'] = true;
			$impact['table_count'] = count($tables);

			foreach (self::DATABASE_MODULES as $moduleLabel => $moduleTables)
				{
				$moduleRecords = [];
				$existingModuleTables = [];

				foreach ($moduleTables as $table => $specification)
					{
					if (!isset($tables[$table]))
						{
						continue;
						}
					$existingModuleTables[] = $table;

					$columns = array_merge(['id'], array_keys($specification['fields']));
					$select = implode(', ', array_map([$this, 'quoteIdentifier'], $columns));
					$sql = 'SELECT ' . $select
						. ' FROM ' . $this->quoteIdentifier($dbName) . '.' . $this->quoteIdentifier($table)
						. ' ORDER BY ' . $this->quoteIdentifier('id');

					foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row)
						{
						$moduleRecords[] = [
							'table' => $table,
							'id' => (string) $row['id'],
							'label' => $this->databaseRecordLabel($specification, $row),
						];
						}
					}

				if ($existingModuleTables !== [])
					{
					$impact['modules'][] = [
						'scope' => $dbName,
						'label' => $moduleLabel,
						'records' => $moduleRecords,
						'count' => count($moduleRecords),
						'tables' => $existingModuleTables,
					];
					$impact['record_count'] += count($moduleRecords);
					}
				}

			return $impact;
			}
		catch (\Throwable $e)
			{
			throw new \RuntimeException('Não foi possível consultar a base de dados do tenant.', 0, $e);
			}
		}

	/**
	 * Permanently remove the tenant database. IF EXISTS makes retries safe.
	 *
	 * @param string $databaseNumber Formatted database number such as 0001.
	 * @return void
	 * @throws \RuntimeException When MySQL cannot remove the database.
	 */
	public function dropDatabase(string $databaseNumber): void
		{
		$dbName = $this->databaseName($databaseNumber);

		try
			{
			$pdo = $this->createConnection(sprintf('mysql:host=%s;charset=%s', $this->dbHost, $this->dbCharset));
			$pdo->exec('DROP DATABASE IF EXISTS ' . $this->quoteIdentifier($dbName));
			}
		catch (\Throwable $e)
			{
			throw new \RuntimeException('Não foi possível excluir a base de dados do tenant.', 0, $e);
			}
		}

	/**
	 * Format one tenant-database row for the confirmation list.
	 *
	 * @param array{label: string, fields: array<string, string>} $specification Table specification.
	 * @param array<string, mixed> $row Database row.
	 * @return string
	 */
	private function databaseRecordLabel(array $specification, array $row): string
		{
		$parts = [];
		foreach ($specification['fields'] as $field => $label)
			{
			$value = isset($row[$field]) ? trim((string) $row[$field]) : '';
			if ($value !== '')
				{
				$parts[] = $label . ': ' . $value;
				}
			}

		$recordLabel = $specification['label'] . ' #' . (string) $row['id'];
		return $parts === [] ? $recordLabel : $recordLabel . ' — ' . implode(' | ', $parts);
		}

	/**
	 * Build and validate the tenant database identifier before DDL or metadata queries.
	 *
	 * @param string $databaseNumber Tenant database suffix.
	 * @return string
	 */
	private function databaseName(string $databaseNumber): string
		{
		$databaseNumber = trim($databaseNumber);
		if (!preg_match('/^[0-9]{4}$/', $databaseNumber))
			{
			throw new \InvalidArgumentException('Número de base do tenant inválido.');
			}

		return $this->dbPrefix . $databaseNumber;
		}

	/**
	 * Quote a MySQL identifier.
	 *
	 * @param string $identifier Identifier to quote.
	 * @return string
	 */
	private function quoteIdentifier(string $identifier): string
		{
		return '`' . str_replace('`', '``', $identifier) . '`';
		}

	/**
	 * Create a PDO connection using the informed DSN.
	 * @param string $dsn Data source name used to create the connection.
	 * @return PDO Configured PDO connection instance.
	 **/
	private function createConnection(string $dsn): PDO
		{
		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		];

		if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS'))
			{
			$options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
			}

		return new PDO($dsn, $this->dbUser, $this->dbPassword, $options);
		}
	}

?>
