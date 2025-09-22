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
    private static ?\WeakMap $lastUsedTimes = null;

    protected static function createConnection(string $name): mixed
    {
        self::$lastUsedTimes ??= new \WeakMap();

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

            self::$lastUsedTimes[$redis] = time();
            return $redis;
        } catch (Throwable $e) {
            static::$app->make(\Psr\Log\LoggerInterface::class)
                ->error("Failed to create Redis connection for '{$name}': " . $e->getMessage());
            return false;
        }
    }

    protected static function ping(mixed $connection, string $name): bool
    {
        if (!$connection instanceof Redis) {
            return false;
        }

        $config = static::$configs[$name] ?? [];
        $heartbeat = $config['heartbeat'] ?? 60;

        if (isset(self::$lastUsedTimes[$connection]) && time() - self::$lastUsedTimes[$connection] < $heartbeat) {
            return true;
        }

        try {
            $connection->ping();
            self::$lastUsedTimes[$connection] = time();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected static function isValid(mixed $connection): bool
    {
        if ($connection instanceof Redis) {
            return !$connection->getMode() == Redis::MULTI;
        }
        return false;
    }
}
