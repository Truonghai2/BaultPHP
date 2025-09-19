<?php

namespace Core\Queue;

use Core\Application;
use Core\Contracts\Queue\Job;
use Core\Contracts\Queue\Queue;
use Core\Queue\Jobs\ProcessJobTask;
use Core\Redis\FiberRedisManager;
use Core\Server\SwooleServer;
use DateInterval;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SwooleQueue implements Queue
{
    protected SwooleServer $server;
    protected FiberRedisManager $redisManager;

    public function __construct(protected Application $app)
    {
        if (!$app->bound(SwooleServer::class)) {
            throw new RuntimeException('The Swoole queue driver can only be used when running within the Swoole server.');
        }
        $this->server = $this->app->make(SwooleServer::class);
        $this->redisManager = $this->app->make(FiberRedisManager::class);
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

        $redis = null;
        try {
            $redis = $this->redisManager->get();
            $redis->zAdd($queueName, $executionTimestamp, $serializedJob)->await();
        } catch (\Throwable $e) {
            $this->app->make(LoggerInterface::class)->error(
                'Failed to add a delayed job to the queue.',
                ['exception' => $e, 'job' => get_class($job)],
            );
        } finally {
            if ($redis) {
                $this->redisManager->put($redis);
            }
        }
    }

    /**
     * Pop the next job off of the queue.
     * This is not applicable for the Swoole driver, as tasks are pushed to workers.
     */
    public function pop(?string $queue = null): ?Job
    {
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
