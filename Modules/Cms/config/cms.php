<?php

/**
 * CMS Module Configuration
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Auto-Sync Blocks
    |--------------------------------------------------------------------------
    |
    | Automatically sync blocks to database without running commands.
    |
    | - In development (local): Syncs every 30 seconds
    | - In production: Disabled by default (use command instead)
    |
    */
    'auto_sync_blocks' => env('CMS_AUTO_SYNC_BLOCKS', true),

    /*
    |--------------------------------------------------------------------------
    | Block Sync Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long to cache block sync status (in seconds)
    |
    | - Development: 30 seconds (for real-time experience)
    | - Production: 3600 seconds (1 hour)
    |
    */
    'sync_cache_ttl' => env('CMS_SYNC_CACHE_TTL', config('app.env') === 'local' ? 30 : 3600),

    /*
    |--------------------------------------------------------------------------
    | Block Discovery Paths
    |--------------------------------------------------------------------------
    |
    | Directories to scan for block classes
    |
    */
    'block_paths' => [
        base_path('Modules/*/Domain/Blocks'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Block Cache Settings
    |--------------------------------------------------------------------------
    |
    | Smart caching:
    | - Development: Cache disabled by default (real-time changes)
    | - Production: Cache enabled (optimal performance)
    |
    */
    'block_cache' => [
        'enabled' => env('CMS_BLOCK_CACHE_ENABLED', !app()->environment('local')),
        'ttl' => env('CMS_BLOCK_CACHE_TTL', app()->environment('local') ? 0 : 3600),
        'prefix' => 'cms.block.',
        'auto_disable_in_debug' => env('CMS_CACHE_AUTO_DISABLE_DEBUG', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Page Settings
    |--------------------------------------------------------------------------
    */
    'pages' => [
        'per_page' => 15,
        'max_blocks_per_page' => 50,
        'cache_enabled' => env('CMS_PAGE_CACHE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO Settings
    |--------------------------------------------------------------------------
    */
    'seo' => [
        'default_meta_description' => env('CMS_DEFAULT_META_DESCRIPTION', ''),
        'default_og_image' => env('CMS_DEFAULT_OG_IMAGE', ''),
    ],
];
