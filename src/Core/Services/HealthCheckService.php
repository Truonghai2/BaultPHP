<?php

namespace Core\Services;

use Core\Database\Swoole\SwoolePdoPool;
use Core\Database\Swoole\SwooleRedisPool;
use Core\Exceptions\ServiceUnavailableException;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;
use Throwable;

class HealthCheckService
{
    /**
     * Run all health checks and return the aggregated status.
     *
     * @return array{status: string, components: array}
     */
    public function runChecks(): array
    {
        $checks = [
            'database' => fn () => $this->checkDatabase(),
            'redis' => fn () => $this->checkRedis(),
            'circuit_breakers' => fn () => $this->checkCircuitBreakers(),
        ];

        // Run checks in parallel using Swoole coroutines for efficiency.
        $results = [];
        
        if (Coroutine::getCid() > 0) {
            // Inside coroutine context - run in parallel with WaitGroup
            $wg = new WaitGroup();
            
            foreach ($checks as $key => $callable) {
                $wg->add(1);
                Coroutine::create(function () use ($key, $callable, &$results, $wg) {
                    try {
                        $results[$key] = $callable();
                    } catch (Throwable $e) {
                        $results[$key] = [
                            'status' => 'DOWN',
                            'details' => ['error' => $e->getMessage()],
                        ];
                    } finally {
                        $wg->done();
                    }
                });
            }
            
            // Wait for all coroutines to complete
            $wg->wait();
        } else {
            // Outside coroutine context - run sequentially
            foreach ($checks as $key => $callable) {
                try {
                    $results[$key] = $callable();
                } catch (Throwable $e) {
                    $results[$key] = [
                        'status' => 'DOWN',
                        'details' => ['error' => $e->getMessage()],
                    ];
                }
            }
        }

        $overallStatus = 'UP';
        foreach ($results as $component) {
            if ($component['status'] === 'DOWN') {
                $overallStatus = 'DOWN';
                break;
            }
            
            if ($component['status'] === 'DEGRADED') {
                $overallStatus = 'DEGRADED';
            }
        }

        return [
            'status' => $overallStatus,
            'components' => $results,
        ];
    }

    /**
     * Checks the database connection by getting a connection from the pool and running a simple query.
     *
     * @return array{status: string, details: array}
     */
    private function checkDatabase(): array
    {
        // Health check should bypass circuit breaker by creating direct connection
        // This ensures we can check the actual database status, not just circuit breaker state
        
        $pdo = null;
        try {
            $startTime = microtime(true);
            
            $config = config('database.connections.' . config('database.default'));
            if (!$config) {
                return ['status' => 'DOWN', 'details' => ['error' => 'Database config not found']];
            }
            
            $driver = $config['driver'] ?? 'mysql';
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 3306;
            $database = $config['database'] ?? '';
            $username = $config['username'] ?? '';
            $password = $config['password'] ?? '';
            $charset = $config['charset'] ?? 'utf8mb4';
            
            $dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";
            
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 2, // 2 second timeout
            ]);
            
            $pdo->query('SELECT 1');
            
            $latency = round((microtime(true) - $startTime) * 1000);
            
            return [
                'status' => 'UP',
                'details' => ['latency_ms' => $latency],
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'DOWN',
                'details' => ['error' => $e->getMessage()],
            ];
        } finally {
            $pdo = null;
        }
    }

    /**
     * Checks the Redis connection by getting a connection from the pool and running a PING command.
     *
     * @return array{status: string, details: array}
     */
    private function checkRedis(): array
    {
        $redis = null;
        try {
            $startTime = microtime(true);
            
            $config = config('redis.connections.' . config('redis.default', 'default'));
            if (!$config) {
                return ['status' => 'DOWN', 'details' => ['error' => 'Redis config not found']];
            }
            
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 6379;
            $password = $config['password'] ?? null;
            $database = $config['database'] ?? 0;
            $timeout = 2.0;
            
            $redis = new \Redis();
            $connected = $redis->connect($host, $port, $timeout);
            
            if (!$connected) {
                return ['status' => 'DOWN', 'details' => ['error' => 'Failed to connect to Redis']];
            }
            
            if ($password) {
                $redis->auth($password);
            }
            
            if ($database > 0) {
                $redis->select($database);
            }
            
            $response = $redis->ping('PONG');
            
            $latency = round((microtime(true) - $startTime) * 1000);
            
            if ($response === true || strtoupper($response) === 'PONG') {
                return [
                    'status' => 'UP',
                    'details' => ['latency_ms' => $latency, 'response' => $response],
                ];
            }
            
            return ['status' => 'DOWN', 'details' => ['error' => 'Invalid PING response']];
        } catch (Throwable $e) {
            return [
                'status' => 'DOWN',
                'details' => ['error' => $e->getMessage()],
            ];
        } finally {
            if ($redis) {
                try {
                    $redis->close();
                } catch (Throwable $e) {
                }
            }
        }
    }

    /**
     * Checks the status of all configured circuit breakers.
     *
     * @return array{status: string, details: array}
     */
    private function checkCircuitBreakers(): array
    {
        $details = [];
        $overallStatus = 'UP';

        if (SwoolePdoPool::isInitialized()) {
            $details['database'] = ['status' => 'CONFIGURED'];
        } else {
            $details['database'] = ['status' => 'NOT_CONFIGURED'];
        }

        if (SwooleRedisPool::isInitialized()) {
            $details['redis'] = ['status' => 'CONFIGURED'];
        } else {
            $details['redis'] = ['status' => 'NOT_CONFIGURED'];
        }

        return [
            'status' => $overallStatus,
            'details' => $details,
        ];
    }
}
