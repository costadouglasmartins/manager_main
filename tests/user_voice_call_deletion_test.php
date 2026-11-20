<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli')
	{
	http_response_code(404);
	exit;
	}

require_once __DIR__ . '/../app/Core/AuditsChanges.php';
require_once __DIR__ . '/../app/Core/Model.php';
require_once __DIR__ . '/../app/Models/User.php';
require_once __DIR__ . '/../app/Models/Person.php';
require_once __DIR__ . '/../app/Services/AuditLogService.php';
require_once __DIR__ . '/../app/Services/UserDeletionService.php';

use App\Services\UserDeletionService;

/**
 * Run the production SQL against an isolated SQLite database with central schema aliases.
 **/
class UserVoiceDeletionDatabase extends Pdo\Sqlite
	{
	public bool $failPersonDeletion = false;

	/**
	 * Create an isolated database and the minimal metadata required by the service.
	 **/
	public function __construct()
		{
		parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
		$this->createFunction('DATABASE', static fn (): string => 'manager_main');
		$this->createFunction('REGEXP', static fn (string $pattern, string $value): int => preg_match('/' . $pattern . '/', $value));
		$this->exec('PRAGMA foreign_keys = ON');
		$this->exec("ATTACH DATABASE ':memory:' AS information_schema");
		$this->exec('CREATE TABLE information_schema.TABLES (TABLE_SCHEMA, TABLE_NAME, TABLE_TYPE)');
		$this->exec('CREATE TABLE information_schema.SCHEMATA (SCHEMA_NAME)');
		$this->exec("INSERT INTO information_schema.TABLES VALUES ('manager_main', 'voice_calls', 'BASE TABLE')");
		}

	/**
	 * Translate central schema and MySQL update syntax, optionally injecting a late write failure.
	 * @param string $query Production SQL statement.
	 * @param array $options PDO statement options.
	 * @return PDOStatement|false Prepared statement.
	 **/
	public function prepare(string $query, array $options = []): PDOStatement|false
		{
		if ($this->failPersonDeletion && str_starts_with($query, 'DELETE FROM people'))
			{
			throw new RuntimeException('Injected person deletion failure.');
			}
		return parent::prepare(str_replace(
			['`manager_main`.', 'SET ChatRow.last_message_at'],
			['', 'SET last_message_at'],
			$query
		), $options);
		}
	}

/**
 * Assert a focused deletion invariant.
 * @param bool $condition Expected outcome.
 * @param string $message Failure description.
 **/
function expectUserVoice(bool $condition, string $message): void
	{
	if (!$condition)
		{
		throw new RuntimeException('FAIL: ' . $message);
		}
	}

/**
 * Create a fixture with calls across users, tenants and support chats.
 * @return UserVoiceDeletionDatabase Isolated database connection.
 **/
