<?php

namespace App\Http\Controllers\Admin;

use App\Http\JsonResponse;
use App\Http\Middleware\VerifyDeveloperIpMiddleware;
use Core\Database\Swoole\SwoolePdoPool;
use Core\Database\Swoole\SwooleRedisPool;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Psr\Http\Message\ResponseInterface;

class StatsController extends Controller
{
    /**
     * Lấy và trả về trạng thái của tất cả các connection pool đang hoạt động.
     * Route này được bảo vệ bởi VerifyDeveloperIpMiddleware, chỉ cho phép truy cập
     * từ các IP đã được cấu hình trong file .env (APP_DEVELOPER_IPS).
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[Route('/admin/stats/pools', method: 'GET')]
    public function connectionPools(): ResponseInterface
    {
        $pdoStats = SwoolePdoPool::getAllStats();
        $redisStats = SwooleRedisPool::getAllStats();

        $stats = [
            'database' => $pdoStats,
            'redis' => $redisStats,
        ];

        return new JsonResponse($stats);
    }
}
