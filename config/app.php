<?php

return [

    'name' => env('APP_NAME', 'BaultFrame'),
    'env' => env('APP_ENV', 'local'),
    'debug' => (bool) env('APP_DEBUG', true),

    'url' => env('APP_URL', 'http://localhost'),
    'asset_url' => env('ASSET_URL', null),

    'timezone' => 'Asia/Ho_Chi_Minh',
    'locale' => 'vi',
    'fallback_locale' => 'en',

    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',

    'providers' => [
        Illuminate\Events\EventServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,

        App\Providers\ConsoleServiceProvider::class,
    ],

    'aliases' => [
        'App'     => Illuminate\Support\Facades\App::class,
        'Event'   => Illuminate\Support\Facades\Event::class,
        'File'    => Illuminate\Support\Facades\File::class,
        'Config'  => Illuminate\Support\Facades\Config::class,
    ],
];