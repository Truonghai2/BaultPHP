<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Request Deduplication
    |--------------------------------------------------------------------------
    |
    | Tránh xử lý trùng lặp request giống nhau: một request xử lý, response
    | được cache và chia sẻ cho các request cùng signature (trong TTL).
    | Dùng cho route tốn tài nguyên (report, export), không dùng cho GET /
    | hoặc route high-throughput (đã có excluded_paths / included_paths).
    |
    */

    'enabled' => env('REQUEST_DEDUP_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    |
    | cache_only: Chỉ trả từ cache nếu hit. Nếu miss và không lấy được lock
    |   → xử lý bình thường (không chờ). Không serial hóa, phù hợp khi nhiều
    |   request đồng thời cùng signature.
    | coalesce: Một request xử lý, request cùng signature chờ (trong max_wait)
    |   rồi lấy từ cache. Giảm tải backend nhưng có thể tăng latency.
    |
    */

    'mode' => env('REQUEST_DEDUP_MODE', 'cache_only'),

    /*
    |--------------------------------------------------------------------------
    | Included Paths (opt-in)
    |--------------------------------------------------------------------------
    |
    | Nếu không rỗng: CHỈ các path khớp prefix mới được deduplicate.
    | Nếu rỗng: mọi GET (trừ excluded_paths) đều được deduplicate.
    | Ví dụ: ['/api/reports/', '/api/export/'] → chỉ deduplicate các route đó.
    |
    */

    'included_paths' => array_filter(explode(',', env('REQUEST_DEDUP_INCLUDED_PATHS', ''))),

    /*
    |--------------------------------------------------------------------------
    | Excluded Paths
    |--------------------------------------------------------------------------
    |
    | Paths không bao giờ deduplicate (prefix match). Luôn áp dụng cả khi
    | included_paths được dùng.
    |
    */

    'excluded_paths' => [
        '/',
        '/ping',
        '/assets',
        '/api/auth',
        '/api/logout',
        '/api/upload',
        '/api/stream',
        '/health',
        '/metrics',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    */

    'cache_ttl' => (int) env('REQUEST_DEDUP_CACHE_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Lock Timeout (seconds)
    |--------------------------------------------------------------------------
    */

    'lock_timeout' => (int) env('REQUEST_DEDUP_LOCK_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Max Wait (seconds) – chỉ dùng khi mode = coalesce
    |--------------------------------------------------------------------------
    */

    'max_wait' => (int) env('REQUEST_DEDUP_MAX_WAIT', 15),

    /*
    |--------------------------------------------------------------------------
    | Lock Wait Interval (milliseconds) – chỉ dùng khi mode = coalesce
    |--------------------------------------------------------------------------
    */

    'lock_wait_interval' => (int) env('REQUEST_DEDUP_WAIT_INTERVAL', 50),

    /*
    |--------------------------------------------------------------------------
    | Include User in Signature
    |--------------------------------------------------------------------------
    */

    'include_user' => env('REQUEST_DEDUP_INCLUDE_USER', false),

    /*
    |--------------------------------------------------------------------------
    | Include Headers in Signature
    |--------------------------------------------------------------------------
    */

    'include_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    */

    'cache_key_prefix' => env('REQUEST_DEDUP_CACHE_PREFIX', 'dedup:'),
];
