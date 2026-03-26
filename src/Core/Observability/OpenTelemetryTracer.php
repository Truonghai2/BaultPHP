<?php

namespace Core\Observability;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Context\Context;
use Psr\Http\Message\ServerRequestInterface;

/**
 * OpenTelemetry Tracer for Distributed Tracing.
 * 
 * Integrates with Jaeger for visual trace analysis.
 * 
 * Features:
 * - HTTP request tracing
 * - Database query tracing
 * - CQRS command/query tracing
 * - Custom span creation
 * - Automatic context propagation
 */
class OpenTelemetryTracer
{
    private TracerInterface $tracer;
    private TracerProviderInterface $tracerProvider;

    public function __construct(
        private string $serviceName = 'baultframe',
        private string $serviceVersion = '1.0.0',
        private ?string $otlpEndpoint = null,
    ) {
        $this->otlpEndpoint = $otlpEndpoint ?? env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://localhost:4318');
        $this->initialize();
    }

    /**
     * Initialize OpenTelemetry.
     */
    protected function initialize(): void
    {
        // Create resource with service information
        $resource = ResourceInfoFactory::emptyResource()->merge(
            ResourceInfo::create(Attributes::create([
                'service.name' => $this->serviceName,
                'service.version' => $this->serviceVersion,
                'deployment.environment' => env('APP_ENV', 'production'),
            ]))
        );

        // Create OTLP exporter
        $exporter = new SpanExporter(
            $this->otlpEndpoint . '/v1/traces'
        );

        // Create span processor (batch for production, simple for development)
        $spanProcessor = env('APP_ENV') === 'local'
            ? new SimpleSpanProcessor($exporter)
            : new BatchSpanProcessor($exporter);

        // Create tracer provider
        $this->tracerProvider = TracerProvider::builder()
            ->addSpanProcessor($spanProcessor)
            ->setResource($resource)
            ->build();

        // Get tracer instance
        $this->tracer = $this->tracerProvider->getTracer(
            'baultframe-tracer',
            $this->serviceVersion
        );
    }

    /**
     * Start HTTP request span.
     */
    public function startHttpSpan(ServerRequestInterface $request): \OpenTelemetry\API\Trace\SpanInterface
    {
        $span = $this->tracer->spanBuilder($request->getMethod() . ' ' . $request->getUri()->getPath())
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        // Add HTTP attributes
        $span->setAttribute('http.method', $request->getMethod());
        $span->setAttribute('http.url', (string) $request->getUri());
        $span->setAttribute('http.target', $request->getUri()->getPath());
        $span->setAttribute('http.host', $request->getUri()->getHost());
        $span->setAttribute('http.scheme', $request->getUri()->getScheme());

        // Add headers
        if ($userAgent = $request->getHeaderLine('User-Agent')) {
            $span->setAttribute('http.user_agent', $userAgent);
        }

        // Add correlation ID
        if ($correlationId = $request->getHeaderLine('X-Correlation-ID')) {
            $span->setAttribute('correlation.id', $correlationId);
        }

        return $span;
    }

    /**
     * Start database query span.
     */
    public function startDatabaseSpan(string $query, array $bindings = []): \OpenTelemetry\API\Trace\SpanInterface
    {
        $span = $this->tracer->spanBuilder('db.query')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $span->setAttribute('db.system', 'postgresql');
        $span->setAttribute('db.statement', $this->sanitizeQuery($query));
        $span->setAttribute('db.binding_count', count($bindings));

        return $span;
    }

    /**
     * Start CQRS command span.
     */
    public function startCommandSpan(string $commandName): \OpenTelemetry\API\Trace\SpanInterface
    {
        $span = $this->tracer->spanBuilder("command:{$commandName}")
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttribute('cqrs.type', 'command');
        $span->setAttribute('cqrs.name', $commandName);

        return $span;
    }

    /**
     * Start CQRS query span.
     */
    public function startQuerySpan(string $queryName): \OpenTelemetry\API\Trace\SpanInterface
    {
        $span = $this->tracer->spanBuilder("query:{$queryName}")
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttribute('cqrs.type', 'query');
        $span->setAttribute('cqrs.name', $queryName);

        return $span;
    }

    /**
     * Start custom span.
     */
    public function startSpan(string $name, SpanKind $kind = SpanKind::KIND_INTERNAL): \OpenTelemetry\API\Trace\SpanInterface
    {
        return $this->tracer->spanBuilder($name)
            ->setSpanKind($kind)
            ->startSpan();
    }

    /**
     * End span with success.
     */
    public function endSpan(\OpenTelemetry\API\Trace\SpanInterface $span, array $attributes = []): void
    {
        foreach ($attributes as $key => $value) {
            $span->setAttribute($key, $value);
        }

        $span->setStatus(StatusCode::STATUS_OK);
        $span->end();
    }

    /**
     * End span with error.
     */
    public function endSpanWithError(\OpenTelemetry\API\Trace\SpanInterface $span, \Throwable $error): void
    {
        $span->recordException($error);
        $span->setStatus(StatusCode::STATUS_ERROR, $error->getMessage());
        $span->end();
    }

    /**
     * Trace a callable.
     */
    public function trace(string $name, callable $callback, SpanKind $kind = SpanKind::KIND_INTERNAL): mixed
    {
        $span = $this->startSpan($name, $kind);

        try {
            $result = $callback($span);
            $this->endSpan($span);
            return $result;
        } catch (\Throwable $e) {
            $this->endSpanWithError($span, $e);
            throw $e;
        }
    }

    /**
     * Get tracer instance.
     */
    public function getTracer(): TracerInterface
    {
        return $this->tracer;
    }

    /**
     * Get tracer provider.
     */
    public function getTracerProvider(): TracerProviderInterface
    {
        return $this->tracerProvider;
    }

    /**
     * Sanitize SQL query for logging.
     */
    protected function sanitizeQuery(string $query): string
    {
        // Remove sensitive data patterns
        $query = preg_replace('/password\s*=\s*[\'"][^\'"]*[\'"]/i', 'password=***', $query);
        $query = preg_replace('/token\s*=\s*[\'"][^\'"]*[\'"]/i', 'token=***', $query);
        
        // Truncate long queries
        if (strlen($query) > 1000) {
            $query = substr($query, 0, 1000) . '...';
        }

        return $query;
    }

    /**
     * Shutdown and flush spans.
     */
    public function shutdown(): void
    {
        if ($this->tracerProvider instanceof TracerProvider) {
            $this->tracerProvider->shutdown();
        }
    }
}
