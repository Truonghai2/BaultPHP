<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Swoole HTTP Server Options
    |--------------------------------------------------------------------------
    |
    | Đây là các tùy chọn được truyền trực tiếp đến Swoole HTTP Server.
    | Bạn có thể tùy chỉnh chúng để tinh chỉnh hiệu suất và hành vi của server.
    |
    */
    'swoole' => [
        'host' => env('SWOOLE_HOST', '127.0.0.1'),
        'port' => env('SWOOLE_PORT', 9501),

        // Chạy server ở chế độ nền (daemon)
        'daemonize' => env('SWOOLE_DAEMONIZE', false),

        // Các tùy chọn cho worker
        'worker_num' => env('SWOOLE_WORKER_NUM', swoole_cpu_num() * 2),

        // Đường dẫn đến các file log và pid
        'pid_file' => storage_path('logs/swoole.pid'),
        'log_file' => storage_path('logs/swoole.log'),

        /*
        |--------------------------------------------------------------------------
        | File Watcher (Dành cho Development)
        |--------------------------------------------------------------------------
        |
        | Cấu hình cho lệnh `serve:watch`. Lệnh này sẽ tự động khởi động lại
        | server khi có sự thay đổi trong các file được theo dõi.
        |
        */
        'watch' => [
            'directories' => [
                base_path('src'),
                base_path('Modules'),
                base_path('config'),
                base_path('resources'),
            ],
        ],
    ],
];
