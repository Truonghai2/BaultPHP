<?php

namespace App\Http\Middleware;

use App\Http\JsonResponse;
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
        $expectedToken = config('services.prometheus.metrics_token');

        if (empty($expectedToken)) {
            return new JsonResponse(['error' => 'Metrics endpoint is not configured for access.'], 403);
        }

        $providedToken = $request->getHeaderLine('X-Metrics-Token');

        if (!hash_equals($expectedToken, $providedToken)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        return $handler->handle($request);
    }
}
