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

            return \Amp\Future::complete(\Amp\Redis\createRedisClient($connectionString));
        };

        return new FiberRedisPool($factory, $poolSize);
    }

    /**
     * Warms up the configured connection pools by creating a predefined number of connections.
     */
    public function warmup(): void
    {
        $config = $this->app->make('config');
        $redisPoolsConfig = $config->get('server.swoole.redis_pool.pools', []);

        // Only warm up pools explicitly defined in the server config.
        foreach ($redisPoolsConfig as $name => $poolConfig) {
            // This logic relies on 'swoole.server' being bound in the container.
            $server = $this->app->make('swoole.server');
            $isTaskWorker = (bool) ($server->taskworker ?? false);

            $poolSize = $isTaskWorker
                ? ($poolConfig['task_worker_pool_size'] ?? $poolConfig['worker_pool_size'] ?? 10)
                : ($poolConfig['worker_pool_size'] ?? 10);

            // Allow per-pool warmup_size, falling back to a global default, then 0.
            $warmupSize = $poolConfig['warmup_size'] ?? $config->get('server.swoole.redis_pool.warmup_size', 0);
            $targetSize = min((int)$warmupSize, (int)$poolSize);

            if ($targetSize <= 0) {
                continue;
            }

            $connections = [];
            for ($i = 0; $i < $targetSize; $i++) {
                // get() will create the pool if it doesn't exist.
                $connections[] = $this->get($name);
            }

            foreach ($connections as $connection) {
                $this->put($connection, $name);
            }
        }
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
