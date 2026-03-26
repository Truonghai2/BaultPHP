<?php

namespace Core\Server;

use Ackintosh\Ganesha\Builder;
use Ackintosh\Ganesha\Storage\Adapter\Apcu;
use Ackintosh\Ganesha\Storage\Adapter\Redis as RedisAdapter;
use Core\Application;
use InvalidArgumentException;
use RuntimeException;

class CircuitBreakerFactory
{
    /** @var array<string, \Ackintosh\Ganesha> Cache for Ganesha instances to ensure one instance per service per worker. */
    private static array $instances = [];

    /**
     * Creates a Ganesha instance based on the provided configuration.
     *
     * @param array $config The circuit breaker configuration array.
     * @param Application $app The application container.
     * @param string $serviceName Tên của service (ví dụ: 'mysql', 'default_redis').
     * @return \Ackintosh\Ganesha
     */
    public static function create(array $config, Application $app, string $serviceName): \Ackintosh\Ganesha
    {
        $instanceKey = 'breaker_' . $serviceName;
        if (isset(self::$instances[$instanceKey])) {
            return self::$instances[$instanceKey];
        }

        $storageType = $config['storage'] ?? 'apcu'; // Default to APCu for Swoole compatibility
        $strategyType = strtolower($config['strategy'] ?? 'count');

        if ($storageType === 'redis' && extension_loaded('swoole')) {
            $storageType = 'apcu';
        }

        $adapter = match ($storageType) {
            'redis' => self::createRedisAdapter($app, $config),
            'apcu' => self::createApcuAdapter(),
            default => throw new InvalidArgumentException("Unsupported circuit breaker storage: {$storageType}. Supported: 'redis', 'apcu'."),
        };

        $builder = match ($strategyType) {
            'count' => self::configureCountStrategy(Builder::withCountStrategy(), $config['count'] ?? []),
            'rate' => self::configureRateStrategy(Builder::withRateStrategy(), $config['rate'] ?? []),
            default => throw new InvalidArgumentException("Unsupported circuit breaker strategy: {$strategyType}"),
        };

        return self::$instances[$instanceKey] = $builder->adapter($adapter)->build();
    }

    /**
     * Configures the builder for the 'count' strategy.
     */
    private static function configureCountStrategy(
        \Ackintosh\Ganesha\Strategy\Count\Builder $builder,
        array $config,
    ): \Ackintosh\Ganesha\Strategy\Count\Builder {
        return $builder
            ->failureCountThreshold((int)($config['failure_threshold'] ?? 5))
            ->intervalToHalfOpen((int)($config['interval_to_half_open'] ?? 30));
    }

    /**
     * Configures the builder for the 'rate' strategy.
     */
    private static function configureRateStrategy(
        \Ackintosh\Ganesha\Strategy\Rate\Builder $builder,
        array $config,
    ): \Ackintosh\Ganesha\Strategy\Rate\Builder {
        return $builder
            ->failureRateThreshold((int)($config['failure_rate'] ?? 50))
            ->minimumRequests((int)($config['minimum_requests'] ?? 10))
            ->timeWindow((int)($config['time_window'] ?? 60))
            ->intervalToHalfOpen((int)($config['interval_to_half_open'] ?? 30));
    }

    /**
     * Creates a Redis adapter for Ganesha.
     * 
     * IMPORTANT: In Swoole, Redis connections cannot be shared between coroutines.
     * This method creates a dedicated Redis connection for the circuit breaker that is
     * NOT shared with the connection pool. This ensures thread-safety.
     * 
     * For better performance and Swoole compatibility, consider using APCu adapter instead.
     *
     * @param Application $app The application container.
     * @param array $config The circuit breaker configuration, used to find the redis_pool name.
     * @throws RuntimeException If the Redis service cannot be resolved.
     */
    private static function createRedisAdapter(Application $app, array $config): RedisAdapter
    {
        try {
            $redisPoolNameForConfig = $config['redis_pool'] ?? 'default';
            $redisConfig = $app->make('config')->get("redis.connections.{$redisPoolNameForConfig}");

            if (!$redisConfig) {
                throw new \InvalidArgumentException("Redis connection configuration '{$redisPoolNameForConfig}' not found for circuit breaker.");
            }

            $redis = new \Redis();
            $redis->connect(
                $redisConfig['host'],
                $redisConfig['port'],
                $redisConfig['timeout'] ?? 1.0,
            );
            
            if (!empty($redisConfig['password'])) {
                $redis->auth($redisConfig['password']);
            }
            
            if (isset($redisConfig['database'])) {
                $redis->select($redisConfig['database']);
            }
            
            return new RedisAdapter($redis);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Failed to create Redis adapter for Ganesha. Consider using APCu adapter instead for better Swoole compatibility. Error: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * Creates an APCu adapter for Ganesha.
     *
     * @throws RuntimeException If the APCu extension is not loaded.
     */
    private static function createApcuAdapter(): Apcu
    {
        return new Apcu();
    }
    /**
     * Returns the status of all registered circuit breakers.
     *
     * @return array<string, string> Map of Service Name => Status (Closed, Open, Half-Open)
     */
    public static function getAllStatuses(): array
    {
        $statuses = [];
        foreach (self::$instances as $key => $ganesha) {
            $serviceName = str_replace('breaker_', '', $key);
            if ($ganesha->isAvailable($serviceName)) {
                $status = 'Closed'; // Healthy
            } else {
                // Ganesha doesn't easily distinguish between Open and Half-Open in isAvailable API without more checks,
                // but !isAvailable generally means Open.
                $status = 'Open'; // Unhealthy
            }
            $statuses[$serviceName] = $status;
        }
        return $statuses;
    }
}
