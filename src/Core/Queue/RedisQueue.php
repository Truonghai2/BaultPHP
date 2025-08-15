<?php

namespace Core\Queue;

use Core\Contracts\Queue\Job;
use Core\Contracts\Queue\Queue;
use Predis\ClientInterface as RedisClient;

class RedisQueue implements Queue
{
    protected string $defaultQueue;

    public function __construct(protected RedisClient $redis, string $defaultQueue = 'default')
    {
        $this->defaultQueue = $defaultQueue;
    }

    public function push(Job $job, ?string $queue = null): void
    {
        $payload = $this->createPayload($job);
        $this->redis->rPush($this->getQueue($queue), $payload);
    }

    public function later($delay, Job $job, ?string $queue = null): void
    {
        // Redis không hỗ trợ 'later' một cách tự nhiên.
        // Cần một logic phức tạp hơn với sorted sets. Tạm thời push ngay.
        $this->push($job, $queue);
    }

    public function pop(?string $queue = null): ?Job
    {
        $payload = $this->redis->lPop($this->getQueue($queue));
        return $payload ? unserialize($payload) : null;
    }

    protected function createPayload(Job $job): string
    {
        return serialize($job);
    }

    protected function getQueue(?string $queue): string
    {
        return 'queues:' . ($queue ?: $this->defaultQueue);
    }
}
