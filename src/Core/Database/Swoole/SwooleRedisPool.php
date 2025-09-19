<?php

namespace Core\Database\Swoole;

use Redis;
use Throwable;

/**
 * Manages a pool of Redis connections using a custom channel-based pool.
 * This class extends BaseSwoolePool to provide Redis-specific logic for
 * creating, pinging, and validating connections.
 */
class SwooleRedisPool extends BaseSwoolePool
{
    protected static function createConnection(string $name): mixed
    {
        $config = static::$configs[$name];
        $redis = new Redis();

        try {
            $redis->connect(
                $config['host'] ?? '127.0.0.1',
                (int) ($config['port'] ?? 6379),
                $config['timeout'] ?? 1.0,
            );

            if (!empty($config['password'])) {
                $redis->auth($config['password']);
            }

            if (isset($config['database'])) {
                $redis->select($config['database']);
            }

            $redis->lastUsedTime = time();
            return $redis;
        } catch (Throwable $e) {
            static::$app->make(\Psr\Log\LoggerInterface::class)
                ->error("Failed to create Redis connection for '{$name}': " . $e->getMessage());
            return false;
        }
    }

    protected static function ping(mixed $connection): bool
    {
        if (!$connection instanceof Redis) {
            return false;
        }

        // Giả sử tên pool là 'default' nếu không có cách nào xác định.
        // Một cải tiến trong tương lai có thể là truyền tên pool vào hàm ping.
        $config = static::$configs['default'] ?? [];
        $heartbeat = $config['heartbeat'] ?? 60;

        if (isset($connection->lastUsedTime) && time() - $connection->lastUsedTime < $heartbeat) {
            return true;
        }

        try {
            // Lệnh PING của Redis sẽ trả về "+PONG" hoặc ném exception nếu thất bại.
            $connection->ping();
            $connection->lastUsedTime = time();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected static function isValid(mixed $connection): bool
    {
        if ($connection instanceof Redis) {
            // Không trả về pool nếu đang trong một transaction.
            return !$connection->getMode() == Redis::MULTI;
        }
        return false;
    }
}
