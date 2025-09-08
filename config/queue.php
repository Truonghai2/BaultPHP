<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | BaultPHP's queue API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for every one. Here you may define a default connection.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with BaultPHP. You are free to add more.
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],
        'swoole' => [
            'driver' => 'swoole',
            'queue' => 'default',
        ],
        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'delayed_queue' => env('REDIS_DELAYED_QUEUE', 'queues:delayed:default'),
            'retry_after' => 90,
        ],

        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => (int) env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
            'queue' => env('RABBITMQ_QUEUE', 'default'),

            'options' => [
                'exchange' => [
                    'name' => env('RABBITMQ_EXCHANGE_NAME', 'default_exchange'),
                    'type' => env('RABBITMQ_EXCHANGE_TYPE', 'direct'),
                    'passive' => false,
                    'durable' => true,
                    'auto_delete' => false,
                ],
            ],
        ],
    ],

    'scheduler' => [
        'check_interval' => 1000,
    ],

    'failed' => [
        'driver' => 'database',
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];
