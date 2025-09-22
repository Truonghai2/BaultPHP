<?php

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class LogRequestMiddleware implements MiddlewareInterface
{
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startTime = microtime(true);

        $response = $handler->handle($request);

        $duration = (microtime(true) - $startTime) * 1000;

        $this->logger->info('Request Handled', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
            'status' => $response->getStatusCode(),
            'duration_ms' => number_format($duration, 2),
        ]);

        return $response;
    }
}
