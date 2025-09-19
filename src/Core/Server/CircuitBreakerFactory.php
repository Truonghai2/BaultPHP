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
            return self::$instances[$serviceName];
        }

        $storageType = $config['storage'] ?? 'redis';
        $strategyType = strtolower($config['strategy'] ?? 'count');

        $adapter = match ($storageType) {
            'redis' => self::createRedisAdapter($app),
            'apcu' => self::createApcuAdapter(),
            default => throw new InvalidArgumentException("Unsupported circuit breaker storage: {$storageType}. Only 'redis' is supported."),
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
     * @throws RuntimeException If the Redis service cannot be resolved.
     */
    private static function createRedisAdapter(Application $app): RedisAdapter
    {
        try {
            $config = $app->make('config')->get('database.redis.default', []);

            $redis = new \Redis();
            $redis->connect(
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? 6379,
                1.0,
            );

            if (!empty($config['password'])) {
                $redis->auth($config['password']);
            }

            $redis->select($config['database'] ?? 0);

            return new RedisAdapter($redis);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Failed to create Redis adapter for Ganesha. Ensure the Redis service is bound correctly in the container.',
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
}
