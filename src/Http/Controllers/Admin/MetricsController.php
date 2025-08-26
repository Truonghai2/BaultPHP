<?php

namespace Http\Controllers\Admin;

use Core\Database\Swoole\SwoolePdoPool;
use Core\Database\Swoole\SwooleRedisPool;
use Core\Routing\Attributes\Route;
use Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MetricsController
{
    #[Route('/_metrics', method: 'GET', middleware: ['protect-metrics'])]
    public function __invoke(ServerRequestInterface $request, ResponseFactory $responseFactory): ResponseInterface
    {
        $metrics = [
            'swoole_server' => app(\Core\Server\SwooleServer::class)->stats(),
            'pdo_pool' => SwoolePdoPool::isInitialized() ? SwoolePdoPool::stats() : [],
            'redis_pool' => SwooleRedisPool::isInitialized() ? SwooleRedisPool::stats() : [],
            'memory_usage' => memory_get_usage(true),
            'memory_peak_usage' => memory_get_peak_usage(true),
        ];

        $output = '';

        $output .= "# HELP memory_usage_bytes Memory usage of the application.\n";
        $output .= "# TYPE memory_usage_bytes gauge\n";
        $output .= 'memory_usage_bytes ' . $metrics['memory_usage'] . "\n";

        $output .= "# HELP memory_peak_usage_bytes Peak memory usage of the application.\n";
        $output .= "# TYPE memory_peak_usage_bytes gauge\n";
        $output .= 'memory_peak_usage_bytes ' . $metrics['memory_peak_usage'] . "\n";

        if (is_array($metrics['swoole_server'])) {
            foreach ($metrics['swoole_server'] as $key => $value) {
                $key = str_replace(['.', '-'], '_', $key);
                $output .= "# HELP swoole_server_{$key} Swoole server stat {$key}.\n";
                $output .= "# TYPE swoole_server_{$key} gauge\n";
                $output .= "swoole_server_{$key} " . $value . "\n";
            }
        }

        if (is_array($metrics['pdo_pool'])) {
            foreach ($metrics['pdo_pool'] as $key => $value) {
                $key = str_replace(['.', '-'], '_', $key);
                $output .= "# HELP pdo_pool_{$key} PDO pool stat {$key}.\n";
                $output .= "# TYPE pdo_pool_{$key} gauge\n";
                $output .= "pdo_pool_{$key} " . $value . "\n";
            }
        }

        if (is_array($metrics['redis_pool'])) {
            foreach ($metrics['redis_pool'] as $key => $value) {
                $key = str_replace(['.', '-'], '_', $key);
                $output .= "# HELP redis_pool_{$key} Redis pool stat {$key}.\n";
                $output .= "# TYPE redis_pool_{$key} gauge\n";
                $output .= "redis_pool_{$key} " . $value . "\n";
            }
        }

        return $responseFactory->make($output, 200, ['Content-Type' => 'text/plain; version=0.0.4']);
    }
}
