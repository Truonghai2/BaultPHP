<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | This file defines the feature flags for your application. You can use
    | this to enable or disable features on the fly without deploying new code.
    | It's a good practice to use environment variables for flags that might
    | change between environments.
    |
    */

    'new-admin-dashboard' => env('FEATURE_NEW_ADMIN_DASHBOARD', true),
    'beta-user-profile' => env('FEATURE_BETA_USER_PROFILE', false),
    'experimental-api' => env('FEATURE_EXPERIMENTAL_API', false),
];
