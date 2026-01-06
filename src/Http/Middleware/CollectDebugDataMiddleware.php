<?php

namespace App\Http\Middleware;

use Core\Application;
use Core\Contracts\WebSocket\WebSocketManagerInterface;
use Core\Debug\DebugBroadcaster;
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
        private DebugBroadcaster $broadcaster,
        private WebSocketManagerInterface $wsManager,
        private LoggerInterface $logger,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = $this->app->get('request_id');
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $this->debugManager->enable();
        $this->broadcaster->enable($requestId);

        if ($this->app->bound('route')) {
            $route = $this->app->get('route');
            $this->broadcaster->broadcastRoute(
                $request->getMethod(),
                (string) $request->getUri()->getPath(),
                is_array($route->handler) ? ($route->handler[0] . '::' . $route->handler[1]) : (string) $route->handler,
                $route->middleware ?? []
            );
        }

        $this->startMetricsBroadcasting($startTime, $startMemory);

        $response = $handler->handle($request);
        
        $this->broadcaster->broadcastMetrics(
            microtime(true) - $startTime,
            memory_get_usage(true)
        );
        
        $this->broadcaster->disable();

        if (!$this->debugManager->isEnabled()) {
            return $response;
        }

        $requestId = $this->app->get('request_id');

        $this->debugManager->recordRequestInfo([
            'id' => $requestId,
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'status_code' => $response->getStatusCode(),
            'response_headers' => $response->getHeaders(),
            'start_time' => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true),
            'duration_ms' => round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000, 2),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
        ]);

        $this->debugManager->recordConfig($this->app->make('config')->all());

        $this->debugManager->set('cookies', $request->getCookieParams());

        if ($this->app->bound(\Core\Contracts\Session\SessionInterface::class)) {
            /** @var \Core\Contracts\Session\SessionInterface $session */
            $session = $this->app->make(\Core\Contracts\Session\SessionInterface::class);
            if ($session->isStarted()) {
                $this->debugManager->set('session', $session->all());
            } else {
                $this->debugManager->set('session', []);
            }
        } else {
            $this->debugManager->set('session', []);
        }

        if ($this->app->bound('debugbar')) {
            /** @var \DebugBar\DebugBar $debugbar */
            $debugbar = $this->app->make('debugbar');
            
            if ($debugbar->hasCollector('cache')) {
                $cacheCollector = $debugbar->getCollector('cache');
                $cacheData = $cacheCollector->collect();
                $this->debugManager->set('cache', $cacheData);
            }
        }

        $data = $this->debugManager->getData();

        $response = $response->withHeader('X-Debug-ID', $requestId);

        if (\Core\Database\Swoole\SwooleRedisPool::isInitialized('default')) {
            try {
                $redis = \Core\Database\Swoole\SwooleRedisPool::get('default');
                try {
                    $key = 'debug:' . $requestId;
                    $redis->setex($key, 300, json_encode($data));
                } finally {
                    \Core\Database\Swoole\SwooleRedisPool::put($redis);
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to save debug data to Redis: ' . $e->getMessage(), [
                    'exception' => $e,
                    'request_id' => $requestId,
                ]);
            }
        } else {
            $this->logger->debug('Redis pool not initialized, skipping debug data storage');
        }

        try {
            $this->wsManager->sendToUser($requestId, [
                'type' => 'full_debug_load',
                'payload' => $data,
            ]);
        } catch (\Throwable $wsError) {
            $this->logger->debug('WebSocket send failed: ' . $wsError->getMessage());
        }

        return $response;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Broadcast metrics periodically during request.
     */
    private function startMetricsBroadcasting(float $startTime, int $startMemory): void
    {
    }
}
