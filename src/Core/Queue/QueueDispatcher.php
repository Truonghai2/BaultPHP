<?php

namespace Core\Queue;

use Core\Contracts\Queue\Dispatcher;

class QueueDispatcher implements Dispatcher
{
    public function __construct(protected QueueManager $queueManager)
    {
    }

    /**
     * Dispatch a job to the queue.
     * Accepts both Core\Queue\Job and Core\Contracts\Queue\Job
     *
     * @param mixed $job
     * @return void
     */
    public function dispatch($job): void
    {
        // Get queue connection from app and push job
        $queue = app('queue');

        if (method_exists($queue, 'push')) {
            $queue->push($job);
        } else {
            // Fallback: assume $job is Core\Queue\Job with handle method
            if (method_exists($job, 'handle')) {
                // For sync queue or fallback, just execute immediately
                app()->call([$job, 'handle']);
            }
        }
    }
}
