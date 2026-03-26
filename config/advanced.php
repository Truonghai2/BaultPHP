<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Advanced Technologies Configuration
    |--------------------------------------------------------------------------
    |
    | Consolidated advanced features configuration including:
    | - Advanced database technologies (Vector, TimeSeries, Graph)
    | - Edge computing (Cloudflare, Fastly)
    | - WebAssembly (WASM) runtime
    | - GraphQL advanced features
    | - gRPC communication
    | - Modern PHP 8.3+ features
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Vector Database Configuration
    |--------------------------------------------------------------------------
    |
    | Vector embeddings storage for AI/ML applications.
    |
    */
    'vector_db' => [
        'enabled' => env('VECTOR_DB_ENABLED', false),
        'driver' => env('VECTOR_DB_DRIVER', 'pgvector'), // pinecone, weaviate, qdrant, pgvector

        'pinecone' => [
            'api_key' => env('PINECONE_API_KEY'),
            'environment' => env('PINECONE_ENVIRONMENT', 'us-east1-gcp'),
        ],

        'weaviate' => [
            'url' => env('WEAVIATE_URL', 'http://localhost:8080'),
            'api_key' => env('WEAVIATE_API_KEY'),
        ],

        'qdrant' => [
            'url' => env('QDRANT_URL', 'http://localhost:6333'),
            'api_key' => env('QDRANT_API_KEY'),
        ],

        'pgvector' => [
            'default_dimension' => env('VECTOR_DB_DIMENSION', 1536), // OpenAI embedding dimension
            'index_type' => env('VECTOR_DB_INDEX_TYPE', 'ivfflat'), // ivfflat, hnsw
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Time-Series Database Configuration
    |--------------------------------------------------------------------------
    |
    | Optimized storage for time-series metrics and logs.
    |
    */
    'timeseries_db' => [
        'enabled' => env('TIMESERIES_DB_ENABLED', false),
        'driver' => env('TIMESERIES_DB_DRIVER', 'timescaledb'), // influxdb, timescaledb

        'influxdb' => [
            'url' => env('INFLUXDB_URL', 'http://localhost:8086'),
            'token' => env('INFLUXDB_TOKEN'),
            'org' => env('INFLUXDB_ORG', 'my-org'),
            'bucket' => env('INFLUXDB_BUCKET', 'metrics'),
        ],

        'timescaledb' => [
            'chunk_interval' => env('TIMESCALEDB_CHUNK_INTERVAL', '1 day'),
            'compression' => env('TIMESCALEDB_COMPRESSION', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Graph Database Configuration
    |--------------------------------------------------------------------------
    |
    | Graph-based relationships and queries.
    |
    */
    'graph_db' => [
        'enabled' => env('GRAPH_DB_ENABLED', false),
        'driver' => env('GRAPH_DB_DRIVER', 'neo4j'), // neo4j, arangodb

        'neo4j' => [
            'uri' => env('NEO4J_URI', 'bolt://localhost:7687'),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'password'),
        ],

        'arangodb' => [
            'url' => env('ARANGODB_URL', 'http://localhost:8529'),
            'username' => env('ARANGODB_USERNAME', 'root'),
            'password' => env('ARANGODB_PASSWORD', ''),
            'database' => env('ARANGODB_DATABASE', '_system'),
            'edge_collection' => env('ARANGODB_EDGE_COLLECTION', 'edges'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Edge Computing Configuration
    |--------------------------------------------------------------------------
    |
    | Deploy code to edge locations for lower latency.
    |
    */
    'edge_computing' => [
        'enabled' => env('EDGE_FUNCTIONS_ENABLED', false),
        'default_provider' => env('EDGE_DEFAULT_PROVIDER', 'cloudflare'),
        'default_runtime' => env('EDGE_DEFAULT_RUNTIME', 'javascript'), // javascript, wasm, php-wasm

        // Cloudflare Workers
        'cloudflare' => [
            'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
            'api_token' => env('CLOUDFLARE_API_TOKEN'),
            'workers_dev' => env('CLOUDFLARE_WORKERS_DEV', true),
            'route' => env('CLOUDFLARE_ROUTE', null),
        ],

        // Fastly Compute@Edge
        'fastly' => [
            'api_token' => env('FASTLY_API_TOKEN'),
            'service_id' => env('FASTLY_SERVICE_ID'),
            'service_url' => env('FASTLY_SERVICE_URL', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WebAssembly (WASM) Configuration
    |--------------------------------------------------------------------------
    |
    | Execute WebAssembly modules for high-performance computations.
    |
    */
    'wasm' => [
        'enabled' => env('WASM_ENABLED', false),
        'runtime' => env('WASM_RUNTIME', 'wasmtime'), // wasmtime, wasmer, wavm, php-ext
        'runtime_path' => env('WASM_RUNTIME_PATH', null), // Auto-detect if null
        'wasm_directory' => env('WASM_DIRECTORY', base_path('wasm')),
        
        // Cache Configuration
        'cache_enabled' => env('WASM_CACHE_ENABLED', true),
        'cache_ttl' => env('WASM_CACHE_TTL', 3600),
        
        // Fallback to PHP if WASM unavailable
        'fallback_to_php' => env('WASM_FALLBACK_TO_PHP', true),
        
        // Registered WASM Modules
        'modules' => [
            'image_processor' => base_path('wasm/image_processor.wasm'),
            'calculator' => base_path('wasm/calculator.wasm'),
            'fft' => base_path('wasm/fft.wasm'),
            'matrix' => base_path('wasm/matrix.wasm'),
            'statistics' => base_path('wasm/statistics.wasm'),
        ],
        
        // PHP Fallback Classes
        'fallbacks' => [
            'image_processor.wasm' => \Core\WebAssembly\Fallbacks\ImageProcessorFallback::class,
            'calculator.wasm' => \Core\WebAssembly\Fallbacks\CalculatorFallback::class,
        ],
        
        // Image Processing
        'image' => [
            'default_quality' => 90,
            'default_format' => 'jpeg',
            'preserve_aspect' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | GraphQL Advanced Configuration
    |--------------------------------------------------------------------------
    |
    | Advanced GraphQL features beyond basic setup.
    |
    */
    'graphql' => [
        'enabled' => env('GRAPHQL_ENABLED', false),
        
        // Query Complexity Analysis
        'complexity' => [
            'enabled' => env('GRAPHQL_COMPLEXITY_ENABLED', true),
            'max_complexity' => env('GRAPHQL_MAX_COMPLEXITY', 1000),
            'max_depth' => env('GRAPHQL_MAX_DEPTH', 10),
        ],
        
        // DataLoader for N+1 query optimization
        'dataloader' => [
            'enabled' => env('GRAPHQL_DATALOADER_ENABLED', true),
            'batch_size' => env('GRAPHQL_DATALOADER_BATCH_SIZE', 100),
        ],
        
        // Persistent Queries
        'persistent_queries' => [
            'enabled' => env('GRAPHQL_PERSISTENT_QUERIES', false),
            'cache_driver' => env('GRAPHQL_PERSISTENT_CACHE', 'redis'),
        ],
        
        // Subscriptions (Real-time)
        'subscriptions' => [
            'enabled' => env('GRAPHQL_SUBSCRIPTIONS_ENABLED', false),
            'transport' => env('GRAPHQL_SUBSCRIPTIONS_TRANSPORT', 'websocket'), // websocket, sse
        ],
        
        // Federation (Microservices)
        'federation' => [
            'enabled' => env('GRAPHQL_FEDERATION_ENABLED', false),
            'services' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | gRPC Configuration
    |--------------------------------------------------------------------------
    |
    | High-performance RPC framework using Protocol Buffers.
    |
    */
    'grpc' => [
        'enabled' => env('GRPC_ENABLED', false),
        
        // Client Configuration
        'client' => [
            'timeout' => env('GRPC_CLIENT_TIMEOUT', 30), // seconds
            'retry' => env('GRPC_CLIENT_RETRY', false),
            'services' => [],
        ],
        
        // Server Configuration
        'server' => [
            'host' => env('GRPC_SERVER_HOST', '0.0.0.0'),
            'port' => env('GRPC_SERVER_PORT', 50051),
            'secure' => env('GRPC_SERVER_SECURE', false),
            'cert' => env('GRPC_SERVER_CERT', null),
            'key' => env('GRPC_SERVER_KEY', null),
            'services' => [],
        ],
        
        // Protocol Buffers
        'protobuf' => [
            'protoc_path' => env('PROTOC_PATH', null), // Auto-detect if null
            'grpc_plugin_path' => env('GRPC_PHP_PLUGIN_PATH', null),
            'proto_directory' => env('PROTO_DIRECTORY', base_path('proto')),
            'generated_directory' => env('GRPC_GENERATED_DIRECTORY', base_path('src/Grpc/Generated')),
            'import_paths' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Modern PHP Features (PHP 8.3+)
    |--------------------------------------------------------------------------
    |
    | Enable modern PHP language features and enhancements.
    |
    */
    'modern_php' => [
        'enabled' => env('MODERN_PHP_ENABLED', true),
        
        // PHP 8.3+ Features
        'php83_features' => [
            'enabled' => env('PHP83_FEATURES_ENABLED', true),
            'readonly_classes' => env('PHP83_READONLY_CLASSES', true),
            'typed_constants' => env('PHP83_TYPED_CONSTANTS', true),
            'override_attribute' => env('PHP83_OVERRIDE_ATTRIBUTE', true),
            'dynamic_constants' => env('PHP83_DYNAMIC_CONSTANTS', true),
        ],
        
        // Attributes Enhancement
        'attributes' => [
            'enabled' => env('ATTRIBUTES_ENHANCEMENT_ENABLED', true),
            'cache_enabled' => env('ATTRIBUTES_CACHE_ENABLED', true),
            'code_generation' => env('ATTRIBUTES_CODE_GENERATION', true),
            'runtime_optimization' => env('ATTRIBUTES_RUNTIME_OPTIMIZATION', true),
        ],
        
        // Fibers (Async/Await)
        'fibers' => [
            'enabled' => env('FIBERS_ENABLED', true),
            'max_concurrent' => env('FIBERS_MAX_CONCURRENT', 1000),
        ],
    ],
];
