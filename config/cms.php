<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto Sync Blocks
    |--------------------------------------------------------------------------
    |
    | Automatically sync block types to database in development mode.
    | This allows you to add/update blocks without running seeders.
    | Set to false in production for better performance.
    |
    */
    'auto_sync_blocks' => env('CMS_AUTO_SYNC_BLOCKS', true),

    /*
    |--------------------------------------------------------------------------
    | Block Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long to cache block sync checks (in seconds).
    | In development: 300 (5 minutes)
    | In production: 3600 (1 hour)
    |
    */
    'sync_cache_ttl' => env('CMS_SYNC_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Default Regions
    |--------------------------------------------------------------------------
    |
    | Default block regions that should always exist.
    | These will be auto-created during sync.
    |
    */
    'default_regions' => [
        'header-nav',
        'header-user',
        'sidebar',
        'sidebar-left',
        'content',
        'footer',
        'homepage-hero',
        'homepage-features',
        'homepage-stats',
    ],

    /*
    |--------------------------------------------------------------------------
    | Block Settings
    |--------------------------------------------------------------------------
    |
    | General block system settings
    |
    */
    'block_wrapper' => env('CMS_BLOCK_WRAPPER', true), // Wrap blocks in container
    'show_block_titles' => env('CMS_SHOW_BLOCK_TITLES', true), // Show block titles by default
    'enable_block_cache' => env('CMS_ENABLE_BLOCK_CACHE', true), // Enable block caching
];