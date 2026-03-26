<?php

namespace App\Providers;

use Core\Application;
use Core\Observability\OpenTelemetryTracer;
use Core\Observability\PrometheusMetrics;

/**
 * Observability Service Provider.
 * 
 * Registers OpenTelemetry tracing and Prometheus metrics.
 */
class ObservabilityServiceProvider
{
    public function __construct(
        protected Application $app
    ) {
    }

    public function register(): void
    {
        // Register OpenTelemetry Tracer
        $this->app->singleton(OpenTelemetryTracer::class, function ($app) {
            return new OpenTelemetryTracer(
                serviceName: config('app.name', 'baultframe'),
                serviceVersion: config('app.version', '1.0.0'),
                otlpEndpoint: config('observability.otlp_endpoint'),
            );
        });

        // Register Prometheus Metrics
        $this->app->singleton(PrometheusMetrics::class, function ($app) {
            return new PrometheusMetrics();
        });

        // Alias for easy access
        $this->app->alias(OpenTelemetryTracer::class, 'tracer');
        $this->app->alias(PrometheusMetrics::class, 'metrics');
    }

    public function boot(): void
    {
        // Boot logic if needed
    }
}
