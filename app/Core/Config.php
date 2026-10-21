<?php

/**
 * Load environment-specific settings without relying on the request domain.
 * Keep this configuration in sync with manager/app/Core/Config.php.
 **/
(static function (): void {
    /** Read environment overrides while preserving empty strings and zero values. **/
    $readEnvironment = static function (string $name, string $default): string {
        /** Read settings shared by PHP-FPM and CLI processes. **/
        $value = getenv($name);

        return $value === false ? $default : $value;
    };

    /** Prefer an explicit environment; otherwise treat Docker as development. **/
    $environment = strtolower(trim($readEnvironment('APP_ENV', '')));
    if ($environment === '') {
        $environment = is_file('/.dockerenv') ? 'development' : 'production';
    }

    if (!in_array($environment, ['development', 'production'], true)) {
        throw new RuntimeException('APP_ENV must be development or production.');
    }

    define('APP_ENV', $environment);

    /** Database defaults for the local Docker stack and the standalone VPS. **/
    $databaseDefaults = [
        'development' => [
            'DB_HOST' => 'mysql',
            'DB_PASSWORD' => 'rootpassword',
        ],
        'production' => [
            'DB_HOST' => '127.0.0.1',
            'DB_PASSWORD' => 'IzzI695500.-',
        ],
    ];

    /** Shared defaults; every setting can be overridden by its environment variable. **/
    $settings = array_merge([
        'DB_PORT' => '3306',
        'DB_DATABASE_MANAGER' => 'manager_main',
        'DB_USERNAME' => 'root',
        'DB_CHARSET' => 'utf8mb4',
        'SUPPORT_CHAT_SECRET' => '9323d5e71fe951602b452cc683ff7c49f8d5cec8',
        'SUPPORT_CHAT_WS_HOST' => '0.0.0.0',
        'SUPPORT_CHAT_WS_PORT' => '8081',
    ], $databaseDefaults[$environment]);

    foreach ($settings as $name => $default) {
        /** Preserve the WebSocket port's integer type used by the chat server. **/
        $value = $readEnvironment($name, $default);
        define($name, $name === 'SUPPORT_CHAT_WS_PORT' ? (int) $value : $value);
    }
})();
