<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | Tùy chọn này kiểm soát cache store mặc định sẽ được sử dụng.
    | Bạn có thể chỉ định bất kỳ store nào được định nghĩa trong mảng "stores" bên dưới.
    |
    */

    'default' => env('CACHE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Tại đây bạn có thể định nghĩa tất cả các "store" cache cho ứng dụng.
    | BaultPHP có thể hỗ trợ nhiều driver phổ biến như Redis, file, array.
    |
    */

    'stores' => [

        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache', // Tên kết nối trong config/database.php (phần redis)
        ],

        'file' => [
            'driver' => 'file',
            // Đường dẫn lưu file cache. Hàm storage_path() nên tồn tại.
            'path' => storage_path('framework/cache/data'),
        ],

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | Khi dùng Redis, bạn nên chỉ định một tiền tố cho tất cả các key cache
    | để tránh xung đột với các ứng dụng khác dùng chung Redis server.
    |
    */

    'prefix' => env('CACHE_PREFIX', 'bault_cache'),

];
