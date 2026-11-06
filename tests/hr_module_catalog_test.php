<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Services/TenantProvisioner.php';

use App\Services\TenantProvisioner;

/**
 * Stops the test run with a readable failure message.
 * @param string $message Failure description.
 * @return never
 **/
function failTest(string $message): never
	{
	fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
	exit(1);
	}

/**
 * Requires a condition without relying on PHP assertion settings.
 * @param bool $condition Expected outcome.
 * @param string $message Failure description.
 **/
function expectTrue(bool $condition, string $message): void
	{
	if (!$condition)
		{
		failTest($message);
		}
	}

// Read the private deletion catalog without opening a database connection.
$modules = (new ReflectionClassConstant(TenantProvisioner::class, 'DATABASE_MODULES'))->getValue();
$cataloguedTables = [];
foreach ($modules as $tables)
	{
	$cataloguedTables = array_merge($cataloguedTables, array_keys($tables));
	}

// Every table created by the tenant template must appear in the deletion manifest catalog.
$template = (string) file_get_contents(__DIR__ . '/../public/database/tenant_template.sql');
preg_match_all('/CREATE TABLE IF NOT EXISTS `([a-z0-9_]+)`/i', $template, $matches);
$templateTables = $matches[1];
$missing = array_diff($templateTables, $cataloguedTables);
expectTrue($missing === [], 'Tables missing from DATABASE_MODULES: ' . implode(', ', $missing));

// Catalog entries must point to real template tables (a typo would silently hide rows from the manifest).
$unknown = array_diff($cataloguedTables, $templateTables);
expectTrue($unknown === [], 'DATABASE_MODULES lists tables absent from the template: ' . implode(', ', $unknown));

// The HR module lists the child table before the catalogs it references.
expectTrue(isset($modules['RH']), 'DATABASE_MODULES has no RH module.');
expectTrue(
	array_keys($modules['RH']) === ['hr_employees', 'hr_positions', 'hr_departments'],
	'RH module must list hr_employees (child) before hr_positions and hr_departments.'
);

// Template: parents are created before the child that references them, and the schema version is registered.
$positions = array_map(static fn (string $table): int => (int) array_search($table, $templateTables, true), ['hr_departments', 'hr_positions', 'hr_employees']);
expectTrue($positions[0] < $positions[2] && $positions[1] < $positions[2], 'hr_employees must be created after hr_departments and hr_positions.');
expectTrue(str_contains($template, "('0.0.9'"), 'The template must register schema version 0.0.9.');

// Project rule: nothing in the central app may remove data of hr_departments.
$scanRoots = [__DIR__ . '/../app', __DIR__ . '/../public/database'];
$forbidden = '/(delete\s+from|truncate(\s+table)?|drop\s+table(\s+if\s+exists)?)\s+`?(manager_\d{4}`?\.`?)?hr_departments\b/i';
foreach ($scanRoots as $root)
	{
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file)
		{
		if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'sql'], true))
			{
			continue;
			}

		expectTrue(
			preg_match($forbidden, (string) file_get_contents($file->getPathname())) !== 1,
			'Destructive statement on hr_departments found in ' . $file->getPathname()
		);
		}
	}

// User deletion removes the HR row before the person (hr_employees.person_id is RESTRICT) and never touches the catalogs.
$userDeletion = (string) file_get_contents(__DIR__ . '/../app/Services/UserDeletionService.php');
$hrDeletePosition = strpos($userDeletion, "'hr_employees', \$databasePlan['hr_employee_ids']");
$personDeletePosition = strpos($userDeletion, "'DELETE FROM people WHERE id = :id'");
expectTrue($hrDeletePosition !== false, 'UserDeletionService does not delete hr_employees rows.');
expectTrue($personDeletePosition !== false && $hrDeletePosition < $personDeletePosition, 'hr_employees must be deleted before people.');
expectTrue(!str_contains($userDeletion, 'hr_departments') && !str_contains($userDeletion, 'hr_positions'), 'UserDeletionService must not touch the HR catalogs.');

echo 'OK: HR module is catalogued, ordered and protected.' . PHP_EOL;