function userVoiceFixture(): UserVoiceDeletionDatabase
	{
	// Tables outside voice calls remain minimal because their rows are unrelated to this regression.
	$schemas = [
		'users' => 'id INTEGER PRIMARY KEY, person_id INTEGER, tenant_id INTEGER, username, type, active',
		'people' => 'id INTEGER PRIMARY KEY, name, document',
		'emails' => 'id, person_id INTEGER, email, is_login',
		'addresses' => 'id, person_id INTEGER, street, number',
		'phone_numbers' => 'id, person_id INTEGER, phone_number',
		'internal_chats' => 'id, created_by_user_id INTEGER, chat_type, title, conversation_key, last_message_at',
		'internal_chat_messages' => 'id, internal_chat_id INTEGER, sender_user_id INTEGER, sender_name, created',
		'internal_chat_participants' => 'internal_chat_id INTEGER, user_id INTEGER',
		'support_chats' => 'id INTEGER PRIMARY KEY, customer_user_id INTEGER, attendant_user_id INTEGER, closed_by_user_id INTEGER, tenant_id INTEGER, status, last_message_at',
		'support_chats_messages' => 'id, support_chat_id INTEGER, sender_user_id INTEGER, sender_name, created',
		'financial_operations' => 'id, user_id INTEGER, type, total_value',
		'financial_operations_payments' => 'id, financial_operation_id INTEGER, value',
		'user_permissions' => 'user_id INTEGER, permission_id INTEGER',
		'permissions' => 'id, system, ability',
		'user_remember_tokens' => 'id, user_id INTEGER, expires_at',
		'tenant_users' => 'tenant_id INTEGER, user_id INTEGER, role',
		'tenants' => 'id, database_number',
		'user_presences' => 'id, user_id INTEGER, status',
		'tenant_people' => 'id, person_id INTEGER, tenant_id INTEGER, crm_status_id, active',
		'logs' => 'id INTEGER PRIMARY KEY, event_uuid, tenant_id, tenant_database, tenant_name_snapshot, module, action, entity_name, entity_id, performed_by_user_id, performed_by_username, performed_by_name, request_method, route, ip_address, user_agent, occurred_at, before_data, after_data, changed_fields, context_json, created, modified',
		'voice_calls' => 'id INTEGER PRIMARY KEY, kind, tenant_id, support_chat_id INTEGER REFERENCES support_chats(id) ON DELETE RESTRICT, caller_user_id INTEGER REFERENCES users(id) ON DELETE RESTRICT, callee_user_id INTEGER REFERENCES users(id) ON DELETE RESTRICT, status, started_at',
	];
	// No fixture file or application database is opened.
	$db = new UserVoiceDeletionDatabase();
	foreach ($schemas as $table => $columns)
		{
		$db->exec('CREATE TABLE ' . $table . ' (' . $columns . ')');
		}
	$db->exec("INSERT INTO people VALUES (3, 'Protected', ''), (70, 'Deleted user', ''), (80, 'Other user', ''), (90, 'Another user', '')");
	$db->exec("INSERT INTO users VALUES (1, 3, 1, 'protected', 'root', 1), (7, 70, 7, 'removed', 'user', 1), (8, 80, 8, 'other', 'user', 1), (9, 90, 8, 'another', 'user', 1)");
	$db->exec("INSERT INTO support_chats VALUES (10, 7, 1, NULL, 7, 'closed', NULL), (20, 8, 7, NULL, 8, 'closed', NULL)");
	$db->exec("INSERT INTO voice_calls VALUES
		(101, 'internal', 7, NULL, 7, 1, 'completed', '2026-11-19 12:00:00'),
		(102, 'support', 8, 20, 8, 7, 'completed', '2026-11-19 12:01:00'),
		(103, 'support', 7, 10, 8, 9, 'completed', '2026-11-19 12:02:00'),
		(104, 'internal', 8, 20, 8, 9, 'completed', '2026-11-19 12:03:00'),
		(105, 'internal', 8, NULL, 7, 7, 'completed', '2026-11-19 12:04:00')");
	return $db;
	}

/**
 * Extract planned voice-call identifiers from the public manifest.
 * @param array $impact Public deletion impact.
 * @return array<int, int> Planned call identifiers.
 **/
function userVoiceManifestIds(array $impact): array
	{
	// The public manifest is the source of truth for the user's confirmation.
	$ids = [];
	foreach ($impact['modules'] as $module)
		{
		foreach ($module['records'] as $record)
			{
			if ($record['table'] === 'voice_calls')
				{
				$ids[] = (int) $record['id'];
				}
			}
		}
	return $ids;
	}

// The caller, callee, deleted-chat association and duplicate participant case are all covered.
$db = userVoiceFixture();
// Public methods exercise plan building, optional migration detection and real SQL predicates.
$service = new UserDeletionService($db);
expectUserVoice(userVoiceManifestIds($service->impact(7)) === [101, 102, 103, 105], 'The manifest must include exactly the affected voice calls once.');
expectUserVoice($db->query('SELECT COUNT(*) FROM voice_calls')->fetchColumn() === 5, 'Impact must be read-only.');
$service->delete(7);
expectUserVoice($db->query('SELECT id FROM voice_calls ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) === [104], 'Only manifested voice calls may be removed.');
expectUserVoice($db->query('SELECT id FROM users ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) === [1, 8, 9], 'Other users, including protected participants, must remain.');
expectUserVoice($db->query('SELECT id FROM support_chats ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) === [20], 'Other support chats must remain.');

// A late failure must roll back the call removals along with the user aggregate.
$db = userVoiceFixture();
$db->failPersonDeletion = true;
$service = new UserDeletionService($db);
try
	{
	$service->delete(7);
	throw new RuntimeException('FAIL: A late failure was not propagated.');
	}
catch (RuntimeException $error)
	{
	expectUserVoice($error->getMessage() === 'Injected person deletion failure.', 'The injected failure must be rethrown.');
	}
expectUserVoice(!$db->inTransaction(), 'The failed deletion must finish its owned transaction.');
expectUserVoice($db->query('SELECT id FROM voice_calls ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) === [101, 102, 103, 104, 105], 'Rollback must restore all affected calls.');
expectUserVoice($db->query('SELECT COUNT(*) FROM users WHERE id = 7')->fetchColumn() === 1, 'Rollback must restore the user.');
expectUserVoice($db->query('SELECT COUNT(*) FROM support_chats WHERE id = 10')->fetchColumn() === 1, 'Rollback must restore the support chat.');

// Existing user/person protections must stop the operation before any call is removed.
$db = userVoiceFixture();
$service = new UserDeletionService($db);
try
	{
	$service->delete(1);
	throw new RuntimeException('FAIL: A protected user was accepted.');
	}
catch (RuntimeException $error)
	{
	expectUserVoice(str_contains($error->getMessage(), 'protegido'), 'Protected users must retain the existing block.');
	}
$db->exec("INSERT INTO users VALUES (77, 70, 8, 'shared-person', 'user', 1)");
try
	{
	$service->delete(7);
	throw new RuntimeException('FAIL: A shared person was accepted.');
	}
catch (RuntimeException $error)
	{
	expectUserVoice(str_contains($error->getMessage(), 'outro usuário'), 'Shared people must retain the existing block.');
	}
expectUserVoice($db->query('SELECT COUNT(*) FROM voice_calls')->fetchColumn() === 5, 'Blocked deletion must preserve every call.');

// Older installations without the optional migration must keep their deletion flow working.
$db = userVoiceFixture();
$db->exec('DROP TABLE voice_calls');
$db->exec('DELETE FROM information_schema.TABLES');
$service = new UserDeletionService($db);
expectUserVoice(userVoiceManifestIds($service->impact(7)) === [], 'An absent voice table must produce no voice records.');
$service->delete(7);
expectUserVoice($db->query('SELECT COUNT(*) FROM users WHERE id = 7')->fetchColumn() === 0, 'Deletion must support installations without voice calls.');

echo 'OK: User voice-call manifest, removal, isolation, protections and rollback.' . PHP_EOL;
