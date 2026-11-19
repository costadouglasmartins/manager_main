<?php

declare(strict_types=1);

/**
 * Run from the Docker directory with:
 * docker compose exec -T php php /var/www/html/manager_main/tests/system_setting_values_test.php
 *
 * Uses the configured MariaDB connection and session-local InnoDB temporary tables.
 * Temporary tables shadow the application tables; permanent data is never changed.
 **/
if (PHP_SAPI !== 'cli')
	{
	http_response_code(404);
	exit;
	}

require_once __DIR__ . '/../app/Core/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/AuditsChanges.php';
require_once __DIR__ . '/../app/Core/Model.php';
require_once __DIR__ . '/../app/Models/SystemSetting.php';
require_once __DIR__ . '/../app/Services/TenantSettingService.php';

use App\Core\Database;
use App\Models\SystemSetting;
use App\Services\TenantSettingService;

/**
 * Supplies local view links without bootstrapping an HTTP application.
 * @param string $path Application route.
 * @return string Local route URL.
 **/
function url(string $path): string
	{
	return '/' . $path;
	}

/**
 * Requires an expectation without relying on PHP assertion settings.
 * @param bool $condition Expected outcome.
 * @param string $message Failure description.
 * @return void
 **/
function expectSettingResult(bool $condition, string $message): void
	{
	if (!$condition)
		{
		throw new RuntimeException($message);
		}
	}

/**
 * Requires a submitted value to fail validation instead of being silently changed.
 * @param callable $operation Attempted save operation.
 * @param string $message Failure description.
 * @return void
 **/
function expectSettingRejected(callable $operation, string $message): void
	{
	/** Track only validation failures; database errors must still fail the test. **/
	$rejected = false;
	try
		{
		$operation();
		}
	catch (InvalidArgumentException $exception)
		{
		$rejected = true;
		expectSettingResult($exception->getMessage() !== '', 'Validation failures must have a readable message.');
		}

	expectSettingResult($rejected, $message);
	}

/** Reuse the exact connection used by the application model and setting resolver. **/
$db = Database::getInstance();

