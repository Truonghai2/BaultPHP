<?php

namespace Core\Queue;

use Core\Contracts\Queue\Job;

/**
 * Trait to allow a class to dispatch jobs.
 */
trait DispatchesJobs
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