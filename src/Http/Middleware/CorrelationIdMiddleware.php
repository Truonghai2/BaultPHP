<?php

namespace App\Http\Middleware;

use Core\Support\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Correlation ID Middleware.
 *
 * Generates or extracts correlation ID from request headers
 * and stores it in Context for the request lifecycle.
 */
class CorrelationIdMiddleware implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $correlationId = $this->extractCorrelationId($request);

        if (!$correlationId) {
            $correlationId = Context::generateCorrelationId();
        }

        Context::setCorrelationId($correlationId);

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-Correlation-ID', $correlationId)
            ->withHeader('X-Request-ID', $correlationId);
    }

    /**
     * Extract correlation ID from request headers.
     */
    private function extractCorrelationId(ServerRequestInterface $request): ?string
    {
        $headers = [
            'X-Correlation-ID',
            'X-Request-ID',
            'X-Trace-ID',
            'Correlation-Id',
        ];

        foreach ($headers as $header) {
            $value = $request->getHeaderLine($header);
            if ($value !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
