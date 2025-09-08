<?php

namespace Http\Controllers;

use Core\Database\Swoole\SwoolePdoPool;
use Core\Database\Swoole\SwooleRedisPool;
use Core\Http\Controller;
use Core\Server\SwooleServer;
use Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides real-time server metrics in Prometheus exposition format.
 */
class PrometheusMetricsController extends Controller
{
    public function __invoke(SwooleServer $server, ResponseFactory $responseFactory): ResponseInterface
    {
        $metrics = [];

        // --- Database Pool Metrics ---
        if (method_exists(SwoolePdoPool::class, 'getAllStats')) {
            $dbPoolStats = SwoolePdoPool::getAllStats();
            foreach ($dbPoolStats as $name => $stats) {
                if (isset($stats['pool_size'])) {
                    $metrics[] = $this->gauge(
                        'bault_db_pool_connections_total',
                        'Total connections configured for the pool.',
                        $stats['pool_size'],
                        ['pool' => $name],
                    );
                }
                if (isset($stats['connections_in_use'])) {
                    $metrics[] = $this->gauge(
                        'bault_db_pool_connections_in_use',
                        'Number of connections currently in use.',
                        $stats['connections_in_use'],
                        ['pool' => $name],
                    );
                }
            }
        }

        // --- Redis Pool Metrics ---
        if (method_exists(SwooleRedisPool::class, 'getAllStats')) {
            $redisPoolStats = SwooleRedisPool::getAllStats();
            foreach ($redisPoolStats as $name => $stats) {
                if (isset($stats['pool_size'])) {
                    $metrics[] = $this->gauge(
                        'bault_redis_pool_connections_total',
                        'Total Redis connections configured for the pool.',
                        $stats['pool_size'],
                        ['pool' => $name],
                    );
                }
                if (isset($stats['connections_in_use'])) {
                    $metrics[] = $this->gauge(
                        'bault_redis_pool_connections_in_use',
                        'Number of Redis connections currently in use.',
                        $stats['connections_in_use'],
                        ['pool' => $name],
                    );
                }
            }
        }

        // --- Swoole Server Metrics ---
        $swooleStats = $server->stats();
        $metrics[] = $this->gauge('bault_swoole_connections_active', 'Number of active TCP connections.', $swooleStats['connection_num'] ?? 0);
        $metrics[] = $this->counter('bault_swoole_requests_total', 'Total number of requests received.', $swooleStats['request_count'] ?? 0);
        $metrics[] = $this->gauge('bault_swoole_workers_idle', 'Number of idle worker processes.', $swooleStats['idle_worker_num'] ?? 0);
        $metrics[] = $this->gauge('bault_swoole_task_queue_num', 'Number of tasks waiting in the task queue.', $swooleStats['task_queue_num'] ?? 0);

        $body = implode("\n", $metrics);

        // Trả về response dạng text với Content-Type phù hợp
        return $responseFactory->make($body, 200, [
            'Content-Type' => 'application/openmetrics-text; version=1.0.0; charset=utf-8',
        ]);
    }

    /**
     * Formats a metric line for Prometheus.
     */
    private function formatMetric(string $type, string $name, string $help, float|int $value, array $labels = []): string
    {
        $labelStr = '';
        if (!empty($labels)) {
            $labelParts = [];
            foreach ($labels as $key => $val) {
                $labelParts[] = sprintf('%s="%s"', $key, addslashes($val));
            }
            $labelStr = '{' . implode(',', $labelParts) . '}';
        }

        return sprintf("# HELP %s %s\n# TYPE %s %s\n%s%s %s", $name, $help, $name, $type, $name, $labelStr, $value);
    }

    private function gauge(string $name, string $help, float|int $value, array $labels = []): string
    {
        return $this->formatMetric('gauge', $name, $help, $value, $labels);
    }

    private function counter(string $name, string $help, float|int $value, array $labels = []): string
    {
        return $this->formatMetric('counter', $name, $help, $value, $labels);
    }
}
