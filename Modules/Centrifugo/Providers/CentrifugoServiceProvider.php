<?php

namespace Modules\Centrifugo\Providers;

use Core\Support\ServiceProvider;
use GuzzleHttp\Client as GuzzleClient;
use InvalidArgumentException;
use Modules\Centrifugo\Infrastructure\Services\CentrifugoAPIService;
use Psr\Log\LoggerInterface;

class CentrifugoServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(CentrifugoAPIService::class, function ($app) {
            $apiUrl = config('centrifugo.api_url');
            $apiKey = config('centrifugo.api_key');

            if (empty($apiKey) || empty($apiUrl)) {
                throw new InvalidArgumentException('Centrifugo API URL or API Key is not configured in config/centrifugo.php or .env file.');
            }

            return new CentrifugoAPIService(
                $apiUrl,
                $apiKey,
                $app->make(GuzzleClient::class),
                $app->make(LoggerInterface::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../config/centrifugo.php', 'centrifugo');
    }
}
