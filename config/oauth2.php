<?php

return [
    // Đường dẫn tới private key để ký JWT.
    // Chạy `php cli oauth:keys` để tạo key.
    'private_key' => storage_path('oauth-private.key'),

    // Đường dẫn tới public key để xác thực JWT.
    'public_key' => storage_path('oauth-public.key'),

    // Khóa mã hóa để bảo vệ auth codes.
    'encryption_key' => env('OAUTH_ENCRYPTION_KEY'),

    // Thời gian sống của access token (ví dụ: 1 giờ).
    'access_token_ttl' => 'PT1H',

    // Thời gian sống của refresh token (ví dụ: 1 tháng).
    'refresh_token_ttl' => 'P1M',

    // Thời gian sống của auth code (ví dụ: 10 phút).
    'auth_code_ttl' => 'PT10M',
];
