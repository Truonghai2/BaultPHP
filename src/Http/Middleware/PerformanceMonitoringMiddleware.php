<?php

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Middleware để monitor hiệu năng của các request
 */
class PerformanceMonitoringMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected LoggerInterface $logger,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $response = $handler->handle($request);

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsed = $endMemory - $startMemory;

        // Log slow requests (> 1000ms)
        if ($duration > 1000) {
            $this->logger->warning('Slow request detected', [
                'method' => $request->getMethod(),
                'uri' => $request->getUri()->getPath(),
                'duration_ms' => round($duration, 2),
                'memory_used_kb' => round($memoryUsed / 1024, 2),
                'user_agent' => $request->getHeaderLine('User-Agent'),
            ]);
        }

        // Log high memory usage (> 50MB)
        if ($memoryUsed > 50 * 1024 * 1024) {
            $this->logger->warning('High memory usage detected', [
                'method' => $request->getMethod(),
                'uri' => $request->getUri()->getPath(),
                'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
                'duration_ms' => round($duration, 2),
            ]);
        }

        return $response->withHeader('X-Response-Time', round($duration, 2) . 'ms');
    }
}
