<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Đây là disk lưu trữ mặc định sẽ được sử dụng khi không có disk nào
    | được chỉ định.
    |
    */
    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Tại đây bạn có thể cấu hình bao nhiêu "disk" lưu trữ tùy thích. BaultPHP
    | hỗ trợ sẵn driver "local". Bạn có thể thêm các driver khác cho S3, FTP...
    |
    | Ví dụ:
    | 's3' => [
    |     'driver' => 's3',
    |     'key' => env('AWS_ACCESS_KEY_ID'),
    |     'secret' => env('AWS_SECRET_ACCESS_KEY'),
    |     'region' => env('AWS_DEFAULT_REGION'),
    |     'bucket' => env('AWS_BUCKET'),
    |     'url' => env('AWS_URL'),
    |     'endpoint' => env('AWS_ENDPOINT'),
    | ],
    |
    */
    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Tại đây bạn có thể cấu hình các liên kết tượng trưng (symbolic links)
    | cần được tạo khi chạy lệnh `storage:link`.
    |
    */
    'links' => [
        public_path('storage') => storage_path('app/uploads'),
    ],

];
