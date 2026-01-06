<?php

namespace Core\Contracts\Queue;

interface Dispatcher
{
    /**
     * Dispatch a job to the queue.
     *
     * @param mixed $job Job instance (can be Core\Queue\Job or Core\Contracts\Queue\Job)
     * @return void
     */
    public function dispatch($job): void;
}

