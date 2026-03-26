<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Performance & Optimization Configuration
    |--------------------------------------------------------------------------
    |
    | Consolidated optimization configuration including:
    | - OPcache & JIT optimization
    | - Request batching & coalescing
    | - Database optimization
    | - CQRS read model optimization
    | - Advanced caching strategies
    | - Request deduplication
    |
    */

    /*
    |--------------------------------------------------------------------------
    | OPcache Configuration
    |--------------------------------------------------------------------------
    |
    | OPcache preloading and optimization settings.
    |
    */
    'opcache' => [
        'enabled' => env('OPCACHE_ENABLED', function_exists('opcache_get_status')),
        'preload_enabled' => env('OPCACHE_PRELOAD_ENABLED', false),
        'preload_file' => env('OPCACHE_PRELOAD_FILE', base_path('bootstrap/cache/preload.php')),
        'validate_timestamps' => env('OPCACHE_VALIDATE_TIMESTAMPS', !env('APP_ENV') === 'production'),
    ],

    /*
    |--------------------------------------------------------------------------
    | JIT Optimization Configuration
    |--------------------------------------------------------------------------
    |
    | Just-In-Time compilation optimization with profile-based approach.
    |
    */
    'jit' => [
        'enabled' => env('JIT_OPTIMIZATION_ENABLED', false),
        'min_access_count' => env('JIT_MIN_ACCESS_COUNT', 100),
        'hot_path_threshold' => env('JIT_HOT_PATH_THRESHOLD', 50),
        'memory_threshold' => env('JIT_MEMORY_THRESHOLD', 80), // percent
        'min_hit_rate' => env('JIT_MIN_HIT_RATE', 90), // percent
        'preload_limit' => env('JIT_PRELOAD_LIMIT', 100),
        'stale_threshold' => env('JIT_STALE_THRESHOLD', 3600), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Batching Configuration
    |--------------------------------------------------------------------------
    |
    | Batch and coalesce similar requests for better performance.
    |
    */
    'request_batching' => [
        'enabled' => env('REQUEST_BATCHING_ENABLED', false),
        'parallel' => env('REQUEST_BATCHING_PARALLEL', true),
        'timeout' => env('REQUEST_BATCHING_TIMEOUT', 30), // seconds
        'max_concurrency' => env('REQUEST_BATCHING_MAX_CONCURRENCY', 10),
        'coalesce_enabled' => env('REQUEST_BATCHING_COALESCE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Deduplication Configuration
    |--------------------------------------------------------------------------
    |
    | Prevent duplicate concurrent requests.
    |
    */
    'deduplication' => [
        'enabled' => env('REQUEST_DEDUPLICATION_ENABLED', false),
        'ttl' => env('REQUEST_DEDUPLICATION_TTL', 10), // seconds
        'cache_driver' => env('REQUEST_DEDUPLICATION_CACHE_DRIVER', 'redis'),
        'ignored_paths' => [
            '/health',
            '/metrics',
            '/api/v1/events', // SSE endpoints
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Optimization Configuration
    |--------------------------------------------------------------------------
    |
    | Database connection pooling, query optimization, and monitoring.
    |
    */
    'database' => [
        // Metrics
        'metrics' => [
            'enabled' => env('DB_METRICS_ENABLED', true),
            'slow_query_threshold_ms' => env('DB_SLOW_QUERY_THRESHOLD', 1000),
            'export_to_prometheus' => env('DB_METRICS_PROMETHEUS', true),
        ],

        // Adaptive Pool Manager
        'adaptive_pool' => [
            'enabled' => env('DB_ADAPTIVE_POOL_ENABLED', false),
            'min_pool_size' => env('DB_ADAPTIVE_POOL_MIN', 5),
            'max_pool_size' => env('DB_ADAPTIVE_POOL_MAX', 50),
            'initial_pool_size' => env('DB_ADAPTIVE_POOL_INITIAL', 10),
            'target_utilization' => env('DB_ADAPTIVE_POOL_TARGET', 0.75),
            'scale_up_threshold' => env('DB_ADAPTIVE_POOL_SCALE_UP', 0.85),
            'scale_down_threshold' => env('DB_ADAPTIVE_POOL_SCALE_DOWN', 0.30),
            'check_interval' => env('DB_ADAPTIVE_POOL_CHECK_INTERVAL', 30), // seconds
            'scale_up_amount' => env('DB_ADAPTIVE_POOL_SCALE_UP_AMOUNT', 5),
            'scale_down_amount' => env('DB_ADAPTIVE_POOL_SCALE_DOWN_AMOUNT', 3),
        ],

        // Connection Leak Detection
        'leak_detection' => [
            'enabled' => env('DB_LEAK_DETECTION_ENABLED', true),
            'leak_threshold' => env('DB_LEAK_THRESHOLD', 60), // seconds
            'warning_threshold' => env('DB_LEAK_WARNING_THRESHOLD', 30), // seconds
            'check_interval' => env('DB_LEAK_CHECK_INTERVAL', 60), // seconds
        ],

        // Query Timeouts
        'timeouts' => [
            'query_timeout' => env('DB_QUERY_TIMEOUT', 30), // seconds
            'connection_timeout' => env('DB_CONNECTION_TIMEOUT', 5), // seconds
            'read_timeout' => env('DB_READ_TIMEOUT', 30), // seconds
            'write_timeout' => env('DB_WRITE_TIMEOUT', 30), // seconds
        ],

        // Connection Idle Management
        'idle_management' => [
            'enabled' => env('DB_IDLE_MANAGEMENT_ENABLED', true),
            'max_idle_time' => env('DB_MAX_IDLE_TIME', 3600), // 1 hour
            'cleanup_interval' => env('DB_IDLE_CLEANUP_INTERVAL', 300), // 5 minutes
        ],

        // Prepared Statement Cache
        'prepared_statement_cache' => [
            'enabled' => env('DB_PREPARED_CACHE_ENABLED', true),
            'max_size' => env('DB_PREPARED_CACHE_SIZE', 1000),
            'ttl' => env('DB_PREPARED_CACHE_TTL', 3600), // seconds
        ],

        // Query Result Cache
        'query_result_cache' => [
            'enabled' => env('DB_QUERY_CACHE_ENABLED', false),
            'default_ttl' => env('DB_QUERY_CACHE_TTL', 300), // 5 minutes
            'cache_driver' => env('DB_QUERY_CACHE_DRIVER', 'redis'),
            'cache_prefix' => env('DB_QUERY_CACHE_PREFIX', 'db_query_'),
        ],

        // Read Replica Load Balancing
        'read_replicas' => [
            'enabled' => env('DB_READ_REPLICAS_ENABLED', false),
            'strategy' => env('DB_READ_REPLICA_STRATEGY', 'round_robin'), // round_robin, random, weighted
            'health_check_interval' => env('DB_READ_REPLICA_HEALTH_CHECK', 60), // seconds
            'retry_on_failure' => env('DB_READ_REPLICA_RETRY', true),
            'max_retries' => env('DB_READ_REPLICA_MAX_RETRIES', 2),
        ],

        // Connection Warmup
        'warmup' => [
            'enabled' => env('DB_WARMUP_ENABLED', true),
            'warmup_percentage' => env('DB_WARMUP_PERCENTAGE', 50), // % of pool size
            'max_concurrent_warmup' => env('DB_WARMUP_MAX_CONCURRENT', 5),
            'warmup_timeout' => env('DB_WARMUP_TIMEOUT', 10), // seconds
        ],

        // Database Sharding
        'sharding' => [
            'enabled' => env('DB_SHARDING_ENABLED', false),
            'strategy' => env('DB_SHARDING_STRATEGY', 'range'), // range, hash, consistent_hash
            'shard_key' => env('DB_SHARDING_KEY', 'id'),
            'shards' => [],
        ],

        // Performance Optimization
        'optimization' => [
            'persistent_connections' => env('DB_PERSISTENT_CONNECTIONS', false),
            'emulate_prepares' => env('DB_EMULATE_PREPARES', false),
            'buffer_results' => env('DB_BUFFER_RESULTS', true),
            'compression' => env('DB_COMPRESSION', false),
        ],

        // Debug & Troubleshooting
        'debug' => [
            'log_queries' => env('DB_LOG_QUERIES', false),
            'log_slow_queries_only' => env('DB_LOG_SLOW_QUERIES_ONLY', true),
            'log_connection_events' => env('DB_LOG_CONNECTION_EVENTS', false),
            'log_pool_stats_interval' => env('DB_LOG_POOL_STATS_INTERVAL', 300), // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Read Model Optimization (CQRS)
    |--------------------------------------------------------------------------
    |
    | Optimization for read models including denormalization and materialized views.
    |
    */
    'read_models' => [
        'enabled' => env('READ_MODEL_OPTIMIZATION_ENABLED', false),
        'auto_denormalize' => env('READ_MODEL_AUTO_DENORMALIZE', true),

        // Materialized Views
        'materialized_views' => [
            'enabled' => env('READ_MODEL_MATERIALIZED_VIEWS', true),
            'refresh_interval' => env('READ_MODEL_REFRESH_INTERVAL', 3600), // seconds
        ],

        // Index Optimization
        'index_optimization' => [
            'enabled' => env('READ_MODEL_INDEX_OPTIMIZATION', true),
            'analyze_queries' => env('READ_MODEL_ANALYZE_QUERIES', true),
        ],

        // Projections Configuration
        'projections' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Multi-tier caching, AI predictive caching, and CRDT distributed caching.
    |
    */
    'cache' => [
        // Multi-Tier Cache (L1/L2/L3)
        'multi_tier' => [
            'enabled' => env('CACHE_MULTI_TIER_ENABLED', false),
            
            'l1' => [
                'enabled' => env('CACHE_L1_ENABLED', function_exists('apcu_fetch')),
                'driver' => 'apcu', // apcu, array
                'default_ttl' => env('CACHE_L1_TTL', 60), // 1 minute
                'ttl_ratio' => env('CACHE_L1_TTL_RATIO', 0.1), // 10% of L2 TTL
                'max_size' => env('CACHE_L1_MAX_SIZE', 64 * 1024 * 1024), // 64MB
            ],

            'l2' => [
                'enabled' => true, // Always enabled (Redis)
                'driver' => env('CACHE_DRIVER', 'redis'),
                'default_ttl' => env('CACHE_L2_TTL', 3600), // 1 hour
            ],

            'l3' => [
                'enabled' => env('CACHE_L3_ENABLED', false),
                'driver' => 'file', // file, database
                'default_ttl' => env('CACHE_L3_TTL', 86400), // 24 hours
                'persist_all' => env('CACHE_L3_PERSIST_ALL', false),
            ],
        ],

        // AI Predictive Cache
        'predictive' => [
            'enabled' => env('CACHE_PREDICTIVE_ENABLED', false),
            'pattern_window' => env('CACHE_PREDICTIVE_WINDOW', 100),
            'confidence_threshold' => env('CACHE_PREDICTIVE_CONFIDENCE', 0.7),
            'preload_enabled' => env('CACHE_PREDICTIVE_PRELOAD', true),
            'preload_limit' => env('CACHE_PREDICTIVE_PRELOAD_LIMIT', 10),
        ],

        // CRDT Distributed Cache
        'crdt' => [
            'enabled' => env('CACHE_CRDT_ENABLED', false),
            'node_id' => env('CACHE_CRDT_NODE_ID', null),
            'replicas' => env('CACHE_CRDT_REPLICAS', ''),
            'conflict_resolution' => env('CACHE_CRDT_CONFLICT_RESOLUTION', 'lww'),
            'replication_timeout' => env('CACHE_CRDT_REPLICATION_TIMEOUT', 5),
            'replication_channel' => env('CACHE_CRDT_REPLICATION_CHANNEL', 'crdt:replication'),
        ],

        // Edge Cache
        'edge' => [
            'enabled' => env('CACHE_EDGE_ENABLED', false),
            'provider' => env('CACHE_EDGE_PROVIDER', 'cloudflare'),
            'ttl' => env('CACHE_EDGE_TTL', 3600),
            'purge_on_update' => env('CACHE_EDGE_PURGE_ON_UPDATE', true),
        ],
    ],
];
