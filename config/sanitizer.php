<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Log Sanitization Keys
    |--------------------------------------------------------------------------
    |
    | Mảng này chứa các key sẽ bị kiểm duyệt khỏi log của ứng dụng.
    | Bạn có thể thêm bất kỳ key nào có thể chứa thông tin nhạy cảm vào đây.
    | Việc so sánh không phân biệt chữ hoa, chữ thường.
    |
    */
    'keys' => [
        'password',
        'password_confirmation',
        'token',
        'api_key',
        'secret',
        'authorization',
        'php-auth-pw',
        'credit_card_number',
    ],
];
