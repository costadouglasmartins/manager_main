<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class SupportChat extends Model
	{
	private const DEFAULT_TIMEZONE = 'America/Sao_Paulo';

	/**
	 * Returns the support attendants list (root users only).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getAttendants(): array
		{
		$sql = "SELECT User.id,
		               Person.name,
		               User.username
		        FROM users AS User
		        INNER JOIN people AS Person ON Person.id = User.person_id
		        WHERE User.active = 1
		          AND User.type = 'root'
		        ORDER BY Person.name ASC, User.id ASC";

		$stmt = $this->db->query($sql);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns a compact list of chats for the support dashboard.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function listChats(int $limit = 80): array
		{
		return $this->searchChats([], $limit, 0);
		}

	/**
	 * Searches support chats using listing filters.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function searchChats(array $filters = [], int $limit = 20, int $offset = 0): array
		{
		$params = [];
		$where = $this->buildSearchWhere($filters, $params);

		$sql = "SELECT Chat.id,
		               Chat.status,
		               Chat.customer_user_id,
		               Chat.attendant_user_id,
		               Chat.tenant_id,
		               Chat.tenant_database_number,
		               Chat.last_message_at,
		               Chat.created,
		               Chat.closed_at,
		               CustomerPerson.name AS customer_name,
		               CustomerUser.username AS customer_username,
		               Tenant.observations AS tenant_name,
		               Tenant.status AS tenant_status,
		               AttendantPerson.name AS attendant_name,
		               AttendantUser.username AS attendant_username
		        FROM support_chats AS Chat
		        INNER JOIN users AS CustomerUser ON CustomerUser.id = Chat.customer_user_id
		        INNER JOIN people AS CustomerPerson ON CustomerPerson.id = CustomerUser.person_id
		        LEFT JOIN tenants AS Tenant ON Tenant.id = Chat.tenant_id
		        LEFT JOIN users AS AttendantUser ON AttendantUser.id = Chat.attendant_user_id
		        LEFT JOIN people AS AttendantPerson ON AttendantPerson.id = AttendantUser.person_id
		        WHERE {$where}
		        ORDER BY
		          CASE Chat.status
		            WHEN 'pending' THEN 0
		            WHEN 'open' THEN 1
		            ELSE 2
		          END ASC,
		          COALESCE(Chat.last_message_at, Chat.created) DESC,
		          Chat.id DESC
		        LIMIT :limit OFFSET :offset";

		$stmt = $this->db->prepare($sql);
		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Counts chats for the current listing filters.
	 */
	public function countChats(array $filters = []): int
		{
		$params = [];
		$where = $this->buildSearchWhere($filters, $params);

		$sql = "SELECT COUNT(*) AS total
		        FROM support_chats AS Chat
		        INNER JOIN users AS CustomerUser ON CustomerUser.id = Chat.customer_user_id
		        INNER JOIN people AS CustomerPerson ON CustomerPerson.id = CustomerUser.person_id
		        LEFT JOIN tenants AS Tenant ON Tenant.id = Chat.tenant_id
		        LEFT JOIN users AS AttendantUser ON AttendantUser.id = Chat.attendant_user_id
		        LEFT JOIN people AS AttendantPerson ON AttendantPerson.id = AttendantUser.person_id
		        WHERE {$where}";

		$stmt = $this->db->prepare($sql);
		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Builds the WHERE clause used by chat listing queries.
	 *
	 * @param array<string, mixed> $filters
	 * @param array<string, mixed> $params
	 */
	private function buildSearchWhere(array $filters, array &$params): string
		{
		$where = ["1=1"];

		if (!empty($filters['date_from']))
			{
			$where[] = "DATE(Chat.created) >= :date_from";
			$params['date_from'] = (string) $filters['date_from'];
			}

		if (!empty($filters['date_to']))
			{
			$where[] = "DATE(Chat.created) <= :date_to";
			$params['date_to'] = (string) $filters['date_to'];
			}

		if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'open', 'closed'], true))
			{
			$where[] = "Chat.status = :status";
			$params['status'] = (string) $filters['status'];
			}

		$participant = trim((string) ($filters['participant'] ?? ''));
		if ($participant !== '')
			{
			$where[] = "(CustomerPerson.name LIKE :participant_customer_name
			            OR CustomerUser.username LIKE :participant_customer_username
			            OR AttendantPerson.name LIKE :participant_attendant_name
			            OR AttendantUser.username LIKE :participant_attendant_username
			            OR Tenant.observations LIKE :participant_tenant_name
			            OR Tenant.database_number LIKE :participant_tenant_database
			            OR Chat.tenant_database_number LIKE :participant_chat_tenant_database)";
			$participantLike = '%' . $participant . '%';
			$params['participant_customer_name'] = $participantLike;
			$params['participant_customer_username'] = $participantLike;
			$params['participant_attendant_name'] = $participantLike;
			$params['participant_attendant_username'] = $participantLike;
			$params['participant_tenant_name'] = $participantLike;
			$params['participant_tenant_database'] = $participantLike;
			$params['participant_chat_tenant_database'] = $participantLike;
			}

		return implode(' AND ', $where);
		}

	/**
	 * Returns one chat with its derived labels.
	 */
	public function findChatById(int $chatId): ?array
		{
		$sql = "SELECT Chat.id,
		               Chat.status,
		               Chat.customer_user_id,
		               Chat.attendant_user_id,
		               Chat.tenant_id,
		               Chat.tenant_database_number,
		               Chat.created,
		               Chat.modified,
		               Chat.last_message_at,
		               Chat.closed_at,
		               Chat.closed_by_user_id,
		               Chat.customer_last_seen_at,
		               Chat.attendant_last_seen_at,
		               CustomerPerson.name AS customer_name,
		               CustomerUser.username AS customer_username,
		               Tenant.observations AS tenant_name,
		               Tenant.database_number,
		               Tenant.status AS tenant_status,
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
	 * Returns chat messages ordered by creation time.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getMessages(int $chatId, int $limit = 200): array
		{
		$sql = "SELECT Message.id,
		               Message.support_chat_id,
		               Message.sender_user_id,
		               Message.sender_role,
		               Message.sender_name,
		               Message.message_text,
		               Message.created
		        FROM support_chats_messages AS Message
		        WHERE Message.support_chat_id = :chat_id
		        ORDER BY Message.id ASC
		        LIMIT :limit";

		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':chat_id', $chatId, PDO::PARAM_INT);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Reopens a closed support chat and preserves its current attendant.
	 *
	 * @return array<string, mixed>|null
	 */
	public function reopenChat(int $chatId): ?array
		{
		$chat = $this->findChatById($chatId);
		if (!$chat)
			{
			return null;
			}

		if ((string) ($chat['status'] ?? '') !== 'closed')
			{
			return $chat;
			}

		$status = empty($chat['attendant_user_id']) ? 'pending' : 'open';
		$stmt = $this->db->prepare("
			UPDATE support_chats
			SET status = :status,
			    closed_at = NULL,
			    closed_by_user_id = NULL,
			    modified = :modified
			WHERE id = :chat_id
		");
		$stmt->execute([
			'status' => $status,
			'modified' => $this->now(),
			'chat_id' => $chatId,
		]);

		return $this->findChatById($chatId);
		}

	/**
	 * Deletes a support chat and its messages through database cascades.
	 */
	public function deleteChat(int $chatId): bool
		{
		$stmt = $this->db->prepare("DELETE FROM support_chats WHERE id = :chat_id");
		$stmt->execute(['chat_id' => $chatId]);

		return $stmt->rowCount() > 0;
		}

	/**
	 * Returns customer messages that have not been seen by an attendant yet.
	 *
	 * @return array{count: int, notifications: array<int, array<string, mixed>>}
	 */
	public function getUnreadCustomerNotifications(int $limit = 8): array
		{
		$limit = max(1, min(50, $limit));
		$where = "Message.sender_role = 'customer'
		          AND Chat.status IN ('pending', 'open')
		          AND (
		            Chat.attendant_last_seen_at IS NULL
		            OR Message.created > Chat.attendant_last_seen_at
		          )";

		$countSql = "SELECT COUNT(*) AS total
		             FROM support_chats_messages AS Message
		             INNER JOIN support_chats AS Chat ON Chat.id = Message.support_chat_id
		             WHERE {$where}";
		$countStmt = $this->db->query($countSql);
		$count = (int) $countStmt->fetchColumn();

		$sql = "SELECT Message.id AS message_id,
		               Message.support_chat_id AS chat_id,
		               Message.sender_name,
		               Message.message_text,
		               Message.created,
		               Chat.status,
		               Chat.tenant_database_number,
		               CustomerPerson.name AS customer_name,
		               CustomerUser.username AS customer_username,
		               Tenant.observations AS tenant_name
		        FROM support_chats_messages AS Message
		        INNER JOIN support_chats AS Chat ON Chat.id = Message.support_chat_id
		        INNER JOIN users AS CustomerUser ON CustomerUser.id = Chat.customer_user_id
		        INNER JOIN people AS CustomerPerson ON CustomerPerson.id = CustomerUser.person_id
		        LEFT JOIN tenants AS Tenant ON Tenant.id = Chat.tenant_id
		        WHERE {$where}
		        ORDER BY Message.created DESC, Message.id DESC
		        LIMIT :limit";

		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		return [
			'count' => $count,
			'notifications' => $stmt->fetchAll(PDO::FETCH_ASSOC),
		];
		}

	/**
	 * Marks the messages displayed to an attendant as seen.
	 *
	 * @param array<int, array<string, mixed>> $messages
	 */
	public function markAttendantMessagesSeen(int $chatId, array $messages = []): void
		{
		$seenAt = null;
		foreach ($messages as $message)
			{
			$created = trim((string) ($message['created'] ?? ''));
			if ($created !== '' && ($seenAt === null || $created > $seenAt))
				{
				$seenAt = $created;
				}
			}

		if ($seenAt === null)
			{
			$seenAt = $this->now();
			}

		$stmt = $this->db->prepare("
			UPDATE support_chats
			SET attendant_last_seen_at = CASE
			      WHEN attendant_last_seen_at IS NULL OR attendant_last_seen_at < :seen_at THEN :seen_at
			      ELSE attendant_last_seen_at
			    END,
			    modified = :modified
			WHERE id = :chat_id
		");
		$stmt->execute([
			'seen_at' => $seenAt,
			'modified' => $this->now(),
			'chat_id' => $chatId,
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
