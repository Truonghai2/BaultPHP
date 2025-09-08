<?php

return [
    'private_key' => env('OAUTH_PRIVATE_KEY_PATH', storage_path('oauth-private.key')),

    'public_key' => env('OAUTH_PUBLIC_KEY_PATH', storage_path('oauth-public.key')),

    'encryption_key' => env('OAUTH_ENCRYPTION_KEY'),

    'access_token_ttl' => 'PT1H',

    'refresh_token_ttl' => 'P1M',

    'auth_code_ttl' => 'PT10M',

    /*
    |--------------------------------------------------------------------------
    | Restricted Scopes by Client
    |--------------------------------------------------------------------------
    |
    | Đây là nơi để định nghĩa các scope bị giới hạn và danh sách các client ID
    | được phép yêu cầu chúng. Nếu một scope không được định nghĩa ở đây,
    | nó được coi là công khai và mọi client đều có thể yêu cầu.
    |
    | 'scope-name' => [
    |     'client-id-1',
    |     'client-id-2',
    | ],
    |
    */
    'restricted_scopes' => [
        'websocket' => [
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Restricted Scopes by User Permission
    |--------------------------------------------------------------------------
    |
    | Đây là nơi để ánh xạ một scope với một "permission" (quyền) cụ thể
    | trong hệ thống RBAC. Nếu một scope được định nghĩa ở đây, chỉ những
    | người dùng có quyền tương ứng mới được cấp scope đó.
    |
    | 'scope-name' => 'permission-name',
    |
    */
    'user_restricted_scopes' => [
        'websocket' => 'scope:websocket',
    ],
];
