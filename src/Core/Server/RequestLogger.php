<?php

namespace Core\Server;

use Core\Application;
use Core\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles logging of HTTP requests and responses.
 * This class is intended to be used in a development environment to provide
 * detailed information about the request lifecycle.
 */
class RequestLogger
{
    public function __construct(
        private Application $app,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Log an incoming request and its response.
     */
    public function log(ServerRequestInterface $request, ResponseInterface $response, float $startTime): void
    {
        $message = sprintf(
            '%s - - "%s %s HTTP/%s" %d %d "%s" "%s"',
            $request->getServerParams()['REMOTE_ADDR'] ?? '?.?.?.?',
            $request->getMethod(),
            $request->getUri()->getPath() . ($request->getUri()->getQuery() ? '?' . $request->getUri()->getQuery() : ''),
            $request->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getBody()->getSize() ?? 0,
            $request->getHeaderLine('Referer') ?: '-',
            $request->getHeaderLine('User-Agent') ?: '-',
        );

        $duration = round((microtime(true) - $startTime) * 1000);
        $this->logger->info($message, ['duration_ms' => $duration, 'request_id' => $this->app->make('request_id')]);
    }
}
