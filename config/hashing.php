<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | Tùy chọn này kiểm soát driver hash mặc định sẽ được sử dụng để
    | hash mật khẩu cho ứng dụng của bạn.
    |
    | Hỗ trợ: "bcrypt", "argon" (Argon2i), "argon2id" (RECOMMENDED)
    |
    | Argon2id là sự lựa chọn TỐT NHẤT:
    | - Chiến thắng Password Hashing Competition 2015
    | - Kết hợp ưu điểm của Argon2i (chống side-channel) và Argon2d (chống GPU)
    | - Được khuyến nghị bởi OWASP và security experts
    |
    */
    'driver' => env('HASH_DRIVER', 'argon2id'),

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
        'rounds' => env('BCRYPT_ROUNDS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon2i Hashing Options (Legacy)
    |--------------------------------------------------------------------------
    |
    | Argon2i - chống side-channel attacks nhưng yếu hơn với GPU attacks.
    | Khuyến nghị nâng cấp lên Argon2id.
    |
    */
    'argon' => [
        'memory' => env('ARGON_MEMORY_COST', 65536),  // 64KB
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME_COST', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon2id Hashing Options (RECOMMENDED)
    |--------------------------------------------------------------------------
    |
    | Argon2id kết hợp ưu điểm của Argon2i và Argon2d:
    | - Chống side-channel attacks (như Argon2i)
    | - Chống GPU/ASIC attacks (như Argon2d)
    |
    | Khuyến nghị cấu hình:
    | - Development: memory=64KB, time=2 (~100-150ms)
    | - Production: memory=128KB, time=3 (~200-300ms)
    | - High Security: memory=256KB, time=4 (~400-500ms)
    |
    */
    'argon2id' => [
        'memory' => env('ARGON2ID_MEMORY_COST', 65536),  // 64KB for development
        'threads' => env('ARGON2ID_THREADS', 1),
        'time' => env('ARGON2ID_TIME_COST', 2),  // Balanced performance/security

        // Pepper (server-side secret key) - IMPORTANT FOR SECURITY
        // Generate: openssl rand -hex 32
        // Store in .env: HASH_PEPPER=your_secret_key_here
        'pepper' => env('HASH_PEPPER', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Risk-Based Hashing Profiles
    |--------------------------------------------------------------------------
    |
    | Adaptive hashing sử dụng các profile khác nhau dựa trên risk level:
    | - 'low': Regular users, development (fast)
    | - 'standard': Most users (balanced)
    | - 'high': Admin users (more secure)
    | - 'critical': Super admins (maximum security)
    |
    */
    'adaptive' => env('HASH_ADAPTIVE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Security Monitoring
    |--------------------------------------------------------------------------
    |
    | Giám sát các hoạt động hashing bất thường:
    | - Quá nhiều attempts từ cùng IP
    | - Thời gian hashing quá chậm
    | - Pattern tấn công brute-force
    |
    */
    'monitoring' => [
        'enabled' => env('HASH_MONITORING_ENABLED', true),
        'max_attempts_per_hour' => 10,
        'alert_threshold_ms' => 500,
    ],
];
