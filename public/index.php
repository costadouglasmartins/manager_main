<?php
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);

/**
 * Detecta protocolo (http/https), respeitando proxy/reverso se existir.
 */
$scheme = 'http';

if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) 
  {
  $scheme = 'https';
  } 
elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) 
  {
  // útil em proxy reverso / load balancer
  $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
  }

/**
 * Descobre o host (domínio + porta, se houver).
 */
$host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

/**
 * Diretório base da aplicação (ex.: /manager_main/public).
 */
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$basePath  = ($scriptDir === '/' ? '' : $scriptDir);

/**
 * BASE_URL ABSOLUTA, ex.:
 * http://localhost/manager_main/public/
 * https://meusistema.com/
 */
define('BASE_URL', $scheme . '://' . $host . $basePath . '/');

if (session_status() === PHP_SESSION_NONE) 
  {
  session_start();
  }

$sharedAssetsPath = getenv('SHARED_ASSETS_PATH') ?: dirname(ROOT_PATH) . '/manager_assets';
$composerAutoload = rtrim($sharedAssetsPath, '/\\') . '/vendor/autoload.php';

if (!is_file($composerAutoload))
  {
  throw new RuntimeException('Shared Composer dependencies were not installed.');
  }

// Reuse Composer's loader for application classes on case-sensitive filesystems.
$classLoader = require_once $composerAutoload;
$classLoader->addPsr4('App\\', ROOT_PATH . '/app');
	
require_once ROOT_PATH . '/app/Core/App.php';
require_once ROOT_PATH . '/app/Core/Controller.php';
require_once ROOT_PATH . '/app/Core/Config.php';
require_once ROOT_PATH . '/app/Core/Database.php';
require_once ROOT_PATH . '/app/Core/AuditsChanges.php';
require_once ROOT_PATH . '/app/Core/Model.php';
require_once ROOT_PATH . '/app/Helpers/Helper.php';
require_once ROOT_PATH . '/app/Helpers/FlashMessageHelper.php';
require_once ROOT_PATH . '/app/Helpers/PaginationHelper.php';
require_once ROOT_PATH . '/app/Helpers/SortingHelper.php';
require_once ROOT_PATH . '/app/Helpers/ExcelExportHelper.php';
require_once ROOT_PATH . '/app/Helpers/PdfExportHelper.php';

\App\Core\RememberLogin::attemptFromCookie();

new \App\Core\App();
