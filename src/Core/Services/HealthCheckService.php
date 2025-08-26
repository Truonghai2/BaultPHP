<?php

namespace Core\Services;

use Core\Database\Swoole\SwoolePdoPool;
use Core\Database\Swoole\SwooleRedisPool;
use Swoole\Coroutine;
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
        $results = Coroutine\parallel($checks);

        $overallStatus = 'UP';
        foreach ($results as $component) {
            if ($component['status'] === 'DOWN') {
                $overallStatus = 'DOWN';
                break; // A single DOWN component makes the whole system DOWN.
            }
            // Mark as DEGRADED if not already DOWN.
            // This is useful for states like HALF_OPEN circuit breakers.
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
        if (!SwoolePdoPool::isInitialized()) {
            return ['status' => 'DOWN', 'details' => ['error' => 'DB pool not initialized.']];
        }

        $connection = null;
        try {
            $startTime = microtime(true);
            $connection = SwoolePdoPool::get();
            $connection->query('SELECT 1');
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
            // CRITICAL: Always return the connection to the pool.
            if ($connection) {
                SwoolePdoPool::put($connection);
            }
        }
    }

    /**
     * Checks the Redis connection by getting a connection from the pool and running a PING command.
     *
     * @return array{status: string, details: array}
     */
    private function checkRedis(): array
    {
        if (!SwooleRedisPool::isInitialized()) {
            return ['status' => 'DOWN', 'details' => ['error' => 'Redis pool not initialized.']];
        }

        $connection = null;
        try {
            $startTime = microtime(true);
            $connection = SwooleRedisPool::get();
            $response = $connection->ping('PONG');
            $latency = round((microtime(true) - $startTime) * 1000);

            if ($response === true || strtoupper($response) === 'PONG') {
                return [
                    'status' => 'UP',
                    'details' => ['latency_ms' => $latency, 'response' => $response],
                ];
            }

            return ['status' => 'DOWN', 'details' => ['error' => 'Invalid PING response.']];
        } catch (Throwable $e) {
            return [
                'status' => 'DOWN',
                'details' => ['error' => $e->getMessage()],
            ];
        } finally {
            // CRITICAL: Always return the connection to the pool.
            if ($connection) {
                SwooleRedisPool::put($connection);
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

        $dbBreaker = SwoolePdoPool::getCircuitBreaker();
        if ($dbBreaker) {
            if (!$dbBreaker->isAvailable(SwoolePdoPool::getServiceName())) {
                $details['database'] = ['state' => 'OPEN', 'status' => 'DOWN'];
                $details['database']['status'] = 'DOWN';
                $overallStatus = 'DOWN';
            } else {
                $details['database'] = ['state' => 'CLOSED_OR_HALF_OPEN', 'status' => 'UP'];
            }
        } else {
            $details['database'] = ['status' => 'NOT_CONFIGURED'];
        }

        $redisBreaker = SwooleRedisPool::getCircuitBreaker();
        if ($redisBreaker) {
            if (!$redisBreaker->isAvailable(SwooleRedisPool::getServiceName())) {
                $details['redis'] = ['state' => 'OPEN', 'status' => 'DOWN'];
                $details['redis']['status'] = 'DOWN';
                $overallStatus = 'DOWN';
            } else {
                $details['redis'] = ['state' => 'CLOSED_OR_HALF_OPEN', 'status' => 'UP'];
            }
        } else {
            $details['redis'] = ['status' => 'NOT_CONFIGURED'];
        }

        return [
            'status' => $overallStatus,
            'details' => $details,
        ];
    }
}
