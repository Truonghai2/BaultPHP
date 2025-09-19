<?php

namespace Core\Queue\Jobs;

use Core\Application;
use Core\Contracts\Queue\Job;
use Core\Contracts\Task\Task;
use Core\Database\Fiber\FiberConnectionManager;
use Core\Queue\QueueWorker;
use Core\Redis\FiberRedisManager;

/**
 * A specific Task for processing a queue Job.
 * This class acts as a wrapper that is sent to the Swoole task worker.
 */
class ProcessJobTask implements Task
{
    public function __construct(public Job $job)
    {
    }

    /**
     * The logic executed by the Swoole Task Worker.
     * It wraps the job's handle method with a try/finally block
     * to ensure any leaked connections are released.
     */
    public function handle(): void
    {
        $app = Application::getInstance();
        /** @var QueueWorker $worker */
        $worker = $app->make(QueueWorker::class);

        try {
            $worker->process('swoole', $this->job);
        } finally {
            if ($app->bound(FiberRedisManager::class)) {
                $app->make(FiberRedisManager::class)->releaseUnmanaged();
            }
            if ($app->bound(FiberConnectionManager::class)) {
                $app->make(FiberConnectionManager::class)->releaseUnmanaged();
            }
        }
    }
}
