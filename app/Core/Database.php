<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
	{
	/** @var array<string, PDO> */
	private static array $instances = [];

	/**
	 * Return the shared connection for the manager database.
	 * @param bool $allowMultiStatements Whether the connection may execute multiple statements.
	 * @return PDO Manager database connection.
	 **/
	public static function getManagerConnection(bool $allowMultiStatements = false): PDO
		{
		return self::getConnection(
			self::validateDatabaseName(self::getManagerDatabaseName()),
			$allowMultiStatements
		);
		}

	/**
	 * Return the shared connection for a tenant database.
	 * @param string $databaseName Tenant database name.
	 * @param bool $allowMultiStatements Whether the connection may execute multiple statements.
	 * @return PDO Tenant database connection.
	 **/
	public static function getTenantConnection(string $databaseName, bool $allowMultiStatements = false): PDO
		{
		return self::getConnection(
			self::validateDatabaseName($databaseName),
			$allowMultiStatements
		);
		}

	/**
	 * Return a server-level connection without selecting a database.
	 * @param bool $allowMultiStatements Whether the connection may execute multiple statements.
	 * @return PDO Server-level database connection.
	 **/
	public static function getServerConnection(bool $allowMultiStatements = false): PDO
		{
		return self::getConnection(null, $allowMultiStatements);
		}

	/**
	 * Preserve the existing manager connection API.
	 * @return PDO Manager database connection.
	 **/
	public static function getInstance(): PDO
		{
		return self::getManagerConnection();
		}

	/**
	 * Return the configured manager database name.
	 * @return string Manager database name.
	 **/
	public static function getManagerDatabaseName(): string
		{
		return DB_DATABASE_MANAGER;
		}

	/**
	 * Create or reuse a configured connection.
	 * @param string|null $databaseName Database name or null for a server connection.
	 * @param bool $allowMultiStatements Whether the connection may execute multiple statements.
	 * @return PDO Configured database connection.
	 **/
	private static function getConnection(?string $databaseName, bool $allowMultiStatements): PDO
		{
		$connectionKey = sprintf(
			'%s:%s',
			$databaseName ?? '__server__',
			$allowMultiStatements ? 'multi' : 'single'
		);

		if (isset(self::$instances[$connectionKey]))
			{
			return self::$instances[$connectionKey];
			}

		$dsn = sprintf(
			'mysql:host=%s;port=%s;%scharset=%s',
			DB_HOST,
			DB_PORT,
			$databaseName === null ? '' : 'dbname=' . $databaseName . ';',
			DB_CHARSET
		);
		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		];

		if ($allowMultiStatements && defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS'))
			{
			$options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
			}

		try
			{
			self::$instances[$connectionKey] = new PDO(
				$dsn,
				DB_USERNAME,
				DB_PASSWORD,
				$options
			);
			}
		catch (PDOException $e)
			{
			error_log(sprintf(
				'Database connection failed for "%s": %s',
				$databaseName ?? 'database server',
				$e->getMessage()
			));
			throw new PDOException('Não foi possível conectar ao banco de dados.');
			}

		return self::$instances[$connectionKey];
		}

	/**
	 * Validate a database identifier before including it in a DSN.
	 * @param string $databaseName Database name to validate.
	 * @return string Validated database name.
	 **/
	private static function validateDatabaseName(string $databaseName): string
		{
		$databaseName = trim($databaseName);

		if ($databaseName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $databaseName))
			{
			throw new \InvalidArgumentException('Nome de banco de dados inválido.');
			}

		return $databaseName;
		}
	}

?>
