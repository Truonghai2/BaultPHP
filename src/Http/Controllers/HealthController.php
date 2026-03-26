<?php

namespace App\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Psr\Http\Message\ResponseInterface;

/**
 * Health Check Controller.
 * Provides health check endpoints for monitoring and load balancers.
 * Group 'light': không session/CSRF – ổn định toàn hệ thống.
 */
#[Route(group: 'light')]
class HealthController extends Controller
{
    /**
     * Basic health check.
     * 
     * GET /health
     */
    #[Route('/health', method: 'GET')]
    public function index(): ResponseInterface
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => time(),
            'service' => config('app.name'),
            'version' => config('app.version', '1.0.0'),
        ]);
    }

    /**
     * Readiness probe.
     * 
     * Checks if application is ready to accept traffic.
     * 
     * GET /health/ready
     */
    #[Route('/health/ready', method: 'GET')]
    public function ready(): ResponseInterface
    {
        $checks = [];
        $ready = true;

        // Check database
        try {
            $pdo = app()->make(\PDO::class);
            $pdo->query('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Throwable $e) {
            $checks['database'] = 'error: ' . $e->getMessage();
            $ready = false;
        }

        // Check cache (Redis)
        try {
            cache()->set('health_check', time(), 10);
            cache()->get('health_check');
            $checks['cache'] = 'ok';
        } catch (\Throwable $e) {
            $checks['cache'] = 'warning: ' . $e->getMessage();
            // Not critical for readiness
        }

        $status = $ready ? 200 : 503;

        return response()->json([
            'ready' => $ready,
            'checks' => $checks,
            'timestamp' => time(),
        ], $status);
    }

    /**
     * Liveness probe.
     * 
     * Checks if application is alive (not deadlocked).
     * 
     * GET /health/live
     */
    #[Route('/health/live', method: 'GET')]
    public function live(): ResponseInterface
    {
        // Simple liveness check - if we can respond, we're alive
        return response()->json([
            'alive' => true,
            'timestamp' => time(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ]);
    }

    /**
     * Detailed status (for monitoring).
     * 
     * GET /health/status
     */
    #[Route('/health/status', method: 'GET')]
    public function status(): ResponseInterface
    {
        $status = [
            'service' => config('app.name'),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'timestamp' => time(),
            'uptime' => $this->getUptime(),
            'memory' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit'),
            ],
            'swoole' => extension_loaded('swoole') ? [
                'version' => phpversion('swoole'),
                'worker_id' => $this->getWorkerId(),
            ] : null,
        ];

        return response()->json($status);
    }

    /**
     * Get application uptime.
     */
    protected function getUptime(): ?int
    {
        if (extension_loaded('swoole')) {
            $stats = \Swoole\Server::stats();
            return $stats['start_time'] ?? null;
        }

        return null;
    }

    /**
     * Get Swoole worker ID.
     */
    protected function getWorkerId(): ?int
    {
        if (extension_loaded('swoole')) {
            return \Swoole\Coroutine::getCid();
        }

        return null;
    }
}
