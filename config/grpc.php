<?php

return [
    /*
    |--------------------------------------------------------------------------
    | gRPC Server Configuration
    |--------------------------------------------------------------------------
    |
    | Enable/disable gRPC server and configure connection settings.
    |
    */

    'enabled' => env('GRPC_ENABLED', false),

    'host' => env('GRPC_HOST', '0.0.0.0'),

    'port' => env('GRPC_PORT', 50051),

    /*
    |--------------------------------------------------------------------------
    | Server Options
    |--------------------------------------------------------------------------
    |
    | Swoole server configuration for gRPC.
    |
    */

    'options' => [
        'worker_num' => env('GRPC_WORKERS', 4),
        'enable_coroutine' => true,
        'open_http2_protocol' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Configure authentication for gRPC services.
    |
    */

    'auth' => [
        'enabled' => env('GRPC_AUTH_ENABLED', true),
        'token_type' => 'jwt', // jwt, api_key
    ],

    /*
    |--------------------------------------------------------------------------
    | Client Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for gRPC clients.
    |
    */

    'client' => [
        'timeout' => 10000000, // 10 seconds (microseconds)
        'secure' => env('GRPC_SECURE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Discovery
    |--------------------------------------------------------------------------
    |
    | Configure service discovery for distributed systems.
    |
    */

    'services' => [
        'todo' => env('GRPC_TODO_SERVICE', 'localhost:50051'),
        'user' => env('GRPC_USER_SERVICE', 'localhost:50051'),
    ],
];
