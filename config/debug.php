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

    /*
    |--------------------------------------------------------------------------
    | On-Demand Debugging
    |--------------------------------------------------------------------------
    |
    | If set to true, the debug bar will only be enabled when a specific
    | cookie (`X-DEBUG-ENABLED`) is present in the request. This is useful for
    | selectively debugging in a shared development environment without affecting
    | other users or services.
    |
    | Nếu được đặt thành true, thanh debug sẽ chỉ được bật khi có một
    | cookie cụ thể (`X-DEBUG-ENABLED`) trong request. Điều này hữu ích để
    | debug có chọn lọc trong môi trường dev mà không ảnh hưởng đến người dùng khác.
    |
    */
    'on_demand' => false,
];
