<?php

declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'CondoConecta',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'url' => rtrim((string) (getenv('APP_URL') ?: ''), '/'),
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_DATABASE') ?: '',
        'username' => getenv('DB_USERNAME') ?: '',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
    'mail' => [
        'driver' => getenv('MAIL_DRIVER') ?: 'mail',
        'from_address' => getenv('MAIL_FROM_ADDRESS') ?: '',
        'from_name' => getenv('MAIL_FROM_NAME') ?: (getenv('APP_NAME') ?: 'CondoConecta'),
    ],
];
