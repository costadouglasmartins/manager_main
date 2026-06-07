<?php

namespace App\SupportChat;

use PDO;

class SupportChatRepository
	{
	private const DEFAULT_TIMEZONE = 'America/Sao_Paulo';

	private PDO $db;

	public function __construct(PDO $db)
		{
		$this->db = $db;
		}

	/**
	 * Returns one chat by id.
	 *
	 * @return array<string, mixed>|null
	 */
	public function findChatById(int $chatId): ?array
		{
		$sql = "SELECT Chat.*,
		               CustomerPerson.name AS customer_name,
		               CustomerUser.username AS customer_username,
		               Tenant.observations AS tenant_name,
		               AttendantPerson.name AS attendant_name,
		               AttendantUser.username AS attendant_username
		        FROM support_chats AS Chat
		        INNER JOIN users AS CustomerUser ON CustomerUser.id = Chat.customer_user_id
		        INNER JOIN people AS CustomerPerson ON CustomerPerson.id = CustomerUser.person_id
		        LEFT JOIN tenants AS Tenant ON Tenant.id = Chat.tenant_id
		        LEFT JOIN users AS AttendantUser ON AttendantUser.id = Chat.attendant_user_id
		        LEFT JOIN people AS AttendantPerson ON AttendantPerson.id = AttendantUser.person_id
		        WHERE Chat.id = :chat_id
		        LIMIT 1";

		$stmt = $this->db->prepare($sql);
		$stmt->execute(['chat_id' => $chatId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ?: null;
		}

	/**
	 * Finds the latest open or pending chat for a customer in the selected tenant.
	 *
	 * @return array<string, mixed>|null
	 */
	public function findActiveCustomerChat(int $customerUserId, int $tenantId): ?array
		{
		$stmt = $this->db->prepare("
			SELECT *
			FROM support_chats
			WHERE customer_user_id = :customer_user_id
			  AND tenant_id = :tenant_id
			  AND status IN ('pending', 'open')
			ORDER BY id DESC
			LIMIT 1
		");
		$stmt->execute([
			'customer_user_id' => $customerUserId,
			'tenant_id' => $tenantId,
		]);

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
		}

	/**
	 * Creates a new chat and assigns the least busy root attendant when available.
	 *
	 * @return array<string, mixed>
	 */
	public function createCustomerChat(int $customerUserId, int $tenantId, string $tenantDatabaseNumber): array
		{
		$attendant = $this->pickAttendant();
		$now = $this->now();
		$status = $attendant ? 'open' : 'pending';

		$stmt = $this->db->prepare("
			INSERT INTO support_chats (
				customer_user_id,
				attendant_user_id,
				tenant_id,
				tenant_database_number,
				status,
				last_message_at,
				customer_last_seen_at,
				created,
				modified
			) VALUES (
				:customer_user_id,
				:attendant_user_id,
				:tenant_id,
				:tenant_database_number,
				:status,
				:last_message_at,
				:customer_last_seen_at,
				:created,
				:modified
			)
		");
		$stmt->execute([
			'customer_user_id' => $customerUserId,
			'attendant_user_id' => $attendant ? (int) $attendant['id'] : null,
			'tenant_id' => $tenantId,
			'tenant_database_number' => $tenantDatabaseNumber,
			'status' => $status,
			'last_message_at' => $now,
			'customer_last_seen_at' => $now,
			'created' => $now,
			'modified' => $now,
		]);

		return $this->findChatById((int) $this->db->lastInsertId()) ?? [];
		}

	/**
	 * Persists a new message and updates chat timestamps/assignment.
	 *
	 * @return array<string, mixed>
	 */
	public function createMessage(int $chatId, int $senderUserId, string $senderRole, string $senderName, string $messageText): array
		{
		$chat = $this->findChatById($chatId);
		if (!$chat)
			{
			throw new \RuntimeException('Chat not found.');
			}
		if ((string) ($chat['status'] ?? '') === 'closed')
			{
			throw new \RuntimeException('Closed chats cannot receive new messages.');
			}

		$now = $this->now();

		if ($senderRole === 'attendant' && empty($chat['attendant_user_id']))
			{
			$this->assignChat($chatId, $senderUserId);
			$chat = $this->findChatById($chatId) ?? $chat;
			}

		if ($senderRole === 'attendant' && (int) ($chat['attendant_user_id'] ?? 0) !== $senderUserId)
			{
			$this->assignChat($chatId, $senderUserId);
			$chat = $this->findChatById($chatId) ?? $chat;
			}

		$stmt = $this->db->prepare("
			INSERT INTO support_chats_messages (
				support_chat_id,
				sender_user_id,
				sender_role,
				sender_name,
				message_text,
				created
			) VALUES (
				:chat_id,
				:sender_user_id,
				:sender_role,
				:sender_name,
				:message_text,
				:created
			)
		");
		$stmt->execute([
			'chat_id' => $chatId,
			'sender_user_id' => $senderUserId,
			'sender_role' => $senderRole,
			'sender_name' => $senderName,
			'message_text' => $messageText,
			'created' => $now,
		]);
		$messageId = (int) $this->db->lastInsertId();

		$lastSeenColumn = $senderRole === 'attendant' ? 'attendant_last_seen_at' : 'customer_last_seen_at';
		$status = empty($chat['attendant_user_id']) && $senderRole === 'customer' ? 'pending' : 'open';

		$updateSql = sprintf(
			"UPDATE support_chats
			 SET status = :status,
			     last_message_at = :last_message_at,
			     modified = :modified,
			     %s = :last_seen
			 WHERE id = :chat_id",
			$lastSeenColumn
		);
		$update = $this->db->prepare($updateSql);
		$update->execute([
			'status' => $status,
			'last_message_at' => $now,
			'modified' => $now,
			'last_seen' => $now,
			'chat_id' => $chatId,
		]);

		$messageStmt = $this->db->prepare("
			SELECT id,
			       support_chat_id,
			       sender_user_id,
			       sender_role,
			       sender_name,
			       message_text,
			       created
			FROM support_chats_messages
			WHERE id = :id
			LIMIT 1
		");
		$messageStmt->execute(['id' => $messageId]);
		$message = $messageStmt->fetch(PDO::FETCH_ASSOC);

		if (!$message)
			{
			throw new \RuntimeException('Message not found after insert.');
			}

		return $message;
		}

	/**
	 * Assigns a chat to a root attendant.
	 */
	public function assignChat(int $chatId, int $attendantUserId): ?array
		{
		if (!$this->isRootUser($attendantUserId))
			{
			throw new \RuntimeException('Attendant must be a root user.');
			}

		$chat = $this->findChatById($chatId);
		if (!$chat)
			{
			return null;
			}
		if ((string) ($chat['status'] ?? '') === 'closed')
			{
			throw new \RuntimeException('Closed chats cannot be reassigned.');
			}

		$now = $this->now();
		$stmt = $this->db->prepare("
			UPDATE support_chats
			SET attendant_user_id = :attendant_user_id,
			    status = 'open',
			    modified = :modified
			WHERE id = :chat_id
		");
		$stmt->execute([
			'attendant_user_id' => $attendantUserId,
			'modified' => $now,
			'chat_id' => $chatId,
		]);

		return $this->findChatById($chatId);
		}

	/**
	 * Closes a chat by a root attendant.
	 */
	public function closeChat(int $chatId, int $closedByUserId): ?array
		{
		if (!$this->isRootUser($closedByUserId))
			{
			throw new \RuntimeException('Only root users can close chats.');
			}

		$chat = $this->findChatById($chatId);
		if (!$chat)
			{
			return null;
			}
		if ((string) ($chat['status'] ?? '') === 'closed')
			{
			return $chat;
			}

		$now = $this->now();
		$stmt = $this->db->prepare("
			UPDATE support_chats
			SET status = 'closed',
			    closed_at = :closed_at,
			    closed_by_user_id = :closed_by_user_id,
			    modified = :modified
			WHERE id = :chat_id
		");
		$stmt->execute([
			'closed_at' => $now,
			'closed_by_user_id' => $closedByUserId,
			'modified' => $now,
			'chat_id' => $chatId,
		]);

		return $this->findChatById($chatId);
		}

	/**
	 * Marks the latest seen timestamp for a chat participant.
	 */
	public function touchSeen(int $chatId, string $role): void
		{
		$column = $role === 'attendant' ? 'attendant_last_seen_at' : 'customer_last_seen_at';
		$sql = sprintf("UPDATE support_chats SET %s = :seen_at, modified = :modified WHERE id = :chat_id", $column);
		$stmt = $this->db->prepare($sql);
		$now = $this->now();
		$stmt->execute([
			'seen_at' => $now,
			'modified' => $now,
			'chat_id' => $chatId,
		]);
		}

	/**
	 * Returns whether the provided user is a root user.
	 */
	public function isRootUser(int $userId): bool
		{
		$stmt = $this->db->prepare("
			SELECT 1
			FROM users
			WHERE id = :user_id
			  AND type = 'root'
			  AND active = 1
			LIMIT 1
		");
		$stmt->execute(['user_id' => $userId]);

		return (bool) $stmt->fetchColumn();
		}

	/**
	 * Returns a lightweight attendant row for automatic assignment.
	 *
	 * @return array<string, mixed>|null
	 */
	private function pickAttendant(): ?array
		{
		$sql = "SELECT User.id,
		               Person.name,
		               COALESCE(Stats.open_total, 0) AS open_total
		        FROM users AS User
		        INNER JOIN people AS Person ON Person.id = User.person_id
		        LEFT JOIN (
		          SELECT attendant_user_id,
		                 COUNT(*) AS open_total
		          FROM support_chats
		          WHERE status IN ('pending', 'open')
		            AND attendant_user_id IS NOT NULL
		          GROUP BY attendant_user_id
		        ) AS Stats ON Stats.attendant_user_id = User.id
		        WHERE User.active = 1
		          AND User.type = 'root'
		        ORDER BY COALESCE(Stats.open_total, 0) ASC, User.id ASC
		        LIMIT 1";

		$stmt = $this->db->query($sql);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ?: null;
		}

	/**
	 * Returns the support chat local timestamp used for MySQL DATETIME fields.
	 */
	private function now(): string
		{
		$timezoneName = defined('SUPPORT_CHAT_TIMEZONE')
			? (string) SUPPORT_CHAT_TIMEZONE
			: (string) (getenv('SUPPORT_CHAT_TIMEZONE') ?: getenv('APP_TIMEZONE') ?: self::DEFAULT_TIMEZONE);

		try
			{
			$timezone = new \DateTimeZone($timezoneName);
			}
		catch (\Exception $exception)
			{
			$timezone = new \DateTimeZone(self::DEFAULT_TIMEZONE);
			}

		return (new \DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s');
		}
	}

?>
