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

    protected static function ping(mixed $rawConnection, string $name): bool
    {
        if (!$rawConnection instanceof Redis) {
            return false;
        }

        $config = static::$configs[$name] ?? [];
        $heartbeat = $config['heartbeat'] ?? 60;

        if (isset(self::$lastUsedTimes[$rawConnection]) && time() - self::$lastUsedTimes[$rawConnection] < $heartbeat) {
            return true;
        }

        try {
            $rawConnection->ping();
            self::$lastUsedTimes[$rawConnection] = time();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected static function isValid(mixed $rawConnection): bool
    {
        if ($rawConnection instanceof Redis) {
            return $rawConnection->getMode() !== Redis::MULTI;
        }
        return false;
    }

    /**
     * Lấy thông tin trạng thái của một pool cụ thể.
     *
     * @param string $name Tên của pool.
     * @return array|null
     */
    public static function stats(string $name): ?array
    {
        if (!isset(static::$pools[$name])) {
            return null;
        }

        return [
            'pool_size' => static::$pools[$name]->capacity,
            'connections_in_use' => static::$pools[$name]->capacity - static::$pools[$name]->length(),
            'connections_idle' => static::$pools[$name]->length(),
        ];
    }

    /** @return array<string, array> */
    public static function getAllStats(): array
    {
        $stats = [];
        foreach (array_keys(static::$pools) as $name) {
            $stats[$name] = static::stats($name);
        }
        return $stats;
    }
}
