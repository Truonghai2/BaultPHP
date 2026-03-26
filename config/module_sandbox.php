<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module sandbox
    |--------------------------------------------------------------------------
    |
    | When enabled, code running in a module context (e.g. extension point
    | handlers) is restricted to the permissions declared in that module's
    | module.json "permissions" array. Cache, and optionally DB/network,
    | are wrapped to enforce cache:read, cache:write, etc.
    |
    */
    'enabled' => env('MODULE_SANDBOX_ENABLED', false),

    /*
    | Enforce cache operations (get/set/delete) against module permissions.
    | Requires "cache:read" and "cache:write" in module.json permissions.
    */
    'enforce_cache' => true,

    /*
    | Reserved for future: enforce database and HTTP client by permission.
    */
    'enforce_database' => false,
    'enforce_network'  => false,
];
