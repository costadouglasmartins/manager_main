<?php

declare(strict_types=1);

/**
 * Run with php tests/tenant_voice_call_deletion_test.php.
 * Executes the real deletion service against SQLite memory only. The adapter
 * translates metadata and locking syntax; no application database is opened.
 **/
namespace App\Models
	{
	class User
		{
		public const PROTECTED_USER_IDS = [1, 2];
		}

	class Person
		{
		public const PROTECTED_PERSON_IDS = [3, 4];
		}

	class Tenant
		{
		public static \PDO $database;

		/**
		 * Resolve the fixture tenant without an application connection.
		 * @param int $tenantId Requested fixture ID.
		 * @return array<string, mixed>|false
		 **/
		public function find(int $tenantId): array|false
			{
			/** Bind the requested tenant to the isolated fixture query. **/
			$statement = self::$database->prepare('SELECT id, database_number, associated_person_name FROM tenants WHERE id = :id');
			$statement->execute(['id' => $tenantId]);
			return $statement->fetch(\PDO::FETCH_ASSOC);
			}

		/**
		 * Supply only the audit snapshot needed by this test.
		 * @param int $tenantId Requested fixture ID.
		 * @return array<string, mixed>
		 **/
		public function findAllByTenantId(int $tenantId): array
			{
			return $this->find($tenantId) ?: [];
			}
		}
	}

namespace App\Services
	{
	class TenantProvisioner
		{
		public array $droppedDatabases = [];

		/**
		 * Represent an absent dedicated database; no server access is needed.
		 * @param string $databaseNumber Fixture database suffix.
		 * @return array<string, mixed>
		 **/
		public function deletionImpact(string $databaseNumber): array
			{
			return ['database' => 'manager_' . $databaseNumber, 'exists' => false,
				'table_count' => 0, 'record_count' => 0, 'uncatalogued_tables' => [], 'modules' => []];
			}

		/**
		 * Record the simulated drop without executing DDL.
		 * @param string $databaseNumber Fixture database suffix.
		 * @return void
		 **/
		public function dropDatabase(string $databaseNumber): void
			{
			$this->droppedDatabases[] = $databaseNumber;
			}
		}

	class AuditLogService
		{
		/**
		 * Accept the service connection without external logging.
		 * @param \PDO $database Isolated fixture connection.
		 **/
		public function __construct(\PDO $database)
			{
			}

		/**
		 * Leave audit persistence outside this voice-call regression fixture.
		 * @param mixed ...$arguments Audit event values.
		 * @return void
		 **/
		public function logDelete(mixed ...$arguments): void
			{
			}
		}

	class SupportChatTypingService
		{
		/**
		 * Keep the regression independent from transient storage.
		 * @param int $chatId Fixture chat ID.
		 * @return array<int, mixed>
		 **/
		public static function getTypingUsers(int $chatId): array
			{
			return [];
			}

		/**
		 * Avoid touching application typing-cache files.
		 * @param array<int, int> $chatIds Fixture chat IDs.
		 * @return void
		 **/
		public static function clearChats(array $chatIds): void
			{
			}
		}
	}

