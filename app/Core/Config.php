<?php

/**
 * Read an environment override without discarding empty strings or zero.
 * @param string $name Environment variable name.
 * @param string|int $default Default value when the variable is not set.
 * @return string|int Environment override or default value.
 **/
$readConfigValue = static function (string $name, string|int $default): string|int {
    /** Read the value provided to PHP-FPM or the CLI process. */
    $value = getenv($name);

    return $value === false ? $default : $value;
};

/**
 * Prefer an explicit environment and otherwise detect the Docker container.
 **/
$appEnvironment = trim((string) getenv('APP_ENV'));
define('APP_ENV', $appEnvironment !== '' ? $appEnvironment : (is_file('/.dockerenv') ? 'development' : 'production'));

/**
 * Select database and WebSocket defaults for Docker or the production VPS.
 * Docker supplies its database password through the DB_PASSWORD variable.
 **/
if (APP_ENV === 'development') {
    define('DB_HOST', $readConfigValue('DB_HOST', '127.0.0.1'));
    define('DB_USERNAME', $readConfigValue('DB_USERNAME', 'manager_app'));
    define('DB_PASSWORD', $readConfigValue('DB_PASSWORD', 'a655cfd8889990aabaabbcda9a58afb581a9318c0524b72c0e5e8c8fdf4613b4'));
    define('SUPPORT_CHAT_WS_HOST', $readConfigValue('SUPPORT_CHAT_WS_HOST', '0.0.0.0'));
} else {
    define('DB_HOST', $readConfigValue('DB_HOST', '127.0.0.1'));
    define('DB_USERNAME', $readConfigValue('DB_USERNAME', 'root'));
    define('DB_PASSWORD', $readConfigValue('DB_PASSWORD', 'IzzI695500.-'));
    define('SUPPORT_CHAT_WS_HOST', $readConfigValue('SUPPORT_CHAT_WS_HOST', '127.0.0.1'));
}

/**
 * Share the remaining defaults across both environments.
 **/
define('DB_PORT', $readConfigValue('DB_PORT', '3306'));
define('DB_DATABASE_MANAGER', $readConfigValue('DB_DATABASE_MANAGER', 'manager_main'));
define('DB_CHARSET', $readConfigValue('DB_CHARSET', 'utf8mb4'));
define('SUPPORT_CHAT_SECRET', $readConfigValue('SUPPORT_CHAT_SECRET', '9323d5e71fe951602b452cc683ff7c49f8d5cec8'));
define('SUPPORT_CHAT_WS_PORT', (int) $readConfigValue('SUPPORT_CHAT_WS_PORT', 8081));

unset($readConfigValue, $appEnvironment);
