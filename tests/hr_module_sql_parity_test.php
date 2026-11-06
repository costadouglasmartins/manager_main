<?php

declare(strict_types=1);

/**
 * Stops the test run with a readable failure message.
 * @param string $message Failure description.
 * @return never
 **/
function failSqlTest(string $message): never
	{
	fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
	exit(1);
	}

/**
 * Requires a condition without relying on PHP assertion settings.
 * @param bool $condition Expected outcome.
 * @param string $message Failure description.
 **/
function expectSql(bool $condition, string $message): void
	{
	if (!$condition)
		{
		failSqlTest($message);
		}
	}

/**
 * Extracts the hr_* DDL and seed statements of an SQL file, normalized for comparison.
 * @param string $sql Full SQL file content.
 * @return array<string, string> Normalized statements keyed by "ddl:<table>" or "seed:<table>".
 **/
function extractHrStatements(string $sql): array
	{
	$statements = [];

	// Table definitions.
	preg_match_all('/CREATE TABLE IF NOT EXISTS `(hr_[a-z_]+)`.*?;\s*(?=\n|$)/is', $sql, $ddl, PREG_SET_ORDER);
	foreach ($ddl as $match)
		{
		$statements['ddl:' . $match[1]] = $match[0];
		}

	// Seeds of the catalogs.
	preg_match_all('/INSERT INTO `(hr_[a-z_]+)`.*?;/is', $sql, $seeds, PREG_SET_ORDER);
	foreach ($seeds as $match)
		{
		$statements['seed:' . $match[1]] = $match[0];
		}

	// Collapse whitespace and drop the NOW() noise so formatting differences do not matter.
	return array_map(static fn (string $statement): string => trim((string) preg_replace('/\s+/', ' ', $statement)), $statements);
	}

$base = __DIR__ . '/../public/database';
$template = extractHrStatements((string) file_get_contents($base . '/tenant_template.sql'));
$migration = extractHrStatements((string) file_get_contents($base . '/migrations/0.0.9_hr_module.sql'));

// Both files must define the three tables and seed the two catalogs.
$expectedKeys = ['ddl:hr_departments', 'ddl:hr_positions', 'ddl:hr_employees', 'seed:hr_departments', 'seed:hr_positions'];
foreach ($expectedKeys as $key)
	{
	expectSql(isset($template[$key]), 'tenant_template.sql is missing ' . $key);
	expectSql(isset($migration[$key]), '0.0.9_hr_module.sql is missing ' . $key);
	expectSql($template[$key] === $migration[$key], 'tenant_template.sql and 0.0.9_hr_module.sql differ on ' . $key);
	}

// Seeds must not overwrite what tenants edited when reapplied, and must never delete anything.
foreach (['seed:hr_departments', 'seed:hr_positions'] as $key)
	{
	expectSql(str_contains($template[$key], 'ON DUPLICATE KEY UPDATE `id` = `id`'), $key . ' must be idempotent without overwriting (ON DUPLICATE KEY UPDATE `id` = `id`).');
	expectSql(!str_contains($template[$key], 'VALUES(`description`)'), $key . ' must not overwrite descriptions on reapply.');
	}

// Project rule: no SQL or PHP file of manager_main may remove data of hr_departments.
$forbidden = '/(delete\s+from|truncate(\s+table)?|drop\s+table(\s+if\s+exists)?)\s+`?(manager_\d{4}`?\.`?)?hr_departments\b/i';
$projectRoot = realpath(__DIR__ . '/..');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file)
	{
	$path = $file->getPathname();
	if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'sql'], true) || str_contains($path, '/vendor/') || realpath($path) === __FILE__)
		{
		continue;
		}

	expectSql(preg_match($forbidden, (string) file_get_contents($path)) !== 1, 'Destructive statement on hr_departments found in ' . $path);
	}

echo 'OK: HR template and migration are in parity and hr_departments is never removed.' . PHP_EOL;
