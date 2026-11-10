<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/SupportChat/SupportChatRepository.php';

use App\SupportChat\SupportChatRepository;

/**
 * SQLite adapter that removes the MySQL-only row lock clause for isolated tests.
 **/
final class AssignmentTestDatabase extends PDO
	{
	/**
	 * Creates an isolated in-memory database.
	 **/
	public function __construct()
		{
		parent::__construct('sqlite::memory:');
		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}

	/**
	 * Removes MySQL row locks while preserving repository query semantics.
	 * @param string $query SQL query prepared by the repository.
	 * @param array $options Driver options supplied by the repository.
	 * @return PDOStatement|false Prepared SQLite statement.
	 **/
	public function prepare(string $query, array $options = []): PDOStatement|false
		{
		$query = (string) preg_replace('/\s+FOR\s+UPDATE\b/i', '', $query);
		return parent::prepare($query, $options);
		}
	}

/**
 * Fails the test with a clear assertion message.
 * @param bool $condition Expected condition.
 * @param string $message Failure description.
 * @return void
 **/
function expectSupportAssignment(bool $condition, string $message): void
	{
	if (!$condition)
		{
		throw new RuntimeException($message);
		}
	}

/**
 * Adds one active root or tenant user with a matching person record.
 * @param PDO $database Isolated test database.
 * @param int $userId User identifier.
 * @param string $type User type.
 * @param string $name Display name.
 * @return void
 **/
function insertAssignmentUser(PDO $database, int $userId, string $type, string $name): void
	{
	$person = $database->prepare('INSERT INTO people (id, name) VALUES (:id, :name)');
	$person->execute(['id' => $userId, 'name' => $name]);
	$user = $database->prepare('INSERT INTO users (id, person_id, type, active) VALUES (:id, :person_id, :type, :active)');
	$user->execute([
		'id' => $userId,
		'person_id' => $userId,
		'type' => $type,
		'active' => 1,
	]);
	}

/**
 * Changes one test root availability status.
 * @param PDO $database Isolated test database.
 * @param int $userId Root user identifier.
 * @param string $status Requested presence status.
 * @return void
 **/
function setAssignmentPresence(PDO $database, int $userId, string $status): void
	{
	$stmt = $database->prepare('UPDATE user_presences SET status = :status WHERE user_id = :user_id');
	$stmt->execute(['status' => $status, 'user_id' => $userId]);
	}

$database = new AssignmentTestDatabase();
$database->exec('CREATE TABLE people (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
$database->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, person_id INTEGER NOT NULL, username TEXT NULL, type TEXT NOT NULL, active INTEGER NOT NULL)');
$database->exec('CREATE TABLE user_presences (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL UNIQUE, status TEXT NOT NULL)');
$database->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, observations TEXT NULL)');
$database->exec("CREATE TABLE support_chats (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	customer_user_id INTEGER NOT NULL,
	attendant_user_id INTEGER NULL,
	tenant_id INTEGER NOT NULL,
	tenant_database_number TEXT NOT NULL,
	status TEXT NOT NULL,
	last_message_at TEXT NULL,
	customer_last_seen_at TEXT NULL,
	attendant_last_seen_at TEXT NULL,
	closed_at TEXT NULL,
	closed_by_user_id INTEGER NULL,
	created TEXT NULL,
	modified TEXT NULL
)");
$database->exec('CREATE TABLE support_chats_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, support_chat_id INTEGER NOT NULL, sender_user_id INTEGER NOT NULL, sender_role TEXT NOT NULL, sender_name TEXT NOT NULL, message_text TEXT NOT NULL, created TEXT NOT NULL)');
$database->exec("INSERT INTO tenants (id, observations) VALUES (1, 'Tenant test')");

insertAssignmentUser($database, 1, 'root', 'Root A');
insertAssignmentUser($database, 2, 'root', 'Root B');
insertAssignmentUser($database, 3, 'admin', 'Customer A');
insertAssignmentUser($database, 4, 'admin', 'Customer B');
insertAssignmentUser($database, 5, 'admin', 'Customer C');
insertAssignmentUser($database, 6, 'admin', 'Customer D');
$database->exec("INSERT INTO user_presences (user_id, status) VALUES (1, 'online'), (2, 'online')");
$database->exec("INSERT INTO support_chats (customer_user_id, attendant_user_id, tenant_id, tenant_database_number, status, created, modified) VALUES (3, 1, 1, '0001', 'open', '2026-01-01 00:00:00', '2026-01-01 00:00:00')");

$repository = new SupportChatRepository($database);
$balancedChat = $repository->createCustomerChat(4, 1, '0001');
expectSupportAssignment((int) ($balancedChat['attendant_user_id'] ?? 0) === 2, 'The least-loaded online root must receive a new chat.');
expectSupportAssignment((string) ($balancedChat['status'] ?? '') === 'open', 'An online root must create an open chat.');

setAssignmentPresence($database, 1, 'offline');
setAssignmentPresence($database, 2, 'offline');
$pendingChat = $repository->createCustomerChat(5, 1, '0001');
$pendingChatId = (int) ($pendingChat['id'] ?? 0);
expectSupportAssignment($pendingChatId > 0, 'A chat must be created while no roots are online.');
expectSupportAssignment(empty($pendingChat['attendant_user_id']), 'A chat without online roots must not have an attendant.');
expectSupportAssignment((string) ($pendingChat['status'] ?? '') === 'pending', 'A chat without online roots must remain pending.');

setAssignmentPresence($database, 1, 'online');
$capturedChat = $repository->assignAvailableAttendant($pendingChatId);
expectSupportAssignment((int) ($capturedChat['attendant_user_id'] ?? 0) === 1, 'A pending chat must be assigned when a root becomes available.');

$seenStmt = $database->prepare('UPDATE support_chats SET attendant_last_seen_at = :seen_at WHERE id = :chat_id');
$seenStmt->execute(['seen_at' => '2026-01-01 10:00:00', 'chat_id' => $pendingChatId]);
$transferredChat = $repository->assignChat($pendingChatId, 2);
expectSupportAssignment((int) ($transferredChat['attendant_user_id'] ?? 0) === 2, 'A chat must allow a transfer to an active root even when offline.');
expectSupportAssignment(empty($transferredChat['attendant_last_seen_at']), 'A transfer must reset the new attendant read cursor.');

setAssignmentPresence($database, 1, 'offline');
setAssignmentPresence($database, 2, 'online');
$automaticChat = $repository->createCustomerChat(6, 1, '0001');
$automaticChatId = (int) ($automaticChat['id'] ?? 0);
expectSupportAssignment((int) ($automaticChat['attendant_user_id'] ?? 0) === 2, 'An available root must receive an automatically assigned chat.');

setAssignmentPresence($database, 1, 'online');
setAssignmentPresence($database, 2, 'offline');
$reroutedChat = $repository->assignAvailableAttendant($automaticChatId, true);
expectSupportAssignment((int) ($reroutedChat['attendant_user_id'] ?? 0) === 1, 'A chat whose attendant went offline must be rerouted to an online root.');

setAssignmentPresence($database, 1, 'offline');
$queuedAgainChat = $repository->assignAvailableAttendant($automaticChatId, true);
expectSupportAssignment(empty($queuedAgainChat['attendant_user_id']), 'A chat must be unassigned when no roots remain online.');
expectSupportAssignment((string) ($queuedAgainChat['status'] ?? '') === 'pending', 'An unavailable chat must return to the pending queue.');

echo "PASS: support assignment balances online roots, queues unavailable chats, and resets transferred read state.\n";
