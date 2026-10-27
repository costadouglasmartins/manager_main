<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/SupportChat/SupportChatServer.php';
require_once __DIR__ . '/../app/SupportChat/SupportChatToken.php';
require_once __DIR__ . '/../app/SupportChat/SupportChatRepository.php';

use App\SupportChat\SupportChatServer;

class FailingChatDatabase extends PDO
	{
	public ?Throwable $failure = null;
	public int $attempts = 0;

	/**
	 * Avoid opening a real database connection in this regression test.
	 **/
	public function __construct()
		{
		}

	/**
	 * Raise the configured database failure during the real authorization path.
	 * @param string $query SQL prepared by the chat repository.
	 * @param array $options Driver options supplied by the caller.
	 * @return PDOStatement|false
	 **/
	public function prepare(string $query, array $options = []): PDOStatement|false
		{
		$this->attempts++;
		throw $this->failure ?? new RuntimeException('Expected a configured failure.');
		}
	}

/**
 * Require a regression expectation without relying on PHP assertion settings.
 * @param bool $condition Expected outcome.
 * @param string $message Failure description.
 **/
function expectChatResult(bool $condition, string $message): void
	{
	if (!$condition)
		{
		throw new RuntimeException($message);
		}
	}

// One fake connection also respects the repository's process-wide instance cache.
$database = new FailingChatDatabase();
// No configuration, secrets, database connections, or listening sockets are loaded.
$server = new SupportChatServer('127.0.0.1', 0, 'regression-test-secret', $database);
// Capture logs without printing expected SQL failures to the terminal.
$log = fopen('php://temp', 'w+');
(new ReflectionProperty($server, 'stdout'))->setValue($server, $log);
// Inspect client authorization and disconnect behavior through the existing server.
$clientsProperty = new ReflectionProperty($server, 'clients');
// Invoke the real token authentication and operation failure boundaries.
$authenticate = new ReflectionMethod($server, 'authenticateClient');
// Operation failures must use the same restart policy, including nested exceptions.
$operationFailure = new ReflectionMethod($server, 'handleOperationFailure');
// Build a valid signed token without loading an application bootstrap.
$payload = rtrim(strtr(base64_encode(json_encode([
	'role' => 'root', 'user_id' => 1, 'exp' => time() + 300,
])), '+/', '-_'), '=');
// Match the HMAC contract used by the HTTP application.
$signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, 'regression-test-secret', true)), '+/', '-_'), '=');

foreach ([2006, 2013, 2055, 4031, 1146, 1062] as $driverCode)
	{
	// Driver error information reproduces PDO's MySQL failure format.
	$failure = new PDOException('Simulated database failure.');
	$failure->errorInfo = ['HY000', $driverCode, 'Simulated database failure.'];
	$database->failure = $failure;
	$database->attempts = 0;
	// A memory stream captures frames without opening a network socket.
	$socket = fopen('php://temp', 'w+');
	$clientsProperty->setValue($server, [1 => ['socket' => $socket, 'authenticated' => false]]);
	// Only connection failures should escape and terminate the daemon.
	$thrown = null;
	try
		{
		$authenticate->invoke($server, 1, $payload . '.' . $signature);
		}
	catch (Throwable $exception)
		{
		$thrown = $exception;
		}
	expectChatResult($database->attempts === 1, 'Authorization must not retry database queries.');
	if (in_array($driverCode, [2006, 2013, 2055, 4031], true))
		{
		expectChatResult($thrown === $failure, 'Connection loss must escape authentication.');
		expectChatResult(!$clientsProperty->getValue($server)[1]['authenticated'], 'Failed database checks must never authorize clients.');
		fclose($socket);
		}
	else
		{
		expectChatResult($thrown === null, 'Ordinary SQL failures must not stop the daemon.');
		expectChatResult($clientsProperty->getValue($server) === [], 'Authorization failures must disconnect the client.');
		}
	}

foreach ([['HY000', 2006, true], ['08006', 0, true], ['23000', 1062, false]] as [$sqlState, $driverCode, $mustStop])
	{
	// Include wrapped exceptions because operation handlers may add context.
	$failure = new PDOException('Simulated operation failure.');
	$failure->errorInfo = [$sqlState, $driverCode, 'Simulated operation failure.'];
	// Preserve the original exception chain when requesting a supervisor restart.
	$wrapped = new RuntimeException('Operation failed.', 0, $failure);
	$socket = fopen('php://temp', 'w+');
	$clientsProperty->setValue($server, [1 => ['socket' => $socket, 'authenticated' => true]]);
	$thrown = null;
	try
		{
		$operationFailure->invoke($server, 1, $wrapped);
		}
	catch (Throwable $exception)
		{
		$thrown = $exception;
		}
	expectChatResult($mustStop ? $thrown === $wrapped : $thrown === null, 'Operation restart policy mismatch.');
	if (!$mustStop)
		{
		rewind($socket);
		expectChatResult(str_contains(stream_get_contents($socket), 'Unable to process'), 'Ordinary operation failures must return the protocol error.');
		}
	fclose($socket);
	}

echo "PASS: database disconnects escape for supervisor restart; SQL errors remain contained; authorization fails closed; no query retries.\n";
