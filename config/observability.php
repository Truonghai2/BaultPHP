<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Observability Configuration
    |--------------------------------------------------------------------------
    |
    | Configure distributed tracing, metrics, and monitoring.
    |
    */

    // Production: tắt mặc định để tránh overhead (tracing/OTLP). Bật khi đã có collector.
    'enabled' => filter_var(env('OBSERVABILITY_ENABLED', env('APP_ENV', 'production') !== 'production'), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | OpenTelemetry Configuration
    |--------------------------------------------------------------------------
    */

    'otlp_endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://localhost:4318'),

    'service_name' => env('OTEL_SERVICE_NAME', 'baultframe'),

    'service_version' => env('OTEL_SERVICE_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Tracing Configuration
    |--------------------------------------------------------------------------
    */

    'tracing' => [
        'enabled' => filter_var(
            env('TRACING_ENABLED', env('APP_ENV', 'production') !== 'production'),
            FILTER_VALIDATE_BOOLEAN
        ),

        // Sample rate (0.0 - 1.0). Production: nên 0.01 hoặc 0 nếu không cần.
        'sample_rate' => (float) env('TRACING_SAMPLE_RATE', env('APP_ENV', 'production') === 'production' ? 0.0 : 1.0),

        // Trace HTTP requests
        'trace_http' => env('TRACE_HTTP', true),

        // Trace database queries
        'trace_database' => env('TRACE_DATABASE', true),

        // Trace CQRS operations
        'trace_cqrs' => env('TRACE_CQRS', true),

        // Trace cache operations
        'trace_cache' => env('TRACE_CACHE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Configuration
    |--------------------------------------------------------------------------
    */

    'metrics' => [
        'enabled' => env('METRICS_ENABLED', true),

        // Metrics endpoint
        'endpoint' => env('METRICS_ENDPOINT', '/metrics'),

        // Metrics port (if separate from app)
        'port' => env('METRICS_PORT', 9091),

        // Record HTTP metrics
        'record_http' => env('METRICS_RECORD_HTTP', true),

        // Record database metrics
        'record_database' => env('METRICS_RECORD_DATABASE', true),

        // Record CQRS metrics
        'record_cqrs' => env('METRICS_RECORD_CQRS', true),

        // Record cache metrics
        'record_cache' => env('METRICS_RECORD_CACHE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Jaeger Configuration
    |--------------------------------------------------------------------------
    */

    'jaeger' => [
        'enabled' => env('JAEGER_ENABLED', true),
        'host' => env('JAEGER_HOST', 'localhost'),
        'port' => env('JAEGER_PORT', 16686),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prometheus Configuration
    |--------------------------------------------------------------------------
    */

    'prometheus' => [
        'enabled' => env('PROMETHEUS_ENABLED', true),
        'host' => env('PROMETHEUS_HOST', 'localhost'),
        'port' => env('PROMETHEUS_PORT', 9090),
        'namespace' => env('PROMETHEUS_NAMESPACE', 'baultframe'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Grafana Configuration
    |--------------------------------------------------------------------------
    */

    'grafana' => [
        'enabled' => env('GRAFANA_ENABLED', true),
        'host' => env('GRAFANA_HOST', 'localhost'),
        'port' => env('GRAFANA_PORT', 3000),
        'admin_password' => env('GRAFANA_ADMIN_PASSWORD', 'admin'),
    ],
];
