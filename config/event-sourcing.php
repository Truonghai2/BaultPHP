<?php

/**
 * Event Sourcing Global Configuration
 *
 * Core settings for event sourcing infrastructure.
 * Each module can override these in their own config files.
 *
 * Module configs: Modules/{ModuleName}/config/event-sourcing.php
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Global Enable/Disable
    |--------------------------------------------------------------------------
    |
    | Master switch for entire event sourcing system.
    | Set to false to disable event sourcing globally.
    |
    */
    'enabled' => env('EVENT_SOURCING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Dual Write Mode (Default for all modules)
    |--------------------------------------------------------------------------
    |
    | When true, writes to both traditional DB and event store.
    | Modules can override this in their own config.
    |
    */
    'dual_write' => env('EVENT_SOURCING_DUAL_WRITE', true),

    /*
    |--------------------------------------------------------------------------
    | Auto Record (Default for all modules)
    |--------------------------------------------------------------------------
    |
    | Automatically record events when Eloquent models change.
    | Modules can override this in their own config.
    |
    */
    'auto_record' => env('EVENT_SOURCING_AUTO_RECORD', true),

    /*
    |--------------------------------------------------------------------------
    | Module Discovery
    |--------------------------------------------------------------------------
    |
    | How to discover and load module-specific configurations.
    |
    */
    'module_discovery' => [
        // Auto-discover modules with event-sourcing.php config
        'auto_discover' => true,

        // Module config file name
        'config_filename' => 'event-sourcing.php',

        // Paths to search for modules
        'module_paths' => [
            base_path('Modules'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Store Infrastructure
    |--------------------------------------------------------------------------
    |
    | Core infrastructure settings (shared across all modules)
    |
    */
    'event_store' => [
        'connection' => env('EVENT_STORE_CONNECTION', 'pgsql'),
        'tables' => [
            'events' => 'events',
            'aggregates' => 'aggregates',
            'snapshots' => 'snapshots',
            'projections' => 'projections',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Snapshot Configuration (Default)
    |--------------------------------------------------------------------------
    |
    | Default snapshot settings. Modules can override.
    |
    */
    'snapshots' => [
        'enabled' => env('EVENT_SOURCING_SNAPSHOTS_ENABLED', false),
        'frequency' => env('EVENT_SOURCING_SNAPSHOT_FREQUENCY', 100),
        'storage' => 'database', // database, redis, file
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Publishing (Default)
    |--------------------------------------------------------------------------
    |
    | Publish events to message queue for async processing.
    | Modules can override.
    |
    */
    'publish_events' => [
        'enabled' => env('EVENT_SOURCING_PUBLISH_ENABLED', false),
        'queue' => env('EVENT_SOURCING_QUEUE', 'events'),
        'connection' => env('EVENT_SOURCING_QUEUE_CONNECTION', 'rabbitmq'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Projection Configuration (Default)
    |--------------------------------------------------------------------------
    */
    'projections' => [
        'enabled' => env('EVENT_SOURCING_PROJECTIONS_ENABLED', false),
        'auto_rebuild' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit & Logging
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => env('EVENT_SOURCING_AUDIT_ENABLED', true),
        'log_channel' => env('EVENT_SOURCING_LOG_CHANNEL', 'event_sourcing'),
        'log_level' => env('EVENT_SOURCING_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance & Optimization
    |--------------------------------------------------------------------------
    */
    'performance' => [
        // Cache aggregate lookups
        'cache_aggregates' => env('EVENT_SOURCING_CACHE_AGGREGATES', false),
        'cache_ttl' => env('EVENT_SOURCING_CACHE_TTL', 3600), // seconds

        // Batch event processing
        'batch_size' => env('EVENT_SOURCING_BATCH_SIZE', 100),

        // Async event recording
        'async_recording' => env('EVENT_SOURCING_ASYNC', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development & Debug
    |--------------------------------------------------------------------------
    */
    'debug' => [
        'enabled' => env('EVENT_SOURCING_DEBUG', env('APP_DEBUG', false)),
        'log_queries' => false,
        'track_performance' => false,
    ],
];

