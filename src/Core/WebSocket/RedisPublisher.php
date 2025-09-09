<?php

namespace Core\WebSocket;

use Core\Cache\CacheManager;
use Predis\ClientInterface;

/**
 * Gửi tin nhắn đến Redis Pub/Sub để WebSocket server có thể nhận và xử lý.
 */
class RedisPublisher
{
    private ClientInterface $redis;
    private string $channel = 'websocket_messages';

    public function __construct(CacheManager $cacheManager)
    {
        // Giả sử CacheManager có thể trả về Redis client gốc
        $this->redis = $cacheManager->connection('redis')->client();
    }

    /**
     * Gửi một tin nhắn đến một hoặc nhiều người dùng.
     *
     * @param array|int $userIds ID của người dùng nhận tin.
     * @param array $payload Dữ liệu cần gửi.
     */
    public function publish(array|int $userIds, array $payload): void
    {
        $message = json_encode([
            'users' => (array) $userIds,
            'payload' => $payload,
        ]);

        $this->redis->publish($this->channel, $message);
    }
}
