<?php

namespace Core\Queue;

use Core\Application;
use Core\Contracts\Queue\Job;
use Core\Contracts\Queue\Queue;
use Core\Queue\Jobs\ProcessJobTask;
use Core\Redis\RedisManager;
use Core\Server\SwooleServer;
use DateInterval;
use DateTimeInterface;
use RuntimeException;

class SwooleQueue implements Queue
{
    protected SwooleServer $server;
    protected RedisManager $redisManager;

    public function __construct(protected Application $app)
    {
        if (!$app->bound(SwooleServer::class)) {
            throw new RuntimeException('The Swoole queue driver can only be used when running within the Swoole server.');
        }
        $this->server = $this->app->make(SwooleServer::class);
        $this->redisManager = $this->app->make(RedisManager::class);
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  Job  $job
     * @param  string|null  $queue This is not supported by Swoole tasks and will be ignored.
     * @return void
     */
    public function push(Job $job, ?string $queue = null): void
    {
        $this->server->dispatchTask(new ProcessJobTask($job));
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  DateTimeInterface|DateInterval|int  $delay
     * @param  Job  $job
     * @param  string|null  $queue
     * @return void
     */
    public function later($delay, Job $job, ?string $queue = null): void
    {
        $executionTimestamp = $this->normalizeDelayToSeconds($delay) + time();
        $queueName = $this->getQueueName($queue);
        $serializedJob = serialize($job);

        // The RedisManager's __call magic method is now Swoole-aware.
        // It will automatically get a connection from the pool, execute the command,
        // and put the connection back, making this operation safe.
        $this->redisManager->zadd($queueName, [$serializedJob => $executionTimestamp]);
    }

    /**
     * Pop the next job off of the queue.
     * This is not applicable for the Swoole driver, as tasks are pushed to workers.
     */
    public function pop(?string $queue = null): ?Job
    {
        // Không được hỗ trợ/không áp dụng cho hàng đợi dựa trên task của Swoole.
        return null;
    }

    /**
     * Get the queue or return the default.
     *
     * @param  string|null  $queue
     * @return string
     */
    protected function getQueueName(?string $queue): string
    {
        return 'queues:delayed:' . ($queue ?? 'default');
    }

    /**
     * Normalize the delay value into seconds.
     *
     * @param  DateTimeInterface|DateInterval|int  $delay
     * @return int
     */
    protected function normalizeDelayToSeconds($delay): int
    {
        if ($delay instanceof DateInterval) {
            $delay = (new \DateTime())->add($delay)->getTimestamp() - time();
        }

        if ($delay instanceof DateTimeInterface) {
            return max(0, $delay->getTimestamp() - time());
        }

        return (int) $delay;
    }
}
