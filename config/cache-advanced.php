<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Advanced Caching Configurations
    |--------------------------------------------------------------------------
    |
    | Multi-tier, Predictive, and CRDT caching strategies.
    |
    */

    'multi_tier' => [
        'enabled' => env('CACHE_MULTI_TIER_ENABLED', true),
        
        // L1: Local In-Memory (APCu) - Fastest, but per-process/node
        'l1' => [
            'enabled' => env('CACHE_L1_ENABLED', true),
            'driver' => 'apcu',
            'ttl' => 60,
        ],

        // L2: Shared Distributed (Redis) - Standard shared cache
        'l2' => [
            'enabled' => true,
            'driver' => 'redis',
        ],

        // L3: Persistent Store (File/DB) - Fallback for critical data
        'l3' => [
            'enabled' => env('CACHE_L3_ENABLED', false),
            'driver' => 'file',
        ],

        'sync_enabled' => true, // Sync L1 across workers via PubSub (if applicable)
    ],

    /*
    | AI Predictive Cache Settings
    */
    'predictive' => [
        'enabled' => env('CACHE_PREDICTIVE_ENABLED', false),
        'min_hits' => 10,
        'bootstrap_factor' => 0.8,
    ],

    /*
    | CRDT Eventual Consistency Cache
    */
    'crdt' => [
        'enabled' => env('CACHE_CRDT_ENABLED', false),
        'replicas' => env('CACHE_CRDT_REPLICAS', ''),
        'node_id' => env('APP_NODE_ID', null),
    ],
];
