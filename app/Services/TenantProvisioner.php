<?php

namespace App\Services;

use PDO;

/**
 * TenantProvisioner - Creates tenant databases.
 *
 * Database naming: manager_0001, manager_0002, ...
 * Same user and password for all tenants.
 */
class TenantProvisioner
	{

	private $dbHost     = 'mysql';
	private $dbUser     = 'root';
	private $dbPassword = 'rootpassword';
	private $dbCharset  = 'utf8';
	private $dbPrefix   = 'manager_';

	/**
	 * Provision method
	 * @param string $databaseNumber Formatted number (e.g.: '0001')
	 * @return bool
	 */
	public function provision(string $databaseNumber): bool
		{
		$dbName = $this->dbPrefix . $databaseNumber;
		$dsn = sprintf('mysql:host=%s;charset=%s', $this->dbHost, $this->dbCharset);

		try
			{
			$pdo = $this->createConnection($dsn);

			$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb3");
			$pdo->exec("USE `{$dbName}`");

			$templateFile = ROOT_PATH . '/public/database/tenant_template.sql';
			if (!file_exists($templateFile))
				{
				throw new \RuntimeException('Template file not found: ' . $templateFile);
				}

			$sql = file_get_contents($templateFile);
			if ($sql === false)
				{
				throw new \RuntimeException('Unable to read template file: ' . $templateFile);
				}

			if (trim($sql) === '')
				{
				throw new \RuntimeException('Template file is empty: ' . $templateFile);
				}

			$pdo->exec($sql);

			return true;
			}
		catch (\Throwable $e)
			{
			error_log(sprintf('TenantProvisioner::provision error for %s: %s', $dbName, $e->getMessage()));
			return false;
			}
		}

	/**
	 * Database exists method
	 * @param string $databaseNumber Formatted number (e.g.: '0001')
	 * @return bool
	 */
	public function databaseExists(string $databaseNumber): bool
		{
		$dbName = $this->dbPrefix . $databaseNumber;
		$dsn = sprintf('mysql:host=%s;charset=%s', $this->dbHost, $this->dbCharset);

		try
			{
			$pdo = $this->createConnection($dsn);

			$stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :dbname");
			$stmt->execute(['dbname' => $dbName]);

			return $stmt->fetchColumn() !== false;
			}
		catch (\Throwable $e)
			{
			return false;
			}
		}

	private function createConnection(string $dsn): PDO
		{
		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		];

		if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS'))
			{
			$options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
			}

		return new PDO($dsn, $this->dbUser, $this->dbPassword, $options);
		}
	}

?>
