<?php

namespace Http\Controllers;

use Core\Metrics\SwooleMetricsService;
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
    public function __invoke(
        SwooleServer $server,
        ResponseFactory $responseFactory,
        ?SwooleMetricsService $metricsService = null
    ): ResponseInterface {
        if ($metricsService === null) {
            return $responseFactory->make('Metrics service is not available.', 503, ['Content-Type' => 'text/plain']);
        }

        // --- Cập nhật các metrics động (dynamic metrics) trước khi xuất ---
        $this->updateDynamicMetrics($server, $metricsService);

        // Lấy tất cả metrics đã được định dạng sẵn từ service
        $body = $metricsService->getMetricsAsPrometheus();

        // Trả về response dạng text với Content-Type phù hợp
        return $responseFactory->make($body, 200, [
            'Content-Type' => 'application/openmetrics-text; version=1.0.0; charset=utf-8',
        ]);
    }

    /**
     * Update metrics that change on every scrape, like gauges for current states.
     */
    private function updateDynamicMetrics(SwooleServer $server, SwooleMetricsService $metricsService): void
    {
        // --- Database Pool Metrics ---
        if (method_exists(SwoolePdoPool::class, 'getAllStats')) {
            $dbPoolStats = SwoolePdoPool::getAllStats();
            foreach ($dbPoolStats as $name => $stats) {
                $metricsService->setGauge('bault_db_pool_connections_total', $stats['pool_size'] ?? 0, ['pool' => $name]);
                $metricsService->setGauge('bault_db_pool_connections_in_use', $stats['connections_in_use'] ?? 0, ['pool' => $name]);
            }
        }

        // --- Redis Pool Metrics ---
        if (method_exists(SwooleRedisPool::class, 'getAllStats')) {
            $redisPoolStats = SwooleRedisPool::getAllStats();
            foreach ($redisPoolStats as $name => $stats) {
                $metricsService->setGauge('bault_redis_pool_connections_total', $stats['pool_size'] ?? 0, ['pool' => $name]);
                $metricsService->setGauge('bault_redis_pool_connections_in_use', $stats['connections_in_use'] ?? 0, ['pool' => $name]);
            }
        }

        // --- Swoole Server Metrics ---
        $swooleStats = $server->stats();
        $metricsService->setGauge('bault_swoole_connections_active', $swooleStats['connection_num'] ?? 0);
        $metricsService->setGauge('bault_swoole_workers_idle', $swooleStats['idle_worker_num'] ?? 0);
        $metricsService->setGauge('bault_swoole_task_queue_num', $swooleStats['task_queue_num'] ?? 0);
        // `request_count` là một counter, nó nên được tăng lên ở mỗi request, không phải set ở đây.
        // Ví dụ: $metricsService->increment('bault_swoole_requests_total'); trong middleware.
    }
}
