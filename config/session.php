<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    |
    | BaultPHP hỗ trợ nhiều driver session khác nhau. Bạn có thể chọn
    | driver mặc định sẽ được sử dụng cho các request.
    |
    | Hỗ trợ: "file", "cookie", "database", "redis", "array"
    |
    */

    'driver' => env('SESSION_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    |
    | Thời gian (tính bằng phút) mà session sẽ được duy trì. Nếu người dùng
    | không hoạt động trong khoảng thời gian này, session sẽ hết hạn.
    | Nếu bạn muốn session không bao giờ hết hạn, hãy đặt một giá trị lớn.
    |
    */

    'lifetime' => env('SESSION_LIFETIME', 120),

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Name
    |--------------------------------------------------------------------------
    |
    | Tên của cookie được sử dụng để định danh session. Tên này nên là
    | một chuỗi duy nhất và khó đoán để tránh xung đột.
    |
    */

    'cookie' => env('SESSION_COOKIE', 'bault_session'),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Path
    |--------------------------------------------------------------------------
    |
    | Đường dẫn mà cookie session sẽ có hiệu lực. Mặc định là "/",
    | nghĩa là cookie sẽ có sẵn trên toàn bộ domain của bạn.
    |
    */

    'path' => '/',

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Domain
    |--------------------------------------------------------------------------
    |
    | Domain mà cookie sẽ có hiệu lực. Để cookie hoạt động trên tất cả
    | các subdomain, hãy đặt giá trị này là ".yourdomain.com".
    |
    */

    'domain' => env('SESSION_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | HTTPS Only Cookies (Secure)
    |--------------------------------------------------------------------------
    |
    | Khi được đặt là `true`, cookie session sẽ chỉ được gửi qua kết nối
    | HTTPS. Điều này giúp ngăn chặn kẻ tấn công đọc cookie nếu họ có thể
    | nghe lén lưu lượng mạng. Bắt buộc bật `true` trong môi trường production.
    |
    */

    'secure' => env('SESSION_SECURE_COOKIE', true),

    /*
    |--------------------------------------------------------------------------
    | HTTP Only Cookies
    |--------------------------------------------------------------------------
    |
    | Khi được đặt là `true`, cookie sẽ không thể được truy cập thông qua
    | JavaScript. Đây là một biện pháp bảo mật quan trọng để chống lại
    | các cuộc tấn công XSS.
    |
    */

    'http_only' => true,

    /*
    |--------------------------------------------------------------------------
    | Same-Site Cookies
    |--------------------------------------------------------------------------
    |
    | Thuộc tính này giúp giảm thiểu rủi ro từ các cuộc tấn công CSRF.
    |
    | Hỗ trợ: "lax", "strict", "none", null
    | "lax" là một giá trị cân bằng tốt cho hầu hết các ứng dụng.
    | "none" yêu cầu phải đặt 'secure' là true.
    |
    */

    'same_site' => 'lax',

    /*
    |--------------------------------------------------------------------------
    | File Session Driver
    |--------------------------------------------------------------------------
    |
    | Khi sử dụng driver "file", đây là đường dẫn nơi các file session
    | sẽ được lưu trữ.
    |
    */

    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Database Session Driver
    |--------------------------------------------------------------------------
    |
    | Khi sử dụng driver "database", đây là tên bảng và kết nối CSDL
    | sẽ được sử dụng.
    |
    */

    'table' => 'sessions',

    'database_connection' => env('SESSION_DB_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Redis Session Driver
    |--------------------------------------------------------------------------
    |
    | Khi sử dụng driver "redis", đây là tên kết nối Redis sẽ được sử dụng.
    |
    */

    'connection' => env('SESSION_REDIS_CONNECTION', 'default'),
];
