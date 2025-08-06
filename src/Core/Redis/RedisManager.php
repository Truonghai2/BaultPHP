<?php

namespace Core\Redis;

use Core\Application;
use InvalidArgumentException;
use RedisException;
use Spiral\RoadRunner\Console\Configuration\Section\Redis;

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
     * @var array<string, Redis>
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
     * @return Redis
     */
    public function connection(?string $name = null): Redis
    {
        $name = $name ?: $this->getDefaultConnection();

        // Nếu kết nối đã được tạo, trả về nó.
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        // Nếu chưa, tạo kết nối mới, lưu lại và trả về.
        return $this->connections[$name] = $this->resolve($name);
    }

    /**
     * Tạo một kết nối Redis mới.
     *
     * @param string $name
     * @return Redis
     * @throws RedisException
     */
    protected function resolve(string $name): Redis
    {
        $config = $this->getConfig($name);

        $redis = new Redis();

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

        return $redis;
    }

    protected function getConfig(string $name): array
    {
        if (!isset($this->config['connections'][$name])) {
            throw new InvalidArgumentException("Redis connection [{$name}] not configured.");
        }

        return $this->config['connections'][$name];
    }

    public function getDefaultConnection(): string
    {
        return $this->config['default'] ?? 'default';
    }

    public function __call(string $method, array $parameters)
    {
        return $this->connection()->{$method}(...$parameters);
    }
}
