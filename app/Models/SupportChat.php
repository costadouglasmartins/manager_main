<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class SupportChat extends Model
	{
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
		        FROM support_chat_messages AS Message
		        WHERE Message.support_chat_id = :chat_id
		        ORDER BY Message.id ASC
		        LIMIT :limit";

		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':chat_id', $chatId, PDO::PARAM_INT);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}
	}

?>
