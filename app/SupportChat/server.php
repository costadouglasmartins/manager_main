<?php

declare(strict_types=1);

use App\SupportChat\SupportChatServer;

$rootPath = dirname(__DIR__, 2);

define('ROOT_PATH', $rootPath);

$timezone = getenv('SUPPORT_CHAT_TIMEZONE') ?: getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo';
if (!@date_default_timezone_set((string) $timezone))
	{
	date_default_timezone_set('America/Sao_Paulo');
	}
define('SUPPORT_CHAT_TIMEZONE', date_default_timezone_get());

if (PHP_SAPI !== 'cli')
	{
	http_response_code(403);
	header('Content-Type: text/plain; charset=utf-8');
	echo "Este arquivo inicia o servidor WebSocket e deve ser executado no terminal.\n";
	echo "Use: php manager_main/app/SupportChat/server.php\n";
	exit;
	}

require_once ROOT_PATH . '/app/Core/Config.php';
require_once ROOT_PATH . '/app/Core/Database.php';

spl_autoload_register(function ($class) use ($rootPath) {
	$class = str_replace('\\', '/', $class);
	$file = $rootPath . '/' . preg_replace('/^App\//', 'app/', $class) . '.php';
	if (file_exists($file))
		{
		require_once $file;
		}
});

$db = \App\Core\Database::getManagerConnection();

$server = new SupportChatServer(
	SUPPORT_CHAT_WS_HOST,
	SUPPORT_CHAT_WS_PORT,
	SUPPORT_CHAT_SECRET,
	$db
);
$server->run();
