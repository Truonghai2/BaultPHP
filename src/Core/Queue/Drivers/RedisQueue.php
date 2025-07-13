<?php

namespace Core\Queue\Drivers;

use Core\Application;
use Core\Cache\CacheManager;
use Core\Contracts\Queue\Job;

class RedisQueue extends Queue
{
    protected \Redis $redis;
    protected string $defaultQueue;

    public function __construct(Application $app, array $config)
    {
        // Giả định CacheManager quản lý kết nối Redis
        /** @var CacheManager $cacheManager */
        $cacheManager = $app->make('cache');
        $this->redis = $cacheManager->connection($config['connection'] ?? 'default');
        $this->defaultQueue = $config['queue'] ?? 'default';
    }

    public function push(Job $job, string $queue = null): void
    {
        $queue = $this->getQueue($queue);
        $payload = $this->createPayload($job);

        $this->redis->rPush($queue, $payload);
    }

    public function pop(string $queue = null): ?Job
    {
        $queue = $this->getQueue($queue);

        // BLPOP là lệnh blocking, nó sẽ chờ cho đến khi có job hoặc timeout
        $payload = $this->redis->blPop([$queue], 10);

        if (isset($payload[1])) {
            return unserialize($payload[1]);
        }

        return null;
    }

    protected function createPayload(Job $job): string
    {
        // Serialize the job object to store it in Redis
        return serialize($job);
    }

    protected function getQueue(?string $queue): string
    {
        return 'queues:' . ($queue ?? $this->defaultQueue);
    }
}