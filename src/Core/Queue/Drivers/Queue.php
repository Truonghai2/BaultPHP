<?php

namespace Core\Queue\Drivers;

use Core\Contracts\Queue\Job;

abstract class Queue
{
    protected string $connectionName;

    abstract public function push(Job $job, string $queue = null): void;

    abstract public function pop(string $queue = null): ?Job;

    public function setConnectionName(string $name): void
    {
        $this->connectionName = $name;
    }
}
