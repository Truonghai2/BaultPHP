<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy
    |--------------------------------------------------------------------------
    */
    'enabled' => env('TENANCY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Tenant resolution (how we determine current tenant per request)
    |--------------------------------------------------------------------------
    | header: read from HTTP header (e.g. X-Tenant-Id)
    | subdomain: resolve from subdomain (e.g. acme.example.com -> acme)
    | user: from authenticated user's tenant_id (if users have tenant_id)
    */
    'resolution' => env('TENANCY_RESOLUTION', 'header'),

    'header_name' => env('TENANCY_HEADER', 'X-Tenant-Id'),

    /*
    |--------------------------------------------------------------------------
    | Subdomain -> tenant slug mapping
    |--------------------------------------------------------------------------
    | If resolution is subdomain, we match request host to tenant slug.
    | No config needed if slug equals subdomain; use custom resolver for more.
    */
    'subdomain_slug_delimiter' => '.',

    /*
    |--------------------------------------------------------------------------
    | When no tenant is resolved
    |--------------------------------------------------------------------------
    | global: all globally enabled modules (default app behavior)
    | strict: require tenant; 403 if missing (for pure SaaS)
    */
    'when_missing' => env('TENANCY_WHEN_MISSING', 'global'),
];
