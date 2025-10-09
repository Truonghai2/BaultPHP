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
        | Daemonize
        |--------------------------------------------------------------------------
        |
        | Chạy server ở chế độ nền (daemon).
        | Thường được sử dụng trong môi trường production.
        | Có thể được ghi đè bằng cờ --daemon khi chạy lệnh serve:start.
        */
        'daemonize' => env('SWOOLE_DAEMONIZE', false),

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

        'pid_file' => storage_path('logs/swoole.pid'),
        'log_file' => storage_path('logs/swoole.log'),
        'log_level' => env('SWOOLE_LOG_LEVEL', null),

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
            'files' => [
                base_path('.env'),
            ],
            'directories' => [
                base_path('src'),
                base_path('Modules'),
                base_path('config'),
                base_path('resources'),
                base_path('database'),
                base_path('routes'),
            ],
            /*
            |--------------------------------------------------------------------------
            | Use File Polling
            |--------------------------------------------------------------------------
            |
            | Buộc watcher sử dụng chế độ polling thay vì lắng nghe sự kiện file.
            | Điều này cần thiết khi chạy trong Docker trên Windows/macOS.
            */
            'use_polling' => env('SWOOLE_WATCH_USE_POLLING', true),
            'ignore' => [
                storage_path(),
                base_path('bootstrap/cache'),
                base_path('vendor'),
                '*.log',
                base_path('node_modules'),
                base_path('.git'),
            ],
        ],

        /*
         |--------------------------------------------------------------------------
         | Connection Pools
         |--------------------------------------------------------------------------
         |
         | Cấu hình tập trung cho tất cả các loại connection pool.
         | Cấu trúc này giúp dễ dàng thêm các loại pool mới (vd: gRPC, HTTP client)
         | mà không cần sửa đổi code của ConnectionPoolManager.
         |
         */
        'pools' => [
            'redis' => [
                'enabled' => env('REDIS_POOL_ENABLED', true),
                'class' => \Core\Database\Swoole\SwooleRedisPool::class,
                'config_prefix' => 'redis.connections',

                'defaults' => [
                    'worker_pool_size' => env('REDIS_POOL_WORKER_SIZE', 10),
                    'task_worker_pool_size' => env('REDIS_POOL_TASK_SIZE', 10),
                    'scheduler_pool_size' => env('REDIS_POOL_SCHEDULER_SIZE', 1),
                    'retry' => [
                        'max_attempts' => env('REDIS_POOL_RETRY_ATTEMPTS', 5),
                        'initial_delay_ms' => env('REDIS_POOL_RETRY_DELAY_MS', 500),
                        'backoff_multiplier' => env('REDIS_POOL_RETRY_BACKOFF', 1.5),
                        'max_delay_ms' => env('REDIS_POOL_RETRY_MAX_DELAY_MS', 10000),
                    ],
                    'circuit_breaker' => [
                        'enabled'  => env('REDIS_CIRCUIT_BREAKER_ENABLED', true),
                        'storage'  => env('REDIS_CIRCUIT_BREAKER_STORAGE', 'redis'),
                        'redis_pool' => env('REDIS_CIRCUIT_BREAKER_REDIS_POOL', 'default'),
                        'strategy' => env('REDIS_CIRCUIT_BREAKER_STRATEGY', 'rate'),
                        'rate' => [
                            'failure_rate'    => (int) env('REDIS_CIRCUIT_BREAKER_FAILURE_RATE', 50),
                            'minimum_requests'  => (int) env('REDIS_CIRCUIT_BREAKER_MIN_REQUESTS', 10),
                            'time_window'       => (int) env('REDIS_CIRCUIT_BREAKER_TIME_WINDOW', 60),
                            'interval_to_half_open'   => (int) env('REDIS_CIRCUIT_BREAKER_TIMEOUT', 30),
                        ],
                    ],
                ],

                'connections' => [
                    'default' => [
                        'worker_pool_size' => 20,
                    ],
                    'cache' => [
                        'alias' => 'default',
                    ],
                    'queue' => [
                        'alias' => 'default',
                    ],
                ],
            ],

            'database' => [
                'enabled' => env('DB_POOL_ENABLED', true),
                'class' => \Core\Database\Swoole\SwoolePdoPool::class,
                'config_prefix' => 'database.connections',

                'defaults' => [
                    'worker_pool_size' => env('DB_POOL_WORKER_SIZE', 10),
                    'task_worker_pool_size' => env('DB_POOL_TASK_SIZE', 10),

                    'retry' => [
                        'max_attempts' => env('DB_POOL_RETRY_ATTEMPTS', 3),
                        'initial_delay_ms' => env('DB_POOL_RETRY_DELAY_MS', 1000),
                        'backoff_multiplier' => env('DB_POOL_RETRY_BACKOFF', 2.0),
                        'max_delay_ms' => env('DB_POOL_RETRY_MAX_DELAY_MS', 30000),
                    ],

                    'heartbeat' => env('DB_POOL_HEARTBEAT', 60),

                    'circuit_breaker' => [
                        'enabled'  => env('DB_CIRCUIT_BREAKER_ENABLED', true),
                        'storage'  => env('DB_CIRCUIT_BREAKER_STORAGE', 'redis'),
                        'redis_pool' => env('DB_CIRCUIT_BREAKER_REDIS_POOL', 'default'),
                        'strategy' => 'rate',
                        'rate' => [
                            'failure_rate'    => (int) env('DB_CIRCUIT_BREAKER_FAILURE_RATE', 50),
                            'minimum_requests'  => (int) env('DB_CIRCUIT_BREAKER_MIN_REQUESTS', 10),
                            'time_window'       => (int) env('DB_CIRCUIT_BREAKER_TIME_WINDOW', 60),
                            'interval_to_half_open'   => (int) env('DB_CIRCUIT_BREAKER_TIMEOUT', 30),
                        ],
                    ],
                ],

                'connections' => [
                    ...(function () {
                        $allPoolConfigs = [
                            'mysql' => [
                                'worker_pool_size' => 15,
                                'circuit_breaker' => [
                                    'rate' => ['failure_rate' => 30, 'minimum_requests' => 5],
                                ],
                            ],
                            'pgsql' => [
                                'worker_pool_size' => 5,
                            ],
                        ];

                        $enabledPoolsStr = env('DB_POOLS_ENABLED');
                        $enabledPoolNames = $enabledPoolsStr
                            ? array_map('trim', explode(',', $enabledPoolsStr))
                            : [env('DB_CONNECTION', 'mysql')];

                        return array_intersect_key($allPoolConfigs, array_flip($enabledPoolNames));
                    })(),
                ],
            ],
        ],

        'guzzle' => [
            'enabled' => env('GUZZLE_POOL_ENABLED', true),
            'class' => \Core\Http\Swoole\SwooleGuzzlePool::class,
            'config_prefix' => 'services.guzzle.clients', // Nơi lấy cấu hình chi tiết cho từng client

            'defaults' => [
                'worker_pool_size' => env('GUZZLE_POOL_WORKER_SIZE', 15),
                'task_worker_pool_size' => env('GUZZLE_POOL_TASK_SIZE', 5),

                'retry' => [
                    'max_attempts' => 3,
                    'initial_delay_ms' => 500,
                ],
            ],

            'connections' => [
                // Cấu hình cho một client Guzzle mặc định
                'default' => [
                    // Các tùy chọn Guzzle sẽ được lấy từ 'services.guzzle.clients.default'
                ],

                // Ví dụ về một client khác để gọi một API cụ thể
                'weather_api' => [
                    'worker_pool_size' => 5,
                ],
            ],
        ],
    ],
];
