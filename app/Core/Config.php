<?php

/**
 * Shared database configuration for this application.
 */
define('DB_HOST', 'mysql');
define('DB_PORT', '3306');
define('DB_DATABASE_MANAGER', 'manager_main');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'rootpassword');
define('DB_CHARSET', 'utf8mb4');

/**
 * Shared secret used to sign and validate support chat tokens.
 */
define('SUPPORT_CHAT_SECRET', '9323d5e71fe951602b452cc683ff7c49f8d5cec8');
define('SUPPORT_CHAT_WS_HOST', '0.0.0.0');
define('SUPPORT_CHAT_WS_PORT', 8081);
