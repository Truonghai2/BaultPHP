<?php

namespace Http\Controllers;

use Core\Application;
use Core\Http\Controller;
use Core\Support\Benchmark;
use Http\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class PerformanceTestController extends Controller
{
    public function __construct(protected Application $app)
    {
    }

    public function testDb(ServerRequestInterface $request): JsonResponse
    {
        $queryParams = $request->getQueryParams();
        $iterations = (int)($queryParams['iterations'] ?? 1000);
        $driver = config('database.default');

        if ($iterations > 10000) {
            $iterations = 10000; // Giới hạn số lần lặp để tránh lạm dụng
        }

        $results = [];
        $error = null;

        try {
            Benchmark::start('db_query_test');
            $db = $this->app->make(\Core\ORM\Connection::class);

            for ($i = 0; $i < $iterations; $i++) {
                $db->connection()->query('SELECT 1');
            }
            $results = Benchmark::stop('db_query_test');
        } catch (Throwable $e) {
            $error = 'Kiểm tra CSDL thất bại: ' . $e->getMessage();
        }

        if ($error) {
            return response()->json([
                'status' => 'error',
                'message' => $error,
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'test' => 'Hiệu năng truy vấn CSDL',
            'driver' => $driver,
            'iterations' => $iterations,
            'total_time_ms' => number_format($results['time'], 2),
            'avg_time_ms' => number_format($results['time'] / $iterations, 4),
            'memory_usage' => Benchmark::formatBytes($results['memory']),
            'peak_memory_usage' => Benchmark::formatBytes($results['memory_peak']),
        ]);
    }
}
