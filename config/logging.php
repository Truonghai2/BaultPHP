<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | Kênh log mặc định sẽ được sử dụng khi bạn gọi Log::info('...').
    | Bạn có thể đặt tên của một trong các channel được định nghĩa bên dưới.
    |
    */

    'default' => env('LOG_CHANNEL', 'daily'), // Tạm thời chuyển sang 'daily' để debug

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Đây là nơi bạn có thể cấu hình tất cả các kênh log cho ứng dụng.
    | BaultPHP hỗ trợ sẵn một vài driver: "single", "daily", "slack",
    | "syslog", "errorlog", "monolog", "custom", "stack".
    |
    */

    'channels' => [
        'default_stack' => [
            'driver' => 'stack',
            'channels' => ['async', 'sentry'],
            'ignore_exceptions' => false,
        ],
        'sync_stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'sentry'],
            'ignore_exceptions' => false,
        ],

        'task_worker' => [
            'driver' => 'daily',
            'path' => storage_path('logs/tasks.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 7,
        ],

        'sentry' => [
            'driver' => 'sentry',
            'level' => env('SENTRY_LOG_LEVEL', 'error'),
        ],

        'async' => [
            'driver' => 'async', // Driver tùy chỉnh của chúng ta
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/bault.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/bault.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'BaultPHP Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'with' => [
                'stream' => 'php://stderr',
            ],
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],
    ],

];
