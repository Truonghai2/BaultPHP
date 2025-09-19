<?php

namespace Core\Tasking;

use Core\Contracts\Task\Task;
use Core\Database\Swoole\SwooleRedisPool;
use Psr\Log\LoggerInterface;
use Throwable;

class CacheDebugDataTask implements Task
{
    public function __construct(
        private string $requestId,
        private array $debugData,
        private int $expiration,
    ) {
    }

    public function handle(): void
    {
        $redisClient = null;
        try {
            $redisClient = SwooleRedisPool::get('default');
            $key = 'debug:requests:' . $this->requestId;
            $redisClient->set($key, json_encode($this->debugData), ['ex' => $this->expiration]);
        } catch (Throwable $e) {
            app(LoggerInterface::class)->error('Failed to save debug data to Redis in task.', ['exception' => $e]);
        } finally {
            if ($redisClient) {
                SwooleRedisPool::put($redisClient, 'default');
            }
        }
    }
}
