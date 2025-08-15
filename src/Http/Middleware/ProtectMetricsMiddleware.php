<?php

namespace Http\Middleware;

use Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware to protect sensitive routes like /_metrics.
 * It checks for a secret token in the 'X-Metrics-Token' header.
 */
class ProtectMetricsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $expectedToken = env('METRICS_SECRET_TOKEN');

        // If no token is configured in the environment, deny access by default for security.
        if (empty($expectedToken)) {
            return new JsonResponse(['error' => 'Metrics endpoint is not configured for access.'], 403);
        }

        $providedToken = $request->getHeaderLine('X-Metrics-Token');

        if ($providedToken !== $expectedToken) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        // Token is valid, proceed to the next handler (the controller).
        return $handler->handle($request);
    }
}
