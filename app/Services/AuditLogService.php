<?php

namespace App\Services;

use PDO;

class AuditLogService
	{
	private PDO $db;

	/**
	 * Creates the audit logger using the active write connection.
	 * @param PDO $db Active write connection.
	 **/
	public function __construct(PDO $db)
		{
		$this->db = $db;
		}

	/**
	 * Persists a create event in logs.
	 * @param string $module Source module name.
	 * @param string $entityName Main entity/table name.
	 * @param mixed $entityId Main entity identifier.
	 * @param mixed $afterData Snapshot after the write succeeds.
	 * @param array $context Additional context for the event.
	 **/
	public function logCreate(string $module, string $entityName, $entityId, $afterData, array $context = []): void
		{
		$this->logEvent($module, 'create', $entityName, $entityId, null, $afterData, $context);
		}

	/**
	 * Persists an update event in logs.
	 * @param string $module Source module name.
	 * @param string $entityName Main entity/table name.
	 * @param mixed $entityId Main entity identifier.
	 * @param mixed $beforeData Snapshot before the write.
	 * @param mixed $afterData Snapshot after the write.
	 * @param array $context Additional context for the event.
	 **/
	public function logUpdate(string $module, string $entityName, $entityId, $beforeData, $afterData, array $context = []): void
		{
		$this->logEvent($module, 'update', $entityName, $entityId, $beforeData, $afterData, $context);
		}

	/**
	 * Persists a delete event in logs.
	 * @param string $module Source module name.
	 * @param string $entityName Main entity/table name.
	 * @param mixed $entityId Main entity identifier.
	 * @param mixed $beforeData Snapshot before the delete.
	 * @param array $context Additional context for the event.
	 **/
	public function logDelete(string $module, string $entityName, $entityId, $beforeData, array $context = []): void
		{
		$this->logEvent($module, 'delete', $entityName, $entityId, $beforeData, null, $context);
		}

	/**
	 * Writes a normalized audit row to logs.
	 * @param string $module Source module name.
	 * @param string $action Event action.
	 * @param string $entityName Main entity/table name.
	 * @param mixed $entityId Main entity identifier.
	 * @param mixed $beforeData Snapshot before the change.
	 * @param mixed $afterData Snapshot after the change.
	 * @param array $context Additional context for the event.
	 **/
	private function logEvent(
		string $module,
		string $action,
		string $entityName,
		$entityId,
		$beforeData,
		$afterData,
		array $context = []
	): void
		{
		// User data comes from the central manager session.
		$user = $_SESSION['manager_main']['user'] ?? [];

		// The same payload is reused across the log columns and JSON sections.
		$beforePayload = $this->normalizeValue($beforeData);
		$afterPayload = $this->normalizeValue($afterData);
		$changedFields = $this->buildChangedFields($beforePayload, $afterPayload);
		$tenantPayload = $this->resolveTenantPayload($context, $beforePayload, $afterPayload);
		$contextPayload = $this->buildContextPayload($context);
		$now = date('Y-m-d H:i:s');

		$stmt = $this->db->prepare("
			INSERT INTO logs
				(event_uuid, tenant_id, tenant_database, tenant_name_snapshot, module, action, entity_name, entity_id,
				 performed_by_user_id, performed_by_username, performed_by_name, request_method, route, ip_address,
				 user_agent, occurred_at, before_data, after_data, changed_fields, context_json, created, modified)
			VALUES
				(:event_uuid, :tenant_id, :tenant_database, :tenant_name_snapshot, :module, :action, :entity_name, :entity_id,
				 :performed_by_user_id, :performed_by_username, :performed_by_name, :request_method, :route, :ip_address,
				 :user_agent, :occurred_at, :before_data, :after_data, :changed_fields, :context_json, :created, :modified)
		");

		$stmt->execute([
			'event_uuid' => $this->generateUuidV4(),
			'tenant_id' => $tenantPayload['tenant_id'],
			'tenant_database' => $tenantPayload['tenant_database'],
			'tenant_name_snapshot' => $tenantPayload['tenant_name_snapshot'],
			'module' => trim($module),
			'action' => $action,
			'entity_name' => trim($entityName),
			'entity_id' => $entityId !== null ? (string) $entityId : null,
			'performed_by_user_id' => !empty($user['id']) ? (int) $user['id'] : null,
			'performed_by_username' => $this->truncate((string) ($user['username'] ?? ''), 120),
			'performed_by_name' => $this->truncate((string) ($user['name'] ?? ''), 160),
			'request_method' => $this->truncate((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 10),
			'route' => $this->truncate($this->currentRoute(), 255),
			'ip_address' => $this->truncate((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 45),
			'user_agent' => $this->truncate((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 255),
			'occurred_at' => $now,
			'before_data' => $beforePayload === null ? null : $this->encodeJson($beforePayload),
			'after_data' => $afterPayload === null ? null : $this->encodeJson($afterPayload),
			'changed_fields' => $changedFields === null ? null : $this->encodeJson($changedFields),
			'context_json' => $contextPayload === [] ? null : $this->encodeJson($contextPayload),
			'created' => $now,
			'modified' => $now,
		]);
		}

	/**
	 * Resolves the tenant columns using explicit context or entity snapshots.
	 * @param array $context Additional context received from the caller.
	 * @param mixed $beforePayload Normalized before payload.
	 * @param mixed $afterPayload Normalized after payload.
	 * @return array
	 **/
	private function resolveTenantPayload(array $context, $beforePayload, $afterPayload): array
		{
		// Prefer an explicit tenant payload informed by the caller.
		$tenantId = isset($context['tenant_id']) ? (int) $context['tenant_id'] : null;
		$tenantDatabase = isset($context['tenant_database']) ? trim((string) $context['tenant_database']) : '';
		$tenantName = isset($context['tenant_name_snapshot']) ? trim((string) $context['tenant_name_snapshot']) : '';

		// Fallback to entity snapshots when the caller did not provide tenant columns.
		$snapshots = [$afterPayload, $beforePayload];
		foreach ($snapshots as $snapshot)
			{
			if (!is_array($snapshot))
				{
				continue;
				}

			if ($tenantId === null && isset($snapshot['tenant_id']) && $snapshot['tenant_id'] !== '')
				{
				$tenantId = (int) $snapshot['tenant_id'];
				}

			if ($tenantDatabase === '')
				{
				if (!empty($snapshot['tenant_database']))
					{
					$tenantDatabase = trim((string) $snapshot['tenant_database']);
					}
				elseif (!empty($snapshot['database_number']))
					{
					$tenantDatabase = 'manager_' . str_pad((string) $snapshot['database_number'], 4, '0', STR_PAD_LEFT);
					}
				}

			if ($tenantName === '')
				{
				if (!empty($snapshot['tenant_name_snapshot']))
					{
					$tenantName = trim((string) $snapshot['tenant_name_snapshot']);
					}
				elseif (!empty($snapshot['associated_person_name']))
					{
					$tenantName = trim((string) $snapshot['associated_person_name']);
					}
				elseif (!empty($snapshot['name']) && isset($snapshot['database_number']))
					{
					$tenantName = trim((string) $snapshot['name']);
					}
				}
			}

		return [
			'tenant_id' => $tenantId,
			'tenant_database' => $tenantDatabase !== '' ? $this->truncate($tenantDatabase, 120) : null,
			'tenant_name_snapshot' => $tenantName !== '' ? $this->truncate($tenantName, 160) : null,
		];
		}

	/**
	 * Adds request information to the provided context payload.
	 * @param array $context Additional context received from the caller.
	 * @return array
	 **/
	private function buildContextPayload(array $context): array
		{
		// Keep tenant column hints out of the generic JSON payload to avoid duplication.
		unset($context['tenant_id'], $context['tenant_database'], $context['tenant_name_snapshot']);

		$payload = $this->normalizeValue($context);

		if (!is_array($payload))
			{
			return [];
			}

		$payload['request_query'] = $this->normalizeValue($_GET ?? []);
		return $payload;
		}

	/**
	 * Builds the diff payload stored in changed_fields.
	 * @param mixed $beforeData Snapshot before the change.
	 * @param mixed $afterData Snapshot after the change.
	 * @return mixed
	 **/
	private function buildChangedFields($beforeData, $afterData)
		{
		if ($beforeData === $afterData)
			{
			return null;
			}

		if (is_array($beforeData) && is_array($afterData))
			{
			if (array_is_list($beforeData) || array_is_list($afterData))
				{
				return [
					'from' => $beforeData,
					'to' => $afterData,
				];
				}

			$changes = [];
			$keys = array_unique(array_merge(array_keys($beforeData), array_keys($afterData)));

			foreach ($keys as $key)
				{
				$childChange = $this->buildChangedFields($beforeData[$key] ?? null, $afterData[$key] ?? null);

				if ($childChange !== null)
					{
					$changes[$key] = $childChange;
					}
				}

			return $changes === [] ? null : $changes;
			}

		return [
			'from' => $beforeData,
			'to' => $afterData,
		];
		}

	/**
	 * Normalizes values before persisting them as JSON.
	 * @param mixed $value Raw value.
	 * @return mixed
	 **/
	private function normalizeValue($value)
		{
		if ($value === null || is_scalar($value))
			{
			return $value;
			}

		if ($value instanceof \DateTimeInterface)
			{
			return $value->format('Y-m-d H:i:s');
			}

		if (is_object($value))
			{
			if ($value instanceof \JsonSerializable)
				{
				return $this->normalizeValue($value->jsonSerialize());
				}

			return $this->normalizeValue((array) $value);
			}

		if (!is_array($value))
			{
			return (string) $value;
			}

		$normalized = [];

		foreach ($value as $key => $item)
			{
			$normalized[$key] = $this->normalizeValue($item);
			}

		return $normalized;
		}

	/**
	 * Encodes data using the project JSON options.
	 * @param mixed $value Payload to encode.
	 * @return string
	 **/
	private function encodeJson($value): string
		{
		return (string) json_encode(
			$value,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
		);
		}

	/**
	 * Returns the current application route path.
	 * @return string
	 **/
	private function currentRoute(): string
		{
		$route = trim((string) ($_GET['url'] ?? ''));
		return ($route !== '') ? $route : '/';
		}

	/**
	 * Generates a UUID v4 string.
	 * @return string
	 **/
	private function generateUuidV4(): string
		{
		$data = random_bytes(16);
		$data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
		$data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
		}

	/**
	 * Truncates strings to fit log column limits.
	 * @param string $value Raw value.
	 * @param int $length Maximum allowed length.
	 * @return string
	 **/
	private function truncate(string $value, int $length): string
		{
		$value = trim($value);
		if ($value === '')
			{
			return '';
			}

		if (function_exists('mb_substr'))
			{
			return mb_substr($value, 0, $length);
			}

		return substr($value, 0, $length);
		}
	}
?>
