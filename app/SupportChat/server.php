<?php

declare(strict_types=1);

use App\SupportChat\SupportChatServer;

$rootPath = dirname(__DIR__, 2);

define('ROOT_PATH', $rootPath);
define('DB_HOST', getenv('DB_HOST') ?: 'mysql');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_DATABASE_MANAGER', getenv('DB_DATABASE_MANAGER') ?: 'manager_main');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'rootpassword');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

$timezone = getenv('SUPPORT_CHAT_TIMEZONE') ?: getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo';
if (!@date_default_timezone_set((string) $timezone))
	{
	date_default_timezone_set('America/Sao_Paulo');
	}
define('SUPPORT_CHAT_TIMEZONE', date_default_timezone_get());

require_once ROOT_PATH . '/app/Core/Database.php';

if (PHP_SAPI !== 'cli')
	{
	http_response_code(403);
	header('Content-Type: text/plain; charset=utf-8');
	echo "Este arquivo inicia o servidor WebSocket e deve ser executado no terminal.\n";
	echo "Use: php manager_main/app/SupportChat/server.php\n";
	exit;
	}

spl_autoload_register(function ($class) use ($rootPath) {
	$class = str_replace('\\', '/', $class);
	$file = $rootPath . '/' . preg_replace('/^App\//', 'app/', $class) . '.php';
	if (file_exists($file))
		{
		require_once $file;
		}
});

$host = getenv('SUPPORT_CHAT_WS_HOST') ?: '127.0.0.1';
$port = (int) (getenv('SUPPORT_CHAT_WS_PORT') ?: 8081);
$secret = (string) (getenv('SUPPORT_CHAT_SECRET') ?: '9323d5e71fe951602b452cc683ff7c49f8d5cec8');
$db = method_exists(\App\Core\Database::class, 'getManagerConnection')
	? \App\Core\Database::getManagerConnection()
	: \App\Core\Database::getInstance();

$server = new SupportChatServer((string) $host, $port, $secret, $db);
$server->run();
