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

    /** Prefer APP_ENV; otherwise detect Docker without relying on the request domain. **/
    $environment = strtolower(trim($readEnvironment('APP_ENV', '')));
    if ($environment === '') {
        $environment = is_file('/.dockerenv') ? 'docker' : 'production';
    }

    /** Keep compatibility with the existing Docker Compose environment. **/
    if ($environment === 'development') {
        $environment = 'docker';
    }

    if (!in_array($environment, ['production', 'docker'], true)) {
        throw new RuntimeException('APP_ENV must be production or docker (development is an alias for docker).');
    }

    define('APP_ENV', $environment);

    /** Keep production and Docker database credentials and WebSocket settings separate. **/
    $environmentDefaults = [
        'production' => [
            'DB_HOST' => '127.0.0.1',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => 'SUA_SENHA',
            'SUPPORT_CHAT_WS_HOST' => '127.0.0.1',
        ],
        'docker' => [
            'DB_HOST' => 'mariadb',
            'DB_USERNAME' => 'manager_app',
            'DB_PASSWORD' => 'SUA_SENHA_EM_FORMA_DE_HASH',
            'SUPPORT_CHAT_WS_HOST' => '127.0.0.1',
        ],
    ];

    /** Shared defaults; every setting can be overridden by its environment variable. **/
    $settings = array_merge([
        'DB_PORT' => '3306',
        'DB_DATABASE_MANAGER' => 'manager_main',
        'DB_CHARSET' => 'utf8mb4',
        'SUPPORT_CHAT_SECRET' => 'SEU_CHAT_SECRET',
        'SUPPORT_CHAT_WS_PORT' => '8081',
    ], $environmentDefaults[$environment]);

    foreach ($settings as $name => $default) {
        /** Preserve the WebSocket port's integer type used by the chat server. **/
        $value = $readEnvironment($name, $default);
        define($name, $name === 'SUPPORT_CHAT_WS_PORT' ? (int) $value : $value);
    }
})();
