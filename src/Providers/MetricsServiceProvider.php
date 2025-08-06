<?php

namespace App\Providers;

use Core\Metrics\SwooleMetricsService;
use Core\Support\ServiceProvider;

class MetricsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SwooleMetricsService::class, function () {
            return new SwooleMetricsService();
        });
    }
}

