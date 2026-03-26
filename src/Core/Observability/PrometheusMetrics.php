<?php

namespace Core\Observability;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;
use Prometheus\RenderTextFormat;

/**
 * Prometheus Metrics Collector.
 * 
 * Exposes application metrics in Prometheus format.
 * 
 * Metrics:
 * - HTTP request duration
 * - HTTP request count
 * - Database query duration
 * - Cache hit/miss rate
 * - CQRS command/query duration
 * - Custom business metrics
 */
class PrometheusMetrics
{
    private CollectorRegistry $registry;
    private string $namespace = 'baultframe';

    public function __construct(?CollectorRegistry $registry = null)
    {
        $this->registry = $registry ?? $this->createRegistry();
    }

    /**
     * Create registry with appropriate storage.
     */
    protected function createRegistry(): CollectorRegistry
    {
        // Use Redis if available, otherwise in-memory
        if (extension_loaded('redis') && env('REDIS_HOST')) {
            Redis::setDefaultOptions([
                'host' => env('REDIS_HOST', 'localhost'),
                'port' => (int) env('REDIS_PORT', 6379),
                'timeout' => 0.1,
            ]);
            
            return new CollectorRegistry(new Redis());
        }

        return new CollectorRegistry(new InMemory());
    }

    /**
     * Record HTTP request.
     */
    public function recordHttpRequest(
        string $method,
        string $path,
        int $statusCode,
        float $durationMs
    ): void {
        // Request duration histogram
        $histogram = $this->registry->getOrRegisterHistogram(
            $this->namespace,
            'http_request_duration_milliseconds',
            'HTTP request duration in milliseconds',
            ['method', 'path', 'status'],
            [10, 50, 100, 250, 500, 1000, 2500, 5000, 10000]
        );

        $histogram->observe(
            $durationMs,
            [$method, $this->normalizePath($path), (string) $statusCode]
        );

        // Request counter
        $counter = $this->registry->getOrRegisterCounter(
            $this->namespace,
            'http_requests_total',
            'Total HTTP requests',
            ['method', 'path', 'status']
        );

        $counter->inc([$method, $this->normalizePath($path), (string) $statusCode]);
    }

    /**
     * Record database query.
     */
    public function recordDatabaseQuery(string $operation, float $durationMs): void
    {
        $histogram = $this->registry->getOrRegisterHistogram(
            $this->namespace,
            'database_query_duration_milliseconds',
            'Database query duration in milliseconds',
            ['operation'],
            [1, 5, 10, 25, 50, 100, 250, 500, 1000]
        );

        $histogram->observe($durationMs, [$operation]);

        // Query counter
        $counter = $this->registry->getOrRegisterCounter(
            $this->namespace,
            'database_queries_total',
            'Total database queries',
            ['operation']
        );

        $counter->inc([$operation]);
    }

    /**
     * Record cache access.
     */
    public function recordCacheAccess(string $operation, bool $hit): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            $this->namespace,
            'cache_operations_total',
            'Total cache operations',
            ['operation', 'result']
        );

        $counter->inc([$operation, $hit ? 'hit' : 'miss']);
    }

    /**
     * Record CQRS command/query.
     */
    public function recordCqrsOperation(
        string $type,
        string $name,
        float $durationMs,
        bool $success
    ): void {
        $histogram = $this->registry->getOrRegisterHistogram(
            $this->namespace,
            'cqrs_operation_duration_milliseconds',
            'CQRS operation duration in milliseconds',
            ['type', 'name'],
            [5, 10, 25, 50, 100, 250, 500, 1000, 2500]
        );

        $histogram->observe($durationMs, [$type, $name]);

        // Operation counter
        $counter = $this->registry->getOrRegisterCounter(
            $this->namespace,
            'cqrs_operations_total',
            'Total CQRS operations',
            ['type', 'name', 'status']
        );

        $counter->inc([$type, $name, $success ? 'success' : 'failure']);
    }

    /**
     * Set gauge metric.
     */
    public function setGauge(string $name, string $help, float $value, array $labels = []): void
    {
        $gauge = $this->registry->getOrRegisterGauge(
            $this->namespace,
            $name,
            $help,
            array_keys($labels)
        );

        $gauge->set($value, array_values($labels));
    }

    /**
     * Increment counter.
     */
    public function incrementCounter(string $name, string $help, array $labels = []): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            $this->namespace,
            $name,
            $help,
            array_keys($labels)
        );

        $counter->inc(array_values($labels));
    }

    /**
     * Record histogram value.
     */
    public function recordHistogram(
        string $name,
        string $help,
        float $value,
        array $labels = [],
        ?array $buckets = null
    ): void {
        $buckets = $buckets ?? [0.1, 0.5, 1, 2.5, 5, 10];

        $histogram = $this->registry->getOrRegisterHistogram(
            $this->namespace,
            $name,
            $help,
            array_keys($labels),
            $buckets
        );

        $histogram->observe($value, array_values($labels));
    }

    /**
     * Render metrics in Prometheus format.
     */
    public function render(): string
    {
        $renderer = new RenderTextFormat();
        return $renderer->render($this->registry->getMetricFamilySamples());
    }

    /**
     * Get registry.
     */
    public function getRegistry(): CollectorRegistry
    {
        return $this->registry;
    }

    /**
     * Normalize path for metrics (remove IDs).
     */
    protected function normalizePath(string $path): string
    {
        // Replace UUIDs
        $path = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '{uuid}', $path);
        
        // Replace numeric IDs
        $path = preg_replace('/\/\d+/', '/{id}', $path);
        
        return $path;
    }
}
