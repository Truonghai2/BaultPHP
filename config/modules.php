<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Composer-installed modules
    |--------------------------------------------------------------------------
    |
    | Packages with type "bault-module" or extra.bault.module are discovered
    | from vendor/. By default all discovered composer modules are enabled.
    | List package names (e.g. "vendor/package") here to disable them.
    |
    */
    'composer_disabled' => env('MODULES_COMPOSER_DISABLED') ? array_map('trim', explode(',', env('MODULES_COMPOSER_DISABLED'))) : [],
];
