<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CORS (Cross-Origin Resource Sharing) Options
    |--------------------------------------------------------------------------
    |
    | Tại đây bạn có thể cấu hình các thiết lập để xử lý các request từ
    | các domain khác (cross-origin).
    | Bạn có thể tìm hiểu thêm tại: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Đọc các domain được phép từ file .env.
    // Ví dụ: CORS_ALLOWED_ORIGINS=http://localhost:5173,http://your-app.com
    // Sử dụng array_map('trim', ...) để loại bỏ các khoảng trắng thừa, ví dụ: "domain1, domain2"
    'allowed_origins' => array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'X-CSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
