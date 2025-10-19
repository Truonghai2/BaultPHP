<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Authentication Guard
    |--------------------------------------------------------------------------
    |
    | Tùy chọn này kiểm soát "guard" xác thực mặc định sẽ được sử dụng.
    | Bạn có thể thay đổi giá trị này để phù hợp với nhu cầu của ứng dụng.
    |
    */
    'defaults' => [
        'guard' => 'web',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Tại đây bạn có thể định nghĩa mọi guard xác thực cho ứng dụng của mình.
    | Mỗi guard có một driver và một provider. Provider có thể được cấu hình
    | trong phần "providers" bên dưới.
    |
    | Hỗ trợ sẵn: "session", "token"
    |
    */
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'token',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Super Admin Permission
    |--------------------------------------------------------------------------
    | The permission name that grants a user all permissions across the system.
    | Assign this permission to your super admin role.
    */
    'super_admin_permission' => 'super-admin',

    'cache' => [
        'permissions_ttl' => 3600,
    ],
    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | Tất cả các driver xác thực đều có một user provider. Nó định nghĩa cách
    | người dùng thực sự được lấy ra từ cơ sở dữ liệu hoặc nơi lưu trữ khác.
    |
    | Hỗ trợ sẵn: "orm"
    |
    */
    'providers' => [
        'users' => [
            'driver' => 'orm',
            // Model này phải implement interface Core\Contracts\Auth\Authenticatable
            'model' => \Modules\User\Infrastructure\Models\User::class,
        ],
    ],
];
