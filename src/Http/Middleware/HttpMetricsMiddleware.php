<?php

namespace App\Http\Middleware;

use Core\Application;
use Core\Metrics\SwooleMetricsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HttpMetricsMiddleware implements MiddlewareInterface
{
    private ?SwooleMetricsService $metrics = null;

    public function __construct(
        private Application $app
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Lazy load metrics service to avoid circular dependency
        if ($this->metrics === null && $this->app->bound(SwooleMetricsService::class)) {
            $this->metrics = $this->app->make(SwooleMetricsService::class);
        }

        // Skip metrics if service is not available
        if ($this->metrics === null) {
            return $handler->handle($request);
        }

        $startTime = microtime(true);

        $response = $handler->handle($request);

        $duration = microtime(true) - $startTime;

        // Sử dụng route pattern thay vì full path để tránh quá nhiều label khác nhau
        $route = $request->getAttribute('route');
        $path = $route ? $route->uri : $request->getUri()->getPath();

        $labels = [
            'method' => $request->getMethod(),
            'path' => $path,
            'status' => $response->getStatusCode(),
        ];

        try {
            // Tăng bộ đếm tổng số request
            $this->metrics->increment('http_requests_total', 1.0, $labels);

            // Ghi nhận (observe) thời gian xử lý request vào histogram.
            // Histogram là cách đúng đắn để theo dõi các giá trị có thể thay đổi như độ trễ.
            $this->metrics->observe('http_request_duration_seconds', $duration, $labels);
        } catch (\Throwable $e) {
            // Silently fail if metrics collection fails to avoid breaking requests
        }

        return $response;
    }
}
