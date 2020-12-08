<?php

return [
    'default' => [
        'type' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'test'),
        'username' => env('DB_USER', 'root'),
        'password' => env('DB_PASS', ''),
        'prefix' => env('DB_PREFIX', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        'pool' => [
            'max_connection' => 100,
            'max_idle_time' => 60
        ]
    ]
];
