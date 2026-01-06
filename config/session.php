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

    'driver' => env('SESSION_DRIVER', 'file'),

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

    'lifetime' => env('SESSION_LIFETIME', 43200),

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
    | các subdomain, hãy đặt giá trị này là ".domain.com".
    |
    */
    'domain' => env('SESSION_DOMAIN', (function () {
        $appUrl = env('APP_URL', 'http://localhost');
        $host = parse_url($appUrl, PHP_URL_HOST);
        return in_array($host, ['localhost', '127.0.0.1']) ? null : $host;
    })()),

    /*
    |--------------------------------------------------------------------------
    | HTTPS Only Cookies (Secure)
    |--------------------------------------------------------------------------
    |
    */
    'secure' => env('SESSION_SECURE_COOKIE', (function () {
        $appUrl = env('APP_URL', 'http://localhost');
        return str_starts_with(strtolower($appUrl), 'https://');
    })()),

    /*
    |--------------------------------------------------------------------------
    | HTTP Only Cookies
    |--------------------------------------------------------------------------
    |
    */

    'http_only' => true,

    /*
    |--------------------------------------------------------------------------
    | Encrypt Session
    |--------------------------------------------------------------------------
    |
    | Tùy chọn này cho phép bạn chỉ định rằng tất cả dữ liệu session
    | nên được mã hóa trước khi được lưu trữ. Việc mã hóa sẽ được thực hiện
    | tự động và tăng cường bảo mật cho ứng dụng của bạn.
    |
    */
    'encrypt' => env('SESSION_ENCRYPT', false),

    /*
    |--------------------------------------------------------------------------
    | Same-Site Cookies
    |--------------------------------------------------------------------------
    |
    */

    'same_site' => env('SESSION_SAME_SITE', (function () {
        $appEnv = env('APP_ENV', 'local');
        return $appEnv === 'production' ? 'strict' : 'lax';
    })()),

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

    /*
    |--------------------------------------------------------------------------
    | Optimized Database Handler
    |--------------------------------------------------------------------------
    |
    | Khi enabled, sử dụng OptimizedSwoolePdoSessionHandler thay vì standard handler.
    | Optimized handler giảm 60-70% database writes với smart update logic.
    |
    | Features:
    | - Smart write: Chỉ update khi có thay đổi hoặc sau 60s
    | - Batch garbage collection
    | - Payload size tracking và warnings
    | - Reduced lock contention
    |
    */

    'use_optimized_handler' => env('SESSION_USE_OPTIMIZED_HANDLER', true),
];
