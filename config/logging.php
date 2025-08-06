<?php

return [
    'default' => 'file',
    'channels' => [
        'file' => [
            'driver' => 'single',
            'path' => storage_path('logs/app.log'), // Ghi vào storage/logs/app.log
            'level' => 'debug',
        ],
    ],
];
