<?php

namespace Core\Debug;

use Core\Contracts\WebSocket\WebSocketManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service để broadcast debug events real-time qua WebSocket.
 */
class DebugBroadcaster
{
    protected bool $enabled = false;
    protected ?string $requestId = null;

    public function __construct(
        protected WebSocketManagerInterface $wsManager,
        protected LoggerInterface $logger,
    ) {
    }

    /**
     * Enable broadcaster và set request ID.
     */
    public function enable(string $requestId): void
    {
        $this->enabled = true;
        $this->requestId = $requestId;
    }

    /**
     * Disable broadcaster.
     */
    public function disable(): void
    {
        $this->enabled = false;
        $this->requestId = null;
    }

    /**
     * Check if broadcaster is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->requestId !== null;
    }

    /**
     * Broadcast query event.
     */
    public function broadcastQuery(array $data): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->broadcast('query', $data);
    }

    /**
     * Broadcast event dispatch.
     */
    public function broadcastEvent(string $name, array $payload = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->broadcast('event', [
            'name' => $name,
            'payload' => $payload,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Broadcast cache operation.
     */
    public function broadcastCache(string $operation, string $key, mixed $value = null): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->broadcast('cache', [
            'operation' => $operation,
            'key' => $key,
            'value' => $value,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Broadcast log message.
     */
    public function broadcastLog(string $level, string $message, array $context = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->broadcast('log', [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Broadcast session operation.
     */
    public function broadcastSession(string $operation, string $key, mixed $value = null): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->broadcast('session', [
            'operation' => $operation,
            'key' => $key,
            'value' => $value,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Broadcast cookie operation.
     */
    public function broadcastCookie(string $operation, string $name, mixed $value = null, array $options = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->broadcast('cookie', [
            'operation' => $operation,
            'name' => $name,
            'value' => $value,
            'options' => $options,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Broadcast queue job.
     */
    public function broadcastQueue(string $job, array $data = [], string $queue = 'default'): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->broadcast('queue', [
            'job' => $job,
            'data' => $data,
            'queue' => $queue,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Broadcast route match.
     */
    public function broadcastRoute(string $method, string $uri, string $action, array $middleware = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->broadcast('route', [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
            'middleware' => $middleware,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Broadcast performance metrics.
     */
    public function broadcastMetrics(float $time, int $memory): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->broadcast('metrics', [
            'time_ms' => round($time * 1000, 2),
            'memory_mb' => round($memory / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Internal method to send data via WebSocket.
     */
    protected function broadcast(string $type, array $data): void
    {
        try {
            $this->wsManager->sendToUser($this->requestId, [
                'type' => 'debug_realtime',
                'payload' => [
                    'type' => $type,
                    'data' => $data,
                ],
            ]);
        } catch (\Throwable $e) {
            // Silent fail - real-time debug không quan trọng bằng app stability
            $this->logger->debug('Failed to broadcast debug data: ' . $e->getMessage());
        }
    }
}

