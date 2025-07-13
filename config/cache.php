<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | Tên của store cache mặc định sẽ được sử dụng bởi framework.
    | Bạn có thể thay đổi giá trị này để chuyển đổi giữa các driver cache.
    |
    | Hỗ trợ: "redis", "file" (chưa implement), "array" (cho testing)
    |
    */
    'default' => env('CACHE_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Tại đây bạn có thể cấu hình tất cả các "store" cache cho ứng dụng.
    |
    */
    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default', // Tên kết nối redis trong config/database.php
        ],
    ],
];