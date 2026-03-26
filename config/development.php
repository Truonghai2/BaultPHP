<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Development & Testing Configuration
    |--------------------------------------------------------------------------
    |
    | Consolidated development tools configuration including:
    | - Hot reload & live reloading
    | - Visual debugging tools
    | - Debug mode configuration
    | - Advanced testing (Property-based, Mutation)
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Debug Mode Configuration
    |--------------------------------------------------------------------------
    |
    | Enable debug mode with debug bar and detailed error messages.
    | Should only be enabled in local/development environments.
    |
    */
    'debug' => [
        'enabled' => env('APP_DEBUG', false),
        'expiration' => 3600, // Debug data TTL in Redis (1 hour)
        'on_demand' => false, // Only enable when X-DEBUG-ENABLED cookie is present
    ],

    /*
    |--------------------------------------------------------------------------
    | Hot Reload Configuration
    |--------------------------------------------------------------------------
    |
    | Automatically reload application when files change.
    |
    */
    'hot_reload' => [
        'enabled' => env('HOT_RELOAD_ENABLED', true),
        'interval' => env('HOT_RELOAD_INTERVAL', 500), // milliseconds
        'directories' => [
            base_path('src'),
            base_path('Modules'),
            base_path('config'),
            base_path('resources'),
            base_path('routes'),
        ],
        'ignore' => [
            storage_path(),
            base_path('bootstrap/cache'),
            base_path('vendor'),
            '*.log',
            base_path('node_modules'),
            base_path('.git'),
            '*.tmp',
            '*.swp',
            '*.swo',
            '.DS_Store',
            'Thumbs.db',
        ],
        'dependency_tracking' => env('HOT_RELOAD_DEPENDENCY_TRACKING', true),
        'incremental_compilation' => env('HOT_RELOAD_INCREMENTAL', true),
        'fast_refresh' => env('HOT_RELOAD_FAST_REFRESH', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Visual Debugging Configuration
    |--------------------------------------------------------------------------
    |
    | Advanced visual debugging tools for development.
    |
    */
    'visual_debugging' => [
        'enabled' => env('VISUAL_DEBUGGING_ENABLED', false),
        'request_flow' => env('VISUAL_DEBUG_REQUEST_FLOW', true),
        'query_analyzer' => env('VISUAL_DEBUG_QUERY_ANALYZER', true),
        'performance_profiler' => env('VISUAL_DEBUG_PERFORMANCE', true),
        'memory_leak_detector' => env('VISUAL_DEBUG_MEMORY_LEAK', true),
        'flame_graph' => env('VISUAL_DEBUG_FLAME_GRAPH', true),
        'slow_query_threshold' => env('VISUAL_DEBUG_SLOW_QUERY', 100), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Property-Based Testing Configuration
    |--------------------------------------------------------------------------
    |
    | Generative testing with random inputs to find edge cases.
    |
    */
    'property_testing' => [
        'enabled' => env('PROPERTY_TESTING_ENABLED', false),
        'max_tests' => env('PROPERTY_TESTING_MAX_TESTS', 100),
        'max_shrinks' => env('PROPERTY_TESTING_MAX_SHRINKS', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mutation Testing Configuration
    |--------------------------------------------------------------------------
    |
    | Test your tests by introducing mutations to your code.
    |
    */
    'mutation_testing' => [
        'enabled' => env('MUTATION_TESTING_ENABLED', false),
        'threads' => env('MUTATION_TESTING_THREADS', 4),
        'only_covered' => env('MUTATION_TESTING_ONLY_COVERED', true),
        'test_framework' => env('MUTATION_TESTING_FRAMEWORK', 'phpunit'),
        'timeout' => env('MUTATION_TESTING_TIMEOUT', 600), // seconds
        'min_msi' => env('MUTATION_TESTING_MIN_MSI', 70), // Minimum Mutation Score Indicator
    ],
];
