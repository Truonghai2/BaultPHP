<?php

namespace Core\WebSocket;

use Core\Contracts\StatefulService;
use Core\Contracts\WebSocket\WebSocketManagerInterface;
use Swoole\Coroutine\Channel;

/**
 * Quản lý việc gửi tin nhắn đến WebSocket server.
 * Hoạt động như một cầu nối giữa ứng dụng và WebSocket worker.
 */
class WebSocketManager implements WebSocketManagerInterface, StatefulService
{
    /**
     * Kênh giao tiếp giữa các worker.
     * @var Channel|null
     */
    private ?Channel $channel = null;

    public function __construct()
    {
        if (PHP_SAPI === 'cli' && defined('SWOOLE_VERSION')) {
            $this->channel = new Channel(1024);
        }
    }

    /**
     * Lấy channel để WebSocket server có thể lắng nghe.
     */
    public function getChannel(): ?Channel
    {
        return $this->channel;
    }

    /**
     * Gửi một tin nhắn đến một hoặc nhiều người dùng.
     *
     * @param array|string $userIds
     * @param array $payload
     */
    public function sendToUser(array|string $userIds, array $payload): void
    {
        if (!$this->channel) {
            return;
        }

        $message = [
            'type' => 'users',
            'users' => array_map('strval', (array) $userIds),
            'payload' => $payload,
        ];

        // Gửi non-blocking
        $this->channel->push($message, 0.001);
    }

    /**
     * Gửi tin nhắn đến tất cả các client đang kết nối.
     */
    public function broadcast(array $payload): void
    {
        if (!$this->channel) {
            return;
        }

        $message = [
            'type' => 'broadcast',
            'payload' => $payload,
        ];

        $this->channel->push($message, 0.001);
    }

    public function flush(): void
    {
        // Không cần reset channel vì nó là global
    }

    public function resetState(): void
    {
    }
}
