<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Component File Uploads
    |--------------------------------------------------------------------------
    |
    | Cấu hình các quy tắc validation cho việc upload file từ các component.
    |
    */

    // Kích thước file tối đa tính bằng kilobytes (KB). 10240 KB = 10 MB.
    'max_size_kb' => env('UPLOAD_MAX_SIZE_KB', 10240),

    // Danh sách các loại MIME được phép.
    // Để trống mảng để cho phép mọi loại file (KHÔNG KHUYẾN KHÍCH).
    'allowed_mimes' => [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', // Images
        'application/pdf', // Documents
        'video/mp4', 'video/quicktime', 'video/x-msvideo', // Videos
    ],
];
