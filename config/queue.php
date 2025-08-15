<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | BaultPHP's queue API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for every one. Here you may define a default connection.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with BaultPHP. You are free to add more.
    |
    */

    'connections' => [

        'sync' => [
            // Chạy job ngay lập tức trong process hiện tại.
            // Hữu ích cho việc debug, nhưng sẽ làm chậm request. Không dùng cho production.
            'driver' => 'sync',
        ],
        'swoole' => [
            // Sử dụng task worker của Swoole. Rất nhanh nhưng không bền bỉ.
            // Job sẽ bị mất nếu server khởi động lại. Phù hợp cho các tác vụ không quan trọng.
            'driver' => 'swoole',
            'queue' => 'default',
        ],
        'database' => [
            // Lưu job vào một bảng trong CSDL. Bền bỉ và đáng tin cậy.
            // Yêu cầu bạn phải tạo bảng 'jobs' (xem hướng dẫn).
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ],
        'redis' => [
            // Sử dụng Redis để lưu job. Đây là lựa chọn được khuyến khích nhất cho production.
            // Nhanh, hiệu quả và bền bỉ.
            'driver' => 'redis',
            'connection' => 'default', // Tên kết nối redis trong config/database.php
            'queue' => env('REDIS_QUEUE', 'default'),
            // Tên của sorted set trong Redis dùng cho các job bị trì hoãn.
            'delayed_queue' => env('REDIS_DELAYED_QUEUE', 'queues:delayed:default'),
            'retry_after' => 90,
        ],

    ],

    'scheduler' => [
        // Khoảng thời gian (ms) mà scheduler sẽ kiểm tra các job mới. 1000ms = 1 giây.
        'check_interval' => 1000,
    ],
];
