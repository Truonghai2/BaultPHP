<?php

namespace Core\Observability\OpenTelemetry;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * OpenTelemetry Manager
 *
 * Centralized tracing, metrics, and logging management.
 * Provides automatic instrumentation for:
 * - HTTP requests
 * - Database queries
 * - Cache operations
 * - Queue jobs
 * - External API calls
 */
class TelemetryManager
{
    private TracerProviderInterface $tracerProvider;
    private TracerInterface $tracer;
    private ?SpanInterface $currentSpan = null;

    public function __construct(
        private string $serviceName,
        private string $serviceVersion,
        private ?string $exporterEndpoint = null,
    ) {
        $this->initialize();
    }

    /**
     * Initialize OpenTelemetry with OTLP exporter
     */
    private function initialize(): void
    {
        // Create resource with service information
        $resource = ResourceInfoFactory::defaultResource()->merge(
            ResourceInfo::create(Attributes::create([
                'service.name' => $this->serviceName,
                'service.version' => $this->serviceVersion,
                'deployment.environment' => config('app.env', 'production'),
            ])),
        );

        // Setup exporter (OTLP to Jaeger/Tempo/etc)
        $endpoint = $this->exporterEndpoint ?? 'http://localhost:4318';
        $exporter = new SpanExporter($endpoint);

        // Create tracer provider
        $this->tracerProvider = new TracerProvider(
            new SimpleSpanProcessor($exporter),
            new AlwaysOnSampler(),
            $resource,
        );

        // Get tracer instance
        $this->tracer = $this->tracerProvider->getTracer(
            instrumentationName: 'bault-php',
            instrumentationVersion: $this->serviceVersion,
        );
    }

    /**
     * Start a new span
     */
    public function startSpan(
        string $name,
        array $attributes = [],
        ?Context $context = null,
    ): SpanInterface {
        $builder = $this->tracer->spanBuilder($name);

        if ($context) {
            $builder->setParent($context);
        }

        foreach ($attributes as $key => $value) {
            $builder->setAttribute($key, $value);
        }

        $span = $builder->startSpan();
        $this->currentSpan = $span;

        return $span;
    }

    /**
     * End current span
     */
    public function endSpan(?SpanInterface $span = null): void
    {
        $span = $span ?? $this->currentSpan;

        if ($span) {
            $span->end();
            $this->currentSpan = null;
        }
    }

    /**
     * Trace HTTP request
     */
    public function traceHttpRequest(
        string $method,
        string $uri,
        callable $callback,
    ): mixed {
        $span = $this->startSpan("HTTP {$method}", [
            'http.method' => $method,
            'http.url' => $uri,
            'http.target' => parse_url($uri, PHP_URL_PATH),
        ]);

        try {
            $result = $callback();

            $span->setAttribute('http.status_code', 200);
            $span->setStatus('ok');

            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setAttribute('http.status_code', 500);
            $span->setStatus('error', $e->getMessage());

            throw $e;
        } finally {
            $this->endSpan($span);
        }
    }

    /**
     * Trace database query
     */
    public function traceDbQuery(
        string $query,
        array $bindings,
        callable $callback,
    ): mixed {
        $span = $this->startSpan('DB Query', [
            'db.system' => 'mysql',
            'db.statement' => $this->sanitizeQuery($query),
            'db.operation' => $this->getQueryOperation($query),
        ]);

        try {
            $startTime = microtime(true);
            $result = $callback();
            $duration = (microtime(true) - $startTime) * 1000; // ms

            $span->setAttribute('db.duration_ms', $duration);
            $span->setStatus('ok');

            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus('error', $e->getMessage());
            throw $e;
        } finally {
            $this->endSpan($span);
        }
    }

    /**
     * Trace cache operation
     */
    public function traceCache(
        string $operation,
        string $key,
        callable $callback,
    ): mixed {
        $span = $this->startSpan("Cache {$operation}", [
            'cache.operation' => $operation,
            'cache.key' => $key,
        ]);

        try {
            $result = $callback();

            $span->setAttribute('cache.hit', $result !== null);
            $span->setStatus('ok');

            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus('error', $e->getMessage());
            throw $e;
        } finally {
            $this->endSpan($span);
        }
    }

    /**
     * Trace queue job
     */
    public function traceJob(
        string $jobName,
        array $payload,
        callable $callback,
    ): mixed {
        $span = $this->startSpan("Job: {$jobName}", [
            'job.name' => $jobName,
            'job.queue' => 'default',
        ]);

        try {
            $startTime = microtime(true);
            $result = $callback();
            $duration = (microtime(true) - $startTime) * 1000;

            $span->setAttribute('job.duration_ms', $duration);
            $span->setStatus('ok');

            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus('error', $e->getMessage());
            throw $e;
        } finally {
            $this->endSpan($span);
        }
    }

    /**
     * Trace external API call
     */
    public function traceExternalApi(
        string $service,
        string $endpoint,
        callable $callback,
    ): mixed {
        $span = $this->startSpan("External API: {$service}", [
            'peer.service' => $service,
            'http.url' => $endpoint,
        ]);

        try {
            $startTime = microtime(true);
            $result = $callback();
            $duration = (microtime(true) - $startTime) * 1000;

            $span->setAttribute('http.duration_ms', $duration);
            $span->setStatus('ok');

            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus('error', $e->getMessage());
            throw $e;
        } finally {
            $this->endSpan($span);
        }
    }

    /**
     * Add custom span attribute
     */
    public function addAttribute(string $key, mixed $value): void
    {
        if ($this->currentSpan) {
            $this->currentSpan->setAttribute($key, $value);
        }
    }

    /**
     * Add event to current span
     */
    public function addEvent(string $name, array $attributes = []): void
    {
        if ($this->currentSpan) {
            $this->currentSpan->addEvent($name, $attributes);
        }
    }

    /**
     * Get current span
     */
    public function getCurrentSpan(): ?SpanInterface
    {
        return $this->currentSpan;
    }

    /**
     * Sanitize SQL query for tracing (remove sensitive data)
     */
    private function sanitizeQuery(string $query): string
    {
        // Replace actual values with placeholders
        $query = preg_replace("/'[^']*'/", "'?'", $query);
        $query = preg_replace('/\b\d+\b/', '?', $query);

        return substr($query, 0, 500); // Limit length
    }

    /**
     * Extract operation from SQL query
     */
    private function getQueryOperation(string $query): string
    {
        $query = trim(strtoupper($query));

        if (str_starts_with($query, 'SELECT')) {
            return 'SELECT';
        }
        if (str_starts_with($query, 'INSERT')) {
            return 'INSERT';
        }
        if (str_starts_with($query, 'UPDATE')) {
            return 'UPDATE';
        }
        if (str_starts_with($query, 'DELETE')) {
            return 'DELETE';
        }

        return 'QUERY';
    }

    /**
     * Shutdown and flush all pending spans
     */
    public function shutdown(): void
    {
        if ($this->tracerProvider instanceof TracerProvider) {
            $this->tracerProvider->shutdown();
        }
    }
}
