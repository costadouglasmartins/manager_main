<?php

namespace App\SupportChat;

use PDO;

require_once __DIR__ . '/VoiceCallStore.php';

class SupportChatRepository implements VoiceCallStore
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
		$startedTransaction = !$this->db->inTransaction();

		try
			{
			if ($startedTransaction)
				{
				$this->db->beginTransaction();
				}

			$activeChatId = $this->findActiveCustomerChatIdForUpdate($customerUserId, $tenantId);
			if ($activeChatId > 0)
				{
				$activeChat = $this->findChatById($activeChatId) ?? [];
				if ($startedTransaction)
					{
					$this->db->commit();
					}

				return $activeChat;
				}

			$attendant = $this->pickAttendant(true);
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

			$chat = $this->findChatById((int) $this->db->lastInsertId()) ?? [];
			if ($startedTransaction)
				{
				$this->db->commit();
				}

			return $chat;
			}
		catch (\Throwable $exception)
			{
			if ($startedTransaction && $this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			throw $exception;
			}
		}

	/**
	 * Attempts to assign a pending or unavailable chat to an online root attendant.
	 *
	 * @param int $chatId Support chat identifier.
	 * @param bool $replaceUnavailableAttendant Whether an offline assigned attendant can be replaced.
	 * @return array<string, mixed>|null
	 **/
	public function assignAvailableAttendant(int $chatId, bool $replaceUnavailableAttendant = false): ?array
		{
		if ($chatId <= 0)
			{
			return null;
			}

		$startedTransaction = !$this->db->inTransaction();

		try
			{
			if ($startedTransaction)
				{
				$this->db->beginTransaction();
				}

			$lockedChat = $this->findChatAssignmentForUpdate($chatId);
			if (!$lockedChat)
				{
				if ($startedTransaction)
					{
					$this->db->commit();
					}

				return null;
				}

			$currentAttendantUserId = (int) ($lockedChat['attendant_user_id'] ?? 0);
			if ((string) ($lockedChat['status'] ?? '') === 'closed'
				|| ($currentAttendantUserId > 0 && !$replaceUnavailableAttendant))
				{
				$chat = $this->findChatById($chatId);
				if ($startedTransaction)
					{
					$this->db->commit();
					}

				return $chat;
				}

			$attendant = $this->pickAttendant(true);
			if ($currentAttendantUserId > 0 && $this->isOnlineRootUser($currentAttendantUserId))
				{
				$chat = $this->findChatById($chatId);
				if ($startedTransaction)
					{
					$this->db->commit();
					}

				return $chat;
				}

			if (!$attendant)
				{
				$this->markChatPending($chatId);
				$chat = $this->findChatById($chatId);
				if ($startedTransaction)
					{
					$this->db->commit();
					}

				return $chat;
				}

			$now = $this->now();
			$assignmentCondition = $replaceUnavailableAttendant
				? "AND status <> 'closed'"
				: "AND attendant_user_id IS NULL AND status <> 'closed'";
			$assignStmt = $this->db->prepare("
				UPDATE support_chats
				SET attendant_last_seen_at = NULL,
				    attendant_user_id = :attendant_user_id,
				    status = 'open',
				    modified = :modified
				WHERE id = :chat_id
				  {$assignmentCondition}
			");
			$assignStmt->execute([
				'attendant_user_id' => (int) $attendant['id'],
				'modified' => $now,
				'chat_id' => $chatId,
			]);

			$chat = $this->findChatById($chatId);
			if ($startedTransaction)
				{
				$this->db->commit();
				}

			return $chat;
			}
		catch (\Throwable $exception)
			{
			if ($startedTransaction && $this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			throw $exception;
			}
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
			SET attendant_last_seen_at = CASE
			      WHEN COALESCE(attendant_user_id, 0) <> :new_attendant_user_id THEN NULL
			      ELSE attendant_last_seen_at
			    END,
			    attendant_user_id = :attendant_user_id,
			    status = 'open',
			    modified = :modified
			WHERE id = :chat_id
		");
		$stmt->execute([
			'new_attendant_user_id' => $attendantUserId,
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
	 * Returns whether the provided user is an active root marked as online.
	 **/
	public function isOnlineRootUser(int $userId): bool
		{
		if ($userId <= 0)
			{
			return false;
			}

		$stmt = $this->db->prepare("
			SELECT 1
			FROM users AS User
			INNER JOIN user_presences AS UserPresence ON UserPresence.user_id = User.id
			WHERE User.id = :user_id
			  AND User.type = 'root'
			  AND User.active = 1
			  AND UserPresence.status = 'online'
			LIMIT 1
		");
		$stmt->execute(['user_id' => $userId]);

		return (bool) $stmt->fetchColumn();
		}

	/**
	 * Returns whether an active customer still has access to the active tenant
	 * represented by the token database number.
	 */
	public function canAccessTenant(int $userId, int $tenantId, string $databaseNumber): bool
		{
		$databaseNumber = trim($databaseNumber);
		if ($userId <= 0 || $tenantId <= 0 || $databaseNumber === '')
			{
			return false;
			}

		$stmt = $this->db->prepare("
			SELECT 1
			FROM users AS User
			INNER JOIN tenants AS Tenant
			        ON Tenant.id = :tenant_id
			       AND Tenant.database_number = :database_number
			       AND Tenant.status = 'active'
			WHERE User.id = :user_id
			  AND User.active = 1
			  AND (
			    User.tenant_id = Tenant.id
			    OR EXISTS (
			      SELECT 1
			      FROM tenant_users AS TenantUser
			      WHERE TenantUser.tenant_id = Tenant.id
			        AND TenantUser.user_id = User.id
			    )
			  )
			LIMIT 1
		");
		$stmt->execute([
			'user_id' => $userId,
			'tenant_id' => $tenantId,
			'database_number' => $databaseNumber,
		]);

		return (bool) $stmt->fetchColumn();
		}

	/**
	 * Returns a lightweight attendant row for automatic assignment.
	 *
	 * @return array<string, mixed>|null
	 */
	private function pickAttendant(bool $lockOnlineAttendants = false): ?array
		{
		if ($lockOnlineAttendants)
			{
			$this->lockOnlineAttendants();
			}

		$sql = "SELECT User.id,
		               Person.name,
		               COALESCE(Stats.open_total, 0) AS open_total
		        FROM users AS User
		        INNER JOIN people AS Person ON Person.id = User.person_id
		        INNER JOIN user_presences AS UserPresence ON UserPresence.user_id = User.id
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
		          AND UserPresence.status = 'online'
		        ORDER BY COALESCE(Stats.open_total, 0) ASC, User.id ASC
		        LIMIT 1";

		$stmt = $this->db->query($sql);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ?: null;
		}

	/**
	 * Locks active chats for one customer/tenant before opening another one.
	 **/
	private function findActiveCustomerChatIdForUpdate(int $customerUserId, int $tenantId): int
		{
		$stmt = $this->db->prepare("
			SELECT Chat.id
			FROM support_chats AS Chat
			WHERE Chat.customer_user_id = :customer_user_id
			  AND Chat.tenant_id = :tenant_id
			  AND Chat.status IN ('pending', 'open')
			ORDER BY Chat.id DESC
			LIMIT 1
			FOR UPDATE
		");
		$stmt->execute([
			'customer_user_id' => $customerUserId,
			'tenant_id' => $tenantId,
		]);

		return (int) ($stmt->fetchColumn() ?: 0);
		}

	/**
	 * Locks one chat while its availability assignment is evaluated.
	 *
	 * @return array<string, mixed>|null
	 **/
	private function findChatAssignmentForUpdate(int $chatId): ?array
		{
		$stmt = $this->db->prepare("
			SELECT Chat.id,
			       Chat.attendant_user_id,
			       Chat.status
			FROM support_chats AS Chat
			WHERE Chat.id = :chat_id
			LIMIT 1
			FOR UPDATE
		");
		$stmt->execute(['chat_id' => $chatId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ?: null;
		}

	/**
	 * Locks all active online roots so concurrent selections observe prior assignments.
	 **/
	private function lockOnlineAttendants(): void
		{
		$stmt = $this->db->prepare("
			SELECT User.id
			FROM users AS User
			INNER JOIN user_presences AS UserPresence ON UserPresence.user_id = User.id
			WHERE User.active = 1
			  AND User.type = 'root'
			  AND UserPresence.status = 'online'
			ORDER BY User.id ASC
			FOR UPDATE
		");
		$stmt->execute();
		$stmt->fetchAll(PDO::FETCH_COLUMN);
		}

	/**
	 * Returns an unavailable chat to the pending support queue without an attendant.
	 **/
	private function markChatPending(int $chatId): void
		{
		$stmt = $this->db->prepare("
			UPDATE support_chats
			SET attendant_user_id = NULL,
			    attendant_last_seen_at = NULL,
			    status = 'pending',
			    modified = :modified
			WHERE id = :chat_id
			  AND status <> 'closed'
		");
		$stmt->execute([
			'modified' => $this->now(),
			'chat_id' => $chatId,
		]);
		}

	/**
	 * Returns whether an active user belongs to an active tenant, using the
	 * same membership rules as the internal chat team list.
	 */
	public function userBelongsToTenant(int $userId, int $tenantId): bool
		{
		if ($userId <= 0 || $tenantId <= 0)
			{
			return false;
			}

		$stmt = $this->db->prepare("
			SELECT 1
			FROM users AS User
			INNER JOIN tenants AS Tenant
			        ON Tenant.id = :tenant_id
			       AND Tenant.status = 'active'
			WHERE User.id = :user_id
			  AND User.active = 1
			  AND (
			    User.tenant_id = Tenant.id
			    OR EXISTS (
			      SELECT 1
			      FROM tenant_users AS TenantUser
			      WHERE TenantUser.tenant_id = Tenant.id
			        AND TenantUser.user_id = User.id
			    )
			  )
			LIMIT 1
		");
		$stmt->execute([
			'user_id' => $userId,
			'tenant_id' => $tenantId,
		]);

		return (bool) $stmt->fetchColumn();
		}

	/**
	 * Returns whether voice calls are enabled for a tenant. A missing setting
	 * definition (migration not applied) keeps the feature off.
	 */
	public function voiceCallsEnabled(int $tenantId): bool
		{
		if ($tenantId <= 0)
			{
			return false;
			}

		$stmt = $this->db->prepare("
			SELECT COALESCE(TenantSettingValue.value, SystemSettingDefinition.default_value) AS resolved_value
			FROM system_setting_definitions AS SystemSettingDefinition
			LEFT JOIN tenant_setting_values AS TenantSettingValue
			       ON TenantSettingValue.setting_definition_id = SystemSettingDefinition.id
			      AND TenantSettingValue.tenant_id = :tenant_id
			WHERE SystemSettingDefinition.key_name = 'voice_calls_enabled'
			  AND SystemSettingDefinition.active = 1
			LIMIT 1
		");
		$stmt->execute(['tenant_id' => $tenantId]);
		$value = $stmt->fetchColumn();

		return $value !== false && (string) $value === '1';
		}

	/**
	 * Returns the display name of an active user.
	 */
	public function userDisplayName(int $userId): string
		{
		$stmt = $this->db->prepare("
			SELECT COALESCE(NULLIF(Person.name, ''), User.username) AS display_name
			FROM users AS User
			LEFT JOIN people AS Person ON Person.id = User.person_id
			WHERE User.id = :user_id
			LIMIT 1
		");
		$stmt->execute(['user_id' => $userId]);
		$name = $stmt->fetchColumn();

		return $name === false ? ('Usuário #' . $userId) : (string) $name;
		}

	/**
	 * Records a new voice call attempt.
	 *
	 * @param array<string, mixed> $call
	 */
	public function createVoiceCallLog(array $call): void
		{
		$stmt = $this->db->prepare("
			INSERT INTO voice_calls
				(call_uid, kind, tenant_id, support_chat_id, caller_user_id, caller_system, callee_user_id, callee_system, status, started_at, created, modified)
			VALUES
				(:call_uid, :kind, :tenant_id, :support_chat_id, :caller_user_id, :caller_system, :callee_user_id, :callee_system, :status, :started_at, :created, :modified)
		");
		$now = $this->now();
		$stmt->execute([
			'call_uid' => (string) $call['id'],
			'kind' => (string) $call['kind'],
			'tenant_id' => (int) ($call['tenant_id'] ?? 0) > 0 ? (int) $call['tenant_id'] : null,
			'support_chat_id' => (int) ($call['chat_id'] ?? 0) > 0 ? (int) $call['chat_id'] : null,
			'caller_user_id' => (int) $call['caller']['user_id'],
			'caller_system' => (string) $call['caller']['system'],
			'callee_user_id' => (int) $call['callee']['user_id'],
			'callee_system' => (string) $call['callee']['system'],
			'status' => (string) $call['status'],
			'started_at' => $now,
			'created' => $now,
			'modified' => $now,
		]);
		}

	/**
	 * Updates a voice call record after a state change.
	 */
	public function updateVoiceCallLog(string $callUid, string $status, ?string $endReason, bool $answered, bool $ended, int $durationSeconds): void
		{
		$now = $this->now();
		$stmt = $this->db->prepare("
			UPDATE voice_calls
			SET status = :status,
			    end_reason = COALESCE(:end_reason, end_reason),
			    answered_at = CASE WHEN :answered = 1 AND answered_at IS NULL THEN :answered_at ELSE answered_at END,
			    ended_at = CASE WHEN :ended = 1 THEN :ended_at ELSE ended_at END,
			    duration_seconds = :duration_seconds,
			    modified = :modified
			WHERE call_uid = :call_uid
		");
		$stmt->execute([
			'status' => $status,
			'end_reason' => $endReason,
			'answered' => $answered ? 1 : 0,
			'answered_at' => $now,
			'ended' => $ended ? 1 : 0,
			'ended_at' => $now,
			'duration_seconds' => max(0, $durationSeconds),
			'modified' => $now,
			'call_uid' => $callUid,
		]);
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
