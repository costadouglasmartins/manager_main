<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class TenantSettingService
	{
	private const DEFAULT_MAX_ONLINE_USERS = 1;

	/**
	 * Database connection used by the service.
	 * @var PDO
	 **/
	private PDO $db;

	/**
	 * Creates the service with the central database connection.
	 **/
	public function __construct()
		{
		$this->db = Database::getInstance();
		}

	/**
	 * Returns an integer setting value for the tenant.
	 * @param int $tenantId Tenant identifier.
	 * @param string $keyName Setting key name.
	 * @return int Resolved integer value.
	 **/
	public function getInt(int $tenantId, string $keyName): int
		{
		$value = $this->get($tenantId, $keyName);

		if ($value === null || trim((string) $value) === '')
			{
			return $this->fallbackInt($keyName);
			}

		return max(1, (int) $value);
		}

	/**
	 * Returns the tenant override or the catalog default for a setting.
	 * @param int $tenantId Tenant identifier.
	 * @param string $keyName Setting key name.
	 * @return string|null Resolved setting value.
	 **/
	public function get(int $tenantId, string $keyName): ?string
		{
		try
			{
			$stmt = $this->db->prepare("SELECT COALESCE(TenantSettingValue.value, SystemSettingDefinition.default_value) AS resolved_value
			                            FROM system_setting_definitions AS SystemSettingDefinition
			                            LEFT JOIN tenant_setting_values AS TenantSettingValue ON TenantSettingValue.setting_definition_id = SystemSettingDefinition.id
			                             AND TenantSettingValue.tenant_id = :tenant_id
			                            WHERE SystemSettingDefinition.key_name = :key_name
			                              AND SystemSettingDefinition.active = 1
			                            LIMIT 1");
			$stmt->execute([
				'tenant_id' => $tenantId,
				'key_name' => $keyName,
			]);

			$value = $stmt->fetchColumn();
			return $value === false ? null : (string) $value;
			}
		catch (\Throwable $e)
			{
			error_log('TenantSettingService::get error: ' . $e->getMessage());
			return null;
			}
		}

	/**
	 * Returns the hard-coded fallback for required settings.
	 * @param string $keyName Setting key name.
	 * @return int Fallback value.
	 **/
	private function fallbackInt(string $keyName): int
		{
		if ($keyName === 'max_online_users')
			{
			return self::DEFAULT_MAX_ONLINE_USERS;
			}

		return 1;
		}
	}
?>
