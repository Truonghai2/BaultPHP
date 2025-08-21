<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    |
    */

    'connections' => [

        'mysql' => [
            'driver' => 'mysql',
            // Use a read/write split to distribute database load.
            // In development, the read host will safely fall back to the main DB_HOST.
            'read' => [
                // Use the read replica host if defined, otherwise fall back to the main write host.
                'host' => env('DB_READ_HOST', env('DB_HOST')),
            ],
            'write' => [
                'host' => env('DB_HOST', '127.0.0.1'),
            ],
            // This option, if supported by the framework, ensures that after a write
            // operation, subsequent read operations in the same request cycle
            // use the write connection to avoid issues with replication lag.
            'sticky'    => true,
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'bault'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    */

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
    ],

];
