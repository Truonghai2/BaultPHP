<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Centrifugo JWT Secret Key
    |--------------------------------------------------------------------------
    |
    | Khóa bí mật này được sử dụng để ký các connection token (JWT) cho client.
    | Giá trị này PHẢI TRÙNG KHỚP với 'token_hmac_secret_key' trong file
    | cấu hình của Centrifugo server.
    |
    */
    'secret' => env('JWT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Token Lifetime
    |--------------------------------------------------------------------------
    |
    | Thời gian sống của connection token, tính bằng giây. Sau khoảng thời gian
    | này, client sẽ cần phải lấy một token mới.
    |
    */
    'lifetime' => (int) env('CENTRIFUGO_TOKEN_LIFETIME', 3600), // 1 hour

    /*
    |--------------------------------------------------------------------------
    | Centrifugo Server API
    |--------------------------------------------------------------------------
    |
    | Các thông tin này được sử dụng bởi CentrifugoAPIService để gửi các
    | lệnh từ backend của bạn đến Centrifugo server, ví dụ như publish
    | một message vào một channel.
    |
    */

    // URL của Centrifugo API endpoint.
    // Thường là http://localhost:8000/api
    'api_url' => env('CENTRIFUGO_API_URL'),

    // API key để xác thực với Centrifugo server.
    // Giá trị này PHẢI TRÙNG KHỚP với 'api_key' trong file cấu hình
    // của Centrifugo server.
    'api_key' => env('CENTRIFUGO_API_KEY'),
];
