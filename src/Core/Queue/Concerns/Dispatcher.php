<?php

namespace Core\Queue\Concerns;

use Core\Contracts\Queue\Job;

/**
 * Trait to allow a class to dispatch jobs.
 */
trait Dispatcher
{
    /**
     * Dispatch a job to its appropriate handler.
     *
     * @param Job $job
     */
    public function dispatch(Job $job): void
    {
        app('queue')->push($job);
    }
}
