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

    'driver' => env('SESSION_DRIVER', 'file'),

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
    | Redis Session Connection
    |--------------------------------------------------------------------------
    |
    | Tên của Redis connection (từ config/database.php) sẽ được sử dụng.
    |
    */

    'connection' => env('SESSION_REDIS_CONNECTION', 'default'),
];
