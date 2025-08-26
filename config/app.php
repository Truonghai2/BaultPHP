<?php

return [
    'name' => env('APP_NAME', 'BaultPHP'),

    'env' => env('APP_ENV', 'local'),

    'debug' => (bool) env('APP_DEBUG', true),

    'editor' => env('EDITOR', null),

    'url' => env('APP_URL', 'http://localhost:9501'),
    'asset_url' => env('ASSET_URL', null),

    // This URL is used by the `vite()` helper to generate the correct URLs
    // for your assets when running in development mode with a dev server.
    // It should point to the Vite container from the perspective of the PHP container.
    'vite_dev_url' => env('VITE_DEV_URL', 'http://localhost:5173'),

    'timezone' => 'Asia/Ho_Chi_Minh',
    'locale' => 'en',
    'fallback_locale' => 'en',

    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',

    'rpc_secret_token' => env('RPC_SECRET_TOKEN', 'baultPHP'),

    'providers' => [
        \App\Providers\AppServiceProvider::class,
        \App\Providers\ViewServiceProvider::class,
        \App\Providers\EncryptionServiceProvider::class,
        \App\Providers\HashServiceProvider::class,
        \App\Providers\AuthServiceProvider::class,
        \App\Providers\RouteServiceProvider::class,
        \App\Providers\ConsoleServiceProvider::class,
        \App\Providers\DatabaseServiceProvider::class,
        \App\Providers\FilesystemServiceProvider::class,
        \App\Providers\EventServiceProvider::class,
        \App\Providers\RedisServiceProvider::class,
        \App\Providers\LoggingServiceProvider::class,
        \App\Providers\MailServiceProvider::class,
        \App\Providers\ServerServiceProvider::class,
        \App\Providers\SessionServiceProvider::class,
        \Core\Queue\QueueServiceProvider::class,
        \App\Providers\MeilisearchServiceProvider::class,
        \App\Providers\TranslationServiceProvider::class,
    ],

    'aliases' => [
        'App'     => Illuminate\Support\Facades\App::class,
        'Event'   => Illuminate\Support\Facades\Event::class,
        'File'    => Illuminate\Support\Facades\File::class,
        'Config'  => Illuminate\Support\Facades\Config::class,
        'Log'     => Core\Support\Facades\Log::class,
        'Gate'    => Core\Support\Facades\Gate::class,
        'Hash'    => Core\Support\Facades\Hash::class,
        'Queue'   => Core\Support\Facades\Queue::class,
        'Mail'    => Core\Support\Facades\Mail::class,
    ],
];
