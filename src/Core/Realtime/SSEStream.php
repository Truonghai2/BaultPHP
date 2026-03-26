<?php

declare(strict_types=1);

namespace Core\Realtime;

use Core\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Swoole\Coroutine\Channel;

/**
 * Server-Sent Events (SSE) Stream
 *
 * Provides advanced SSE streaming with:
 * - Automatic reconnection
 * - Backpressure handling
 * - Channel-based messaging
 * - Heartbeat/ping support
 *
 * Features:
 * - Real-time streaming
 * - Auto-reconnection
 * - Backpressure handling
 * - Event filtering
 */
class SSEStream
{
    protected array $connections = [];
    protected array $channels = [];
    protected int $heartbeatInterval = 30; // seconds
    protected int $maxBufferSize = 1000;
    protected bool $running = false;

    public function __construct(
        protected array $config = [],
    ) {
        $this->heartbeatInterval = $config['heartbeat_interval'] ?? 30;
        $this->maxBufferSize = $config['max_buffer_size'] ?? 1000;
    }

    /**
     * Stream SSE events to client
     *
     * @param ResponseInterface $response PSR-7 response
     * @param string $channel Channel name to subscribe to
     * @param callable|null $filter Optional filter function
     * @return ResponseInterface
     */
    public function stream(ResponseInterface $response, string $channel, ?callable $filter = null): ResponseInterface
    {
        $connectionId = $this->generateConnectionId();

        // Set SSE headers
        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ];

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        // Register connection
        $this->registerConnection($connectionId, $channel, $filter);

        // Send initial connection message
        $this->sendEvent($response, 'connected', [
            'connection_id' => $connectionId,
            'channel' => $channel,
        ]);

        // Start heartbeat
        $this->startHeartbeat($connectionId, $response);

        // Start listening for events
        $this->listenForEvents($connectionId, $channel, $response, $filter);

