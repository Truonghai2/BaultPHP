<?php

namespace App\Providers;

use Core\Contracts\Http\Kernel as KernelContract;
use Core\Http\FormRequest;
use Core\Redis\FiberRedisManager;
use Core\Services\HealthCheckService;
use Core\Support\ServiceProvider;
use Core\WebSocket\CentrifugoAPIService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KernelContract::class, \App\Http\Kernel::class);

        $this->app->singleton(CentrifugoAPIService::class, function () {
            $apiUrl = config('services.centrifugo.api_url', 'http://127.0.0.1:8000');
            $apiKey = config('services.centrifugo.api_key');

            if (is_null($apiKey)) {
                throw new \InvalidArgumentException('Centrifugo API key is not configured.');
            }

            return new CentrifugoAPIService($apiUrl, $apiKey);
        });

        $this->app->singleton(FiberRedisManager::class);

        $this->app->singleton(HealthCheckService::class);

        $this->configureFormRequestValidation();
    }

    protected function configureFormRequestValidation(): void
    {
        $this->app->afterResolving(FormRequest::class, function (FormRequest $request) {
            $request->validateResolved();
        });
    }
}
