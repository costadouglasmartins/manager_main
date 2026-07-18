<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class UserPresenceService
	{
	public const STATUS_ONLINE = 'online';
	public const STATUS_AWAY = 'away';
	public const STATUS_BUSY = 'busy';
	public const STATUS_OFFLINE = 'offline';

	/**
	 * Database connection used by the service.
	 * @var PDO
	 **/
	private PDO $db;

	/**
	 * Tenant setting resolver.
	 * @var TenantSettingService
	 **/
	private TenantSettingService $settings;

	/**
	 * Creates the service with central database dependencies.
	 **/
	public function __construct()
		{
		$this->db = Database::getInstance();
		$this->settings = new TenantSettingService();
		}

	/**
	 * Returns supported status metadata.
	 * @return array<string, array<string, string>> Status metadata keyed by status.
	 **/
	public static function statuses(): array
		{
		return [
			self::STATUS_ONLINE => [
				'label' => 'Online',
				'class' => 'text-bg-success',
				'icon' => 'bi-circle-fill',
			],
			self::STATUS_AWAY => [
				'label' => 'Away',
				'class' => 'text-bg-warning',
				'icon' => 'bi-clock-fill',
			],
			self::STATUS_BUSY => [
				'label' => 'Busy',
				'class' => 'text-bg-danger',
				'icon' => 'bi-dash-circle-fill',
			],
			self::STATUS_OFFLINE => [
				'label' => 'Offline',
				'class' => 'text-bg-secondary',
				'icon' => 'bi-circle',
			],
		];
		}

	/**
	 * Returns metadata for one status.
	 * @param string $status Status key.
	 * @return array<string, string> Status metadata.
	 **/
	public static function statusMeta(string $status): array
		{
		$statuses = self::statuses();
		return $statuses[$status] ?? $statuses[self::STATUS_OFFLINE];
		}

	/**
	 * Returns the current presence row for a user.
	 * @param int $userId User identifier.
	 * @return array<string, mixed> Presence row with metadata.
	 **/
	public function current(int $userId): array
		{
		try
			{
			$stmt = $this->db->prepare("SELECT UserPresence.user_id,
			                                   UserPresence.tenant_id,
			                                   UserPresence.status,
			                                   UserPresence.last_seen_at,
			                                   UserPresence.status_changed_at
			                            FROM user_presences AS UserPresence
			                            WHERE UserPresence.user_id = :user_id
			                            LIMIT 1");
			$stmt->execute(['user_id' => $userId]);

			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if (!is_array($row))
				{
				return $this->defaultPresence($userId);
				}

			return $this->withMeta($row);
			}
		catch (\Throwable $e)
			{
			error_log('UserPresenceService::current error: ' . $e->getMessage());
			return $this->defaultPresence($userId);
			}
		}

	/**
	 * Updates the selected status for a user.
	 * @param int $userId User identifier.
	 * @param int|null $tenantId Tenant identifier.
	 * @param string $status Requested status.
	 * @return array<string, mixed> Updated presence row with metadata.
	 **/
	public function setStatus(int $userId, ?int $tenantId, string $status): array
		{
		$status = $this->normalizeStatus($status);

		if ($status === self::STATUS_ONLINE && $tenantId !== null && $tenantId > 0)
			{
			$this->assertOnlineCapacity($tenantId, $userId);
			}

		$now = date('Y-m-d H:i:s');
		$stmt = $this->db->prepare("INSERT INTO user_presences (user_id, tenant_id, status, last_seen_at, status_changed_at, created, modified)
		                            VALUES (:user_id, :tenant_id, :status, :last_seen_at, :status_changed_at, :created, :modified)
		                            ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id),
		                                                    status = VALUES(status),
		                                                    last_seen_at = VALUES(last_seen_at),
		                                                    status_changed_at = VALUES(status_changed_at),
		                                                    modified = VALUES(modified)");
		$stmt->execute([
			'user_id' => $userId,
			'tenant_id' => $tenantId,
			'status' => $status,
			'last_seen_at' => $now,
			'status_changed_at' => $now,
			'created' => $now,
			'modified' => $now,
		]);

		return $this->current($userId);
		}

	/**
	 * Refreshes the last seen timestamp without changing the selected status.
	 * @param int $userId User identifier.
	 * @return void
	 **/
	public function touch(int $userId): void
		{
		$stmt = $this->db->prepare("UPDATE user_presences AS UserPresence
		                            SET UserPresence.last_seen_at = :last_seen_at,
		                                UserPresence.modified = :modified
		                            WHERE UserPresence.user_id = :user_id");
		$stmt->execute([
			'user_id' => $userId,
			'last_seen_at' => date('Y-m-d H:i:s'),
			'modified' => date('Y-m-d H:i:s'),
		]);
		}

	/**
	 * Marks a user as offline.
	 * @param int $userId User identifier.
	 * @return void
	 **/
	public function markOffline(int $userId): void
		{
		if ($userId <= 0)
			{
			return;
			}

		$this->setStatus($userId, null, self::STATUS_OFFLINE);
		}

	/**
	 * Ensures that the requested status is supported.
	 * @param string $status Requested status.
	 * @return string Normalized status.
	 **/
	private function normalizeStatus(string $status): string
		{
		$status = strtolower(trim($status));
		return array_key_exists($status, self::statuses()) ? $status : self::STATUS_OFFLINE;
		}

	/**
	 * Blocks a new online status when the tenant limit was reached.
	 * @param int $tenantId Tenant identifier.
	 * @param int $userId User identifier.
	 * @return void
	 **/
	private function assertOnlineCapacity(int $tenantId, int $userId): void
		{
		$limit = $this->settings->getInt($tenantId, 'max_online_users');
		$stmt = $this->db->prepare("SELECT COUNT(*) AS total
		                            FROM user_presences AS UserPresence
		                            WHERE UserPresence.tenant_id = :tenant_id
		                              AND UserPresence.status = :status
		                              AND UserPresence.user_id <> :user_id");
		$stmt->execute([
			'tenant_id' => $tenantId,
			'status' => self::STATUS_ONLINE,
			'user_id' => $userId,
		]);

		if ((int) $stmt->fetchColumn() >= $limit)
			{
			throw new RuntimeException('The tenant online user limit was reached.');
			}
		}

	/**
	 * Returns a default offline row for users without persisted presence.
	 * @param int $userId User identifier.
	 * @return array<string, mixed> Default presence row.
	 **/
	private function defaultPresence(int $userId): array
		{
		return $this->withMeta([
			'user_id' => $userId,
			'tenant_id' => null,
			'status' => self::STATUS_OFFLINE,
			'last_seen_at' => null,
			'status_changed_at' => null,
		]);
		}

	/**
	 * Adds display metadata to a presence row.
	 * @param array<string, mixed> $presence Presence row.
	 * @return array<string, mixed> Presence row with metadata.
	 **/
	private function withMeta(array $presence): array
		{
		$meta = self::statusMeta((string) ($presence['status'] ?? self::STATUS_OFFLINE));

		$presence['label'] = $meta['label'];
		$presence['class'] = $meta['class'];
		$presence['icon'] = $meta['icon'];

		return $presence;
		}
	}
?>
