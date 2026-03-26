<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module marketplace
    |--------------------------------------------------------------------------
    |
    | Registry URL returns a JSON catalog of available modules. Each entry
    | should have: id, name, version, description, download_url (ZIP), and
    | optionally package (Composer name), permissions, min_core_version.
    |
    */
    'enabled' => env('MODULE_MARKETPLACE_ENABLED', true),

    'registry_url' => env('MODULE_MARKETPLACE_REGISTRY_URL', ''),
    'registry_timeout' => (int) env('MODULE_MARKETPLACE_TIMEOUT', 15),
    'registry_headers' => array_filter([
        'Accept' => 'application/json',
        'Authorization' => env('MODULE_MARKETPLACE_TOKEN') ? 'Bearer ' . env('MODULE_MARKETPLACE_TOKEN') : null,
    ]),

    'download_timeout' => (int) env('MODULE_MARKETPLACE_DOWNLOAD_TIMEOUT', 120),
];
