<?php

namespace Core\Redis;

use Amp\Redis\RedisClient;
use Core\Application;
use Core\Contracts\PoolManager;
use InvalidArgumentException;

/**
 * Quản lý các pool kết nối Redis trong môi trường Fiber.
 */
class FiberRedisManager implements PoolManager
{
    /** @var FiberRedisPool[] */
    private array $pools = [];

    public function __construct(private readonly Application $app)
    {
    }

    /**
     * Lấy một kết nối Redis từ pool.
     *
     * @param string|null $name Tên của connection (ví dụ: 'default', 'cache').
     * @return RedisClient
     */
    public function get(?string $name = null): RedisClient
    {
        $name = $name ?? 'default';
        return $this->pool($name)->get();
    }

    /**
     * Trả một kết nối Redis về lại pool.
     *
     * @param RedisClient $connection
     * @param string|null $name
     */
    public function put(RedisClient $connection, ?string $name = null): void
    {
        $name = $name ?? 'default';
        $this->pool($name)->put($connection);
    }

    /**
     * Lấy ra instance của pool cho một connection cụ thể.
     *
     * @param string $name
     * @return FiberRedisPool
     */
    private function pool(string $name): FiberRedisPool
    {
        if (!isset($this->pools[$name])) {
            $this->pools[$name] = $this->createPool($name);
        }

        return $this->pools[$name];
    }

    /**
     * Tạo một pool mới cho một connection.
     *
     * @param string $name
     * @return FiberRedisPool
     */
    private function createPool(string $name): FiberRedisPool
    {
        $config = $this->app->make('config');
        $redisConfig = $config->get("redis.connections.{$name}");

        if (!$redisConfig) {
            throw new InvalidArgumentException("Redis connection [{$name}] not configured.");
        }

        $poolSize = $config->get('server.swoole.redis_pool.worker_pool_size', 10);

        $poolConfig = $config->get("server.swoole.redis_pool.pools.{$name}");
        if ($poolConfig) {
            $isTaskWorker = $this->app->make('swoole.server')->taskworker ?? false;
            $poolSize = $isTaskWorker
                ? ($poolConfig['task_worker_pool_size'] ?? $poolConfig['worker_pool_size'] ?? 10)
                : ($poolConfig['worker_pool_size'] ?? 10);
        }

        $factory = function () use ($redisConfig): \Amp\Future {
            $password = $redisConfig['password'] ? rawurlencode($redisConfig['password']) . '@' : '';
            $connectionString = sprintf(
                'redis://%s%s:%s',
                $password,
                $redisConfig['host'],
                $redisConfig['port'],
            );

            return \Amp\Redis\connect($connectionString);
        };

        return new FiberRedisPool($factory, $poolSize);
    }

    /**
     * Đóng tất cả các kết nối trong tất cả các pool.
     */
    public function close(): void
    {
        foreach ($this->pools as $pool) {
            $pool->close();
        }
        $this->pools = [];
    }
}
