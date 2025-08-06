<?php

namespace Core\Queue\Jobs;

use Core\Contracts\Queue\Job;
use Core\Contracts\Task\Task;

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
     * It simply calls the handle method on the encapsulated job.
     */
    public function handle(): void
    {
        $this->job->handle();
    }
}
