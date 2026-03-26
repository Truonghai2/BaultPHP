<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WebAssembly Configuration
    |--------------------------------------------------------------------------
    |
    | Toggle and configure WASM runtime behavior.
    |
    */
    'enabled' => env('WASM_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Runtime Settings
    |--------------------------------------------------------------------------
    */
    'runtime' => env('WASM_RUNTIME', 'wasmtime'),
    'runtime_path' => env('WASM_RUNTIME_PATH', null),
    'wasm_directory' => env('WASM_DIR', base_path('wasm')),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache_enabled' => env('WASM_CACHE_ENABLED', true),
    'cache_ttl' => env('WASM_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Fallbacks
    |--------------------------------------------------------------------------
    |
    | When WASM fails, optionally fall back to PHP implementations.
    |
    */
    'fallback_to_php' => env('WASM_FALLBACK_TO_PHP', true),
    'fallbacks' => [
        'image_processor.wasm' => null,
        'calculator.wasm' => null,
        'statistics.wasm' => null,
        'matrix.wasm' => null,
        'fft.wasm' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Processing
    |--------------------------------------------------------------------------
    */
    'image' => [
        'thumbnail_dir' => env('WASM_THUMB_DIR', 'public/uploads/media/thumbnails'),
        'thumbnail_url_prefix' => env('WASM_THUMB_URL', '/uploads/media/thumbnails'),
        'default_quality' => env('WASM_IMAGE_QUALITY', 85),
        'preserve_aspect' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin ABI (for module WASM plugins)
    |--------------------------------------------------------------------------
    |
    | stdio: send JSON to stdin, read JSON from stdout (WASI). Module must
    | read from fd 0 and write to fd 1. invoke: pass args via --invoke (legacy).
    |
    */
    'plugin_abi' => env('WASM_PLUGIN_ABI', 'stdio'),

    /*
    |--------------------------------------------------------------------------
    | Module WASM directory name
    |--------------------------------------------------------------------------
    */
    'module_wasm_dir' => 'wasm',
];
