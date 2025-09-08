<?php

namespace Http\Controllers;

use Core\Database\Swoole\SwoolePdoPool;
use Core\Database\Swoole\SwooleRedisPool;
use Core\Http\Controller;
use Core\Server\SwooleServer;
use Http\JsonResponse;

/**
 * Provides real-time status information about the running server,
 * including connection pool statistics.
 */
class ServerStatusController extends Controller
{
    public function __invoke(SwooleServer $server): JsonResponse
    {
        $dbPoolStats = [];
        if (method_exists(SwoolePdoPool::class, 'getAllStats')) {
            $dbPoolStats = SwoolePdoPool::getAllStats();
        } else {
            $dbPoolStats = ['error' => 'SwoolePdoPool::getAllStats() method is not yet implemented.'];
        }

        $redisPoolStats = [];
        if (method_exists(SwooleRedisPool::class, 'getAllStats')) {
            $redisPoolStats = SwooleRedisPool::getAllStats();
        } else {
            $redisPoolStats = ['error' => 'SwooleRedisPool::getAllStats() method is not yet implemented.'];
        }

        return response()->json([
            'server' => [
                'status' => 'ok',
                'swoole_stats' => $server->stats(),
            ],
            'pools' => [
                'database' => $dbPoolStats,
                'redis' => $redisPoolStats,
            ],
        ]);
    }
}
