<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | Tùy chọn này kiểm soát driver hash mặc định sẽ được sử dụng để
    | hash mật khẩu cho ứng dụng của bạn. Mặc định, thuật toán bcrypt
    | được sử dụng.
    |
    | Hỗ trợ: "bcrypt", "argon" (sử dụng Argon2i)
    |
    */
    'driver' => env('HASH_DRIVER', 'argon'),

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Hashing Options
    |--------------------------------------------------------------------------
    |
    | Tại đây bạn có thể cấu hình cost factor cho thuật toán Bcrypt.
    | Điều này kiểm soát lượng tài nguyên CPU được tiêu thụ khi hash mật khẩu.
    | Giá trị càng cao, hash càng an toàn nhưng càng tốn thời gian.
    |
    */
    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon2 Hashing Options
    |--------------------------------------------------------------------------
    |
    | Tại đây bạn có thể cấu hình các tham số cho thuật toán Argon2i.
    | Argon2 là thuật toán hiện đại, an toàn và được khuyến khích sử dụng.
    | Yêu cầu PHP phải được biên dịch với hỗ trợ Argon2.
    |
    */
    'argon' => [
        'memory' => env('ARGON_MEMORY_COST', 65536), // Kilobytes
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME_COST', 4),
    ],
];
