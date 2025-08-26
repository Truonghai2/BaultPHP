<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    |
    | Đây là driver mặc định sẽ được sử dụng để lưu trữ session.
    | Framework hỗ trợ sẵn: "file", "redis".
    |
    */

    'driver' => env('SESSION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    |
    | Thời gian (tính bằng phút) mà session sẽ được lưu trữ. Nếu hết hạn,
    | session sẽ bị xóa.
    |
    */

    'lifetime' => env('SESSION_LIFETIME', 120),

    /*
    |--------------------------------------------------------------------------
    | Session Database Table
    |--------------------------------------------------------------------------
    |
    | Khi sử dụng driver "database", đây là tên bảng sẽ được sử dụng
    | để lưu trữ thông tin session.
    |
    */
    'table' => 'sessions',

    /*
    |--------------------------------------------------------------------------
    | Redis Session Connection
    |--------------------------------------------------------------------------
    |
    | Tên của Redis connection (từ config/redis.php) sẽ được sử dụng nếu bạn chọn driver là "redis".
    |
    */

    'connection' => env('SESSION_REDIS_CONNECTION', 'default'),
];
