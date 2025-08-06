<?php

namespace Http\Middleware;

use Core\Metrics\SwooleMetricsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HttpMetricsMiddleware implements MiddlewareInterface
{
    public function __construct(private SwooleMetricsService $metrics)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
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

        // Tăng bộ đếm tổng số request
        $this->metrics->increment('http_requests_total', 1.0, $labels);

        // Ghi lại thời gian xử lý của request này
        $this->metrics->setGauge('http_request_duration_seconds', $duration, $labels);

        return $response;
    }
}
