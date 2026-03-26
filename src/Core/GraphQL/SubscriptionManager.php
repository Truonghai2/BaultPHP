<?php

declare(strict_types=1);

namespace Core\GraphQL;

use Core\Application;
use Core\Support\Facades\Log;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\WebSocket\WsServer;

/**
 * GraphQL Subscription Manager
 *
 * Manages GraphQL subscriptions over WebSocket.
 * Supports real-time data updates.
 */
class SubscriptionManager implements MessageComponentInterface
{
    protected array $subscriptions = [];
    protected array $connections = [];
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Handle new WebSocket connection
     *
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->connections[$conn->resourceId] = $conn;
        
        Log::info("GraphQL subscription client connected", [
            'connection_id' => $conn->resourceId,
        ]);
    }

    /**
     * Handle WebSocket message
     *
     * @param ConnectionInterface $from
     * @param string $msg
     */
    public function onMessage(ConnectionInterface $from, string $msg): void
    {
        try {
            $data = json_decode($msg, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError($from, 'Invalid JSON');
                return;
            }

            $type = $data['type'] ?? null;
            
            match ($type) {
                'connection_init' => $this->handleConnectionInit($from, $data),
                'start' => $this->handleStart($from, $data),
                'stop' => $this->handleStop($from, $data),
                'connection_terminate' => $this->handleTerminate($from),
                default => $this->sendError($from, "Unknown message type: {$type}"),
            };

        } catch (\Throwable $e) {
            Log::error("GraphQL subscription error", [
                'error' => $e->getMessage(),
                'connection_id' => $from->resourceId,
            ]);
            $this->sendError($from, $e->getMessage());
        }
    }

    /**
     * Handle connection close
     *
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn): void
    {
        $connectionId = $conn->resourceId;
        
        // Remove subscriptions
        if (isset($this->subscriptions[$connectionId])) {
            foreach ($this->subscriptions[$connectionId] as $subscriptionId) {
                $this->unsubscribe($connectionId, $subscriptionId);
            }
            unset($this->subscriptions[$connectionId]);
        }

        unset($this->connections[$connectionId]);

        Log::info("GraphQL subscription client disconnected", [
            'connection_id' => $connectionId,
        ]);
    }

    /**
     * Handle connection error
     *
     * @param ConnectionInterface $conn
     * @param \Exception $e
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        Log::error("GraphQL subscription connection error", [
            'connection_id' => $conn->resourceId,
            'error' => $e->getMessage(),
        ]);

        $conn->close();
    }

    /**
     * Handle connection initialization
     *
     * @param ConnectionInterface $conn
     * @param array $data
     */
    protected function handleConnectionInit(ConnectionInterface $conn, array $data): void
    {
        $this->sendMessage($conn, [
            'type' => 'connection_ack',
        ]);
    }

    /**
     * Handle subscription start
     *
     * @param ConnectionInterface $conn
     * @param array $data
     */
    protected function handleStart(ConnectionInterface $conn, array $data): void
    {
        $subscriptionId = $data['id'] ?? null;
        $payload = $data['payload'] ?? [];

        if (!$subscriptionId) {
            $this->sendError($conn, 'Missing subscription ID');
            return;
        }

        $query = $payload['query'] ?? '';
        $variables = $payload['variables'] ?? [];

        // Execute subscription
        $this->subscribe($conn->resourceId, $subscriptionId, $query, $variables);
    }

    /**
     * Handle subscription stop
     *
     * @param ConnectionInterface $conn
     * @param array $data
     */
    protected function handleStop(ConnectionInterface $conn, array $data): void
    {
        $subscriptionId = $data['id'] ?? null;
        if ($subscriptionId) {
            $this->unsubscribe($conn->resourceId, $subscriptionId);
        }
    }

    /**
     * Handle connection termination
     *
     * @param ConnectionInterface $conn
     */
    protected function handleTerminate(ConnectionInterface $conn): void
    {
        $conn->close();
    }

    /**
     * Subscribe to a GraphQL subscription
     *
     * @param int $connectionId
     * @param string $subscriptionId
     * @param string $query
     * @param array $variables
     */
    protected function subscribe(int $connectionId, string $subscriptionId, string $query, array $variables): void
    {
        if (!isset($this->subscriptions[$connectionId])) {
            $this->subscriptions[$connectionId] = [];
        }

        $this->subscriptions[$connectionId][$subscriptionId] = [
            'query' => $query,
            'variables' => $variables,
            'connection_id' => $connectionId,
        ];

        Log::info("GraphQL subscription started", [
            'connection_id' => $connectionId,
            'subscription_id' => $subscriptionId,
        ]);
    }

    /**
     * Unsubscribe from a subscription
     *
     * @param int $connectionId
     * @param string $subscriptionId
     */
    protected function unsubscribe(int $connectionId, string $subscriptionId): void
    {
        if (isset($this->subscriptions[$connectionId][$subscriptionId])) {
            unset($this->subscriptions[$connectionId][$subscriptionId]);
            
            Log::info("GraphQL subscription stopped", [
                'connection_id' => $connectionId,
                'subscription_id' => $subscriptionId,
            ]);
        }
    }

    /**
     * Publish data to subscribers
     *
     * @param string $channel Subscription channel/type
     * @param mixed $data Data to publish
     */
    public function publish(string $channel, mixed $data): void
    {
        foreach ($this->subscriptions as $connectionId => $subscriptions) {
            foreach ($subscriptions as $subscriptionId => $subscription) {
                // Check if subscription matches channel
                if ($this->matchesChannel($subscription['query'], $channel)) {
                    $this->sendData($connectionId, $subscriptionId, $data);
                }
            }
        }
    }

    /**
     * Check if subscription query matches channel
     *
     * @param string $query
     * @param string $channel
     * @return bool
     */
    protected function matchesChannel(string $query, string $channel): bool
    {
        // Simple matching - in production, parse query AST
        return str_contains($query, $channel);
    }

    /**
     * Send data to subscription
     *
     * @param int $connectionId
     * @param string $subscriptionId
     * @param mixed $data
     */
    protected function sendData(int $connectionId, string $subscriptionId, mixed $data): void
    {
        if (!isset($this->connections[$connectionId])) {
            return;
        }

        $conn = $this->connections[$connectionId];
        $this->sendMessage($conn, [
            'type' => 'data',
            'id' => $subscriptionId,
            'payload' => $data,
        ]);
    }

    /**
     * Send message to connection
     *
     * @param ConnectionInterface $conn
     * @param array $data
     */
    protected function sendMessage(ConnectionInterface $conn, array $data): void
    {
        $conn->send(json_encode($data));
    }

    /**
     * Send error to connection
     *
     * @param ConnectionInterface $conn
     * @param string $message
     */
    protected function sendError(ConnectionInterface $conn, string $message): void
    {
        $this->sendMessage($conn, [
            'type' => 'error',
            'payload' => ['message' => $message],
        ]);
    }
}
