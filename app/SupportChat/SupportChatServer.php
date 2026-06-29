<?php

namespace App\SupportChat;

use App\Services\SupportChatTypingService;
use PDO;

class SupportChatServer
	{
	private string $host;
	private int $port;
	private string $secret;
	private PDO $db;
	/** @var resource */
	private $server;
	/** @var array<int, array<string, mixed>> */
	private array $clients = [];
	private int $nextClientId = 1;
	private int $lastPingAt = 0;
	/** @var resource|null */
	private $stdout = null;

	public function __construct(string $host, int $port, string $secret, PDO $db)
		{
		$this->host = $host;
		$this->port = $port;
		$this->secret = $secret;
		$this->db = $db;
		$this->stdout = fopen('php://stdout', 'wb');
		}

	/**
	 * Starts the socket server loop.
	 */
	public function run(): void
		{
		$server = @stream_socket_server(
			sprintf('tcp://%s:%d', $this->host, $this->port),
			$errorNumber,
			$errorMessage
		);

		if ($server === false)
			{
			throw new \RuntimeException('Could not start WebSocket server: ' . $errorMessage . ' (' . $errorNumber . ').');
			}

		stream_set_blocking($server, false);
		$this->server = $server;
		$this->lastPingAt = time();

		$this->writeLog(sprintf(
			"[%s] Support chat WebSocket listening on %s:%d\n",
			date('Y-m-d H:i:s'),
			$this->host,
			$this->port
		));

		while (true)
			{
			$readSockets = [$this->server];
			foreach ($this->clients as $client)
				{
				$readSockets[] = $client['socket'];
				}

			$write = null;
			$except = null;
			$changed = @stream_select($readSockets, $write, $except, 1);

			if ($changed === false)
				{
				continue;
				}

			foreach ($readSockets as $socket)
				{
				if ($socket === $this->server)
					{
					$this->acceptClient();
					continue;
					}

				$clientId = $this->findClientIdBySocket($socket);
				if ($clientId === null)
					{
					continue;
					}

				$chunk = @fread($socket, 8192);
				if ($chunk === '' || $chunk === false)
					{
					$this->disconnectClient($clientId);
					continue;
					}

				$this->clients[$clientId]['last_activity_at'] = time();

				if (!$this->clients[$clientId]['handshake_complete'])
					{
					$this->clients[$clientId]['handshake_buffer'] .= $chunk;
					if (strpos($this->clients[$clientId]['handshake_buffer'], "\r\n\r\n") !== false)
						{
						$this->performHandshake($clientId, $this->clients[$clientId]['handshake_buffer']);
						}
					continue;
					}

				$this->clients[$clientId]['buffer'] .= $chunk;
				$this->drainFrames($clientId);
				}

			$this->sendHeartbeat();
			$this->cleanupIdleClients();
			}
		}

	/**
	 * Accepts a new TCP client.
	 */
	private function acceptClient(): void
		{
		$socket = @stream_socket_accept($this->server, 0);
		if ($socket === false)
			{
			return;
			}

		stream_set_blocking($socket, false);

		$this->clients[$this->nextClientId] = [
			'id' => $this->nextClientId,
			'socket' => $socket,
			'handshake_complete' => false,
			'handshake_buffer' => '',
			'buffer' => '',
			'authenticated' => false,
			'claims' => [],
			'last_activity_at' => time(),
		];
		$this->nextClientId++;
		}

	/**
	 * Performs the WebSocket upgrade handshake.
	 */
	private function performHandshake(int $clientId, string $request): void
		{
		if (!preg_match('/Sec-WebSocket-Key:\s*(.+)\r\n/i', $request, $matches))
			{
			$this->disconnectClient($clientId);
			return;
			}

		$key = trim($matches[1]);
		$accept = base64_encode(
			sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)
		);

		$response = "HTTP/1.1 101 Switching Protocols\r\n"
			. "Upgrade: websocket\r\n"
			. "Connection: Upgrade\r\n"
			. "Sec-WebSocket-Accept: {$accept}\r\n\r\n";

		@fwrite($this->clients[$clientId]['socket'], $response);
		$this->clients[$clientId]['handshake_complete'] = true;
		$this->clients[$clientId]['handshake_buffer'] = '';
		}

	/**
	 * Processes all complete frames already buffered for a client.
	 */
	private function drainFrames(int $clientId): void
		{
		while (true)
			{
			$frame = $this->decodeFrame($this->clients[$clientId]['buffer']);
			if ($frame === null)
				{
				return;
				}

			$this->clients[$clientId]['buffer'] = substr($this->clients[$clientId]['buffer'], $frame['frame_length']);

			if ($frame['opcode'] === 0x8)
				{
				$this->disconnectClient($clientId);
				return;
				}

			if ($frame['opcode'] === 0x9)
				{
				$this->sendFrame($clientId, $frame['payload'], 0xA);
				continue;
				}

			if ($frame['opcode'] === 0xA)
				{
				continue;
				}

			if ($frame['opcode'] !== 0x1)
				{
				continue;
				}

			$this->handleTextMessage($clientId, $frame['payload']);
			}
		}

	/**
	 * Handles a JSON message received from the browser.
	 */
	private function handleTextMessage(int $clientId, string $payload): void
		{
		$message = json_decode($payload, true);
		if (!is_array($message))
			{
			$this->sendError($clientId, 'Invalid JSON payload.');
			return;
			}

		$type = (string) ($message['type'] ?? '');
		if ($type === '')
			{
			$this->sendError($clientId, 'Message type is required.');
			return;
			}

		if ($type === 'authenticate')
			{
			$this->authenticateClient($clientId, (string) ($message['token'] ?? ''));
			return;
			}

		if (!$this->clients[$clientId]['authenticated'])
			{
			$this->sendError($clientId, 'Authenticate first.');
			return;
			}

		switch ($type)
			{
			case 'send_message':
				$this->handleSendMessage($clientId, $message);
				return;

			case 'typing':
				$this->handleTyping($clientId, $message);
				return;

			case 'assign_chat':
				$this->handleAssignChat($clientId, $message);
				return;

			case 'close_chat':
				$this->handleCloseChat($clientId, $message);
				return;
			}

		$this->sendError($clientId, 'Unsupported message type.');
		}

	/**
	 * Authenticates a socket using the signed token issued by the HTTP app.
	 */
	private function authenticateClient(int $clientId, string $token): void
		{
		$claims = SupportChatToken::verify($token, $this->secret);
		if (!$claims)
			{
			$this->sendError($clientId, 'Invalid or expired token.');
			return;
			}

		$role = (string) ($claims['role'] ?? '');
		$userId = (int) ($claims['user_id'] ?? 0);

		if ($role === 'root' && !$this->repository()->isRootUser($userId))
			{
			$this->sendError($clientId, 'User is not a root attendant.');
			return;
			}

		if ($role === 'customer' && ((int) ($claims['tenant_id'] ?? 0) <= 0))
			{
			$this->sendError($clientId, 'Tenant context is required.');
			return;
			}

		$this->clients[$clientId]['authenticated'] = true;
		$this->clients[$clientId]['claims'] = $claims;

		$this->sendJson($clientId, [
			'type' => 'auth_ok',
			'role' => $role,
			'user_id' => $userId,
		]);
		}

	/**
	 * Creates or appends a message to a chat.
	 *
	 * @param array<string, mixed> $message
	 */
	private function handleSendMessage(int $clientId, array $message): void
		{
		try
			{
			$claims = $this->clients[$clientId]['claims'];
			$role = (string) ($claims['role'] ?? '');
			$userId = (int) ($claims['user_id'] ?? 0);
			$userName = (string) ($claims['user_name'] ?? $claims['username'] ?? ('User #' . $userId));
			$text = trim((string) ($message['message'] ?? ''));

			if ($text === '')
				{
				$this->sendError($clientId, 'Message cannot be empty.');
				return;
				}

			$repository = $this->repository();
			$chatId = (int) ($message['chat_id'] ?? 0);
			$chat = null;

			if ($role === 'customer')
				{
				$tenantId = (int) ($claims['tenant_id'] ?? 0);
				$tenantDatabaseNumber = (string) ($claims['tenant_database_number'] ?? '');

				if ($chatId > 0)
					{
					$chat = $repository->findChatById($chatId);
					}

				if (!$chat || (int) ($chat['customer_user_id'] ?? 0) !== $userId || (int) ($chat['tenant_id'] ?? 0) !== $tenantId || (string) ($chat['status'] ?? '') === 'closed')
					{
					$activeChat = $repository->findActiveCustomerChat($userId, $tenantId);
					$chat = $activeChat ?: $repository->createCustomerChat($userId, $tenantId, $tenantDatabaseNumber);
					$chatId = (int) ($chat['id'] ?? 0);
					$this->broadcastChatCreated($chatId, $chat);
					}
				}
			else
				{
				$chat = $repository->findChatById($chatId);
				if (!$chat)
					{
					$this->sendError($clientId, 'Chat not found.');
					return;
					}
				}

			$messageRow = $repository->createMessage($chatId, $userId, $role === 'root' ? 'attendant' : 'customer', $userName, $text);
			$chat = $repository->findChatById($chatId) ?? $chat;
			SupportChatTypingService::setTyping($chatId, $userId, $role === 'root' ? 'attendant' : 'customer', $userName, false);

			$this->broadcastMessageCreated($chatId, $messageRow, $chat);
			}
		catch (\RuntimeException $exception)
			{
			$this->sendError($clientId, $exception->getMessage());
			}
		}

	/**
	 * Broadcasts typing activity to the opposite side of the conversation.
	 *
	 * @param array<string, mixed> $message
	 */
	private function handleTyping(int $clientId, array $message): void
		{
		$claims = $this->clients[$clientId]['claims'];
		$role = (string) ($claims['role'] ?? '');
		$chatId = (int) ($message['chat_id'] ?? 0);
		$isTyping = !empty($message['is_typing']);

		if ($chatId <= 0)
			{
			return;
			}

		$chat = $this->repository()->findChatById($chatId);
		if (!$chat)
			{
			return;
			}

		if ($role === 'customer' && ((int) ($chat['customer_user_id'] ?? 0) !== (int) ($claims['user_id'] ?? 0)))
			{
			return;
			}

		$payload = [
			'type' => 'typing',
			'chat_id' => $chatId,
			'is_typing' => $isTyping,
			'user_name' => (string) ($claims['user_name'] ?? $claims['username'] ?? ''),
		];
		SupportChatTypingService::setTyping(
			$chatId,
			(int) ($claims['user_id'] ?? 0),
			$role === 'root' ? 'attendant' : 'customer',
			(string) ($claims['user_name'] ?? $claims['username'] ?? ''),
			$isTyping
		);

		if ($role === 'root')
			{
			$this->broadcastToChat($chat, $payload, 'root');
			return;
			}

		$this->broadcastToRoots($payload);
		}

	/**
	 * Assigns a chat to a root attendant.
	 *
	 * @param array<string, mixed> $message
	 */
	private function handleAssignChat(int $clientId, array $message): void
		{
		try
			{
			$claims = $this->clients[$clientId]['claims'];
			if ((string) ($claims['role'] ?? '') !== 'root')
				{
				$this->sendError($clientId, 'Only root users can assign chats.');
				return;
				}

			$chatId = (int) ($message['chat_id'] ?? 0);
			$attendantUserId = (int) ($message['attendant_user_id'] ?? 0);

			if ($chatId <= 0 || $attendantUserId <= 0)
				{
				$this->sendError($clientId, 'Chat and attendant are required.');
				return;
				}

			$chat = $this->repository()->assignChat($chatId, $attendantUserId);
			if (!$chat)
				{
				$this->sendError($clientId, 'Chat not found.');
				return;
				}

			$this->broadcastChatAssigned($chatId, $chat);
			}
		catch (\RuntimeException $exception)
			{
			$this->sendError($clientId, $exception->getMessage());
			}
		}

	/**
	 * Closes a chat from the support dashboard.
	 *
	 * @param array<string, mixed> $message
	 */
	private function handleCloseChat(int $clientId, array $message): void
		{
		try
			{
			$claims = $this->clients[$clientId]['claims'];
			if ((string) ($claims['role'] ?? '') !== 'root')
				{
				$this->sendError($clientId, 'Only root users can close chats.');
				return;
				}

			$chatId = (int) ($message['chat_id'] ?? 0);
			if ($chatId <= 0)
				{
				$this->sendError($clientId, 'Chat id is required.');
				return;
				}

			$chat = $this->repository()->closeChat($chatId, (int) ($claims['user_id'] ?? 0));
			if (!$chat)
				{
				$this->sendError($clientId, 'Chat not found.');
				return;
				}

			$this->broadcastChatClosed($chatId, $chat);
			}
		catch (\RuntimeException $exception)
			{
			$this->sendError($clientId, $exception->getMessage());
			}
		}

	/**
	 * Sends a periodic ping frame to keep browser and proxies warm.
	 */
	private function sendHeartbeat(): void
		{
		$now = time();
		if (($now - $this->lastPingAt) < 20)
			{
			return;
			}

		foreach (array_keys($this->clients) as $clientId)
			{
			if ($this->clients[$clientId]['handshake_complete'])
				{
				$this->sendFrame($clientId, 'keepalive', 0x9);
				}
			}

		$this->lastPingAt = $now;
		}

	/**
	 * Removes clients that remained silent for too long.
	 */
	private function cleanupIdleClients(): void
		{
		$threshold = time() - 120;

		foreach (array_keys($this->clients) as $clientId)
			{
			if (($this->clients[$clientId]['last_activity_at'] ?? 0) < $threshold)
				{
				$this->disconnectClient($clientId);
				}
			}
		}

	/**
	 * Broadcast helpers.
	 *
	 * @param array<string, mixed> $messageRow
	 * @param array<string, mixed>|null $chat
	 */
	private function broadcastMessageCreated(int $chatId, array $messageRow, ?array $chat): void
		{
		$payload = [
			'type' => 'message_created',
			'chat_id' => $chatId,
			'message' => $messageRow,
			'status' => $chat['status'] ?? null,
		];

		$this->broadcastToChat($chat ?? ['id' => $chatId], $payload, null);
		$this->broadcastToRoots($payload);
		}

	/**
	 * @param array<string, mixed>|null $chat
	 */
	private function broadcastChatCreated(int $chatId, ?array $chat = null): void
		{
		$payload = [
			'type' => 'chat_created',
			'chat_id' => $chatId,
			'status' => $chat['status'] ?? null,
		];

		$this->broadcastToRoots($payload);
		if ($chat) {
			$this->broadcastToChat($chat, $payload, null);
		}
		}

	/**
	 * @param array<string, mixed> $chat
	 */
	private function broadcastChatAssigned(int $chatId, array $chat): void
		{
		$payload = [
			'type' => 'chat_assigned',
			'chat_id' => $chatId,
			'attendant_user_id' => (int) ($chat['attendant_user_id'] ?? 0),
			'attendant_name' => (string) ($chat['attendant_name'] ?? $chat['attendant_username'] ?? ''),
		];

		$this->broadcastToRoots($payload);
		$this->broadcastToChat($chat, $payload, null);
		}

	/**
	 * @param array<string, mixed> $chat
	 */
	private function broadcastChatClosed(int $chatId, array $chat): void
		{
		$payload = [
			'type' => 'chat_closed',
			'chat_id' => $chatId,
			'status' => 'closed',
		];

		$this->broadcastToRoots($payload);
		$this->broadcastToChat($chat, $payload, null);
		}

	/**
	 * @param array<string, mixed> $chat
	 * @param array<string, mixed> $payload
	 */
	private function broadcastToChat(array $chat, array $payload, ?string $excludeRole): void
		{
		foreach ($this->clients as $clientId => $client)
			{
			if (!$client['authenticated'])
				{
				continue;
				}

			$claims = $client['claims'];
			$role = (string) ($claims['role'] ?? '');

			if ($excludeRole !== null && $role === $excludeRole)
				{
				continue;
				}

			if ($role === 'customer')
				{
				if ((int) ($claims['user_id'] ?? 0) !== (int) ($chat['customer_user_id'] ?? 0))
					{
					continue;
					}

				if ((int) ($claims['tenant_id'] ?? 0) !== (int) ($chat['tenant_id'] ?? 0))
					{
					continue;
					}
				}
			elseif ($role === 'root')
				{
				// Roots can receive all chat events via broadcastToRoots as well.
				continue;
				}

			$this->sendJson($clientId, $payload);
			}
		}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function broadcastToRoots(array $payload): void
		{
		foreach ($this->clients as $clientId => $client)
			{
			if (!$client['authenticated'])
				{
				continue;
				}

			if ((string) (($client['claims'] ?? [])['role'] ?? '') !== 'root')
				{
				continue;
				}

			$this->sendJson($clientId, $payload);
			}
		}

	/**
	 * Returns the repository instance bound to the central database.
	 */
	private function repository(): SupportChatRepository
		{
		static $repository = null;
		if ($repository === null)
			{
			$repository = new SupportChatRepository($this->db);
			}

		return $repository;
		}

	/**
	 * Sends a JSON payload over the socket.
	 *
	 * @param array<string, mixed> $payload
	 */
	private function sendJson(int $clientId, array $payload): void
		{
		$this->sendFrame(
			$clientId,
			json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			0x1
		);
		}

	/**
	 * Sends a protocol error back to the browser.
	 */
	private function sendError(int $clientId, string $message): void
		{
		$this->sendJson($clientId, [
			'type' => 'error',
			'message' => $message,
		]);
		}

	/**
	 * Writes a WebSocket frame to the client socket.
	 */
	private function sendFrame(int $clientId, string $payload, int $opcode): void
		{
		if (!isset($this->clients[$clientId]))
			{
			return;
			}

		$length = strlen($payload);
		$frame = chr(0x80 | ($opcode & 0x0F));

		if ($length <= 125)
			{
			$frame .= chr($length);
			}
		elseif ($length <= 65535)
			{
			$frame .= chr(126) . pack('n', $length);
			}
		else
			{
			$frame .= chr(127) . pack('NN', 0, $length);
			}

		$frame .= $payload;
		@fwrite($this->clients[$clientId]['socket'], $frame);
		}

	/**
	 * Decodes a single frame from the buffered client payload.
	 *
	 * @return array<string, mixed>|null
	 */
	private function decodeFrame(string $buffer): ?array
		{
		$length = strlen($buffer);
		if ($length < 2)
			{
			return null;
			}

		$firstByte = ord($buffer[0]);
		$secondByte = ord($buffer[1]);
		$opcode = $firstByte & 0x0F;
		$isMasked = ($secondByte & 0x80) === 0x80;
		$payloadLength = $secondByte & 0x7F;
		$offset = 2;

		if ($payloadLength === 126)
			{
			if ($length < 4)
				{
				return null;
				}

			$payloadLength = unpack('n', substr($buffer, 2, 2))[1];
			$offset = 4;
			}
		elseif ($payloadLength === 127)
			{
			if ($length < 10)
				{
				return null;
				}

			$extended = unpack('N2', substr($buffer, 2, 8));
			$payloadLength = ($extended[1] << 32) + $extended[2];
			$offset = 10;
			}

		$maskLength = $isMasked ? 4 : 0;
		$frameLength = $offset + $maskLength + $payloadLength;

		if ($length < $frameLength)
			{
			return null;
			}

		$mask = $isMasked ? substr($buffer, $offset, 4) : '';
		$payload = substr($buffer, $offset + $maskLength, $payloadLength);

		if ($isMasked)
			{
			$decoded = '';
			for ($i = 0; $i < $payloadLength; $i++)
				{
				$decoded .= $payload[$i] ^ $mask[$i % 4];
				}
			$payload = $decoded;
			}

		return [
			'opcode' => $opcode,
			'payload' => $payload,
			'frame_length' => $frameLength,
		];
		}

	/**
	 * Disconnects and forgets a client socket.
	 */
	private function disconnectClient(int $clientId): void
		{
		if (!isset($this->clients[$clientId]))
			{
			return;
			}

		@fclose($this->clients[$clientId]['socket']);
		unset($this->clients[$clientId]);
		}

	/**
	 * Locates a client id from a socket resource.
	 *
	 * @param resource $socket
	 */
	private function findClientIdBySocket($socket): ?int
		{
		foreach ($this->clients as $clientId => $client)
			{
			if ($client['socket'] === $socket)
				{
				return $clientId;
				}
			}

		return null;
		}

	/**
	 * Writes a log line without depending on the global STDOUT constant.
	 */
	private function writeLog(string $message): void
		{
		if (is_resource($this->stdout))
			{
			fwrite($this->stdout, $message);
			return;
			}

		error_log(trim($message));
		}
	}

?>
