<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CORS Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Danh sách các origin (domain) được phép gửi cross-origin requests.
    | Bạn có thể thêm/xóa origins trực tiếp trong file này.
    |
    | Hỗ trợ:
    | - Exact match: 'https://example.com'
    | - Subdomain wildcard: '*.example.com' (cho phép app.example.com, api.example.com, ...)
    | - All origins: '*' (KHÔNG KHUYẾN NGHỊ cho production khi dùng credentials)
    |
    */

    'allowed' => [
        // Development
        'http://localhost:3000',
        'http://localhost:5173',  // Vite default
        'http://localhost:8080',  // Vue CLI default
        'http://localhost:4200',  // Angular default
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',

        // Staging (nếu có)
        // 'https://staging.yourdomain.com',

        // Production (thêm domain thật của bạn vào đây)
        // 'https://yourdomain.com',
        // 'https://www.yourdomain.com',
        // '*.yourdomain.com',  // Cho phép tất cả subdomain

        // Đọc thêm từ .env (optional)
        ...array_filter(
            array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', ''))),
            fn($origin) => !empty($origin)
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Origin Validation Rules
    |--------------------------------------------------------------------------
    |
    | Các quy tắc bổ sung để validate origins.
    |
    */

    'rules' => [
        // Chặn các protocol không an toàn trong production
        'block_insecure_in_production' => true,

        // Chặn IP addresses (chỉ cho phép domain names)
        'block_ip_addresses' => false,

        // Yêu cầu HTTPS trong production
        'require_https_in_production' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Origin Patterns
    |--------------------------------------------------------------------------
    |
    | Các pattern phức tạp hơn sử dụng regex.
    | Chỉ dùng nếu bạn cần logic phức tạp.
    |
    */

    'patterns' => [
        // Ví dụ: cho phép tất cả subdomain của example.com
        // '/^https?:\/\/([a-z0-9\-]+\.)?example\.com$/',

        // Ví dụ: cho phép các port 3000-3999 trên localhost
        // '/^http:\/\/localhost:3[0-9]{3}$/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Cache danh sách origins đã validate để cải thiện hiệu suất.
    |
    */

    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'key' => 'cors_allowed_origins',
    ],
];