<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Ackintosh\Ganesha;
use Core\Server\CircuitBreakerFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Core\Application;

/**
 * Circuit Breaker Middleware
 *
 * Uses Ganesha (ackintosh/ganesha) to automatically protect slow or failing
 * downstream services (DB, external APIs, Redis) from cascading failures.
 *
 * When a service exceeds its failure threshold, the breaker opens and
 * requests return a 503 immediately (fast-fail), giving the service time to recover.
 *
 * Config: config('server.circuit_breaker') or per-route via route attributes.
 */
class CircuitBreakerMiddleware implements MiddlewareInterface
{
    private Ganesha $breaker;
    private string $serviceName;

    public function __construct(
        private readonly Application $app,
    ) {
        $config = config('server.circuit_breaker', []);
        $this->serviceName = $config['service_name'] ?? 'http';
        $this->breaker = CircuitBreakerFactory::create($config, $app, $this->serviceName);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $service = $this->resolveServiceName($request);

        if (!$this->breaker->isAvailable($service)) {
            // Circuit is OPEN → fast-fail with 503
            return response()->json([
                'error' => 'Service temporarily unavailable. Please try again later.',
                'circuit' => 'open',
            ], 503);
        }

        try {
            $response = $handler->handle($request);

            // Record success (2xx/3xx are successes; 5xx counts as failure)
            if ($response->getStatusCode() < 500) {
                $this->breaker->success($service);
            } else {
                $this->breaker->failure($service);
            }

            return $response;
        } catch (\Throwable $e) {
            $this->breaker->failure($service);
            throw $e;
        }
    }

    /**
     * Resolve service name from the request (e.g. group API host, route group, or global name).
     */
    private function resolveServiceName(ServerRequestInterface $request): string
    {
        // Allow per-request override via request attribute set by route/controller
        if ($attr = $request->getAttribute('circuit_breaker_service')) {
            return $attr;
        }

        return $this->serviceName;
    }
}
