<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GraphQLite Configuration
    |--------------------------------------------------------------------------
    |
    | Tại đây, bạn định nghĩa các namespace mà GraphQLite sẽ quét để tự động
    | phát hiện các Query, Mutation, Type, và Factory.
    |
    | Việc tách biệt các namespace giúp GraphQLite hoạt động hiệu quả hơn
    | và giữ cho cấu trúc dự án của bạn rõ ràng.
    |
    */
    'namespaces' => [
        // Namespace cho các class định nghĩa Query và Mutation.
        // GraphQLite sẽ tìm các attribute #[Query] và #[Mutation] ở đây.
        'controllers' => [
            'Modules\\', // Quét tất cả các module
        ],

        // Namespace cho các class định nghĩa Type.
        // GraphQLite sẽ tìm các attribute #[Type] ở đây.
        'types' => [
            'Modules\\', // Quét tất cả các module
        ],
    ],
];
