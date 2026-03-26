<?php

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Clockwork Middleware for BaultFrame
 * 
 * Handles the request/response lifecycle for Clockwork profiling:
 * 1. Start timeline and event tracking
 * 2. Allow request to proceed
 * 3. Finalize data collection
 * 4. Add Clockwork headers to response
 */
class ClockworkMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected $clockwork = null
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // If Clockwork is not installed/available, just pass through
        if (!$this->clockwork) {
            return $handler->handle($request);
        }

        // Start tracking this request
        $this->clockwork->timeline()->startEvent('total', 'Total execution time');
        
        // Record request data
        $this->clockwork->userData('Request')->counters([
            'Method' => $request->getMethod(),
            'URI' => (string) $request->getUri(),
            'IP' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
        ]);

        // Process the request
        $response = $handler->handle($request);

        // Stop the main timeline event
        $this->clockwork->timeline()->endEvent('total');

        // Resolve the data (finalize collection)
        $this->clockwork->resolveRequest();
        $this->clockwork->storeRequest();

        // Get the request ID for headers
        $requestData = $this->clockwork->getRequest();
        $requestId = $requestData->id ?? null;

        // Add Clockwork headers to the response
        if ($requestId && class_exists(\Clockwork\Clockwork::class)) {
            $response = $response
                ->withHeader('X-Clockwork-Id', $requestId)
                ->withHeader('X-Clockwork-Version', \Clockwork\Clockwork::VERSION)
                ->withHeader('X-Clockwork-Path', '/__clockwork/');
        }

        return $response;
    }
}
