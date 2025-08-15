<?php

namespace App\Providers;

use Core\Contracts\Support\DeferrableProvider;
use Core\Metrics\SwooleMetricsService;
use Core\Support\ServiceProvider;

class MetricsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(SwooleMetricsService::class, function () {
            return new SwooleMetricsService();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [SwooleMetricsService::class];
    }
}
