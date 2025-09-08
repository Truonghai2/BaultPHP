<?php

namespace App\Providers;

use Core\Contracts\Http\Kernel as KernelContract;
use Core\Database\CoroutineConnectionManager;
use Core\Database\CoroutineRedisManager;
use Core\Services\HealthCheckService;
use Core\Support\ServiceProvider;
use Core\WebSocket\CentrifugoAPIService;
use Psr\Log\LoggerInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KernelContract::class, \Http\Kernel::class);

        $this->app->singleton(CentrifugoAPIService::class, function () {
            $apiUrl = config('services.centrifugo.api_url', 'http://127.0.0.1:8000');
            $apiKey = config('services.centrifugo.api_key');

            if (is_null($apiKey)) {
                throw new \InvalidArgumentException('Centrifugo API key is not configured.');
            }

            return new CentrifugoAPIService($apiUrl, $apiKey);
        });

        $this->app->singleton(CoroutineConnectionManager::class, function ($app) {
            return new CoroutineConnectionManager($app->make(LoggerInterface::class));
        });

        $this->app->singleton(CoroutineRedisManager::class, function ($app) {
            return new CoroutineRedisManager($app->make(LoggerInterface::class));
        });

        $this->app->singleton(HealthCheckService::class);

        $this->app->singleton('hash', function () {
            return new \Core\Hashing\BcryptHasher();
        });
    }
}
