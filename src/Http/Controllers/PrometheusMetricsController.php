<?php

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Http\ResponseFactory;
use Core\Database\Swoole\SwoolePdoPool;
use Core\Database\Swoole\SwooleRedisPool;
use Core\Http\Controller;
use Core\Metrics\SwooleMetricsService;
use Core\Routing\Attributes\Route;
use Core\Server\SwooleServer;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides real-time server metrics in Prometheus exposition format.
 */
class PrometheusMetricsController extends Controller
{
    /**
     * Trả về metrics ở định dạng Prometheus.
     * Được bảo vệ bởi token.
     */
    #[Route('/metrics', method: 'GET', middleware: [\App\Http\Middleware\ProtectMetricsMiddleware::class])]
    public function __invoke(
        SwooleServer $server,
        ResponseFactory $responseFactory,
        ?SwooleMetricsService $metricsService = null,
    ): ResponseInterface {
        if ($metricsService === null) {
            return $responseFactory->make('Metrics service is not available.', 503, ['Content-Type' => 'text/plain']);
        }

        $this->updateDynamicMetrics($server, $metricsService);

        $body = $metricsService->getMetricsAsPrometheus();

        return $responseFactory->make($body, 200, [
            'Content-Type' => 'application/openmetrics-text; version=1.0.0; charset=utf-8',
        ]);
    }

    /**
     * Trả về trạng thái của server và các pool dưới dạng JSON.
     * Được bảo vệ bằng IP whitelist.
     */
    #[Route('/stats', method: 'GET', middleware: [\App\Http\Middleware\VerifyDeveloperIpMiddleware::class])]
    public function jsonStats(SwooleServer $server): JsonResponse
    {
        $dbPoolStats = [];
        if (class_exists(SwoolePdoPool::class) && method_exists(SwoolePdoPool::class, 'getAllStats')) {
            $dbPoolStats = SwoolePdoPool::getAllStats();
        }
        $redisPoolStats = [];
        if (class_exists(SwooleRedisPool::class) && method_exists(SwooleRedisPool::class, 'getAllStats')) {
            $redisPoolStats = SwooleRedisPool::getAllStats();
        }
        $swooleStats = $server->stats();

        $stats = array_filter([
            'swoole_server' => $swooleStats,
            'database_pools' => $dbPoolStats,
            'redis_pools' => $redisPoolStats,
        ]);

        return new JsonResponse($stats);
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
