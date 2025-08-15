<?php

namespace Core\Queue\Drivers;

use Core\Application;
use Core\Contracts\Queue\Job;
use Core\Contracts\Queue\Queue as QueueContract;
use Core\Queue\Jobs\RedisJob;
use DateInterval;
use DateTimeInterface;

class RedisQueueDriver implements QueueContract
{
    protected Application $app;
    /**
     * The Redis connection instance.
     * @var \Predis\ClientInterface
     */
    protected $redis;
    protected array $config;

    public function __construct(Application $app, array $config)
    {
        $this->app = $app;
        $this->config = $config;

        // === ĐÂY LÀ PHẦN SỬA LỖI QUAN TRỌNG NHẤT ===
        // Lấy đúng connection từ RedisManager thay vì inject chính manager.
        // Tên connection được đọc từ file config/queue.php
        $connectionName = $config['connection'] ?? 'default';
        $this->redis = $app->make('redis')->connection($connectionName);
    }

    /**
     * Push a new job onto the queue.
     *
     * @param object $job
     * @param string|null $queue
     */
    public function push(Job $job, ?string $queue = null): void
    {
        $queueName = $this->getQueueName($queue);
        $payload = $this->createPayload($job);

        $this->redis->lpush($queueName, [$payload]);
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param DateTimeInterface|DateInterval|int $delay
     * @param object $job
     * @param string|null $queue
     */
    public function later($delay, Job $job, ?string $queue = null): void
    {
        $delayedQueueName = $this->getDelayedQueueName($queue);
        $executionTimestamp = $this->normalizeDelayToSeconds($delay) + time();
        $payload = $this->createPayload($job);

        $this->redis->zadd($delayedQueueName, [$payload => $executionTimestamp]);
    }

    /**
     * Pop the next job off of the queue.
     */
    public function pop(?string $queue = null): ?Job
    {
        $this->migrateExpiredJobs($queue);

        $queueName = $this->getQueueName($queue);
        $payload = $this->redis->rpop($queueName);

        if ($payload) {
            return new RedisJob($this->app, $this, $payload, $queueName);
        }

        return null;
    }

    /**
     * Migrate any expired jobs from the delayed queue to the primary queue.
     */
    protected function migrateExpiredJobs(?string $queue = null): void
    {
        $delayedQueueName = $this->getDelayedQueueName($queue);
        $queueName = $this->getQueueName($queue);

        // Lấy tất cả các job đã đến hạn (score <= now)
        $jobs = $this->redis->zrangebyscore($delayedQueueName, '-inf', (string) time());

        if (!empty($jobs)) {
            // Xóa chúng khỏi hàng đợi trễ
            if ($this->redis->zremrangebyscore($delayedQueueName, '-inf', (string) time())) {
                // Đẩy chúng vào hàng đợi chính để xử lý
                $this->redis->lpush($queueName, $jobs);
            }
        }
    }

    protected function createPayload(Job $job): string
    {
        return serialize($job);
    }

    protected function getQueueName(?string $queue): string
    {
        return 'queues:' . ($queue ?? $this->config['queue'] ?? 'default');
    }

    protected function getDelayedQueueName(?string $queue): string
    {
        return $this->getQueueName($queue) . ':delayed';
    }

    protected function normalizeDelayToSeconds($delay): int
    {
        if ($delay instanceof DateInterval) {
            return (new \DateTimeImmutable())->add($delay)->getTimestamp() - time();
        }

        if ($delay instanceof DateTimeInterface) {
            return max(0, $delay->getTimestamp() - time());
        }

        return (int) $delay;
    }

    /**
     * Get the underlying Redis instance.
     *
     * @return \Predis\ClientInterface
     */
    public function getRedis()
    {
        return $this->redis;
    }
}
