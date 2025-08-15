<?php

namespace Core\Queue;

use Core\Application;
use Core\Contracts\Queue\Job;
use Core\Queue\Jobs\ProcessJobTask;
use Core\Redis\RedisManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Handles the logic for polling and dispatching delayed jobs from a sorted set in Redis.
 * This class is designed to be run periodically by a Swoole timer.
 */
class DelayedJobScheduler
{
    protected array $config;
    protected string $queueName;
    protected RedisManager $redisManager;

    public function __construct(
        protected Application $app,
        protected LoggerInterface $logger,
    ) {
        $this->config = $this->app->make('config')->get('queue');
        $this->queueName = $this->config['connections']['redis']['delayed_queue'] ?? 'queues:delayed:default';
        $this->redisManager = $this->app->make(RedisManager::class);
    }

    /**
     * This is the main entry point to be called by the timer.
     */
    public function __invoke(): void
    {
        // Because this is a long-running timer, we must get and release the connection
        // for each invocation to ensure we are using the connection pool correctly.
        $redis = $this->redisManager->getFromPool();
        try {
            // Get all jobs with a timestamp <= the current time
            $jobsToProcess = $redis->zrangebyscore($this->queueName, '-inf', time());

            if (empty($jobsToProcess)) {
                // No jobs to process, just return. The `finally` block will release the connection.
                return;
            }

            // Atomically remove the jobs we just fetched to prevent race conditions
            $redis->zremrangebyscore($this->queueName, '-inf', time());

            $this->logger->info(sprintf('Found %d delayed jobs to process.', count($jobsToProcess)));

            foreach ($jobsToProcess as $serializedJob) {
                $job = unserialize($serializedJob);
                if ($job instanceof Job) {
                    // Dispatch the job to a task worker for execution
                    /** @var \Core\Server\SwooleServer $server */
                    $server = $this->app->make(\Core\Server\SwooleServer::class);
                    $server->dispatchTask(new ProcessJobTask($job));
                }
            }
        } catch (Throwable $e) {
            $this->logger->error('Delayed job scheduler error: ' . $e->getMessage(), ['exception' => $e]);
        } finally {
            $this->redisManager->putToPool($redis);
        }
    }
}
