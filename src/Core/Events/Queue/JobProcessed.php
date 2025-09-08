<?php

namespace Core\Events\Queue;

use Core\Contracts\Queue\Job;

class JobProcessed
{
    /**
     * @param string $connectionName
     * @param Job $job
     */
    public function __construct(public string $connectionName, public Job $job)
    {
    }
}