try
	{
	// Successful creation of all temporary tables precedes every fixture write.
	$db->exec('CREATE TEMPORARY TABLE system_setting_modules (
		id INT UNSIGNED NOT NULL PRIMARY KEY,
		slug VARCHAR(80) NOT NULL,
		name VARCHAR(120) NOT NULL,
		description VARCHAR(255) NULL,
		active TINYINT NOT NULL DEFAULT 1,
		sort_order INT NOT NULL DEFAULT 0
	) ENGINE=InnoDB');
	$db->exec('CREATE TEMPORARY TABLE system_setting_definitions (
		id INT UNSIGNED NOT NULL PRIMARY KEY,
		module_id INT UNSIGNED NOT NULL DEFAULT 1,
		key_name VARCHAR(120) NOT NULL UNIQUE,
		label VARCHAR(160) NOT NULL DEFAULT \'Test setting\',
		description VARCHAR(255) NULL,
		value_type VARCHAR(20) NOT NULL,
		default_value LONGTEXT NULL,
		validation_rules LONGTEXT NULL,
		active TINYINT NOT NULL DEFAULT 1,
		sort_order INT NOT NULL DEFAULT 0,
		modified DATETIME NOT NULL DEFAULT \'2000-01-01 00:00:00\'
	) ENGINE=InnoDB');
	$db->exec('CREATE TEMPORARY TABLE tenant_setting_values (
		tenant_id INT UNSIGNED NOT NULL,
		setting_definition_id INT UNSIGNED NOT NULL,
		value LONGTEXT NULL,
		created DATETIME NOT NULL,
		modified DATETIME NOT NULL,
		PRIMARY KEY (tenant_id, setting_definition_id)
	) ENGINE=InnoDB');
	$db->exec("INSERT INTO system_setting_modules (id, slug, name) VALUES (1, 'test', 'Test module')");

	/** Fixture metadata distinguishes the minimum-only user limit from an explicitly bounded setting. **/
	$insertDefinition = $db->prepare('INSERT INTO system_setting_definitions
		(id, key_name, value_type, default_value, validation_rules, active)
		VALUES (:id, :key_name, :value_type, :default_value, :validation_rules, :active)');
	$insertDefinition->execute([
		'id' => 1,
		'key_name' => 'max_online_users',
		'value_type' => 'integer',
		'default_value' => '1',
		'validation_rules' => '{"min":1,"max":1,"extra":"retained"}',
		'active' => 1,
	]);
	$insertDefinition->execute([
		'id' => 2,
		'key_name' => 'bounded_integer',
		'value_type' => 'integer',
		'default_value' => '2',
		'validation_rules' => '{"min":1,"max":1}',
		'active' => 1,
	]);

	/** Stale production metadata must work before the separate SQL migration runs. **/
	$model = new SystemSetting();
	/** Resolve the saved override through the actual user-limit consumer. **/
	$service = new TenantSettingService();
	$model->saveTenantValues(7, [1 => '2']);
	expectSettingResult(($model->tenantValues(7)[1] ?? null) === '2', 'Legacy user-limit metadata must allow saving a tenant override above one without migration.');
	expectSettingResult($service->getInt(7, 'max_online_users') === 2, 'The user-limit consumer must resolve an override saved before migration.');
	expectSettingRejected(
		static fn () => $model->saveTenantValues(7, [1 => '0']),
		'The legacy user-limit repair must preserve the minimum of one.'
	);
	expectSettingRejected(
		static fn () => $model->saveTenantValues(7, [2 => '2']),
		'Identical bounds on another setting key must still be enforced.'
	);
	/** Load the same grouped definitions supplied to the settings form. **/
	$legacyModules = $model->groupedDefinitions();
	/** Index definitions for independent legacy and bounded-setting expectations. **/
	$legacyDefinitions = array_column($legacyModules[0]['definitions'] ?? [], null, 'id');
	expectSettingResult(($legacyDefinitions[1]['validation_rules'] ?? null) === ['min' => 1, 'extra' => 'retained'], 'Grouped definitions must remove only the legacy user-limit maximum and preserve other rules.');
	expectSettingResult(($legacyDefinitions[2]['validation_rules'] ?? null) === ['min' => 1, 'max' => 1], 'Grouped definitions must preserve the bounds of another setting key.');

	/** Render the real form so the browser receives the same effective rules as saving. **/
	$renderView = static function (array $modules, array $values): string
		{
		/** Select the tenant whose override was saved above. **/
		$tenantId = 7;
		/** Supply the tenant selector without reading permanent tenant records. **/
		$tenants = [['id' => 7, 'database_number' => '0007']];
		ob_start();
		try
			{
			require __DIR__ . '/../app/Views/system_settings/index.php';
			return (string) ob_get_contents();
			}
		finally
			{
			ob_end_clean();
			}
		};
	/** Inspect the real view output with the unmigrated catalog rules. **/
	$html = $renderView($legacyModules, $model->tenantValues(7));
	expectSettingResult(preg_match('/<input\\b[^>]*\\bid="setting_1"[^>]*>/', $html, $legacyInput) === 1, 'The user-limit input must be rendered.');
	expectSettingResult(str_contains($legacyInput[0], 'min="1"') && !preg_match('/\\bmax\\s*=/', $legacyInput[0]), 'The legacy user-limit input must retain its minimum without a maximum.');
	expectSettingResult(str_contains($legacyInput[0], 'value="2"'), 'The form must display the saved tenant override above one.');
	expectSettingResult(preg_match('/<input\\b[^>]*\\bid="setting_2"[^>]*>/', $html, $boundedInput) === 1, 'The bounded-setting input must be rendered.');
	expectSettingResult(str_contains($boundedInput[0], 'min="1"') && str_contains($boundedInput[0], 'max="1"'), 'The form must preserve explicit bounds for another setting key.');
	expectSettingResult(json_decode((string) $db->query('SELECT validation_rules FROM system_setting_definitions WHERE id = 1')->fetchColumn(), true) === ['min' => 1, 'max' => 1, 'extra' => 'retained'], 'Reading definitions and saving overrides must not rewrite stored metadata.');

	/** Legacy JSON can store bounds as strings; preserve their other rule values. **/
	$db->exec('UPDATE system_setting_definitions SET validation_rules = \'{"min":"1","max":"1","extra":"retained"}\' WHERE id = 1');
	$model->saveTenantValues(7, [1 => '3']);
	expectSettingResult(($model->tenantValues(7)[1] ?? null) === '3', 'String-valued legacy bounds must also allow an override above one.');
	/** Check that string-valued bounds retain their original minimum and extra rules. **/
	$stringDefinitions = array_column($model->groupedDefinitions()[0]['definitions'] ?? [], null, 'id');
	expectSettingResult(($stringDefinitions[1]['validation_rules'] ?? null) === ['min' => '1', 'extra' => 'retained'], 'String-valued legacy bounds must preserve their minimum and other rules.');
	$db->exec('UPDATE system_setting_definitions SET validation_rules = \'{"min":1,"max":1,"extra":"retained"}\' WHERE id = 1');

	/** Execute the actual migration against the temporary catalog, including preservation cases. **/
	$migration = (string) file_get_contents(__DIR__ . '/../public/database/migrations/manager_main_max_online_users_validation.sql');
	expectSettingResult(trim($migration) !== '', 'The user-limit migration must be readable.');
	$db->exec($migration);
	/** Inspect persisted JSON after the legacy rule has been repaired. **/
	$repairedRules = json_decode((string) $db->query('SELECT validation_rules FROM system_setting_definitions WHERE id = 1')->fetchColumn(), true);
	expectSettingResult($repairedRules === ['min' => 1, 'extra' => 'retained'], 'The migration must remove the legacy maximum while preserving other rules.');
	expectSettingResult(json_decode((string) $db->query('SELECT validation_rules FROM system_setting_definitions WHERE id = 2')->fetchColumn(), true) === ['min' => 1, 'max' => 1], 'The migration must preserve identical bounds on another setting key.');

	$db->exec("UPDATE system_setting_definitions SET modified = '2001-01-01 00:00:00' WHERE id = 1");
	$db->exec($migration);
	expectSettingResult($db->query('SELECT modified FROM system_setting_definitions WHERE id = 1')->fetchColumn() === '2001-01-01 00:00:00', 'Rerunning the migration must not rewrite an already repaired definition.');

	$db->exec('UPDATE system_setting_definitions SET validation_rules = \'{"min":1,"max":20}\' WHERE id = 1');
	$db->exec($migration);
	expectSettingResult(json_decode((string) $db->query('SELECT validation_rules FROM system_setting_definitions WHERE id = 1')->fetchColumn(), true) === ['min' => 1, 'max' => 20], 'The migration must preserve an explicitly configured higher maximum.');
	$model->saveTenantValues(7, [1 => '20']);
	expectSettingRejected(
		static fn () => $model->saveTenantValues(7, [1 => '21']),
		'A deliberately configured higher user-limit maximum must still be enforced.'
	);
	expectSettingResult(($model->tenantValues(7)[1] ?? null) === '20', 'Rejecting a value above a custom user-limit maximum must preserve the last save.');
	/** Inspect the effective rules after configuring a deliberate higher maximum. **/
	$boundedDefinitions = array_column($model->groupedDefinitions()[0]['definitions'] ?? [], null, 'id');
	expectSettingResult(($boundedDefinitions[1]['validation_rules'] ?? null) === ['min' => 1, 'max' => 20], 'Grouped definitions must preserve an explicitly configured higher user-limit maximum.');
	$db->exec('UPDATE system_setting_definitions SET validation_rules = \'{"min":1}\' WHERE id = 1');
	$db->exec('UPDATE system_setting_definitions SET validation_rules = \'{"min":1,"max":5}\' WHERE id = 2');

	/** Exercise the public save/read APIs and the consumer after the migration. **/
	expectSettingResult($model->tenantValues(4) === [], 'A tenant with no overrides must start empty.');
	expectSettingResult($service->getInt(4, 'max_online_users') === 1, 'A tenant without an override must use the catalog default.');

	$model->saveTenantValues(4, [1 => '4']);
	expectSettingResult(($model->tenantValues(4)[1] ?? null) === '4', 'Saving a user limit above one must persist the submitted value.');
	expectSettingResult($service->getInt(4, 'max_online_users') === 4, 'The user-limit consumer must see the saved override.');
	expectSettingResult($service->getInt(5, 'max_online_users') === 1, 'Another tenant must still use the default.');

	$model->saveTenantValues(4, [1 => '7']);
	expectSettingResult(($model->tenantValues(4)[1] ?? null) === '7', 'Saving an existing override must update its previous value.');
	expectSettingResult($service->getInt(4, 'max_online_users') === 7, 'The consumer must see an updated override without stale values.');
	expectSettingResult((int) $db->query('SELECT COUNT(*) FROM tenant_setting_values WHERE tenant_id = 4 AND setting_definition_id = 1')->fetchColumn() === 1, 'Updating an override must not create duplicate tenant values.');

	$model->saveTenantValues(5, [1 => '3']);
	expectSettingResult(($model->tenantValues(5)[1] ?? null) === '3', 'A second tenant must save its own override.');
	expectSettingResult($service->getInt(5, 'max_online_users') === 3, 'The second tenant must resolve its own user limit.');
	expectSettingResult($service->getInt(4, 'max_online_users') === 7, 'Saving another tenant must preserve the first tenant override.');

	$model->saveTenantValues(4, [2 => '1']);
	expectSettingResult(($model->tenantValues(4)[2] ?? null) === '1', 'An explicit minimum must be inclusive.');
	$model->saveTenantValues(4, [2 => '5']);
	expectSettingResult(($model->tenantValues(4)[2] ?? null) === '5', 'An explicit maximum must be inclusive.');
	$model->saveTenantValues(4, [2 => '2']);

	// Validation must reject both out-of-range and non-integer values, preserving the last save.
	foreach (['0', '-1', '6', '2.5', 'invalid', '', null, []] as $invalidValue)
		{
		expectSettingRejected(
			static fn () => $model->saveTenantValues(4, [2 => $invalidValue]),
			'An invalid integer or a value outside explicit bounds must be rejected.'
		);
		expectSettingResult(($model->tenantValues(4)[2] ?? null) === '2', 'A rejected value must preserve the stored override.');
		expectSettingResult(!$db->inTransaction(), 'A rejected save must close its transaction.');
		}

	// The first value is valid and is written before the second value fails validation.
	expectSettingRejected(
		static fn () => $model->saveTenantValues(4, [1 => '9', 2 => '6']),
		'A batch containing an out-of-range value must fail as a whole.'
	);
	expectSettingResult(($model->tenantValues(4)[1] ?? null) === '7', 'A later validation failure must roll back an earlier update in the same save.');
	expectSettingResult(($model->tenantValues(4)[2] ?? null) === '2', 'A failed batch must preserve all previously stored values.');
	expectSettingResult($service->getInt(4, 'max_online_users') === 7, 'The consumer must never observe a partially saved batch.');
	expectSettingResult($service->getInt(5, 'max_online_users') === 3, 'A failed batch must preserve another tenant override.');
	expectSettingResult(!$db->inTransaction(), 'A failed batch must close its transaction.');

	$model->saveTenantValues(6, [1 => '12']);
	expectSettingResult($service->getInt(6, 'max_online_users') === 12, 'A valid save must still succeed after a rollback.');
	}
finally
	{
	if ($db->inTransaction())
		{
		$db->rollBack();
		}
	// TEMPORARY is mandatory here so cleanup can never drop a permanent application table.
	$db->exec('DROP TEMPORARY TABLE IF EXISTS tenant_setting_values');
	$db->exec('DROP TEMPORARY TABLE IF EXISTS system_setting_definitions');
	$db->exec('DROP TEMPORARY TABLE IF EXISTS system_setting_modules');
	}

echo 'OK: tenant settings persist, update, remain isolated, validate bounds and roll back atomically.' . PHP_EOL;
