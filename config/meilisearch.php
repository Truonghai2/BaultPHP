<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kết nối Meilisearch mặc định
    |--------------------------------------------------------------------------
    |
    | Tại đây bạn có thể chỉ định kết nối Meilisearch nào sẽ được sử dụng
    | làm mặc định cho tất cả các công việc tìm kiếm.
    |
    */

    'default' => env('MEILISEARCH_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Các kết nối Meilisearch
    |--------------------------------------------------------------------------
    |
    | Đây là nơi cấu hình cho từng kết nối Meilisearch trong ứng dụng của bạn.
    |
    */

    'connections' => [
        'default' => [
            'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
            'key' => env('MEILISEARCH_KEY'),
        ],
    ],
];
