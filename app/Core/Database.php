<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
	{
	/**
	 * Private static instance
	 * @var Static
	 */
	private static $instance = null;

	/**
	 * Get instance method
	 * @return instance
	 */
	public static function getInstance()
		{
		if (self::$instance === null)
			{
			try
				{
				self::$instance = new PDO('mysql:host=mysql;dbname=manager_main;charset=utf8', 'root', 'rootpassword');
				self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				}
			catch (PDOException $e)
				{
				die('Erro de conexão: ' . $e->getMessage());
				}
			}
		return self::$instance;
		}
	}

?>