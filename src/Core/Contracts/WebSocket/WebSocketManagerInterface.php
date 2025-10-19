<?php

namespace Core\Contracts\WebSocket;

use Swoole\Coroutine\Channel;

/**
 * Interface for managing WebSocket messages.
 * Acts as a bridge between the application and the WebSocket worker.
 */
interface WebSocketManagerInterface
{
    /**
     * Get the channel for the WebSocket server to listen on.
     */
    public function getChannel(): ?Channel;

    /**
     * Send a message to one or more users/sessions.
     */
    public function sendToUser(array|string $userIds, array $payload): void;

    /**
     * Send a message to all connected clients.
     */
    public function broadcast(array $payload): void;
}
