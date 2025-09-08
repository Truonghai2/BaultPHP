<?php

namespace Core\Events\Queue;

use Core\Contracts\Queue\Job;
use Throwable;

class JobFailed
{
    /**
     * @param string $connectionName
     * @param Job $job
     * @param Throwable $exception
     */
    public function __construct(public string $connectionName, public Job $job, public Throwable $exception)
    {
    }
}