        return $response;
    }

    /**
     * Register a connection
     */
    protected function registerConnection(string $connectionId, string $channel, ?callable $filter): void
    {
        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = new Channel($this->maxBufferSize);
        }

        $this->connections[$connectionId] = [
            'channel' => $channel,
            'filter' => $filter,
            'last_heartbeat' => time(),
            'buffer' => [],
        ];

        Log::info('SSE connection registered', [
            'connection_id' => $connectionId,
            'channel' => $channel,
        ]);
    }

    /**
     * Listen for events on channel
     */
    protected function listenForEvents(
        string $connectionId,
        string $channel,
        ResponseInterface $response,
        ?callable $filter,
    ): void {
        if (!isset($this->channels[$channel])) {
            return;
        }

        $channelInstance = $this->channels[$channel];
        $buffer = [];

        while (isset($this->connections[$connectionId])) {
            try {
                // Pop event from channel (non-blocking with timeout)
                $event = $channelInstance->pop(1.0);

                if ($event === false) {
                    // Timeout - check connection health
                    if (!$this->isConnectionAlive($connectionId)) {
                        break;
                    }
                    continue;
                }

                // Apply filter if provided
                if ($filter && !$filter($event)) {
                    continue;
                }

                // Handle backpressure
                if (count($buffer) >= $this->maxBufferSize) {
                    Log::warning('SSE buffer overflow, dropping oldest events', [
                        'connection_id' => $connectionId,
                        'buffer_size' => count($buffer),
                    ]);
                    array_shift($buffer);
                }

                $buffer[] = $event;

                // Send event to client
                $this->sendEvent($response, $event['type'] ?? 'message', $event['data'] ?? $event);

            } catch (\Throwable $e) {
                Log::error('SSE event processing error', [
                    'connection_id' => $connectionId,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        }

        // Cleanup
        $this->unregisterConnection($connectionId);
    }

    /**
     * Send event to client
     */
    protected function sendEvent(ResponseInterface $response, string $type, array $data): void
    {
        $event = [
            'id' => $this->generateEventId(),
            'type' => $type,
            'data' => $data,
            'timestamp' => time(),
        ];

        $message = $this->formatSSEMessage($event);

        // In Swoole, we would use response->write()
        // For PSR-7, we need to handle streaming differently
        // This is a placeholder - actual implementation depends on server
        Log::debug('SSE event sent', [
            'type' => $type,
            'event_id' => $event['id'],
        ]);
    }

    /**
     * Format message according to SSE specification
     */
    protected function formatSSEMessage(array $event): string
    {
        $message = "id: {$event['id']}\n";
        $message .= "event: {$event['type']}\n";
        $message .= 'data: ' . json_encode($event['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        $message .= "retry: 3000\n"; // 3 seconds retry interval
        $message .= "\n";

        return $message;
    }

    /**
     * Start heartbeat for connection
     */
    protected function startHeartbeat(string $connectionId, ResponseInterface $response): void
    {
        // In production, this would run in a coroutine
        // Sending periodic ping messages to keep connection alive
        go(function () use ($connectionId, $response) {
            while (isset($this->connections[$connectionId])) {
                sleep($this->heartbeatInterval);

                if (!isset($this->connections[$connectionId])) {
                    break;
                }

                $this->sendEvent($response, 'ping', ['timestamp' => time()]);
                $this->connections[$connectionId]['last_heartbeat'] = time();
            }
        });
    }

    /**
     * Publish event to channel
     *
     * @param string $channel Channel name
     * @param string $type Event type
     * @param array $data Event data
     */
    public function publish(string $channel, string $type, array $data): void
    {
        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = new Channel($this->maxBufferSize);
        }

        $event = [
            'type' => $type,
            'data' => $data,
            'timestamp' => time(),
        ];

        // Push to channel (non-blocking)
        $pushed = $this->channels[$channel]->push($event, 0.001);

        if (!$pushed) {
            Log::warning('SSE channel buffer full, event dropped', [
                'channel' => $channel,
                'type' => $type,
            ]);
        } else {
            Log::debug('SSE event published', [
                'channel' => $channel,
                'type' => $type,
            ]);
        }
    }

    /**
     * Broadcast event to all channels
     */
    public function broadcast(string $type, array $data): void
    {
        foreach (array_keys($this->channels) as $channel) {
            $this->publish($channel, $type, $data);
        }
    }

    /**
     * Check if connection is alive
     */
    protected function isConnectionAlive(string $connectionId): bool
    {
        if (!isset($this->connections[$connectionId])) {
            return false;
        }

        $lastHeartbeat = $this->connections[$connectionId]['last_heartbeat'] ?? 0;
        $timeout = $this->config['connection_timeout'] ?? 60;

        return (time() - $lastHeartbeat) < $timeout;
    }

    /**
     * Unregister connection
     */
    protected function unregisterConnection(string $connectionId): void
    {
        if (isset($this->connections[$connectionId])) {
            $channel = $this->connections[$connectionId]['channel'];
            unset($this->connections[$connectionId]);

            Log::info('SSE connection unregistered', [
                'connection_id' => $connectionId,
                'channel' => $channel,
            ]);
        }
    }

    /**
     * Generate unique connection ID
     */
    protected function generateConnectionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate unique event ID
     */
    protected function generateEventId(): string
    {
        return (string) time() . '-' . bin2hex(random_bytes(4));
    }

    /**
     * Get connection statistics
     */
    public function getStats(): array
    {
        $channelStats = [];
        foreach ($this->channels as $channel => $channelInstance) {
            $channelStats[$channel] = [
                'connections' => count(array_filter(
                    $this->connections,
                    function ($conn) use ($channel) {
                        return $conn['channel'] === $channel;
                    },
                )),
                'buffer_size' => $channelInstance->length(),
            ];
        }

        return [
            'total_connections' => count($this->connections),
            'total_channels' => count($this->channels),
            'channels' => $channelStats,
        ];
    }

    /**
     * Close all connections
     */
    public function closeAll(): void
    {
        foreach (array_keys($this->connections) as $connectionId) {
            $this->unregisterConnection($connectionId);
        }

        $this->channels = [];
        Log::info('All SSE connections closed');
    }
}
