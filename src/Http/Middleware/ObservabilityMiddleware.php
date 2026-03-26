<?php

namespace App\Http\Middleware;

use Core\Observability\OpenTelemetryTracer;
use Core\Observability\PrometheusMetrics;
use Core\Support\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Observability Middleware.
 * 
 * Automatically instruments HTTP requests with:
 * - Distributed tracing (OpenTelemetry/Jaeger)
 * - Metrics collection (Prometheus)
 */
class ObservabilityMiddleware implements MiddlewareInterface
{
    public function __construct(
        private OpenTelemetryTracer $tracer,
        private PrometheusMetrics $metrics,
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $startTime = microtime(true);

        // Start distributed trace span
        $span = $this->tracer->startHttpSpan($request);

        try {
            // Process request
            $response = $handler->handle($request);

            // Calculate duration
            $durationMs = (microtime(true) - $startTime) * 1000;

            // Add response attributes to span
            $span->setAttribute('http.status_code', $response->getStatusCode());
            $span->setAttribute('http.response_time_ms', $durationMs);

            // Record metrics
            $this->metrics->recordHttpRequest(
                $request->getMethod(),
                $request->getUri()->getPath(),
                $response->getStatusCode(),
                $durationMs
            );

            // End span successfully
            $this->tracer->endSpan($span);

            // Add observability headers
            $response = $response->withHeader('X-Trace-Id', $this->getTraceId($span))
                ->withHeader('X-Response-Time-Ms', (string) round($durationMs, 2));

            return $response;
        } catch (\Throwable $e) {
            // Calculate duration even on error
            $durationMs = (microtime(true) - $startTime) * 1000;

            // Record error in span
            $this->tracer->endSpanWithError($span, $e);

            // Record metrics
            $this->metrics->recordHttpRequest(
                $request->getMethod(),
                $request->getUri()->getPath(),
                500,
                $durationMs
            );

            throw $e;
        }
    }

    /**
     * Get trace ID from span.
     */
    protected function getTraceId($span): string
    {
        $context = $span->getContext();
        return $context->getTraceId();
    }
}