namespace
	{
	use App\Models\Tenant;
	use App\Services\TenantDeletionService;
	use App\Services\TenantProvisioner;

	if (PHP_SAPI !== 'cli')
		{
		http_response_code(404);
		exit;
		}

	require_once __DIR__ . '/../app/Services/TenantDeletionService.php';

	// The deliberately nonexistent storage root also prevents application file access.
	define('ROOT_PATH', sys_get_temp_dir() . '/tenant-voice-test-' . bin2hex(random_bytes(12)) . '/manager_main');

	class TenantVoiceFixtureDatabase extends PDO
		{
		public bool $failTenantDeletion = false;
		public array $voiceIdsBeforeFailure = [];

		/**
		 * Create an isolated database with real transaction and FK enforcement.
		 **/
		public function __construct()
			{
			parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
			$this->exec('PRAGMA foreign_keys = ON');
			}

		/**
		 * Translate only MySQL metadata and locking/assignment syntax.
		 * @param string $query Service SQL.
		 * @param array<string, mixed> $options Driver options.
		 * @return PDOStatement|false
		 **/
		public function prepare(string $query, array $options = []): PDOStatement|false
			{
			if ($this->failTenantDeletion && str_starts_with($query, 'DELETE FROM tenants'))
				{
				$this->voiceIdsBeforeFailure = $this->query('SELECT id FROM voice_calls ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
				throw new RuntimeException('Injected tenant deletion failure.');
				}
			if (str_contains($query, 'information_schema.TABLES'))
				{
				$query = "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table";
				}
			elseif (str_contains($query, 'information_schema.KEY_COLUMN_USAGE'))
				{
				/** Preserve bindings from the empty external-reference metadata fixture. **/
				preg_match_all('/:[a-z_]+/', $query, $parameters);
				$query = 'SELECT NULL AS TABLE_SCHEMA, NULL AS TABLE_NAME, NULL AS COLUMN_NAME WHERE 0';
				foreach (array_unique($parameters[0]) as $parameter)
					{
					$query .= ' AND ' . $parameter . ' IS NULL';
					}
				}
			$query = str_replace([' FOR UPDATE', 'SET UserRow.tenant_id', 'UserRow.modified ='], ['', 'SET tenant_id', 'modified ='], $query);
			return parent::prepare($query, $options);
			}

		/**
		 * Discover fixture tenant columns while leaving the service gate intact.
		 * @param string $query Service SQL.
		 * @param int|null $fetchMode Optional fetch mode.
		 * @param mixed ...$fetchModeArgs Additional fetch options.
		 * @return PDOStatement|false
		 **/
		public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
			{
			if (str_contains($query, 'AssociationInventory.association'))
				{
				$query = "SELECT TableMeta.name || '.' || ColumnMeta.name AS association
				          FROM sqlite_master AS TableMeta
				          INNER JOIN pragma_table_info(TableMeta.name) AS ColumnMeta
				          WHERE TableMeta.type = 'table' AND TableMeta.name <> 'tenants'
				            AND ColumnMeta.name IN ('tenant_id', 'tenant_database', 'tenant_database_number')
				          ORDER BY association";
				}
			elseif (str_contains($query, 'information_schema.KEY_COLUMN_USAGE'))
				{
				$query = 'SELECT NULL AS TABLE_SCHEMA, NULL AS TABLE_NAME, NULL AS COLUMN_NAME WHERE 0';
				}
			return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
			}
		}

	/**
	 * Require an observable regression expectation.
	 * @param bool $condition Expected outcome.
	 * @param string $message Failure description.
	 * @return void
	 **/
	function expectTenantVoiceResult(bool $condition, string $message): void
		{
		if (!$condition)
			{
			throw new RuntimeException($message);
			}
		}

	/**
	 * Create only the central tables queried by the real tenant deletion service.
	 * @return array{TenantVoiceFixtureDatabase, TenantDeletionService, TenantProvisioner}
	 **/
	function tenantVoiceFixture(): array
		{
		/** All application-like state lives inside this memory connection. **/
		$database = new TenantVoiceFixtureDatabase();
		$database->exec("CREATE TABLE tenants (id INTEGER PRIMARY KEY, database_number TEXT, associated_person_name TEXT);
			CREATE TABLE people (id INTEGER PRIMARY KEY, name TEXT, document TEXT);
			CREATE TABLE users (id INTEGER PRIMARY KEY, person_id INTEGER, username TEXT, type TEXT, active INTEGER, tenant_id INTEGER, modified TEXT);
			CREATE TABLE tenant_users (tenant_id INTEGER, user_id INTEGER, role TEXT);
			CREATE TABLE tenant_people (id INTEGER PRIMARY KEY, tenant_id INTEGER, person_id INTEGER, source TEXT, active INTEGER);
			CREATE TABLE internal_chats (id INTEGER PRIMARY KEY, tenant_id INTEGER, title TEXT, conversation_key TEXT);
			CREATE TABLE internal_chat_messages (id INTEGER PRIMARY KEY, internal_chat_id INTEGER, sender_name TEXT);
			CREATE TABLE internal_chat_participants (internal_chat_id INTEGER, user_id INTEGER);
			CREATE TABLE support_chats (id INTEGER PRIMARY KEY, tenant_id INTEGER, status TEXT, customer_user_id INTEGER);
			CREATE TABLE support_chats_messages (id INTEGER PRIMARY KEY, support_chat_id INTEGER, sender_name TEXT);
			CREATE TABLE logs (id INTEGER PRIMARY KEY, tenant_id INTEGER, tenant_database TEXT, module TEXT, action TEXT, entity_name TEXT, entity_id INTEGER);
			CREATE TABLE tenant_setting_values (id INTEGER PRIMARY KEY, tenant_id INTEGER, setting_definition_id INTEGER);
			CREATE TABLE system_setting_definitions (id INTEGER PRIMARY KEY, key_name TEXT);
			CREATE TABLE user_presences (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, status TEXT);
			CREATE TABLE user_permissions (user_id INTEGER, permission_id INTEGER);
			CREATE TABLE permissions (id INTEGER PRIMARY KEY, system TEXT, ability TEXT, description TEXT);
			CREATE TABLE user_remember_tokens (id INTEGER PRIMARY KEY, user_id INTEGER, expires_at TEXT);
			CREATE TABLE voice_calls (id INTEGER PRIMARY KEY, kind TEXT, tenant_id INTEGER REFERENCES tenants(id) ON DELETE RESTRICT,
				support_chat_id INTEGER REFERENCES support_chats(id) ON DELETE RESTRICT, caller_user_id INTEGER REFERENCES users(id) ON DELETE RESTRICT,
				callee_user_id INTEGER REFERENCES users(id) ON DELETE RESTRICT, status TEXT, started_at TEXT);
			INSERT INTO tenants VALUES (1, '0001', 'Protected'), (7, '0007', 'Target'), (8, '0008', 'Other');
			INSERT INTO people VALUES (3, 'Protected', NULL), (10, 'Other', NULL), (12, 'Shared', NULL), (99, 'No calls', NULL);
			INSERT INTO users VALUES (1, 3, 'root', 'root', 1, 7, NULL), (10, 10, 'other', 'user', 1, 8, NULL),
				(12, 12, 'shared', 'user', 1, 8, NULL), (99, 99, 'no_calls', 'user', 1, 8, NULL);
			INSERT INTO tenant_users VALUES (7, 1, 'owner'), (7, 12, 'user'), (8, 12, 'owner');
			INSERT INTO support_chats VALUES (701, 7, 'closed', 12), (801, 8, 'closed', 12);
			INSERT INTO user_permissions VALUES (1, 1), (12, 1);
			INSERT INTO permissions VALUES (1, 'manager', 'home.index', 'Home');
			INSERT INTO user_remember_tokens VALUES (1, 1, '2030-01-01'), (12, 12, '2030-01-01');
			INSERT INTO voice_calls VALUES
				(101, 'internal', 7, NULL, 1, 12, 'completed', '2026-11-19 10:00:00'),
				(102, 'support', 8, 701, 10, 12, 'completed', '2026-11-19 10:01:00'),
				(103, 'support', NULL, 701, 10, 12, 'completed', '2026-11-19 10:02:00'),
				(104, 'support', 8, 801, 10, 12, 'completed', '2026-11-19 10:03:00'),
				(105, 'internal', NULL, NULL, 10, 12, 'completed', '2026-11-19 10:04:00'),
				(106, 'internal', 8, NULL, 10, 12, 'completed', '2026-11-19 10:05:00'),
				(107, 'support', 7, 801, 1, 12, 'completed', '2026-11-19 10:06:00'),
				(108, 'support', NULL, 801, 1, 12, 'completed', '2026-11-19 10:07:00');");
		Tenant::$database = $database;
		/** Simulated provisioning can record intent but cannot drop any real schema. **/
		$provisioner = new TenantProvisioner();
		return [$database, new TenantDeletionService($database, $provisioner), $provisioner];
		}

	/**
	 * Return voice IDs from the public deletion manifest.
	 * @param array<string, mixed> $impact Service manifest.
	 * @return array<int, int>
	 **/
	function tenantVoiceManifestIds(array $impact): array
		{
		/** Collect identifiers only from the registered voice-call module. **/
		$ids = [];
		foreach ($impact['modules'] as $module)
			{
			foreach ($module['records'] as $record)
				{
				if ($record['table'] === 'voice_calls')
					{
					expectTenantVoiceResult($module['scope'] === 'manager_main' && $record['label'] !== '', 'Calls must identify their database and description.');
					$ids[] = (int) $record['id'];
					}
				}
			}
		return $ids;
		}

	/** Public impact and delete must select exactly the same tenant-owned calls. **/
	[$database, $service, $provisioner] = tenantVoiceFixture();
	expectTenantVoiceResult(tenantVoiceManifestIds($service->impact(7)) === [101, 102, 103, 107], 'Manifest must include direct calls and calls attached to deleted support chats exactly once.');
	$service->delete(7);
	expectTenantVoiceResult(array_map('intval', $database->query('SELECT id FROM voice_calls ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)) === [104, 105, 106, 108], 'Deletion must remove exactly the manifested calls and preserve unrelated calls.');
	expectTenantVoiceResult($database->query('SELECT COUNT(*) FROM tenants WHERE id = 7')->fetchColumn() === 0, 'Target tenant must be deleted.');
	expectTenantVoiceResult($database->query('SELECT COUNT(*) FROM support_chats WHERE id = 801')->fetchColumn() === 1, 'Other support chats must survive.');
	expectTenantVoiceResult($database->query('SELECT COUNT(*) FROM users WHERE id IN (1, 12)')->fetchColumn() === 2, 'Protected and shared users must survive.');
	expectTenantVoiceResult($database->query('SELECT COUNT(*) FROM user_permissions WHERE user_id = 1')->fetchColumn() === 1, 'Protected user permissions must survive.');
	expectTenantVoiceResult($database->query('SELECT COUNT(*) FROM user_remember_tokens WHERE user_id = 1')->fetchColumn() === 1, 'Protected user sessions must survive.');
	expectTenantVoiceResult($provisioner->droppedDatabases === ['0007'], 'Only the confirmed target database can be passed to the simulated provisioner.');

	/** A later central failure must roll back the explicit call cleanup too. **/
	[$database, $service] = tenantVoiceFixture();
	$database->failTenantDeletion = true;
	try
		{
		$service->delete(7);
		throw new RuntimeException('Expected injected deletion failure.');
		}
	catch (RuntimeException $exception)
		{
		expectTenantVoiceResult($exception->getMessage() === 'Injected tenant deletion failure.', 'Rollback must report the original failure.');
		}
	expectTenantVoiceResult(array_map('intval', $database->voiceIdsBeforeFailure) === [104, 105, 106, 108], 'Failure must occur after real call deletion inside the transaction.');
	expectTenantVoiceResult($database->query('SELECT COUNT(*) FROM voice_calls')->fetchColumn() === 8, 'Rollback must restore all central calls.');
	expectTenantVoiceResult($database->query('SELECT COUNT(*) FROM support_chats WHERE id = 701')->fetchColumn() === 1 && !$database->inTransaction(), 'Rollback must restore parent chats and close the transaction.');

	/** Calls outside the selected tenant must retain participant accounts. **/
	[$database, $service] = tenantVoiceFixture();
	/** Invoke the focused cross-tenant reference query with real relational data. **/
	$referenceMethod = new ReflectionMethod(TenantDeletionService::class, 'voiceCallUserReferenceIds');
	/** Calls involving either caller or callee must count, including null tenant calls. **/
	$referencedUsers = $referenceMethod->invoke($service, [1, 10, 12, 99], 7);
	sort($referencedUsers);
	expectTenantVoiceResult($referencedUsers === [1, 10, 12], 'Surviving calls must retain both participant accounts but not unrelated users.');
	$database->exec('DELETE FROM voice_calls WHERE id IN (104, 105, 106, 108)');
	expectTenantVoiceResult($referenceMethod->invoke($service, [1, 10, 12, 99], 7) === [], 'Calls already selected for tenant deletion must not retain otherwise exclusive participants.');

	/** The aggregate plan must use surviving voice references, not only the helper. **/
	[$database, $service] = tenantVoiceFixture();
	$database->exec('UPDATE users SET tenant_id = 7 WHERE id = 99; UPDATE voice_calls SET caller_user_id = 99 WHERE id = 105; UPDATE voice_calls SET callee_user_id = 99 WHERE id = 101');
	/** Inspect the plan without removing the exclusive candidate fixture. **/
	$planMethod = new ReflectionMethod(TenantDeletionService::class, 'buildPlan');
	/** A null-tenant call that survives must keep its otherwise exclusive caller. **/
	$plan = $planMethod->invoke($service, 7);
	expectTenantVoiceResult(in_array(99, $plan['retained_exclusive_user_ids'], true) && !in_array(99, $plan['deletable_user_ids'], true), 'Surviving voice references must retain an otherwise exclusive user in the aggregate plan.');
	$database->exec('DELETE FROM voice_calls WHERE id = 105');
	/** Only a target-owned call remains after removing the unrelated history. **/
	$plan = $planMethod->invoke($service, 7);
	expectTenantVoiceResult(in_array(99, $plan['deletable_user_ids'], true), 'A target-owned call must not unnecessarily retain an exclusive user.');

	/** Missing optional migration must remain compatible with impact and cleanup. **/
	[$database, $service] = tenantVoiceFixture();
	$database->exec('DROP TABLE voice_calls');
	expectTenantVoiceResult(tenantVoiceManifestIds($service->impact(7)) === [], 'Unmigrated installations must have no voice-call module.');
	expectTenantVoiceResult($referenceMethod->invoke($service, [1, 12], 7) === [], 'Missing voice-call table must not prevent user reference checks.');
	$service->delete(7);
	expectTenantVoiceResult($database->query('SELECT COUNT(*) FROM tenants WHERE id = 7')->fetchColumn() === 0, 'Deletion must work when the optional voice-call table is absent.');

	/** The metadata gate and protected tenant checks must remain enforced. **/
	[$database, $service, $provisioner] = tenantVoiceFixture();
	$database->exec('CREATE TABLE unregistered_records (id INTEGER PRIMARY KEY, tenant_id INTEGER)');
	foreach (['impact', 'delete'] as $operation)
		{
		try
			{
			$service->$operation(7);
			throw new RuntimeException('Expected unregistered tenant association rejection.');
			}
		catch (RuntimeException $exception)
			{
			expectTenantVoiceResult(str_contains($exception->getMessage(), 'unregistered_records.tenant_id'), 'Unknown associations must still block both public flows.');
			}
		}
	foreach (['impact', 'delete'] as $operation)
		{
		try
			{
			$service->$operation(1);
			throw new RuntimeException('Expected protected tenant rejection.');
			}
		catch (RuntimeException $exception)
			{
			expectTenantVoiceResult($exception->getMessage() === 'O tenant principal não pode ser excluído.', 'Tenant 1 must remain protected in both public flows.');
			}
		}
	expectTenantVoiceResult($provisioner->droppedDatabases === [], 'Rejected operations must not reach provisioning.');
	echo "Tenant voice-call deletion tests passed.\n";
	}
