<?php

namespace App\Providers;

use Core\Contracts\Support\DeferrableProvider;
use Core\Metrics\SwooleMetricsService;
use Core\Observability\AnomalyDetector;
use Core\Observability\OpenTelemetryTracer;
use Core\Support\ServiceProvider;

class MetricsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(SwooleMetricsService::class, function () {
            return new SwooleMetricsService();
        });

        // Register OpenTelemetry Tracer
        $this->app->singleton(OpenTelemetryTracer::class, function ($app) {
            $config = config('observability.opentelemetry', []);
            return new OpenTelemetryTracer($config);
        });

        // Register Anomaly Detector
        $this->app->singleton(AnomalyDetector::class, function ($app) {
            $config = config('observability.anomaly_detection', []);
            return new AnomalyDetector($config);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            SwooleMetricsService::class,
            OpenTelemetryTracer::class,
            AnomalyDetector::class,
        ];
    }
}
