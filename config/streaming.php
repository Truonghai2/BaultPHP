<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NATS JetStream Configuration
    |--------------------------------------------------------------------------
    |
    | Configure NATS server connection and JetStream settings.
    |
    */

    'enabled' => env('NATS_ENABLED', true),

    'connection' => [
        'host' => env('NATS_HOST', 'localhost'),
        'port' => env('NATS_PORT', 4222),
        'user' => env('NATS_USER'),
        'password' => env('NATS_PASSWORD'),
        'timeout' => env('NATS_TIMEOUT', 1),
        'reconnect' => env('NATS_RECONNECT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Streams Configuration
    |--------------------------------------------------------------------------
    |
    | Configure JetStream streams for different event types.
    |
    */

    'streams' => [
        'events' => [
            'name' => 'EVENTS',
            'subjects' => ['events.>'],
            'retention' => 'limits', // limits, interest, workqueue
            'max_age' => 7 * 24 * 3600, // 7 days in seconds
            'max_msgs' => 1_000_000,
            'max_bytes' => 1024 * 1024 * 1024, // 1GB
            'storage' => 'file', // file, memory
        ],

        'commands' => [
            'name' => 'COMMANDS',
            'subjects' => ['commands.>'],
            'retention' => 'workqueue',
            'max_age' => 24 * 3600, // 1 day
            'max_msgs' => 100_000,
            'storage' => 'file',
        ],

        'integration' => [
            'name' => 'INTEGRATION',
            'subjects' => ['integration.>'],
            'retention' => 'limits',
            'max_age' => 30 * 24 * 3600, // 30 days
            'max_msgs' => 10_000_000,
            'storage' => 'file',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Consumers Configuration
    |--------------------------------------------------------------------------
    */

    'consumers' => [
        'projections' => [
            'stream' => 'EVENTS',
            'durable_name' => 'projections-consumer',
            'subjects' => ['events.>'],
            'ack_policy' => 'explicit',
            'max_deliver' => 3,
            'ack_wait' => 30, // seconds
            'batch_size' => 100,
            'poll_interval' => 100, // milliseconds
        ],

        'notifications' => [
            'stream' => 'EVENTS',
            'durable_name' => 'notifications-consumer',
            'subjects' => ['events.user.>', 'events.todo.>'],
            'ack_policy' => 'explicit',
            'max_deliver' => 5,
            'ack_wait' => 60,
            'batch_size' => 50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Serialization
    |--------------------------------------------------------------------------
    |
    | Choose serialization format: protobuf (fast, compact) or json (readable).
    |
    */

    'serializer' => env('NATS_SERIALIZER', 'protobuf'), // protobuf, json

    /*
    |--------------------------------------------------------------------------
    | Compression
    |--------------------------------------------------------------------------
    |
    | Enable compression for large events (reduces network bandwidth).
    |
    */

    'compression' => [
        'enabled' => env('NATS_COMPRESSION', true),
        'algorithm' => 'gzip', // gzip, zstd
        'level' => 6, // 1-9 for gzip
        'threshold' => 1024, // Compress if > 1KB
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        'enabled' => env('NATS_MONITORING', true),
        'endpoint' => env('NATS_MONITORING_ENDPOINT', 'http://localhost:8222'),
    ],
];
