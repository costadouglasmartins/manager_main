<?php

namespace App\Services;

use App\Core\Database;
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
		'CRM' => [
			'crm_statuses' => ['label' => 'Situação CRM', 'fields' => ['name' => 'nome', 'slug' => 'slug']],
			'crm_activity_statuses' => ['label' => 'Situação de atividade CRM', 'fields' => ['name' => 'nome', 'slug' => 'slug']],
			'crm_activity_types' => ['label' => 'Tipo de atividade CRM', 'fields' => ['name' => 'nome', 'slug' => 'slug']],
			'crm_inactivity_reasons' => ['label' => 'Motivo de inatividade CRM', 'fields' => ['name' => 'nome', 'slug' => 'slug']],
			'crm_loss_reasons' => ['label' => 'Motivo de perda CRM', 'fields' => ['name' => 'nome', 'slug' => 'slug']],
			'crm_opportunity_types' => ['label' => 'Tipo de oportunidade CRM', 'fields' => ['name' => 'nome', 'slug' => 'slug']],
			'crm_pipeline_stages' => ['label' => 'Etapa do funil CRM', 'fields' => ['name' => 'nome', 'slug' => 'slug']],
			'crm_sources' => ['label' => 'Origem CRM', 'fields' => ['name' => 'nome', 'slug' => 'slug']],
			'crm_opportunities' => ['label' => 'Oportunidade CRM', 'fields' => ['tenant_person_id' => 'vínculo CRM', 'title' => 'título', 'estimated_value' => 'valor estimado']],
			'crm_activities' => ['label' => 'Atividade CRM', 'fields' => ['tenant_person_id' => 'vínculo CRM', 'title' => 'título', 'scheduled_at' => 'agendamento']],
		],
		'Produtos e estoque' => [
			'product_units' => ['label' => 'Unidade de produto', 'fields' => ['code' => 'código', 'name' => 'nome', 'symbol' => 'símbolo']],
			'product_attributes' => ['label' => 'Atributo de produto', 'fields' => ['code' => 'código', 'name' => 'nome', 'data_type' => 'tipo']],
			'product_attribute_options' => ['label' => 'Opção de atributo', 'fields' => ['product_attribute_id' => 'atributo', 'value' => 'valor', 'label' => 'rótulo']],
			'product_categories' => ['label' => 'Categoria de produto', 'fields' => ['name' => 'nome']],
			'product_category_attributes' => ['label' => 'Atributo da categoria', 'fields' => ['product_category_id' => 'categoria', 'product_attribute_id' => 'atributo', 'product_unit_id' => 'unidade']],
			'product_category_attribute_conditions' => ['label' => 'Condição de atributo', 'fields' => ['product_category_id' => 'categoria', 'target_product_attribute_id' => 'atributo alvo', 'action' => 'ação']],
			'product_category_templates' => ['label' => 'Modelo de categoria', 'fields' => ['name' => 'nome', 'active' => 'ativo']],
			'product_import_batches' => ['label' => 'Importação de produtos', 'fields' => ['file_name' => 'arquivo', 'status' => 'status', 'total_rows' => 'linhas']],
			'product_import_errors' => ['label' => 'Erro de importação', 'fields' => ['product_import_batch_id' => 'importação', 'row_number' => 'linha', 'message' => 'mensagem']],
			'products' => ['label' => 'Produto', 'fields' => ['name' => 'nome', 'sku' => 'SKU']],
			'product_images' => ['label' => 'Imagem de produto', 'fields' => ['product_id' => 'produto', 'original_name' => 'arquivo']],
			'product_stocks_movements' => ['label' => 'Movimento de estoque', 'fields' => ['product_id' => 'produto', 'movement_type' => 'tipo', 'quantity' => 'quantidade']],
			'product_items' => ['label' => 'Item de produto', 'fields' => ['name' => 'nome', 'sku' => 'SKU', 'barcode' => 'código de barras']],
			'product_item_variations' => ['label' => 'Variação de item', 'fields' => ['product_item_id' => 'item', 'sku' => 'SKU', 'name' => 'nome']],
			'product_item_attribute_values' => ['label' => 'Valor de atributo do item', 'fields' => ['product_item_id' => 'item', 'product_attribute_id' => 'atributo', 'value_text' => 'valor']],
			'product_item_variation_attribute_values' => ['label' => 'Valor de atributo da variação', 'fields' => ['product_item_variation_id' => 'variação', 'product_attribute_id' => 'atributo', 'value_text' => 'valor']],
			'product_item_images' => ['label' => 'Imagem do item', 'fields' => ['product_item_id' => 'item', 'product_item_variation_id' => 'variação', 'original_name' => 'arquivo']],
			'product_item_price_rules' => ['label' => 'Regra de preço do item', 'fields' => ['product_item_id' => 'item', 'product_item_variation_id' => 'variação', 'sale_price' => 'preço']],
			'product_stock_movements' => ['label' => 'Movimento de estoque do item', 'fields' => ['product_item_id' => 'item', 'product_item_variation_id' => 'variação', 'movement_type' => 'tipo', 'quantity' => 'quantidade']],
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

	private $dbPrefix   = 'manager_';

	/**
	 * Provision a tenant database from the template schema file.
	 * @param string $databaseNumber Formatted database number such as 0001.
	 * @return bool True when the tenant database is provisioned successfully.
	 **/
	public function provision(string $databaseNumber): bool
		{
		$dbName = $this->databaseName($databaseNumber);

		try
			{
			$serverConnection = Database::getServerConnection();
			$serverConnection->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
			$pdo = Database::getTenantConnection($dbName, true);

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

		try
			{
			$pdo = Database::getServerConnection();

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
			'uncatalogued_tables' => [],
			'modules' => [],
		];

		try
			{
			$pdo = Database::getServerConnection();
			$schemaStmt = $pdo->prepare("SELECT 1
			                            FROM information_schema.SCHEMATA
			                            WHERE SCHEMA_NAME = :database");
			$schemaStmt->execute(['database' => $dbName]);
			if ($schemaStmt->fetchColumn() === false)
				{
				return $impact;
				}

			$impact['exists'] = true;
			$stmt = $pdo->prepare("SELECT TABLE_NAME
			                       FROM information_schema.TABLES
			                       WHERE TABLE_SCHEMA = :database
			                         AND TABLE_TYPE = 'BASE TABLE'
			                       ORDER BY TABLE_NAME");
			$stmt->execute(['database' => $dbName]);
			$tables = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);

			if ($tables === [])
				{
				return $impact;
				}

			$impact['table_count'] = count($tables);
			$columnStmt = $pdo->prepare("SELECT TABLE_NAME, COLUMN_NAME
			                             FROM information_schema.COLUMNS
			                             WHERE TABLE_SCHEMA = :database
			                             ORDER BY TABLE_NAME, ORDINAL_POSITION");
			$columnStmt->execute(['database' => $dbName]);
			$tableColumns = [];
			foreach ($columnStmt->fetchAll(PDO::FETCH_ASSOC) as $column)
				{
				$tableColumns[(string) $column['TABLE_NAME']][(string) $column['COLUMN_NAME']] = true;
				}

			// Existing tables registered in the readable module catalog.
			$catalogedTables = [];
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
					$catalogedTables[$table] = true;

					$availableColumns = $tableColumns[$table] ?? [];
					if (!isset($availableColumns['id']))
						{
						$moduleRecords = array_merge(
							$moduleRecords,
							$this->genericDatabaseRecords($pdo, $dbName, $table, $specification['label'])
						);
						continue;
						}

					$columns = ['id'];
					foreach (array_keys($specification['fields']) as $field)
						{
						if (isset($availableColumns[$field]))
							{
							$columns[] = $field;
							}
						}
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

			// Future or legacy tables must never disappear from the confirmation manifest.
			$uncataloguedTables = array_values(array_diff(array_keys($tables), array_keys($catalogedTables)));
			if ($uncataloguedTables !== [])
				{
				$impact['uncatalogued_tables'] = $uncataloguedTables;
				$uncataloguedModule = $this->uncataloguedDatabaseModule($pdo, $dbName, $uncataloguedTables);
				$impact['modules'][] = $uncataloguedModule;
				$impact['record_count'] += (int) $uncataloguedModule['count'];
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
			$pdo = Database::getServerConnection();
			$pdo->exec('DROP DATABASE IF EXISTS ' . $this->quoteIdentifier($dbName));
			}
		catch (\Throwable $e)
			{
			throw new \RuntimeException('Não foi possível excluir a base de dados do tenant.', 0, $e);
			}
		}

	/**
	 * Build a generic manifest for tenant tables not yet registered in the readable catalog.
	 *
	 * @param PDO $pdo Server-level database connection.
	 * @param string $databaseName Validated tenant database name.
	 * @param array<int, string> $tableNames Uncatalogued table names.
	 * @return array<string, mixed>
	 */
	private function uncataloguedDatabaseModule(PDO $pdo, string $databaseName, array $tableNames): array
		{
		// Generic records expose only synthetic identifiers and never unknown row contents or keys.
		$records = [];
		foreach ($tableNames as $tableName)
			{
			$records = array_merge(
				$records,
				$this->genericDatabaseRecords($pdo, $databaseName, $tableName, 'Registro não catalogado')
			);
			}

		return [
			'scope' => $databaseName,
			'label' => 'Outras tabelas da base',
			'records' => $records,
			'count' => count($records),
			'tables' => $tableNames,
		];
		}

	/**
	 * Build safe synthetic rows when a table has no readable catalog identifier.
	 *
	 * @param PDO $pdo Server-level database connection.
	 * @param string $databaseName Validated tenant database name.
	 * @param string $tableName Table resolved from metadata or the explicit catalog.
	 * @param string $recordLabel Safe record label.
	 * @return array<int, array<string, string>>
	 */
	private function genericDatabaseRecords(
		PDO $pdo,
		string $databaseName,
		string $tableName,
		string $recordLabel
	): array
		{
		$countSql = 'SELECT COUNT(*) FROM '
			. $this->quoteIdentifier($databaseName) . '.' . $this->quoteIdentifier($tableName);
		$rowCount = (int) $pdo->query($countSql)->fetchColumn();
		$records = [];
		for ($rowNumber = 1; $rowNumber <= $rowCount; $rowNumber++)
			{
			$records[] = [
				'table' => $tableName,
				'id' => 'linha-' . $rowNumber,
				'label' => $recordLabel . ' de ' . $tableName . ' — linha #' . $rowNumber,
			];
			}

		return $records;
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

	}

?>
