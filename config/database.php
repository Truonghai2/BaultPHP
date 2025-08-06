<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tên Kết Nối Cơ Sở Dữ Liệu Mặc Định
    |--------------------------------------------------------------------------
    |
    | Tại đây, bạn có thể chỉ định kết nối CSDL nào sẽ được sử dụng làm
    | kết nối mặc định. Giá trị này sẽ được đọc từ biến DB_CONNECTION
    | trong file .env của bạn.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Các Kết Nối Cơ Sở Dữ Liệu
    |--------------------------------------------------------------------------
    |
    | Đây là nơi định nghĩa tất cả các kết nối CSDL cho ứng dụng của bạn.
    | BaultPHP hỗ trợ nhiều loại CSDL khác nhau, và bạn có thể thêm
    | các cấu hình cho chúng tại đây.
    |
    */

    'connections' => [

        'mysql' => [
            'write' => [
                'host' => env('DB_HOST', '127.0.0.1'), // Primary DB
            ],
            'read' => [
                'host' => env('DB_HOST', '127.0.0.1'), // Replica DB
            ],
            'driver'    => 'mysql',
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_DATABASE', 'bault'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'options' => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'bault'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'schema' => 'public',
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            // Đối với SQLite dựa trên file:
            // 'database' => env('DB_DATABASE', database_path('database.sqlite')),
            // Đối với SQLite trong bộ nhớ (lý tưởng cho testing):
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cấu hình Migration
    |--------------------------------------------------------------------------
    |
    | Bạn có thể thay đổi tên bảng được sử dụng để lưu trữ lịch sử migration.
    |
    */
    'migrations' => [
        'enabled' => true,
        'table' => 'migrations',
        'paths' => [],
    ],
];
