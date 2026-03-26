<?php

namespace App\Providers;

use Core\Application;
use Core\Http\Client\HttpClient;
use Psr\Log\LoggerInterface;

/**
 * HTTP Service Provider.
 * 
 * Registers HTTP client services.
 */
class HttpServiceProvider
{
    public function __construct(
        protected Application $app
    ) {
    }

    public function register(): void
    {
        // Register HTTP Client
        $this->app->bind(HttpClient::class, function ($app) {
            $config = [
                'timeout' => config('http.timeout', 30),
                'connect_timeout' => config('http.connect_timeout', 10),
                'verify' => config('http.verify', true),
            ];

            if ($proxy = config('http.proxy')) {
                $config['proxy'] = $proxy;
            }

            $client = new HttpClient(
                $app->make(LoggerInterface::class),
                $config
            );

            // Add retry middleware if enabled
            if (config('http.retry.enabled', true)) {
                $client->retry(
                    config('http.retry.times', 3),
                    config('http.retry.delay_ms', 100)
                );
            }

            // Add logging if enabled
            if (config('http.logging.enabled', true)) {
                $client->withLogging();
            }

            return $client;
        });
    }

    public function boot(): void
    {
        // Boot logic if needed
    }
}
