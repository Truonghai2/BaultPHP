<?php

namespace Http\Controllers\Debug;

use Core\Http\Controller;
use Core\Redis\RedisManager;
use Core\Routing\Attributes\Route;
use Psr\Http\Message\ResponseInterface;

class DebugController extends Controller
{
    private RedisManager $redis;

    public function __construct(RedisManager $redis)
    {
        $this->redis = $redis;
    }

    #[Route('/_debug/{id}', method: 'GET')]
    public function show(string $id): ResponseInterface
    {
        if (!config('debug.enabled', false)) {
            return response()->json(['error' => 'Debugging is disabled.'], 404);
        }

        $key = 'debug:requests:' . $id;
        $jsonData = $this->redis->get($key);

        if (!$jsonData) {
            return response()->json(['error' => 'Debug data not found or expired.'], 404);
        }

        return response($jsonData)
            ->withHeader('Content-Type', 'application/json');
    }
}
