<?php

namespace Core\Queue\Drivers;

use Core\Application;
use Core\Contracts\Queue\Job;
use Core\Contracts\Queue\Queue;
use Throwable;

class SyncQueue implements Queue
{
    public function __construct(protected Application $app)
    {
    }

    public function push(Job $job, ?string $queue = null): void
    {
        try {
            $job->handle();
        } catch (Throwable $e) {
            // In a real sync driver, you might want to log this failure.
            throw $e;
        }
    }

    public function later($delay, Job $job, ?string $queue = null): void
    {
        $this->push($job, $queue);
    }

    public function pop(?string $queue = null): ?Job
    {
        return null;
    }
}
