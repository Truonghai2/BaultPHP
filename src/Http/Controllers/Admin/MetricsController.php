<?php

namespace Http\Controllers\Admin;

use Core\Database\Swoole\SwoolePdoPool;
use Core\Database\Swoole\SwooleRedisPool;
use Core\Routing\Attributes\Route;
use Http\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;

class MetricsController
{
    #[Route('/_metrics', method: 'GET', middleware: ['protect-metrics'])]
    public function __invoke(ServerRequestInterface $request): JsonResponse
    {
        // !!! CẢNH BÁO BẢO MẬT !!!
        // Điểm cuối này tiết lộ thông tin nội bộ của ứng dụng.
        // Trong môi trường production, bạn PHẢI bảo vệ nó bằng middleware,
        // ví dụ: chỉ cho phép truy cập từ IP nội bộ hoặc yêu cầu một secret token.
        // if ($request->getServerParams()['REMOTE_ADDR'] !== '127.0.0.1') {
        //     return new JsonResponse(['error' => 'Forbidden'], 403);
        // }

        $metrics = [
            'swoole_server' => app(\Core\Server\SwooleServer::class)->stats(),
            'pdo_pool' => SwoolePdoPool::isInitialized() ? SwoolePdoPool::stats() : 'disabled',
            'redis_pool' => SwooleRedisPool::isInitialized() ? SwooleRedisPool::stats() : 'disabled',
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'memory_peak_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
        ];

        return new JsonResponse($metrics);
    }
}
