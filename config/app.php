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

    'rpc_secret_token' => env('RPC_SECRET_TOKEN', 'baultPHP'),

    'providers' => [
        \App\Providers\AppServiceProvider::class,
        \App\Providers\RouteServiceProvider::class,
        \App\Providers\ConsoleServiceProvider::class,
        \App\Providers\DatabaseServiceProvider::class,
        \App\Providers\FilesystemServiceProvider::class,
        \App\Providers\LoggingServiceProvider::class,
        \App\Providers\EventServiceProvider::class,
        \Core\CQRS\CqrsServiceProvider::class,
        \App\Providers\CommandDecoratorServiceProvider::class,
        \App\Providers\RedisServiceProvider::class,
        \App\Providers\ViewServiceProvider::class,
        \App\Providers\MailServiceProvider::class,
        \App\Providers\ServerServiceProvider::class,
        \Core\Queue\QueueServiceProvider::class,
        \App\Providers\TranslationServiceProvider::class,
    ],

    'commands' => [
        \Core\Console\Commands\Server\ServerReloadCommand::class,
        \Core\Console\Commands\Server\ServerStopCommand::class,
        \Core\Console\Commands\KeyGenerateCommand::class,
    ],

    'aliases' => [
        'App'     => Illuminate\Support\Facades\App::class,
        'Event'   => Illuminate\Support\Facades\Event::class,
        'File'    => Illuminate\Support\Facades\File::class,
        'Config'  => Illuminate\Support\Facades\Config::class,
        'Log'     => Core\Support\Facades\Log::class,
        'Queue'   => Core\Support\Facades\Queue::class,
        'Mail'    => Core\Support\Facades\Mail::class,
    ],
];
