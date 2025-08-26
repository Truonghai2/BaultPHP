<?php

return [
    'private_key' => env('OAUTH_PRIVATE_KEY_PATH', storage_path('oauth-private.key')),

    'public_key' => env('OAUTH_PUBLIC_KEY_PATH', storage_path('oauth-public.key')),

    'encryption_key' => env('OAUTH_ENCRYPTION_KEY'),

    'access_token_ttl' => 'PT1H',

    'refresh_token_ttl' => 'P1M',

    'auth_code_ttl' => 'PT10M',
];
