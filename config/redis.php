<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Redis Client
    |--------------------------------------------------------------------------
    |
    | Client được sử dụng để kết nối với Redis.
    | Hỗ trợ: "phpredis", "predis"
    |
    */

    'client' => env('REDIS_CLIENT', 'phpredis'),

    /*
    |--------------------------------------------------------------------------
    | Redis Connections
    |--------------------------------------------------------------------------
    |
    | Tại đây bạn có thể cấu hình các kết nối đến Redis server.
    |
    */

    'connections' => [

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],

        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
        ],

    ],

    'options' => [
        'timeout' => 1.0,
    ],

];
