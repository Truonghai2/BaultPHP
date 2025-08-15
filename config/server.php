<?php

return [
    'swoole' => [
        /*
        |--------------------------------------------------------------------------
        | Server Host
        |--------------------------------------------------------------------------
        |
        | Địa chỉ host mà server Swoole sẽ lắng nghe.
        |
        | Trong môi trường Docker, giá trị này PHẢI là '0.0.0.0' để có thể
        | nhận kết nối từ bên ngoài container. Khi phát triển trên máy local
        | không dùng Docker, '127.0.0.1' là một giá trị an toàn.
        |
        */
        'host' => env('SWOOLE_HOST', '0.0.0.0'),

        /*
        |--------------------------------------------------------------------------
        | Server Port
        |--------------------------------------------------------------------------
        */
        'port' => env('SWOOLE_PORT', 9501),

        /*
        |--------------------------------------------------------------------------
        | Worker Processes
        |--------------------------------------------------------------------------
        |
        | Số lượng worker process để xử lý request. Giá trị mặc định trong
        | SwooleServer.php đã được tối ưu, nhưng bạn có thể ghi đè ở đây.
        | Đối với ứng dụng I/O-bound, `swoole_cpu_num() * 2` hoặc `* 4` là lựa chọn tốt.
        |
        */
        'worker_num' => env('SWOOLE_WORKER_NUM', null), // null để dùng mặc định của framework
        'task_worker_num' => env('SWOOLE_TASK_WORKER_NUM', null), // null để dùng mặc định của framework

        /*
        |--------------------------------------------------------------------------
        | Max Requests Per Worker
        |--------------------------------------------------------------------------
        |
        | Số lượng request tối đa mà một worker sẽ xử lý trước khi tự khởi động lại.
        | Giúp ngăn chặn rò rỉ bộ nhớ trong các ứng dụng chạy dài hạn.
        |
        */
        'max_request' => env('SWOOLE_MAX_REQUEST', 10000),

        // Đường dẫn đến các file log và pid
        'pid_file' => storage_path('logs/swoole.pid'),
        'log_file' => storage_path('logs/swoole.log'),
        'log_level' => env('SWOOLE_LOG_LEVEL', null), // null để dùng mặc định của framework

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
                base_path('database'),
                base_path('routes'),
                base_path('public'),
                base_path(), // Watch root directory for files like composer.json, .env, etc.
            ],
            'ignore' => [
                storage_path(),
                base_path('bootstrap/cache'),
                base_path('vendor'),
                '*.log',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Database Connection Pool (Ví dụ)
        |--------------------------------------------------------------------------
        */
        'db_pool' => [
            'enabled' => env('DB_POOL_ENABLED', true),
            'connection' => env('DB_CONNECTION', 'mysql'),
            // Số lượng kết nối cho mỗi HTTP worker
            'worker_pool_size' => env('DB_POOL_WORKER_SIZE', 10),
            // Số lượng kết nối cho mỗi Task worker
            'task_worker_pool_size' => env('DB_POOL_TASK_SIZE', 10),
            // Tần suất kiểm tra kết nối (giây). Giúp tránh lỗi "MySQL has gone away".
            'heartbeat' => env('DB_POOL_HEARTBEAT', 60),

            // Cấu hình Circuit Breaker cho Database
            'circuit_breaker' => [
                'enabled'  => env('DB_CIRCUIT_BREAKER_ENABLED', true),
                // 'storage' xác định nơi lưu trạng thái. Hiện tại chỉ hỗ trợ 'redis'.
                // Việc dùng 'redis' cho phép tất cả worker chia sẻ chung một trạng thái, giúp giảm tải cho DB khi có sự cố.
                'storage'  => env('DB_CIRCUIT_BREAKER_STORAGE', 'redis'), // or 'apcu'
                // QUAN TRỌNG: Khi sử dụng 'redis' làm storage, Ganesha chỉ hỗ trợ chiến lược 'rate'.
                'strategy' => 'rate',

                // Cài đặt cho chiến lược 'count' (đếm lỗi liên tiếp)
                'count' => [
                    'failure_threshold' => 3,   // Mở mạch sau 3 lần thất bại liên tiếp
                    'interval_to_half_open'   => 10,  // Thử lại sau 10 giây
                ],

                // Cài đặt cho chiến lược 'rate' (tỷ lệ lỗi)
                'rate' => [
                    'failure_rate'    => (int) env('DB_CIRCUIT_BREAKER_FAILURE_RATE', 50), // Mở nếu 50% request lỗi
                    'minimum_requests'  => (int) env('DB_CIRCUIT_BREAKER_MIN_REQUESTS', 10), // ...với tối thiểu 10 request
                    'time_window'       => (int) env('DB_CIRCUIT_BREAKER_TIME_WINDOW', 60),  // ...trong khoảng 60 giây
                    'interval_to_half_open'   => (int) env('DB_CIRCUIT_BREAKER_TIMEOUT', 30),    // Thử lại sau 30 giây
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Redis Connection Pool
        |--------------------------------------------------------------------------
        */
        'redis_pool' => [
            'enabled' => env('REDIS_POOL_ENABLED', true),
            'connection' => env('REDIS_CONNECTION', 'default'), // Tên connection trong config/database.php
            'worker_pool_size' => env('REDIS_POOL_WORKER_SIZE', 10),
            'task_worker_pool_size' => env('REDIS_POOL_TASK_SIZE', 10),

            // Cấu hình Circuit Breaker cho Redis
            'circuit_breaker' => [
                'enabled'  => env('REDIS_CIRCUIT_BREAKER_ENABLED', true),
                // Mặc định dùng 'redis'. Điều này tạo ra một "chicken-and-egg problem":
                // nếu Redis bị lỗi, circuit breaker cho Redis cũng sẽ không thể ghi trạng thái.
                // Tuy nhiên, việc này đảm bảo tất cả worker chia sẻ chung một trạng thái circuit breaker.
                // Ganesha sẽ fail-safe (mở mạch) nếu không kết nối được storage.
                'storage'  => env('REDIS_CIRCUIT_BREAKER_STORAGE', 'redis'),
                'strategy' => env('REDIS_CIRCUIT_BREAKER_STRATEGY', 'rate'), // 'count' hoặc 'rate'. Redis storage chỉ hỗ trợ 'rate'.

                // Cài đặt cho chiến lược 'count'
                'count' => [
                    'failure_threshold' => (int) env('REDIS_CIRCUIT_BREAKER_THRESHOLD', 5),
                    'interval_to_half_open'   => (int) env('REDIS_CIRCUIT_BREAKER_TIMEOUT', 30),
                ],

                // Cài đặt cho chiến lược 'rate'
                'rate' => [
                    'failure_rate'    => (int) env('REDIS_CIRCUIT_BREAKER_FAILURE_RATE', 50),
                    'minimum_requests'  => (int) env('REDIS_CIRCUIT_BREAKER_MIN_REQUESTS', 10),
                    'time_window'       => (int) env('REDIS_CIRCUIT_BREAKER_TIME_WINDOW', 60),
                    'interval_to_half_open'   => (int) env('REDIS_CIRCUIT_BREAKER_TIMEOUT', 30),
                ],
            ],
        ],
    ],
];
