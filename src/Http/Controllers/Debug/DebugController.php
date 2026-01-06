<?php

namespace App\Http\Controllers\Debug;

use Core\Database\Swoole\SwooleRedisPool;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Psr\Http\Message\ResponseInterface;

class DebugController extends Controller
{
    #[Route('/_debug/{id}', method: 'GET', name: 'debug.show')]
    public function show(string $id): ResponseInterface
    {
        if (!config('debug.enabled', false)) {
            return response()->json(['error' => 'Debug mode is disabled.'], 404);
        }

        if (!SwooleRedisPool::isInitialized('default')) {
            return response()->json(['error' => 'Redis storage is not available. Debug data cannot be retrieved.'], 503);
        }

        try {
            $redis = SwooleRedisPool::get('default');

            try {
                $key = 'debug:' . $id;
                $data = $redis->get($key);
            } finally {
                SwooleRedisPool::put($redis);
            }

            if (!$data) {
                return response()->json(['error' => 'Debug data not found or expired.'], 404);
            }

            return response()->make($data, 200, ['Content-Type' => 'application/json']);
        } catch (\Throwable $e) {
            app('log')->error('Failed to fetch debug data: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Failed to fetch debug data from storage.'], 500);
        }
    }
}
