<?php

namespace Core\Http\Client\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Logging Middleware.
 * 
 * Logs all HTTP requests and responses.
 */
class LoggingMiddleware
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create Guzzle middleware.
     */
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $startTime = microtime(true);

            // Log request
            $this->logger->debug("HTTP Request", [
                'method' => $request->getMethod(),
                'uri' => (string) $request->getUri(),
                'headers' => $this->sanitizeHeaders($request->getHeaders()),
            ]);

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($request, $startTime) {
                    $duration = (microtime(true) - $startTime) * 1000;

                    // Log response
                    $this->logger->info("HTTP Response", [
                        'method' => $request->getMethod(),
                        'uri' => (string) $request->getUri(),
                        'status' => $response->getStatusCode(),
                        'duration_ms' => round($duration, 2),
                    ]);

                    return $response;
                },
                function ($reason) use ($request, $startTime) {
                    $duration = (microtime(true) - $startTime) * 1000;

                    // Log error
                    $this->logger->error("HTTP Request Failed", [
                        'method' => $request->getMethod(),
                        'uri' => (string) $request->getUri(),
                        'error' => $reason->getMessage(),
                        'duration_ms' => round($duration, 2),
                    ]);

                    throw $reason;
                }
            );
        };
    }

    /**
     * Sanitize headers (remove sensitive data).
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'api-key', 'x-api-key', 'cookie', 'set-cookie'];

        foreach ($sensitive as $key) {
            foreach ($headers as $name => $value) {
                if (strtolower($name) === $key) {
                    $headers[$name] = ['***REDACTED***'];
                }
            }
        }

        return $headers;
    }
}
