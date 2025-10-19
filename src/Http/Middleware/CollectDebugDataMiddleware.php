<?php

namespace App\Http\Middleware;

use Core\Application;
use Core\Contracts\WebSocket\WebSocketManagerInterface;
use Core\Debug\DebugManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Middleware to collect and store debug information at the end of a request.
 * This is the bridge between the data collected during the request and the
 * storage (Redis) that the DebugController reads from.
 */
class CollectDebugDataMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Application $app,
        private DebugManager $debugManager,
        private WebSocketManagerInterface $wsManager,
        private LoggerInterface $logger,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->debugManager->enable();

        $response = $handler->handle($request);

        if (!$this->debugManager->isEnabled()) {
            return $response;
        }

        $requestId = $this->app->get('request_id');

        $this->debugManager->recordRequestInfo([
            'status_code' => $response->getStatusCode(),
            'response_headers' => $response->getHeaders(),
        ]);

        $this->debugManager->recordConfig($this->app->make('config')->all());

        $data = $this->debugManager->getData();

        try {
            // Gửi toàn bộ dữ liệu debug qua WebSocket đến đúng session debug
            $this->wsManager->sendToUser($requestId, [
                'type' => 'full_debug_load',
                'payload' => $data,
            ]);
            return $response->withHeader('X-Debug-ID', $requestId);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send debug data via WebSocket: ' . $e->getMessage(), [
                'exception' => $e,
                'request_id' => $requestId,
            ]);

            return $response;
        }
    }
}
