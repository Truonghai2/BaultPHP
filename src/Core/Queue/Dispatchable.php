<?php

namespace Core\Queue;

trait Dispatchable
{
    /**
     * Dispatch the job with the given arguments.
     *
     * @return void
     */
    public static function dispatch(...$args): void
    {
        /** @var \Core\Queue\QueueManager $queue */
        $queue = app(\Core\Queue\QueueManager::class);
        $jobInstance = new static(...$args);

        $queue->push($jobInstance);
    }
}
