<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/SupportChat/SupportChatServer.php';
require_once __DIR__ . '/../app/SupportChat/SupportChatRepository.php';

use App\SupportChat\SupportChatServer;

class AuthorizedBroadcastStatement extends PDOStatement
	{
	/**
	 * Build a statement without a real database connection.
	 **/
	public function __construct()
		{
		}

	/**
	 * Accept the authorization query parameters.
	 * @param array<string, mixed>|null $params Bound query parameters.
	 **/
	public function execute(?array $params = null): bool
		{
		return true;
		}

	/**
	 * Authorize every synthetic account used by this isolated regression test.
	 **/
	public function fetchColumn(int $column = 0): mixed
		{
		return 1;
		}
	}

class AuthorizedBroadcastDatabase extends PDO
	{
	/**
	 * Avoid opening a real database connection in this regression test.
	 **/
	public function __construct()
		{
		}

	/**
	 * Return an authorization statement for the server revalidation path.
	 * @param string $query SQL prepared by the chat repository.
	 * @param array $options Driver options supplied by the caller.
	 **/
	public function prepare(string $query, array $options = []): PDOStatement|false
		{
		return new AuthorizedBroadcastStatement();
		}
	}

/**
 * Require a regression expectation without relying on PHP assertion settings.
 * @param bool $condition Expected outcome.
 * @param string $message Failure description.
 **/
function expectBroadcastIsolation(bool $condition, string $message): void
	{
	if (!$condition)
		{
		throw new RuntimeException($message);
		}
	}

/**
 * Return the bytes written to one synthetic client socket.
 * @param resource $socket Client stream.
 **/
function broadcastSocketContents($socket): string
	{
	rewind($socket);
	return stream_get_contents($socket);
	}

$database = new AuthorizedBroadcastDatabase();
$server = new SupportChatServer('127.0.0.1', 0, 'regression-test-secret', $database);
$clientsProperty = new ReflectionProperty($server, 'clients');
$broadcastToChat = new ReflectionMethod($server, 'broadcastToChat');
$expiresAt = time() + 300;
$internalSocket = fopen('php://temp', 'w+');
$customerSocket = fopen('php://temp', 'w+');
$otherCustomerSocket = fopen('php://temp', 'w+');
$rootSocket = fopen('php://temp', 'w+');

$clientsProperty->setValue($server, [
	1 => [
		'socket' => $internalSocket,
		'authenticated' => true,
		'claims' => [
			'role' => 'internal',
			'user_id' => 8,
			'tenant_id' => 2,
			'tenant_database_number' => '0002',
			'exp' => $expiresAt,
		],
	],
	2 => [
		'socket' => $customerSocket,
		'authenticated' => true,
		'claims' => [
			'role' => 'customer',
			'user_id' => 5,
			'tenant_id' => 2,
			'tenant_database_number' => '0002',
			'exp' => $expiresAt,
		],
	],
	3 => [
		'socket' => $otherCustomerSocket,
		'authenticated' => true,
		'claims' => [
			'role' => 'customer',
			'user_id' => 6,
			'tenant_id' => 2,
			'tenant_database_number' => '0002',
			'exp' => $expiresAt,
		],
	],
	4 => [
		'socket' => $rootSocket,
		'authenticated' => true,
		'claims' => [
			'role' => 'root',
			'user_id' => 1,
			'exp' => $expiresAt,
		],
	],
]);

$broadcastToChat->invoke($server, [
	'id' => 10,
	'customer_user_id' => 5,
	'tenant_id' => 2,
], [
	'type' => 'message_created',
	'chat_id' => 10,
], null);

expectBroadcastIsolation(
	broadcastSocketContents($internalSocket) === '',
	'Internal tenant chat sockets must not receive support chat payloads.'
);
expectBroadcastIsolation(
	str_contains(broadcastSocketContents($customerSocket), 'message_created'),
	'The support chat customer must receive its own chat payload.'
);
expectBroadcastIsolation(
	broadcastSocketContents($otherCustomerSocket) === '',
	'Other support chat customers must not receive the payload.'
);
expectBroadcastIsolation(
	broadcastSocketContents($rootSocket) === '',
	'Root sockets must remain exclusive to broadcastToRoots.'
);

foreach ([$internalSocket, $customerSocket, $otherCustomerSocket, $rootSocket] as $socket)
	{
	fclose($socket);
	}

echo "PASS: support broadcasts remain isolated from internal tenant chat sockets and unrelated customers.\n";
