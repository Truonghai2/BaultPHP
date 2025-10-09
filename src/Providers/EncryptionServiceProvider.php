<?php

namespace App\Providers;

use Core\Encryption\Encrypter;
use Core\Support\ServiceProvider;
use RuntimeException;

class EncryptionServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Encrypter::class, function ($app) {
            $config = $app->make('config')->get('app');

            $key = $config['key'];
            $cipher = $config['cipher'] ?? 'AES-256-CBC';

            if (empty($key)) {
                throw new RuntimeException(
                    'No application encryption key has been specified. Please run `php cli key:generate` or set APP_KEY in your .env file.',
                );
            }

            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7));
            }

            return new Encrypter($key, $cipher);
        });

        $this->app->alias(Encrypter::class, 'encrypter');
    }
}
