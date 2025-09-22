<?php

namespace Core\Redis;

use Amp\Redis\RedisClient;
use Core\Application;
use Core\Contracts\PoolManager;
use Core\Exceptions\ServiceUnavailableException;
use Core\Server\CircuitBreakerFactory;
use InvalidArgumentException;
use Swoole\Coroutine;

/**
 * Quản lý các pool kết nối Redis trong môi trường Fiber.
 */
class FiberRedisManager implements PoolManager
{
    /** @var FiberRedisPool[] */
    private array $pools = [];
    /** @var \Ackintosh\Ganesha[] */
    private array $circuitBreakers = [];

    /** @var array<int, array<string, array<int, RedisClient>>> */
    private array $checkedOutConnections = [];

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
        $breaker = $this->circuitBreaker($name);

        if ($breaker && !$breaker->isAvailable($name)) {
            throw new ServiceUnavailableException(
                "Redis service '{$name}' is currently unavailable (circuit is open).",
            );
        }

        try {
            $connection = $this->pool($name)->get();
            $breaker?->success($name);

            $cid = Coroutine::getCid();
            if ($cid > 0) {
                $this->checkedOutConnections[$cid][$name][spl_object_id($connection)] = $connection;
            }

            return $connection;
        } catch (\Throwable $e) {
            $breaker?->failure($name);
            throw $e;
        }
    }

    /**
     * Lấy một kết nối Redis từ pool được cấu hình riêng cho scheduler.
     *
     * @param string|null $name Tên của connection (ví dụ: 'default', 'cache').
     * @return RedisClient
     */
    public function getForScheduler(?string $name = null): RedisClient
    {
        $name = $name ?? 'default';
        return $this->getInternal($name, null, true);
    }

    /**
     * Trả một kết nối Redis về lại pool.
     *
     * @param mixed $connection
     * @param string|null $name
     */
    public function put($connection, ?string $name = null): void
    {
        if (!$connection instanceof RedisClient) {
            throw new \InvalidArgumentException('Connection must be an instance of RedisClient.');
        }
        $name = $name ?? 'default';
        $this->pool($name)->put($connection);

        $cid = Coroutine::getCid();
        if ($cid > 0) {
            unset($this->checkedOutConnections[$cid][$name][spl_object_id($connection)]);
            if (empty($this->checkedOutConnections[$cid][$name])) {
                unset($this->checkedOutConnections[$cid][$name]);
            }
            if (empty($this->checkedOutConnections[$cid])) {
                unset($this->checkedOutConnections[$cid]);
            }
        }
    }

    /**
     * Releases any connections that were checked out by the current coroutine but not explicitly returned.
     * This is a failsafe mechanism to prevent connection leaks in case of unhandled exceptions.
     */
    public function releaseUnmanaged(): void
    {
        $cid = Coroutine::getCid();
        if ($cid <= 0 || !isset($this->checkedOutConnections[$cid])) {
            return;
        }

        foreach ($this->checkedOutConnections[$cid] as $name => $connections) {
            foreach ($connections as $connection) {
                $this->pool($name)->put($connection);
            }
        }
        unset($this->checkedOutConnections[$cid]);
    }

    /**
     * Lấy ra instance của pool cho một connection cụ thể.
     *
     * @param string $name
     * @return FiberRedisPool
     */
    private function pool(string $name, ?int $poolSize = null, bool $isScheduler = false): FiberRedisPool
    {
        if (!isset($this->pools[$name])) {
            $this->pools[$name] = $this->createPool($name, $poolSize, $isScheduler);
        }

        return $this->pools[$name];
    }

    /**
     * Tạo một pool mới cho một connection.
     *
     * @param string $name
     * @return FiberRedisPool
     */
    private function createPool(string $name, ?int $poolSize = null, bool $isScheduler = false): FiberRedisPool
    {
        $config = $this->app->make('config');
        $redisConfig = $config->get("redis.connections.{$name}");

        if (!$redisConfig) {
            throw new InvalidArgumentException("Redis connection [{$name}] not configured.");
        }

        if ($isScheduler) {
            $poolSize = $this->getPoolConfigValue($name, 'scheduler_pool_size', 1);
        } elseif ($poolSize === null) {
            $isTaskWorker = false;
            if ($this->app->bound('swoole.server')) {
                $server = $this->app->make('swoole.server');
                $isTaskWorker = (bool) ($server->taskworker ?? false);
            }
            $poolSize = $this->getPoolConfigValue($name, $isTaskWorker ? 'task_worker_pool_size' : 'worker_pool_size');
        }

        $factory = function () use ($redisConfig): \Amp\Redis\RedisClient {
            $password = !empty($redisConfig['password']) ? rawurlencode((string)$redisConfig['password']) . '@' : '';
            $database = $redisConfig['database'] ?? 0;
            $connectionString = sprintf(
                'redis://%s%s:%s/%d',
                $password,
                $redisConfig['host'],
                $redisConfig['port'],
                $database,
            );

            return \Amp\Redis\createRedisClient($connectionString);
        };

        return new FiberRedisPool($factory, (int) $poolSize);
    }

    /**
     * Làm ấm các pool kết nối đã được cấu hình.
     */
    public function warmup(): void
    {
        $config = $this->app->make('config');
        $redisPoolConfig = $config->get('server.swoole.redis_pool', []);

        if (empty($redisPoolConfig['enabled'])) {
            return;
        }

        $redisPoolsConfig = $redisPoolConfig['pools'] ?? [];

        foreach (array_keys($redisPoolsConfig) as $name) {
            $isTaskWorker = false;
            if ($this->app->bound('swoole.server')) {
                $server = $this->app->make('swoole.server');
                $isTaskWorker = (bool) ($server->taskworker ?? false);
            }

            $poolSize = $this->getPoolConfigValue($name, $isTaskWorker ? 'task_worker_pool_size' : 'worker_pool_size');
            $warmupSize = $this->getPoolConfigValue($name, $isTaskWorker ? 'task_worker_warmup_size' : 'worker_warmup_size');

            $targetSize = min((int) $warmupSize, (int) $poolSize);

            if ($targetSize <= 0) {
                continue;
            }

            $connections = [];
            for ($i = 0; $i < $targetSize; $i++) {
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

    /**
     * Lấy hoặc tạo instance Circuit Breaker cho một pool.
     *
     * @param string $name
     * @return \Ackintosh\Ganesha|null
     */
    private function circuitBreaker(string $name): ?\Ackintosh\Ganesha
    {
        if (isset($this->circuitBreakers[$name])) {
            return $this->circuitBreakers[$name];
        }

        $config = $this->app->make('config');
        $specificConfig = $config->get("server.swoole.redis_pool.pools.{$name}.circuit_breaker", []);
        $defaultConfig = $config->get('server.swoole.redis_pool.circuit_breaker', []);

        $breakerConfig = array_replace_recursive($defaultConfig, $specificConfig);

        if (empty($breakerConfig['enabled'])) {
            return null;
        }

        return $this->circuitBreakers[$name] = CircuitBreakerFactory::create($breakerConfig, $this->app, $name);
    }

    /**
     * Internal method to get a connection, allowing for a specific pool size override.
     */
    private function getInternal(string $name, ?int $poolSize = null, bool $isScheduler = false): RedisClient
    {
        $breaker = $this->circuitBreaker($name);

        if ($breaker && !$breaker->isAvailable($name)) {
            throw new ServiceUnavailableException(
                "Redis service '{$name}' is currently unavailable (circuit is open).",
            );
        }

        try {
            $connection = $this->pool($name, $poolSize, $isScheduler)->get();
            $breaker?->success($name);

            $cid = Coroutine::getCid();
            if ($cid > 0) {
                $this->checkedOutConnections[$cid][$name][spl_object_id($connection)] = $connection;
            }

            return $connection;
        } catch (\Throwable $e) {
            $breaker?->failure($name);
            throw $e;
        }
    }

    /**
     * Lấy một giá trị cấu hình cho pool, ưu tiên cấu hình riêng của pool,
     * sau đó fallback về cấu hình mặc định.
     *
     * @param string $poolName Tên của pool (ví dụ: 'default').
     * @param string $key Tên của khóa cấu hình (ví dụ: 'worker_pool_size').
     * @param mixed|null $default The default value to return if no config is found.
     * @return mixed
     */
    private function getPoolConfigValue(string $poolName, string $key, mixed $default = null): mixed
    {
        $config = $this->app->make('config');

        $specificValue = $config->get("server.swoole.redis_pool.pools.{$poolName}.{$key}");
        if ($specificValue !== null) {
            return $specificValue;
        }

        return $config->get("server.swoole.redis_pool.defaults.{$key}", $default);
    }
}
