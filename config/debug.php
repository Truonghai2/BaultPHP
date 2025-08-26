<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Khi được bật, framework sẽ thu thập dữ liệu chi tiết về mỗi request
    | và hiển thị một thanh debug bar. Tính năng này chỉ nên được bật
    | trong môi trường local hoặc development.
    |
    */
    'enabled' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Dữ liệu debug sẽ hết hạn sau bao nhiêu giây trong Redis.
    |--------------------------------------------------------------------------
    */
    'expiration' => 3600, // 1 giờ
];
