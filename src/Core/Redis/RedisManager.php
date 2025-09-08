<?php

namespace Core\Redis;

use Core\Application;
use Core\Database\Swoole\SwooleRedisPool;

if (!class_exists('RedisException')) {
    class RedisException extends \Exception
    {
    }
}

/**
 * Class RedisManager
 * Quản lý các kết nối Redis.
 * Cho phép sử dụng nhiều kết nối khác nhau được định nghĩa trong file config.
 */
class RedisManager
{
    /**
     * The application instance.
     * @var Application
     */
    protected Application $app;

    /**
     * The Redis configuration array.
     * @var array
     */
    protected array $config;

    /**
     * The active Redis connections.
     * @var array<string, \Redis>
     */
    protected array $connections = [];

    public function __construct(Application $app, array $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Lấy một instance kết nối Redis.
     *
     * @param string|null $name Tên của kết nối. Nếu là null, sẽ sử dụng kết nối mặc định.
     * @return \Redis
     */
    public function connection(?string $name = null): \Redis
    {
        // Tối ưu hóa cho Swoole: Trong môi trường coroutine, việc giữ một kết nối
        // lâu dài là nguy hiểm. Bắt buộc phải sử dụng connection pool.
        $isSwooleCoroutine = extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0;
        if ($isSwooleCoroutine && class_exists(SwooleRedisPool::class) && SwooleRedisPool::isInitialized()) {
            throw new RedisException('In a Swoole coroutine, you must use getFromPool() and putToPool() to manage Redis connections safely.');
        }

        $name = $name ?: $this->getDefaultConnection();

        // Nếu kết nối đã được tạo, trả về nó.
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        // Nếu chưa, tạo kết nối mới, lưu lại và trả về.
        return $this->connections[$name] = $this->resolve($name);
    }

    /**
     * Lấy một kết nối từ pool trong môi trường Swoole.
     *
     * @return \Redis
     * @throws RedisException
     */
    public function getFromPool(): \Redis
    {
        if (class_exists(SwooleRedisPool::class) && SwooleRedisPool::isInitialized()) {
            return SwooleRedisPool::get();
        }

        // Fallback to a regular connection if not in a Swoole pool environment.
        return $this->connection();
    }

    /**
     * Trả một kết nối về lại pool trong môi trường Swoole.
     * @param \Redis $connection
     */
    public function putToPool(\Redis $connection): void
    {
        if (class_exists(SwooleRedisPool::class) && SwooleRedisPool::isInitialized()) {
            SwooleRedisPool::put($connection);
        }
    }

    /**
     * Tạo một kết nối Redis mới.
     *
     * @param string $name
     * @return \Redis
     * @throws RedisException
     */
    protected function resolve(string $name): \Redis
    {
        if (!class_exists('Redis')) {
            throw new RedisException('The phpredis extension is not installed or enabled. Please install it to use Redis features.');
        }

        $config = $this->getConfig($name);

        try {
            $redis = new \Redis();
            $redis->connect(
                $config['host'],
                $config['port'],
                $this->config['options']['timeout'] ?? 0.0,
            );

            if (!empty($config['password'])) {
                $redis->auth($config['password']);
            }

            if (isset($config['database'])) {
                $redis->select((int) $config['database']);
            }
        } catch (\RedisException $e) {
            throw new RedisException(
                "Could not connect to Redis connection [{$name}] at {$config['host']}:{$config['port']}. Please check your network configuration and credentials. Original error: " . $e->getMessage(),
                $e->getCode(),
                $e,
            );
        }
        return $redis;
    }

    protected function getConfig(string $name): array
    {
        if (!isset($this->config['connections'][$name])) {
            throw new \InvalidArgumentException("Redis connection [{$name}] not configured.");
        }

        return $this->config['connections'][$name];
    }

    public function getDefaultConnection(): string
    {
        return $this->config['default'] ?? 'default';
    }

    public function __call(string $method, array $parameters)
    {
        // Tối ưu hóa cho Swoole: Lấy kết nối từ pool, thực thi, rồi trả về.
        // Điều này làm cho việc gọi các lệnh Redis đơn lẻ trở nên an toàn trong coroutine.
        $isSwooleCoroutine = extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0;
        if ($isSwooleCoroutine && class_exists(SwooleRedisPool::class) && SwooleRedisPool::isInitialized()) {
            $redis = $this->getFromPool();
            try {
                return $redis->{$method}(...$parameters);
            } finally {
                $this->putToPool($redis);
            }
        }

        return $this->connection()->{$method}(...$parameters);
    }
}
