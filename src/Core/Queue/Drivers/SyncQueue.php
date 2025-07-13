<?php

namespace Core\Queue\Drivers;

use Core\Application;
use Core\Contracts\Queue\Job;

class SyncQueue extends Queue
{
    public function __construct(protected Application $app) {}

    public function push(Job $job, string $queue = null): void
    {
        // For the sync driver, we resolve and handle the job immediately.
        $this->app->make(get_class($job))->handle();
    }

    public function pop(string $queue = null): ?Job
    {
        // The sync driver doesn't actually queue anything, so pop does nothing.
        return null;
    }
}