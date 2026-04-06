<?php

namespace App\Services;

use PDO;
use PDOException;

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
			$pdo = new PDO($dsn, $this->dbUser, $this->dbPassword);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb3");
			$pdo->exec("USE `{$dbName}`");

			$templateFile = ROOT_PATH . '/public/database/tenant_template.sql';
			if (file_exists($templateFile))
				{
				$sql = file_get_contents($templateFile);
				if ($sql !== false && trim($sql) !== '')
					{
					$pdo->exec($sql);
					}
				}

			return true;
			}
		catch (PDOException $e)
			{
			error_log('TenantProvisioner::provision error: ' . $e->getMessage());
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
			$pdo = new PDO($dsn, $this->dbUser, $this->dbPassword);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			$stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :dbname");
			$stmt->execute(['dbname' => $dbName]);

			return $stmt->fetchColumn() !== false;
			}
		catch (PDOException $e)
			{
			return false;
			}
		}
	}

?>
