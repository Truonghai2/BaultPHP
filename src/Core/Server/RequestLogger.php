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
     * Optimized for performance - minimal allocations and fast path for common cases.
     */
    public function log(ServerRequestInterface $request, ResponseInterface $response, float $startTime, ?string $requestId = null): void
    {
        // Fast path: Get remote address with minimal lookups
        $serverParams = $request->getServerParams();
        $remoteAddr = $serverParams['REMOTE_ADDR'] 
            ?? $request->getAttribute('_swoole_remote_addr')
            ?? '?.?.?.?';

        // Optimize URI string building - avoid unnecessary concatenation
        $uri = $request->getUri();
        $path = $uri->getPath();
        $query = $uri->getQuery();
        $requestUri = $query ? $path . '?' . $query : $path;

        // Optimize response body size calculation - cache if possible
        // getSize() can be expensive for large streams, so we try to get it efficiently
        $bodySize = 0;
        try {
            $body = $response->getBody();
            // Check if body has metadata with size (faster than getSize())
            $metadata = $body->getMetadata();
            if (isset($metadata['size'])) {
                $bodySize = $metadata['size'];
            } elseif (method_exists($body, 'getSize')) {
                $bodySize = $body->getSize() ?? 0;
            }
        } catch (\Throwable $e) {
            // If size calculation fails, use 0
            $bodySize = 0;
        }

        // Build log message efficiently
        $method = $request->getMethod();
        $protocol = $request->getProtocolVersion();
        $statusCode = $response->getStatusCode();
        $referer = $request->getHeaderLine('Referer') ?: '-';
        $userAgent = $request->getHeaderLine('User-Agent') ?: '-';

        $message = sprintf(
            '%s - - "%s %s HTTP/%s" %d %d "%s" "%s"',
            $remoteAddr,
            $method,
            $requestUri,
            $protocol,
            $statusCode,
            $bodySize,
            $referer,
            $userAgent,
        );

        $duration = round((microtime(true) - $startTime) * 1000);
        
        // Use provided request_id (already resolved, no container lookup needed)
        if ($requestId === null) {
        }
        
        $this->logger->info($message, [
            'duration_ms' => $duration,
            'request_id' => $requestId,
        ]);
    }
}
