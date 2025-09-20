<?php

return [
    'name' => env('APP_NAME', 'BaultPHP'),

    'env' => env('APP_ENV', 'local'),

    'debug' => (bool) env('APP_DEBUG', true),

    'editor' => env('EDITOR', null),

    'url' => env('APP_URL', 'http://localhost:9501'),
    'asset_url' => env('ASSET_URL', null),

    'timezone' => 'Asia/Ho_Chi_Minh',
    'locale' => 'en',
    'fallback_locale' => 'en',

    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',

    'rpc_secret_token' => env('RPC_SECRET_TOKEN', 'baultPHP'),

    'developer_ips' => env('DEVELOPER_IPS', ''),

    'providers' => [
        \App\Providers\AppServiceProvider::class,
        \App\Providers\ViewServiceProvider::class,
        \App\Providers\EncryptionServiceProvider::class,
        \App\Providers\HashServiceProvider::class,
        \App\Providers\AuthServiceProvider::class,
        \App\Providers\RouteServiceProvider::class,
        \App\Providers\FilesystemServiceProvider::class,
        \App\Providers\ConsoleServiceProvider::class,
        \App\Providers\DatabaseServiceProvider::class,
        \App\Providers\EventServiceProvider::class,
        \App\Providers\RedisServiceProvider::class,
        \App\Providers\LoggingServiceProvider::class,
        \App\Providers\MailServiceProvider::class,
        \App\Providers\ServerServiceProvider::class,
        \App\Providers\StatefulServiceProvider::class,
        \App\Providers\SessionServiceProvider::class,
        \Core\Queue\QueueServiceProvider::class,
        \App\Providers\TranslationServiceProvider::class,
        \App\Providers\CacheServiceProvider::class,
        \App\Providers\FeatureServiceProvider::class,
        \App\Providers\ScheduleServiceProvider::class,
    ],

    'aliases' => [
        'App'     => Illuminate\Support\Facades\App::class,
        'Event'   => Illuminate\Support\Facades\Event::class,
        'File'    => Illuminate\Support\Facades\File::class,
        'Config'  => Illuminate\Support\Facades\Config::class,
        'Log'     => Core\Support\Facades\Log::class,
        'Gate'    => Core\Support\Facades\Gate::class,
        'Hash'    => Core\Support\Facades\Hash::class,
        'Storage' => Core\Support\Facades\Storage::class,
        'Queue'   => Core\Support\Facades\Queue::class,
        'Mail'    => Core\Support\Facades\Mail::class,
        'Feature' => Core\Support\Facades\Feature::class,
    ],
];
